import { computed, ref, watch, type ComputedRef, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { addDays, addMonths, addWeeks, format, parseISO } from 'date-fns';
import type { CalendarDensity, CalendarFilters, CalendarView } from '@/types';

/** Where the composable reads the server's current answer from. */
export interface CalendarQuerySource {
    view: () => CalendarView;
    date: () => string;
    filters: () => CalendarFilters;
    density: () => CalendarDensity;
}

export interface UseCalendarQuery {
    filters: Ref<CalendarFilters>;
    /** True when anything in the rail is narrowing the board. */
    hasFilters: ComputedRef<boolean>;
    navigate: (direction: number) => void;
    goToToday: () => void;
    switchView: (view: CalendarView, date?: string) => void;
    toggleEmployee: (id: number) => void;
    toggleStatus: (id: number) => void;
    toggleUnassigned: () => void;
    toggleConflicts: () => void;
    setDensity: (density: CalendarDensity) => void;
    resetFilters: () => void;
}

export const CALENDAR_VIEW_STORAGE_KEY = 'biotech-calendar-view';
export const CALENDAR_DENSITY_STORAGE_KEY = 'biotech-team-week-detailed';

function cloneFilters(filters: CalendarFilters): CalendarFilters {
    return {
        employees: [...filters.employees],
        statuses: [...filters.statuses],
        unassigned: filters.unassigned,
        conflicts: filters.conflicts,
    };
}

function toggleId(list: number[], id: number): number[] {
    return list.includes(id) ? list.filter((value) => value !== id) : [...list, id];
}

/**
 * The calendar's whole query string in one place: which view, which day, and
 * which slice of the team the user is looking at.
 *
 * Filters live in the URL rather than in component state so a filtered board can
 * be pasted into a chat and still mean the same thing for the colleague who
 * opens it.
 */
export function useCalendarQuery(source: CalendarQuerySource): UseCalendarQuery {
    const filters = ref<CalendarFilters>(cloneFilters(source.filters()));

    watch(
        () => source.filters(),
        (next) => {
            filters.value = cloneFilters(next);
        },
        { deep: true },
    );

    /**
     * Narrowing the board (a filter chip, a density switch) `replace`s the
     * history entry the way the list screens do, so Back still means "the week
     * I was looking at before" rather than walking back through every chip.
     * Moving in time or changing view pushes, because that IS the navigation.
     */
    function visit(
        overrides: {
            view?: CalendarView
            date?: string | null
            density?: CalendarDensity
            replace?: boolean
        } = {},
    ): void {
        const view = overrides.view ?? source.view();
        const date = overrides.date === undefined ? source.date() : overrides.date;
        const density = overrides.density ?? source.density();

        const query: Record<string, string | number[] | number> = { view };
        if (date) query.date = date;
        // The default reads cleaner as an absent parameter than as `compact`.
        if (density === 'detailed') query.density = density;
        if (filters.value.employees.length > 0) query.employees = filters.value.employees;
        if (filters.value.statuses.length > 0) query.statuses = filters.value.statuses;
        if (filters.value.unassigned) query.unassigned = 1;
        if (filters.value.conflicts) query.conflicts = 1;

        router.get(route('calendar.index'), query, {
            preserveState: true,
            preserveScroll: true,
            replace: overrides.replace ?? false,
        });
    }

    function navigate(direction: number): void {
        const current = parseISO(source.date());
        const view = source.view();
        const step = view === 'day'
            ? addDays(current, direction)
            : view === 'month'
              ? addMonths(current, direction)
              : addWeeks(current, direction);

        visit({ date: format(step, 'yyyy-MM-dd') });
    }

    function goToToday(): void {
        visit({ date: null });
    }

    function switchView(view: CalendarView, date?: string): void {
        localStorage.setItem(CALENDAR_VIEW_STORAGE_KEY, view);
        visit({ view, date });
    }

    function toggleEmployee(id: number): void {
        filters.value.employees = toggleId(filters.value.employees, id);
        visit({ replace: true });
    }

    function toggleStatus(id: number): void {
        filters.value.statuses = toggleId(filters.value.statuses, id);
        visit({ replace: true });
    }

    function toggleUnassigned(): void {
        filters.value.unassigned = !filters.value.unassigned;
        visit({ replace: true });
    }

    function toggleConflicts(): void {
        filters.value.conflicts = !filters.value.conflicts;
        visit({ replace: true });
    }

    function setDensity(density: CalendarDensity): void {
        localStorage.setItem(CALENDAR_DENSITY_STORAGE_KEY, String(density === 'detailed'));
        visit({ density, replace: true });
    }

    function resetFilters(): void {
        filters.value = { employees: [], statuses: [], unassigned: false, conflicts: false };
        visit({ replace: true });
    }

    return {
        filters,
        hasFilters: computed(
            () =>
                filters.value.employees.length > 0 ||
                filters.value.statuses.length > 0 ||
                filters.value.unassigned ||
                filters.value.conflicts,
        ),
        navigate,
        goToToday,
        switchView,
        toggleEmployee,
        toggleStatus,
        toggleUnassigned,
        toggleConflicts,
        setDensity,
        resetFilters,
    };
}
