<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Permission;
use App\Models\StaffRole;
use App\Models\User;
use Carbon\Carbon;
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
            ->with(['permissions', 'staffRole'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20)
            ->withQueryString();

        $workload = $this->weeklyWorkload($companyId);

        $paginated->getCollection()->transform(fn (User $user) => [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'name' => $user->name,
            'email' => $user->email,
            'permissions' => $user->permissionKeys(),
            'staff_role' => $user->staffRole ? [
                'id' => $user->staffRole->id,
                'slug' => $user->staffRole->slug,
                'name' => $user->staffRole->name,
            ] : null,
            'has_custom_permissions' => $user->hasCustomPermissions(),
            'appointments_this_week' => $workload[$user->id]['count'] ?? 0,
            'utilisation' => $workload[$user->id]['utilisation'] ?? 0,
        ]);

        return Inertia::render('Employees/Index', [
            'employees' => $paginated,
            'availablePermissions' => Permission::ALL,
            'roles' => StaffRole::ordered()->get()->map(fn (StaffRole $r) => [
                'id' => $r->id,
                'slug' => $r->slug,
                'name' => $r->name,
                'description' => $r->description,
                'permissions' => $r->permissions,
            ]),
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

    /** Apply a named role preset, replacing the user's individual permissions. */
    public function assignRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'staff_role_id' => ['required', 'integer', 'exists:staff_roles,id'],
        ]);

        if ($user->isOwner()) {
            abort(403, 'Cannot modify owner permissions.');
        }

        $role = StaffRole::findOrFail($validated['staff_role_id']);
        abort_unless($role->company_id === $request->user()->company_id, 403);

        $user->applyStaffRole($role);

        return back();
    }

    /**
     * Appointments per employee in the current week, plus a rough utilisation
     * figure against a 40 hour week. Answers "who has room on Thursday?".
     *
     * @return array<int, array{count: int, utilisation: int}>
     */
    private function weeklyWorkload(int $companyId): array
    {
        $start = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $end = Carbon::now()->endOfWeek(Carbon::SUNDAY);

        $rows = Appointment::query()
            ->where('company_id', $companyId)
            ->whereBetween('start_at', [$start, $end])
            ->join('appointment_user', 'appointment_user.appointment_id', '=', 'appointments.id')
            ->groupBy('appointment_user.user_id')
            ->selectRaw('appointment_user.user_id as uid, count(*) as total, sum(timestampdiff(minute, start_at, end_at)) as minutes')
            ->get();

        $capacityMinutes = 40 * 60;

        return $rows->mapWithKeys(fn ($row) => [
            (int) $row->uid => [
                'count' => (int) $row->total,
                'utilisation' => (int) min(100, round(((int) $row->minutes / $capacityMinutes) * 100)),
            ],
        ])->all();
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
