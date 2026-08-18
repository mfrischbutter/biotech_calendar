import { beforeEach, describe, expect, it } from 'vitest';
import { useAppointmentForm, type AppointmentFormDefaults } from '@/lib/use-appointment-form';
import { makeAppointment, makeStatus, makeWorker, setAuthedOwner } from '../helpers';
import type { Appointment } from '@/types';

const defaults: AppointmentFormDefaults = {
    date: '2026-04-08',
    startTime: '09:00',
    endTime: '10:00',
    workerIds: [],
};

function build(appointment?: Appointment) {
    return useAppointmentForm(() => appointment, () => defaults);
}

describe('useAppointmentForm', () => {
    beforeEach(() => setAuthedOwner());

    it('starts a create form from the slot the user dragged', () => {
        const { form } = build();

        expect(form.start_date).toBe('2026-04-08');
        expect(form.start_time).toBe('09:00');
        expect(form.end_time).toBe('10:00');
        expect(form.status_id).toBe('none');
        expect(form.is_recurring).toBe(false);
    });

    it('seeds an edit form from the appointment', () => {
        const status = makeStatus({ id: 12 });
        const worker = makeWorker({ id: 4 });
        const appointment = makeAppointment({ start: '13:00', end: '14:30', workers: [worker], status });
        appointment.notes = 'Zugang über den Hof';

        const { form } = build(appointment);

        expect(form.start_time).toBe('13:00');
        expect(form.end_time).toBe('14:30');
        expect(form.status_id).toBe('12');
        expect(form.worker_ids).toEqual([4]);
        expect(form.notes).toBe('Zugang über den Hof');
    });

    it('recognises an occurrence as part of a series', () => {
        const occurrence = makeAppointment();
        occurrence.parent_id = 99;

        expect(build(occurrence).form.is_recurring).toBe(true);
    });

    it('sends the recurrence rule only when creating', () => {
        const { form, payload } = build();
        form.is_recurring = true;
        form.recurrence_type = 'custom';
        form.recurrence_interval = 4;
        form.recurrence_end = '2026-12-31';

        const body = payload(false, 'single');

        expect(body.recurrence_type).toBe('custom');
        expect(body.recurrence_interval).toBe(4);
        expect(body.recurrence_end).toBe('2026-12-31');
        expect(body.scope).toBeUndefined();
    });

    it('drops the interval for a rule that does not use one', () => {
        const { form, payload } = build();
        form.is_recurring = true;
        form.recurrence_type = 'monthly';

        expect(payload(false, 'single').recurrence_interval).toBeNull();
    });

    it('carries the series scope on an edit, never the recurrence rule', () => {
        const appointment = makeAppointment();
        appointment.parent_id = 99;
        const { payload } = build(appointment);

        const body = payload(true, 'series');

        expect(body.scope).toBe('series');
        expect(body.recurrence_type).toBeUndefined();
    });

    it('defaults an edit to this occurrence only', () => {
        expect(build(makeAppointment()).payload(true, 'single').scope).toBe('single');
    });

    it('turns the empty sentinels into nulls the server understands', () => {
        const { payload } = build();
        const body = payload(false, 'single');

        expect(body.status_id).toBeNull();
        expect(body.notes).toBeNull();
        expect(body.checklist).toBeNull();
        expect(body.contract_id).toBeNull();
    });

    it('notices an edit through the snapshot the discard guard compares', () => {
        const { form, snapshot } = build();
        const before = snapshot();

        form.notes = 'Neu';

        expect(snapshot()).not.toBe(before);
    });

    it('re-seeds every field when the drawer is reopened', () => {
        const appointment = makeAppointment({ start: '13:00', end: '14:30' });
        const { form, fill } = build(appointment);

        form.notes = 'halb getippt';
        fill();

        expect(form.notes).toBe('');
        expect(form.start_time).toBe('13:00');
    });
});
