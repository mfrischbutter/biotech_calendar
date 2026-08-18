import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick, ref } from 'vue';

interface ToastCall {
    message: string;
    options: { description?: string; action?: { label: string; onClick: () => void } };
}

const sonner = vi.hoisted(() => {
    const calls: ToastCall[] = [];
    const error = vi.fn();
    let sequence = 0;
    const toast = Object.assign(
        (message: string, options: ToastCall['options'] = {}) => {
            calls.push({ message, options });

            return ++sequence;
        },
        { success: vi.fn(), error, dismiss: vi.fn() },
    );

    return { calls, toast, error };
});

vi.mock('vue-sonner', () => ({ toast: sonner.toast }));

import { useCalendarAppointments } from '@/lib/use-calendar-appointments';
import { dismissPendingUndos } from '@/lib/use-toast';
import { localToISO } from '@/lib/date-utils';
import { inertiaRouterMock } from '../setup';
import { makeAppointment, makeWorker, setAuthedOwner } from '../helpers';
import type { Appointment, CalendarEmployee } from '@/types';

const ANNA: CalendarEmployee = { id: 501, first_name: 'Anna', last_name: 'Berg', name: 'Anna Berg' };

function lastPut(): { url: string; body: Record<string, unknown>; options: Record<string, () => void> } {
    const call = inertiaRouterMock.put.mock.calls[inertiaRouterMock.put.mock.calls.length - 1];

    return {
        url: call[0] as string,
        body: call[1] as Record<string, unknown>,
        options: call[2] as Record<string, () => void>,
    };
}

function setup(appointment: Appointment) {
    const appointments = ref<Appointment[]>([appointment]);
    const mutations = useCalendarAppointments({
        appointments: () => appointments.value,
        employees: () => [ANNA],
    });

    return { appointments, mutations };
}

describe('useCalendarAppointments', () => {
    beforeEach(() => {
        setAuthedOwner();
        inertiaRouterMock.put.mockClear();
        sonner.calls.length = 0;
        sonner.error.mockClear();
        dismissPendingUndos();
    });

    afterEach(() => dismissPendingUndos());

    it('moves the block immediately, before the server has answered', () => {
        const appointment = makeAppointment({ id: 5, date: '2026-04-08', start: '09:00', end: '10:00' });
        const { mutations } = setup(appointment);

        mutations.move(appointment, '2026-04-09', '11:00', '12:00');

        expect(mutations.effective(appointment).start_at).toBe(localToISO('2026-04-09', '11:00'));
        expect(mutations.effective(appointment).end_at).toBe(localToISO('2026-04-09', '12:00'));
        expect(lastPut().url).toBe('/appointments/update/5');
        expect(lastPut().body.start_at).toBe(localToISO('2026-04-09', '11:00'));
        // A drag moves the block that was grabbed, never the rest of the series.
        expect(lastPut().body.scope).toBe('single');
    });

    it('puts the block back and says so when the request is refused', () => {
        const appointment = makeAppointment({ id: 5, date: '2026-04-08', start: '09:00', end: '10:00' });
        const { mutations } = setup(appointment);

        mutations.move(appointment, '2026-04-09', '11:00', '12:00');
        lastPut().options.onError();

        expect(mutations.overrides.has(5)).toBe(false);
        expect(mutations.effective(appointment).start_at).toBe(appointment.start_at);
        expect(sonner.error).toHaveBeenCalledTimes(1);
    });

    it('offers to take a successful move back', () => {
        const appointment = makeAppointment({ id: 5, date: '2026-04-08', start: '09:00', end: '10:00' });
        const { mutations } = setup(appointment);

        mutations.move(appointment, '2026-04-09', '11:00', '12:00');
        lastPut().options.onSuccess();

        expect(sonner.calls).toHaveLength(1);
        expect(sonner.calls[0].options.action?.label).toBe('Undo');

        inertiaRouterMock.put.mockClear();
        sonner.calls[0].options.action?.onClick();

        expect(lastPut().body.start_at).toBe(appointment.start_at);
        expect(lastPut().body.end_at).toBe(appointment.end_at);
    });

    it('keeps the start when only the end is dragged', () => {
        const appointment = makeAppointment({ id: 6, date: '2026-04-08', start: '09:00', end: '10:00' });
        const { mutations } = setup(appointment);

        mutations.resize(appointment, '11:30');

        expect(mutations.effective(appointment).start_at).toBe(appointment.start_at);
        expect(mutations.effective(appointment).end_at).toBe(localToISO('2026-04-08', '11:30'));
    });

    it('reassigns the block on the team board and sends the new owner', () => {
        const appointment = makeAppointment({
            id: 7,
            date: '2026-04-08',
            start: '09:00',
            end: '10:00',
            workers: [makeWorker({ id: 502, name: 'Max Kern' })],
        });
        const { mutations } = setup(appointment);

        mutations.teamMove(appointment, '2026-04-09', '09:00', '10:00', ANNA.id);

        expect(mutations.effective(appointment).workers).toEqual([ANNA]);
        expect(lastPut().body.worker_ids).toEqual([ANNA.id]);
    });

    it('drops an unassigned drop back into the unassigned row', () => {
        const appointment = makeAppointment({
            id: 8,
            date: '2026-04-08',
            start: '09:00',
            end: '10:00',
            workers: [makeWorker({ id: 502 })],
        });
        const { mutations } = setup(appointment);

        mutations.teamMove(appointment, '2026-04-08', '09:00', '10:00', null);

        expect(mutations.effective(appointment).workers).toEqual([]);
        expect(lastPut().body.worker_ids).toEqual([]);
    });

    it('forgets every provisional position once fresh data arrives', async () => {
        const appointment = makeAppointment({ id: 9, date: '2026-04-08', start: '09:00', end: '10:00' });
        const { appointments, mutations } = setup(appointment);

        mutations.move(appointment, '2026-04-09', '11:00', '12:00');
        expect(mutations.overrides.size()).toBe(1);

        appointments.value = [makeAppointment({ id: 9, date: '2026-04-09', start: '11:00', end: '12:00' })];
        await nextTick();

        expect(mutations.overrides.size()).toBe(0);
    });
});
