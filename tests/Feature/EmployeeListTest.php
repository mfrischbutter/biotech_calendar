<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Contract;
use App\Models\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MatchesJsonSnapshots;
use Tests\TestCase;

class EmployeeListTest extends TestCase
{
    use MatchesJsonSnapshots, RefreshDatabase;

    private Company $company;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday: "this week" runs Mon 6th to Sun 12th of April 2026.
        Carbon::setTestNow('2026-04-08 12:00:00');

        $this->company = Company::factory()->create();
        StaffRole::seedForCompany($this->company->id);

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

    private function employee(string $first, string $last, array $attributes = []): User
    {
        return User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_EMPLOYEE,
            'first_name' => $first,
            'last_name' => $last,
            ...$attributes,
        ]);
    }

    /** The seeded preset for *this* company — the test database holds others. */
    private function role(string $slug): StaffRole
    {
        return StaffRole::where('company_id', $this->company->id)
            ->where('slug', $slug)
            ->firstOrFail();
    }

    private function booking(User $worker, string $start, int $minutes = 60): void
    {
        $contract = Contract::factory()->create(['company_id' => $this->company->id]);

        Appointment::factory()->at($start, $minutes)->create([
            'company_id' => $this->company->id,
            'contract_id' => $contract->id,
            'created_by' => $this->owner->id,
        ])->workers()->sync([$worker->id]);
    }

    /** @return array<string, mixed> */
    private function props(array $query = []): array
    {
        return $this->actingAs($this->owner)
            ->get('/employees?'.http_build_query($query))
            ->assertOk()
            ->viewData('page')['props'];
    }

    /** @return array<int, string> */
    private function names(array $query = []): array
    {
        return collect($this->props($query)['employees']['data'])
            ->pluck('name')
            ->all();
    }

    public function test_it_sorts_by_name_ascending_by_default(): void
    {
        $this->employee('Anna', 'Zimmer');
        $this->employee('Markus', 'Weber');

        $this->assertSame(['Markus Weber', 'Anna Zimmer'], $this->names());
    }

    public function test_it_sorts_by_name_descending(): void
    {
        $this->employee('Anna', 'Zimmer');
        $this->employee('Markus', 'Weber');

        $this->assertSame(['Anna Zimmer', 'Markus Weber'], $this->names(['sort' => 'name', 'dir' => 'desc']));
    }

    public function test_it_sorts_by_appointments_this_week(): void
    {
        $busy = $this->employee('Anna', 'Zimmer');
        $this->booking($busy, '2026-04-08 09:00');
        $this->booking($busy, '2026-04-09 09:00');

        $quiet = $this->employee('Markus', 'Weber');
        $this->booking($quiet, '2026-04-08 09:00');

        $this->assertSame(
            ['Anna Zimmer', 'Markus Weber'],
            $this->names(['sort' => 'appointments', 'dir' => 'desc'])
        );
    }

    public function test_it_only_counts_appointments_inside_the_current_week(): void
    {
        $markus = $this->employee('Markus', 'Weber');
        $this->booking($markus, '2026-04-08 09:00');
        $this->booking($markus, '2026-04-20 09:00');

        $row = $this->props()['employees']['data'][0];

        $this->assertSame(1, $row['appointments_this_week']);
    }

    public function test_it_reports_utilisation_against_a_forty_hour_week(): void
    {
        $markus = $this->employee('Markus', 'Weber');
        $this->booking($markus, '2026-04-08 09:00', 20 * 60);

        $row = $this->props()['employees']['data'][0];

        $this->assertSame(50, $row['utilisation']);
    }

    public function test_it_filters_by_staff_role(): void
    {
        $techniker = $this->role('techniker');
        $this->employee('Markus', 'Weber', ['staff_role_id' => $techniker->id]);
        $this->employee('Anna', 'Zimmer');

        $this->assertSame(['Markus Weber'], $this->names(['role' => 'techniker']));
    }

    public function test_it_filters_employees_without_a_role(): void
    {
        $techniker = $this->role('techniker');
        $this->employee('Markus', 'Weber', ['staff_role_id' => $techniker->id]);
        $this->employee('Anna', 'Zimmer');

        $this->assertSame(['Anna Zimmer'], $this->names(['role' => 'none']));
    }

    public function test_it_flags_permissions_that_no_longer_match_the_role(): void
    {
        $techniker = $this->role('techniker');
        $markus = $this->employee('Markus', 'Weber', ['staff_role_id' => $techniker->id]);
        $markus->syncPermissions(['dashboard.view']);

        $row = $this->props()['employees']['data'][0];

        $this->assertTrue($row['has_custom_permissions']);
        $this->assertSame('techniker', $row['staff_role']['slug']);
    }

    public function test_it_searches_name_and_email(): void
    {
        $this->employee('Markus', 'Weber', ['email' => 'markus@wilhelmsen-biotec.de']);
        $this->employee('Anna', 'Zimmer', ['email' => 'anna@wilhelmsen-biotec.de']);

        $this->assertSame(['Markus Weber'], $this->names(['search' => 'markus@']));
        $this->assertSame(['Anna Zimmer'], $this->names(['search' => 'Zimmer']));
    }

    public function test_it_rejects_an_unknown_role_filter(): void
    {
        $this->actingAs($this->owner)->get('/employees?role=chef')->assertSessionHasErrors('role');
    }

    public function test_it_rejects_an_unknown_sort_key(): void
    {
        $this->actingAs($this->owner)->get('/employees?sort=password')->assertSessionHasErrors('sort');
    }

    public function test_only_owners_may_open_the_employee_list(): void
    {
        $employee = $this->employee('Markus', 'Weber');

        $this->actingAs($employee)->get('/employees')->assertForbidden();
    }

    public function test_it_never_shows_employees_from_another_company(): void
    {
        $this->employee('Markus', 'Weber');

        $other = Company::factory()->create();
        User::factory()->create([
            'company_id' => $other->id,
            'role' => User::ROLE_EMPLOYEE,
            'first_name' => 'Fremder',
            'last_name' => 'Kollege',
        ]);

        $this->assertSame(['Markus Weber'], $this->names());
    }

    public function test_the_list_payload_shape_is_stable(): void
    {
        $techniker = $this->role('techniker');
        $markus = $this->employee('Markus', 'Weber', [
            'email' => 'markus@wilhelmsen-biotec.de',
            'staff_role_id' => $techniker->id,
        ]);
        $markus->syncPermissions($techniker->permissions);
        $this->booking($markus, '2026-04-08 09:00', 4 * 60);

        $props = $this->props();

        $this->assertMatchesJsonSnapshot([
            'data' => $props['employees']['data'],
            'filters' => $props['filters'],
        ], 'employee-list');
    }
}
