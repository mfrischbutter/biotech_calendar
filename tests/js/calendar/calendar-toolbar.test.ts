import { beforeEach, describe, expect, it } from 'vitest';
import CalendarToolbar from '@/Pages/Calendar/partials/CalendarToolbar.vue';
import { mountComponent, setAuthedOwner } from '../helpers';
import type { CalendarView } from '@/types';

function mountToolbar(overrides: Record<string, unknown> = {}) {
    return mountComponent(CalendarToolbar, {
        props: {
            view: 'team-week' as CalendarView,
            title: '6. Apr – 10. Apr 2026',
            conflictCount: 0,
            conflictsActive: false,
            detailed: false,
            ...overrides,
        },
    });
}

describe('CalendarToolbar', () => {
    beforeEach(() => setAuthedOwner());

    it('offers exactly one switcher with four choices', () => {
        const wrapper = mountToolbar();
        const segments = wrapper.findAll('[data-view]');

        expect(segments.map((s) => s.attributes('data-view'))).toEqual(['day', 'week', 'month', 'team']);
    });

    it('marks the team segment active for the team week', () => {
        const wrapper = mountToolbar({ view: 'team-week' });

        expect(wrapper.get('[data-view="team"]').attributes('aria-pressed')).toBe('true');
        expect(wrapper.get('[data-view="week"]').attributes('aria-pressed')).toBe('false');
    });

    it('sends the team segment to the team week', async () => {
        const wrapper = mountToolbar({ view: 'week' });
        await wrapper.get('[data-view="team"]').trigger('click');

        expect(wrapper.emitted('switchView')).toEqual([['team-week']]);
    });

    it('switches between the plain views', async () => {
        const wrapper = mountToolbar({ view: 'team-week' });
        await wrapper.get('[data-view="month"]').trigger('click');

        expect(wrapper.emitted('switchView')).toEqual([['month']]);
    });

    it('offers the density toggle only on the team board', () => {
        expect(mountToolbar({ view: 'team-week' }).find('[data-testid="detail-toggle"]').exists()).toBe(true);
        expect(mountToolbar({ view: 'week' }).find('[data-testid="detail-toggle"]').exists()).toBe(false);
        expect(mountToolbar({ view: 'day' }).find('[data-testid="detail-toggle"]').exists()).toBe(false);
    });

    it('reflects and toggles the density choice', async () => {
        const wrapper = mountToolbar({ detailed: true });
        const toggle = wrapper.get('[data-testid="detail-toggle"]');

        expect(toggle.attributes('aria-pressed')).toBe('true');
        await toggle.trigger('click');
        expect(wrapper.emitted('toggleDetailed')).toHaveLength(1);
    });

    it('hides the conflict chip when nothing clashes', () => {
        expect(mountToolbar({ conflictCount: 0 }).find('[data-testid="conflict-filter"]').exists()).toBe(false);
    });

    it('shows a clickable conflict count', async () => {
        const wrapper = mountToolbar({ conflictCount: 2 });
        const chip = wrapper.get('[data-testid="conflict-filter"]');

        expect(chip.text()).toContain('2 conflicts');
        await chip.trigger('click');
        expect(wrapper.emitted('toggleConflicts')).toHaveLength(1);
    });

    it('uses the singular for a single clash', () => {
        expect(mountToolbar({ conflictCount: 1 }).get('[data-testid="conflict-filter"]').text()).toContain('1 conflict');
    });

    it('keeps the conflict chip reachable while it is the active filter', () => {
        const wrapper = mountToolbar({ conflictCount: 0, conflictsActive: true });

        expect(wrapper.get('[data-testid="conflict-filter"]').attributes('aria-pressed')).toBe('true');
    });

    it('emits navigation and creation intents', async () => {
        const wrapper = mountToolbar();
        const buttons = wrapper.findAll('button');

        await buttons[0].trigger('click');
        await buttons[1].trigger('click');
        expect(wrapper.emitted('navigate')).toEqual([[-1], [1]]);

        await buttons[2].trigger('click');
        expect(wrapper.emitted('today')).toHaveLength(1);
    });
});
