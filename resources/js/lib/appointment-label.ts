import { initials } from '@/lib/utils';
import type { Appointment } from '@/types';

const KIND_PREFIXES: Record<string, string> = {
    kundentermin: '[T]',
    ohne_termin: '[OT]',
};

export function appointmentLabel(appt: Appointment): string {
    const title = appt.contract?.title ?? '';
    const kind = appt.contract?.kind;
    const prefix = kind ? KIND_PREFIXES[kind] ?? '' : '';
    return prefix ? `${prefix} ${title}` : title;
}

/**
 * Who the job is for. In a cramped cell the customer is the thing that
 * identifies an appointment — "Bäckerei Bergmann" beats "Routinekontrolle Ho…".
 */
export function appointmentClientName(appt: Appointment): string {
    const clients = appt.contract?.clients ?? [];
    if (clients.length === 0) {
        return '';
    }

    const first = clients[0];
    const name = first.company_name || first.name || `${first.first_name} ${first.last_name}`.trim();

    return clients.length > 1 ? `${name} +${clients.length - 1}` : name;
}

/** What is being done — the contract title, without the kind prefix. */
export function appointmentService(appt: Appointment): string {
    return appt.contract?.title ?? '';
}

/** Technician initials, for cells too small to carry full names. */
export function appointmentWorkerInitials(appt: Appointment): string[] {
    return appt.workers.map((worker) => initials(worker.name));
}

/** An occurrence of, or the template for, a repeating appointment. */
export function isRecurring(appt: Appointment): boolean {
    return appt.parent_id !== null || appt.recurrence_type !== null;
}

/**
 * Hours as a German decimal, e.g. 390 minutes → "6,5". The unit is left to the
 * caller so it can go through the translation layer.
 */
export function formatHours(minutes: number): string {
    return (Math.round((minutes / 60) * 10) / 10).toString().replace('.', ',');
}
