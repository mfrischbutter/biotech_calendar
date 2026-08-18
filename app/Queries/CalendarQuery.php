<?php

namespace App\Queries;

use App\Models\Appointment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Everything the calendar screen needs to turn a query string into a window of
 * appointments: the visible range, the filter rail's selections, and the
 * per-person / per-day totals the team view prints in its headers.
 *
 * It lives outside the controller so the controller stays a thin
 * validate → authorize → fetch → respond, and so the filter rules have exactly
 * one definition.
 */
class CalendarQuery
{
    /** The team week is where the dispatcher actually works, so it is home. */
    public const DEFAULT_VIEW = 'team-week';

    public const VIEWS = ['day', 'week', 'month', 'team-week'];

    /** How tightly the team board packs its rows. */
    public const DENSITIES = ['compact', 'detailed'];

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'date' => ['nullable', 'date'],
            'view' => ['nullable', 'in:'.implode(',', self::VIEWS)],
            'appointment' => ['nullable', 'integer'],
            'density' => ['nullable', 'in:'.implode(',', self::DENSITIES)],
            'employees' => ['nullable', 'array', 'max:100'],
            'employees.*' => ['integer', 'exists:users,id'],
            'statuses' => ['nullable', 'array', 'max:100'],
            'statuses.*' => ['integer', 'exists:statuses,id'],
            'unassigned' => ['nullable', 'boolean'],
            'conflicts' => ['nullable', 'boolean'],
        ];
    }

    /**
     * The filter rail's state, normalised so the frontend always gets the same
     * shape back regardless of what the query string looked like.
     *
     * @return array{employees: array<int, int>, statuses: array<int, int>, unassigned: bool, conflicts: bool}
     */
    public static function filtersFrom(Request $request): array
    {
        return [
            'employees' => self::intList($request->input('employees')),
            'statuses' => self::intList($request->input('statuses')),
            'unassigned' => $request->boolean('unassigned'),
            'conflicts' => $request->boolean('conflicts'),
        ];
    }

    /**
     * The board's density, or null when the query string does not say — the
     * screen then falls back to whatever this user last chose.
     */
    public static function densityFrom(Request $request): ?string
    {
        $density = $request->input('density');

        return in_array($density, self::DENSITIES, true) ? $density : null;
    }

    /**
     * @return array<int, int>
     */
    private static function intList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $value)));
    }

    /**
     * The window a view covers. Month is padded to whole weeks because the grid
     * always renders complete Monday-to-Sunday rows.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public static function range(string $view, CarbonInterface $date): array
    {
        return match ($view) {
            'day' => [
                $date->copy()->startOfDay(),
                $date->copy()->endOfDay(),
            ],
            'month' => [
                $date->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY),
                $date->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY),
            ],
            default => [
                $date->copy()->startOfWeek(Carbon::MONDAY),
                $date->copy()->endOfWeek(Carbon::SUNDAY),
            ],
        };
    }

    /**
     * Appointments that touch the window in any way, including the long ones
     * that start before it and end after it.
     *
     * @param  Builder<Appointment>  $query
     * @return Builder<Appointment>
     */
    public static function inRange(Builder $query, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $query->where(function (Builder $q) use ($from, $to) {
            $q->whereBetween('start_at', [$from, $to])
                ->orWhereBetween('end_at', [$from, $to])
                ->orWhere(function (Builder $inner) use ($from, $to) {
                    $inner->where('start_at', '<', $from)->where('end_at', '>', $to);
                });
        });
    }

    /**
     * Narrow the window down to the filter rail's selection.
     *
     * Employees and "unassigned" are one OR-group: ticking Markus and
     * "Nicht zugewiesen" means "Markus's work plus the work nobody owns", not
     * the empty intersection of the two.
     *
     * @param  Builder<Appointment>  $query
     * @param  array{employees: array<int, int>, statuses: array<int, int>, unassigned: bool, conflicts: bool}  $filters
     * @param  array<int, int>  $conflictIds
     * @return Builder<Appointment>
     */
    public static function applyFilters(Builder $query, array $filters, array $conflictIds): Builder
    {
        $employees = $filters['employees'];

        if ($employees !== [] || $filters['unassigned']) {
            $query->where(function (Builder $q) use ($employees, $filters) {
                if ($employees !== []) {
                    $q->whereHas('workers', fn (Builder $w) => $w->whereIn('users.id', $employees));
                }
                if ($filters['unassigned']) {
                    $q->orWhereDoesntHave('workers');
                }
            });
        }

        if ($filters['statuses'] !== []) {
            $query->whereIn('status_id', $filters['statuses']);
        }

        if ($filters['conflicts']) {
            // An empty list must match nothing, not everything.
            $query->whereIn('id', $conflictIds !== [] ? $conflictIds : [0]);
        }

        return $query;
    }

    /**
     * Row and column totals for the team grid: how much work each person carries
     * and how loaded each day is.
     *
     * An appointment with two technicians counts once for each of them, because
     * that is what the row header is claiming, but only once for the day.
     *
     * @param  Collection<int, Appointment>  $appointments
     * @return array{
     *     perEmployee: array<int, array{id: int|null, appointments: int, minutes: int}>,
     *     perDay: array<int, array{date: string, appointments: int, minutes: int}>
     * }
     */
    public static function totals(Collection $appointments, CarbonInterface $from, CarbonInterface $to): array
    {
        $perEmployee = [];
        $perDay = [];

        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            $perDay[$day->toDateString()] = ['appointments' => 0, 'minutes' => 0];
        }

        foreach ($appointments as $appointment) {
            $minutes = $appointment->start_at && $appointment->end_at
                ? (int) abs($appointment->start_at->diffInMinutes($appointment->end_at))
                : 0;

            $date = $appointment->start_at?->toDateString();
            if ($date !== null && isset($perDay[$date])) {
                $perDay[$date]['appointments']++;
                $perDay[$date]['minutes'] += $minutes;
            }

            $workerIds = $appointment->workers->pluck('id')->all();
            foreach ($workerIds === [] ? [null] : $workerIds as $workerId) {
                $key = $workerId === null ? 'unassigned' : $workerId;
                $perEmployee[$key] ??= ['id' => $workerId, 'appointments' => 0, 'minutes' => 0];
                $perEmployee[$key]['appointments']++;
                $perEmployee[$key]['minutes'] += $minutes;
            }
        }

        uasort($perEmployee, fn (array $a, array $b) => ($a['id'] ?? -1) <=> ($b['id'] ?? -1));

        return [
            'perEmployee' => array_values($perEmployee),
            'perDay' => array_values(array_map(
                fn (string $date, array $row) => ['date' => $date, ...$row],
                array_keys($perDay),
                $perDay,
            )),
        ];
    }
}
