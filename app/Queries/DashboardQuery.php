<?php

namespace App\Queries;

use App\Models\Status;
use App\Models\User;
use Carbon\Carbon;

/**
 * The dashboard's two aggregate strips: jobs per pipeline stage, and booked
 * hours per technician this week.
 *
 * Both live here rather than in the controller so the controller stays a
 * validate → delegate → respond shell, matching the list screens.
 */
class DashboardQuery
{
    /** @var array<string, int>|null */
    private ?array $jobCounts = null;

    public function __construct(
        private WorkloadQuery $workload,
        private ContractListQuery $contracts,
    ) {}

    /**
     * How many jobs sit in each tab of the contract list, keyed by view.
     *
     * The strip counted appointments while the list it links to counts
     * contracts, so a stage reading "47" opened a list of 40 rows. Both numbers
     * now come from the one query the list itself uses, and cannot drift.
     *
     * @return array<string, int>
     */
    public function jobCounts(): array
    {
        return $this->jobCounts ??= collect($this->contracts->stageCounts([]))
            ->pluck('count', 'stage')
            ->all();
    }

    /**
     * Jobs per pipeline stage, in pipeline order.
     *
     * @return array<int, array{stage: string, count: int}>
     */
    public function pipeline(): array
    {
        $counts = $this->jobCounts();

        return collect(Status::PIPELINE_STAGES)
            ->map(fn (string $stage) => [
                'stage' => $stage,
                'count' => (int) ($counts[$stage] ?? 0),
            ])
            ->all();
    }

    /**
     * Utilisation per technician for the current week, against a 40h week.
     * Employees with nothing booked are left out — the strip is about load.
     *
     * @return array<int, array{id: int, name: string, appointments: int, percent: int}>
     */
    public function workload(int $companyId, Carbon $now): array
    {
        $totals = $this->workload->perUser(
            $companyId,
            $now->copy()->startOfWeek(Carbon::MONDAY),
            $now->copy()->endOfWeek(Carbon::SUNDAY),
        );

        return User::query()
            ->where('company_id', $companyId)
            ->orderBy('first_name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'appointments' => $totals[$u->id]['appointments'] ?? 0,
                'percent' => $totals[$u->id]['percent'] ?? 0,
            ])
            ->filter(fn (array $row) => $row['appointments'] > 0)
            ->values()
            ->all();
    }

    /** Mean utilisation across everyone carrying work this week. */
    public function averageUtilisation(int $companyId, Carbon $now): int
    {
        $rows = $this->workload($companyId, $now);

        if ($rows === []) {
            return 0;
        }

        return (int) round(collect($rows)->avg('percent'));
    }
}
