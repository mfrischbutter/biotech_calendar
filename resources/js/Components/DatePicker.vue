<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue';
import type { DateValue } from '@internationalized/date';
import { CalendarDate } from '@internationalized/date';
import { format, parseISO, parse, isValid } from 'date-fns';
import { de } from 'date-fns/locale';
import { Calendar } from '@/Components/ui/calendar';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/Components/ui/popover';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();

const props = withDefaults(defineProps<{
    modelValue: string;
    placeholder?: string;
    buttonClass?: string;
    minValue?: DateValue;
    maxValue?: DateValue;
    required?: boolean;
    hasError?: boolean;
}>(), {
    placeholder: '',
    buttonClass: 'h-9 w-full justify-start font-normal',
    required: false,
    hasError: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const open = defineModel<boolean>('open', { default: false });

const inputRef = ref<InstanceType<typeof Input> | null>(null);
const inputValue = ref('');

watch(() => props.modelValue, (val) => {
    if (!val) {
        inputValue.value = '';
        return;
    }
    try {
        inputValue.value = format(parseISO(val), 'dd.MM.yyyy');
    } catch {
        inputValue.value = '';
    }
}, { immediate: true });

watch(open, (isOpen) => {
    if (isOpen) {
        nextTick(() => {
            const el = inputRef.value?.$el as HTMLInputElement | undefined;
            el?.focus();
            el?.select();
        });
    }
});

function applyInputValue() {
    const trimmed = inputValue.value.trim();
    if (!trimmed) return;
    const parsed = parse(trimmed, 'dd.MM.yyyy', new Date());
    if (isValid(parsed)) {
        emit('update:modelValue', format(parsed, 'yyyy-MM-dd'));
    }
}

function handleInputKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter') {
        e.preventDefault();
        applyInputValue();
        open.value = false;
    }
}

const calendarValue = computed<DateValue | undefined>({
    get() {
        if (!props.modelValue) return undefined;
        const [y, m, d] = props.modelValue.split('-').map(Number);
        if (!y || !m || !d) return undefined;
        return new CalendarDate(y, m, d);
    },
    set(val: DateValue | undefined) {
        if (!val) return;
        const year = val.year;
        const month = String(val.month).padStart(2, '0');
        const day = String(val.day).padStart(2, '0');
        emit('update:modelValue', `${year}-${month}-${day}`);
        open.value = false;
    },
});

const displayText = computed(() => {
    if (!props.modelValue) return props.placeholder || t('Select date...');
    try {
        const date = parseISO(props.modelValue);
        return format(date, 'd. MMMM yyyy', { locale: de });
    } catch {
        return props.placeholder || t('Select date...');
    }
});

function handlePointerDownOutside(e: Event) {
    e.preventDefault();
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                type="button"
                variant="outline"
                :class="[
                    buttonClass,
                    !modelValue && 'text-muted-foreground',
                    hasError && 'border-destructive text-destructive',
                ]"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                {{ displayText }}
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-auto p-0" @pointer-down-outside="handlePointerDownOutside">
            <div class="p-3 pb-0">
                <Input
                    ref="inputRef"
                    v-model="inputValue"
                    placeholder="TT.MM.JJJJ"
                    class="h-8 text-sm"
                    @keydown="handleInputKeydown"
                    @blur="applyInputValue"
                />
            </div>
            <Calendar
                v-model="calendarValue"
                :min-value="minValue ?? new CalendarDate(1925, 1, 1)"
                :max-value="maxValue ?? new CalendarDate(2035, 1, 1)"
                locale="de"
            />
        </PopoverContent>
    </Popover>
</template>
