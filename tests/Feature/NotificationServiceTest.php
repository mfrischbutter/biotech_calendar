<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Comment;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    private User $markus;

    private User $lisa;

    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->owner = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_OWNER,
            'first_name' => 'Bjoern', 'last_name' => 'Wilhelmsen',
        ]);
        $this->markus = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_EMPLOYEE,
            'first_name' => 'Markus', 'last_name' => 'Weber',
        ]);
        $this->lisa = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_EMPLOYEE,
            'first_name' => 'Lisa', 'last_name' => 'Bauer',
        ]);

        $this->service = app(NotificationService::class);
    }

    private function appointment(string $start = '2026-04-08 09:00', int $minutes = 60): Appointment
    {
        $contract = Contract::factory()->create(['company_id' => $this->company->id]);

        return Appointment::factory()->at($start, $minutes)->create([
            'company_id' => $this->company->id,
            'contract_id' => $contract->id,
            'created_by' => $this->owner->id,
        ]);
    }

    public function test_newly_assigned_workers_are_notified(): void
    {
        $appointment = $this->appointment();

        $this->service->appointmentAssignmentsChanged(
            $appointment, [], [$this->markus->id], $this->owner
        );

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->markus->id,
            'type' => Notification::TYPE_ASSIGNED,
            'appointment_id' => $appointment->id,
        ]);
    }

    public function test_removed_workers_are_told_they_are_off_the_job(): void
    {
        $appointment = $this->appointment();

        $this->service->appointmentAssignmentsChanged(
            $appointment, [$this->markus->id], [$this->lisa->id], $this->owner
        );

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->markus->id,
            'type' => Notification::TYPE_UNASSIGNED,
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->lisa->id,
            'type' => Notification::TYPE_ASSIGNED,
        ]);
    }

    public function test_workers_who_stay_assigned_are_not_notified_again(): void
    {
        $appointment = $this->appointment();

        $this->service->appointmentAssignmentsChanged(
            $appointment, [$this->markus->id], [$this->markus->id, $this->lisa->id], $this->owner
        );

        $this->assertDatabaseMissing('app_notifications', [
            'user_id' => $this->markus->id,
            'appointment_id' => $appointment->id,
        ]);
    }

    public function test_you_are_never_notified_about_your_own_action(): void
    {
        $appointment = $this->appointment();

        $this->service->appointmentAssignmentsChanged(
            $appointment, [], [$this->markus->id], $this->markus
        );

        $this->assertSame(0, Notification::withoutGlobalScope('company')->count());
    }

    public function test_double_booking_notifies_the_worker_and_the_owner(): void
    {
        $first = $this->appointment('2026-04-08 09:00', 120);
        $first->workers()->sync([$this->markus->id]);

        $second = $this->appointment('2026-04-08 10:00', 60);
        $second->workers()->sync([$this->markus->id]);

        $count = $this->service->appointmentConflicts($second->fresh(['workers', 'contract']));

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->markus->id,
            'type' => Notification::TYPE_CONFLICT,
            'appointment_id' => $second->id,
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->owner->id,
            'type' => Notification::TYPE_CONFLICT,
            'appointment_id' => $second->id,
        ]);
    }

    public function test_raising_the_same_conflict_twice_does_not_pile_up(): void
    {
        $first = $this->appointment('2026-04-08 09:00', 120);
        $first->workers()->sync([$this->markus->id]);
        $second = $this->appointment('2026-04-08 10:00', 60);
        $second->workers()->sync([$this->markus->id]);

        $this->service->appointmentConflicts($second->fresh(['workers', 'contract']));
        $this->service->appointmentConflicts($second->fresh(['workers', 'contract']));

        $this->assertSame(
            1,
            Notification::withoutGlobalScope('company')
                ->where('user_id', $this->markus->id)
                ->where('type', Notification::TYPE_CONFLICT)
                ->count(),
        );
    }

    public function test_resolving_a_conflict_clears_the_notification(): void
    {
        $first = $this->appointment('2026-04-08 09:00', 120);
        $first->workers()->sync([$this->markus->id]);
        $second = $this->appointment('2026-04-08 10:00', 60);
        $second->workers()->sync([$this->markus->id]);

        $this->service->appointmentConflicts($second->fresh(['workers', 'contract']));
        $this->assertSame(2, Notification::withoutGlobalScope('company')->count());

        // Move the clashing appointment out of the way.
        $second->update(['start_at' => '2026-04-08 13:00', 'end_at' => '2026-04-08 14:00']);
        $this->service->appointmentConflicts($second->fresh(['workers', 'contract']));

        $this->assertSame(0, Notification::withoutGlobalScope('company')->count());
    }

    public function test_no_conflict_when_the_two_jobs_have_different_workers(): void
    {
        $first = $this->appointment('2026-04-08 09:00', 120);
        $first->workers()->sync([$this->markus->id]);
        $second = $this->appointment('2026-04-08 10:00', 60);
        $second->workers()->sync([$this->lisa->id]);

        $this->assertSame(0, $this->service->appointmentConflicts($second->fresh(['workers', 'contract'])));
    }

    /* ---------------- mentions ---------------- */

    public function test_mentions_resolve_full_names(): void
    {
        $users = $this->service->resolveMentions('Bitte @Markus Weber uebernehmen', $this->company->id);

        $this->assertSame([$this->markus->id], $users->pluck('id')->all());
    }

    public function test_mentions_resolve_first_names_alone(): void
    {
        $users = $this->service->resolveMentions('@Lisa kannst du das machen?', $this->company->id);

        $this->assertSame([$this->lisa->id], $users->pluck('id')->all());
    }

    public function test_mentions_are_case_insensitive(): void
    {
        $users = $this->service->resolveMentions('@markus weber bitte', $this->company->id);

        $this->assertSame([$this->markus->id], $users->pluck('id')->all());
    }

    public function test_unknown_mentions_are_ignored(): void
    {
        $users = $this->service->resolveMentions('@Niemand hier', $this->company->id);

        $this->assertTrue($users->isEmpty());
    }

    public function test_mentions_never_cross_company_boundaries(): void
    {
        $other = Company::factory()->create();
        User::factory()->create([
            'company_id' => $other->id,
            'first_name' => 'Markus', 'last_name' => 'Weber',
        ]);

        $users = $this->service->resolveMentions('@Markus Weber', $this->company->id);

        $this->assertSame([$this->markus->id], $users->pluck('id')->all());
    }

    /* ---------------- comments ---------------- */

    private function comment(Appointment $appointment, User $author, string $body): Comment
    {
        return Comment::create([
            'company_id' => $appointment->company_id,
            'appointment_id' => $appointment->id,
            'user_id' => $author->id,
            'body' => $body,
        ]);
    }

    public function test_a_comment_notifies_the_assigned_workers_and_the_creator_but_not_the_author(): void
    {
        // Owner created the appointment; Markus is assigned; Lisa writes the comment.
        $appointment = $this->appointment();
        $appointment->workers()->sync([$this->markus->id]);

        $this->service->commentPosted(
            $this->comment($appointment->fresh(), $this->lisa, 'Zugang war heute verschlossen.'),
            $this->lisa
        );

        $rows = Notification::withoutGlobalScope('company')->get();

        $this->assertEqualsCanonicalizing(
            [$this->markus->id, $this->owner->id],
            $rows->pluck('user_id')->all()
        );
        $this->assertSame([Notification::TYPE_COMMENT], $rows->pluck('type')->unique()->all());
        $this->assertNotContains($this->lisa->id, $rows->pluck('user_id')->all());
    }

    public function test_someone_both_mentioned_and_assigned_is_told_once_and_specifically(): void
    {
        $appointment = $this->appointment();
        $appointment->workers()->sync([$this->markus->id]);

        $this->service->commentPosted(
            $this->comment($appointment->fresh(), $this->lisa, '@Markus Weber bitte nachfassen'),
            $this->lisa
        );

        $forMarkus = Notification::withoutGlobalScope('company')
            ->where('user_id', $this->markus->id)->get();

        $this->assertCount(1, $forMarkus);
        $this->assertSame(Notification::TYPE_MENTION, $forMarkus->first()->type);
    }

    public function test_the_authors_own_comment_never_notifies_them_even_as_creator(): void
    {
        // Owner both created the appointment and writes the comment, and nobody
        // else is assigned — so there is no one left to tell.
        $appointment = $this->appointment();

        $this->service->commentPosted(
            $this->comment($appointment->fresh(), $this->owner, 'Notiz an mich selbst'),
            $this->owner
        );

        $this->assertSame(0, Notification::withoutGlobalScope('company')->count());
    }

    public function test_a_comment_on_nothing_is_ignored(): void
    {
        $appointment = $this->appointment();
        $comment = $this->comment($appointment->fresh(), $this->lisa, 'Hallo');
        $appointment->delete();

        $this->service->commentPosted($comment->fresh() ?? $comment, $this->lisa);

        $this->assertSame(0, Notification::withoutGlobalScope('company')->count());
    }

    public function test_a_long_comment_is_shortened_to_a_readable_excerpt(): void
    {
        $appointment = $this->appointment();
        $appointment->workers()->sync([$this->markus->id]);

        $body = str_repeat('a', 200);
        $this->service->commentPosted(
            $this->comment($appointment->fresh(), $this->lisa, $body),
            $this->lisa
        );

        $excerpt = Notification::withoutGlobalScope('company')
            ->where('user_id', $this->markus->id)->first()->data['excerpt'];

        $this->assertSame(120, mb_strlen($excerpt));
        $this->assertStringEndsWith('…', $excerpt);
        $this->assertSame(str_repeat('a', 119).'…', $excerpt);
    }

    public function test_a_short_comment_is_carried_whole_with_its_whitespace_collapsed(): void
    {
        $appointment = $this->appointment();
        $appointment->workers()->sync([$this->markus->id]);

        $this->service->commentPosted(
            $this->comment($appointment->fresh(), $this->lisa, "  Tuer\n\n  war   offen  "),
            $this->lisa
        );

        $excerpt = Notification::withoutGlobalScope('company')
            ->where('user_id', $this->markus->id)->first()->data['excerpt'];

        $this->assertSame('Tuer war offen', $excerpt);
    }
}
