<?php

namespace App\Queries;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Status;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * The client list: search, saved views and server-side sorting.
 *
 * Lives outside the controller so the controller stays "validate → delegate →
 * respond", and so the list stays a single query no matter how many rows are
 * on the page (counts and the "next appointment" date are subqueries).
 */
class ClientListQuery
{
    public const VIEW_ALL = 'all';

    public const VIEW_ACTIVE_CONTRACTS = 'active_contracts';

    public const VIEW_DORMANT = 'dormant';

    public const VIEWS = [self::VIEW_ALL, self::VIEW_ACTIVE_CONTRACTS, self::VIEW_DORMANT];

    /** A client counts as dormant after this many days without any appointment. */
    public const DORMANT_DAYS = 90;

    /** Stages that mean the job is off the table — they never count as "open". */
    public const CLOSED_STAGES = [...Status::COMPLETED_STAGES, Status::STAGE_CANCELLED];

    /** Sort key accepted from the URL → the column it maps to. */
    public const SORTS = [
        'name' => 'last_name',
        'city' => 'city',
        'open_contracts' => 'open_contracts_count',
        'next_appointment' => 'next_appointment_at',
    ];

    public const DEFAULT_SORT = 'name';

    /**
     * Validation rules for the query string. Applied by the controller.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', Rule::in(array_keys(self::SORTS))],
            'dir' => ['nullable', 'string', 'in:asc,desc'],
            'view' => ['nullable', 'string', Rule::in(self::VIEWS)],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $now = Carbon::now();

        $query = Client::query()
            ->select('clients.*')
            ->addSelect(['next_appointment_at' => Appointment::query()
                ->select('appointments.start_at')
                ->join('contract_client', 'contract_client.contract_id', '=', 'appointments.contract_id')
                ->whereColumn('contract_client.client_id', 'clients.id')
                ->where('appointments.start_at', '>=', $now)
                ->orderBy('appointments.start_at')
                ->limit(1),
            ])
            ->withCount(['contracts as open_contracts_count' => fn (Builder $q) => self::openContracts($q)]);

        $this->applySearch($query, $filters['search'] ?? null);
        $this->applyView($query, $filters['view'] ?? self::VIEW_ALL, $now);
        $this->applySort($query, $filters['sort'] ?? null, $filters['dir'] ?? null);

        $paginated = $query->paginate($perPage)->withQueryString();

        $paginated->getCollection()->transform(fn (Client $client) => $this->present($client));

        return $paginated;
    }

    /**
     * Payload for one row. Deliberately explicit: the list and the side peek
     * read exactly these fields, so the shape is easy to snapshot.
     *
     * @return array<string, mixed>
     */
    private function present(Client $client): array
    {
        return [
            'id' => $client->id,
            'name' => $client->name,
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'salutation' => $client->salutation,
            'company_name' => $client->company_name,
            'billing_name' => $client->billing_name,
            'street' => $client->street,
            'zip' => $client->zip,
            'city' => $client->city,
            'phone' => $client->phone,
            'email' => $client->email,
            'open_contracts' => (int) ($client->open_contracts_count ?? 0),
            'next_appointment' => $client->next_appointment_at
                ? Carbon::parse($client->next_appointment_at)->toISOString()
                : null,
        ];
    }

    private function applySearch(Builder $query, ?string $search): void
    {
        if (! $search) {
            return;
        }

        $query->where(function (Builder $q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('company_name', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        });
    }

    private function applyView(Builder $query, string $view, Carbon $now): void
    {
        if ($view === self::VIEW_ACTIVE_CONTRACTS) {
            $query->whereHas('contracts', fn (Builder $q) => self::openContracts($q));

            return;
        }

        if ($view === self::VIEW_DORMANT) {
            $cutoff = $now->copy()->subDays(self::DORMANT_DAYS);
            $query->whereDoesntHave(
                'contracts.appointments',
                fn (Builder $q) => $q->where('appointments.start_at', '>=', $cutoff)
            );
        }
    }

    private function applySort(Builder $query, ?string $sort, ?string $dir): void
    {
        $sort = $sort && isset(self::SORTS[$sort]) ? $sort : self::DEFAULT_SORT;
        $direction = $dir === 'desc' ? 'desc' : 'asc';

        $query->orderBy(self::SORTS[$sort], $direction);

        if ($sort === 'name') {
            $query->orderBy('first_name', $direction);
        }

        // Stable tiebreak so pagination never repeats or drops a row.
        $query->orderBy('clients.id');
    }

    /**
     * A contract is "open" while it still has work on it: no appointments yet,
     * or at least one appointment that is neither invoiceable nor cancelled.
     */
    public static function openContracts(Builder $query): Builder
    {
        return $query->where(function (Builder $contract) {
            $contract->whereDoesntHave('appointments')
                ->orWhereHas('appointments', function (Builder $appointment) {
                    $appointment->whereNull('appointments.status_id')
                        ->orWhereHas(
                            'status',
                            fn (Builder $status) => $status->whereNotIn('stage', self::CLOSED_STAGES)
                        );
                });
        });
    }
}
