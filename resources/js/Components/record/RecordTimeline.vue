<script setup lang="ts">
import { computed, ref } from 'vue';
import { CalendarDays, FileText, History, MessageSquare, Paperclip } from 'lucide-vue-next';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/Components/ui/empty';
import {
    countByType,
    eventTime,
    filterByType,
    groupByDay,
    TIMELINE_ACTION_LABELS,
    TIMELINE_LABELS,
    TIMELINE_TONE,
} from '@/lib/timeline';
import { useTrans } from '@/lib/use-trans';
import type { TimelineEvent, TimelineEventType } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    events: TimelineEvent[];
    emptyTitle: string;
    emptyDescription: string;
}>();

const active = ref<TimelineEventType | 'all'>('all');

const counts = computed(() => countByType(props.events));
const groups = computed(() => groupByDay(filterByType(props.events, active.value)));

const ICONS = {
    appointment: CalendarDays,
    comment: MessageSquare,
    document: Paperclip,
    activity: History,
} as const;

/** The one-line headline of an event — an activity says what it did. */
function headline(event: TimelineEvent): string {
    if (event.type === 'activity' && event.action) {
        return t(TIMELINE_ACTION_LABELS[event.action]);
    }

    return event.title;
}

/**
 * `fields` already arrives as translation keys — App\Queries\RecordTimeline
 * whitelists the loggable columns, so an unmapped one never reaches here.
 */
function changedFields(event: TimelineEvent): string {
    return event.fields.map((field) => t(field)).join(', ');
}
</script>

<template>
    <div class="space-y-4">
        <div v-if="counts.length > 0" class="flex flex-wrap items-center gap-2" data-testid="timeline-filters">
            <button
                type="button"
                class="rounded-full border px-3 py-1 text-xs font-medium transition-colors"
                :class="active === 'all' ? 'border-navy bg-navy-wash text-navy' : 'text-muted-foreground hover:bg-muted'"
                data-testid="timeline-filter-all"
                @click="active = 'all'"
            >
                {{ t('All') }} · {{ events.length }}
            </button>
            <button
                v-for="entry in counts"
                :key="entry.type"
                type="button"
                class="rounded-full border px-3 py-1 text-xs font-medium transition-colors"
                :class="active === entry.type ? 'border-navy bg-navy-wash text-navy' : 'text-muted-foreground hover:bg-muted'"
                :data-testid="`timeline-filter-${entry.type}`"
                @click="active = entry.type"
            >
                {{ t(TIMELINE_LABELS[entry.type]) }} · {{ entry.count }}
            </button>
        </div>

        <Empty v-if="groups.length === 0" class="py-12">
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    <FileText />
                </EmptyMedia>
                <EmptyTitle>{{ emptyTitle }}</EmptyTitle>
                <EmptyDescription>{{ emptyDescription }}</EmptyDescription>
            </EmptyHeader>
        </Empty>

        <div v-for="group in groups" v-else :key="group.key" class="space-y-2">
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ group.label }}</p>

            <ul class="space-y-2">
                <li
                    v-for="event in group.events"
                    :key="event.id"
                    class="flex gap-3 rounded-lg border bg-card p-3"
                    data-testid="timeline-event"
                    :data-event-type="event.type"
                >
                    <span
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                        :class="TIMELINE_TONE[event.type]"
                    >
                        <component :is="ICONS[event.type]" class="h-4 w-4" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline gap-x-2">
                            <component
                                :is="event.url ? 'a' : 'span'"
                                :href="event.url ?? undefined"
                                class="truncate text-sm font-medium text-foreground"
                                :class="event.url ? 'hover:text-navy hover:underline' : ''"
                            >
                                {{ headline(event) }}
                            </component>
                            <span class="text-xs text-muted-foreground">{{ eventTime(event) }}</span>
                            <span
                                v-if="event.status"
                                class="inline-flex items-center gap-1 text-xs text-muted-foreground"
                            >
                                <span
                                    class="h-2 w-2 rounded-full"
                                    :style="{ backgroundColor: event.status.color }"
                                />
                                {{ event.status.name }}
                            </span>
                        </div>

                        <p v-if="event.type === 'activity' && event.title" class="truncate text-xs text-muted-foreground">
                            {{ event.title }}
                        </p>
                        <p v-if="event.excerpt" class="mt-0.5 line-clamp-2 text-sm text-muted-foreground">
                            {{ event.excerpt }}
                        </p>
                        <p v-if="event.fields.length > 0" class="mt-0.5 text-xs text-muted-foreground">
                            {{ t('Changed') }}: {{ changedFields(event) }}
                        </p>
                        <p v-if="event.actor" class="mt-0.5 text-xs text-muted-foreground">{{ event.actor }}</p>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>
