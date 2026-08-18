<script setup lang="ts">
import { Skeleton } from '@/Components/ui/skeleton';
import { TableCell, TableRow } from '@/Components/ui/table';

withDefaults(
    defineProps<{
        columns: number;
        rows?: number;
    }>(),
    { rows: 6 },
);
</script>

<template>
    <TableRow v-for="row in rows" :key="`skeleton-${row}`" data-testid="table-skeleton-row">
        <TableCell v-for="column in columns" :key="`skeleton-${row}-${column}`">
            <Skeleton class="h-4 bg-muted" :class="column === 1 ? 'w-40' : 'w-20'" />
        </TableCell>
    </TableRow>
</template>
