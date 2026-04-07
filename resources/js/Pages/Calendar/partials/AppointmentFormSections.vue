<script setup lang="ts">
import { ref, computed } from 'vue';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import DatePicker from '@/Components/DatePicker.vue';
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
import { Separator } from '@/Components/ui/separator';
import { getStatusDotStyle } from '@/lib/status-colors';
import { useTrans } from '@/lib/use-trans';
import type { Status } from '@/types';
import AddressSection from './AddressSection.vue';
import ChecklistEditor from './ChecklistEditor.vue';
import RecurrenceSection from './RecurrenceSection.vue';

const { t } = useTrans();

type ClientOption = {
    id: number;
    first_name: string;
    last_name: string;
    company_name: string | null;
    name: string;
    street: string | null;
    zip: string | null;
    city: string | null;
};

const props = defineProps<{
    clients: ClientOption[];
    employees: { id: number; first_name: string; last_name: string; name: string }[];
    statuses: Status[];
    isEditing: boolean;
    clientId: string;
    employeeId: string;
    statusId: string;
    startDate: string;
    startTime: string;
    endDate: string;
    endTime: string;
    notes: string;
    street: string;
    zip: string;
    city: string;
    checklist: { text: string; checked: boolean }[];
    isRecurring: boolean;
    recurrenceType: string;
    recurrenceInterval: number;
    recurrenceEnd: string;
    errors: Record<string, string>;
}>();

const emit = defineEmits<{
    'update:clientId': [value: string];
    'update:employeeId': [value: string];
    'update:statusId': [value: string];
    'update:startDate': [value: string];
    'update:startTime': [value: string];
    'update:endDate': [value: string];
    'update:endTime': [value: string];
    'update:notes': [value: string];
    'update:street': [value: string];
    'update:zip': [value: string];
    'update:city': [value: string];
    'update:checklist': [value: { text: string; checked: boolean }[]];
    'update:isRecurring': [value: boolean];
    'update:recurrenceType': [value: string];
    'update:recurrenceInterval': [value: number];
    'update:recurrenceEnd': [value: string];
}>();

const clientPopoverOpen = ref(false);
const clientSearch = ref('');
const employeePopoverOpen = ref(false);
const employeeSearch = ref('');

const filteredClients = computed(() => {
    if (!clientSearch.value) return props.clients;
    const q = clientSearch.value.toLowerCase();
    return props.clients.filter((c) => c.name.toLowerCase().includes(q));
});

const filteredEmployees = computed(() => {
    if (!employeeSearch.value) return props.employees;
    const q = employeeSearch.value.toLowerCase();
    return props.employees.filter((e) => e.name.toLowerCase().includes(q));
});

const selectedClient = computed(() => {
    if (!props.clientId) return null;
    return props.clients.find((c) => c.id.toString() === props.clientId) ?? null;
});

const selectedEmployeeName = computed(() => {
    if (!props.employeeId || props.employeeId === 'none') return '';
    return props.employees.find((e) => e.id.toString() === props.employeeId)?.name ?? '';
});

function formatAddress(c: { street: string | null; zip: string | null; city: string | null }): string {
    const parts: string[] = [];
    if (c.street) parts.push(c.street);
    if (c.zip || c.city) parts.push([c.zip, c.city].filter(Boolean).join(' '));
    return parts.join(', ');
}

const clientAddress = computed(() => {
    if (!selectedClient.value) return '';
    return formatAddress(selectedClient.value);
});

function selectClient(id: string) {
    emit('update:clientId', id);
    clientPopoverOpen.value = false;
    clientSearch.value = '';
}

function selectEmployee(id: string) {
    emit('update:employeeId', id);
    employeePopoverOpen.value = false;
    employeeSearch.value = '';
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
    if (props.startTime && props.endTime && props.endTime <= props.startTime) {
        return t('End time must be after start time');
    }
    return '';
});

function handleDateChange(date: string) {
    emit('update:startDate', date);
    emit('update:endDate', date);
}

function handleStartTimeChange(time: string) {
    emit('update:startTime', time);
    if (props.endTime && time >= props.endTime) {
        const [h, m] = time.split(':').map(Number);
        const newEnd = `${String(Math.min(h + 1, 23)).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
        emit('update:endTime', newEnd);
    }
}

function handleEndTimeChange(time: string) {
    emit('update:endTime', time);
}

defineExpose({ notesRef, autoResize });
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

        <!-- Client & Address -->
        <div class="space-y-1.5">
            <Label class="text-xs text-muted-foreground">{{ t('Client') }}</Label>
            <Popover v-model:open="clientPopoverOpen">
                <PopoverTrigger as-child>
                    <Button type="button" variant="outline" class="w-full justify-start h-auto py-2 font-normal flex flex-col items-start gap-0">
                        <span>{{ selectedClient?.name || t('Select client...') }}</span>
                        <span v-if="selectedClient && clientAddress" class="text-xs text-muted-foreground">{{ clientAddress }}</span>
                    </Button>
                </PopoverTrigger>
                <PopoverContent class="w-[--reka-popover-trigger-width] p-0" @pointer-down-outside="handlePointerDownOutside">
                    <Command>
                        <CommandInput v-model="clientSearch" :placeholder="t('Search clients...')" class="ring-0 focus:ring-0 focus:outline-none border-0 shadow-none" />
                        <CommandList>
                            <CommandEmpty>{{ t('No client found.') }}</CommandEmpty>
                            <CommandGroup class="[&>*]:cursor-pointer [&>*:hover]:bg-accent">
                                <CommandItem value="" @select="selectClient('')">
                                    <span class="text-muted-foreground">{{ t('No selection') }}</span>
                                </CommandItem>
                                <CommandItem
                                    v-for="client in filteredClients"
                                    :key="client.id"
                                    :value="client.name"
                                    @select="selectClient(client.id.toString())"
                                    class="flex flex-col items-start gap-0"
                                >
                                    <span>{{ client.name }}</span>
                                    <span v-if="formatAddress(client)" class="text-xs text-muted-foreground">{{ formatAddress(client) }}</span>
                                </CommandItem>
                            </CommandGroup>
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
            <AddressSection
                :street="street"
                :zip="zip"
                :city="city"
                :client-address="clientAddress"
                @update:street="(v) => emit('update:street', v)"
                @update:zip="(v) => emit('update:zip', v)"
                @update:city="(v) => emit('update:city', v)"
            />
        </div>

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

        <Separator />

        <!-- Allocation (Employee) -->
        <div class="space-y-1.5">
            <Label class="text-xs text-muted-foreground">{{ t('Allocation') }}</Label>
            <Popover v-model:open="employeePopoverOpen">
                <PopoverTrigger as-child>
                    <Button type="button" variant="outline" class="w-full justify-start h-9 font-normal">
                        {{ selectedEmployeeName || t('Select employee...') }}
                    </Button>
                </PopoverTrigger>
                <PopoverContent class="w-[--reka-popover-trigger-width] p-0" @pointer-down-outside="handlePointerDownOutside">
                    <Command>
                        <CommandInput v-model="employeeSearch" :placeholder="t('Select employee...')" class="ring-0 focus:ring-0 focus:outline-none border-0 shadow-none" />
                        <CommandList>
                            <CommandEmpty>{{ t('No client found.') }}</CommandEmpty>
                            <CommandGroup class="[&>*]:cursor-pointer [&>*:hover]:bg-accent">
                                <CommandItem value="none" @select="selectEmployee('none')">
                                    <span class="text-muted-foreground">{{ t('No selection') }}</span>
                                </CommandItem>
                                <CommandItem
                                    v-for="emp in filteredEmployees"
                                    :key="emp.id"
                                    :value="emp.name"
                                    @select="selectEmployee(emp.id.toString())"
                                >
                                    {{ emp.name }}
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
            <Label class="text-xs text-muted-foreground">{{ t('Date & Time') }}</Label>
            <DatePicker
                :model-value="startDate"
                @update:model-value="handleDateChange"
                :has-error="!!errors.start_at"
                required
            />
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <Label class="text-[11px] text-muted-foreground">{{ t('Start') }}</Label>
                    <Input
                        type="time"
                        :model-value="startTime"
                        @update:model-value="(v: string | number) => handleStartTimeChange(String(v))"
                        class="h-9"
                        required
                    />
                </div>
                <div class="space-y-1">
                    <Label class="text-[11px] text-muted-foreground">{{ t('End') }}</Label>
                    <Input
                        type="time"
                        :model-value="endTime"
                        @update:model-value="(v: string | number) => handleEndTimeChange(String(v))"
                        class="h-9"
                        required
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

    </div>
</template>
