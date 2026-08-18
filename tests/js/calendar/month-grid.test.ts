import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import MonthGrid from '@/Pages/Calendar/partials/MonthGrid.vue';
import { mountComponent, setAuthedOwner, makeAppointment, makeClient, makeWorker } from '../helpers';
import type { Appointment, CalendarDayTotal } from '@/types';

const dayTotals: CalendarDayTotal[] = [
    { date: '2026-04-08', appointments: 4, minutes: 480 },
    { date: '2026-04-09', appointments: 1, minutes: 60 },
    { date: '2026-04-10', appointments: 0, minutes: 0 },
];

function mountMonth(appointments: Appointment[], overrides: Record<string, unknown> = {}) {
    return mountComponent(MonthGrid, {
        props: {
            appointments,
            currentDate: '2026-04-08',
            showWeekends: false,
            dayTotals,
            ...overrides,
        },
    });
}

function onDay(date: string, count: number, title = 'Routinekontrolle'): Appointment[] {
    return Array.from({ length: count }, (_, i) =>
        makeAppointment({ id: 200 + i, date, start: '09:00', end: '10:00', title }),
    );
}

describe('MonthGrid', () => {
    beforeEach(() => {
        setAuthedOwner();
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-04-08T12:00:00'));
    });

    afterEach(() => vi.useRealTimers());

    it('honours the show-weekends setting instead of always drawing Sa/So', () => {
        expect(mountMonth([]).html()).toContain('repeat(5, minmax(0, 1fr))');
        expect(mountMonth([], { showWeekends: true }).html()).toContain('repeat(7, minmax(0, 1fr))');
    });

    it('labels the columns in German, without hardcoding them', () => {
        const headers = mountMonth([]).findAll('.grid.shrink-0 > div').map((d) => d.text());

        expect(headers).toEqual(['MO', 'DI', 'MI', 'DO', 'FR']);
    });

    it('leads with the customer and follows with the service', () => {
        const wrapper = mountMonth([
            makeAppointment({
                id: 1,
                date: '2026-04-08',
                title: 'Routinekontrolle',
                clients: [makeClient({ id: 1, company_name: 'Hotel Sonne' })],
            }),
        ]);
        const chip = wrapper.get('[data-appointment-id="1"]');

        expect(chip.text().indexOf('Hotel Sonne')).toBeLessThan(chip.text().indexOf('Routinekontrolle'));
    });

    it('shows technician initials instead of full names', () => {
        const wrapper = mountMonth([
            makeAppointment({
                id: 1,
                date: '2026-04-08',
                workers: [makeWorker({ id: 7, name: 'Anna Berg' })],
            }),
        ]);

        expect(wrapper.get('[data-appointment-id="1"]').text()).toContain('AB');
        expect(wrapper.get('[data-appointment-id="1"]').text()).not.toContain('Anna Berg');
    });

    it('turns the overflow into a real link back to the day', async () => {
        const wrapper = mountMonth(onDay('2026-04-08', 5));
        const link = wrapper.get('[data-testid="month-overflow"]');

        expect(link.text()).toContain('+2');
        await link.trigger('click');
        expect(wrapper.emitted('dayClick')).toEqual([['2026-04-08']]);
    });

    it('shows a load bar whose weight tracks how full the day is', () => {
        const wrapper = mountMonth(onDay('2026-04-08', 4));
        const bars = wrapper.findAll('[data-testid="day-load"]');

        expect(bars).toHaveLength(2);
        expect(bars[0].attributes('style')).toContain('opacity: 1');
        expect(bars[1].attributes('style')).toContain('opacity: 0.2');
    });

    it('lets empty weeks collapse instead of padding the month', () => {
        const wrapper = mountMonth(onDay('2026-04-08', 1));
        const rows = wrapper.get('.grid.flex-1').attributes('style') ?? '';

        expect(rows).toContain('minmax(88px, auto)');
        expect(rows).toContain('minmax(40px, auto)');
    });

    it('flags a double booking', () => {
        const wrapper = mountMonth(onDay('2026-04-08', 2), { conflicts: { 200: [201], 201: [200] } });

        expect(wrapper.findAll('[data-conflict="true"]')).toHaveLength(2);
    });
});
