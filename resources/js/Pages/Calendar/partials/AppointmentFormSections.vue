<script setup lang="ts">
import { ref, computed, nextTick } from 'vue';
import { Label } from '@/Components/ui/label';
import DateTimePicker from '@/Components/DateTimePicker.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/Components/ui/popover';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/Components/ui/command';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Separator } from '@/Components/ui/separator';
import { getStatusDotStyle } from '@/lib/status-colors';
import { useTrans } from '@/lib/use-trans';
import type { Contract, Status } from '@/types';
import ChecklistEditor from './ChecklistEditor.vue';
import RecurrenceSection from './RecurrenceSection.vue';
import ContractFormDrawer from '@/Pages/Contracts/partials/ContractFormDrawer.vue';

const { t } = useTrans();

type ClientOption = { id: number; first_name: string; last_name: string; company_name: string | null; name: string };

const props = defineProps<{
    contracts: Contract[];
    clients: ClientOption[];
    employees: { id: number; first_name: string; last_name: string; name: string }[];
    statuses: Status[];
    isEditing: boolean;
    contractId: string;
    workerIds: number[];
    statusId: string;
    startDate: string;
    startTime: string;
    endDate: string;
    endTime: string;
    notes: string;
    checklist: { text: string; checked: boolean }[];
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
    'update:checklist': [value: { text: string; checked: boolean }[]];
    'update:isRecurring': [value: boolean];
    'update:recurrenceType': [value: string];
    'update:recurrenceInterval': [value: number];
    'update:recurrenceEnd': [value: string];
}>();

const contractPopoverOpen = ref(false);
const contractSearch = ref('');
const createContractOpen = ref(false);
const workerPopoverOpen = ref(false);
const workerSearch = ref('');

const filteredContracts = computed(() => {
    if (!contractSearch.value) return props.contracts;
    const q = contractSearch.value.toLowerCase();
    return props.contracts.filter((c) =>
        c.title.toLowerCase().includes(q) ||
        c.contract_number.toLowerCase().includes(q) ||
        c.clients.some(cl => cl.name.toLowerCase().includes(q)),
    );
});

const filteredEmployees = computed(() => {
    if (!workerSearch.value) return props.employees;
    const q = workerSearch.value.toLowerCase();
    return props.employees.filter((e) => e.name.toLowerCase().includes(q));
});

const selectedContract = computed(() => {
    if (!props.contractId) return null;
    return props.contracts.find((c) => c.id.toString() === props.contractId) ?? null;
});

const selectedWorkerNames = computed(() => {
    return props.employees
        .filter(e => props.workerIds.includes(e.id))
        .map(e => e.name)
        .join(', ');
});

function formatAddress(c: { street: string | null; zip: string | null; city: string | null }): string {
    const parts: string[] = [];
    if (c.street) parts.push(c.street);
    if (c.zip || c.city) parts.push([c.zip, c.city].filter(Boolean).join(' '));
    return parts.join(', ');
}

function selectContract(id: string) {
    emit('update:contractId', id);
    contractPopoverOpen.value = false;
    contractSearch.value = '';
}

function toggleWorker(workerId: number) {
    if (props.workerIds.includes(workerId)) {
        emit('update:workerIds', props.workerIds.filter(id => id !== workerId));
    } else {
        emit('update:workerIds', [...props.workerIds, workerId]);
    }
}

function openCreateContract() {
    contractPopoverOpen.value = false;
    contractSearch.value = '';
    createContractOpen.value = true;
}

function onContractCreated(contractNumber: string) {
    nextTick(() => {
        const created = props.contracts.find(c => c.contract_number === contractNumber);
        if (created) {
            emit('update:contractId', created.id.toString());
        }
    });
}

function handlePointerDownOutside(e: Event) {
    e.preventDefault();
}

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

function handleEndTimeChange(time: string) {
    emit('update:endTime', time);
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
                            class="h-2.5 w-2.5 rounded-full shrink-0"
                            :style="getStatusDotStyle(statuses.find(s => s.id.toString() === statusId)?.color ?? '')"
                        />
                        <SelectValue :placeholder="t('Select...')" />
                    </div>
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="none">{{ t('No selection') }}</SelectItem>
                    <SelectItem v-for="status in statuses" :key="status.id" :value="status.id.toString()">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full shrink-0" :style="getStatusDotStyle(status.color)" />
                            {{ status.name }}
                        </div>
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <Separator />

        <!-- Contract selector -->
        <div class="space-y-1.5">
            <Label class="text-xs text-muted-foreground">{{ t('Contract') }} *</Label>
            <div class="flex gap-1.5">
                <Popover v-model:open="contractPopoverOpen">
                    <PopoverTrigger as-child>
                        <Button type="button" variant="outline" class="flex-1 justify-start h-auto py-2 font-normal flex flex-col items-start gap-0">
                            <span v-if="selectedContract">
                                <span class="font-mono text-xs text-muted-foreground mr-1.5">{{ selectedContract.contract_number }}</span>
                                {{ selectedContract.title }}
                            </span>
                            <span v-else class="text-muted-foreground">{{ t('Select contract...') }}</span>
                            <template v-if="selectedContract">
                                <span v-if="selectedContract.clients.length > 0" class="text-xs text-muted-foreground">
                                    {{ selectedContract.clients.map(c => c.name).join(', ') }}
                                </span>
                                <span v-if="formatAddress(selectedContract)" class="text-xs text-muted-foreground">
                                    {{ formatAddress(selectedContract) }}
                                </span>
                            </template>
                        </Button>
                    </PopoverTrigger>
                <PopoverContent class="w-[--reka-popover-trigger-width] p-0" @pointer-down-outside="handlePointerDownOutside">
                    <Command>
                        <CommandInput v-model="contractSearch" :placeholder="t('Search contracts...')" class="ring-0 focus:ring-0 focus:outline-none border-0 shadow-none" />
                        <CommandList>
                            <CommandEmpty>{{ t('No contract found.') }}</CommandEmpty>
                            <CommandGroup class="[&>*]:cursor-pointer [&>*:hover]:bg-accent">
                                <CommandItem
                                    v-for="contract in filteredContracts"
                                    :key="contract.id"
                                    :value="`${contract.contract_number} ${contract.title}`"
                                    @select="selectContract(contract.id.toString())"
                                    class="flex flex-col items-start gap-0"
                                >
                                    <span>
                                        <span class="font-mono text-xs text-muted-foreground mr-1.5">{{ contract.contract_number }}</span>
                                        {{ contract.title }}
                                    </span>
                                    <span v-if="contract.clients.length > 0" class="text-xs text-muted-foreground">
                                        {{ contract.clients.map(c => c.name).join(', ') }}
                                    </span>
                                    <span v-if="formatAddress(contract)" class="text-xs text-muted-foreground">
                                        {{ formatAddress(contract) }}
                                    </span>
                                </CommandItem>
                            </CommandGroup>
                        </CommandList>
                    </Command>
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 border-t px-3 py-2 text-sm text-primary hover:bg-accent transition-colors cursor-pointer"
                        @click="openCreateContract"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        {{ t('New Contract') }}
                    </button>
                </PopoverContent>
                </Popover>
                <ContractFormDrawer v-if="selectedContract" :contract="selectedContract" :clients="clients">
                    <Button type="button" variant="outline" size="icon" class="h-9 w-9 shrink-0 self-start">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </Button>
                </ContractFormDrawer>
            </div>
            <p v-if="errors.contract_id" class="text-xs text-destructive">{{ errors.contract_id }}</p>
        </div>

        <Separator />

        <!-- Workers (multi-select) -->
        <div class="space-y-1.5">
            <Label class="text-xs text-muted-foreground">{{ t('Workers') }}</Label>
            <Popover v-model:open="workerPopoverOpen">
                <PopoverTrigger as-child>
                    <Button type="button" variant="outline" class="w-full justify-start h-9 font-normal">
                        <span v-if="selectedWorkerNames" class="truncate">{{ selectedWorkerNames }}</span>
                        <span v-else class="text-muted-foreground">{{ t('Select workers...') }}</span>
                    </Button>
                </PopoverTrigger>
                <PopoverContent class="w-[--reka-popover-trigger-width] p-0" @pointer-down-outside="handlePointerDownOutside">
                    <Command>
                        <CommandInput v-model="workerSearch" :placeholder="t('Select workers...')" class="ring-0 focus:ring-0 focus:outline-none border-0 shadow-none" />
                        <CommandList>
                            <CommandEmpty>{{ t('No client found.') }}</CommandEmpty>
                            <CommandGroup class="[&>*]:cursor-pointer">
                                <CommandItem
                                    v-for="emp in filteredEmployees"
                                    :key="emp.id"
                                    :value="emp.name"
                                    @select.prevent="toggleWorker(emp.id)"
                                    class="flex items-center gap-2"
                                >
                                    <Checkbox :model-value="workerIds.includes(emp.id)" class="pointer-events-none" />
                                    <span>{{ emp.name }}</span>
                                </CommandItem>
                            </CommandGroup>
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
        </div>

        <Separator />

        <!-- Date & Time -->
        <div class="space-y-1.5">
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
                        @update:time="handleEndTimeChange"
                    />
                </div>
            </div>
            <p v-if="timeError" class="text-xs text-destructive">{{ timeError }}</p>
            <p v-if="errors.start_at" class="text-xs text-destructive">{{ errors.start_at }}</p>
            <p v-if="errors.end_at" class="text-xs text-destructive">{{ errors.end_at }}</p>
        </div>

        <!-- Recurrence (only for new appointments) -->
        <template v-if="!isEditing">
            <Separator />
            <RecurrenceSection
                :is-recurring="isRecurring"
                :recurrence-type="recurrenceType"
                :recurrence-interval="recurrenceInterval"
                :recurrence-end="recurrenceEnd"
                @update:is-recurring="(val) => emit('update:isRecurring', val)"
                @update:recurrence-type="(val) => emit('update:recurrenceType', val)"
                @update:recurrence-interval="(val) => emit('update:recurrenceInterval', val)"
                @update:recurrence-end="(val) => emit('update:recurrenceEnd', val)"
            />
        </template>

        <Separator />

        <!-- Description -->
        <div class="space-y-1.5">
            <Label class="text-xs text-muted-foreground">{{ t('Description') }}</Label>
            <textarea
                ref="notesRef"
                :value="notes"
                @input="(e) => { emit('update:notes', (e.target as HTMLTextAreaElement).value); autoResize(); }"
                :placeholder="t('Add a description...')"
                rows="3"
                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 resize-none overflow-hidden"
            />
        </div>

        <!-- Checklist -->
        <ChecklistEditor :model-value="checklist" @update:model-value="(val) => emit('update:checklist', val)" />

        <ContractFormDrawer v-model:open="createContractOpen" :clients="clients" @created="onContractCreated" />
    </div>
</template>
