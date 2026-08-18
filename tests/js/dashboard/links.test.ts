import { beforeEach, describe, expect, it } from 'vitest';
import AttentionCards from '@/Pages/Dashboard/partials/AttentionCards.vue';
import PipelineStrip from '@/Pages/Dashboard/partials/PipelineStrip.vue';
import { mountComponent, setAuthedOwner } from '../helpers';
import type { AttentionCounts, PipelineStage } from '@/types';

/*
 * Every tile on the dashboard is a number plus a promise: click it and you land
 * on the list of exactly those items. The promise was broken — the tiles passed
 * `filter=conflicts` and `stage=ready_to_invoice`, neither of which the calendar
 * or the contract list reads, so all five opened the same unfiltered screen.
 *
 * These tests pin the parameter *names*, because a wrong name fails silently:
 * the page renders, the link works, and nothing is filtered.
 */

const attention: AttentionCounts = {
    conflicts: 2,
    unassigned: 3,
    overdue: 4,
    readyToInvoice: 5,
    utilisation: 62,
};

const stages: PipelineStage[] = [
    { stage: 'unconfirmed', count: 10 },
    { stage: 'active', count: 40 },
    { stage: 'ready_to_invoice', count: 6 },
    { stage: 'invoiced', count: 3 },
];

function cardHref(key: string): string | undefined {
    const wrapper = mountComponent(AttentionCards, { props: { attention } });

    return wrapper.get(`[data-testid="attention-${key}"]`).attributes('href');
}

describe('dashboard attention cards', () => {
    beforeEach(() => setAuthedOwner());

    it('sends conflicts to the calendar with its conflict filter on', () => {
        expect(cardHref('conflicts')).toBe('/calendar/index?conflicts=1');
    });

    it('hides the conflict card entirely when there is nothing to resolve', () => {
        const wrapper = mountComponent(AttentionCards, {
            props: { attention: { ...attention, conflicts: 0 } },
        });

        expect(wrapper.findAll('a')).toHaveLength(4);
    });

    it('sends unassigned work to the calendar filtered to nobody', () => {
        expect(cardHref('unassigned')).toBe('/calendar/index?unassigned=1');
    });

    /*
     * Overdue work reaches back three months, which no calendar week can hold,
     * so the tile opens the contract list's saved view instead.
     */
    it('sends overdue work to the contract list, not the calendar', () => {
        expect(cardHref('overdue')).toBe('/contracts/index?view=overdue');
    });

    it('sends invoicing to the matching contract tab', () => {
        expect(cardHref('ready')).toBe('/contracts/index?view=ready_to_invoice');
    });

    // The employee screen is permissions; booked hours live on the team board.
    it('sends utilisation to the board that prints the hours', () => {
        expect(cardHref('utilisation')).toBe('/calendar/index?view=team-week');
    });
});

describe('dashboard pipeline strip', () => {
    beforeEach(() => setAuthedOwner());

    it('opens a different contract tab for every stage', () => {
        const wrapper = mountComponent(PipelineStrip, { props: { stages } });

        expect(wrapper.findAll('a').map((link) => link.attributes('href'))).toEqual([
            '/contracts/index?view=unconfirmed',
            '/contracts/index?view=active',
            '/contracts/index?view=ready_to_invoice',
            '/contracts/index?view=invoiced',
        ]);
    });

    it('gives no two stages the same destination', () => {
        const wrapper = mountComponent(PipelineStrip, { props: { stages } });
        const hrefs = wrapper.findAll('a').map((link) => link.attributes('href'));

        expect(new Set(hrefs).size).toBe(hrefs.length);
    });
});
