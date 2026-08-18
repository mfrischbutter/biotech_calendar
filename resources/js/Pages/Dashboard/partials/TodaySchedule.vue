<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { format, parseISO } from 'date-fns';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Empty, EmptyHeader, EmptyMedia, EmptyTitle, EmptyDescription } from '@/Components/ui/empty';
import { Calendar, UserX } from 'lucide-vue-next';
import { initials } from '@/lib/utils';
import { useTrans } from '@/lib/use-trans';
import type { ScheduleGroup, ScheduleItem } from '@/types';

const { t } = useTrans();

defineProps<{
    groups: ScheduleGroup[];
}>();

function time(value: string): string {
    return format(parseISO(value), 'HH:mm');
}

const stateBadge: Record<string, { label: string; class: string }> = {
    done: { label: 'Done', class: 'bg-success-wash text-success-foreground' },
    now: { label: 'Now', class: 'bg-navy-wash text-navy' },
    past: { label: 'Open', class: 'bg-warning-wash text-warning' },
    upcoming: { label: '', class: '' },
};

function railColour(item: ScheduleItem): string {
    if (item.state === 'done') return 'hsl(var(--success))';
    if (item.state === 'now') return 'hsl(var(--navy))';
    if (item.state === 'past') return 'hsl(var(--warning))';
    return item.status?.color ?? 'hsl(var(--muted-foreground))';
}
</script>

<template>
    <Card class="flex flex-col">
        <CardHeader class="pb-3">
            <CardTitle class="text-base">{{ t('On duty today') }}</CardTitle>
        </CardHeader>

        <CardContent class="flex-1 p-0">
            <Empty v-if="groups.length === 0" class="py-10">
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <Calendar class="h-5 w-5" aria-hidden="true" />
                    </EmptyMedia>
                    <EmptyTitle>{{ t('Nothing scheduled today') }}</EmptyTitle>
                    <EmptyDescription>{{ t('Enjoy the quiet day.') }}</EmptyDescription>
                </EmptyHeader>
            </Empty>

            <div v-else>
                <section v-for="group in groups" :key="group.worker_id ?? 'unassigned'">
                    <h3
                        class="flex items-center gap-2 border-y bg-muted/50 px-4 py-1.5 text-xs font-semibold"
                        :class="group.worker_id === null ? 'text-danger' : 'text-muted-foreground'"
                    >
                        <span
                            v-if="group.worker_id !== null"
                            class="grid h-5 w-5 place-content-center rounded-full bg-navy-wash text-[10px] font-semibold text-navy"
                        >{{ initials(group.worker_name ?? '') }}</span>
                        <UserX v-else class="h-4 w-4" aria-hidden="true" />
                        {{ group.worker_name ?? t('Not assigned') }}
                        <span class="font-normal">
                            &middot; {{ t(':count appointments', { count: String(group.items.length) }) }}
                        </span>
                    </h3>

                    <Link
                        v-for="item in group.items"
                        :key="`${group.worker_id}-${item.id}`"
                        :href="route('calendar.index', { appointment: item.id })"
                        class="flex items-center gap-3 border-b px-4 py-2.5 transition-colors last:border-b-0 hover:bg-accent"
                        :class="{ 'bg-navy-wash/40': item.state === 'now', 'opacity-60': item.state === 'done' }"
                    >
                        <span class="tabular w-11 shrink-0 text-sm text-muted-foreground">
                            {{ time(item.start_at) }}
                        </span>
                        <span
                            class="h-8 w-1 shrink-0 rounded-full"
                            :style="{ backgroundColor: railColour(item) }"
                        />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium">
                                {{ item.title ?? t('Appointment') }}
                            </span>
                            <span v-if="item.address" class="block truncate text-xs text-muted-foreground">
                                {{ item.address }}
                            </span>
                        </span>
                        <span
                            v-if="stateBadge[item.state]?.label"
                            class="shrink-0 rounded px-2 py-0.5 text-xs font-medium"
                            :class="stateBadge[item.state].class"
                        >
                            {{ t(stateBadge[item.state].label) }}
                        </span>
                    </Link>
                </section>
            </div>
        </CardContent>
    </Card>
</template>
