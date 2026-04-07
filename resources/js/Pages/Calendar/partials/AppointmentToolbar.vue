<script setup lang="ts">
import { ref, computed } from 'vue';
import { Button } from '@/Components/ui/button';
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
import { getStatusDotStyle } from '@/lib/status-colors';
import { useTrans } from '@/lib/use-trans';
import type { AppointmentKind, Status } from '@/types';
import DateTimePickerPopover from './DateTimePickerPopover.vue';

const { t } = useTrans();

const props = defineProps<{
    clients: { id: number; first_name: string; last_name: string; company_name: string | null; name: string }[];
    employees: { id: number; first_name: string; last_name: string; name: string }[];
    statuses: Status[];
    clientId: string;
    employeeId: string;
    statusId: string;
    kind: string;
    startDate: string;
    startTime: string;
    endDate: string;
    endTime: string;
    isEditing: boolean;
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
    'update:kind': [value: string];
    'update:startDate': [value: string];
    'update:startTime': [value: string];
    'update:endDate': [value: string];
    'update:endTime': [value: string];
    'update:isRecurring': [value: boolean];
    'update:recurrenceType': [value: string];
    'update:recurrenceInterval': [value: number];
    'update:recurrenceEnd': [value: string];
}>();

const clientPopoverOpen = ref(false);
const clientSearch = ref('');
const employeePopoverOpen = ref(false);
const employeeSearch = ref('');
const statusPopoverOpen = ref(false);
const statusSearch = ref('');
const kindPopoverOpen = ref(false);
const recurrencePopoverOpen = ref(false);

const KINDS: { value: AppointmentKind; label: string }[] = [
    { value: 'ohne_termin', label: t('Without appointment') },
    { value: 'kundentermin', label: t('Client appointment') },
];

const selectedKindLabel = computed(() => {
    if (!props.kind || props.kind === 'none') return '';
    return KINDS.find((k) => k.value === props.kind)?.label ?? '';
});

function selectKind(value: string) {
    emit('update:kind', value);
    kindPopoverOpen.value = false;
}

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

const filteredStatuses = computed(() => {
    if (!statusSearch.value) return props.statuses;
    const q = statusSearch.value.toLowerCase();
    return props.statuses.filter((t) => t.name.toLowerCase().includes(q));
});

const selectedClientName = computed(() => {
    if (!props.clientId) return '';
    return props.clients.find((c) => c.id.toString() === props.clientId)?.name ?? '';
});

const selectedEmployeeName = computed(() => {
    if (!props.employeeId || props.employeeId === 'none') return '';
    return props.employees.find((e) => e.id.toString() === props.employeeId)?.name ?? '';
});

const selectedStatus = computed(() => {
    if (!props.statusId || props.statusId === 'none') return null;
    return props.statuses.find((t) => t.id.toString() === props.statusId) ?? null;
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

function selectStatus(id: string) {
    emit('update:statusId', id);
    statusPopoverOpen.value = false;
    statusSearch.value = '';
}

function toggleRecurring() {
    emit('update:isRecurring', !props.isRecurring);
}

function handlePointerDownOutside(e: Event) {
    e.preventDefault();
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-1.5">
        <!-- Client -->
        <Popover v-model:open="clientPopoverOpen">
            <PopoverTrigger as-child>
                <Button type="button" variant="outline" class="rounded-full h-7 px-3 gap-1.5 text-xs font-normal">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    {{ selectedClientName || t('Client') }}
                </Button>
            </PopoverTrigger>
            <PopoverContent class="w-52 p-0" @pointer-down-outside="handlePointerDownOutside">
                <Command>
                    <CommandInput v-model="clientSearch" :placeholder="t('Search clients...')" class="ring-0 focus:ring-0 focus:outline-none border-0 shadow-none" />
                    <CommandList>
                        <CommandEmpty>{{ t('No client found.') }}</CommandEmpty>
                        <CommandGroup class="[&>*]:cursor-pointer [&>*:hover]:bg-accent">
                            <CommandItem value="" @select="selectClient('')">
                                <span class="text-muted-foreground">{{ t('None') }}</span>
                            </CommandItem>
                            <CommandItem
                                v-for="client in filteredClients"
                                :key="client.id"
                                :value="client.name"
                                @select="selectClient(client.id.toString())"
                            >
                                {{ client.name }}
                            </CommandItem>
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>

        <!-- Employee -->
        <Popover v-model:open="employeePopoverOpen">
            <PopoverTrigger as-child>
                <Button type="button" variant="outline" class="rounded-full h-7 px-3 gap-1.5 text-xs font-normal">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    {{ selectedEmployeeName || t('Employee') }}
                </Button>
            </PopoverTrigger>
            <PopoverContent class="w-52 p-0" @pointer-down-outside="handlePointerDownOutside">
                <Command>
                    <CommandInput v-model="employeeSearch" :placeholder="t('Select employee...')" class="ring-0 focus:ring-0 focus:outline-none border-0 shadow-none" />
                    <CommandList>
                        <CommandEmpty>{{ t('No client found.') }}</CommandEmpty>
                        <CommandGroup class="[&>*]:cursor-pointer [&>*:hover]:bg-accent">
                            <CommandItem value="none" @select="selectEmployee('none')">
                                <span class="text-muted-foreground">{{ t('None') }}</span>
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

        <!-- Status -->
        <Popover v-model:open="statusPopoverOpen">
            <PopoverTrigger as-child>
                <Button type="button" variant="outline" class="rounded-full h-7 px-3 gap-1.5 text-xs font-normal">
                    <template v-if="selectedStatus">
                        <span class="h-2.5 w-2.5 rounded-full shrink-0" :style="getStatusDotStyle(selectedStatus.color)" />
                        {{ selectedStatus.name }}
                    </template>
                    <template v-else>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                        {{ t('Status') }}
                    </template>
                </Button>
            </PopoverTrigger>
            <PopoverContent class="w-52 p-0" @pointer-down-outside="handlePointerDownOutside">
                <Command>
                    <CommandInput v-model="statusSearch" :placeholder="t('Select status...')" class="ring-0 focus:ring-0 focus:outline-none border-0 shadow-none" />
                    <CommandList>
                        <CommandEmpty>{{ t('No client found.') }}</CommandEmpty>
                        <CommandGroup class="[&>*]:cursor-pointer [&>*:hover]:bg-accent">
                            <CommandItem value="none" @select="selectStatus('none')">
                                <span class="text-muted-foreground">{{ t('None') }}</span>
                            </CommandItem>
                            <CommandItem
                                v-for="status in filteredStatuses"
                                :key="status.id"
                                :value="status.name"
                                @select="selectStatus(status.id.toString())"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="h-2.5 w-2.5 rounded-full shrink-0" :style="getStatusDotStyle(status.color)" />
                                    {{ status.name }}
                                </div>
                            </CommandItem>
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>

        <!-- Kind -->
        <Popover v-model:open="kindPopoverOpen">
            <PopoverTrigger as-child>
                <Button type="button" variant="outline" class="rounded-full h-7 px-3 gap-1.5 text-xs font-normal">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    {{ selectedKindLabel || t('Kind') }}
                </Button>
            </PopoverTrigger>
            <PopoverContent class="w-52 p-0" @pointer-down-outside="handlePointerDownOutside">
                <Command>
                    <CommandList>
                        <CommandGroup class="[&>*]:cursor-pointer [&>*:hover]:bg-accent">
                            <CommandItem value="none" @select="selectKind('none')">
                                <span class="text-muted-foreground">{{ t('None') }}</span>
                            </CommandItem>
                            <CommandItem
                                v-for="k in KINDS"
                                :key="k.value"
                                :value="k.label"
                                @select="selectKind(k.value)"
                            >
                                {{ k.label }}
                            </CommandItem>
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>

        <!-- DateTime -->
        <DateTimePickerPopover
            :start-date="startDate"
            :start-time="startTime"
            :end-date="endDate"
            :end-time="endTime"
            :errors="errors"
            @update:start-date="(v) => emit('update:startDate', v)"
            @update:start-time="(v) => emit('update:startTime', v)"
            @update:end-date="(v) => emit('update:endDate', v)"
            @update:end-time="(v) => emit('update:endTime', v)"
        />

        <!-- Recurrence -->
        <Popover v-model:open="recurrencePopoverOpen">
            <PopoverTrigger as-child>
                <Button
                    type="button"
                    variant="outline"
                    class="rounded-full h-7 w-7 p-0"
                    :class="isRecurring ? 'bg-orange-50 text-orange-600 border-orange-200 hover:bg-orange-100 hover:text-orange-700' : ''"
                    :title="t('More options')"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                </Button>
            </PopoverTrigger>
            <PopoverContent class="w-72" @pointer-down-outside="handlePointerDownOutside">
                <div class="space-y-3">
                    <div class="flex items-center gap-2 text-sm cursor-pointer" @click="toggleRecurring">
                        <div class="h-4 w-4 shrink-0 rounded-sm border shadow grid place-content-center" :class="isRecurring ? 'bg-primary border-primary text-primary-foreground' : 'border-primary'">
                            <svg v-if="isRecurring" xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                        {{ t('Recurring Appointment') }}
                    </div>

                    <div class="space-y-3 transition-opacity" :class="isRecurring ? '' : 'opacity-40 pointer-events-none'">
                        <div class="space-y-2">
                            <Label>{{ t('Interval') }}</Label>
                            <Select
                                :model-value="recurrenceType"
                                @update:model-value="(val) => emit('update:recurrenceType', String(val))"
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="weekly">{{ t('Weekly') }}</SelectItem>
                                    <SelectItem value="biweekly">{{ t('Biweekly') }}</SelectItem>
                                    <SelectItem value="monthly">{{ t('Monthly') }}</SelectItem>
                                    <SelectItem value="custom">{{ t('Custom') }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div v-if="recurrenceType === 'custom'" class="space-y-2">
                            <Label>{{ t('Every X Weeks') }}</Label>
                            <Input
                                :model-value="recurrenceInterval"
                                type="number"
                                min="1"
                                max="52"
                                @update:model-value="(val: string | number) => emit('update:recurrenceInterval', Number(val))"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label>{{ t('Series End Date') }} *</Label>
                            <DatePicker
                                :model-value="recurrenceEnd"
                                @update:model-value="(val) => emit('update:recurrenceEnd', val)"
                                required
                            />
                        </div>
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    </div>
</template>
