import { beforeEach, describe, expect, it } from 'vitest';
import { useAppointmentDialogs } from '@/lib/use-appointment-dialogs';
import { useAppointmentForm, type AppointmentFormDefaults } from '@/lib/use-appointment-form';
import { wantsCreateForm } from '@/lib/create-intent';
import { setAuthedOwner } from '../helpers';

/*
 * "Termin planen" on a client, on a contract, in the side peek and in the
 * top-bar search all link with ?new=1 plus the record they were opened from.
 * The calendar dropped everything but the flag, so each of those buttons was a
 * page change that left the user to re-find what they had just been looking at.
 */

function dialogs() {
    return useAppointmentDialogs({
        appointments: () => [],
        fallbackDate: () => '2026-04-08',
    });
}

function formWith(overrides: Partial<AppointmentFormDefaults>) {
    const defaults: AppointmentFormDefaults = {
        date: '2026-04-08',
        startTime: '09:00',
        endTime: '10:00',
        workerIds: [],
        contractId: '',
        ...overrides,
    };

    return useAppointmentForm(() => undefined, () => defaults).form;
}

describe('create form prefill', () => {
    beforeEach(() => setAuthedOwner());

    it('opens the form already pointed at the job the link named', () => {
        const d = dialogs();
        d.openCreateFor({ contractId: 42 });

        expect(d.createOpen.value).toBe(true);
        expect(d.defaults.value.contractId).toBe('42');
    });

    it('carries a customer name when the link named no job', () => {
        const d = dialogs();
        d.openCreateFor({ clientName: 'Klaus Bergmann' });

        expect(d.defaults.value.contractId).toBe('');
        expect(d.defaults.value.clientName).toBe('Klaus Bergmann');
    });

    /*
     * The prefill belongs to the arrival, not to the session: pressing "Neuer
     * Termin" afterwards must not silently book against the job the user
     * happened to walk in from.
     */
    it('forgets the prefill on the next plain create', () => {
        const d = dialogs();
        d.openCreateFor({ contractId: 42, clientName: 'Klaus Bergmann' });
        d.openCreate();

        expect(d.defaults.value.contractId).toBe('');
        expect(d.defaults.value.clientName).toBe('');
    });

    it('keeps a dragged slot free of any earlier prefill', () => {
        const d = dialogs();
        d.openCreateFor({ contractId: 42 });
        d.openCreateForSlot('2026-04-09', '11:00', '12:00', 7);

        expect(d.defaults.value.contractId).toBe('');
        expect(d.defaults.value.workerIds).toEqual([7]);
    });

    it('seeds the form field itself, not just the dialog state', () => {
        expect(formWith({ contractId: '42' }).contract_id).toBe('42');
    });

    it('leaves the job empty when nothing named one', () => {
        expect(formWith({}).contract_id).toBe('');
    });
});

describe('wantsCreateForm', () => {
    function withSearch(search: string): boolean {
        window.history.replaceState({}, '', `/calendar${search}`);

        return wantsCreateForm();
    }

    it('recognises the convention every "plan an appointment" link uses', () => {
        expect(withSearch('?new=1')).toBe(true);
        expect(withSearch('?new=1&contract=42')).toBe(true);
    });

    it('leaves an ordinary visit alone', () => {
        expect(withSearch('')).toBe(false);
        expect(withSearch('?view=month')).toBe(false);
        expect(withSearch('?new=0')).toBe(false);
    });
});
