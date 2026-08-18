<?php

namespace App\Queries;

use App\Models\Appointment;
use App\Models\Status;
use App\Models\User;
use Carbon\Carbon;

/**
 * The dashboard's two aggregate strips: appointments per pipeline stage, and
 * booked hours per technician this week.
 *
 * Both live here rather than in the controller so the controller stays a
 * validate → delegate → respond shell, matching the list screens.
 */
class DashboardQuery
{
    public function __construct(private WorkloadQuery $workload) {}

    /**
     * Counts per pipeline stage — the same data the board view uses.
     *
     * @return array<int, array{stage: string, count: int}>
     */
    public function pipeline(int $companyId): array
    {
        $counts = Appointment::query()
            ->where('appointments.company_id', $companyId)
            ->join('statuses', 'statuses.id', '=', 'appointments.status_id')
            ->groupBy('statuses.stage')
            ->select('statuses.stage')
            ->selectRaw('count(*) as total')
            ->pluck('total', 'stage');

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
