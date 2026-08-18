import { useForm } from '@inertiajs/vue3';
import { isoToLocalParts, localToISO } from './date-utils';
import type { Appointment, ChecklistItem, SeriesScope } from '@/types';

/** What a freshly opened create form starts from. */
export interface AppointmentFormDefaults {
    date: string;
    startTime: string;
    endTime: string;
    workerIds: number[];
    /** The job a "Termin planen" link already named, as the select's string id. */
    contractId: string;
}

interface AppointmentFields {
    contract_id: string;
    worker_ids: number[];
    start_date: string;
    start_time: string;
    end_date: string;
    end_time: string;
    status_id: string;
    notes: string;
    checklist: ChecklistItem[];
    is_recurring: boolean;
    recurrence_type: string;
    recurrence_interval: number;
    recurrence_end: string;
}

function fieldsFor(
    appointment: Appointment | undefined,
    defaults: AppointmentFormDefaults,
): AppointmentFields {
    const start = appointment ? isoToLocalParts(appointment.start_at) : null;
    const end = appointment ? isoToLocalParts(appointment.end_at) : null;

    return {
        contract_id: appointment?.contract?.id?.toString() ?? defaults.contractId,
        worker_ids: appointment?.workers?.map(w => w.id) ?? [...defaults.workerIds],
        start_date: start?.date ?? defaults.date,
        start_time: start?.time ?? defaults.startTime,
        // The end date follows the start; a multi-day appointment is set explicitly.
        end_date: start?.date ?? defaults.date,
        end_time: end?.time ?? defaults.endTime,
        status_id: appointment?.status?.id?.toString() ?? 'none',
        notes: appointment?.notes ?? '',
        checklist: (appointment?.checklist ?? []) as ChecklistItem[],
        is_recurring: !!appointment?.recurrence_type || !!appointment?.parent_id,
        recurrence_type: appointment?.recurrence_type ?? 'weekly',
        recurrence_interval: appointment?.recurrence_interval ?? 1,
        recurrence_end: appointment?.recurrence_end ?? '',
    };
}

/**
 * The appointment drawer's form state: seeding it from an appointment or from
 * the slot the user dragged, tracking whether anything changed, and shaping the
 * request body.
 */
export function useAppointmentForm(
    appointment: () => Appointment | undefined,
    defaults: () => AppointmentFormDefaults,
) {
    const form = useForm<AppointmentFields>(fieldsFor(appointment(), defaults()));

    /** Re-seed every field from the appointment (or the create defaults). */
    function fill(): void {
        Object.assign(form, fieldsFor(appointment(), defaults()));
    }

    /** A comparable image of the fields, for the discard-changes guard. */
    function snapshot(): string {
        return JSON.stringify({
            contract_id: form.contract_id,
            worker_ids: form.worker_ids,
            start_date: form.start_date,
            start_time: form.start_time,
            end_date: form.end_date,
            end_time: form.end_time,
            status_id: form.status_id,
            notes: form.notes,
            checklist: form.checklist,
            is_recurring: form.is_recurring,
            recurrence_type: form.recurrence_type,
            recurrence_interval: form.recurrence_interval,
            recurrence_end: form.recurrence_end,
        });
    }

    /**
     * `scope` is only sent when editing, and only means anything for a series.
     */
    function payload(isEditing: boolean, scope: SeriesScope): Record<string, unknown> {
        const body: Record<string, unknown> = {
            contract_id: form.contract_id ? parseInt(form.contract_id) : null,
            worker_ids: form.worker_ids,
            start_at: localToISO(form.start_date, form.start_time),
            end_at: localToISO(form.end_date, form.end_time),
            status_id: form.status_id && form.status_id !== 'none' ? parseInt(form.status_id) : null,
            notes: form.notes || null,
            checklist: form.checklist.length > 0 ? form.checklist : null,
        };

        if (isEditing) {
            body.scope = scope;

            return body;
        }

        if (form.is_recurring) {
            body.recurrence_type = form.recurrence_type;
            body.recurrence_interval = form.recurrence_type === 'custom' ? form.recurrence_interval : null;
            body.recurrence_end = form.recurrence_end;
        }

        return body;
    }

    return { form, fill, snapshot, payload };
}
