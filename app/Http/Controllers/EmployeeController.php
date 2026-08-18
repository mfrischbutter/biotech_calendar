<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\StaffRole;
use App\Models\User;
use App\Queries\EmployeeListQuery;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function index(Request $request, EmployeeListQuery $list)
    {
        $filters = $request->validate(EmployeeListQuery::rules());

        return Inertia::render('Employees/Index', [
            'employees' => $list->paginate($filters, $request->user()->company_id),
            'availablePermissions' => Permission::ALL,
            'roles' => StaffRole::ordered()->get()->map(fn (StaffRole $r) => [
                'id' => $r->id,
                'slug' => $r->slug,
                'name' => $r->name,
                'description' => $r->description,
                'permissions' => $r->permissions,
            ]),
            'filters' => [
                'search' => $filters['search'] ?? null,
                'sort' => $filters['sort'] ?? EmployeeListQuery::DEFAULT_SORT,
                'dir' => $filters['dir'] ?? 'asc',
                'role' => $filters['role'] ?? null,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', Password::defaults()],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'in:'.implode(',', array_keys(Permission::ALL))],
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => User::ROLE_EMPLOYEE,
            'company_id' => $request->user()->company_id,
        ]);

        $user->syncPermissions($validated['permissions']);

        return back();
    }

    /**
     * User has no company global scope, so route-model binding happily resolves
     * an account from another tenant. Every endpoint that writes to a bound user
     * must confirm it belongs to the caller's company first.
     */
    private function guardSameCompany(Request $request, User $user): void
    {
        abort_unless($user->company_id === $request->user()->company_id, 403);
    }

    public function updatePermissions(Request $request, User $user)
    {
        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'in:'.implode(',', array_keys(Permission::ALL))],
        ]);

        $this->guardSameCompany($request, $user);

        if ($user->isOwner()) {
            abort(403, 'Cannot modify owner permissions.');
        }

        $user->syncPermissions($validated['permissions']);

        return back();
    }

    /** Apply a named role preset, replacing the user's individual permissions. */
    public function assignRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'staff_role_id' => ['required', 'integer', 'exists:staff_roles,id'],
        ]);

        $this->guardSameCompany($request, $user);

        if ($user->isOwner()) {
            abort(403, 'Cannot modify owner permissions.');
        }

        $role = StaffRole::findOrFail($validated['staff_role_id']);
        abort_unless($role->company_id === $request->user()->company_id, 403);

        $user->applyStaffRole($role);

        return back();
    }

    public function destroy(Request $request, User $user)
    {
        $this->guardSameCompany($request, $user);

        if ($user->isOwner()) {
            abort(403, 'Cannot delete owner accounts.');
        }

        $user->permissions()->delete();
        $user->delete();

        return back();
    }
}
