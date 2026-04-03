<script setup lang="ts">
import { ref, watch, computed, nextTick } from 'vue';
import { useForm } from '@inertiajs/vue3';
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
import type { Appointment, ChecklistItem, Tag } from '@/types';
import AppointmentToolbar from './AppointmentToolbar.vue';
import ChecklistEditor from './ChecklistEditor.vue';

const { t } = useTrans();

const props = defineProps<{
    clients: { id: number; name: string }[];
    employees: { id: number; name: string }[];
    tags: Tag[];
    appointment?: Appointment;
    defaultDate?: string;
    defaultStartTime?: string;
    defaultEndTime?: string;
}>();

const open = defineModel<boolean>('open', { default: false });
const isEditing = computed(() => !!props.appointment);

const initStart = props.appointment ? isoToLocalParts(props.appointment.start_at) : null;
const initEnd = props.appointment ? isoToLocalParts(props.appointment.end_at) : null;

const form = useForm({
    title: props.appointment?.title ?? '',
    client_id: props.appointment?.client?.id?.toString() ?? '',
    employee_id: props.appointment?.employee?.id?.toString() ?? 'none',
    start_date: initStart?.date ?? (props.defaultDate ?? ''),
    start_time: initStart?.time ?? (props.defaultStartTime ?? '09:00'),
    end_date: initEnd?.date ?? (props.defaultDate ?? ''),
    end_time: initEnd?.time ?? (props.defaultEndTime ?? '10:00'),
    tag_id: props.appointment?.tag?.id?.toString() ?? 'none',
    notes: props.appointment?.notes ?? '',
    checklist: (props.appointment?.checklist ?? []) as ChecklistItem[],
    is_recurring: false,
    recurrence_type: 'weekly',
    recurrence_interval: 1,
    recurrence_end: '',
});

const notesRef = ref<HTMLTextAreaElement>();
const confirmDiscardOpen = ref(false);
const initialSnapshot = ref('');

function takeSnapshot(): string {
    return JSON.stringify({
        title: form.title,
        client_id: form.client_id,
        employee_id: form.employee_id,
        start_date: form.start_date,
        start_time: form.start_time,
        end_date: form.end_date,
        end_time: form.end_time,
        tag_id: form.tag_id,
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
    e.preventDefault();
    requestClose();
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

watch(open, (value) => {
    if (value) {
        nextTick(() => {
            if (!isEditing.value) {
                form.start_date = props.defaultDate ?? '';
                form.start_time = props.defaultStartTime ?? '09:00';
                form.end_date = props.defaultDate ?? '';
                form.end_time = props.defaultEndTime ?? '10:00';
            }
            nextTick(() => {
                initialSnapshot.value = takeSnapshot();
                autoResize();
            });
        });
    }
    if (!value) {
        form.clearErrors();
        if (isEditing.value) {
            // Reset to original appointment data
            const s = props.appointment ? isoToLocalParts(props.appointment.start_at) : null;
            const e = props.appointment ? isoToLocalParts(props.appointment.end_at) : null;
            form.title = props.appointment?.title ?? '';
            form.client_id = props.appointment?.client?.id?.toString() ?? '';
            form.employee_id = props.appointment?.employee?.id?.toString() ?? 'none';
            form.start_date = s?.date ?? '';
            form.start_time = s?.time ?? '09:00';
            form.end_date = e?.date ?? '';
            form.end_time = e?.time ?? '10:00';
            form.tag_id = props.appointment?.tag?.id?.toString() ?? 'none';
            form.notes = props.appointment?.notes ?? '';
            form.checklist = (props.appointment?.checklist ?? []) as ChecklistItem[];
        } else {
            form.reset();
        }
    }
});

function submit() {
    const payload: Record<string, unknown> = {
        title: form.title,
        client_id: form.client_id ? parseInt(form.client_id) : null,
        employee_id: form.employee_id && form.employee_id !== 'none' ? parseInt(form.employee_id) : null,
        start_at: localToISO(form.start_date, form.start_time),
        end_at: localToISO(form.end_date, form.end_time),
        tag_id: form.tag_id && form.tag_id !== 'none' ? parseInt(form.tag_id) : null,
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
                    :tags="tags"
                    :is-editing="isEditing"
                    v-model:client-id="form.client_id"
                    v-model:employee-id="form.employee_id"
                    v-model:tag-id="form.tag_id"
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

                <DialogFooter>
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
</template>
