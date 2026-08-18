<?php

namespace App\Queries;

use App\Models\Appointment;
use App\Models\Contract;
use App\Models\Status;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * The contract list: search, one saved view per pipeline stage, and a real
 * default sort (contract number ascending — the list used to be in insertion
 * order, which is no order at all once there are two pages of it).
 *
 * Progress comes from `Contract::scopeWithProgressCounts()`, and the stage
 * counts are subqueries, so the row count never changes the query count.
 */
class ContractListQuery
{
    public const VIEW_ALL = 'all';

    /**
     * Not a stage but a saved view: jobs with a visit that is over and still
     * unsettled. It is the one exception the dashboard can hand somebody, and a
     * calendar week is the wrong container for a three-month backlog.
     */
    public const VIEW_OVERDUE = 'overdue';

    /** Sort key accepted from the URL → the column it maps to. */
    public const SORTS = [
        'contract_number' => 'contract_number',
        'title' => 'title',
        'city' => 'city',
        'appointments' => 'appointments_count',
        'created_at' => 'created_at',
    ];

    public const DEFAULT_SORT = 'contract_number';

    /**
     * The tabs on the list: "all", the pipeline in order, then the exception.
     *
     * @return array<int, string>
     */
    public static function views(): array
    {
        return [self::VIEW_ALL, ...Status::PIPELINE_STAGES, self::VIEW_OVERDUE];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', Rule::in(array_keys(self::SORTS))],
            'dir' => ['nullable', 'string', 'in:asc,desc'],
            'view' => ['nullable', 'string', Rule::in(self::views())],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = Contract::query()
            ->with('clients:id,first_name,last_name,company_name')
            ->withProgressCounts()
            ->withCount(self::stageCountSelects());

        $this->applySearch($query, $filters['search'] ?? null);
        $this->applyView($query, $filters['view'] ?? self::VIEW_ALL);
        $this->applySort($query, $filters['sort'] ?? null, $filters['dir'] ?? null);

        $paginated = $query->paginate($perPage)->withQueryString();

        $teams = $this->teamsFor($paginated->getCollection()->pluck('id')->all());

        $paginated->getCollection()->transform(
            fn (Contract $contract) => $this->present($contract, $teams[$contract->id] ?? [])
        );

        return $paginated;
    }

    /**
     * How many contracts sit in each stage tab, for the tab badges.
     * Search is applied so the badges match what the tab would actually show.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array{stage: string, count: int}>
     */
    public function stageCounts(array $filters): array
    {
        $counts = [];

        foreach (self::views() as $view) {
            $query = Contract::query();
            $this->applySearch($query, $filters['search'] ?? null);
            $this->applyView($query, $view);

            $counts[] = ['stage' => $view, 'count' => $query->count()];
        }

        return $counts;
    }

    /**
     * One `appointments as <stage>_stage_count` subquery per pipeline stage.
     *
     * @return array<string, callable>
     */
    public static function stageCountSelects(): array
    {
        $selects = [];

        foreach (Status::PIPELINE_STAGES as $stage) {
            $selects["appointments as {$stage}_stage_count"] = fn (Builder $q) => $q->whereHas(
                'status',
                fn (Builder $s) => $s->where('stage', $stage)
            );
        }

        return $selects;
    }

    /**
     * The people actually booked on a contract. One extra query for the whole
     * page, never one per row.
     *
     * @param  array<int, int>  $contractIds
     * @return array<int, array<int, array{id: int, name: string}>>
     */
    private function teamsFor(array $contractIds): array
    {
        if ($contractIds === []) {
            return [];
        }

        return Appointment::query()
            ->whereIn('contract_id', $contractIds)
            ->with('workers:id,first_name,last_name')
            ->get(['id', 'contract_id'])
            ->groupBy('contract_id')
            ->map(fn (Collection $appointments) => $appointments
                ->flatMap->workers
                ->unique('id')
                ->sortBy('name')
                ->values()
                ->map(fn ($worker) => ['id' => $worker->id, 'name' => $worker->name])
                ->all()
            )
            ->all();
    }

    /**
     * @param  array<int, array{id: int, name: string}>  $team
     * @return array<string, mixed>
     */
    private function present(Contract $contract, array $team): array
    {
        return [
            'id' => $contract->id,
            'contract_number' => $contract->contract_number,
            'title' => $contract->title,
            'kind' => $contract->kind,
            'description' => $contract->description,
            'street' => $contract->street,
            'zip' => $contract->zip,
            'city' => $contract->city,
            'clients' => $contract->clients->map(fn ($client) => [
                'id' => $client->id,
                'name' => $client->name,
                'company_name' => $client->company_name,
            ])->all(),
            'team' => $team,
            'stage' => self::currentStage($contract),
            'progress' => $contract->progress(),
        ];
    }

    /**
     * The stage a contract is *in*: the earliest pipeline stage it still has an
     * appointment sitting in. A job with one unconfirmed visit left is not
     * "invoiced", however many invoiced visits came before it.
     */
    public static function currentStage(Contract $contract): ?string
    {
        foreach (Status::PIPELINE_STAGES as $stage) {
            if ((int) ($contract->{"{$stage}_stage_count"} ?? 0) > 0) {
                return $stage;
            }
        }

        return null;
    }

    private function applySearch(Builder $query, ?string $search): void
    {
        if (! $search) {
            return;
        }

        $query->where(function (Builder $q) use ($search) {
            $q->where('contract_number', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")
                ->orWhereHas('clients', function (Builder $c) use ($search) {
                    $c->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%");
                });
        });
    }

    private function applyView(Builder $query, string $view): void
    {
        if ($view === self::VIEW_OVERDUE) {
            // One contract, however many of its visits are hanging: the tab
            // counts jobs to chase, not appointments.
            $query->whereHas('appointments', fn (Builder $q) => $q->overdue());

            return;
        }

        if ($view === self::VIEW_ALL || ! in_array($view, Status::PIPELINE_STAGES, true)) {
            return;
        }

        $query->whereHas(
            'appointments',
            fn (Builder $q) => $q->whereHas('status', fn (Builder $s) => $s->where('stage', $view))
        );
    }

    private function applySort(Builder $query, ?string $sort, ?string $dir): void
    {
        $sort = $sort && isset(self::SORTS[$sort]) ? $sort : self::DEFAULT_SORT;
        $direction = $dir === 'desc' ? 'desc' : 'asc';

        $query->orderBy(self::SORTS[$sort], $direction)->orderBy('contracts.id');
    }
}
