<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Status;
use App\Models\User;
use App\Queries\ContractListQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MatchesJsonSnapshots;
use Tests\TestCase;

class ContractListTest extends TestCase
{
    use MatchesJsonSnapshots, RefreshDatabase;

    private Company $company;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-04-08 12:00:00');

        $this->company = Company::factory()->create();
        $this->owner = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_OWNER,
            'first_name' => 'Bjoern',
            'last_name' => 'Wilhelmsen',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function contract(string $number, string $title, array $attributes = []): Contract
    {
        return Contract::factory()->create([
            'company_id' => $this->company->id,
            'contract_number' => $number,
            'title' => $title,
            ...$attributes,
        ]);
    }

    private function makeStatus(string $stage): Status
    {
        return Status::factory()->stage($stage)->create(['company_id' => $this->company->id]);
    }

    private function appointment(Contract $contract, ?Status $status = null, array $workers = [], string $at = '2026-04-09 09:00'): Appointment
    {
        $appointment = Appointment::factory()->at($at)->create([
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

    /** @return array<string, mixed> */
    private function props(array $query = []): array
    {
        return $this->actingAs($this->owner)
            ->get('/contracts?'.http_build_query($query))
            ->assertOk()
            ->viewData('page')['props'];
    }

    /** @return array<int, string> */
    private function numbers(array $query = []): array
    {
        return collect($this->props($query)['contracts']['data'])
            ->pluck('contract_number')
            ->all();
    }

    public function test_it_sorts_by_contract_number_by_default(): void
    {
        $this->contract('A-3000', 'Nachkontrolle');
        $this->contract('A-1000', 'Erstbegehung');

        $this->assertSame(['A-1000', 'A-3000'], $this->numbers());
    }

    public function test_it_sorts_by_title(): void
    {
        $this->contract('A-3000', 'Akutbehandlung');
        $this->contract('A-1000', 'Zwischenkontrolle');

        $this->assertSame(['A-3000', 'A-1000'], $this->numbers(['sort' => 'title']));
    }

    public function test_it_sorts_by_appointment_count(): void
    {
        $busy = $this->contract('A-1000', 'Erstbegehung');
        $this->appointment($busy);
        $this->appointment($busy);
        $this->contract('A-2000', 'Nachkontrolle');

        $this->assertSame(['A-1000', 'A-2000'], $this->numbers(['sort' => 'appointments', 'dir' => 'desc']));
    }

    public function test_a_stage_view_only_lists_contracts_with_work_in_that_stage(): void
    {
        $ready = $this->contract('A-1000', 'Erstbegehung');
        $this->appointment($ready, $this->makeStatus(Status::STAGE_READY_TO_INVOICE));

        $running = $this->contract('A-2000', 'Nachkontrolle');
        $this->appointment($running, $this->makeStatus(Status::STAGE_ACTIVE));

        $this->assertSame(['A-1000'], $this->numbers(['view' => Status::STAGE_READY_TO_INVOICE]));
        $this->assertSame(['A-2000'], $this->numbers(['view' => Status::STAGE_ACTIVE]));
    }

    public function test_the_overdue_view_lists_jobs_whose_visit_is_over_and_still_open(): void
    {
        $hanging = $this->contract('A-1000', 'Erstbegehung');
        $this->appointment($hanging, $this->makeStatus(Status::STAGE_ACTIVE), [], '2026-04-01 09:00');

        $closed = $this->contract('A-2000', 'Nachkontrolle');
        $this->appointment($closed, $this->makeStatus(Status::STAGE_INVOICED), [], '2026-04-01 09:00');

        $upcoming = $this->contract('A-3000', 'Abschluss');
        $this->appointment($upcoming, $this->makeStatus(Status::STAGE_ACTIVE), [], '2026-04-20 09:00');

        $this->assertSame(['A-1000'], $this->numbers(['view' => ContractListQuery::VIEW_OVERDUE]));
    }

    public function test_a_job_with_two_hanging_visits_is_listed_once(): void
    {
        $contract = $this->contract('A-1000', 'Erstbegehung');
        $this->appointment($contract, null, [], '2026-04-01 09:00');
        $this->appointment($contract, null, [], '2026-04-02 09:00');

        $props = $this->props(['view' => ContractListQuery::VIEW_OVERDUE]);

        $this->assertSame(['A-1000'], collect($props['contracts']['data'])->pluck('contract_number')->all());
        $this->assertSame(1, $props['contracts']['total']);
    }

    public function test_it_reports_a_count_for_every_stage_tab(): void
    {
        $this->appointment($this->contract('A-1000', 'Erstbegehung'), $this->makeStatus(Status::STAGE_ACTIVE));
        $this->appointment($this->contract('A-2000', 'Nachkontrolle'), $this->makeStatus(Status::STAGE_ACTIVE));
        $this->appointment($this->contract('A-3000', 'Abschluss'), $this->makeStatus(Status::STAGE_INVOICED));

        $counts = collect($this->props()['stageCounts'])->pluck('count', 'stage')->all();

        $this->assertSame(3, $counts['all']);
        $this->assertSame(2, $counts[Status::STAGE_ACTIVE]);
        $this->assertSame(1, $counts[Status::STAGE_INVOICED]);
        $this->assertSame(0, $counts[Status::STAGE_UNCONFIRMED]);
    }

    public function test_the_stage_counts_follow_the_search_term(): void
    {
        $this->appointment($this->contract('A-1000', 'Erstbegehung'), $this->makeStatus(Status::STAGE_ACTIVE));
        $this->appointment($this->contract('A-2000', 'Nachkontrolle'), $this->makeStatus(Status::STAGE_ACTIVE));

        $counts = collect($this->props(['search' => 'Erstbegehung'])['stageCounts'])
            ->pluck('count', 'stage')
            ->all();

        $this->assertSame(1, $counts['all']);
        $this->assertSame(1, $counts[Status::STAGE_ACTIVE]);
    }

    public function test_it_reports_progress_from_the_completed_stages(): void
    {
        $contract = $this->contract('A-1000', 'Erstbegehung');
        $this->appointment($contract, $this->makeStatus(Status::STAGE_INVOICED));
        $this->appointment($contract, $this->makeStatus(Status::STAGE_ACTIVE));

        $row = $this->props()['contracts']['data'][0];

        $this->assertSame(['done' => 1, 'total' => 2, 'percent' => 50], $row['progress']);
    }

    /*
     * A visit that was called off is not outstanding work. Counting it in the
     * denominator left a cancelled one-visit job reading "0/1" with an empty
     * bar — the same picture as a job nobody has started.
     */
    public function test_a_cancelled_visit_leaves_the_progress_denominator(): void
    {
        $contract = $this->contract('A-1000', 'Erstbegehung');
        $this->appointment($contract, $this->makeStatus(Status::STAGE_INVOICED));
        $this->appointment($contract, $this->makeStatus(Status::STAGE_CANCELLED));

        $row = $this->props()['contracts']['data'][0];

        $this->assertSame(['done' => 1, 'total' => 1, 'percent' => 100], $row['progress']);
    }

    public function test_a_job_whose_only_visit_was_called_off_has_nothing_left_to_track(): void
    {
        $contract = $this->contract('A-1000', 'Erstbegehung');
        $this->appointment($contract, $this->makeStatus(Status::STAGE_CANCELLED));

        $this->assertSame(
            ['done' => 0, 'total' => 0, 'percent' => 0],
            $this->props()['contracts']['data'][0]['progress'],
        );
    }

    // Sorting by "Termine" still means every visit ever booked, cancelled ones included.
    public function test_the_appointment_count_column_still_counts_cancelled_visits(): void
    {
        $busy = $this->contract('A-1000', 'Erstbegehung');
        $this->appointment($busy, $this->makeStatus(Status::STAGE_CANCELLED));
        $this->appointment($busy, $this->makeStatus(Status::STAGE_CANCELLED));
        $this->appointment($this->contract('A-2000', 'Nachkontrolle'));

        $this->assertSame(['A-1000', 'A-2000'], $this->numbers(['sort' => 'appointments', 'dir' => 'desc']));
    }

    public function test_the_current_stage_is_the_earliest_stage_still_open(): void
    {
        $contract = $this->contract('A-1000', 'Erstbegehung');
        $this->appointment($contract, $this->makeStatus(Status::STAGE_INVOICED));
        $this->appointment($contract, $this->makeStatus(Status::STAGE_UNCONFIRMED));

        $row = $this->props()['contracts']['data'][0];

        $this->assertSame(Status::STAGE_UNCONFIRMED, $row['stage']);
    }

    public function test_it_lists_the_team_booked_on_the_contract(): void
    {
        $markus = User::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Markus',
            'last_name' => 'Weber',
        ]);

        $contract = $this->contract('A-1000', 'Erstbegehung');
        $this->appointment($contract, null, [$markus->id]);
        $this->appointment($contract, null, [$markus->id]);

        $row = $this->props()['contracts']['data'][0];

        $this->assertCount(1, $row['team']);
        $this->assertSame('Markus Weber', $row['team'][0]['name']);
    }

    public function test_the_list_does_not_run_one_query_per_contract(): void
    {
        $this->seedContracts(3);
        $small = $this->countQueries();

        $this->seedContracts(9, 'B');
        $large = $this->countQueries();

        $this->assertSame(
            $small,
            $large,
            'Contract list query count grew with the number of rows — progress counts must stay subqueries.'
        );
    }

    private function seedContracts(int $count, string $prefix = 'A'): void
    {
        for ($i = 0; $i < $count; $i++) {
            $contract = $this->contract("{$prefix}-".(1000 + $i), "Auftrag {$prefix}{$i}");
            $this->appointment($contract, $this->makeStatus(Status::STAGE_ACTIVE));
        }
    }

    private function countQueries(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->owner)->get('/contracts')->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_it_searches_contract_number_title_and_client(): void
    {
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->owner->id,
            'first_name' => 'Klaus',
            'last_name' => 'Bergmann',
        ]);

        $withClient = $this->contract('A-1000', 'Erstbegehung');
        $withClient->clients()->attach($client->id);
        $this->contract('A-2000', 'Nachkontrolle');

        $this->assertSame(['A-1000'], $this->numbers(['search' => 'Bergmann']));
        $this->assertSame(['A-2000'], $this->numbers(['search' => 'Nachkontrolle']));
    }

    public function test_it_rejects_an_unknown_sort_key(): void
    {
        $this->actingAs($this->owner)->get('/contracts?sort=secret')->assertSessionHasErrors('sort');
    }

    public function test_an_employee_without_the_permission_is_refused(): void
    {
        $employee = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_EMPLOYEE,
        ]);

        $this->actingAs($employee)->get('/contracts')->assertForbidden();
    }

    public function test_it_never_shows_contracts_from_another_company(): void
    {
        $this->contract('A-1000', 'Erstbegehung');

        $other = Company::factory()->create();
        Contract::factory()->create(['company_id' => $other->id, 'contract_number' => 'Z-9999']);

        $this->assertSame(['A-1000'], $this->numbers());
    }

    public function test_the_list_payload_shape_is_stable(): void
    {
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->owner->id,
            'first_name' => 'Klaus',
            'last_name' => 'Bergmann',
            'company_name' => 'Baeckerei Bergmann',
        ]);

        $markus = User::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Markus',
            'last_name' => 'Weber',
        ]);

        $contract = $this->contract('A-1000', 'Erstbegehung', [
            'kind' => 'kundentermin',
            'description' => 'Quartalsweise Kontrolle',
            'street' => 'Hauptstrasse 1',
            'zip' => '80331',
            'city' => 'München',
        ]);
        $contract->clients()->attach($client->id);
        $this->appointment($contract, $this->makeStatus(Status::STAGE_ACTIVE), [$markus->id]);

        $props = $this->props();

        $this->assertMatchesJsonSnapshot([
            'data' => $props['contracts']['data'],
            'stageCounts' => $props['stageCounts'],
            'filters' => $props['filters'],
        ], 'contract-list');
    }
}
