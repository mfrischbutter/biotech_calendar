<script setup lang="ts">
import { computed, ref } from 'vue';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Label } from '@/Components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/Components/ui/command';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();

const props = defineProps<{
    employees: { id: number; first_name: string; last_name: string; name: string }[];
    modelValue: number[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: number[]];
}>();

const open = ref(false);
const search = ref('');

const filtered = computed(() => {
    if (!search.value) return props.employees;
    const q = search.value.toLowerCase();

    return props.employees.filter(e => e.name.toLowerCase().includes(q));
});

const selectedNames = computed(() =>
    props.employees.filter(e => props.modelValue.includes(e.id)).map(e => e.name).join(', '),
);

function toggle(workerId: number) {
    emit('update:modelValue', props.modelValue.includes(workerId)
        ? props.modelValue.filter(id => id !== workerId)
        : [...props.modelValue, workerId]);
}

function keepOpen(e: Event) {
    e.preventDefault();
}
</script>

<template>
    <div class="space-y-1.5">
        <Label class="text-xs text-muted-foreground">{{ t('Workers') }}</Label>
        <Popover v-model:open="open">
            <PopoverTrigger as-child>
                <Button type="button" variant="outline" class="h-9 w-full justify-start font-normal">
                    <span v-if="selectedNames" class="truncate">{{ selectedNames }}</span>
                    <span v-else class="text-muted-foreground">{{ t('Select workers...') }}</span>
                </Button>
            </PopoverTrigger>
            <PopoverContent class="w-[--reka-popover-trigger-width] p-0" @pointer-down-outside="keepOpen">
                <Command>
                    <CommandInput
                        v-model="search"
                        :placeholder="t('Select workers...')"
                        class="border-0 shadow-none ring-0 focus:outline-none focus:ring-0"
                    />
                    <CommandList>
                        <CommandEmpty>{{ t('No employee found.') }}</CommandEmpty>
                        <CommandGroup class="[&>*]:cursor-pointer">
                            <CommandItem
                                v-for="emp in filtered"
                                :key="emp.id"
                                :value="emp.name"
                                class="flex items-center gap-2"
                                @select.prevent="toggle(emp.id)"
                            >
                                <Checkbox :model-value="modelValue.includes(emp.id)" class="pointer-events-none" />
                                <span>{{ emp.name }}</span>
                            </CommandItem>
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    </div>
</template>
