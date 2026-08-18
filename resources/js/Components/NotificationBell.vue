<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import { formatDistanceToNow } from 'date-fns';
import { de } from 'date-fns/locale';
import {
    Bell,
    AlertTriangle,
    UserPlus,
    UserMinus,
    MessageSquare,
    AtSign,
    RotateCw,
    Paperclip,
    CheckCheck,
} from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { useTrans } from '@/lib/use-trans';
import type { AppNotification, NotificationResponse } from '@/types';

const { t } = useTrans();

const open = ref(false);
const loading = ref(false);
const notifications = ref<AppNotification[]>([]);
const unread = ref(0);
const root = ref<HTMLElement | null>(null);
const trigger = ref<HTMLElement | null>(null);

// `ignore` keeps the outside-click handler from racing the trigger's own click,
// which otherwise makes the first click appear to do nothing.
onClickOutside(root, () => (open.value = false), { ignore: [trigger] });

async function load() {
    loading.value = true;
    try {
        const response = await fetch('/notifications', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) return;
        const payload = (await response.json()) as NotificationResponse;
        notifications.value = payload.notifications;
        unread.value = payload.unread;
    } finally {
        loading.value = false;
    }
}

onMounted(load);

async function toggle() {
    open.value = !open.value;
    if (open.value) await load();
}

function iconFor(notification: AppNotification) {
    switch (notification.type) {
        case 'schedule_conflict':
            return AlertTriangle;
        case 'appointment_assigned':
            return UserPlus;
        case 'appointment_unassigned':
            return UserMinus;
        case 'comment_mention':
            return AtSign;
        case 'series_ending':
            return RotateCw;
        case 'attachment_added':
            return Paperclip;
        default:
            return MessageSquare;
    }
}

const severityClass: Record<string, string> = {
    critical: 'bg-danger-wash text-danger',
    warning: 'bg-warning-wash text-warning',
    success: 'bg-success-wash text-success-foreground',
    info: 'bg-navy-wash text-navy',
};

function headline(notification: AppNotification): string {
    const title = notification.appointment_title ?? notification.data.title ?? t('an appointment');
    const actor = notification.actor ?? t('Someone');

    switch (notification.type) {
        case 'schedule_conflict':
            return t('Double booking on :title', { title });
        case 'appointment_assigned':
            return t('You were assigned to :title', { title });
        case 'appointment_unassigned':
            return t('You were removed from :title', { title });
        case 'comment_mention':
            return t(':actor mentioned you in :title', { actor, title });
        case 'series_ending':
            return t('The series :title is ending soon', { title });
        case 'attachment_added':
            return t(':actor uploaded a document to :title', { actor, title });
        default:
            return t(':actor commented on :title', { actor, title });
    }
}

function timeAgo(value: string): string {
    return formatDistanceToNow(new Date(value), { addSuffix: true, locale: de });
}

function openNotification(notification: AppNotification) {
    open.value = false;
    if (!notification.read_at) {
        router.post(route('notifications.read', notification.id), {}, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                notification.read_at = new Date().toISOString();
                unread.value = Math.max(0, unread.value - 1);
            },
        });
    }
    if (notification.url) router.visit(notification.url);
}

function markAllRead() {
    router.post(route('notifications.readAll'), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            notifications.value.forEach((n) => (n.read_at ??= new Date().toISOString()));
            unread.value = 0;
        },
    });
}

const badge = computed(() => (unread.value > 9 ? '9+' : String(unread.value)));
</script>

<template>
    <div ref="root" class="relative">
        <Button
            ref="trigger"
            variant="ghost"
            size="icon"
            class="relative"
            :aria-label="t('Notifications')"
            :aria-expanded="open"
            @click="toggle"
        >
            <Bell class="h-5 w-5" aria-hidden="true" />
            <span
                v-if="unread > 0"
                class="absolute right-1 top-1 grid h-4 min-w-4 place-content-center rounded-full bg-danger px-1 text-[10px] font-semibold leading-none text-white"
            >
                {{ badge }}
            </span>
        </Button>

        <div
            v-if="open"
            class="absolute right-0 top-11 z-50 w-[22rem] max-w-[calc(100vw-2rem)] overflow-hidden rounded-md border bg-popover shadow-lg"
        >
            <div class="flex items-center gap-2 border-b px-3 py-2">
                <p class="text-sm font-semibold">{{ t('Notifications') }}</p>
                <span class="flex-1"></span>
                <button
                    v-if="unread > 0"
                    type="button"
                    class="inline-flex items-center gap-1 text-xs font-medium text-navy hover:underline"
                    @click="markAllRead"
                >
                    <CheckCheck class="h-3.5 w-3.5" aria-hidden="true" />
                    {{ t('Mark all read') }}
                </button>
            </div>

            <p v-if="loading && notifications.length === 0" class="px-3 py-8 text-center text-sm text-muted-foreground">
                {{ t('Loading…') }}
            </p>

            <p v-else-if="notifications.length === 0" class="px-3 py-8 text-center text-sm text-muted-foreground">
                {{ t('Nothing needs your attention.') }}
            </p>

            <div v-else class="max-h-[60vh] overflow-y-auto">
                <button
                    v-for="notification in notifications"
                    :key="notification.id"
                    type="button"
                    class="flex w-full items-start gap-3 border-b px-3 py-2.5 text-left last:border-b-0 transition-colors hover:bg-accent"
                    :class="{ 'bg-navy-wash/50': !notification.read_at }"
                    @click="openNotification(notification)"
                >
                    <span
                        class="mt-0.5 grid h-6 w-6 shrink-0 place-content-center rounded-full"
                        :class="severityClass[notification.severity] ?? severityClass.info"
                    >
                        <component :is="iconFor(notification)" class="h-3.5 w-3.5" aria-hidden="true" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm leading-snug">{{ headline(notification) }}</span>
                        <span
                            v-if="notification.data.excerpt"
                            class="mt-1 block truncate rounded bg-muted px-2 py-1 text-xs text-foreground"
                        >
                            {{ notification.data.excerpt }}
                        </span>
                        <span class="mt-0.5 block text-xs text-muted-foreground">
                            {{ timeAgo(notification.created_at) }}
                        </span>
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>
