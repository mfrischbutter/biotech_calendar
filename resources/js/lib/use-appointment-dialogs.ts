import { computed, ref, watch, type ComputedRef, type Ref } from 'vue';
import type { Appointment } from '@/types';

interface CreateDefaults {
    date: string;
    startTime: string;
    endTime: string;
    workerIds: number[];
    contractId: string;
    /** Narrows the job picker when a link named a customer but not a job. */
    clientName: string;
}

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
    defaults: Ref<CreateDefaults>;
    openCreate: (date?: string, startTime?: string, endTime?: string) => void;
    /**
     * The "Termin planen" path: a create form that already knows the job, or —
     * when only the customer is known — which customer to narrow the job picker
     * to. Both are cleared again by the next plain `openCreate()`.
     */
    openCreateFor: (prefill: { contractId?: number | null; clientName?: string | null }) => void;
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
    const defaults = ref<CreateDefaults>({
        date: '',
        startTime: '09:00',
        endTime: '10:00',
        workerIds: [],
        contractId: '',
        clientName: '',
    });

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
            // A job the user arrived with belongs to that arrival, not to every
            // form they open afterwards.
            contractId: '',
            clientName: '',
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
        openCreateFor({ contractId, clientName }) {
            openCreate();
            defaults.value.contractId = contractId ? String(contractId) : '';
            defaults.value.clientName = clientName ?? '';
        },
        openEdit(appointment) {
            if (closeGuard) return;
            selectedId.value = appointment.id;
            editOpen.value = true;
        },
        openCreateForSlot(date, startTime, endTime, employeeId) {
            defaults.value.workerIds = employeeId ? [employeeId] : [];
            openCreate(date, startTime, endTime);
        },
    };
}
