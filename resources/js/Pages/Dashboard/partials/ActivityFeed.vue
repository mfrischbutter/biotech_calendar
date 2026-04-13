<script setup lang="ts">
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Empty, EmptyHeader, EmptyMedia, EmptyTitle } from '@/Components/ui/empty';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Badge } from '@/Components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/Components/ui/tooltip';
import { Link } from '@inertiajs/vue3';
import { useTrans } from '@/lib/use-trans';
import { formatDistanceToNow } from 'date-fns';
import { de } from 'date-fns/locale';
import type { ActivityLog } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    activities: ActivityLog[];
    comments: ActivityLog[];
    unreadCommentsCount: number;
}>();

const STORAGE_KEY = 'dashboard-activity-tab';
const savedTab = localStorage.getItem(STORAGE_KEY) ?? 'activities';
const activeTab = ref(savedTab);

watch(activeTab, (tab) => {
    localStorage.setItem(STORAGE_KEY, tab);
    if (tab === 'comments' && props.unreadCommentsCount > 0) {
        router.post(route('dashboard.comments.read'), {}, { preserveScroll: true, preserveState: true });
    }
});

// Mark read if the page loads with comments tab already active
if (activeTab.value === 'comments' && props.unreadCommentsCount > 0) {
    router.post(route('dashboard.comments.read'), {}, { preserveScroll: true, preserveState: true });
}

function userName(activity: ActivityLog): string {
    if (!activity.user) return '?';
    return `${activity.user.first_name} ${activity.user.last_name}`;
}

function timeAgo(dateStr: string): string {
    return formatDistanceToNow(new Date(dateStr), { addSuffix: true, locale: de });
}

function actionLabel(activity: ActivityLog): string {
    switch (activity.action) {
        case 'created': return t('created');
        case 'updated': return t('updated');
        case 'deleted': return t('deleted');
        case 'comment_added': return t('commented on');
        default: return activity.action;
    }
}

function appointmentUrl(activity: ActivityLog): string {
    return route('calendar.index', { appointment: activity.appointment!.id });
}

const actionColors: Record<string, string> = {
    created: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    updated: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    deleted: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    comment_added: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
};

const actionIcons: Record<string, string> = {
    created: '+',
    updated: '~',
    deleted: '×',
    comment_added: '💬',
};
</script>

<template>
    <Card class="flex flex-col">
        <CardHeader class="pb-2">
            <CardTitle class="text-base">{{ t('Recent Activity') }}</CardTitle>
        </CardHeader>
        <CardContent class="flex-1">
            <Tabs v-model="activeTab">
                <TabsList class="mb-3 w-full">
                    <TabsTrigger value="activities" class="flex-1">
                        {{ t('Activities') }}
                    </TabsTrigger>
                    <TabsTrigger value="comments" class="relative flex-1">
                        {{ t('Comments') }}
                        <Badge
                            v-if="unreadCommentsCount > 0 && activeTab !== 'comments'"
                            variant="destructive"
                            class="ml-1.5 h-5 min-w-5 px-1 text-[10px]"
                        >
                            {{ unreadCommentsCount }}
                        </Badge>
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="activities">
                    <Empty v-if="activities.length === 0" class="py-8">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                            </EmptyMedia>
                            <EmptyTitle>{{ t('No recent activity') }}</EmptyTitle>
                        </EmptyHeader>
                    </Empty>
                    <TooltipProvider v-else>
                        <div class="max-h-[400px] space-y-3 overflow-y-auto pr-1">
                            <div
                                v-for="activity in activities"
                                :key="activity.id"
                                class="flex items-start gap-3"
                            >
                                <div
                                    class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                                    :class="actionColors[activity.action] ?? 'bg-muted text-muted-foreground'"
                                >
                                    {{ actionIcons[activity.action] ?? '?' }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm">
                                        <span class="font-medium">{{ userName(activity) }}</span>{{ ' ' }}<span class="text-muted-foreground">{{ actionLabel(activity) }}</span>{{ ' ' }}<Tooltip v-if="activity.appointment">
                                            <TooltipTrigger as-child>
                                                <Link
                                                    :href="appointmentUrl(activity)"
                                                    class="font-medium text-primary hover:underline"
                                                >{{ activity.appointment.contract?.title ?? '–' }}</Link>
                                            </TooltipTrigger>
                                            <TooltipContent>{{ t('Open in calendar') }}</TooltipContent>
                                        </Tooltip>
                                    </p>
                                    <p class="mt-0.5 text-xs text-muted-foreground">{{ timeAgo(activity.created_at) }}</p>
                                </div>
                            </div>
                        </div>
                    </TooltipProvider>
                </TabsContent>

                <TabsContent value="comments">
                    <Empty v-if="comments.length === 0" class="py-8">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            </EmptyMedia>
                            <EmptyTitle>{{ t('No recent comments') }}</EmptyTitle>
                        </EmptyHeader>
                    </Empty>
                    <TooltipProvider v-else>
                        <div class="max-h-[400px] space-y-3 overflow-y-auto pr-1">
                            <div
                                v-for="comment in comments"
                                :key="comment.id"
                                class="flex items-start gap-3"
                            >
                                <div
                                    class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                                    :class="actionColors.comment_added"
                                >
                                    💬
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm">
                                        <span class="font-medium">{{ userName(comment) }}</span>{{ ' ' }}<span class="text-muted-foreground">{{ actionLabel(comment) }}</span>{{ ' ' }}<Tooltip v-if="comment.appointment">
                                            <TooltipTrigger as-child>
                                                <Link
                                                    :href="appointmentUrl(comment)"
                                                    class="font-medium text-primary hover:underline"
                                                >{{ comment.appointment.contract?.title ?? '–' }}</Link>
                                            </TooltipTrigger>
                                            <TooltipContent>{{ t('Open in calendar') }}</TooltipContent>
                                        </Tooltip>
                                    </p>
                                    <p v-if="comment.comment?.body" class="mt-1 rounded bg-muted/50 px-2 py-1 text-sm text-foreground">
                                        {{ comment.comment.body }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-muted-foreground">{{ timeAgo(comment.created_at) }}</p>
                                </div>
                            </div>
                        </div>
                    </TooltipProvider>
                </TabsContent>
            </Tabs>
        </CardContent>
    </Card>
</template>
