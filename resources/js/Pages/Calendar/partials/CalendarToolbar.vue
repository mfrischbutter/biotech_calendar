<script setup lang="ts">
import { computed } from 'vue';
import { ChevronLeft, ChevronRight, Keyboard, Plus, TriangleAlert } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { useTrans } from '@/lib/use-trans';
import type { CalendarView } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    view: CalendarView;
    title: string;
    conflictCount: number;
    conflictsActive: boolean;
    detailed: boolean;
}>();

const emit = defineEmits<{
    navigate: [direction: number];
    today: [];
    switchView: [view: CalendarView];
    toggleDetailed: [];
    toggleConflicts: [];
    create: [];
    help: [];
}>();

/**
 * One control, four choices. The team board is a peer of day/week/month rather
 * than a mode layered on top of them, so there is nothing else to reason about.
 */
const segments = computed<{ key: string; label: string; view: CalendarView }[]>(() => [
    { key: 'day', label: t('Day'), view: 'day' },
    { key: 'week', label: t('Week'), view: 'week' },
    { key: 'month', label: t('Month'), view: 'month' },
    { key: 'team', label: t('Team'), view: 'team-week' },
]);

const isTeam = computed(() => props.view === 'team-week');

function isActive(key: string): boolean {
    return key === 'team' ? isTeam.value : props.view === key;
}

const conflictLabel = computed(() =>
    props.conflictCount === 1
        ? t(':count conflict', { count: '1' })
        : t(':count conflicts', { count: String(props.conflictCount) }),
);
</script>

<template>
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="flex items-center gap-2 md:gap-4">
            <div class="flex items-center gap-1">
                <Button variant="outline" size="icon" class="h-8 w-8" :aria-label="t('Previous')" @click="emit('navigate', -1)">
                    <ChevronLeft class="h-4 w-4" />
                </Button>
                <Button variant="outline" size="icon" class="h-8 w-8" :aria-label="t('Next')" @click="emit('navigate', 1)">
                    <ChevronRight class="h-4 w-4" />
                </Button>
            </div>
            <Button variant="outline" size="sm" @click="emit('today')">{{ t('Today') }}</Button>
            <h2 class="text-sm font-semibold text-foreground md:text-lg">{{ title }}</h2>
        </div>

        <div class="flex flex-wrap items-center gap-2 md:gap-3">
            <div class="flex overflow-hidden rounded-md border">
                <button
                    v-for="segment in segments"
                    :key="segment.key"
                    type="button"
                    :data-view="segment.key"
                    :aria-pressed="isActive(segment.key)"
                    class="px-2 py-1 text-xs font-medium transition-colors md:px-3 md:py-1.5 md:text-sm"
                    :class="isActive(segment.key)
                        ? 'bg-navy text-navy-foreground'
                        : 'text-muted-foreground hover:bg-muted'"
                    @click="emit('switchView', segment.view)"
                >
                    {{ segment.label }}
                </button>
            </div>

            <button
                v-if="isTeam"
                type="button"
                data-testid="detail-toggle"
                :aria-pressed="detailed"
                class="rounded-md border px-2 py-1 text-xs font-medium transition-colors md:px-3 md:py-1.5 md:text-sm"
                :class="detailed ? 'border-navy-edge bg-navy-wash text-navy' : 'text-muted-foreground hover:bg-muted'"
                @click="emit('toggleDetailed')"
            >
                {{ t('Detailed') }}
            </button>

            <button
                v-if="conflictCount > 0 || conflictsActive"
                type="button"
                data-testid="conflict-filter"
                :aria-pressed="conflictsActive"
                class="inline-flex items-center gap-1.5 rounded-md border px-2 py-1 text-xs font-medium transition-colors md:px-3 md:py-1.5 md:text-sm"
                :class="conflictsActive
                    ? 'border-danger bg-danger text-danger-foreground'
                    : 'border-danger/40 bg-danger-wash text-danger hover:bg-danger-wash/70'"
                @click="emit('toggleConflicts')"
            >
                <TriangleAlert class="h-3.5 w-3.5" />
                {{ conflictLabel }}
            </button>

            <Button
                variant="ghost"
                size="icon"
                class="h-8 w-8"
                data-testid="shortcut-help-trigger"
                :aria-label="t('Keyboard shortcuts')"
                :title="t('Keyboard shortcuts')"
                @click="emit('help')"
            >
                <Keyboard class="h-4 w-4" />
            </Button>

            <Button size="sm" class="ml-auto md:ml-0" @click="emit('create')">
                <Plus class="h-4 w-4 md:mr-1.5" />
                <span class="hidden md:inline">{{ t('New Appointment') }}</span>
            </Button>
        </div>
    </div>
</template>
