<script setup lang="ts">
import { ref, computed } from 'vue';
import { Label } from '@/Components/ui/label';
import DateTimePicker from '@/Components/DateTimePicker.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Separator } from '@/Components/ui/separator';
import FormSectionHeader from '@/Components/FormSectionHeader.vue';
import { getStatusDotStyle } from '@/lib/status-colors';
import { checklistProgress } from '@/lib/checklist-templates';
import { useTrans } from '@/lib/use-trans';
import type { ChecklistItem, ChecklistTemplate, ClientOption, Contract, Status } from '@/types';
import ChecklistEditor from './ChecklistEditor.vue';
import ChecklistTemplatePicker from './ChecklistTemplatePicker.vue';
import ContractPicker from './ContractPicker.vue';
import RecurrenceChip from './RecurrenceChip.vue';
import WorkerPicker from './WorkerPicker.vue';

const { t } = useTrans();

const props = defineProps<{
    contracts: Contract[];
    clients: ClientOption[];
    employees: { id: number; first_name: string; last_name: string; name: string }[];
    statuses: Status[];
    checklistTemplates: ChecklistTemplate[];
    isEditing: boolean;
    /** The appointment being edited already belongs to a recurring series. */
    isSeries: boolean;
    contractId: string;
    workerIds: number[];
    statusId: string;
    startDate: string;
    startTime: string;
    endDate: string;
    endTime: string;
    notes: string;
    checklist: ChecklistItem[];
    isRecurring: boolean;
    recurrenceType: string;
    recurrenceInterval: number;
    recurrenceEnd: string;
    errors: Record<string, string>;
}>();

const emit = defineEmits<{
    'update:contractId': [value: string];
    'update:workerIds': [value: number[]];
    'update:statusId': [value: string];
    'update:startDate': [value: string];
    'update:startTime': [value: string];
    'update:endDate': [value: string];
    'update:endTime': [value: string];
    'update:notes': [value: string];
    'update:checklist': [value: ChecklistItem[]];
    'update:isRecurring': [value: boolean];
    'update:recurrenceType': [value: string];
    'update:recurrenceInterval': [value: number];
    'update:recurrenceEnd': [value: string];
}>();

const selectedContract = computed(() =>
    props.contractId ? props.contracts.find(c => c.id.toString() === props.contractId) ?? null : null,
);

const progress = computed(() => checklistProgress(props.checklist));
const checklistCount = computed(() =>
    progress.value.total > 0 ? `${progress.value.done}/${progress.value.total}` : null,
);

const notesRef = ref<HTMLTextAreaElement>();

function autoResize() {
    const el = notesRef.value;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = `${el.scrollHeight}px`;
}

const timeError = computed(() => {
    if (props.startDate && props.endDate && props.startDate === props.endDate
        && props.startTime && props.endTime && props.endTime <= props.startTime) {
        return t('End time must be after start time');
    }
    if (props.startDate && props.endDate && props.endDate < props.startDate) {
        return t('End date must be on or after start date');
    }

    return '';
});

const hasValidationError = computed(() => !!timeError.value);

function handleStartDateChange(date: string) {
    emit('update:startDate', date);
    if (!props.endDate || props.endDate < date) {
        emit('update:endDate', date);
    }
}

function handleStartTimeChange(time: string) {
    emit('update:startTime', time);
    const [h, m] = time.split(':').map(Number);
    const newEnd = `${String(Math.min(h + 1, 23)).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
    if (!props.endTime || props.endTime <= time) {
        emit('update:endTime', newEnd);
    }
}

function applyTemplate(items: ChecklistItem[]) {
    emit('update:checklist', [...props.checklist, ...items]);
}

defineExpose({ notesRef, autoResize, hasValidationError });
</script>

<template>
    <div class="space-y-4">
        <!-- Status -->
        <div class="space-y-1.5">
            <Label class="text-xs text-muted-foreground">{{ t('Status') }}</Label>
            <Select
                :model-value="statusId"
                @update:model-value="(val) => emit('update:statusId', String(val))"
            >
                <SelectTrigger class="h-9">
                    <div class="flex items-center gap-2">
                        <span
                            v-if="statusId && statusId !== 'none'"
                            class="h-2.5 w-2.5 shrink-0 rounded-full"
                            :style="getStatusDotStyle(statuses.find(s => s.id.toString() === statusId)?.color ?? '')"
                        />
                        <SelectValue :placeholder="t('Select...')" />
                    </div>
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="none">{{ t('No selection') }}</SelectItem>
                    <SelectItem v-for="status in statuses" :key="status.id" :value="status.id.toString()">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="getStatusDotStyle(status.color)" />
                            {{ status.name }}
                        </div>
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <Separator />

        <ContractPicker
            :contracts="contracts"
            :clients="clients"
            :model-value="contractId"
            :error="errors.contract_id"
            @update:model-value="(val) => emit('update:contractId', val)"
        />

        <Separator />

        <WorkerPicker
            :employees="employees"
            :model-value="workerIds"
            @update:model-value="(val) => emit('update:workerIds', val)"
        />

        <Separator />

        <!-- Date & Time -->
        <div class="space-y-2">
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <Label class="text-[11px] text-muted-foreground">{{ t('Start') }}</Label>
                    <DateTimePicker
                        :date="startDate"
                        :time="startTime"
                        :has-error="!!errors.start_at"
                        @update:date="handleStartDateChange"
                        @update:time="handleStartTimeChange"
                    />
                </div>
                <div class="space-y-1">
                    <Label class="text-[11px] text-muted-foreground">{{ t('End') }}</Label>
                    <DateTimePicker
                        :date="endDate"
                        :time="endTime"
                        :has-error="!!errors.end_at"
                        @update:date="(v) => emit('update:endDate', v)"
                        @update:time="(v) => emit('update:endTime', v)"
                    />
                </div>
            </div>

            <!-- The repeat rule is a chip that states itself, never a "⋯" menu. -->
            <RecurrenceChip
                :is-recurring="isEditing ? isSeries : isRecurring"
                :recurrence-type="recurrenceType"
                :recurrence-interval="recurrenceInterval"
                :recurrence-end="recurrenceEnd"
                :readonly="isEditing"
                @update:is-recurring="(val) => emit('update:isRecurring', val)"
                @update:recurrence-type="(val) => emit('update:recurrenceType', val)"
                @update:recurrence-interval="(val) => emit('update:recurrenceInterval', val)"
                @update:recurrence-end="(val) => emit('update:recurrenceEnd', val)"
            />

            <p v-if="timeError" class="text-xs text-destructive">{{ timeError }}</p>
            <p v-if="errors.start_at" class="text-xs text-destructive">{{ errors.start_at }}</p>
            <p v-if="errors.end_at" class="text-xs text-destructive">{{ errors.end_at }}</p>
        </div>

        <Separator />

        <!-- Notes -->
        <div class="space-y-1.5">
            <FormSectionHeader :label="t('Notes')" />
            <textarea
                ref="notesRef"
                :value="notes"
                :placeholder="t('Add a description...')"
                rows="3"
                class="w-full resize-none overflow-hidden rounded-md border border-input bg-transparent px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                @input="(e) => { emit('update:notes', (e.target as HTMLTextAreaElement).value); autoResize(); }"
            />
        </div>

        <Separator />

        <!-- On-site checklist -->
        <div class="space-y-2">
            <FormSectionHeader :label="t('On site')" :count="checklistCount">
                <template #action>
                    <ChecklistTemplatePicker
                        :templates="checklistTemplates"
                        :kind="selectedContract?.kind ?? null"
                        @apply="applyTemplate"
                    />
                </template>
            </FormSectionHeader>
            <ChecklistEditor :model-value="checklist" @update:model-value="(val) => emit('update:checklist', val)" />
        </div>
    </div>
</template>
