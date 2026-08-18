import { computed, ref, watch, type ComputedRef, type Ref } from 'vue';
import type { Appointment } from '@/types';

export interface AppointmentDialogSource {
    appointments: () => Appointment[];
    /** Where a create form starts when the user did not drag a slot. */
    fallbackDate: () => string;
}

export interface UseAppointmentDialogs {
    createOpen: Ref<boolean>;
    editOpen: Ref<boolean>;
    /** True while either form owns the screen — the shortcuts stand down then. */
    anyOpen: ComputedRef<boolean>;
    selected: ComputedRef<Appointment | undefined>;
    defaults: Ref<{ date: string; startTime: string; endTime: string; workerIds: number[] }>;
    openCreate: (date?: string, startTime?: string, endTime?: string) => void;
    openEdit: (appointment: Appointment) => void;
    /** A dragged or double-clicked slot, optionally already assigned to someone. */
    openCreateForSlot: (
        date: string,
        startTime: string,
        endTime: string,
        employeeId?: number | null,
    ) => void;
}

/** How long a click can still land on whatever the closing drawer covered. */
const CLOSE_GUARD_MS = 300;

/**
 * The calendar's two appointment drawers: which one is open, what it is showing,
 * and what a freshly opened create form starts from.
 */
export function useAppointmentDialogs(source: AppointmentDialogSource): UseAppointmentDialogs {
    const createOpen = ref(false);
    const editOpen = ref(false);
    const selectedId = ref<number | null>(null);
    const defaults = ref({ date: '', startTime: '09:00', endTime: '10:00', workerIds: [] as number[] });

    // Closing the drawer fires a click on whatever is underneath it; without this
    // guard that click immediately reopens the appointment the user just left.
    let closeGuard = false;
    watch(
        editOpen,
        (value) => {
            if (value) return;
            closeGuard = true;
            setTimeout(() => {
                closeGuard = false;
            }, CLOSE_GUARD_MS);
        },
        { flush: 'sync' },
    );

    function openCreate(date?: string, startTime?: string, endTime?: string): void {
        defaults.value = {
            date: date || source.fallbackDate(),
            startTime: startTime || '09:00',
            endTime: endTime || '10:00',
            workerIds: defaults.value.workerIds,
        };
        selectedId.value = null;
        createOpen.value = true;
    }

    return {
        createOpen,
        editOpen,
        anyOpen: computed(() => createOpen.value || editOpen.value),
        selected: computed(() =>
            selectedId.value
                ? source.appointments().find((appointment) => appointment.id === selectedId.value)
                : undefined,
        ),
        defaults,
        openCreate,
        openEdit(appointment) {
            if (closeGuard) return;
            selectedId.value = appointment.id;
            editOpen.value = true;
        },
        openCreateForSlot(date, startTime, endTime, employeeId) {
            defaults.value = { ...defaults.value, workerIds: employeeId ? [employeeId] : [] };
            openCreate(date, startTime, endTime);
        },
    };
}
