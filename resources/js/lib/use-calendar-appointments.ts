import { watch } from 'vue';
import { router } from '@inertiajs/vue3';
import type { FormDataConvertible } from '@inertiajs/core';
import { format, parseISO } from 'date-fns';
import { de } from 'date-fns/locale';
import { localToISO, localDateString, isoToLocalParts } from '@/lib/date-utils';
import { useOptimistic, type OptimisticHandle, type OptimisticStore } from '@/lib/use-optimistic';
import { toastError, toastUndoable } from '@/lib/use-toast';
import { useTrans } from '@/lib/use-trans';
import { usePermissions } from '@/lib/use-permissions';
import type { Appointment, CalendarEmployee } from '@/types';

/** What a drag provisionally changed, before the server has confirmed it. */
export interface AppointmentOverride {
    start_at: string;
    end_at: string;
    workers: Appointment['workers'];
}

export interface CalendarAppointmentsSource {
    appointments: () => Appointment[];
    employees: () => CalendarEmployee[];
}

export interface UseCalendarAppointments {
    overrides: OptimisticStore<AppointmentOverride>;
    /** The appointment as the grid should draw it right now. */
    effective: (appointment: Appointment) => Appointment;
    move: (appointment: Appointment, date: string, startTime: string, endTime: string) => void;
    resize: (appointment: Appointment, endTime: string) => void;
    /** Team board: a drop changes the day, the time and who owns it. */
    teamMove: (
        appointment: Appointment,
        date: string,
        startTime: string,
        endTime: string,
        employeeId: number | null,
    ) => void;
}

/**
 * Dragging a block is a full update of the appointment, so every field the form
 * owns has to travel with it or the server would blank it out.
 */
function body(appointment: Appointment, next: AppointmentOverride): Record<string, FormDataConvertible> {
    return {
        contract_id: appointment.contract?.id ?? null,
        worker_ids: next.workers.map((worker) => worker.id),
        status_id: appointment.status?.id ?? null,
        notes: appointment.notes,
        checklist: appointment.checklist,
        start_at: next.start_at,
        end_at: next.end_at,
        // A drag moves the block the user grabbed, never its siblings.
        scope: 'single',
    } as Record<string, FormDataConvertible>;
}

/**
 * Every write the calendar grids make, applied to the screen first and to the
 * server second.
 *
 * The block lands where it was dropped immediately; a refused request puts it
 * back where it came from and says so, and a successful one offers to take the
 * move back for a few seconds, because a mis-drop is silent otherwise.
 */
export function useCalendarAppointments(source: CalendarAppointmentsSource): UseCalendarAppointments {
    const { t } = useTrans();
    const { may } = usePermissions();
    const overrides = useOptimistic<AppointmentOverride>();

    // Fresh server data is the truth; every provisional position is now stale.
    watch(source.appointments, () => overrides.clear());

    /** The server's version of a block, even when the screen shows a drag. */
    function server(appointment: Appointment): Appointment {
        return source.appointments().find((candidate) => candidate.id === appointment.id) ?? appointment;
    }

    function describe(next: AppointmentOverride): string {
        const day = format(parseISO(localDateString(next.start_at)), 'EE, d. MMM', { locale: de });

        return `${day} · ${isoToLocalParts(next.start_at).time} – ${isoToLocalParts(next.end_at).time}`;
    }

    /** Put the block back and say so — once, whichever callback gets here first. */
    function refuse(handle: OptimisticHandle): void {
        if (handle.isSettled()) return;

        handle.rollback();
        toastError(t('Change could not be saved'), t('The appointment was put back where it was.'));
    }

    function send(appointment: Appointment, next: AppointmentOverride, announce: (() => void) | null): void {
        // The grids do not gate dragging on permission, so a read-only user can
        // still start one. Refuse here rather than drawing a move the server
        // will certainly reject.
        if (!may('appointments.edit')) {
            toastError(t('Change could not be saved'), t('You are not allowed to change appointments.'));

            return;
        }

        const handle = overrides.apply(appointment.id, next);

        router.put(route('appointments.update', appointment.id), body(appointment, next), {
            preserveScroll: true,
            onSuccess: () => {
                handle.commit();
                announce?.();
            },
            onError: () => refuse(handle),
            // Inertia only calls onError for a response carrying props.errors.
            // A 403, a 500 or a network failure reaches neither callback, so the
            // block would otherwise stay drawn at a time the server rejected.
            onFinish: () => refuse(handle),
        });
    }

    function commit(appointment: Appointment, next: AppointmentOverride, message: string): void {
        const original = server(appointment);
        const previous: AppointmentOverride = {
            start_at: original.start_at,
            end_at: original.end_at,
            workers: original.workers,
        };

        send(original, next, () => {
            toastUndoable({
                message,
                description: describe(next),
                undoLabel: t('Undo'),
                onUndo: () => send(original, previous, null),
            });
        });
    }

    function effective(appointment: Appointment): Appointment {
        const override = overrides.get(appointment.id);
        if (!override) return appointment;

        return {
            ...appointment,
            start_at: override.start_at,
            end_at: override.end_at,
            workers: override.workers,
        };
    }

    return {
        overrides,
        effective,
        move(appointment, date, startTime, endTime) {
            commit(
                appointment,
                {
                    start_at: localToISO(date, startTime),
                    end_at: localToISO(date, endTime),
                    workers: appointment.workers,
                },
                t('Appointment moved'),
            );
        },
        resize(appointment, endTime) {
            commit(
                appointment,
                {
                    start_at: appointment.start_at,
                    end_at: localToISO(localDateString(appointment.start_at), endTime),
                    workers: appointment.workers,
                },
                t('Appointment duration changed'),
            );
        },
        teamMove(appointment, date, startTime, endTime, employeeId) {
            const worker = employeeId
                ? source.employees().find((employee) => employee.id === employeeId)
                : undefined;

            commit(
                appointment,
                {
                    start_at: localToISO(date, startTime),
                    end_at: localToISO(date, endTime),
                    workers: worker ? [worker] : [],
                },
                t('Appointment moved'),
            );
        },
    };
}
