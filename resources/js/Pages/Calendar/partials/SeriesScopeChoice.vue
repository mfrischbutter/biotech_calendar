<script setup lang="ts">
import { useTrans } from '@/lib/use-trans';
import type { SeriesScope } from '@/types';

const { t } = useTrans();

defineProps<{
    modelValue: SeriesScope;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: SeriesScope];
}>();

const options: { value: SeriesScope; label: string }[] = [
    { value: 'single', label: t('This appointment only') },
    { value: 'series', label: t('Whole series') },
];
</script>

<template>
    <div data-testid="series-scope" class="flex items-center gap-2">
        <span class="text-xs text-muted-foreground">{{ t('Applies to:') }}</span>
        <div class="inline-flex rounded-full border border-navy-edge p-0.5">
            <button
                v-for="option in options"
                :key="option.value"
                type="button"
                :data-scope="option.value"
                :aria-pressed="modelValue === option.value"
                class="rounded-full px-2.5 py-1 text-xs font-medium transition-colors"
                :class="modelValue === option.value
                    ? 'bg-navy text-navy-foreground'
                    : 'text-muted-foreground hover:text-foreground'"
                @click="emit('update:modelValue', option.value)"
            >
                {{ option.label }}
            </button>
        </div>
    </div>
</template>
