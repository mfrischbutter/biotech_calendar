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

class ContractDetailTest extends TestCase
{
    use MatchesJsonSnapshots, RefreshDatabase;

    private const NOW = '2026-04-08 12:00:00';

    private Company $company;

    private User $owner;

    private User $worker;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::NOW);

        $this->company = Company::factory()->create();
        $this->owner = User::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Bjoern',
            'last_name' => 'Wilhelmsen',
            'role' => User::ROLE_OWNER,
        ]);
        $this->worker = User::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Markus',
            'last_name' => 'Weber',
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

    private function at(string $time, callable $callback): mixed
    {
        Carbon::setTestNow($time);
        $result = $callback();
        Carbon::setTestNow(self::NOW);

        return $result;
    }

    private function makeStatus(string $stage, string $name, string $color = '#F59E0B'): Status
    {
        return Status::factory()->stage($stage)->create([
            'company_id' => $this->company->id,
            'name' => $name,
            'color' => $color,
        ]);
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
     * One job: two visits done and invoiced, one still ahead and unassigned,
     * plus the paper trail around them.
     */
    private function fixture(): Contract
    {
        $client = $this->at('2026-03-02 09:00:00', fn () => Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->owner->id,
            'first_name' => 'Klaus',
            'last_name' => 'Bergmann',
            'company_name' => 'Baeckerei Bergmann',
        ]));

        $contract = $this->at('2026-03-02 09:00:00', fn () => Contract::factory()->create([
            'company_id' => $this->company->id,
            'contract_number' => 'A-1000',
            'title' => 'Routinekontrolle',
            'kind' => 'kundentermin',
            'street' => 'Sendlinger Str. 45',
            'zip' => '80331',
            'city' => 'Muenchen',
        ]));
        $contract->clients()->attach($client->id);

        $invoiced = $this->makeStatus(Status::STAGE_INVOICED, 'Abgerechnet', '#6B7280');
        $active = $this->makeStatus(Status::STAGE_ACTIVE, 'Erste Massnahme');

        $done = $this->at('2026-03-02 09:00:00', fn () => $this->appointment($contract, '2026-04-01 10:00:00', [
            'status_id' => $invoiced->id,
        ]));
        $done->workers()->sync([$this->worker->id]);

        $this->at('2026-03-02 10:00:00', fn () => $this->appointment($contract, '2026-04-10 08:00:00', [
            'status_id' => $active->id,
        ]));

        $this->at('2026-04-06 14:00:00', fn () => Comment::create([
            'company_id' => $this->company->id,
            'appointment_id' => $done->id,
            'user_id' => $this->owner->id,
            'body' => 'Koederboxen im Lager kontrolliert.',
        ]));

        $this->at('2026-04-07 11:00:00', fn () => AppointmentAttachment::create([
            'company_id' => $this->company->id,
            'appointment_id' => $done->id,
            'user_id' => $this->owner->id,
            'original_name' => 'Protokoll.pdf',
            'path' => 'appointments/1/documents/protokoll.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ]));

        return $contract;
    }

    /**
     * @return array<string, mixed>
     */
    private function props(Contract $contract, ?User $user = null): array
    {
        $response = $this->actingAs($user ?? $this->owner)->get(route('contracts.show', $contract->id));
        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    public function test_it_returns_stage_progress_and_team(): void
    {
        $facts = $this->props($this->fixture())['facts'];

        // One visit still sits in "active", so that is the stage the job is in.
        $this->assertSame(Status::STAGE_ACTIVE, $facts['stage']);
        $this->assertSame(['done' => 1, 'total' => 2, 'percent' => 50], $facts['progress']);
        $this->assertSame(['Markus Weber'], array_column($facts['team'], 'name'));
        $this->assertSame('Sendlinger Str. 45, 80331 Muenchen', $facts['address']);
        $this->assertSame(1, $facts['stats']['clients']);
        $this->assertSame(2, $facts['stats']['appointments']);
        $this->assertSame(1, $facts['stats']['upcoming']);
    }

    public function test_badges_flag_the_kind_and_unassigned_work(): void
    {
        $badges = array_column($this->props($this->fixture())['facts']['badges'], 'key');

        $this->assertSame(['kundentermin', 'unassigned_work'], $badges);
    }

    public function test_a_recurring_contract_is_badged_and_gets_series_facts(): void
    {
        $contract = $this->fixture();
        $parent = $this->appointment($contract, '2026-05-04 08:00:00', [
            'recurrence_type' => 'weekly',
            'recurrence_interval' => 1,
            'recurrence_end' => '2026-07-31',
        ]);
        $this->appointment($contract, '2026-05-11 08:00:00', ['parent_id' => $parent->id]);

        $facts = $this->props($contract)['facts'];

        $this->assertContains('recurring', array_column($facts['badges'], 'key'));
        $this->assertSame('weekly', $facts['series']['recurrence_type']);
        $this->assertSame('2026-07-31', $facts['series']['recurrence_end']);
        $this->assertSame(2, $facts['series']['occurrences']);
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

    public function test_timeline_only_covers_this_contract(): void
    {
        $contract = $this->fixture();

        $other = Contract::factory()->create([
            'company_id' => $this->company->id,
            'contract_number' => 'A-2000',
            'title' => 'Fremder Auftrag',
        ]);
        $this->appointment($other, '2026-04-09 08:00:00');

        $titles = array_column($this->props($contract)['timeline'], 'title');

        $this->assertNotContains('Fremder Auftrag', $titles);
    }

    public function test_access_notes_ride_along_and_can_be_saved(): void
    {
        $contract = $this->fixture();

        $this->actingAs($this->owner)
            ->put(route('contracts.update', $contract->id), [
                'contract_number' => $contract->contract_number,
                'title' => $contract->title,
                'access_notes' => 'Schluesseltresor am Tor, Code 4711.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'Schluesseltresor am Tor, Code 4711.',
            $this->props($contract->fresh())['facts']['access_notes']
        );
    }

    public function test_an_employee_without_appointment_access_sees_no_history(): void
    {
        $contract = $this->fixture();

        $employee = User::factory()->create(['company_id' => $this->company->id]);
        $employee->syncPermissions(['contracts.view']);

        $props = $this->props($contract, $employee);

        $this->assertSame([], $props['timeline']);
        $this->assertCount(0, $props['upcomingAppointments']);
        $this->assertNull($props['facts']['stage']);
        $this->assertSame(0, $props['facts']['stats']['appointments']);
        $this->assertSame([], $props['facts']['team']);
    }

    public function test_an_employee_without_contract_access_is_forbidden(): void
    {
        $contract = $this->fixture();
        $employee = User::factory()->create(['company_id' => $this->company->id]);
        $employee->syncPermissions(['appointments.view']);

        $this->actingAs($employee)->get(route('contracts.show', $contract->id))->assertForbidden();
    }

    public function test_a_contract_of_another_company_is_not_found(): void
    {
        $otherCompany = Company::factory()->create();
        $stranger = Contract::factory()->create([
            'company_id' => $otherCompany->id,
            'contract_number' => 'X-1',
        ]);

        $this->actingAs($this->owner)->get(route('contracts.show', $stranger->id))->assertNotFound();
    }

    public function test_deleting_from_the_detail_page_lands_on_the_list(): void
    {
        $contract = $this->fixture();

        $this->actingAs($this->owner)
            ->delete(route('contracts.destroy', $contract->id), ['redirect' => 'index'])
            ->assertRedirect(route('contracts.index'));

        $this->assertDatabaseMissing('contracts', ['id' => $contract->id]);
    }

    public function test_the_payload_matches_the_golden_file(): void
    {
        $props = $this->props($this->fixture());

        $this->assertMatchesJsonSnapshot([
            'facts' => $this->stable($props['facts']),
            'timeline' => $this->stable($props['timeline']),
        ], 'contract-detail');
    }

    public function test_the_query_count_does_not_grow_with_history(): void
    {
        $contract = $this->fixture();

        // Same shape of history, twice the volume: the query count may not move.
        for ($day = 1; $day <= 10; $day++) {
            $this->appointment($contract, sprintf('2026-05-%02d 09:00:00', $day));
        }
        $small = $this->countQueriesFor($contract);

        for ($day = 11; $day <= 25; $day++) {
            $this->appointment($contract, sprintf('2026-05-%02d 09:00:00', $day));
        }
        $large = $this->countQueriesFor($contract);

        $this->assertSame($small, $large, 'the contract page must not issue a query per appointment');
        $this->assertLessThan(40, $large);
    }

    private function countQueriesFor(Contract $contract): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->props($contract);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /**
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
