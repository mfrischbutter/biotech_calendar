<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { formatDistanceToNow, parseISO } from 'date-fns';
import { de } from 'date-fns/locale';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Empty, EmptyHeader, EmptyMedia, EmptyTitle } from '@/Components/ui/empty';
import { AlertTriangle, AtSign, UserPlus, UserMinus, MessageSquare, RotateCw, Paperclip, CheckCircle2 } from 'lucide-vue-next';
import { useTrans } from '@/lib/use-trans';
import type { AttentionNotification } from '@/types';

const { t } = useTrans();

defineProps<{
    notifications: AttentionNotification[];
}>();

function iconFor(type: string) {
    switch (type) {
        case 'schedule_conflict': return AlertTriangle;
        case 'comment_mention': return AtSign;
        case 'appointment_assigned': return UserPlus;
        case 'appointment_unassigned': return UserMinus;
        case 'series_ending': return RotateCw;
        case 'attachment_added': return Paperclip;
        default: return MessageSquare;
    }
}

const severityClass: Record<string, string> = {
    critical: 'bg-danger-wash text-danger',
    warning: 'bg-warning-wash text-warning',
    success: 'bg-success-wash text-success-foreground',
    info: 'bg-navy-wash text-navy',
};

function headline(n: AttentionNotification): string {
    const title = n.title ?? t('an appointment');
    const actor = n.actor ?? t('Someone');

    switch (n.type) {
        case 'schedule_conflict': return t('Double booking on :title', { title });
        case 'appointment_assigned': return t('You were assigned to :title', { title });
        case 'appointment_unassigned': return t('You were removed from :title', { title });
        case 'comment_mention': return t(':actor mentioned you in :title', { actor, title });
        case 'series_ending': return t('The series :title is ending soon', { title });
        case 'attachment_added': return t(':actor uploaded a document to :title', { actor, title });
        default: return t(':actor commented on :title', { actor, title });
    }
}

function timeAgo(value: string): string {
    return formatDistanceToNow(parseISO(value), { addSuffix: true, locale: de });
}
</script>

<template>
    <Card class="flex flex-col">
        <CardHeader class="flex flex-row items-center gap-2 pb-3">
            <CardTitle class="text-base">{{ t('Needs your attention') }}</CardTitle>
            <span
                v-if="notifications.length > 0"
                class="tabular ml-auto rounded bg-danger-wash px-2 py-0.5 text-xs font-semibold text-danger"
            >
                {{ notifications.length }}
            </span>
        </CardHeader>

        <CardContent class="flex-1 p-0">
            <Empty v-if="notifications.length === 0" class="py-10">
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <CheckCircle2 class="h-5 w-5" aria-hidden="true" />
                    </EmptyMedia>
                    <EmptyTitle>{{ t('Nothing needs your attention.') }}</EmptyTitle>
                </EmptyHeader>
            </Empty>

            <component
                :is="n.url ? Link : 'div'"
                v-for="n in notifications"
                v-else
                :key="n.id"
                :href="n.url ?? undefined"
                class="flex items-start gap-3 border-b px-4 py-2.5 last:border-b-0"
                :class="n.url ? 'transition-colors hover:bg-accent' : ''"
            >
                <span
                    class="mt-0.5 grid h-6 w-6 shrink-0 place-content-center rounded-full"
                    :class="severityClass[n.severity] ?? severityClass.info"
                >
                    <component :is="iconFor(n.type)" class="h-3.5 w-3.5" aria-hidden="true" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm leading-snug">{{ headline(n) }}</span>
                    <span v-if="n.excerpt" class="mt-1 block truncate rounded bg-muted px-2 py-1 text-xs">
                        {{ n.excerpt }}
                    </span>
                    <span class="mt-0.5 block text-xs text-muted-foreground">{{ timeAgo(n.created_at) }}</span>
                </span>
            </component>
        </CardContent>
    </Card>
</template>
