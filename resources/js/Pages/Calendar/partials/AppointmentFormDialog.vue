<script setup lang="ts">
import { ref, watch, computed, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import type { FormDataConvertible } from '@inertiajs/core';
import { Trash2, X } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import {
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/Components/ui/drawer';
import ConfirmDiscardDialog from '@/Components/ConfirmDiscardDialog.vue';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/Components/ui/tabs';
import { useAppointmentForm } from '@/lib/use-appointment-form';
import { useAppointmentToasts } from '@/lib/use-appointment-toasts';
import { useTrans } from '@/lib/use-trans';
import type {
    Appointment,
    ChecklistTemplate,
    ClientOption,
    Contract,
    SeriesScope,
    Status,
} from '@/types';
import AppointmentFormHeader from './AppointmentFormHeader.vue';
import AppointmentFormSections from './AppointmentFormSections.vue';
import CommentsSection from './CommentsSection.vue';
import DocumentsSection from './DocumentsSection.vue';
import AppointmentDeleteDialog from './AppointmentDeleteDialog.vue';
import SeriesScopeChoice from './SeriesScopeChoice.vue';

const { t } = useTrans();

const props = defineProps<{
    contracts: Contract[];
    clients: ClientOption[];
    employees: { id: number; first_name: string; last_name: string; name: string }[];
    statuses: Status[];
    checklistTemplates?: ChecklistTemplate[];
    appointment?: Appointment;
    defaultDate?: string;
    defaultStartTime?: string;
    defaultEndTime?: string;
    defaultWorkerIds?: number[];
}>();

const open = defineModel<boolean>('open', { default: false });
const isEditing = computed(() => !!props.appointment);

const { form, fill, snapshot, payload } = useAppointmentForm(
    () => props.appointment,
    () => ({
        date: props.defaultDate ?? '',
        startTime: props.defaultStartTime ?? '09:00',
        endTime: props.defaultEndTime ?? '10:00',
        workerIds: props.defaultWorkerIds ?? [],
    }),
);

const toasts = useAppointmentToasts();
const sectionsRef = ref<InstanceType<typeof AppointmentFormSections>>();
const confirmDiscardOpen = ref(false);
const confirmDeleteOpen = ref(false);
const initialSnapshot = ref('');
const skipDirtyOnClose = ref(false);
const activeTab = ref<'details' | 'comments' | 'documents'>('details');
/** Editing one occurrence must never silently rewrite its siblings. */
const scope = ref<SeriesScope>('single');

const selectedContract = computed(() =>
    form.contract_id ? props.contracts.find(c => c.id.toString() === form.contract_id) ?? null : null,
);

const documents = computed(() =>
    (props.appointment?.attachments ?? []).filter(a => a.comment_id === null),
);
const commentCount = computed(() => props.appointment?.comments?.length ?? 0);
const documentCount = computed(() => {
    const fromComments = (props.appointment?.comments ?? []).reduce(
        (acc, c) => acc + (c.attachments?.length ?? 0),
        0,
    );

    return documents.value.length + fromComments;
});

const isRecurringSeries = computed(() =>
    !!props.appointment && (!!props.appointment.recurrence_type || !!props.appointment.parent_id),
);

function deleteAppointment(mode: 'single' | 'future' | 'series') {
    if (!props.appointment) return;
    router.delete(route('appointments.destroy', props.appointment.id), {
        data: { delete_series: mode === 'series', delete_future: mode === 'future' },
        preserveScroll: true,
        onSuccess: () => {
            closeSkippingGuard();
            toasts.announceDeleted();
        },
    });
}

/** Close without the discard prompt — the change is already written or waived. */
function closeSkippingGuard() {
    skipDirtyOnClose.value = true;
    open.value = false;
}

function requestClose() {
    if (initialSnapshot.value && snapshot() !== initialSnapshot.value) {
        confirmDiscardOpen.value = true;
    } else {
        closeSkippingGuard();
    }
}

function handleOpenChange(value: boolean) {
    if (value) {
        open.value = true;
    } else if (skipDirtyOnClose.value) {
        skipDirtyOnClose.value = false;
        open.value = false;
    } else {
        requestClose();
    }
}

watch(open, (value) => {
    if (value) {
        activeTab.value = 'details';
        scope.value = 'single';
        fill();
        initialSnapshot.value = snapshot();
        nextTick(() => sectionsRef.value?.autoResize());
    }
    if (!value && initialSnapshot.value) {
        form.clearErrors();
        fill();
        skipDirtyOnClose.value = false;
    }
}, { immediate: true });

function submit() {
    const body = payload(isEditing.value, isRecurringSeries.value ? scope.value : 'single');
    const nextStatusId = (body.status_id as number | null) ?? null;
    const result = {
        appointmentId: isEditing.value ? props.appointment!.id : null,
        body: body as Record<string, FormDataConvertible>,
        previousStatusId: props.appointment?.status?.id ?? null,
        nextStatusId,
        nextStatusName: props.statuses.find(s => s.id === nextStatusId)?.name ?? null,
    };
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            closeSkippingGuard();
            toasts.announceSaved(result);
        },
    };

    if (isEditing.value) {
        form.transform(() => body).put(route('appointments.update', props.appointment!.id), options);
    } else {
        form.transform(() => body).post(route('appointments.store'), options);
    }
}
</script>

<template>
    <Drawer :open="open" direction="right" @update:open="handleOpenChange">
        <DrawerContent
            class="fixed inset-y-0 left-auto right-0 mt-0 flex h-full w-full flex-col rounded-l-[10px] rounded-t-none sm:max-w-[520px]"
        >
            <DrawerHeader class="relative space-y-2 pr-10">
                <DrawerTitle>{{ isEditing ? t('Edit Appointment') : t('New Appointment') }}</DrawerTitle>
                <DrawerDescription class="sr-only">
                    {{ isEditing ? t('Update appointment details.') : t('Create a new appointment.') }}
                </DrawerDescription>
                <AppointmentFormHeader :contract="selectedContract" :is-series="isRecurringSeries" />
                <button
                    type="button"
                    class="absolute right-4 top-4 rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                    @click="requestClose"
                >
                    <X class="h-4 w-4" />
                    <span class="sr-only">{{ t('Close') }}</span>
                </button>
            </DrawerHeader>

            <Tabs v-model="activeTab" class="flex flex-1 flex-col overflow-hidden">
                <div v-if="isEditing" class="border-b px-4 pb-2">
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
                            <AppointmentFormSections
                                ref="sectionsRef"
                                :contracts="contracts"
                                :clients="clients"
                                :employees="employees"
                                :statuses="statuses"
                                :checklist-templates="checklistTemplates ?? []"
                                :is-editing="isEditing"
                                :is-series="isRecurringSeries"
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

                    <DrawerFooter class="flex !flex-row !flex-wrap !items-center !justify-between gap-2 border-t">
                        <Button
                            v-if="isEditing"
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="gap-1.5 text-destructive hover:bg-destructive/10 hover:text-destructive"
                            data-testid="delete-appointment"
                            @click="confirmDeleteOpen = true"
                        >
                            <Trash2 class="h-4 w-4" />
                            {{ t('Delete') }}
                        </Button>
                        <span v-else />

                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <SeriesScopeChoice v-if="isRecurringSeries" v-model="scope" />
                            <Button
                                type="submit"
                                form="appointment-form"
                                :disabled="form.processing || sectionsRef?.hasValidationError"
                            >
                                {{ form.processing ? t('Saving...') : (isEditing ? t('Update') : t('Create appointment')) }}
                            </Button>
                        </div>
                    </DrawerFooter>
                </TabsContent>

                <template v-if="isEditing && appointment">
                    <TabsContent value="comments" class="flex flex-1 flex-col overflow-hidden data-[state=inactive]:hidden">
                        <CommentsSection :appointment-id="appointment.id" :comments="appointment.comments ?? []" />
                    </TabsContent>

                    <TabsContent value="documents" class="flex flex-1 flex-col overflow-hidden data-[state=inactive]:hidden">
                        <DocumentsSection :appointment-id="appointment.id" :documents="documents" :comments="appointment.comments ?? []" />
                    </TabsContent>
                </template>
            </Tabs>
        </DrawerContent>
    </Drawer>

    <ConfirmDiscardDialog v-model:open="confirmDiscardOpen" @discard="closeSkippingGuard" />

    <AppointmentDeleteDialog
        v-model:open="confirmDeleteOpen"
        :is-recurring-series="isRecurringSeries"
        @delete="deleteAppointment"
    />
</template>
