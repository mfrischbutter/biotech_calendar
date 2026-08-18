<script setup lang="ts">
import { computed } from 'vue';
import { SlidersHorizontal } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { useTrans } from '@/lib/use-trans';
import type { DataTableColumn } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    columns: DataTableColumn[];
    visible: string[];
}>();

const emit = defineEmits<{
    'update:visible': [keys: string[]];
}>();

/** Menu state, controllable from the outside so a page can close it. */
const open = defineModel<boolean>('open', { default: false });

const choosable = computed(() => props.columns.filter((column) => !column.locked));

function isVisible(key: string): boolean {
    return props.visible.includes(key);
}

function toggle(key: string): void {
    emit(
        'update:visible',
        isVisible(key) ? props.visible.filter((entry) => entry !== key) : [...props.visible, key],
    );
}
</script>

<template>
    <DropdownMenu v-model:open="open">
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm" data-testid="column-chooser">
                <SlidersHorizontal class="mr-2 h-4 w-4" />
                {{ t('Columns') }}
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-56">
            <DropdownMenuLabel>{{ t('Show columns') }}</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem
                v-for="column in choosable"
                :key="column.key"
                class="gap-2"
                :data-testid="`column-toggle-${column.key}`"
                @select.prevent="toggle(column.key)"
            >
                <Checkbox :model-value="isVisible(column.key)" tabindex="-1" />
                <span>{{ column.label }}</span>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
