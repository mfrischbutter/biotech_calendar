<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MatchesJsonSnapshots;
use Tests\TestCase;

class ClientListTest extends TestCase
{
    use MatchesJsonSnapshots, RefreshDatabase;

    private Company $company;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        // "Dormant" and "next appointment" are both relative to now.
        Carbon::setTestNow('2026-04-08 12:00:00');

        $this->company = Company::factory()->create();
        $this->owner = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_OWNER,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function client(string $first, string $last, array $attributes = []): Client
    {
        return Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->owner->id,
            'first_name' => $first,
            'last_name' => $last,
            ...$attributes,
        ]);
    }

    private function contractFor(Client $client, string $number = 'A-1000'): Contract
    {
        $contract = Contract::factory()->create([
            'company_id' => $this->company->id,
            'contract_number' => $number,
        ]);
        $contract->clients()->attach($client->id);

        return $contract;
    }

    private function appointment(Contract $contract, string $start, ?Status $status = null): Appointment
    {
        return Appointment::factory()->at($start)->create([
            'company_id' => $this->company->id,
            'contract_id' => $contract->id,
            'created_by' => $this->owner->id,
            'status_id' => $status?->id,
        ]);
    }

    private function makeStatus(string $stage): Status
    {
        return Status::factory()->stage($stage)->create(['company_id' => $this->company->id]);
    }

    /** @return array<string, mixed> */
    private function props(array $query = []): array
    {
        return $this->actingAs($this->owner)
            ->get('/clients?'.http_build_query($query))
            ->assertOk()
            ->viewData('page')['props'];
    }

    /** @return array<int, string> */
    private function names(array $query = []): array
    {
        return collect($this->props($query)['clients']['data'])
            ->pluck('name')
            ->all();
    }

    public function test_it_sorts_by_name_ascending_by_default(): void
    {
        $this->client('Anna', 'Zimmer');
        $this->client('Klaus', 'Bergmann');

        $this->assertSame(['Klaus Bergmann', 'Anna Zimmer'], $this->names());
    }

    public function test_it_sorts_by_name_descending(): void
    {
        $this->client('Anna', 'Zimmer');
        $this->client('Klaus', 'Bergmann');

        $this->assertSame(['Anna Zimmer', 'Klaus Bergmann'], $this->names(['sort' => 'name', 'dir' => 'desc']));
    }

    public function test_it_sorts_by_city(): void
    {
        $this->client('Anna', 'Zimmer', ['city' => 'Augsburg']);
        $this->client('Klaus', 'Bergmann', ['city' => 'Zwiesel']);

        $this->assertSame(['Anna Zimmer', 'Klaus Bergmann'], $this->names(['sort' => 'city']));
    }

    public function test_it_sorts_by_open_contract_count(): void
    {
        $busy = $this->client('Anna', 'Zimmer');
        $this->contractFor($busy, 'A-1');
        $this->contractFor($busy, 'A-2');
        $this->client('Klaus', 'Bergmann');

        $this->assertSame(
            ['Anna Zimmer', 'Klaus Bergmann'],
            $this->names(['sort' => 'open_contracts', 'dir' => 'desc'])
        );
    }

    public function test_it_sorts_by_next_appointment(): void
    {
        $soon = $this->client('Anna', 'Zimmer');
        $this->appointment($this->contractFor($soon, 'A-1'), '2026-04-09 09:00');

        $later = $this->client('Klaus', 'Bergmann');
        $this->appointment($this->contractFor($later, 'A-2'), '2026-04-20 09:00');

        $this->assertSame(['Anna Zimmer', 'Klaus Bergmann'], $this->names(['sort' => 'next_appointment']));
    }

    public function test_it_counts_only_contracts_that_still_have_work(): void
    {
        $client = $this->client('Anna', 'Zimmer');
        $this->contractFor($client, 'A-1'); // no appointments yet — still open

        $done = $this->contractFor($client, 'A-2');
        $this->appointment($done, '2026-03-01 09:00', $this->makeStatus(Status::STAGE_INVOICED));

        $rows = $this->props()['clients']['data'];

        $this->assertSame(1, $rows[0]['open_contracts']);
    }

    public function test_the_active_contracts_view_hides_clients_without_open_work(): void
    {
        $open = $this->client('Anna', 'Zimmer');
        $this->contractFor($open, 'A-1');

        $closed = $this->client('Klaus', 'Bergmann');
        $this->appointment(
            $this->contractFor($closed, 'A-2'),
            '2026-03-01 09:00',
            $this->makeStatus(Status::STAGE_INVOICED)
        );

        $this->assertSame(['Anna Zimmer'], $this->names(['view' => 'active_contracts']));
    }

    public function test_the_dormant_view_lists_clients_with_no_appointment_in_90_days(): void
    {
        $quiet = $this->client('Anna', 'Zimmer');
        $this->appointment($this->contractFor($quiet, 'A-1'), '2025-11-01 09:00');

        $recent = $this->client('Klaus', 'Bergmann');
        $this->appointment($this->contractFor($recent, 'A-2'), '2026-04-01 09:00');

        $this->assertSame(['Anna Zimmer'], $this->names(['view' => 'dormant']));
    }

    public function test_it_searches_across_name_company_and_city(): void
    {
        $this->client('Anna', 'Zimmer', ['company_name' => 'Baeckerei Bergmann']);
        $this->client('Klaus', 'Mueller', ['company_name' => null, 'city' => 'Kempten']);

        $this->assertSame(['Anna Zimmer'], $this->names(['search' => 'Baeckerei']));
        $this->assertSame(['Klaus Mueller'], $this->names(['search' => 'Kempten']));
    }

    public function test_it_rejects_an_unknown_sort_key(): void
    {
        $this->actingAs($this->owner)
            ->get('/clients?sort=password')
            ->assertSessionHasErrors('sort');
    }

    public function test_it_rejects_an_unknown_view(): void
    {
        $this->actingAs($this->owner)
            ->get('/clients?view=everything')
            ->assertSessionHasErrors('view');
    }

    public function test_an_employee_without_the_permission_is_refused(): void
    {
        $employee = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_EMPLOYEE,
        ]);

        $this->actingAs($employee)->get('/clients')->assertForbidden();
    }

    public function test_it_never_shows_clients_from_another_company(): void
    {
        $this->client('Anna', 'Zimmer');

        $other = Company::factory()->create();
        $otherOwner = User::factory()->create(['company_id' => $other->id, 'role' => User::ROLE_OWNER]);
        Client::factory()->create([
            'company_id' => $other->id,
            'user_id' => $otherOwner->id,
            'first_name' => 'Fremde',
            'last_name' => 'Firma',
        ]);

        $this->assertSame(['Anna Zimmer'], $this->names());
    }

    public function test_the_list_payload_shape_is_stable(): void
    {
        $client = $this->client('Klaus', 'Bergmann', [
            'company_name' => 'Baeckerei Bergmann',
            'billing_name' => 'Bergmann GmbH',
            'salutation' => 'Herr',
            'street' => 'Hauptstrasse 1',
            'zip' => '80331',
            'city' => 'München',
            'phone' => '089 123456',
            'email' => 'klaus@bergmann.de',
        ]);
        $this->appointment($this->contractFor($client, 'A-1'), '2026-04-09 09:00');

        $props = $this->props();

        $this->assertMatchesJsonSnapshot([
            'data' => $props['clients']['data'],
            'filters' => $props['filters'],
        ], 'client-list');
    }
}
