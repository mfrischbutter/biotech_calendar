<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationEndpointTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    private User $markus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->owner = User::factory()->create([
            'company_id' => $this->company->id, 'role' => User::ROLE_OWNER,
        ]);
        $this->markus = User::factory()->create([
            'company_id' => $this->company->id, 'role' => User::ROLE_EMPLOYEE,
            'first_name' => 'Markus', 'last_name' => 'Weber',
        ]);
    }

    private function notify(User $to, string $type = Notification::TYPE_ASSIGNED): Notification
    {
        $contract = Contract::factory()->create(['company_id' => $this->company->id]);
        $appointment = Appointment::factory()->create([
            'company_id' => $this->company->id,
            'contract_id' => $contract->id,
            'created_by' => $this->owner->id,
        ]);

        return Notification::withoutGlobalScope('company')->create([
            'company_id' => $this->company->id,
            'user_id' => $to->id,
            'actor_id' => $this->owner->id,
            'type' => $type,
            'appointment_id' => $appointment->id,
            'data' => ['title' => $contract->title],
        ]);
    }

    public function test_it_lists_only_the_signed_in_users_notifications(): void
    {
        $this->notify($this->markus);
        $this->notify($this->owner);

        $payload = $this->actingAs($this->markus)->getJson('/notifications')->assertOk()->json();

        $this->assertCount(1, $payload['notifications']);
        $this->assertSame(1, $payload['unread']);
    }

    public function test_it_reports_the_unread_count(): void
    {
        $this->notify($this->markus);
        $read = $this->notify($this->markus);
        $read->forceFill(['read_at' => now()])->save();

        $payload = $this->actingAs($this->markus)->getJson('/notifications')->json();

        $this->assertCount(2, $payload['notifications']);
        $this->assertSame(1, $payload['unread']);
    }

    public function test_each_notification_carries_a_severity_for_the_ui(): void
    {
        $this->notify($this->markus, Notification::TYPE_CONFLICT);

        $payload = $this->actingAs($this->markus)->getJson('/notifications')->json();

        $this->assertSame('critical', $payload['notifications'][0]['severity']);
    }

    public function test_each_notification_links_to_its_appointment(): void
    {
        $notification = $this->notify($this->markus);

        $payload = $this->actingAs($this->markus)->getJson('/notifications')->json();

        $this->assertStringContainsString(
            'appointment='.$notification->appointment_id,
            $payload['notifications'][0]['url'],
        );
    }

    public function test_a_user_can_mark_their_notification_read(): void
    {
        $notification = $this->notify($this->markus);

        $this->actingAs($this->markus)
            ->post("/notifications/{$notification->id}/read")
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_a_user_cannot_mark_someone_elses_notification_read(): void
    {
        $notification = $this->notify($this->owner);

        $this->actingAs($this->markus)
            ->post("/notifications/{$notification->id}/read")
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_read_clears_only_the_users_own(): void
    {
        $mine = $this->notify($this->markus);
        $theirs = $this->notify($this->owner);

        $this->actingAs($this->markus)->post('/notifications/read-all')->assertRedirect();

        $this->assertNotNull($mine->fresh()->read_at);
        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_guests_cannot_read_notifications(): void
    {
        $this->getJson('/notifications')->assertUnauthorized();
    }

    public function test_assigning_an_appointment_notifies_the_worker_end_to_end(): void
    {
        $contract = Contract::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs($this->owner)->post('/appointments', [
            'contract_id' => $contract->id,
            'worker_ids' => [$this->markus->id],
            'start_at' => '2026-05-08T10:00:00',
            'end_at' => '2026-05-08T11:00:00',
        ])->assertSessionHasNoErrors();

        $payload = $this->actingAs($this->markus)->getJson('/notifications')->json();

        $this->assertSame(1, $payload['unread']);
        $this->assertSame(Notification::TYPE_ASSIGNED, $payload['notifications'][0]['type']);
    }

    public function test_creating_a_double_booking_raises_a_conflict_notification(): void
    {
        $contract = Contract::factory()->create(['company_id' => $this->company->id]);

        foreach ([['10:00', '12:00'], ['11:00', '13:00']] as [$from, $to]) {
            $this->actingAs($this->owner)->post('/appointments', [
                'contract_id' => $contract->id,
                'worker_ids' => [$this->markus->id],
                'start_at' => "2026-05-08T{$from}:00",
                'end_at' => "2026-05-08T{$to}:00",
            ])->assertSessionHasNoErrors();
        }

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->markus->id,
            'type' => Notification::TYPE_CONFLICT,
        ]);
    }

    public function test_an_at_mention_in_a_comment_notifies_the_named_person(): void
    {
        $contract = Contract::factory()->create(['company_id' => $this->company->id]);
        $appointment = Appointment::factory()->create([
            'company_id' => $this->company->id,
            'contract_id' => $contract->id,
            'created_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)
            ->post("/appointments/{$appointment->id}/comments", [
                'body' => 'Bitte @Markus Weber uebernehmen.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->markus->id,
            'type' => Notification::TYPE_MENTION,
        ]);
    }
}
