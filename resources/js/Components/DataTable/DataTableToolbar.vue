<script setup lang="ts">
import { Search, X } from 'lucide-vue-next';
import { Input } from '@/Components/ui/input';
import DataTableColumnChooser from './DataTableColumnChooser.vue';
import { useTrans } from '@/lib/use-trans';
import type { DataTableColumn, FilterChip, SavedView } from '@/types';

const { t } = useTrans();

withDefaults(
    defineProps<{
        search: string;
        placeholder: string;
        columns: DataTableColumn[];
        visibleColumns: string[];
        views?: SavedView[];
        activeView?: string;
        chips?: FilterChip[];
    }>(),
    { views: () => [], activeView: 'all', chips: () => [] },
);

const emit = defineEmits<{
    'update:search': [value: string];
    'update:view': [view: string];
    'update:visibleColumns': [keys: string[]];
    removeChip: [key: string];
}>();
</script>

<template>
    <div class="mb-4 space-y-3">
        <div v-if="views.length > 0" class="flex flex-wrap items-center gap-1 border-b border-border">
            <button
                v-for="view in views"
                :key="view.key"
                type="button"
                class="-mb-px flex items-center gap-2 border-b-2 px-3 py-2 text-sm transition-colors"
                :class="
                    activeView === view.key
                        ? 'border-navy font-medium text-navy'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                :data-testid="`view-${view.key}`"
                @click="emit('update:view', view.key)"
            >
                {{ view.label }}
                <span
                    v-if="view.count !== undefined"
                    class="rounded-full px-1.5 py-0.5 text-xs tabular-nums"
                    :class="activeView === view.key ? 'bg-navy-wash text-navy' : 'bg-muted text-muted-foreground'"
                >{{ view.count }}</span>
            </button>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="relative max-w-sm flex-1">
                <Search class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    :model-value="search"
                    type="search"
                    class="pl-8"
                    :placeholder="placeholder"
                    data-testid="list-search"
                    @update:model-value="emit('update:search', String($event ?? ''))"
                />
            </div>

            <slot name="filters" />

            <div class="ml-auto flex items-center gap-2">
                <slot name="actions" />
                <DataTableColumnChooser
                    :columns="columns"
                    :visible="visibleColumns"
                    @update:visible="emit('update:visibleColumns', $event)"
                />
            </div>
        </div>

        <div v-if="chips.length > 0" class="flex flex-wrap items-center gap-2">
            <span class="text-xs text-muted-foreground">{{ t('Filters') }}</span>
            <button
                v-for="chip in chips"
                :key="chip.key"
                type="button"
                class="inline-flex items-center gap-1 rounded-full bg-navy-wash px-2.5 py-1 text-xs font-medium text-navy transition-colors hover:bg-navy-wash/70"
                :data-testid="`chip-${chip.key}`"
                @click="emit('removeChip', chip.key)"
            >
                {{ chip.label }}
                <X class="h-3 w-3" />
            </button>
        </div>
    </div>
</template>
