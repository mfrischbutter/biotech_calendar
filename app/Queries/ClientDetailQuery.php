<?php

namespace App\Queries;

use App\Models\Appointment;
use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Everything the client detail page shows besides the client's own columns:
 * the identity facts in the header, the visit lists, and the timeline.
 *
 * The controller stays "authorize → delegate → render"; the derivations
 * (badges, tenure, "is this customer dormant") live here where they can be
 * tested without an HTTP round trip.
 */
class ClientDetailQuery
{
    /** How many past/upcoming visits the tabs list. */
    public const APPOINTMENT_LIMIT = 20;

    public function __construct(
        private RecordFacts $facts,
        private RecordTimeline $timeline,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forClient(Client $client, bool $canSeeAppointments): array
    {
        $now = Carbon::now();
        $contractIds = $client->contracts->pluck('id')->all();
        $openContracts = $this->openContractCount($client);

        if (! $canSeeAppointments) {
            return [
                'upcomingAppointments' => collect(),
                'pastAppointments' => collect(),
                'timeline' => [],
                'facts' => $this->facts($client, $now, $openContracts, [
                    'next' => null,
                    'series' => null,
                    'total' => 0,
                    'upcoming' => 0,
                    'last' => null,
                    'appointments_visible' => false,
                ]),
            ];
        }

        $metrics = [
            'next' => $this->facts->nextAppointment($contractIds, $now),
            'series' => $this->facts->series($contractIds),
            'total' => $contractIds === [] ? 0 : Appointment::whereIn('contract_id', $contractIds)->count(),
            'upcoming' => $contractIds === [] ? 0 : Appointment::whereIn('contract_id', $contractIds)
                ->where('start_at', '>=', $now)->count(),
            'last' => $contractIds === [] ? null : Appointment::whereIn('contract_id', $contractIds)
                ->where('start_at', '<', $now)->orderByDesc('start_at')->value('start_at'),
            'appointments_visible' => true,
        ];

        return [
            'upcomingAppointments' => $this->appointments($contractIds, $now, upcoming: true),
            'pastAppointments' => $this->appointments($contractIds, $now, upcoming: false),
            'timeline' => $this->timeline->forContracts($contractIds),
            'facts' => $this->facts($client, $now, $openContracts, $metrics),
        ];
    }

    /**
     * @param  array<int, int>  $contractIds
     * @return Collection<int, Appointment>
     */
    private function appointments(array $contractIds, Carbon $now, bool $upcoming): Collection
    {
        if ($contractIds === []) {
            return collect();
        }

        return Appointment::query()
            ->whereIn('contract_id', $contractIds)
            ->where('start_at', $upcoming ? '>=' : '<', $now)
            ->with(['contract:id,contract_number,title,kind', 'workers:id,first_name,last_name', 'status:id,name,color'])
            ->orderBy('start_at', $upcoming ? 'asc' : 'desc')
            ->limit(self::APPOINTMENT_LIMIT)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array<string, mixed>
     */
    private function facts(Client $client, Carbon $now, int $openContracts, array $metrics): array
    {
        $address = $this->facts->address($client->street, $client->zip, $client->city);
        $last = $metrics['last'] ? Carbon::parse($metrics['last']) : null;

        return [
            'since' => $client->created_at?->toISOString(),
            'address' => $address,
            'map_url' => $this->facts->mapUrl($address),
            'access_notes' => $client->access_notes,
            'badges' => $this->badges($client, $now, $openContracts, $metrics, $last),
            'stats' => [
                'contracts' => $client->contracts->count(),
                'open_contracts' => $openContracts,
                'appointments' => (int) $metrics['total'],
                'upcoming' => (int) $metrics['upcoming'],
                'last' => $last?->toISOString(),
                'next' => $metrics['next']['start_at'] ?? null,
            ],
            'next_appointment' => $metrics['next'],
            'series' => $metrics['series'],
        ];
    }

    /**
     * The two or three things worth saying about a customer at a glance.
     *
     * @param  array<string, mixed>  $metrics
     * @return array<int, array{key: string, tone: string}>
     */
    private function badges(Client $client, Carbon $now, int $openContracts, array $metrics, ?Carbon $last): array
    {
        $cutoff = $now->copy()->subDays(ClientListQuery::DORMANT_DAYS);
        $badges = [];

        if ($openContracts > 0) {
            $badges[] = ['key' => 'active_contract', 'tone' => 'success'];
        }

        if ($client->created_at && $client->created_at->greaterThanOrEqualTo($cutoff)) {
            $badges[] = ['key' => 'new_client', 'tone' => 'navy'];
        }

        $dormant = $metrics['appointments_visible']
            && $client->contracts->isNotEmpty()
            && $metrics['next'] === null
            && ($last === null || $last->lessThan($cutoff));

        if ($dormant) {
            $badges[] = ['key' => 'dormant', 'tone' => 'warning'];
        }

        foreach ($client->contracts->pluck('kind')->filter()->unique()->sort()->values() as $kind) {
            $badges[] = ['key' => $kind, 'tone' => 'muted'];
        }

        return $badges;
    }

    /** Contracts that still have work on them — the list view's definition. */
    private function openContractCount(Client $client): int
    {
        return $client->contracts()
            ->where(fn (Builder $query) => ClientListQuery::openContracts($query))
            ->count();
    }
}
