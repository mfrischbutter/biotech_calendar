<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { initials } from '@/lib/utils';
import { useTrans } from '@/lib/use-trans';
import type { WorkloadRow } from '@/types';

const { t } = useTrans();

defineProps<{
    rows: WorkloadRow[];
}>();

/** Over 90% is a warning, over 100% a problem — that is the scheduling signal. */
function barClass(percent: number): string {
    if (percent >= 90) return 'bg-danger';
    if (percent >= 70) return 'bg-navy';
    return 'bg-success';
}
</script>

<template>
    <Card>
        <CardHeader class="pb-3">
            <CardTitle class="text-base">{{ t('Utilisation by employee') }}</CardTitle>
        </CardHeader>
        <CardContent>
            <p v-if="rows.length === 0" class="py-4 text-center text-sm text-muted-foreground">
                {{ t('Nobody is scheduled this week.') }}
            </p>
            <ul v-else class="space-y-3">
                <li v-for="row in rows" :key="row.id" class="flex items-center gap-3">
                    <span
                        class="grid h-6 w-6 shrink-0 place-content-center rounded-full bg-navy-wash text-[10px] font-semibold text-navy"
                    >{{ initials(row.name) }}</span>
                    <span class="w-24 shrink-0 truncate text-sm">{{ row.name }}</span>
                    <span class="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                        <span
                            class="block h-full rounded-full transition-all"
                            :class="barClass(row.percent)"
                            :style="{ width: `${Math.min(100, row.percent)}%` }"
                        />
                    </span>
                    <span class="tabular w-10 shrink-0 text-right text-sm text-muted-foreground">
                        {{ row.percent }}%
                    </span>
                </li>
            </ul>
        </CardContent>
    </Card>
</template>
