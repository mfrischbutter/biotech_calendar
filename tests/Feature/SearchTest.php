<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MatchesJsonSnapshots;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use MatchesJsonSnapshots, RefreshDatabase;

    private Company $company;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->owner = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_OWNER,
        ]);

        Client::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Klaus', 'last_name' => 'Bergmann',
            'company_name' => 'Baeckerei Bergmann', 'city' => 'Muenchen',
        ]);
        Client::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Sabine', 'last_name' => 'Gruber',
            'company_name' => 'Restaurant Gruber', 'city' => 'Muenchen',
        ]);

        $contract = Contract::factory()->create([
            'company_id' => $this->company->id,
            'contract_number' => 'A-0042',
            'title' => 'Routinekontrolle Bergmann',
        ]);

        Appointment::factory()->at('2026-04-08 09:00')->create([
            'company_id' => $this->company->id,
            'contract_id' => $contract->id,
        ]);
    }

    private function search(string $q, ?User $as = null): array
    {
        return $this->actingAs($as ?? $this->owner)
            ->getJson('/search?q='.urlencode($q))
            ->assertOk()
            ->json();
    }

    private function groupItems(array $payload, string $key): array
    {
        foreach ($payload['groups'] as $group) {
            if ($group['key'] === $key) {
                return $group['items'];
            }
        }

        return [];
    }

    public function test_it_finds_clients_by_last_name(): void
    {
        $items = $this->groupItems($this->search('Bergmann'), 'clients');

        $this->assertCount(1, $items);
        $this->assertSame('Klaus Bergmann', $items[0]['title']);
    }

    public function test_it_finds_clients_by_company_name(): void
    {
        $items = $this->groupItems($this->search('Baeckerei'), 'clients');

        $this->assertSame('Klaus Bergmann', $items[0]['title']);
    }

    public function test_it_finds_contracts_by_number(): void
    {
        $items = $this->groupItems($this->search('A-0042'), 'contracts');

        $this->assertCount(1, $items);
        $this->assertSame('A-0042', $items[0]['subtitle']);
    }

    public function test_it_finds_appointments_through_their_contract(): void
    {
        $items = $this->groupItems($this->search('Routinekontrolle'), 'appointments');

        $this->assertCount(1, $items);
        $this->assertStringContainsString('appointment=', $items[0]['url']);
    }

    public function test_actions_are_offered_even_with_an_empty_query(): void
    {
        $payload = $this->search('');

        $actions = $this->groupItems($payload, 'actions');
        $this->assertNotEmpty($actions, 'Opening the search field should already offer something to do.');
        $this->assertSame([], $this->groupItems($payload, 'clients'));
    }

    public function test_matching_a_client_offers_a_shortcut_to_book_them(): void
    {
        $actions = $this->groupItems($this->search('Bergmann'), 'actions');

        $titles = array_column($actions, 'title');
        $this->assertContains('New appointment for Klaus Bergmann', $titles);
    }

    public function test_employees_only_see_what_their_permissions_allow(): void
    {
        $employee = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_EMPLOYEE,
        ]);
        $employee->syncPermissions(['appointments.view']);

        $payload = $this->search('Bergmann', $employee);

        $this->assertSame([], $this->groupItems($payload, 'clients'), 'No clients.view permission.');
        $this->assertSame([], $this->groupItems($payload, 'contracts'), 'No contracts.view permission.');
        $this->assertSame([], $this->groupItems($payload, 'actions'), 'No create permissions.');
    }

    public function test_search_never_leaks_across_companies(): void
    {
        $other = Company::factory()->create();
        Client::factory()->create([
            'company_id' => $other->id,
            'first_name' => 'Fremd', 'last_name' => 'Bergmann',
        ]);

        $items = $this->groupItems($this->search('Bergmann'), 'clients');

        $this->assertCount(1, $items);
        $this->assertSame('Klaus Bergmann', $items[0]['title']);
    }

    public function test_a_query_matching_nothing_returns_only_actions_or_nothing(): void
    {
        $payload = $this->search('zzzzz');

        $this->assertSame([], $this->groupItems($payload, 'clients'));
        $this->assertSame([], $this->groupItems($payload, 'contracts'));
        $this->assertSame([], $this->groupItems($payload, 'appointments'));
    }

    public function test_guests_cannot_search(): void
    {
        $this->getJson('/search?q=Bergmann')->assertUnauthorized();
    }

    public function test_the_response_shape_matches_the_golden_file(): void
    {
        $payload = $this->search('Bergmann');

        // URLs and generated action slugs embed database ids, which vary per run.
        array_walk_recursive($payload, function (&$value, $key) {
            if ($key === 'url' || ($key === 'id' && is_string($value))) {
                $value = preg_replace('/\d+/', '<id>', (string) $value);
            }
        });

        $this->assertMatchesJsonSnapshot($payload, 'search-bergmann');
    }
}
