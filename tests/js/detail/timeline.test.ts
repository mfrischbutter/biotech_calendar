import { describe, expect, it } from 'vitest';
import { countByType, eventTime, filterByType, groupByDay } from '@/lib/timeline';
import type { TimelineEvent } from '@/types';

function event(overrides: Partial<TimelineEvent> = {}): TimelineEvent {
    return {
        id: 'appointment-1',
        type: 'appointment',
        action: null,
        at: '2026-04-10T08:00:00.000000Z',
        title: 'Routinekontrolle',
        excerpt: null,
        actor: null,
        url: null,
        status: null,
        fields: [],
        ...overrides,
    };
}

describe('timeline grouping', () => {
    const events: TimelineEvent[] = [
        event({ id: 'a-1', type: 'appointment', at: '2026-04-10T08:00:00.000Z' }),
        event({ id: 'd-1', type: 'document', at: '2026-04-07T11:00:00.000Z' }),
        event({ id: 'c-1', type: 'comment', at: '2026-04-07T09:00:00.000Z' }),
        event({ id: 'c-2', type: 'comment', at: '2026-04-06T14:00:00.000Z' }),
        event({ id: 'x-1', type: 'activity', action: 'updated', at: '2026-04-01T10:00:00.000Z' }),
    ];

    it('counts only the types that occur, in a fixed order', () => {
        expect(countByType(events)).toEqual([
            { type: 'appointment', count: 1 },
            { type: 'comment', count: 2 },
            { type: 'document', count: 1 },
            { type: 'activity', count: 1 },
        ]);
    });

    it('filters to one type and keeps everything under "all"', () => {
        expect(filterByType(events, 'comment').map((e) => e.id)).toEqual(['c-1', 'c-2']);
        expect(filterByType(events, 'all')).toHaveLength(5);
    });

    it('buckets events by day, newest day first', () => {
        const groups = groupByDay(events);

        expect(groups.map((group) => group.events.length)).toEqual([1, 2, 1, 1]);
        expect(groups[1].events.map((e) => e.id)).toEqual(['d-1', 'c-1']);
        expect(groups[0].label).toContain('April');
    });

    it('sorts events inside a day newest first even when handed them jumbled', () => {
        const jumbled = [
            event({ id: 'late', at: '2026-04-07T18:00:00.000Z' }),
            event({ id: 'early', at: '2026-04-07T06:00:00.000Z' }),
        ];

        expect(groupByDay(jumbled)[0].events.map((e) => e.id)).toEqual(['late', 'early']);
    });

    it('prints a time of day, and nothing for a broken timestamp', () => {
        expect(eventTime(event({ at: '2026-04-07T09:30:00' }))).toBe('09:30');
        expect(eventTime(event({ at: 'not-a-date' }))).toBe('');
    });
});
