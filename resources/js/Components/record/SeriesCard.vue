<script setup lang="ts">
import { computed } from 'vue';
import { RotateCw } from 'lucide-vue-next';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { shortDate } from '@/lib/date-utils';
import { recurrenceDescriptor } from '@/lib/recurrence';
import { useTrans } from '@/lib/use-trans';
import type { SeriesFact } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    series: SeriesFact | null;
}>();

const rule = computed(() => {
    if (!props.series) return null;

    return recurrenceDescriptor(true, props.series.recurrence_type ?? '', props.series.recurrence_interval ?? 1);
});
</script>

<template>
    <Card v-if="series && rule" data-testid="series-card">
        <CardHeader class="pb-3">
            <CardTitle class="flex items-center gap-2 text-base">
                <RotateCw class="h-4 w-4 text-navy" />
                {{ t('Series') }}
            </CardTitle>
        </CardHeader>
        <CardContent class="space-y-2 text-sm">
            <p class="font-medium text-foreground">{{ t(rule.key, rule.params) }}</p>
            <div class="flex items-center justify-between text-muted-foreground">
                <span>{{ t('Series start') }}</span>
                <span>{{ shortDate(series.started_at) ?? '–' }}</span>
            </div>
            <div class="flex items-center justify-between text-muted-foreground">
                <span>{{ t('Series end') }}</span>
                <span>{{ shortDate(series.recurrence_end) ?? t('Open-ended') }}</span>
            </div>
            <div class="flex items-center justify-between text-muted-foreground">
                <span>{{ t('Appointments') }}</span>
                <span>{{ series.occurrences }}</span>
            </div>
            <a :href="series.url" class="inline-block pt-1 text-sm text-navy hover:underline">
                {{ t('Open series') }}
            </a>
        </CardContent>
    </Card>
</template>
