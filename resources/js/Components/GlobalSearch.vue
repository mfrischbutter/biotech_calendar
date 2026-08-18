<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import {
    Search,
    Building2,
    FileText,
    Calendar,
    CalendarPlus,
    UserPlus,
    FilePlus,
    CornerDownLeft,
    Loader2,
} from 'lucide-vue-next';
import { useTrans } from '@/lib/use-trans';
import { useGlobalSearch } from '@/lib/use-global-search';
import type { SearchItem } from '@/types';

const { t } = useTrans();

const { query, groups, loading, error, flatItems, activeIndex, move, reset, run } = useGlobalSearch();

const open = ref(false);
const root = ref<HTMLElement | null>(null);
const input = ref<HTMLInputElement | null>(null);

onClickOutside(root, () => close());

const hasResults = computed(() => groups.value.some((g) => g.items.length > 0));

/** Opening the field immediately loads the available actions, so it is useful before typing. */
async function focusOpen() {
    open.value = true;
    if (groups.value.length === 0 && !loading.value) {
        await run(query.value);
    }
}

function close() {
    open.value = false;
}

function clearAndClose() {
    reset();
    close();
}

function select(item: SearchItem) {
    clearAndClose();
    router.visit(item.url);
}

function onEnter() {
    const item = flatItems.value[activeIndex.value];
    if (item) select(item);
}

async function onArrow(delta: number) {
    if (!open.value) {
        await focusOpen();
        return;
    }
    move(delta);
}

function iconFor(item: SearchItem) {
    if (item.type === 'client') return Building2;
    if (item.type === 'contract') return FileText;
    if (item.type === 'appointment') return Calendar;
    if (item.icon === 'user-plus') return UserPlus;
    if (item.icon === 'file-plus') return FilePlus;
    return CalendarPlus;
}

function isActive(item: SearchItem): boolean {
    return flatItems.value[activeIndex.value]?.id === item.id
        && flatItems.value[activeIndex.value]?.type === item.type;
}

/** Exposed so the layout can focus the field from a "Search" button on mobile. */
defineExpose({
    focus: async () => {
        await focusOpen();
        await nextTick();
        input.value?.focus();
    },
});
</script>

<template>
    <div ref="root" class="relative w-full max-w-md">
        <div class="relative">
            <Search
                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />
            <input
                ref="input"
                v-model="query"
                type="search"
                role="combobox"
                aria-autocomplete="list"
                :aria-expanded="open"
                :placeholder="t('Search clients, contracts, appointments…')"
                class="h-9 w-full rounded-md border border-input bg-background pl-9 pr-3 text-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-navy focus:ring-2 focus:ring-navy/20"
                @focus="focusOpen"
                @keydown.down.prevent="onArrow(1)"
                @keydown.up.prevent="onArrow(-1)"
                @keydown.enter.prevent="onEnter"
                @keydown.esc="clearAndClose"
            />
            <Loader2
                v-if="loading"
                class="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-muted-foreground"
                aria-hidden="true"
            />
        </div>

        <div
            v-if="open"
            class="absolute left-0 right-0 top-11 z-50 max-h-[70vh] overflow-y-auto rounded-md border bg-popover shadow-lg"
        >
            <p v-if="error" class="px-3 py-6 text-center text-sm text-destructive">
                {{ t('Search is unavailable right now. Please try again.') }}
            </p>

            <p
                v-else-if="!hasResults && !loading"
                class="px-3 py-6 text-center text-sm text-muted-foreground"
            >
                {{ query ? t('Nothing found for ":query".', { query }) : t('Start typing to search.') }}
            </p>

            <template v-else>
                <div v-for="group in groups" :key="group.key" class="py-1">
                    <p class="px-3 py-1.5 text-xs font-semibold text-muted-foreground">
                        {{ group.label }}
                    </p>
                    <button
                        v-for="item in group.items"
                        :key="`${item.type}-${item.id}`"
                        type="button"
                        class="flex w-full items-center gap-3 px-3 py-2 text-left transition-colors"
                        :class="isActive(item) ? 'bg-navy-wash text-navy' : 'hover:bg-accent'"
                        @click="select(item)"
                    >
                        <component :is="iconFor(item)" class="h-4 w-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium">{{ item.title }}</span>
                            <span v-if="item.subtitle" class="block truncate text-xs text-muted-foreground">
                                {{ item.subtitle }}
                            </span>
                        </span>
                        <CornerDownLeft
                            v-if="isActive(item)"
                            class="h-3.5 w-3.5 shrink-0 text-navy"
                            aria-hidden="true"
                        />
                    </button>
                </div>
            </template>
        </div>
    </div>
</template>
