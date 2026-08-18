import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import TimeGrid from '@/Pages/Calendar/partials/TimeGrid.vue';
import { mountComponent, setAuthedOwner, makeAppointment, makeStatus, makeWorker } from '../helpers';
import type { Appointment, ConflictMap } from '@/types';

const WEEK = ['2026-04-06', '2026-04-07', '2026-04-08', '2026-04-09', '2026-04-10'];

function mountGrid(appointments: Appointment[], overrides: Record<string, unknown> = {}) {
    return mountComponent(TimeGrid, {
        props: {
            appointments,
            dates: WEEK,
            startHour: 6,
            endHour: 20,
            ...overrides,
        },
    });
}

function crowd(count: number, workers = [makeWorker({ id: 501, name: 'Anna Berg' })]): Appointment[] {
    return Array.from({ length: count }, (_, i) =>
        makeAppointment({ id: 100 + i, date: '2026-04-08', start: '09:00', end: '10:00', workers }),
    );
}

describe('TimeGrid', () => {
    beforeEach(() => {
        setAuthedOwner();
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-04-08T12:00:00'));
    });

    afterEach(() => vi.useRealTimers());

    it('renders one block per appointment when the day is not crowded', () => {
        const wrapper = mountGrid(crowd(3));

        expect(wrapper.findAll('[data-appointment-id]')).toHaveLength(3);
        expect(wrapper.find('[data-testid="overflow-chip"]').exists()).toBe(false);
    });

    it('collapses a crowded week column into a readable pair plus a count', () => {
        const wrapper = mountGrid(crowd(5));

        expect(wrapper.findAll('[data-appointment-id]')).toHaveLength(2);
        const chip = wrapper.get('[data-testid="overflow-chip"]');
        expect(chip.text()).toContain('+3');
    });

    it('expands the day when the count is clicked', async () => {
        const wrapper = mountGrid(crowd(5));
        await wrapper.get('[data-testid="overflow-chip"]').trigger('click');

        expect(wrapper.emitted('expandDay')).toEqual([['2026-04-08']]);
    });

    it('shows everything in the single-day view, where there is room', () => {
        const wrapper = mountGrid(crowd(5), { dates: ['2026-04-08'] });

        expect(wrapper.findAll('[data-appointment-id]')).toHaveLength(5);
        expect(wrapper.find('[data-testid="overflow-chip"]').exists()).toBe(false);
    });

    it('renders only what it is given, so a filtered board really is narrower', () => {
        const anna = makeWorker({ id: 501, name: 'Anna Berg' });
        const max = makeWorker({ id: 502, name: 'Max Kern' });
        const all = [
            makeAppointment({ id: 1, date: '2026-04-08', start: '09:00', end: '10:00', workers: [anna] }),
            makeAppointment({ id: 2, date: '2026-04-09', start: '09:00', end: '10:00', workers: [max] }),
        ];

        expect(mountGrid(all).findAll('[data-appointment-id]')).toHaveLength(2);
        expect(mountGrid([all[0]]).findAll('[data-appointment-id]')).toHaveLength(1);
    });

    it('marks double-booked appointments in danger', () => {
        const appts = [
            makeAppointment({ id: 1, date: '2026-04-08', start: '09:00', end: '10:00', workers: [makeWorker({ id: 501 })] }),
            makeAppointment({ id: 2, date: '2026-04-08', start: '09:30', end: '10:30', workers: [makeWorker({ id: 501 })] }),
        ];
        const conflicts: ConflictMap = { 1: [2], 2: [1] };
        const wrapper = mountGrid(appts, { conflicts });

        const flagged = wrapper.findAll('[data-conflict="true"]');
        expect(flagged).toHaveLength(2);
        expect(flagged[0].classes().join(' ')).toContain('ring-danger');
    });

    it('leaves untouched appointments unflagged', () => {
        const appts = [
            makeAppointment({ id: 1, date: '2026-04-08', start: '09:00', end: '10:00', workers: [makeWorker({ id: 501 })] }),
            makeAppointment({ id: 2, date: '2026-04-08', start: '11:00', end: '12:00', workers: [makeWorker({ id: 501 })] }),
        ];
        const wrapper = mountGrid(appts, { conflicts: {} });

        expect(wrapper.findAll('[data-conflict="true"]')).toHaveLength(0);
    });

    it('outlines unassigned work with a dashed edge', () => {
        const wrapper = mountGrid([
            makeAppointment({ id: 1, date: '2026-04-08', start: '09:00', end: '10:00', workers: [] }),
            makeAppointment({ id: 2, date: '2026-04-08', start: '11:00', end: '12:00', workers: [makeWorker({ id: 501 })] }),
        ]);

        const unassigned = wrapper.findAll('[data-unassigned="true"]');
        expect(unassigned).toHaveLength(1);
        expect(unassigned[0].classes().join(' ')).toContain('outline-dashed');
    });

    it('marks a repeating appointment', () => {
        const single = makeAppointment({ id: 1, date: '2026-04-08', start: '09:00', end: '10:00' });
        const series = {
            ...makeAppointment({ id: 2, date: '2026-04-09', start: '09:00', end: '10:00' }),
            recurrence_type: 'weekly',
        } as Appointment;

        const wrapper = mountGrid([single, series]);

        expect(wrapper.findAll('[data-testid="recurring-marker"]')).toHaveLength(1);
    });

    it('draws the current time line on today only', () => {
        const wrapper = mountGrid([]);

        expect(wrapper.findAll('[data-testid="current-time-line"]')).toHaveLength(1);
    });

    it('leads with the customer, then the service', () => {
        const wrapper = mountGrid([
            makeAppointment({ id: 1, date: '2026-04-08', start: '09:00', end: '11:00', title: 'Routinekontrolle' }),
        ]);
        const card = wrapper.get('[data-appointment-id="1"]');

        expect(card.text().indexOf('Baeckerei Bergmann')).toBeLessThan(card.text().indexOf('Routinekontrolle'));
    });

    it('tints the status onto the block', () => {
        const wrapper = mountGrid([
            makeAppointment({
                id: 1,
                date: '2026-04-08',
                start: '09:00',
                end: '10:00',
                status: makeStatus({ id: 3, color: '#22c55e' }),
            }),
        ]);

        expect(wrapper.get('[data-appointment-id="1"]').attributes('style')).toContain('border-left-color');
    });
});
