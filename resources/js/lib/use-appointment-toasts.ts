import { router } from '@inertiajs/vue3';
import type { FormDataConvertible } from '@inertiajs/core';
import { toastSuccess, toastUndoable } from '@/lib/use-toast';
import { useTrans } from '@/lib/use-trans';

export interface AppointmentSaveResult {
    /** null on create — a brand new appointment has nothing to put back. */
    appointmentId: number | null;
    /** The exact body that was just accepted, so an undo can replay it. */
    body: Record<string, FormDataConvertible>;
    previousStatusId: number | null;
    nextStatusId: number | null;
    nextStatusName: string | null;
}

export interface UseAppointmentToasts {
    announceSaved: (result: AppointmentSaveResult) => void;
    announceDeleted: () => void;
}

/**
 * What the drawer says after a write lands.
 *
 * A status change is the one edit people make by reflex and regret by reflex,
 * so it is the one that comes with a way back: the same body is sent again with
 * the previous status, leaving every other field exactly as it was saved.
 */
export function useAppointmentToasts(): UseAppointmentToasts {
    const { t } = useTrans();

    return {
        announceSaved(result) {
            if (result.appointmentId === null) {
                toastSuccess(t('Appointment created'));

                return;
            }

            if (result.previousStatusId === result.nextStatusId) {
                toastSuccess(t('Appointment saved'));

                return;
            }

            toastUndoable({
                message: t('Status changed'),
                description: result.nextStatusName ?? t('No status'),
                undoLabel: t('Undo'),
                onUndo: () => {
                    router.put(
                        route('appointments.update', result.appointmentId as number),
                        { ...result.body, status_id: result.previousStatusId },
                        { preserveScroll: true },
                    );
                },
            });
        },
        announceDeleted() {
            toastSuccess(t('Appointment deleted'));
        },
    };
}
