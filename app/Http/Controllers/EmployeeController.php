<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $companyId = $request->user()->company_id;
        $search = $request->input('search');

        $paginated = User::where('role', User::ROLE_EMPLOYEE)
            ->where('company_id', $companyId)
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->with('permissions')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20)
            ->withQueryString();

        $paginated->getCollection()->transform(fn (User $user) => [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'name' => $user->name,
            'email' => $user->email,
            'permissions' => $user->permissions->pluck('permission')->toArray(),
        ]);

        return Inertia::render('Employees/Index', [
            'employees' => $paginated,
            'availablePermissions' => Permission::ALL,
            'filters' => ['search' => $search],
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
