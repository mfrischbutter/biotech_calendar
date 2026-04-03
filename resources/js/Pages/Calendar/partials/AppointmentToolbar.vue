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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { getTagDotStyle } from '@/lib/tag-colors';
import { useTrans } from '@/lib/use-trans';
import type { Tag } from '@/types';
import DateTimePickerPopover from './DateTimePickerPopover.vue';

const { t } = useTrans();

const props = defineProps<{
    clients: { id: number; name: string }[];
    employees: { id: number; name: string }[];
    tags: Tag[];
    clientId: string;
    employeeId: string;
    tagId: string;
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
    'update:tagId': [value: string];
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
const tagPopoverOpen = ref(false);
const tagSearch = ref('');
const recurrencePopoverOpen = ref(false);

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

const filteredTags = computed(() => {
    if (!tagSearch.value) return props.tags;
    const q = tagSearch.value.toLowerCase();
    return props.tags.filter((t) => t.name.toLowerCase().includes(q));
});

const selectedClientName = computed(() => {
    if (!props.clientId) return '';
    return props.clients.find((c) => c.id.toString() === props.clientId)?.name ?? '';
});

const selectedEmployeeName = computed(() => {
    if (!props.employeeId || props.employeeId === 'none') return '';
    return props.employees.find((e) => e.id.toString() === props.employeeId)?.name ?? '';
});

const selectedTag = computed(() => {
    if (!props.tagId || props.tagId === 'none') return null;
    return props.tags.find((t) => t.id.toString() === props.tagId) ?? null;
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

function selectTag(id: string) {
    emit('update:tagId', id);
    tagPopoverOpen.value = false;
    tagSearch.value = '';
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

        <!-- Tag -->
        <Popover v-model:open="tagPopoverOpen">
            <PopoverTrigger as-child>
                <Button type="button" variant="outline" class="rounded-full h-7 px-3 gap-1.5 text-xs font-normal">
                    <template v-if="selectedTag">
                        <span class="h-2.5 w-2.5 rounded-full shrink-0" :style="getTagDotStyle(selectedTag.color)" />
                        {{ selectedTag.name }}
                    </template>
                    <template v-else>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                        {{ t('Tag') }}
                    </template>
                </Button>
            </PopoverTrigger>
            <PopoverContent class="w-52 p-0" @pointer-down-outside="handlePointerDownOutside">
                <Command>
                    <CommandInput v-model="tagSearch" :placeholder="t('Select tag...')" class="ring-0 focus:ring-0 focus:outline-none border-0 shadow-none" />
                    <CommandList>
                        <CommandEmpty>{{ t('No client found.') }}</CommandEmpty>
                        <CommandGroup class="[&>*]:cursor-pointer [&>*:hover]:bg-accent">
                            <CommandItem value="none" @select="selectTag('none')">
                                <span class="text-muted-foreground">{{ t('None') }}</span>
                            </CommandItem>
                            <CommandItem
                                v-for="tag in filteredTags"
                                :key="tag.id"
                                :value="tag.name"
                                @select="selectTag(tag.id.toString())"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="h-2.5 w-2.5 rounded-full shrink-0" :style="getTagDotStyle(tag.color)" />
                                    {{ tag.name }}
                                </div>
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

        <!-- Recurrence (create only) -->
        <Popover v-if="!isEditing" v-model:open="recurrencePopoverOpen">
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
                            <Input
                                :model-value="recurrenceEnd"
                                type="date"
                                required
                                @update:model-value="(val: string | number) => emit('update:recurrenceEnd', String(val))"
                            />
                        </div>
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    </div>
</template>
