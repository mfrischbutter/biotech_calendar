<script setup lang="ts">
import { computed } from 'vue';
import { ChevronDown, X } from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { useTrans } from '@/lib/use-trans';
import { cn } from '@/lib/utils';

const { t } = useTrans();

const props = defineProps<{
    /** The dimension being filtered, e.g. "Mitarbeiter". Used once several things are picked. */
    label: string;
    /** What the pill reads when nothing is picked, e.g. "Alle Mitarbeiter". */
    idleLabel: string;
    /** Human names of everything currently picked, in list order. */
    selected: string[];
    testid: string;
}>();

const emit = defineEmits<{
    clear: [];
}>();

/** Menu state, controllable from the outside so a test can open it. */
const open = defineModel<boolean>('open', { default: false });

const active = computed(() => props.selected.length > 0);

/*
 * One selection reads better as the thing itself ("Lisa Bauer") than as a
 * count; past that the name list stops fitting, so the dimension plus a
 * count carries it.
 */
const summary = computed(() => {
    if (props.selected.length === 0) return props.idleLabel;
    if (props.selected.length === 1) return props.selected[0];

    return props.label;
});
</script>

<template>
    <DropdownMenu v-model:open="open">
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                :data-testid="testid"
                :data-active="active ? 'true' : 'false'"
                :class="
                    cn(
                        'inline-flex h-8 items-center gap-1.5 rounded-full border px-3 text-xs font-medium transition-colors',
                        active
                            ? 'border-navy/30 bg-navy-wash text-navy'
                            : 'border-input bg-background text-muted-foreground hover:bg-muted hover:text-foreground',
                    )
                "
            >
                <span class="max-w-[12rem] truncate">{{ summary }}</span>
                <span
                    v-if="selected.length > 1"
                    :data-testid="`${testid}-count`"
                    class="rounded-full bg-navy px-1.5 text-[10px] font-semibold leading-4 text-navy-foreground"
                >
                    {{ selected.length }}
                </span>
                <ChevronDown class="h-3.5 w-3.5 shrink-0 opacity-60" />
            </button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="start" class="max-h-80 w-60 overflow-y-auto">
            <slot />

            <template v-if="active">
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    class="gap-2 text-muted-foreground"
                    :data-testid="`${testid}-clear`"
                    @select.prevent="emit('clear')"
                >
                    <X class="h-3.5 w-3.5" />
                    {{ t('Clear selection') }}
                </DropdownMenuItem>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
