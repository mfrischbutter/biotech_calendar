<script setup lang="ts" generic="T extends { id: number }">
import { computed, ref, watch } from 'vue';
import { Card } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import TablePagination from '@/Components/TablePagination.vue';
import DataTableHeaderCell from './DataTableHeaderCell.vue';
import DataTableSelectionBar from './DataTableSelectionBar.vue';
import DataTableSkeleton from './DataTableSkeleton.vue';
import { cn } from '@/lib/utils';
import { useTrans } from '@/lib/use-trans';
import type { DataTableColumn, Paginated, SortState } from '@/types';

const { t } = useTrans();

const props = withDefaults(
    defineProps<{
        rows: T[];
        columns: DataTableColumn[];
        visibleColumns: string[];
        sort: SortState;
        pagination?: Paginated<unknown> | null;
        selectable?: boolean;
        loading?: boolean;
        /** Row currently shown in the side peek, highlighted in the table. */
        activeId?: number | null;
    }>(),
    { pagination: null, selectable: false, loading: false, activeId: null },
);

const emit = defineEmits<{
    sort: [key: string];
    rowClick: [row: T];
    selectionChange: [ids: number[]];
}>();

const selected = ref<number[]>([]);

const shown = computed(() => props.columns.filter((column) => props.visibleColumns.includes(column.key)));
const columnCount = computed(() => shown.value.length + (props.selectable ? 1 : 0) + 1);

const allSelected = computed(
    () => props.rows.length > 0 && selected.value.length === props.rows.length,
);
const someSelected = computed(() => selected.value.length > 0 && !allSelected.value);

// A reload replaces the rows; a selection that referred to the old page would
// silently act on the wrong records, so it is dropped.
watch(
    () => props.rows,
    () => setSelection([]),
);

function setSelection(ids: number[]): void {
    selected.value = ids;
    emit('selectionChange', ids);
}

function isSelected(id: number): boolean {
    return selected.value.includes(id);
}

function toggleRow(id: number): void {
    setSelection(isSelected(id) ? selected.value.filter((entry) => entry !== id) : [...selected.value, id]);
}

function toggleAll(): void {
    setSelection(allSelected.value ? [] : props.rows.map((row) => row.id));
}
</script>

<template>
    <div>
        <slot v-if="rows.length === 0 && !loading" name="empty" />

        <Card v-else>
            <div class="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead v-if="selectable" class="w-10">
                                <Checkbox
                                    :model-value="someSelected ? 'indeterminate' : allSelected"
                                    :aria-label="t('Select all')"
                                    data-testid="select-all"
                                    @update:model-value="toggleAll"
                                />
                            </TableHead>
                            <DataTableHeaderCell
                                v-for="column in shown"
                                :key="column.key"
                                :column="column"
                                :sort="sort"
                                @sort="emit('sort', $event)"
                            />
                            <TableHead class="w-12" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <DataTableSkeleton v-if="loading" :columns="columnCount" />
                        <TableRow
                            v-for="row in loading ? [] : rows"
                            :key="row.id"
                            class="cursor-pointer"
                            :class="activeId === row.id ? 'bg-navy-wash hover:bg-navy-wash' : ''"
                            :data-testid="`row-${row.id}`"
                            @click="emit('rowClick', row)"
                        >
                            <TableCell v-if="selectable" class="w-10" @click.stop>
                                <Checkbox
                                    :model-value="isSelected(row.id)"
                                    :data-testid="`row-select-${row.id}`"
                                    @update:model-value="toggleRow(row.id)"
                                />
                            </TableCell>
                            <TableCell
                                v-for="column in shown"
                                :key="column.key"
                                :class="cn('align-middle', column.cellClass)"
                            >
                                <slot :name="`cell-${column.key}`" :row="row" />
                            </TableCell>
                            <TableCell class="w-12 text-right" @click.stop>
                                <slot name="row-actions" :row="row" />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
            <TablePagination v-if="pagination" :pagination="pagination" />
        </Card>

        <DataTableSelectionBar
            v-if="selected.length > 0"
            :count="selected.length"
            @clear="setSelection([])"
        >
            <slot name="bulk-actions" :ids="selected" />
        </DataTableSelectionBar>
    </div>
</template>
