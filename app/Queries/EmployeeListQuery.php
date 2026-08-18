<?php

namespace App\Queries;

use App\Models\StaffRole;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * The employee list: search, filter by staff role, and server-side sorting.
 *
 * "Appointments this week" is a subquery so it can be sorted on across pages;
 * utilisation needs summed minutes and comes from one extra grouped query for
 * the whole company, never one per row.
 */
class EmployeeListQuery
{
    /** Filter value meaning "no preset role assigned". */
    public const ROLE_NONE = 'none';

    /** Sort key accepted from the URL → the column it maps to. */
    public const SORTS = [
        'name' => 'last_name',
        'email' => 'email',
        'appointments' => 'appointments_this_week_count',
    ];

    public const DEFAULT_SORT = 'name';

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', Rule::in(array_keys(self::SORTS))],
            'dir' => ['nullable', 'string', 'in:asc,desc'],
            'role' => ['nullable', 'string', Rule::in([self::ROLE_NONE, ...self::roleSlugs()])],
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function roleSlugs(): array
    {
        return StaffRole::query()->pluck('slug')->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $companyId, int $perPage = 20): LengthAwarePaginator
    {
        [$start, $end] = $this->currentWeek();

        $query = User::query()
            ->where('role', User::ROLE_EMPLOYEE)
            ->where('company_id', $companyId)
            ->with(['permissions', 'staffRole'])
            ->withCount(['assignedAppointments as appointments_this_week_count' => fn (Builder $q) => $q
                ->whereBetween('appointments.start_at', [$start, $end]),
            ]);

        $this->applySearch($query, $filters['search'] ?? null);
        $this->applyRole($query, $filters['role'] ?? null);
        $this->applySort($query, $filters['sort'] ?? null, $filters['dir'] ?? null);

        $paginated = $query->paginate($perPage)->withQueryString();

        $utilisation = (new WorkloadQuery)->percentPerUser($companyId, $start, $end);

        $paginated->getCollection()->transform(
            fn (User $user) => $this->present($user, $utilisation[$user->id] ?? 0)
        );

        return $paginated;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(User $user, int $utilisation): array
    {
        return [
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
            'appointments_this_week' => (int) ($user->appointments_this_week_count ?? 0),
            'utilisation' => $utilisation,
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function currentWeek(): array
    {
        return [
            Carbon::now()->startOfWeek(Carbon::MONDAY),
            Carbon::now()->endOfWeek(Carbon::SUNDAY),
        ];
    }

    private function applySearch(Builder $query, ?string $search): void
    {
        if (! $search) {
            return;
        }

        $query->where(function (Builder $q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        });
    }

    private function applyRole(Builder $query, ?string $role): void
    {
        if (! $role) {
            return;
        }

        if ($role === self::ROLE_NONE) {
            $query->whereNull('staff_role_id');

            return;
        }

        $query->whereHas('staffRole', fn (Builder $q) => $q->where('slug', $role));
    }

    private function applySort(Builder $query, ?string $sort, ?string $dir): void
    {
        $sort = $sort && isset(self::SORTS[$sort]) ? $sort : self::DEFAULT_SORT;
        $direction = $dir === 'desc' ? 'desc' : 'asc';

        $query->orderBy(self::SORTS[$sort], $direction);

        if ($sort === 'name') {
            $query->orderBy('first_name', $direction);
        }

        $query->orderBy('users.id');
    }
}
