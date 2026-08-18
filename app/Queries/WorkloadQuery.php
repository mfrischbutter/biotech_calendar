<?php

namespace App\Queries;

use App\Models\Appointment;
use Carbon\Carbon;

/**
 * Booked minutes per employee over a window, shared by the dashboard's workload
 * strip and the employee list's utilisation column.
 *
 * The duration is summed in PHP rather than with `timestampdiff`, which only
 * exists on MySQL — the test suite runs on SQLite in CI. One query returns the
 * assignment rows for the window (a week's worth), which is bounded work.
 */
class WorkloadQuery
{
    /** A full week for one employee, in minutes. */
    public const WEEKLY_CAPACITY_MINUTES = 40 * 60;

    /**
     * Appointment count and booked minutes per user id.
     *
     * @return array<int, array{appointments: int, minutes: int, percent: int}>
     */
    public function perUser(int $companyId, Carbon $start, Carbon $end): array
    {
        $rows = Appointment::query()
            ->where('appointments.company_id', $companyId)
            ->whereBetween('appointments.start_at', [$start, $end])
            ->join('appointment_user', 'appointment_user.appointment_id', '=', 'appointments.id')
            ->select([
                'appointment_user.user_id',
                'appointments.start_at',
                'appointments.end_at',
            ])
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $uid = (int) $row->user_id;

            if (! isset($totals[$uid])) {
                $totals[$uid] = ['appointments' => 0, 'minutes' => 0, 'percent' => 0];
            }

            $totals[$uid]['appointments']++;
            $totals[$uid]['minutes'] += $this->minutes($row->start_at, $row->end_at);
        }

        foreach ($totals as $uid => $totalsForUser) {
            $totals[$uid]['percent'] = $this->percent($totalsForUser['minutes']);
        }

        return $totals;
    }

    /**
     * Utilisation percentage per user id — the employee list's narrower need.
     *
     * @return array<int, int>
     */
    public function percentPerUser(int $companyId, Carbon $start, Carbon $end): array
    {
        return array_map(
            fn (array $row) => $row['percent'],
            $this->perUser($companyId, $start, $end)
        );
    }

    private function minutes(?Carbon $start, ?Carbon $end): int
    {
        if (! $start || ! $end || $end->lte($start)) {
            return 0;
        }

        return (int) $start->diffInMinutes($end);
    }

    private function percent(int $minutes): int
    {
        return (int) min(100, round(($minutes / self::WEEKLY_CAPACITY_MINUTES) * 100));
    }
}
