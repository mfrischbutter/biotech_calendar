<script setup lang="ts">
import { ref, watch, computed, nextTick } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Separator } from '@/Components/ui/separator';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { localToISO, isoToLocalParts } from '@/lib/date-utils';
import { useTrans } from '@/lib/use-trans';
import type { Appointment, ChecklistItem, Status } from '@/types';
import AppointmentToolbar from './AppointmentToolbar.vue';
import ChecklistEditor from './ChecklistEditor.vue';

const { t } = useTrans();

const props = defineProps<{
    clients: { id: number; first_name: string; last_name: string; company_name: string | null; name: string }[];
    employees: { id: number; first_name: string; last_name: string; name: string }[];
    statuses: Status[];
    appointment?: Appointment;
    defaultDate?: string;
    defaultStartTime?: string;
    defaultEndTime?: string;
    defaultEmployeeId?: number | null;
}>();

const open = defineModel<boolean>('open', { default: false });
const isEditing = computed(() => !!props.appointment);

const initStart = props.appointment ? isoToLocalParts(props.appointment.start_at) : null;
const initEnd = props.appointment ? isoToLocalParts(props.appointment.end_at) : null;

const form = useForm({
    title: props.appointment?.title ?? '',
    client_id: props.appointment?.client?.id?.toString() ?? '',
    employee_id: props.appointment?.employee?.id?.toString() ?? (props.defaultEmployeeId ? props.defaultEmployeeId.toString() : 'none'),
    start_date: initStart?.date ?? (props.defaultDate ?? ''),
    start_time: initStart?.time ?? (props.defaultStartTime ?? '09:00'),
    end_date: initEnd?.date ?? (props.defaultDate ?? ''),
    end_time: initEnd?.time ?? (props.defaultEndTime ?? '10:00'),
    status_id: props.appointment?.status?.id?.toString() ?? 'none',
    notes: props.appointment?.notes ?? '',
    checklist: (props.appointment?.checklist ?? []) as ChecklistItem[],
    is_recurring: !!props.appointment?.recurrence_type || !!props.appointment?.parent_id,
    recurrence_type: props.appointment?.recurrence_type ?? 'weekly',
    recurrence_interval: props.appointment?.recurrence_interval ?? 1,
    recurrence_end: props.appointment?.recurrence_end ?? '',
});

const notesRef = ref<HTMLTextAreaElement>();
const confirmDiscardOpen = ref(false);
const confirmDeleteOpen = ref(false);
const initialSnapshot = ref('');

const isRecurringSeries = computed(() =>
    !!props.appointment && (!!props.appointment.recurrence_type || !!props.appointment.parent_id),
);

function deleteAppointment(mode: 'single' | 'future' | 'series') {
    if (!props.appointment) return;
    router.delete(route('appointments.destroy', props.appointment.id), {
        data: {
            delete_series: mode === 'series',
            delete_future: mode === 'future',
        },
        preserveScroll: true,
        onSuccess: () => { open.value = false; },
    });
}

function takeSnapshot(): string {
    return JSON.stringify({
        title: form.title,
        client_id: form.client_id,
        employee_id: form.employee_id,
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

function isDirty(): boolean {
    return takeSnapshot() !== initialSnapshot.value;
}

function autoResize() {
    const el = notesRef.value;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = `${el.scrollHeight}px`;
}

function handlePointerDownOutside(e: Event) {
    if (isDirty()) {
        e.preventDefault();
        confirmDiscardOpen.value = true;
    }
}

function requestClose() {
    if (isDirty()) {
        confirmDiscardOpen.value = true;
    } else {
        forceClose();
    }
}

function forceClose() {
    open.value = false;
}

function handleOpenChange(value: boolean) {
    if (value) {
        open.value = true;
    } else {
        requestClose();
    }
}

function populateFromAppointment() {
    const s = props.appointment ? isoToLocalParts(props.appointment.start_at) : null;
    const e = props.appointment ? isoToLocalParts(props.appointment.end_at) : null;
    form.title = props.appointment?.title ?? '';
    form.client_id = props.appointment?.client?.id?.toString() ?? '';
    form.employee_id = props.appointment?.employee?.id?.toString() ?? 'none';
    form.start_date = s?.date ?? '';
    form.start_time = s?.time ?? '09:00';
    form.end_date = e?.date ?? '';
    form.end_time = e?.time ?? '10:00';
    form.status_id = props.appointment?.status?.id?.toString() ?? 'none';
    form.notes = props.appointment?.notes ?? '';
    form.checklist = (props.appointment?.checklist ?? []) as ChecklistItem[];
    form.is_recurring = !!props.appointment?.recurrence_type || !!props.appointment?.parent_id;
    form.recurrence_type = props.appointment?.recurrence_type ?? 'weekly';
    form.recurrence_interval = props.appointment?.recurrence_interval ?? 1;
    form.recurrence_end = props.appointment?.recurrence_end ?? '';
}

watch(open, (value) => {
    if (value) {
        if (isEditing.value) {
            // Re-populate from current appointment (may have changed via v-if remount or prop change)
            populateFromAppointment();
            initialSnapshot.value = takeSnapshot();
            nextTick(() => autoResize());
        } else {
            form.start_date = props.defaultDate ?? '';
            form.start_time = props.defaultStartTime ?? '09:00';
            form.end_date = props.defaultDate ?? '';
            form.end_time = props.defaultEndTime ?? '10:00';
            form.employee_id = props.defaultEmployeeId ? props.defaultEmployeeId.toString() : 'none';
            nextTick(() => {
                initialSnapshot.value = takeSnapshot();
                autoResize();
            });
        }
    }
    if (!value && initialSnapshot.value) {
        form.clearErrors();
        if (isEditing.value) {
            populateFromAppointment();
        } else {
            form.reset();
        }
    }
}, { immediate: true });

function submit() {
    const payload: Record<string, unknown> = {
        title: form.title,
        client_id: form.client_id ? parseInt(form.client_id) : null,
        employee_id: form.employee_id && form.employee_id !== 'none' ? parseInt(form.employee_id) : null,
        start_at: localToISO(form.start_date, form.start_time),
        end_at: localToISO(form.end_date, form.end_time),
        status_id: form.status_id && form.status_id !== 'none' ? parseInt(form.status_id) : null,
        notes: form.notes || null,
        checklist: form.checklist.length > 0 ? form.checklist : null,
    };

    if (form.is_recurring && !isEditing.value) {
        payload.recurrence_type = form.recurrence_type;
        payload.recurrence_interval = form.recurrence_type === 'custom' ? form.recurrence_interval : null;
        payload.recurrence_end = form.recurrence_end;
    }

    if (isEditing.value) {
        form.transform(() => payload).put(route('appointments.update', props.appointment!.id), {
            preserveScroll: true,
            onSuccess: () => { open.value = false; },
        });
    } else {
        form.transform(() => payload).post(route('appointments.store'), {
            preserveScroll: true,
            onSuccess: () => { open.value = false; },
        });
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent
            class="sm:max-w-[624px] pt-4"
            @pointer-down-outside="handlePointerDownOutside"
            @escape-key-down.prevent="requestClose"
        >
            <DialogHeader>
                <DialogTitle>{{ isEditing ? t('Edit Appointment') : t('New Appointment') }}</DialogTitle>
                <DialogDescription class="sr-only">
                    {{ isEditing ? t('Update appointment details.') : t('Create a new appointment.') }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-3 max-h-[70vh] overflow-y-auto pr-1">
                <input
                    v-model="form.title"
                    type="text"
                    :placeholder="t('Appointment title')"
                    required
                    class="w-full bg-transparent text-lg font-medium border-0 outline-none ring-0 focus:outline-none focus:ring-0 p-0 text-foreground placeholder:text-muted-foreground"
                />
                <p v-if="form.errors.title" class="text-xs text-destructive">{{ form.errors.title }}</p>

                <textarea
                    ref="notesRef"
                    v-model="form.notes"
                    @input="autoResize"
                    :placeholder="t('Add a description...')"
                    rows="3"
                    class="w-full bg-transparent text-sm border-0 outline-none ring-0 focus:outline-none focus:ring-0 p-0 text-foreground placeholder:text-muted-foreground resize-none overflow-hidden"
                />

                <ChecklistEditor v-model="form.checklist" />

                <Separator />

                <AppointmentToolbar
                    :clients="clients"
                    :employees="employees"
                    :statuses="statuses"
                    :is-editing="isEditing"
                    v-model:client-id="form.client_id"
                    v-model:employee-id="form.employee_id"
                    v-model:status-id="form.status_id"
                    v-model:start-date="form.start_date"
                    v-model:start-time="form.start_time"
                    v-model:end-date="form.end_date"
                    v-model:end-time="form.end_time"
                    v-model:is-recurring="form.is_recurring"
                    v-model:recurrence-type="form.recurrence_type"
                    v-model:recurrence-interval="form.recurrence_interval"
                    v-model:recurrence-end="form.recurrence_end"
                    :errors="(form.errors as Record<string, string>)"
                />

                <DialogFooter class="flex !justify-between">
                    <Button
                        v-if="isEditing"
                        type="button"
                        variant="outline"
                        size="icon"
                        class="h-8 w-8 rounded-full border-destructive text-destructive hover:bg-destructive/10 hover:text-destructive"
                        data-testid="delete-appointment"
                        @click="confirmDeleteOpen = true"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                    </Button>
                    <div v-else />
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? t('Saving...') : (isEditing ? t('Update') : t('Create appointment')) }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <AlertDialog v-model:open="confirmDiscardOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ t('Discard changes?') }}</AlertDialogTitle>
                <AlertDialogDescription>
                    {{ t('You have unsaved changes. Are you sure you want to discard them?') }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>{{ t('Cancel') }}</AlertDialogCancel>
                <AlertDialogAction @click="forceClose">{{ t('Discard') }}</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>

    <AlertDialog v-model:open="confirmDeleteOpen">
        <AlertDialogContent @pointer-down-outside="confirmDeleteOpen = false">
            <AlertDialogHeader>
                <AlertDialogTitle>{{ t('Delete Appointment') }}?</AlertDialogTitle>
                <AlertDialogDescription>
                    {{ isRecurringSeries
                        ? t('This appointment is part of a series. How would you like to proceed?')
                        : t('This appointment will be permanently deleted. This action cannot be undone.')
                    }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter class="flex-col sm:flex-col gap-2">
                <div v-if="isRecurringSeries" class="flex flex-col gap-2 w-full">
                    <AlertDialogAction
                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        @click="deleteAppointment('single')"
                    >
                        {{ t('Delete This Only') }}
                    </AlertDialogAction>
                    <AlertDialogAction
                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        @click="deleteAppointment('future')"
                    >
                        {{ t('Delete All Future Appointments') }}
                    </AlertDialogAction>
                    <AlertDialogAction
                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        @click="deleteAppointment('series')"
                    >
                        {{ t('Delete Entire Series') }}
                    </AlertDialogAction>
                </div>
                <AlertDialogAction
                    v-else
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90 w-full"
                    @click="deleteAppointment('single')"
                >
                    {{ t('Delete') }}
                </AlertDialogAction>
                <AlertDialogCancel class="w-full">{{ t('Cancel') }}</AlertDialogCancel>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
