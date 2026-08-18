<script setup lang="ts">
import { computed } from 'vue';
import { Skeleton } from '@/Components/ui/skeleton';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();

const props = withDefaults(
    defineProps<{
        /** Column count of the board being replaced, so the shape stays familiar. */
        columns?: number;
        rows?: number;
    }>(),
    { columns: 5, rows: 6 },
);

/** A ragged run of blocks reads as "content loading", a uniform one as a table. */
const HEIGHTS = ['h-10', 'h-16', 'h-8', 'h-20', 'h-12'];

const cells = computed(() =>
    Array.from({ length: props.rows }, (_, row) =>
        Array.from({ length: props.columns }, (_, column) => ({
            key: `${row}-${column}`,
            height: HEIGHTS[(row + column) % HEIGHTS.length],
            filled: (row + column * 2) % 3 !== 0,
        })),
    ),
);
</script>

<template>
    <div
        class="flex h-full flex-col overflow-hidden rounded-lg border bg-background"
        data-testid="calendar-skeleton"
        role="status"
        :aria-label="t('Loading appointments')"
    >
        <div class="flex shrink-0 gap-2 border-b p-2">
            <Skeleton class="h-6 w-28 shrink-0 bg-muted" />
            <Skeleton v-for="column in columns" :key="`head-${column}`" class="h-6 flex-1 bg-muted" />
        </div>

        <div class="flex-1 space-y-2 overflow-hidden p-2">
            <div v-for="(row, index) in cells" :key="`row-${index}`" class="flex gap-2">
                <Skeleton class="h-8 w-28 shrink-0 bg-muted" />
                <template v-for="cell in row" :key="cell.key">
                    <Skeleton v-if="cell.filled" class="flex-1 bg-muted" :class="cell.height" />
                    <div v-else class="flex-1" />
                </template>
            </div>
        </div>
    </div>
</template>
