<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\User;
use App\Services\ScheduleConflictService;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Models are built in memory with their `workers` relation pre-set, so the
 * overlap rules are exercised without touching the database. Extends the
 * Laravel TestCase because Eloquent's attribute casting needs a booted app.
 */
class ScheduleConflictServiceTest extends TestCase
{
    private ScheduleConflictService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ScheduleConflictService;
    }

    /** @param  array<int, int>  $workerIds */
    private function appointment(int $id, string $start, string $end, array $workerIds): Appointment
    {
        $appointment = new Appointment;
        $appointment->id = $id;
        $appointment->start_at = $start;
        $appointment->end_at = $end;
        $appointment->setRelation(
            'workers',
            new Collection(array_map(function (int $wid) {
                $u = new User;
                $u->id = $wid;

                return $u;
            }, $workerIds))
        );

        return $appointment;
    }

    /** @param  array<int, Appointment>  $appointments */
    private function detect(array $appointments): array
    {
        return $this->service->detect(new Collection($appointments));
    }

    public function test_same_worker_overlapping_times_is_a_conflict(): void
    {
        $conflicts = $this->detect([
            $this->appointment(1, '2026-04-08 09:00', '2026-04-08 11:00', [7]),
            $this->appointment(2, '2026-04-08 10:00', '2026-04-08 12:00', [7]),
        ]);

        $this->assertSame([2], $conflicts[1]);
        $this->assertSame([1], $conflicts[2]);
    }

    public function test_different_workers_never_conflict(): void
    {
        $conflicts = $this->detect([
            $this->appointment(1, '2026-04-08 09:00', '2026-04-08 11:00', [7]),
            $this->appointment(2, '2026-04-08 09:30', '2026-04-08 10:30', [8]),
        ]);

        $this->assertSame([], $conflicts);
    }

    public function test_back_to_back_appointments_do_not_conflict(): void
    {
        $conflicts = $this->detect([
            $this->appointment(1, '2026-04-08 09:00', '2026-04-08 10:00', [7]),
            $this->appointment(2, '2026-04-08 10:00', '2026-04-08 11:00', [7]),
        ]);

        $this->assertSame([], $conflicts, 'One ending exactly as the next starts is a clean handover, not a clash.');
    }

    public function test_unassigned_appointments_are_ignored(): void
    {
        $conflicts = $this->detect([
            $this->appointment(1, '2026-04-08 09:00', '2026-04-08 11:00', []),
            $this->appointment(2, '2026-04-08 09:30', '2026-04-08 10:30', []),
        ]);

        $this->assertSame([], $conflicts);
    }

    public function test_conflict_is_detected_when_only_one_of_several_workers_overlaps(): void
    {
        $conflicts = $this->detect([
            $this->appointment(1, '2026-04-08 09:00', '2026-04-08 11:00', [7, 8]),
            $this->appointment(2, '2026-04-08 10:00', '2026-04-08 12:00', [8, 9]),
        ]);

        $this->assertSame([2], $conflicts[1]);
    }

    public function test_one_appointment_can_clash_with_several_others(): void
    {
        $conflicts = $this->detect([
            $this->appointment(1, '2026-04-08 08:00', '2026-04-08 18:00', [7]),
            $this->appointment(2, '2026-04-08 09:00', '2026-04-08 10:00', [7]),
            $this->appointment(3, '2026-04-08 11:00', '2026-04-08 12:00', [7]),
        ]);

        $this->assertSame([2, 3], $conflicts[1]);
        $this->assertSame([1], $conflicts[2]);
        $this->assertSame([1], $conflicts[3]);
    }

    public function test_fully_contained_appointment_conflicts(): void
    {
        $conflicts = $this->detect([
            $this->appointment(1, '2026-04-08 09:00', '2026-04-08 17:00', [7]),
            $this->appointment(2, '2026-04-08 10:00', '2026-04-08 11:00', [7]),
        ]);

        $this->assertSame([2], $conflicts[1]);
    }

    public function test_appointments_on_different_days_do_not_conflict(): void
    {
        $conflicts = $this->detect([
            $this->appointment(1, '2026-04-08 09:00', '2026-04-08 11:00', [7]),
            $this->appointment(2, '2026-04-09 09:00', '2026-04-09 11:00', [7]),
        ]);

        $this->assertSame([], $conflicts);
    }

    public function test_an_empty_schedule_has_no_conflicts(): void
    {
        $this->assertSame([], $this->detect([]));
    }

    public function test_conflicts_are_reported_once_per_pair(): void
    {
        $conflicts = $this->detect([
            $this->appointment(1, '2026-04-08 09:00', '2026-04-08 11:00', [7, 8]),
            $this->appointment(2, '2026-04-08 09:30', '2026-04-08 10:30', [7, 8]),
        ]);

        $this->assertSame([2], $conflicts[1], 'Sharing two workers is still one conflict.');
    }
}
