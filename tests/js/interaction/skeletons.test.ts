import { beforeEach, describe, expect, it } from 'vitest';

import CalendarSkeleton from '@/Pages/Calendar/partials/CalendarSkeleton.vue';
import DashboardSkeleton from '@/Pages/Dashboard/partials/DashboardSkeleton.vue';
import ShortcutHelpList from '@/Pages/Calendar/partials/ShortcutHelpList.vue';
import { CALENDAR_SHORTCUTS } from '@/lib/use-calendar-shortcuts';
import { mountComponent, setAuthedOwner } from '../helpers';

describe('region skeletons', () => {
    beforeEach(() => setAuthedOwner());

    it('mirrors the shape of the board it stands in for', () => {
        const wrapper = mountComponent(CalendarSkeleton, { props: { columns: 5, rows: 4 } });
        const root = wrapper.find('[data-testid="calendar-skeleton"]');

        expect(root.exists()).toBe(true);
        expect(root.attributes('role')).toBe('status');
        expect(root.attributes('aria-label')).toBe('Loading appointments');
        // One header cell per column, plus the row-label gutter.
        expect(wrapper.findAll('.animate-pulse').length).toBeGreaterThan(5);
    });

    it('follows the column count it is given', () => {
        const narrow = mountComponent(CalendarSkeleton, { props: { columns: 1, rows: 2 } });
        const wide = mountComponent(CalendarSkeleton, { props: { columns: 7, rows: 2 } });

        expect(wide.findAll('.animate-pulse').length).toBeGreaterThan(
            narrow.findAll('.animate-pulse').length,
        );
    });

    it('announces the dashboard as loading rather than empty', () => {
        const wrapper = mountComponent(DashboardSkeleton);
        const root = wrapper.find('[data-testid="dashboard-skeleton"]');

        expect(root.exists()).toBe(true);
        expect(root.attributes('aria-label')).toBe('Loading dashboard');
        expect(wrapper.findAll('.animate-pulse').length).toBeGreaterThan(10);
    });
});

describe('ShortcutHelpList', () => {
    beforeEach(() => setAuthedOwner());

    it('spells out every shortcut the calendar implements', () => {
        const wrapper = mountComponent(ShortcutHelpList);
        const text = wrapper.text();

        for (const shortcut of CALENDAR_SHORTCUTS) {
            expect(text).toContain(shortcut.label);
            expect(text).toContain(shortcut.keys);
        }

        expect(wrapper.findAll('kbd')).toHaveLength(CALENDAR_SHORTCUTS.length);
    });
});
