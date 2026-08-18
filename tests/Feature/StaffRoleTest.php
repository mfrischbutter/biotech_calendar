<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffRoleTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        StaffRole::seedForCompany($this->company->id);

        $this->owner = User::factory()->create([
            'company_id' => $this->company->id, 'role' => User::ROLE_OWNER,
        ]);
        $this->employee = User::factory()->create([
            'company_id' => $this->company->id, 'role' => User::ROLE_EMPLOYEE,
        ]);
    }

    private function role(string $slug): StaffRole
    {
        return StaffRole::withoutGlobalScope('company')
            ->where('company_id', $this->company->id)
            ->where('slug', $slug)
            ->firstOrFail();
    }

    public function test_every_company_gets_the_four_preset_roles(): void
    {
        $slugs = StaffRole::withoutGlobalScope('company')
            ->where('company_id', $this->company->id)
            ->orderBy('sort_order')
            ->pluck('slug')
            ->all();

        $this->assertSame(
            [StaffRole::SLUG_TECHNICIAN, StaffRole::SLUG_LEAD, StaffRole::SLUG_OFFICE, StaffRole::SLUG_ADMIN],
            $slugs,
        );
    }

    public function test_seeding_twice_does_not_duplicate_roles(): void
    {
        StaffRole::seedForCompany($this->company->id);

        $this->assertSame(4, StaffRole::withoutGlobalScope('company')
            ->where('company_id', $this->company->id)->count());
    }

    public function test_the_admin_preset_grants_every_permission(): void
    {
        $this->assertSame(
            array_keys(Permission::ALL),
            $this->role(StaffRole::SLUG_ADMIN)->permissions,
        );
    }

    public function test_a_technician_cannot_delete_appointments(): void
    {
        $this->assertNotContains('appointments.delete', $this->role(StaffRole::SLUG_TECHNICIAN)->permissions);
    }

    public function test_applying_a_role_grants_its_permissions(): void
    {
        $role = $this->role(StaffRole::SLUG_TECHNICIAN);

        $this->employee->applyStaffRole($role);
        $this->employee->refresh();

        $this->assertSame($role->id, $this->employee->staff_role_id);
        $this->assertTrue($this->employee->hasPermission('appointments.view'));
        $this->assertFalse($this->employee->hasPermission('appointments.delete'));
    }

    public function test_applying_a_role_replaces_any_previous_permissions(): void
    {
        $this->employee->syncPermissions(array_keys(Permission::ALL));
        $this->employee->applyStaffRole($this->role(StaffRole::SLUG_TECHNICIAN));
        $this->employee->refresh();

        $this->assertFalse($this->employee->hasPermission('settings.edit'));
    }

    public function test_a_freshly_assigned_role_is_not_marked_as_customised(): void
    {
        $this->employee->applyStaffRole($this->role(StaffRole::SLUG_TECHNICIAN));

        $this->assertFalse($this->employee->fresh()->hasCustomPermissions());
    }

    public function test_hand_tuning_a_permission_marks_the_user_as_customised(): void
    {
        $role = $this->role(StaffRole::SLUG_TECHNICIAN);
        $this->employee->applyStaffRole($role);

        $this->employee->syncPermissions([...$role->permissions, 'appointments.delete']);

        $this->assertTrue($this->employee->fresh()->hasCustomPermissions());
    }

    public function test_a_user_without_a_role_is_never_marked_customised(): void
    {
        $this->employee->syncPermissions(['appointments.view']);

        $this->assertFalse($this->employee->fresh()->hasCustomPermissions());
    }

    public function test_permission_order_does_not_affect_the_customised_flag(): void
    {
        $role = $this->role(StaffRole::SLUG_TECHNICIAN);
        $this->employee->applyStaffRole($role);

        $this->employee->syncPermissions(array_reverse($role->permissions));

        $this->assertFalse($this->employee->fresh()->hasCustomPermissions());
    }

    /* ---------------- endpoint ---------------- */

    public function test_an_owner_can_assign_a_role_through_the_endpoint(): void
    {
        $role = $this->role(StaffRole::SLUG_OFFICE);

        $this->actingAs($this->owner)
            ->put("/employees/{$this->employee->id}/role", ['staff_role_id' => $role->id])
            ->assertRedirect();

        $this->assertSame($role->id, $this->employee->fresh()->staff_role_id);
    }

    public function test_owner_permissions_cannot_be_changed_by_role_assignment(): void
    {
        $other = User::factory()->create([
            'company_id' => $this->company->id, 'role' => User::ROLE_OWNER,
        ]);

        $this->actingAs($this->owner)
            ->put("/employees/{$other->id}/role", ['staff_role_id' => $this->role(StaffRole::SLUG_TECHNICIAN)->id])
            ->assertForbidden();
    }

    public function test_a_role_from_another_company_is_rejected(): void
    {
        $otherCompany = Company::factory()->create();
        StaffRole::seedForCompany($otherCompany->id);
        $foreignRole = StaffRole::withoutGlobalScope('company')
            ->where('company_id', $otherCompany->id)->first();

        // 404 rather than 403: the company scope means the role simply does not
        // exist for this user, which is also the answer that leaks the least.
        $this->actingAs($this->owner)
            ->put("/employees/{$this->employee->id}/role", ['staff_role_id' => $foreignRole->id])
            ->assertNotFound();

        $this->assertNull($this->employee->fresh()->staff_role_id);
    }

    public function test_employees_cannot_assign_roles(): void
    {
        $this->actingAs($this->employee)
            ->put("/employees/{$this->employee->id}/role", ['staff_role_id' => $this->role(StaffRole::SLUG_ADMIN)->id])
            ->assertForbidden();
    }

    /* ---------------- tenant isolation ---------------- */

    /**
     * `User` has no company global scope, so route-model binding will happily
     * resolve an account from another tenant. Every endpoint that writes to a
     * bound user has to check the company itself — otherwise an owner could
     * rewrite a stranger's permissions using their own role ids.
     */
    /**
     * Permission carries a company global scope, so reading a stranger's rows
     * back while acting as our own owner would return nothing no matter what
     * the endpoint did. Ask the table directly.
     *
     * @return array<int, string>
     */
    private function rawPermissions(User $user): array
    {
        return Permission::withoutGlobalScope('company')
            ->where('user_id', $user->id)
            ->orderBy('permission')
            ->pluck('permission')
            ->all();
    }

    private function employeeOfAnotherCompany(): User
    {
        $otherCompany = Company::factory()->create();
        StaffRole::seedForCompany($otherCompany->id);

        return User::factory()->create([
            'company_id' => $otherCompany->id, 'role' => User::ROLE_EMPLOYEE,
        ]);
    }

    public function test_a_role_cannot_be_pushed_onto_another_companys_employee(): void
    {
        $stranger = $this->employeeOfAnotherCompany();
        $stranger->syncPermissions(['appointments.view']);

        $this->actingAs($this->owner)
            ->put("/employees/{$stranger->id}/role", [
                'staff_role_id' => $this->role(StaffRole::SLUG_ADMIN)->id,
            ])
            ->assertForbidden();

        $stranger->refresh();
        $this->assertNull($stranger->staff_role_id);
        $this->assertSame(['appointments.view'], $this->rawPermissions($stranger));
    }

    public function test_permissions_cannot_be_rewritten_on_another_companys_employee(): void
    {
        $stranger = $this->employeeOfAnotherCompany();
        $stranger->syncPermissions(['appointments.view']);

        $this->actingAs($this->owner)
            ->put("/employees/{$stranger->id}/permissions", [
                'permissions' => array_keys(Permission::ALL),
            ])
            ->assertForbidden();

        $this->assertSame(['appointments.view'], $this->rawPermissions($stranger));
    }

    public function test_another_companys_employee_cannot_be_deleted(): void
    {
        $stranger = $this->employeeOfAnotherCompany();

        $this->actingAs($this->owner)
            ->delete("/employees/{$stranger->id}")
            ->assertForbidden();

        $this->assertNotNull($stranger->fresh());
    }

    /**
     * Workload figures are asserted by value, against a frozen clock, in
     * EmployeeListTest. This test owns the role vocabulary the picker renders.
     */
    public function test_the_employees_screen_offers_every_role_preset_the_picker_needs(): void
    {
        $this->employee->applyStaffRole($this->role(StaffRole::SLUG_TECHNICIAN));

        $props = $this->actingAs($this->owner)
            ->get('/employees')
            ->assertOk()
            ->viewData('page')['props'];

        // The picker lists them in sort order, least to most privileged.
        $this->assertSame(
            [StaffRole::SLUG_TECHNICIAN, StaffRole::SLUG_LEAD, StaffRole::SLUG_OFFICE, StaffRole::SLUG_ADMIN],
            collect($props['roles'])->pluck('slug')->all()
        );

        $technician = collect($props['roles'])->firstWhere('slug', StaffRole::SLUG_TECHNICIAN);
        $this->assertSame('Techniker', $technician['name']);
        $this->assertNotEmpty($technician['description']);
        $this->assertContains('appointments.view', $technician['permissions']);
        $this->assertNotContains('appointments.delete', $technician['permissions']);

        $row = collect($props['employees']['data'])->firstWhere('id', $this->employee->id);
        $this->assertSame('Techniker', $row['staff_role']['name']);
        $this->assertSame(StaffRole::SLUG_TECHNICIAN, $row['staff_role']['slug']);
        $this->assertFalse($row['has_custom_permissions']);
    }
}
