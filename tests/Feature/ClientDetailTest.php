<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentAttachment;
use App\Models\Client;
use App\Models\Comment;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MatchesJsonSnapshots;
use Tests\TestCase;

class ClientDetailTest extends TestCase
{
    use MatchesJsonSnapshots, RefreshDatabase;

    private const NOW = '2026-04-08 12:00:00';

    private Company $company;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        // Tenure, "dormant" and "next appointment" are all relative to now.
        Carbon::setTestNow(self::NOW);

        $this->company = Company::factory()->create();
        $this->owner = User::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Bjoern',
            'last_name' => 'Wilhelmsen',
            'role' => User::ROLE_OWNER,
        ]);

        // Fixtures are built as the owner, so the activity log records an author
        // just as it would in the app — an anonymous log skips an eager load and
        // would make the query-count test lie.
        $this->actingAs($this->owner);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Run a callback with the clock moved, so created_at is deliberate. */
    private function at(string $time, callable $callback): mixed
    {
        Carbon::setTestNow($time);
        $result = $callback();
        Carbon::setTestNow(self::NOW);

        return $result;
    }

    private function client(array $attributes = []): Client
    {
        return Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->owner->id,
            'first_name' => 'Klaus',
            'last_name' => 'Bergmann',
            'company_name' => 'Baeckerei Bergmann',
            'street' => 'Sendlinger Str. 45',
            'zip' => '80331',
            'city' => 'Muenchen',
            'phone' => '+49 89 234567',
            'email' => 'bergmann@example.de',
            ...$attributes,
        ]);
    }

    private function contractFor(Client $client, string $number = 'A-1000', array $attributes = []): Contract
    {
        $contract = Contract::factory()->create([
            'company_id' => $this->company->id,
            'contract_number' => $number,
            'title' => 'Routinekontrolle',
            'kind' => 'kundentermin',
            ...$attributes,
        ]);
        $contract->clients()->attach($client->id);

        return $contract;
    }

    private function appointment(Contract $contract, string $start, array $attributes = []): Appointment
    {
        return Appointment::factory()->at($start)->create([
            'company_id' => $this->company->id,
            'contract_id' => $contract->id,
            'created_by' => $this->owner->id,
            ...$attributes,
        ]);
    }

    /**
     * The fixture every test reads: one customer, one contract, one visit behind
     * and one ahead, plus a comment and a document.
     */
    private function fixture(): Client
    {
        $client = $this->at('2026-03-02 09:00:00', fn () => $this->client());
        $contract = $this->at('2026-03-02 09:00:00', fn () => $this->contractFor($client));

        $status = Status::factory()->stage(Status::STAGE_ACTIVE)->create([
            'company_id' => $this->company->id,
            'name' => 'Erste Massnahme',
            'color' => '#F59E0B',
        ]);

        $past = $this->at('2026-03-02 09:00:00', fn () => $this->appointment($contract, '2026-04-01 10:00:00'));
        $this->at('2026-03-02 10:00:00', fn () => $this->appointment($contract, '2026-04-10 08:00:00', [
            'status_id' => $status->id,
        ]));

        $this->at('2026-04-06 14:00:00', fn () => Comment::create([
            'company_id' => $this->company->id,
            'appointment_id' => $past->id,
            'user_id' => $this->owner->id,
            'body' => 'Koederboxen im Lager kontrolliert.',
        ]));

        $this->at('2026-04-07 11:00:00', fn () => AppointmentAttachment::create([
            'company_id' => $this->company->id,
            'appointment_id' => $past->id,
            'user_id' => $this->owner->id,
            'original_name' => 'Protokoll.pdf',
            'path' => 'appointments/1/documents/protokoll.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ]));

        return $client;
    }

    /**
     * @return array<string, mixed>
     */
    private function props(Client $client, ?User $user = null): array
    {
        $response = $this->actingAs($user ?? $this->owner)->get(route('clients.show', $client->id));
        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    public function test_it_returns_identity_facts_and_compact_stats(): void
    {
        $props = $this->props($this->fixture());
        $facts = $props['facts'];

        $this->assertSame('2026-03-02T09:00:00.000000Z', $facts['since']);
        $this->assertSame('Sendlinger Str. 45, 80331 Muenchen', $facts['address']);
        $this->assertStringContainsString('maps', (string) $facts['map_url']);
        $this->assertStringContainsString('Sendlinger', urldecode((string) $facts['map_url']));

        $this->assertSame(1, $facts['stats']['contracts']);
        $this->assertSame(2, $facts['stats']['appointments']);
        $this->assertSame(1, $facts['stats']['upcoming']);
        $this->assertSame('2026-04-10T08:00:00.000000Z', $facts['stats']['next']);
        $this->assertSame('2026-04-01T10:00:00.000000Z', $facts['stats']['last']);
    }

    public function test_next_appointment_carries_what_a_dispatcher_needs(): void
    {
        $client = $this->fixture();
        $worker = User::factory()->create(['company_id' => $this->company->id, 'first_name' => 'Markus', 'last_name' => 'Weber']);
        Appointment::where('start_at', '2026-04-10 08:00:00')->first()->workers()->sync([$worker->id]);

        $next = $this->props($client)['facts']['next_appointment'];

        $this->assertSame('Routinekontrolle', $next['title']);
        $this->assertSame('A-1000', $next['contract_number']);
        $this->assertSame('Erste Massnahme', $next['status']['name']);
        $this->assertSame(Status::STAGE_ACTIVE, $next['status']['stage']);
        $this->assertSame(['Markus Weber'], array_column($next['workers'], 'name'));
    }

    public function test_timeline_merges_every_source_newest_first(): void
    {
        $timeline = $this->props($this->fixture())['timeline'];

        $this->assertSame(
            ['appointment', 'document', 'comment', 'appointment', 'activity', 'activity'],
            array_column($timeline, 'type')
        );

        $timestamps = array_column($timeline, 'at');
        $sorted = $timestamps;
        rsort($sorted);
        $this->assertSame($sorted, $timestamps);
    }

    public function test_timeline_events_carry_their_own_kind_of_detail(): void
    {
        $timeline = collect($this->props($this->fixture())['timeline']);

        $comment = $timeline->firstWhere('type', 'comment');
        $this->assertSame('Koederboxen im Lager kontrolliert.', $comment['excerpt']);
        $this->assertSame('Bjoern Wilhelmsen', $comment['actor']);

        $document = $timeline->firstWhere('type', 'document');
        $this->assertSame('Protokoll.pdf', $document['title']);
        $this->assertStringContainsString('/attachments/', $document['url']);

        $appointment = $timeline->firstWhere('type', 'appointment');
        $this->assertSame('Erste Massnahme', $appointment['status']['name']);
        $this->assertStringContainsString('appointment=', $appointment['url']);

        $activity = $timeline->firstWhere('type', 'activity');
        $this->assertSame('created', $activity['action']);
    }

    public function test_field_changes_are_listed_on_activity_events(): void
    {
        $client = $this->fixture();
        $appointment = Appointment::where('start_at', '2026-04-01 10:00:00')->first();

        $this->at('2026-04-07 16:00:00', fn () => $appointment->update(['notes' => 'Nachkontrolle noetig']));

        $updated = collect($this->props($client)['timeline'])->firstWhere('action', 'updated');

        $this->assertNotNull($updated);
        // The payload carries translation keys, not column names — a raw column
        // would render untranslated in the timeline.
        $this->assertSame(['Notes'], $updated['fields']);
    }

    public function test_a_column_with_no_label_is_left_out_of_the_change_list(): void
    {
        $client = $this->fixture();
        $appointment = Appointment::where('start_at', '2026-04-01 10:00:00')->first();

        // created_by is an internal column with no label; notes has one.
        $this->at(
            '2026-04-07 16:00:00',
            fn () => $appointment->update(['created_by' => $this->owner->id, 'notes' => 'Nachkontrolle noetig'])
        );

        $updated = collect($this->props($client)['timeline'])->firstWhere('action', 'updated');

        // Unmapped columns are dropped rather than leaking a database column
        // name into the UI, where it would render untranslated.
        $this->assertSame(['Notes'], $updated['fields']);
        $this->assertNotContains('created_by', $updated['fields']);
    }

    public function test_badges_describe_the_customer(): void
    {
        $client = $this->fixture();

        $this->assertSame(
            ['active_contract', 'new_client', 'kundentermin'],
            array_column($this->props($client)['facts']['badges'], 'key')
        );
    }

    public function test_a_customer_with_only_old_visits_is_dormant(): void
    {
        $client = $this->at('2024-01-05 09:00:00', fn () => $this->client());
        $contract = $this->at('2024-01-05 09:00:00', fn () => $this->contractFor($client));
        $invoiced = Status::factory()->stage(Status::STAGE_INVOICED)->create(['company_id' => $this->company->id]);
        $this->at('2024-01-05 09:00:00', fn () => $this->appointment($contract, '2024-02-01 10:00:00', [
            'status_id' => $invoiced->id,
        ]));

        $badges = array_column($this->props($client)['facts']['badges'], 'key');

        $this->assertContains('dormant', $badges);
        $this->assertNotContains('active_contract', $badges);
        $this->assertNotContains('new_client', $badges);
    }

    public function test_series_facts_describe_the_recurrence_rule(): void
    {
        $client = $this->at('2026-03-02 09:00:00', fn () => $this->client());
        $contract = $this->at('2026-03-02 09:00:00', fn () => $this->contractFor($client));

        $parent = $this->appointment($contract, '2026-04-10 08:00:00', [
            'recurrence_type' => 'biweekly',
            'recurrence_interval' => 2,
            'recurrence_end' => '2026-06-30',
        ]);
        $this->appointment($contract, '2026-04-24 08:00:00', ['parent_id' => $parent->id]);

        $series = $this->props($client)['facts']['series'];

        $this->assertSame('biweekly', $series['recurrence_type']);
        $this->assertSame('2026-06-30', $series['recurrence_end']);
        $this->assertSame(2, $series['occurrences']);
        $this->assertSame($parent->id, $series['appointment_id']);
    }

    public function test_access_notes_ride_along_with_the_facts(): void
    {
        $client = $this->client(['access_notes' => 'Schluesseltresor am Tor, Code 4711.']);

        $this->assertSame('Schluesseltresor am Tor, Code 4711.', $this->props($client)['facts']['access_notes']);
    }

    public function test_access_notes_can_be_saved(): void
    {
        $client = $this->client();

        $this->actingAs($this->owner)
            ->put(route('clients.update', $client->id), [
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'access_notes' => 'Hofhund laeuft frei.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Hofhund laeuft frei.', $client->fresh()->access_notes);
    }

    public function test_an_employee_without_appointment_access_sees_no_history(): void
    {
        $client = $this->fixture();

        $employee = User::factory()->create(['company_id' => $this->company->id]);
        $employee->syncPermissions(['clients.view']);

        $props = $this->props($client, $employee);

        $this->assertSame([], $props['timeline']);
        $this->assertCount(0, $props['upcomingAppointments']);
        $this->assertSame(0, $props['facts']['stats']['appointments']);
        $this->assertNull($props['facts']['next_appointment']);
        // Nothing appointment-derived may be asserted, so no "dormant" guess.
        $this->assertNotContains('dormant', array_column($props['facts']['badges'], 'key'));
    }

    public function test_an_employee_without_client_access_is_forbidden(): void
    {
        $client = $this->client();
        $employee = User::factory()->create(['company_id' => $this->company->id]);
        $employee->syncPermissions(['appointments.view']);

        $this->actingAs($employee)->get(route('clients.show', $client->id))->assertForbidden();
    }

    public function test_a_client_of_another_company_is_not_found(): void
    {
        $otherCompany = Company::factory()->create();
        $stranger = Client::factory()->create(['company_id' => $otherCompany->id]);

        $this->actingAs($this->owner)->get(route('clients.show', $stranger->id))->assertNotFound();
    }

    public function test_the_payload_matches_the_golden_file(): void
    {
        $props = $this->props($this->fixture());

        $this->assertMatchesJsonSnapshot([
            'facts' => $this->stable($props['facts']),
            'timeline' => $this->stable($props['timeline']),
        ], 'client-detail');
    }

    public function test_the_query_count_does_not_grow_with_history(): void
    {
        $client = $this->fixture();
        $contract = $client->contracts()->firstOrFail();

        // Same shape of history, twice the volume: the query count may not move.
        for ($day = 1; $day <= 10; $day++) {
            $this->appointment($contract, sprintf('2026-05-%02d 09:00:00', $day));
        }
        $small = $this->countQueriesFor($client);

        for ($day = 11; $day <= 25; $day++) {
            $this->appointment($contract, sprintf('2026-05-%02d 09:00:00', $day));
        }
        $large = $this->countQueriesFor($client);

        $this->assertSame($small, $large, 'the client page must not issue a query per appointment');
        $this->assertLessThan(40, $large);
    }

    private function countQueriesFor(Client $client): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->props($client);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /**
     * Auto-increment ids survive a transaction rollback, so a golden file may
     * not contain them: they drift with test order. String ids and urls carry
     * them, so they are blanked here; the trait already handles integer ids.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function stable(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->stable($value);
            } elseif (is_string($value) && in_array($key, ['id', 'url'], true)) {
                $data[$key] = preg_replace('/\d+/', '<id>', $value);
            }
        }

        return $data;
    }
}
