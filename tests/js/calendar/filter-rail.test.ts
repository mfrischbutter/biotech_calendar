import { beforeEach, describe, expect, it, vi } from 'vitest';
import CalendarFilterRail from '@/Pages/Calendar/partials/CalendarFilterRail.vue';
import FilterPill from '@/Components/FilterPill.vue';
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

    // The pill menus portal to the body, so teleport has to render for real.
    return mountComponent(CalendarFilterRail, {
        props: {
            employees,
            statuses,
            filters: merged,
            hasFilters:
                merged.employees.length > 0 || merged.statuses.length > 0 || merged.unassigned || merged.conflicts,
        },
        global: { stubs: { teleport: false } },
        attachTo: document.body,
    });
}

/** Open one pill's menu through its `open` model; jsdom cannot synthesise reka's pointer event. */
async function openPill(wrapper: ReturnType<typeof mountRail>, testid: string) {
    const pill = wrapper
        .findAllComponents(FilterPill)
        .find((candidate) => candidate.props('testid') === testid);

    // The rail leaves `open` unbound, so the model's own ref is what has to move.
    (pill?.vm as unknown as { open: boolean }).open = true;
    await wrapper.vm.$nextTick();
}

function item(testid: string): HTMLElement | null {
    return document.body.querySelector(`[data-testid="${testid}"]`);
}

describe('CalendarFilterRail', () => {
    beforeEach(() => setAuthedOwner());

    it('collapses the whole team and every status into two pills', () => {
        const wrapper = mountRail();

        expect(wrapper.find('[data-testid="filter-employees"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="filter-statuses"]').exists()).toBe(true);

        // Nothing is unfolded until someone asks for it.
        expect(wrapper.find('[data-testid="filter-employee-7"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="filter-status-31"]').exists()).toBe(false);

        wrapper.unmount();
    });

    it('names the unfiltered state instead of showing an empty control', () => {
        const wrapper = mountRail();

        expect(wrapper.get('[data-testid="filter-employees"]').text()).toContain('All employees');
        expect(wrapper.get('[data-testid="filter-statuses"]').text()).toContain('All statuses');
        expect(wrapper.get('[data-testid="filter-employees"]').attributes('data-active')).toBe('false');

        wrapper.unmount();
    });

    it('reads back a single selection by name rather than as a count', () => {
        const wrapper = mountRail({ employees: [9] });
        const pill = wrapper.get('[data-testid="filter-employees"]');

        expect(pill.text()).toContain('Max Kern');
        expect(pill.attributes('data-active')).toBe('true');
        expect(wrapper.find('[data-testid="filter-employees-count"]').exists()).toBe(false);

        wrapper.unmount();
    });

    it('falls back to a count once more than one thing is picked', () => {
        const wrapper = mountRail({ employees: [7, 9], unassigned: true });
        const pill = wrapper.get('[data-testid="filter-employees"]');

        expect(pill.text()).toContain('Employee');
        expect(wrapper.get('[data-testid="filter-employees-count"]').text()).toBe('3');

        wrapper.unmount();
    });

    it('counts the unassigned row towards the people pill', () => {
        const wrapper = mountRail({ unassigned: true });

        expect(wrapper.get('[data-testid="filter-employees"]').text()).toContain('Unassigned');

        wrapper.unmount();
    });

    it('lists every person, the unassigned row and every status once unfolded', async () => {
        const wrapper = mountRail();
        await openPill(wrapper, 'filter-employees');

        expect(item('filter-employee-7')).not.toBeNull();
        expect(item('filter-employee-9')).not.toBeNull();
        expect(item('filter-unassigned')).not.toBeNull();

        await openPill(wrapper, 'filter-statuses');
        expect(item('filter-status-31')).not.toBeNull();
        expect(item('filter-status-32')).not.toBeNull();

        wrapper.unmount();
    });

    it('marks the entries that are on', async () => {
        const wrapper = mountRail({ employees: [9], unassigned: true });
        await openPill(wrapper, 'filter-employees');

        expect(item('filter-employee-9')?.getAttribute('aria-checked')).toBe('true');
        expect(item('filter-employee-7')?.getAttribute('aria-checked')).toBe('false');
        expect(item('filter-unassigned')?.getAttribute('aria-checked')).toBe('true');

        wrapper.unmount();
    });

    it('emits a toggle for the person that was clicked', async () => {
        const wrapper = mountRail();
        await openPill(wrapper, 'filter-employees');

        item('filter-employee-9')?.click();
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('toggleEmployee')).toEqual([[9]]);
        wrapper.unmount();
    });

    it('emits a toggle for the status that was clicked', async () => {
        const wrapper = mountRail();
        await openPill(wrapper, 'filter-statuses');

        item('filter-status-31')?.click();
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('toggleStatus')).toEqual([[31]]);
        wrapper.unmount();
    });

    it('offers a per-pill clear only while that pill is narrowing anything', async () => {
        const idle = mountRail();
        await openPill(idle, 'filter-statuses');
        expect(item('filter-statuses-clear')).toBeNull();
        idle.unmount();

        const wrapper = mountRail({ statuses: [31] });
        await openPill(wrapper, 'filter-statuses');
        item('filter-statuses-clear')?.click();
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('clearStatuses')).toHaveLength(1);
        wrapper.unmount();
    });

    it('offers a reset only once something is filtered', async () => {
        const idle = mountRail();
        expect(idle.find('[data-testid="filter-reset"]').exists()).toBe(false);
        idle.unmount();

        const wrapper = mountRail({ statuses: [31] });
        await wrapper.get('[data-testid="filter-reset"]').trigger('click');
        expect(wrapper.emitted('reset')).toHaveLength(1);

        wrapper.unmount();
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

    it('empties one pill without disturbing the other', () => {
        query({ employees: [7], statuses: [31], unassigned: true }).clearEmployees();

        expect(lastVisit()).toEqual({ view: 'week', date: '2026-04-08', statuses: [31] });
    });

    it('empties the status pill on its own', () => {
        query({ employees: [7], statuses: [31, 32] }).clearStatuses();

        expect(lastVisit()).toEqual({ view: 'week', date: '2026-04-08', employees: [7] });
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
