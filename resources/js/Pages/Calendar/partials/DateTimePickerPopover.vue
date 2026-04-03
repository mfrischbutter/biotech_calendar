<script setup lang="ts">
import { computed } from 'vue';
import { format, parseISO } from 'date-fns';
import { de } from 'date-fns/locale';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/Components/ui/popover';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();

const props = defineProps<{
    startDate: string;
    startTime: string;
    endDate: string;
    endTime: string;
    errors: Record<string, string>;
}>();

const emit = defineEmits<{
    'update:startDate': [value: string];
    'update:startTime': [value: string];
    'update:endDate': [value: string];
    'update:endTime': [value: string];
}>();

const displayText = computed(() => {
    if (!props.startDate || !props.startTime) return t('Date & Time');
    try {
        const start = parseISO(props.startDate);
        const formatted = format(start, 'EE, d. MMM', { locale: de });
        const sameDay = props.startDate === props.endDate;
        if (sameDay) {
            return `${formatted} ${props.startTime} – ${props.endTime}`;
        }
        const end = parseISO(props.endDate);
        const endFormatted = format(end, 'EE, d. MMM', { locale: de });
        return `${formatted} ${props.startTime} – ${endFormatted} ${props.endTime}`;
    } catch {
        return t('Date & Time');
    }
});

function handlePointerDownOutside(e: Event) {
    e.preventDefault();
}
</script>

<template>
    <Popover>
        <PopoverTrigger as-child>
            <Button
                type="button"
                variant="outline"
                class="rounded-full h-7 px-3 gap-1.5 text-xs font-normal"
                :class="{ 'border-destructive text-destructive': errors.start_at || errors.end_at }"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                {{ displayText }}
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-auto p-3" @pointer-down-outside="handlePointerDownOutside">
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1.5">
                    <Label class="text-xs text-muted-foreground">{{ t('Start') }}</Label>
                    <Input
                        type="date"
                        :model-value="startDate"
                        @update:model-value="(v: string | number) => emit('update:startDate', String(v))"
                        class="h-8 text-xs"
                        required
                    />
                </div>
                <div class="space-y-1.5">
                    <Label class="text-xs text-muted-foreground">&nbsp;</Label>
                    <Input
                        type="time"
                        :model-value="startTime"
                        @update:model-value="(v: string | number) => emit('update:startTime', String(v))"
                        class="h-8 text-xs"
                        required
                    />
                </div>
                <div class="space-y-1.5">
                    <Label class="text-xs text-muted-foreground">{{ t('End') }}</Label>
                    <Input
                        type="date"
                        :model-value="endDate"
                        @update:model-value="(v: string | number) => emit('update:endDate', String(v))"
                        class="h-8 text-xs"
                        required
                    />
                </div>
                <div class="space-y-1.5">
                    <Label class="text-xs text-muted-foreground">&nbsp;</Label>
                    <Input
                        type="time"
                        :model-value="endTime"
                        @update:model-value="(v: string | number) => emit('update:endTime', String(v))"
                        class="h-8 text-xs"
                        required
                    />
                </div>
            </div>
            <p v-if="errors.start_at" class="text-xs text-destructive mt-2">{{ errors.start_at }}</p>
            <p v-if="errors.end_at" class="text-xs text-destructive mt-2">{{ errors.end_at }}</p>
        </PopoverContent>
    </Popover>
</template>
