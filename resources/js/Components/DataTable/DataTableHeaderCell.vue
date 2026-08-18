<script setup lang="ts">
import { computed } from 'vue';
import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-vue-next';
import { TableHead } from '@/Components/ui/table';
import { cn } from '@/lib/utils';
import type { DataTableColumn, SortState } from '@/types';

const props = defineProps<{
    column: DataTableColumn;
    sort: SortState;
}>();

const emit = defineEmits<{
    sort: [key: string];
}>();

const isSorted = computed(() => !!props.column.sortKey && props.sort.key === props.column.sortKey);
const icon = computed(() => {
    if (!isSorted.value) return ChevronsUpDown;

    return props.sort.dir === 'asc' ? ArrowUp : ArrowDown;
});
</script>

<template>
    <TableHead :class="cn('whitespace-nowrap', column.headerClass)">
        <button
            v-if="column.sortKey"
            type="button"
            class="-ml-2 inline-flex items-center gap-1 rounded px-2 py-1 text-left font-medium transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            :class="isSorted ? 'text-navy' : 'text-muted-foreground'"
            :aria-sort="isSorted ? (sort.dir === 'asc' ? 'ascending' : 'descending') : 'none'"
            :data-testid="`sort-${column.key}`"
            @click="emit('sort', column.sortKey)"
        >
            {{ column.label }}
            <component :is="icon" class="h-3.5 w-3.5 shrink-0" :class="isSorted ? 'opacity-100' : 'opacity-40'" />
        </button>
        <span v-else>{{ column.label }}</span>
    </TableHead>
</template>
