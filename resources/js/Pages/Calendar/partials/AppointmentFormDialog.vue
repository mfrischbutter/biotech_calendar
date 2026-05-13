<script setup lang="ts">
import { ref, watch, computed, nextTick } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import {
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/Components/ui/drawer';
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
import { appointmentLabel } from '@/lib/appointment-label';
import { useTrans } from '@/lib/use-trans';
import type { Appointment, ChecklistItem, Contract, Status } from '@/types';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/Components/ui/tabs';
import AppointmentFormSections from './AppointmentFormSections.vue';
import CommentsSection from './CommentsSection.vue';
import DocumentsSection from './DocumentsSection.vue';
import AppointmentDeleteDialog from './AppointmentDeleteDialog.vue';

const { t } = useTrans();

const props = defineProps<{
    contracts: Contract[];
    clients: { id: number; first_name: string; last_name: string; company_name: string | null; name: string }[];
    employees: { id: number; first_name: string; last_name: string; name: string }[];
    statuses: Status[];
    appointment?: Appointment;
    defaultDate?: string;
    defaultStartTime?: string;
    defaultEndTime?: string;
    defaultWorkerIds?: number[];
}>();

const open = defineModel<boolean>('open', { default: false });
const isEditing = computed(() => !!props.appointment);

const initStart = props.appointment ? isoToLocalParts(props.appointment.start_at) : null;
const initEnd = props.appointment ? isoToLocalParts(props.appointment.end_at) : null;

const form = useForm({
    contract_id: props.appointment?.contract?.id?.toString() ?? '',
    worker_ids: (props.appointment?.workers?.map(w => w.id) ?? (props.defaultWorkerIds ?? [])) as number[],
    start_date: initStart?.date ?? (props.defaultDate ?? ''),
    start_time: initStart?.time ?? (props.defaultStartTime ?? '09:00'),
    end_date: initStart?.date ?? (props.defaultDate ?? ''),
    end_time: initEnd?.time ?? (props.defaultEndTime ?? '10:00'),
    status_id: props.appointment?.status?.id?.toString() ?? 'none',
    notes: props.appointment?.notes ?? '',
    checklist: (props.appointment?.checklist ?? []) as ChecklistItem[],
    is_recurring: !!props.appointment?.recurrence_type || !!props.appointment?.parent_id,
    recurrence_type: props.appointment?.recurrence_type ?? 'weekly',
    recurrence_interval: props.appointment?.recurrence_interval ?? 1,
    recurrence_end: props.appointment?.recurrence_end ?? '',
});

const selectedContract = computed(() => {
    if (!form.contract_id) return null;
    return props.contracts.find(c => c.id.toString() === form.contract_id) ?? null;
});

const headerLabel = computed(() => {
    if (selectedContract.value) {
        const c = selectedContract.value;
        return c.title;
    }
    return '';
});

const sectionsRef = ref<InstanceType<typeof AppointmentFormSections>>();
const confirmDiscardOpen = ref(false);
const confirmDeleteOpen = ref(false);
const initialSnapshot = ref('');
const activeTab = ref<'details' | 'comments' | 'documents'>('details');

const documents = computed(() =>
    (props.appointment?.attachments ?? []).filter(a => a.comment_id === null),
);
const commentCount = computed(() => props.appointment?.comments?.length ?? 0);
const documentCount = computed(() => {
    const direct = documents.value.length;
    const fromComments = (props.appointment?.comments ?? []).reduce(
        (acc, c) => acc + (c.attachments?.length ?? 0),
        0,
    );
    return direct + fromComments;
});

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
        contract_id: form.contract_id, worker_ids: form.worker_ids,
        start_date: form.start_date, start_time: form.start_time, end_date: form.end_date,
        end_time: form.end_time, status_id: form.status_id,
        notes: form.notes,
        checklist: form.checklist, is_recurring: form.is_recurring,
        recurrence_type: form.recurrence_type, recurrence_interval: form.recurrence_interval,
        recurrence_end: form.recurrence_end,
    });
}

function isDirty(): boolean {
    return takeSnapshot() !== initialSnapshot.value;
}

function requestClose() {
    if (initialSnapshot.value && isDirty()) {
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
    form.contract_id = props.appointment?.contract?.id?.toString() ?? '';
    form.worker_ids = props.appointment?.workers?.map(w => w.id) ?? [];
    form.start_date = s?.date ?? '';
    form.start_time = s?.time ?? '09:00';
    form.end_date = s?.date ?? '';
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
        activeTab.value = 'details';
        if (isEditing.value) {
            populateFromAppointment();
            initialSnapshot.value = takeSnapshot();
            nextTick(() => sectionsRef.value?.autoResize());
        } else {
            form.start_date = props.defaultDate ?? '';
            form.start_time = props.defaultStartTime ?? '09:00';
            form.end_date = props.defaultDate ?? '';
            form.end_time = props.defaultEndTime ?? '10:00';
            form.worker_ids = props.defaultWorkerIds ?? [];
            nextTick(() => {
                initialSnapshot.value = takeSnapshot();
                sectionsRef.value?.autoResize();
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
        contract_id: form.contract_id ? parseInt(form.contract_id) : null,
        worker_ids: form.worker_ids,
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
    <Drawer :open="open" direction="right" @update:open="handleOpenChange">
        <DrawerContent
            class="fixed inset-y-0 right-0 left-auto mt-0 flex h-full w-full flex-col rounded-l-[10px] rounded-t-none sm:max-w-[520px]"
        >
            <DrawerHeader class="relative">
                <DrawerTitle>{{ isEditing ? t('Edit Appointment') : t('New Appointment') }}</DrawerTitle>
                <DrawerDescription class="sr-only">
                    {{ isEditing ? t('Update appointment details.') : t('Create a new appointment.') }}
                </DrawerDescription>
                <button
                    type="button"
                    class="absolute right-4 top-4 rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                    @click="requestClose"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    <span class="sr-only">Close</span>
                </button>
            </DrawerHeader>

            <div class="flex flex-1 flex-col overflow-hidden">
                <Tabs
                    v-if="isEditing && appointment"
                    v-model="activeTab"
                    class="flex flex-1 flex-col overflow-hidden"
                >
                    <div class="border-b px-4 pb-2">
                        <TabsList class="grid w-full grid-cols-3">
                            <TabsTrigger value="details">{{ t('Details') }}</TabsTrigger>
                            <TabsTrigger value="comments" class="gap-1.5">
                                {{ t('Comments') }}
                                <span
                                    v-if="commentCount"
                                    class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-muted-foreground/15 px-1 text-[10px] font-medium"
                                >{{ commentCount }}</span>
                            </TabsTrigger>
                            <TabsTrigger value="documents" class="gap-1.5">
                                {{ t('Documents') }}
                                <span
                                    v-if="documentCount"
                                    class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-muted-foreground/15 px-1 text-[10px] font-medium"
                                >{{ documentCount }}</span>
                            </TabsTrigger>
                        </TabsList>
                    </div>

                    <TabsContent value="details" class="flex flex-1 flex-col overflow-hidden data-[state=inactive]:hidden">
                        <div class="no-scrollbar flex-1 overflow-y-auto px-4 pb-4 pt-4">
                            <form id="appointment-form" @submit.prevent="submit">
                                <div v-if="headerLabel" class="mb-4">
                                    <p class="text-lg font-medium text-foreground leading-snug">{{ headerLabel }}</p>
                                </div>

                                <AppointmentFormSections
                                    ref="sectionsRef"
                                    :contracts="contracts"
                                    :clients="clients"
                                    :employees="employees"
                                    :statuses="statuses"
                                    :is-editing="isEditing"
                                    v-model:contract-id="form.contract_id"
                                    v-model:worker-ids="form.worker_ids"
                                    v-model:status-id="form.status_id"
                                    v-model:start-date="form.start_date"
                                    v-model:start-time="form.start_time"
                                    v-model:end-date="form.end_date"
                                    v-model:end-time="form.end_time"
                                    v-model:notes="form.notes"
                                    v-model:checklist="form.checklist"
                                    v-model:is-recurring="form.is_recurring"
                                    v-model:recurrence-type="form.recurrence_type"
                                    v-model:recurrence-interval="form.recurrence_interval"
                                    v-model:recurrence-end="form.recurrence_end"
                                    :errors="(form.errors as Record<string, string>)"
                                />
                            </form>
                        </div>

                        <DrawerFooter class="flex !flex-row !justify-between border-t">
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                class="h-8 w-8 rounded-full border-destructive text-destructive hover:bg-destructive/10 hover:text-destructive"
                                data-testid="delete-appointment"
                                @click="confirmDeleteOpen = true"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                            </Button>
                            <Button type="submit" form="appointment-form" :disabled="form.processing || sectionsRef?.hasValidationError">
                                {{ form.processing ? t('Saving...') : t('Update') }}
                            </Button>
                        </DrawerFooter>
                    </TabsContent>

                    <TabsContent value="comments" class="flex flex-1 flex-col overflow-hidden data-[state=inactive]:hidden">
                        <CommentsSection :appointment-id="appointment.id" :comments="appointment.comments ?? []" />
                    </TabsContent>

                    <TabsContent value="documents" class="flex flex-1 flex-col overflow-hidden data-[state=inactive]:hidden">
                        <DocumentsSection :appointment-id="appointment.id" :documents="documents" :comments="appointment.comments ?? []" />
                    </TabsContent>
                </Tabs>

                <template v-else>
                    <div class="no-scrollbar flex-1 overflow-y-auto px-4 pb-4">
                        <form id="appointment-form" @submit.prevent="submit">
                            <div v-if="headerLabel" class="mb-4">
                                <p class="text-lg font-medium text-foreground leading-snug">{{ headerLabel }}</p>
                            </div>

                            <AppointmentFormSections
                                ref="sectionsRef"
                                :contracts="contracts"
                                :clients="clients"
                                :employees="employees"
                                :statuses="statuses"
                                :is-editing="isEditing"
                                v-model:contract-id="form.contract_id"
                                v-model:worker-ids="form.worker_ids"
                                v-model:status-id="form.status_id"
                                v-model:start-date="form.start_date"
                                v-model:start-time="form.start_time"
                                v-model:end-date="form.end_date"
                                v-model:end-time="form.end_time"
                                v-model:notes="form.notes"
                                v-model:checklist="form.checklist"
                                v-model:is-recurring="form.is_recurring"
                                v-model:recurrence-type="form.recurrence_type"
                                v-model:recurrence-interval="form.recurrence_interval"
                                v-model:recurrence-end="form.recurrence_end"
                                :errors="(form.errors as Record<string, string>)"
                            />
                        </form>
                    </div>

                    <DrawerFooter class="flex !flex-row !justify-end border-t">
                        <Button type="submit" form="appointment-form" :disabled="form.processing || sectionsRef?.hasValidationError">
                            {{ form.processing ? t('Saving...') : t('Create appointment') }}
                        </Button>
                    </DrawerFooter>
                </template>
            </div>
        </DrawerContent>
    </Drawer>

    <AlertDialog v-model:open="confirmDiscardOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ t('Discard changes?') }}</AlertDialogTitle>
                <AlertDialogDescription>{{ t('You have unsaved changes. Are you sure you want to discard them?') }}</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>{{ t('Cancel') }}</AlertDialogCancel>
                <AlertDialogAction @click="forceClose">{{ t('Discard') }}</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>

    <AppointmentDeleteDialog
        v-model:open="confirmDeleteOpen"
        :is-recurring-series="isRecurringSeries"
        @delete="deleteAppointment"
    />
</template>
