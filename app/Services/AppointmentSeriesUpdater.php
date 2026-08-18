<?php

namespace App\Services;

use App\Models\Appointment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Applies an appointment edit to either the single occurrence the user opened
 * or the whole recurring series.
 *
 * Series semantics, deliberately narrow so nothing is silently lost:
 *  - the descriptive fields (contract, status, notes, checklist) and the worker
 *    assignment are copied to every member;
 *  - a time change is applied as "new time of day + same duration", and a date
 *    change as the *day offset* the user made, so a series can be shifted from
 *    Monday to Tuesday without collapsing onto one date;
 *  - the recurrence rule itself is never touched here.
 */
class AppointmentSeriesUpdater
{
    public const SCOPE_SINGLE = 'single';

    public const SCOPE_SERIES = 'series';

    public const SCOPES = [self::SCOPE_SINGLE, self::SCOPE_SERIES];

    /**
     * @param  array<string, mixed>  $data  validated attributes, without worker_ids/scope
     * @param  array<int, int>|null  $workerIds  null means "leave the assignment alone"
     * @return Collection<int, Appointment> every appointment that was written
     */
    public function apply(Appointment $appointment, array $data, ?array $workerIds, string $scope): Collection
    {
        if ($scope !== self::SCOPE_SERIES || ! $appointment->belongsToSeries()) {
            return $this->applySingle($appointment, $data, $workerIds);
        }

        $members = $appointment->seriesMembers();
        $shift = $this->timeShift($appointment, $data);
        $shared = collect($data)->except(['start_at', 'end_at'])->all();

        foreach ($members as $member) {
            $attributes = $shared;

            if ($shift !== null) {
                [$dayOffset, $hour, $minute, $duration] = $shift;
                $start = $member->start_at->copy()
                    ->addDays($dayOffset)
                    ->setTime($hour, $minute);
                $attributes['start_at'] = $start;
                $attributes['end_at'] = $start->copy()->addMinutes($duration);
            }

            $member->update($attributes);

            if ($workerIds !== null) {
                $member->workers()->sync($workerIds);
            }
        }

        return $members;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>|null  $workerIds
     * @return Collection<int, Appointment>
     */
    private function applySingle(Appointment $appointment, array $data, ?array $workerIds): Collection
    {
        $appointment->update($data);

        if ($workerIds !== null) {
            $appointment->workers()->sync($workerIds);
        }

        return collect([$appointment]);
    }

    /**
     * The move the user made on the appointment they opened, expressed so it can
     * be replayed on every sibling.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: int, 1: int, 2: int, 3: int}|null [dayOffset, hour, minute, durationMinutes]
     */
    private function timeShift(Appointment $appointment, array $data): ?array
    {
        if (! isset($data['start_at']) || ! isset($data['end_at'])) {
            return null;
        }

        $newStart = Carbon::parse($data['start_at']);
        $newEnd = Carbon::parse($data['end_at']);

        $dayOffset = (int) $appointment->start_at->copy()->startOfDay()
            ->diffInDays($newStart->copy()->startOfDay(), false);

        return [
            $dayOffset,
            (int) $newStart->hour,
            (int) $newStart->minute,
            (int) abs($newStart->diffInMinutes($newEnd)),
        ];
    }
}
