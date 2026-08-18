import { describe, expect, it } from 'vitest';
import { computeOverlapLayout } from '@/lib/overlap-layout';
import { makeAppointment } from '../helpers';
import type { Appointment } from '@/types';

/**
 * The week grid is only as useful as its narrowest block. These tests pin the
 * point where "show everything" stops being the readable answer.
 */
function at(start: string, end: string, id?: number): Appointment {
    return makeAppointment({ id, start, end });
}

function concurrent(count: number): Appointment[] {
    return Array.from({ length: count }, (_, i) => at('09:00', '10:00', 1000 + i));
}

describe('computeOverlapLayout', () => {
    it('returns nothing for an empty day', () => {
        const layout = computeOverlapLayout([]);

        expect(layout.slots.size).toBe(0);
        expect(layout.overflow).toEqual([]);
    });

    it('gives a lone appointment the full column', () => {
        const appt = at('09:00', '10:00');
        const layout = computeOverlapLayout([appt]);

        expect(layout.slots.get(appt.id)).toMatchObject({ column: 0, columnSpan: 1, totalColumns: 1 });
        expect(layout.overflow).toEqual([]);
    });

    it('cascades two overlapping appointments', () => {
        const [a, b] = [at('09:00', '11:00'), at('09:30', '10:30')];
        const layout = computeOverlapLayout([a, b]);

        expect(layout.slots.get(a.id)).toMatchObject({ column: 0, columnSpan: 2, totalColumns: 2 });
        expect(layout.slots.get(b.id)).toMatchObject({ column: 1, columnSpan: 1, totalColumns: 2 });
        expect(layout.overflow).toEqual([]);
    });

    it('still shows every block at three concurrent', () => {
        const appts = concurrent(3);
        const layout = computeOverlapLayout(appts);

        expect(layout.slots.size).toBe(3);
        expect(layout.overflow).toEqual([]);
        expect([...layout.slots.values()].map((s) => s.totalColumns)).toEqual([3, 3, 3]);
    });

    it('collapses the tail above three concurrent', () => {
        const appts = concurrent(4);
        const layout = computeOverlapLayout(appts);

        expect(layout.slots.size).toBe(2);
        expect([...layout.slots.values()].map((s) => [s.column, s.columnSpan, s.totalColumns])).toEqual([
            [0, 2, 2],
            [1, 1, 2],
        ]);
        expect(layout.overflow).toHaveLength(1);
        expect(layout.overflow[0]).toMatchObject({ count: 2, startMinutes: 540, endMinutes: 600 });
    });

    it('counts the whole hidden tail, however deep it gets', () => {
        const layout = computeOverlapLayout(concurrent(6));

        expect(layout.slots.size).toBe(2);
        expect(layout.overflow[0].count).toBe(4);
        expect(layout.overflow[0].appointments).toHaveLength(4);
    });

    it('never collapses when the ceiling is lifted', () => {
        const layout = computeOverlapLayout(concurrent(6), undefined, { maxConcurrent: Infinity });

        expect(layout.slots.size).toBe(6);
        expect(layout.overflow).toEqual([]);
    });

    it('honours a different visible-column budget', () => {
        const layout = computeOverlapLayout(concurrent(5), undefined, { visibleColumns: 3 });

        expect(layout.slots.size).toBe(3);
        expect([...layout.slots.values()].map((s) => s.totalColumns)).toEqual([3, 3, 3]);
        expect(layout.overflow[0].count).toBe(2);
    });

    it('keeps separate clusters independent', () => {
        const morning = concurrent(4);
        const afternoon = [at('14:00', '15:00', 2001), at('14:15', '15:15', 2002)];
        const layout = computeOverlapLayout([...morning, ...afternoon]);

        expect(layout.overflow).toHaveLength(1);
        expect(layout.slots.get(afternoon[0].id)).toMatchObject({ totalColumns: 2 });
    });

    it('splits a hidden tail that covers two separate stretches', () => {
        // A long block bridges both crowds into one cluster, but the crowds
        // themselves do not overlap, so they get a chip each.
        const bridge = at('09:00', '13:00', 3000);
        const morning = [at('09:00', '10:00', 3001), at('09:00', '10:00', 3002), at('09:00', '10:00', 3003)];
        const midday = [at('11:00', '12:00', 3004), at('11:00', '12:00', 3005), at('11:00', '12:00', 3006)];

        const layout = computeOverlapLayout([bridge, ...morning, ...midday]);

        expect(layout.overflow.map((g) => [g.startMinutes, g.endMinutes, g.count])).toEqual([
            [540, 600, 2],
            [660, 720, 2],
        ]);
    });

    it('lays out from the dragged position, not the saved one', () => {
        const dragged = at('09:00', '10:00', 4001);
        const other = at('14:00', '15:00', 4002);

        const layout = computeOverlapLayout(
            [dragged, other],
            new Map([[dragged.id, { startMinutes: 14 * 60 + 30, endMinutes: 15 * 60 + 30 }]]),
        );

        expect(layout.slots.get(other.id)).toMatchObject({ column: 0, totalColumns: 2 });
        expect(layout.slots.get(dragged.id)).toMatchObject({ column: 1, totalColumns: 2 });
    });

    it('gives every overflow chip a distinct key', () => {
        const layout = computeOverlapLayout([
            at('09:00', '13:00', 5000),
            ...[5001, 5002, 5003].map((id) => at('09:00', '10:00', id)),
            ...[5004, 5005, 5006].map((id) => at('11:00', '12:00', id)),
        ]);

        expect(new Set(layout.overflow.map((g) => g.key)).size).toBe(layout.overflow.length);
    });
});
