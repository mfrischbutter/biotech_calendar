import { beforeEach, describe, expect, it } from 'vitest';
import RecordTimeline from '@/Components/record/RecordTimeline.vue';
import { mountComponent, setAuthedOwner } from '../helpers';
import type { TimelineEvent } from '@/types';

function event(overrides: Partial<TimelineEvent> = {}): TimelineEvent {
    return {
        id: 'appointment-1',
        type: 'appointment',
        action: null,
        at: '2026-04-10T08:00:00.000Z',
        title: 'Routinekontrolle',
        excerpt: null,
        actor: null,
        url: null,
        status: null,
        fields: [],
        ...overrides,
    };
}

const events: TimelineEvent[] = [
    event({ id: 'a-1', url: '/calendar?appointment=1', status: { name: 'Erste Massnahme', color: '#F59E0B', stage: 'active' } }),
    event({ id: 'd-1', type: 'document', at: '2026-04-07T11:00:00.000Z', title: 'Protokoll.pdf', url: '/attachments/9' }),
    event({ id: 'c-1', type: 'comment', at: '2026-04-06T14:00:00.000Z', excerpt: 'Koederboxen kontrolliert.', actor: 'Bjoern Wilhelmsen' }),
    event({ id: 'x-1', type: 'activity', action: 'updated', at: '2026-04-01T10:00:00.000Z', fields: ['Status', 'Notes'] }),
];

function mountTimeline(list: TimelineEvent[] = events) {
    return mountComponent(RecordTimeline, {
        props: {
            events: list,
            emptyTitle: 'Noch nichts passiert',
            emptyDescription: 'Hier erscheint der Verlauf.',
        },
    });
}

describe('RecordTimeline', () => {
    beforeEach(() => setAuthedOwner());

    it('offers one filter per type that occurs, with counts', () => {
        const wrapper = mountTimeline();

        expect(wrapper.find('[data-testid="timeline-filter-all"]').text()).toContain('4');
        expect(wrapper.find('[data-testid="timeline-filter-appointment"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="timeline-filter-comment"]').text()).toContain('1');
        // Nothing of this type is in the list, so there is no chip for it.
        expect(wrapper.find('[data-testid="timeline-filter-invoice"]').exists()).toBe(false);
    });

    it('groups the list by type when a filter is picked', async () => {
        const wrapper = mountTimeline();

        expect(wrapper.findAll('[data-testid="timeline-event"]')).toHaveLength(4);

        await wrapper.find('[data-testid="timeline-filter-comment"]').trigger('click');

        const rows = wrapper.findAll('[data-testid="timeline-event"]');
        expect(rows).toHaveLength(1);
        expect(rows[0].attributes('data-event-type')).toBe('comment');
        expect(rows[0].text()).toContain('Koederboxen kontrolliert.');
        expect(rows[0].text()).toContain('Bjoern Wilhelmsen');
    });

    it('renders newest first and links events that have a url', () => {
        const wrapper = mountTimeline();
        const rows = wrapper.findAll('[data-testid="timeline-event"]');

        expect(rows.map((row) => row.attributes('data-event-type'))).toEqual([
            'appointment',
            'document',
            'comment',
            'activity',
        ]);
        expect(rows[0].find('a').attributes('href')).toBe('/calendar?appointment=1');
        expect(rows[0].text()).toContain('Erste Massnahme');
        expect(rows[2].find('a').exists()).toBe(false);
    });

    it('says what an activity event did and which fields moved', () => {
        const wrapper = mountTimeline();
        const activity = wrapper.findAll('[data-testid="timeline-event"]')[3];

        expect(activity.text()).toContain('Appointment updated');
        expect(activity.text()).toContain('Status, Notes');
    });

    it('falls back to the empty state', () => {
        const wrapper = mountTimeline([]);

        expect(wrapper.text()).toContain('Noch nichts passiert');
        expect(wrapper.find('[data-testid="timeline-filters"]').exists()).toBe(false);
    });
});
