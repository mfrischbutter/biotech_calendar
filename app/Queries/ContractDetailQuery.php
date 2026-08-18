<?php

namespace App\Queries;

use App\Models\Appointment;
use App\Models\Contract;
use App\Models\Status;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The contract detail page's derived half: which stage the job is in, how far
 * along it is, who is actually on it, and the same timeline the client page
 * shows — scoped to this one contract.
 */
class ContractDetailQuery
{
    public const APPOINTMENT_LIMIT = 20;

    public function __construct(
        private RecordFacts $facts,
        private RecordTimeline $timeline,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forContract(Contract $contract, bool $canSeeAppointments): array
    {
        $now = Carbon::now();
        $ids = [$contract->id];

        if (! $canSeeAppointments) {
            return [
                'upcomingAppointments' => collect(),
                'pastAppointments' => collect(),
                'timeline' => [],
                'facts' => $this->facts($contract, [
                    'next' => null,
                    'series' => null,
                    'total' => 0,
                    'upcoming' => 0,
                    'last' => null,
                    'team' => [],
                    'unassigned' => 0,
                ]),
            ];
        }

        // One count query covers the progress bar and the stage pill.
        $contract->loadCount([
            'appointments',
            'appointments as completed_appointments_count' => fn (Builder $query) => $query->whereHas(
                'status',
                fn (Builder $status) => $status->whereIn('stage', Status::COMPLETED_STAGES)
            ),
            ...ContractListQuery::stageCountSelects(),
        ]);

        $metrics = [
            'next' => $this->facts->nextAppointment($ids, $now),
            'series' => $this->facts->series($ids),
            'total' => (int) $contract->appointments_count,
            'upcoming' => Appointment::where('contract_id', $contract->id)->where('start_at', '>=', $now)->count(),
            'last' => Appointment::where('contract_id', $contract->id)
                ->where('start_at', '<', $now)->orderByDesc('start_at')->value('start_at'),
            'team' => $this->team($contract),
            'unassigned' => Appointment::where('contract_id', $contract->id)
                ->where('start_at', '>=', $now)
                ->whereDoesntHave('workers')
                ->count(),
        ];

        return [
            'upcomingAppointments' => $this->appointments($contract, $now, upcoming: true),
            'pastAppointments' => $this->appointments($contract, $now, upcoming: false),
            'timeline' => $this->timeline->forContracts($ids),
            'facts' => $this->facts($contract, $metrics),
        ];
    }

    /**
     * @return Collection<int, Appointment>
     */
    private function appointments(Contract $contract, Carbon $now, bool $upcoming): Collection
    {
        return Appointment::query()
            ->where('contract_id', $contract->id)
            ->where('start_at', $upcoming ? '>=' : '<', $now)
            ->with(['workers:id,first_name,last_name', 'status:id,name,color'])
            ->orderBy('start_at', $upcoming ? 'asc' : 'desc')
            ->limit(self::APPOINTMENT_LIMIT)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array<string, mixed>
     */
    private function facts(Contract $contract, array $metrics): array
    {
        $address = $this->facts->address($contract->street, $contract->zip, $contract->city);
        $last = $metrics['last'] ? Carbon::parse($metrics['last']) : null;

        return [
            'since' => $contract->created_at?->toISOString(),
            'address' => $address,
            'map_url' => $this->facts->mapUrl($address),
            'access_notes' => $contract->access_notes,
            'stage' => isset($contract->appointments_count) ? ContractListQuery::currentStage($contract) : null,
            'progress' => isset($contract->appointments_count)
                ? $contract->progress()
                : ['done' => 0, 'total' => 0, 'percent' => 0],
            'team' => $metrics['team'],
            'badges' => $this->badges($contract, $metrics),
            'stats' => [
                'appointments' => (int) $metrics['total'],
                'upcoming' => (int) $metrics['upcoming'],
                'clients' => $contract->clients->count(),
                'last' => $last?->toISOString(),
                'next' => $metrics['next']['start_at'] ?? null,
            ],
            'next_appointment' => $metrics['next'],
            'series' => $metrics['series'],
        ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array<int, array{key: string, tone: string}>
     */
    private function badges(Contract $contract, array $metrics): array
    {
        $badges = [];

        if ($contract->kind) {
            $badges[] = ['key' => $contract->kind, 'tone' => 'muted'];
        }

        if ($metrics['series'] !== null) {
            $badges[] = ['key' => 'recurring', 'tone' => 'navy'];
        }

        if ((int) $metrics['unassigned'] > 0) {
            $badges[] = ['key' => 'unassigned_work', 'tone' => 'warning'];
        }

        return $badges;
    }

    /**
     * The people booked on this contract. One query, whatever the history size.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function team(Contract $contract): array
    {
        return Appointment::query()
            ->where('contract_id', $contract->id)
            ->with('workers:id,first_name,last_name')
            ->get(['id', 'contract_id'])
            ->flatMap->workers
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->map(fn ($worker) => ['id' => $worker->id, 'name' => $worker->name])
            ->all();
    }
}
