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
