import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    CALENDAR_DENSITY_STORAGE_KEY,
    useCalendarQuery,
} from '@/lib/use-calendar-query';
import CalendarToolbar from '@/Pages/Calendar/partials/CalendarToolbar.vue';
import { inertiaRouterMock } from '../setup';
import { mountComponent, setAuthedOwner } from '../helpers';
import type { CalendarDensity, CalendarFilters, CalendarView } from '@/types';

const noFilters: CalendarFilters = { employees: [], statuses: [], unassigned: false, conflicts: false };

function query(density: CalendarDensity, view: CalendarView = 'team-week') {
    return useCalendarQuery({
        view: () => view,
        date: () => '2026-04-08',
        filters: () => noFilters,
        density: () => density,
    });
}

function lastVisit(): Record<string, unknown> {
    const calls = inertiaRouterMock.get.mock.calls;

    return calls[calls.length - 1][1] as Record<string, unknown>;
}

function lastOptions(): Record<string, unknown> {
    const calls = inertiaRouterMock.get.mock.calls;

    return calls[calls.length - 1][2] as Record<string, unknown>;
}

describe('calendar density lives in the URL', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        localStorage.clear();
    });

    it('leaves the default out of the query string', () => {
        query('compact').goToToday();

        expect(lastVisit()).not.toHaveProperty('density');
    });

    it('carries a chosen density through every visit', () => {
        query('detailed').navigate(1);

        expect(lastVisit().density).toBe('detailed');
    });

    it('writes the choice to the URL and remembers it for next time', () => {
        query('compact').setDensity('detailed');

        expect(lastVisit().density).toBe('detailed');
        expect(localStorage.getItem(CALENDAR_DENSITY_STORAGE_KEY)).toBe('true');
    });

    it('drops the flag again when the board goes back to compact', () => {
        query('detailed').setDensity('compact');

        expect(lastVisit()).not.toHaveProperty('density');
        expect(localStorage.getItem(CALENDAR_DENSITY_STORAGE_KEY)).toBe('false');
    });

    it('keeps the density alongside the view when switching views', () => {
        query('detailed').switchView('week');

        expect(lastVisit()).toMatchObject({ view: 'week', density: 'detailed' });
    });
});

/**
 * The list screens replace the history entry when a filter changes; the board
 * has to agree, or Back means something different depending on which screen you
 * are standing on.
 */
describe('calendar history matches the list screens', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        localStorage.clear();
    });

    it.each([
        ['an employee chip', (q: ReturnType<typeof query>) => q.toggleEmployee(7)],
        ['a status chip', (q: ReturnType<typeof query>) => q.toggleStatus(3)],
        ['the unassigned chip', (q: ReturnType<typeof query>) => q.toggleUnassigned()],
        ['the conflicts chip', (q: ReturnType<typeof query>) => q.toggleConflicts()],
        ['reset', (q: ReturnType<typeof query>) => q.resetFilters()],
        ['density', (q: ReturnType<typeof query>) => q.setDensity('detailed')],
    ])('replaces the history entry for %s', (_label, act) => {
        act(query('compact'));

        expect(lastOptions().replace).toBe(true);
    });

    it.each([
        ['stepping to the next week', (q: ReturnType<typeof query>) => q.navigate(1)],
        ['jumping to today', (q: ReturnType<typeof query>) => q.goToToday()],
        ['switching view', (q: ReturnType<typeof query>) => q.switchView('month')],
    ])('pushes a history entry for %s, because that is the navigation', (_label, act) => {
        act(query('compact'));

        expect(lastOptions().replace).toBe(false);
    });
});

describe('CalendarToolbar shortcut affordance', () => {
    beforeEach(() => setAuthedOwner());

    it('offers a visible way to discover the shortcuts', async () => {
        const wrapper = mountComponent(CalendarToolbar, {
            props: {
                view: 'team-week',
                title: '6. Apr – 10. Apr 2026',
                conflictCount: 0,
                conflictsActive: false,
                detailed: false,
            },
        });

        const trigger = wrapper.find('[data-testid="shortcut-help-trigger"]');
        expect(trigger.exists()).toBe(true);
        expect(trigger.attributes('aria-label')).toBe('Keyboard shortcuts');

        await trigger.trigger('click');
        expect(wrapper.emitted('help')).toHaveLength(1);
    });
});
