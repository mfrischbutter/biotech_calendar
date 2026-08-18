<script setup lang="ts">
import { computed, ref } from 'vue';
import { RotateCw } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { recurrenceDescriptor } from '@/lib/recurrence';
import { useTrans } from '@/lib/use-trans';
import RecurrenceSection from './RecurrenceSection.vue';

const { t } = useTrans();

const props = defineProps<{
    isRecurring: boolean;
    recurrenceType: string;
    recurrenceInterval: number;
    recurrenceEnd: string;
    /** Existing series: the rule is shown but cannot be rewritten from here. */
    readonly?: boolean;
}>();

const emit = defineEmits<{
    'update:isRecurring': [value: boolean];
    'update:recurrenceType': [value: string];
    'update:recurrenceInterval': [value: number];
    'update:recurrenceEnd': [value: string];
}>();

const open = ref(false);

const label = computed(() => {
    const descriptor = recurrenceDescriptor(props.isRecurring, props.recurrenceType, props.recurrenceInterval);

    return t(descriptor.key, descriptor.params);
});
</script>

<template>
    <div
        v-if="readonly"
        data-testid="recurrence-chip"
        class="inline-flex items-center gap-1.5 rounded-full border border-navy-edge bg-navy-wash px-3 py-1 text-xs font-medium text-navy"
    >
        <RotateCw class="h-3.5 w-3.5" />
        {{ label }}
    </div>

    <Popover v-else v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                type="button"
                variant="outline"
                size="sm"
                data-testid="recurrence-chip"
                class="h-8 gap-1.5 rounded-full px-3 text-xs font-medium"
                :class="isRecurring ? 'border-navy-edge bg-navy-wash text-navy hover:bg-navy-wash' : ''"
            >
                <RotateCw class="h-3.5 w-3.5" />
                {{ label }}
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-80" align="start">
            <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                {{ t('Repeat') }}
            </p>
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
        </PopoverContent>
    </Popover>
</template>
