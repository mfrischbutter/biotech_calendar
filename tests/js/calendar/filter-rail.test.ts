import { beforeEach, describe, expect, it, vi } from 'vitest';
import CalendarFilterRail from '@/Pages/Calendar/partials/CalendarFilterRail.vue';
import { useCalendarQuery } from '@/lib/use-calendar-query';
import { mountComponent, setAuthedOwner, makeStatus } from '../helpers';
import { inertiaRouterMock } from '../setup';
import type { CalendarEmployee, CalendarFilters, Status } from '@/types';

const employees: CalendarEmployee[] = [
    { id: 7, first_name: 'Anna', last_name: 'Berg', name: 'Anna Berg' },
    { id: 9, first_name: 'Max', last_name: 'Kern', name: 'Max Kern' },
];

const statuses: Status[] = [
    makeStatus({ id: 31, name: 'Offen' }),
    makeStatus({ id: 32, name: 'In Arbeit' }),
];

const noFilters: CalendarFilters = { employees: [], statuses: [], unassigned: false, conflicts: false };

function mountRail(filters: Partial<CalendarFilters> = {}) {
    const merged = { ...noFilters, ...filters };
    return mountComponent(CalendarFilterRail, {
        props: {
            employees,
            statuses,
            filters: merged,
            hasFilters:
                merged.employees.length > 0 || merged.statuses.length > 0 || merged.unassigned || merged.conflicts,
        },
    });
}

describe('CalendarFilterRail', () => {
    beforeEach(() => setAuthedOwner());

    it('replaces the legend with one checkbox per person and per status', () => {
        const wrapper = mountRail();

        expect(wrapper.find('[data-testid="filter-employee-7"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="filter-employee-9"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="filter-unassigned"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="filter-status-31"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="filter-status-32"]').exists()).toBe(true);
    });

    it('shows which filters are on', () => {
        const wrapper = mountRail({ employees: [9], unassigned: true });

        expect(wrapper.get('[data-testid="filter-employee-9"]').attributes('aria-checked')).toBe('true');
        expect(wrapper.get('[data-testid="filter-employee-7"]').attributes('aria-checked')).toBe('false');
        expect(wrapper.get('[data-testid="filter-unassigned"]').attributes('aria-checked')).toBe('true');
    });

    it('emits a toggle for the person that was clicked', async () => {
        const wrapper = mountRail();
        await wrapper.get('[data-testid="filter-employee-9"]').trigger('click');

        expect(wrapper.emitted('toggleEmployee')).toEqual([[9]]);
    });

    it('emits a toggle for the status that was clicked', async () => {
        const wrapper = mountRail();
        await wrapper.get('[data-testid="filter-status-31"]').trigger('click');

        expect(wrapper.emitted('toggleStatus')).toEqual([[31]]);
    });

    it('offers a reset only once something is filtered', async () => {
        expect(mountRail().find('[data-testid="filter-reset"]').exists()).toBe(false);

        const wrapper = mountRail({ statuses: [31] });
        await wrapper.get('[data-testid="filter-reset"]').trigger('click');
        expect(wrapper.emitted('reset')).toHaveLength(1);
    });
});

describe('useCalendarQuery', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        localStorage.clear();
    });

    function query(filters: Partial<CalendarFilters> = {}, view = 'week', density = 'compact') {
        const state = { ...noFilters, ...filters };
        return useCalendarQuery({
            view: () => view as never,
            date: () => '2026-04-08',
            filters: () => state,
            density: () => density as never,
        });
    }

    function lastVisit(): Record<string, unknown> {
        const calls = inertiaRouterMock.get.mock.calls;
        return calls[calls.length - 1][1] as Record<string, unknown>;
    }

    it('puts a ticked person in the query string', () => {
        query().toggleEmployee(7);

        expect(lastVisit()).toEqual({ view: 'week', date: '2026-04-08', employees: [7] });
    });

    it('accumulates and removes selections', () => {
        const q = query({ employees: [7] });
        q.toggleEmployee(9);
        expect(lastVisit().employees).toEqual([7, 9]);

        q.toggleEmployee(7);
        expect(lastVisit().employees).toEqual([9]);
    });

    it('carries unassigned and conflicts as flags', () => {
        const q = query();
        q.toggleUnassigned();
        expect(lastVisit()).toMatchObject({ unassigned: 1 });

        q.toggleConflicts();
        expect(lastVisit()).toMatchObject({ unassigned: 1, conflicts: 1 });
    });

    it('keeps filters when the view changes', () => {
        query({ statuses: [31], unassigned: true }).switchView('month');

        expect(lastVisit()).toEqual({ view: 'month', date: '2026-04-08', statuses: [31], unassigned: 1 });
        expect(localStorage.getItem('biotech-calendar-view')).toBe('month');
    });

    it('drops every filter on reset', () => {
        query({ employees: [7], statuses: [31], unassigned: true, conflicts: true }).resetFilters();

        expect(lastVisit()).toEqual({ view: 'week', date: '2026-04-08' });
    });

    it('reports whether anything is narrowing the board', () => {
        expect(query().hasFilters.value).toBe(false);
        expect(query({ conflicts: true }).hasFilters.value).toBe(true);
    });

    it('steps a week at a time in the week view and a day at a time in the day view', () => {
        query().navigate(1);
        expect(lastVisit()).toMatchObject({ date: '2026-04-15' });

        query({}, 'day').navigate(-1);
        expect(lastVisit()).toMatchObject({ date: '2026-04-07' });
    });

    it('drops the date when jumping to today', () => {
        query().goToToday();

        expect(lastVisit()).toEqual({ view: 'week' });
    });
});
