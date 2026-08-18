<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Notification;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MatchesJsonSnapshots;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use MatchesJsonSnapshots, RefreshDatabase;

    private Company $company;

    private User $owner;

    private User $markus;

    protected function setUp(): void
    {
        parent::setUp();

        // Freeze time so "today", "this week" and relative states are deterministic.
        Carbon::setTestNow('2026-04-08 12:00:00');

        $this->company = Company::factory()->create();
        $this->owner = User::factory()->create([
            'company_id' => $this->company->id, 'role' => User::ROLE_OWNER,
            'first_name' => 'Bjoern', 'last_name' => 'Wilhelmsen',
        ]);
        $this->markus = User::factory()->create([
            'company_id' => $this->company->id, 'role' => User::ROLE_EMPLOYEE,
            'first_name' => 'Markus', 'last_name' => 'Weber',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function appointment(string $start, int $minutes = 60, ?Status $status = null, array $workers = []): Appointment
    {
        $contract = Contract::factory()->create(['company_id' => $this->company->id]);

        $appointment = Appointment::factory()->at($start, $minutes)->create([
            'company_id' => $this->company->id,
            'contract_id' => $contract->id,
            'created_by' => $this->owner->id,
            'status_id' => $status?->id,
        ]);

        if ($workers !== []) {
            $appointment->workers()->sync($workers);
        }

        return $appointment;
    }

    private function makeStatus(string $stage): Status
    {
        return Status::factory()->stage($stage)->create(['company_id' => $this->company->id]);
    }

    private function props(): array
    {
        return $this->actingAs($this->owner)->get('/dashboard')->assertOk()->viewData('page')['props'];
    }

    public function test_it_counts_appointments_with_nobody_assigned(): void
    {
        $this->appointment('2026-04-09 09:00');
        $this->appointment('2026-04-10 09:00');
        $this->appointment('2026-04-09 14:00', 60, null, [$this->markus->id]);

        $this->assertSame(2, $this->props()['attention']['unassigned']);
    }

    public function test_it_counts_work_that_finished_without_reaching_a_closing_status(): void
    {
        $this->appointment('2026-04-01 09:00');                                    // no status -> overdue
        $this->appointment('2026-04-02 09:00', 60, $this->makeStatus(Status::STAGE_ACTIVE)); // still open -> overdue
        $this->appointment('2026-04-03 09:00', 60, $this->makeStatus(Status::STAGE_INVOICED)); // closed
        $this->appointment('2026-04-20 09:00');                                    // future

        $this->assertSame(2, $this->props()['attention']['overdue']);
    }

    public function test_cancelled_work_is_not_treated_as_overdue(): void
    {
        $this->appointment('2026-04-01 09:00', 60, $this->makeStatus(Status::STAGE_CANCELLED));

        $this->assertSame(0, $this->props()['attention']['overdue']);
    }

    public function test_it_counts_what_is_ready_to_invoice(): void
    {
        $ready = $this->makeStatus(Status::STAGE_READY_TO_INVOICE);
        $this->appointment('2026-04-01 09:00', 60, $ready);
        $this->appointment('2026-04-02 09:00', 60, $ready);
        $this->appointment('2026-04-03 09:00', 60, $this->makeStatus(Status::STAGE_INVOICED));

        $this->assertSame(2, $this->props()['attention']['readyToInvoice']);
    }

    public function test_todays_schedule_is_grouped_by_technician_in_time_order(): void
    {
        $this->appointment('2026-04-08 14:00', 60, null, [$this->markus->id]);
        $this->appointment('2026-04-08 09:00', 60, null, [$this->markus->id]);

        $schedule = $this->props()['schedule'];

        $this->assertCount(1, $schedule);
        $this->assertSame('Markus Weber', $schedule[0]['worker_name']);
        $this->assertCount(2, $schedule[0]['items']);
        $this->assertStringContainsString('T09:00', $schedule[0]['items'][0]['start_at']);
    }

    public function test_unassigned_work_gets_its_own_group_listed_first(): void
    {
        $this->appointment('2026-04-08 09:00', 60, null, [$this->markus->id]);
        $this->appointment('2026-04-08 10:00');

        $schedule = $this->props()['schedule'];

        $this->assertNull($schedule[0]['worker_id'], 'The group needing a decision comes first.');
    }

    public function test_an_appointment_with_two_workers_appears_under_both(): void
    {
        $lisa = User::factory()->create(['company_id' => $this->company->id, 'first_name' => 'Lisa', 'last_name' => 'Bauer']);
        $this->appointment('2026-04-08 09:00', 60, null, [$this->markus->id, $lisa->id]);

        $schedule = $this->props()['schedule'];

        $this->assertCount(2, $schedule);
        $this->assertSame(1, count($schedule[0]['items']));
    }

    public function test_only_todays_appointments_are_in_the_schedule(): void
    {
        $this->appointment('2026-04-08 09:00', 60, null, [$this->markus->id]);
        $this->appointment('2026-04-09 09:00', 60, null, [$this->markus->id]);

        $this->assertCount(1, $this->props()['schedule'][0]['items']);
    }

    public function test_an_appointment_running_right_now_is_marked_as_such(): void
    {
        $this->appointment('2026-04-08 11:30', 60, null, [$this->markus->id]); // 11:30-12:30, now = 12:00

        $this->assertSame('now', $this->props()['schedule'][0]['items'][0]['state']);
    }

    public function test_a_finished_appointment_is_marked_done(): void
    {
        $this->appointment('2026-04-08 08:00', 60, $this->makeStatus(Status::STAGE_INVOICED), [$this->markus->id]);

        $this->assertSame('done', $this->props()['schedule'][0]['items'][0]['state']);
    }

    public function test_the_pipeline_reports_every_stage_even_when_empty(): void
    {
        $this->appointment('2026-04-08 09:00', 60, $this->makeStatus(Status::STAGE_READY_TO_INVOICE));

        $pipeline = collect($this->props()['pipeline']);

        $this->assertSame(
            ['unconfirmed', 'active', 'ready_to_invoice', 'invoiced'],
            $pipeline->pluck('stage')->all(),
        );
        $this->assertSame(1, $pipeline->firstWhere('stage', 'ready_to_invoice')['count']);
        $this->assertSame(0, $pipeline->firstWhere('stage', 'unconfirmed')['count']);
    }

    public function test_workload_only_lists_people_with_work_this_week(): void
    {
        $this->appointment('2026-04-08 09:00', 240, null, [$this->markus->id]);

        $workload = $this->props()['workload'];

        $this->assertCount(1, $workload);
        $this->assertSame('Markus Weber', $workload[0]['name']);
        $this->assertSame(1, $workload[0]['appointments']);
        $this->assertSame(10, $workload[0]['percent'], '4h of a 40h week is 10%.');
    }

    public function test_utilisation_is_capped_at_one_hundred_percent(): void
    {
        $this->appointment('2026-04-08 00:00', 60 * 60, null, [$this->markus->id]);

        $this->assertSame(100, $this->props()['workload'][0]['percent']);
    }

    public function test_the_dashboard_never_shows_another_companys_work(): void
    {
        $other = Company::factory()->create();
        $otherContract = Contract::factory()->create(['company_id' => $other->id]);
        Appointment::factory()->at('2026-04-08 09:00')->create([
            'company_id' => $other->id,
            'contract_id' => $otherContract->id,
            'created_by' => User::factory()->create(['company_id' => $other->id])->id,
        ]);

        $this->assertSame([], $this->props()['schedule']);
        $this->assertSame(0, $this->props()['attention']['unassigned']);
    }

    public function test_work_that_finished_today_but_is_still_open_reads_as_past(): void
    {
        // 10:00-11:00, now = 12:00, still in an ACTIVE stage: over, but not done.
        $this->appointment('2026-04-08 10:00', 60, $this->makeStatus(Status::STAGE_ACTIVE), [$this->markus->id]);

        $this->assertSame('past', $this->props()['schedule'][0]['items'][0]['state']);
    }

    public function test_the_unassigned_window_starts_today_and_ends_on_sunday(): void
    {
        $this->appointment('2026-04-07 09:00'); // yesterday — already missed, not "this week's gap"
        $this->appointment('2026-04-12 09:00'); // Sunday — the last day counted
        $this->appointment('2026-04-13 09:00'); // next Monday — next week's problem

        $this->assertSame(1, $this->props()['attention']['unassigned']);
    }

    public function test_work_older_than_three_months_has_stopped_being_actionable(): void
    {
        $this->appointment('2025-12-01 09:00'); // 4 months back
        $this->appointment('2026-03-01 09:00'); // inside the window

        $this->assertSame(1, $this->props()['attention']['overdue']);
    }

    public function test_unread_conflict_notifications_are_counted_as_attention(): void
    {
        $appointment = $this->appointment('2026-04-08 09:00', 60, null, [$this->markus->id]);

        $conflict = Notification::withoutGlobalScope('company')->create([
            'company_id' => $this->company->id,
            'user_id' => $this->owner->id,
            'type' => Notification::TYPE_CONFLICT,
            'appointment_id' => $appointment->id,
            'data' => [],
        ]);
        // A different type, and someone else's conflict, must not be counted.
        Notification::withoutGlobalScope('company')->create([
            'company_id' => $this->company->id,
            'user_id' => $this->owner->id,
            'type' => Notification::TYPE_ASSIGNED,
            'appointment_id' => $appointment->id,
            'data' => [],
        ]);
        Notification::withoutGlobalScope('company')->create([
            'company_id' => $this->company->id,
            'user_id' => $this->markus->id,
            'type' => Notification::TYPE_CONFLICT,
            'appointment_id' => $appointment->id,
            'data' => [],
        ]);

        $this->assertSame(1, $this->props()['attention']['conflicts']);

        $conflict->forceFill(['read_at' => now()])->save();

        $this->assertSame(0, $this->props()['attention']['conflicts'], 'Reading it clears it.');
    }

    public function test_average_utilisation_is_the_mean_across_everyone_carrying_work(): void
    {
        $lisa = User::factory()->create([
            'company_id' => $this->company->id, 'first_name' => 'Lisa', 'last_name' => 'Bauer',
        ]);

        $this->appointment('2026-04-08 09:00', 4 * 60, null, [$this->markus->id]);  // 10%
        $this->appointment('2026-04-09 09:00', 12 * 60, null, [$lisa->id]);         // 30%

        // Nobody with an empty week drags the mean down — the strip is about load.
        $this->assertSame(20, $this->props()['attention']['utilisation']);
    }

    public function test_utilisation_is_zero_when_nothing_is_booked(): void
    {
        $this->assertSame(0, $this->props()['attention']['utilisation']);
    }

    public function test_the_attention_feed_shows_unread_notifications_newest_first(): void
    {
        $appointment = $this->appointment('2026-04-08 09:00', 60, null, [$this->markus->id]);

        foreach ([['older', '2026-04-08 08:00'], ['newer', '2026-04-08 10:00']] as [$excerpt, $at]) {
            Notification::withoutGlobalScope('company')->create([
                'company_id' => $this->company->id,
                'user_id' => $this->owner->id,
                'actor_id' => $this->markus->id,
                'type' => Notification::TYPE_MENTION,
                'appointment_id' => $appointment->id,
                'data' => ['excerpt' => $excerpt],
            ])->forceFill(['created_at' => Carbon::parse($at)])->save();
        }

        $feed = $this->props()['notifications'];

        $this->assertSame(['newer', 'older'], array_column($feed, 'excerpt'));
        $this->assertSame('Markus Weber', $feed[0]['actor']);
        $this->assertStringContainsString('appointment='.$appointment->id, $feed[0]['url']);
    }

    public function test_the_attention_feed_leaves_out_what_has_been_read(): void
    {
        $appointment = $this->appointment('2026-04-08 09:00', 60, null, [$this->markus->id]);

        Notification::withoutGlobalScope('company')->create([
            'company_id' => $this->company->id,
            'user_id' => $this->owner->id,
            'type' => Notification::TYPE_MENTION,
            'appointment_id' => $appointment->id,
            'data' => [],
            'read_at' => now(),
        ]);

        $this->assertSame([], $this->props()['notifications']);
    }

    public function test_recent_activity_reports_who_changed_what(): void
    {
        // AppointmentObserver writes the log, so this exercises the real path
        // rather than hand-inserting a row.
        $this->actingAs($this->markus);
        $this->appointment('2026-04-09 09:00');

        $activity = $this->props()['recentActivity'];

        $this->assertNotEmpty($activity, 'Creating an appointment leaves a trace.');
        $this->assertSame('created', $activity[0]['action']);
        $this->assertSame('Markus Weber', $activity[0]['user']);
    }

    public function test_every_aggregate_stops_at_the_company_boundary(): void
    {
        $other = Company::factory()->create();
        $otherOwner = User::factory()->create(['company_id' => $other->id, 'role' => User::ROLE_OWNER]);
        $otherContract = Contract::factory()->create(['company_id' => $other->id]);
        $otherStatus = Status::factory()->stage(Status::STAGE_READY_TO_INVOICE)->create(['company_id' => $other->id]);

        $theirs = Appointment::factory()->at('2026-04-08 09:00', 240)->create([
            'company_id' => $other->id,
            'contract_id' => $otherContract->id,
            'created_by' => $otherOwner->id,
            'status_id' => $otherStatus->id,
        ]);
        $theirs->workers()->sync([$otherOwner->id]);

        $past = Appointment::factory()->at('2026-04-01 09:00')->create([
            'company_id' => $other->id,
            'contract_id' => $otherContract->id,
            'created_by' => $otherOwner->id,
        ]);
        $past->workers()->sync([$otherOwner->id]);

        $props = $this->props();

        $this->assertSame([], $props['schedule']);
        $this->assertSame([], $props['workload']);
        $this->assertSame(0, $props['attention']['unassigned']);
        $this->assertSame(0, $props['attention']['overdue']);
        $this->assertSame(0, $props['attention']['readyToInvoice']);
        $this->assertSame(0, $props['attention']['utilisation']);
        $this->assertSame(
            [0, 0, 0, 0],
            collect($props['pipeline'])->pluck('count')->all(),
        );
    }

    public function test_the_payload_shape_matches_the_golden_file(): void
    {
        $ready = $this->makeStatus(Status::STAGE_READY_TO_INVOICE);
        $this->appointment('2026-04-08 09:00', 90, $ready, [$this->markus->id]);
        $this->appointment('2026-04-08 13:00');

        $props = $this->actingAs($this->owner)->get('/dashboard')->viewData('page')['props'];

        $payload = collect($props)->only(['attention', 'pipeline', 'schedule', 'workload'])->toArray();

        // Titles and addresses come from the faker-backed contract factory.
        $payload['schedule'] = array_map(function (array $group) {
            $group['items'] = array_map(function (array $item) {
                $item['title'] = '<title>';
                $item['address'] = '<address>';

                return $item;
            }, $group['items']);

            return $group;
        }, $payload['schedule']);

        $this->assertMatchesJsonSnapshot($payload, 'dashboard-props');
    }
}
