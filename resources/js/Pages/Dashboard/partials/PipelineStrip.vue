<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useTrans } from '@/lib/use-trans';
import { STAGE_LABELS, STAGE_DOT } from '@/lib/pipeline-stages';
import type { PipelineStage } from '@/types';

const { t } = useTrans();

defineProps<{
    stages: PipelineStage[];
}>();
</script>

<template>
    <div class="overflow-x-auto">
        <div class="flex min-w-max items-stretch gap-2">
            <Link
                v-for="(stage, index) in stages"
                :key="stage.stage"
                :href="route('contracts.index', { view: stage.stage })"
                class="flex min-w-[10rem] flex-1 items-center gap-3 rounded-lg border bg-card px-4 py-3 transition-shadow hover:shadow-sm"
            >
                <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="STAGE_DOT[stage.stage]" />
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-xs text-muted-foreground">
                        {{ t(STAGE_LABELS[stage.stage]) }}
                    </span>
                    <span class="tabular block text-xl font-semibold">{{ stage.count }}</span>
                </span>
                <span
                    v-if="index < stages.length - 1"
                    class="hidden text-muted-foreground sm:block"
                    aria-hidden="true"
                >&rarr;</span>
            </Link>
        </div>
    </div>
</template>
