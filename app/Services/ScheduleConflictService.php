<?php

namespace App\Services;

use App\Models\Appointment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Finds technicians who are booked in two places at once.
 *
 * Double-booking is the most expensive scheduling mistake this business can
 * make, and until now the app allowed it silently.
 */
class ScheduleConflictService
{
    /**
     * Conflicts among the given appointments.
     *
     * Two appointments conflict when they share at least one assigned worker
     * and their time ranges overlap. Touching ranges (one ends exactly when the
     * next starts) do not conflict.
     *
     * @param  Collection<int, Appointment>  $appointments  must have `workers` loaded
     * @return array<int, array<int, int>> appointment id => conflicting appointment ids
     */
    public function detect(Collection $appointments): array
    {
        $items = $appointments
            ->map(fn (Appointment $a) => [
                'id' => $a->id,
                'start' => $a->start_at?->getTimestamp(),
                'end' => $a->end_at?->getTimestamp(),
                'workers' => $a->relationLoaded('workers')
                    ? $a->workers->pluck('id')->all()
                    : $a->workers()->pluck('users.id')->all(),
            ])
            ->filter(fn (array $a) => $a['start'] !== null && $a['end'] !== null && $a['workers'] !== [])
            ->sortBy('start')
            ->values()
            ->all();

        $conflicts = [];

        for ($i = 0; $i < count($items); $i++) {
            for ($j = $i + 1; $j < count($items); $j++) {
                // Sorted by start: once a later item starts at/after this one
                // ends, nothing further can overlap it.
                if ($items[$j]['start'] >= $items[$i]['end']) {
                    break;
                }

                if (array_intersect($items[$i]['workers'], $items[$j]['workers']) === []) {
                    continue;
                }

                $conflicts[$items[$i]['id']][] = $items[$j]['id'];
                $conflicts[$items[$j]['id']][] = $items[$i]['id'];
            }
        }

        return array_map(fn (array $ids) => array_values(array_unique($ids)), $conflicts);
    }

    /**
     * Conflicts for every appointment overlapping the given window.
     *
     * @return array<int, array<int, int>>
     */
    public function detectInRange(CarbonInterface $from, CarbonInterface $to): array
    {
        $appointments = Appointment::with('workers:id')
            ->where('start_at', '<', $to)
            ->where('end_at', '>', $from)
            ->get();

        return $this->detect($appointments);
    }

    /**
     * The appointments that clash with a single appointment.
     *
     * @return Collection<int, Appointment>
     */
    public function conflictsFor(Appointment $appointment): Collection
    {
        $workerIds = $appointment->relationLoaded('workers')
            ? $appointment->workers->pluck('id')->all()
            : $appointment->workers()->pluck('users.id')->all();

        if ($workerIds === [] || ! $appointment->start_at || ! $appointment->end_at) {
            return collect();
        }

        return Appointment::with(['workers:id,first_name,last_name', 'contract:id,title'])
            ->where('id', '!=', $appointment->id)
            ->where('start_at', '<', $appointment->end_at)
            ->where('end_at', '>', $appointment->start_at)
            ->whereHas('workers', fn ($q) => $q->whereIn('users.id', $workerIds))
            ->get();
    }
}
