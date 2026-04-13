<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue';
import type { DateValue } from '@internationalized/date';
import { CalendarDate } from '@internationalized/date';
import { format, parseISO } from 'date-fns';
import { de } from 'date-fns/locale';
import { Calendar } from '@/Components/ui/calendar';
import { Button } from '@/Components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/Components/ui/popover';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();

const props = withDefaults(defineProps<{
    date: string;
    time: string;
    label?: string;
    hasError?: boolean;
}>(), {
    label: '',
    hasError: false,
});

const emit = defineEmits<{
    'update:date': [value: string];
    'update:time': [value: string];
}>();

const open = ref(false);
const timeListRef = ref<HTMLDivElement>();

const TIME_SLOTS: string[] = [];
for (let h = 0; h < 24; h++) {
    for (let m = 0; m < 60; m += 15) {
        TIME_SLOTS.push(`${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`);
    }
}

const calendarValue = computed<DateValue | undefined>({
    get() {
        if (!props.date) return undefined;
        const [y, m, d] = props.date.split('-').map(Number);
        if (!y || !m || !d) return undefined;
        return new CalendarDate(y, m, d);
    },
    set(val: DateValue | undefined) {
        if (!val) return;
        const year = val.year;
        const month = String(val.month).padStart(2, '0');
        const day = String(val.day).padStart(2, '0');
        emit('update:date', `${year}-${month}-${day}`);
    },
});

const displayText = computed(() => {
    if (!props.date) return props.label || t('Select date & time...');
    try {
        const d = parseISO(props.date);
        const dateStr = format(d, 'EE, d. MMM yyyy', { locale: de });
        return props.time ? `${dateStr}, ${props.time}` : dateStr;
    } catch {
        return props.label || t('Select date & time...');
    }
});

function selectTime(slot: string) {
    emit('update:time', slot);
}

function scrollToSelectedTime() {
    nextTick(() => {
        const container = timeListRef.value;
        if (!container) return;
        const selected = container.querySelector('[data-selected="true"]');
        if (selected) {
            selected.scrollIntoView({ block: 'center' });
        }
    });
}

watch(open, (isOpen) => {
    if (isOpen) {
        scrollToSelectedTime();
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
                    'w-full justify-start h-9 font-normal',
                    !date && 'text-muted-foreground',
                    hasError && 'border-destructive text-destructive',
                ]"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                {{ displayText }}
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-auto p-0" align="start" @pointer-down-outside="handlePointerDownOutside">
            <div class="flex">
                <Calendar
                    v-model="calendarValue"
                    :min-value="new CalendarDate(1925, 1, 1)"
                    :max-value="new CalendarDate(2035, 1, 1)"
                    locale="de"
                />
                <div class="border-l flex flex-col w-[70px]">
                    <div class="px-2 py-2 text-xs font-medium text-muted-foreground border-b text-center">
                        {{ t('Time') }}
                    </div>
                    <div ref="timeListRef" class="flex-1 overflow-y-auto max-h-[280px]">
                        <button
                            v-for="slot in TIME_SLOTS"
                            :key="slot"
                            type="button"
                            :data-selected="time === slot"
                            class="w-full px-2 py-1.5 text-sm text-center hover:bg-accent transition-colors"
                            :class="time === slot ? 'bg-primary text-primary-foreground font-medium hover:bg-primary/90' : ''"
                            @click="selectTime(slot)"
                        >
                            {{ slot }}
                        </button>
                    </div>
                </div>
            </div>
        </PopoverContent>
    </Popover>
</template>
