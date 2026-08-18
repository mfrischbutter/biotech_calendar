import { beforeEach, describe, expect, it, vi } from 'vitest';

interface ToastCall {
    message: string;
    options: { description?: string; action?: { label: string; onClick: () => void } };
}

const sonner = vi.hoisted(() => {
    const calls: ToastCall[] = [];
    const success = vi.fn();
    let sequence = 0;
    const toast = Object.assign(
        (message: string, options: ToastCall['options'] = {}) => {
            calls.push({ message, options });

            return ++sequence;
        },
        { success, error: vi.fn(), dismiss: vi.fn() },
    );

    return { calls, toast, success };
});

vi.mock('vue-sonner', () => ({ toast: sonner.toast }));

import { useAppointmentToasts } from '@/lib/use-appointment-toasts';
import { dismissPendingUndos } from '@/lib/use-toast';
import { inertiaRouterMock } from '../setup';
import { setAuthedOwner } from '../helpers';

const body = { contract_id: 1, status_id: 4, notes: 'Zugang über Hof' };

describe('useAppointmentToasts', () => {
    beforeEach(() => {
        setAuthedOwner();
        sonner.calls.length = 0;
        sonner.success.mockClear();
        inertiaRouterMock.put.mockClear();
        dismissPendingUndos();
    });

    it('confirms a brand new appointment', () => {
        useAppointmentToasts().announceSaved({
            appointmentId: null,
            body,
            previousStatusId: null,
            nextStatusId: 4,
            nextStatusName: 'In Arbeit',
        });

        expect(sonner.success).toHaveBeenCalledWith('Appointment created', { description: undefined });
        expect(sonner.calls).toHaveLength(0);
    });

    it('confirms an edit that left the status alone', () => {
        useAppointmentToasts().announceSaved({
            appointmentId: 9,
            body,
            previousStatusId: 4,
            nextStatusId: 4,
            nextStatusName: 'In Arbeit',
        });

        expect(sonner.success).toHaveBeenCalledWith('Appointment saved', { description: undefined });
    });

    it('offers to put a changed status back, leaving every other field as saved', () => {
        useAppointmentToasts().announceSaved({
            appointmentId: 9,
            body,
            previousStatusId: 2,
            nextStatusId: 4,
            nextStatusName: 'In Arbeit',
        });

        expect(sonner.calls).toHaveLength(1);
        expect(sonner.calls[0].message).toBe('Status changed');
        expect(sonner.calls[0].options.description).toBe('In Arbeit');

        sonner.calls[0].options.action?.onClick();

        const [url, payload] = inertiaRouterMock.put.mock.calls[0];
        expect(url).toBe('/appointments/update/9');
        expect(payload).toEqual({ ...body, status_id: 2 });
    });

    it('names the empty status when one is cleared', () => {
        useAppointmentToasts().announceSaved({
            appointmentId: 9,
            body,
            previousStatusId: 2,
            nextStatusId: null,
            nextStatusName: null,
        });

        expect(sonner.calls[0].options.description).toBe('No status');
    });

    it('confirms a delete', () => {
        useAppointmentToasts().announceDeleted();

        expect(sonner.success).toHaveBeenCalledWith('Appointment deleted', { description: undefined });
    });
});
