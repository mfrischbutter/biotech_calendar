<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function index()
    {
        $employees = User::where('role', User::ROLE_EMPLOYEE)
            ->with('permissions')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'permissions' => $user->permissions->pluck('permission')->toArray(),
            ]);

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'availablePermissions' => Permission::ALL,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', Password::defaults()],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'in:'.implode(',', array_keys(Permission::ALL))],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => User::ROLE_EMPLOYEE,
        ]);

        $user->syncPermissions($validated['permissions']);

        return back();
    }

    public function updatePermissions(Request $request, User $user)
    {
        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'in:'.implode(',', array_keys(Permission::ALL))],
        ]);

        if ($user->isOwner()) {
            abort(403, 'Cannot modify owner permissions.');
        }

        $user->syncPermissions($validated['permissions']);

        return back();
    }

    public function destroy(User $user)
    {
        if ($user->isOwner()) {
            abort(403, 'Cannot delete owner accounts.');
        }

        $user->permissions()->delete();
        $user->delete();

        return back();
    }
}
