<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MatchesJsonSnapshots;
use Tests\TestCase;

class NotificationEndpointTest extends TestCase
{
    use MatchesJsonSnapshots, RefreshDatabase;

    private Company $company;

    private User $owner;

    private User $markus;

    protected function setUp(): void
    {
        parent::setUp();

        // The payload carries relative and absolute timestamps; pin "now".
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

    /* ---------------- payload shape ---------------- */

    /**
     * One notification of every type, newest first, so the golden file pins the
     * exact fields NotificationBell.vue reads — data.excerpt and created_at
     * included, which no other assertion covers.
     */
    public function test_the_bell_payload_matches_the_golden_file(): void
    {
        $contract = Contract::factory()->create([
            'company_id' => $this->company->id,
            'contract_number' => 'A-0042',
            'title' => 'Routinekontrolle Bergmann',
        ]);
        $appointment = Appointment::factory()->at('2026-04-09 09:00')->create([
            'company_id' => $this->company->id,
            'contract_id' => $contract->id,
            'created_by' => $this->owner->id,
        ]);

        foreach (array_values(Notification::TYPES) as $index => $type) {
            Notification::withoutGlobalScope('company')->create([
                'company_id' => $this->company->id,
                'user_id' => $this->markus->id,
                'actor_id' => $this->owner->id,
                'type' => $type,
                'appointment_id' => $appointment->id,
                'data' => ['title' => $contract->title, 'excerpt' => 'Zugang war heute verschlossen.'],
            ])->forceFill([
                // Distinct minutes so `latest()` has a deterministic order.
                'created_at' => Carbon::parse('2026-04-08 11:00:00')->subMinutes($index),
            ])->save();
        }

        $payload = $this->actingAs($this->markus)->getJson('/notifications')->json();

        $this->assertMatchesJsonSnapshot($payload, 'bell-payload');
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function severityCases(): array
    {
        return [
            'conflict is critical' => [Notification::TYPE_CONFLICT, 'critical'],
            'unassigned is a warning' => [Notification::TYPE_UNASSIGNED, 'warning'],
            'series ending is a warning' => [Notification::TYPE_SERIES_ENDING, 'warning'],
            'attachment is a success' => [Notification::TYPE_ATTACHMENT, 'success'],
            'mention falls back to info' => [Notification::TYPE_MENTION, 'info'],
            'comment falls back to info' => [Notification::TYPE_COMMENT, 'info'],
            'assignment falls back to info' => [Notification::TYPE_ASSIGNED, 'info'],
        ];
    }

    #[DataProvider('severityCases')]
    public function test_every_type_maps_to_the_severity_the_bell_colours_by(string $type, string $expected): void
    {
        $this->notify($this->markus, $type);

        $payload = $this->actingAs($this->markus)->getJson('/notifications')->json();

        $this->assertSame($expected, $payload['notifications'][0]['severity']);
    }

    public function test_every_known_type_is_covered_by_the_severity_cases(): void
    {
        $this->assertEqualsCanonicalizing(
            Notification::TYPES,
            array_column(self::severityCases(), 0),
        );
    }

    public function test_the_bell_never_returns_more_than_thirty_rows(): void
    {
        foreach (range(1, 35) as $i) {
            $this->notify($this->markus);
        }

        $payload = $this->actingAs($this->markus)->getJson('/notifications')->json();

        $this->assertCount(30, $payload['notifications']);
        // The unread count is the true total, not the truncated page.
        $this->assertSame(35, $payload['unread']);
    }

    /* ---------------- marking read ---------------- */

    public function test_a_notification_from_another_company_cannot_be_found_at_all(): void
    {
        $otherCompany = Company::factory()->create();
        $stranger = User::factory()->create([
            'company_id' => $otherCompany->id, 'role' => User::ROLE_OWNER,
        ]);
        $theirs = Notification::withoutGlobalScope('company')->create([
            'company_id' => $otherCompany->id,
            'user_id' => $stranger->id,
            'type' => Notification::TYPE_ASSIGNED,
            'data' => [],
        ]);

        // 404, not 403: the company scope means the row does not exist for us,
        // which is also the answer that leaks the least.
        $this->actingAs($this->markus)
            ->post("/notifications/{$theirs->id}/read")
            ->assertNotFound();

        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_marking_an_already_read_notification_read_again_keeps_the_first_time(): void
    {
        $notification = $this->notify($this->markus);
        $notification->forceFill(['read_at' => Carbon::parse('2026-04-08 09:00:00')])->save();

        $this->actingAs($this->markus)
            ->post("/notifications/{$notification->id}/read")
            ->assertRedirect();

        $this->assertTrue($notification->fresh()->read_at->equalTo(Carbon::parse('2026-04-08 12:00:00')));
    }
}
