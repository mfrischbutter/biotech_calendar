import type { Appointment } from '@/types';

const KIND_PREFIXES: Record<string, string> = {
    kundentermin: '[T]',
    ohne_termin: '[OT]',
};

export function appointmentLabel(appt: Appointment): string {
    const prefix = appt.kind ? KIND_PREFIXES[appt.kind] ?? '' : '';
    return prefix ? `${prefix} ${appt.title}` : appt.title;
}
