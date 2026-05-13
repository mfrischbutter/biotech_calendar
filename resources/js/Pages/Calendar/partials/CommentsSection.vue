<script setup lang="ts">
import { ref, computed, nextTick, watch, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import {
    Empty,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
    EmptyDescription,
} from '@/Components/ui/empty';
import { useTrans } from '@/lib/use-trans';
import { formatDistanceToNow, format, parseISO } from 'date-fns';
import { de } from 'date-fns/locale';
import { formatBytes, fileIconKey, isImageMime } from '@/lib/file-utils';
import AttachmentPreview from '@/Components/AttachmentPreview.vue';
import type { Comment, AppointmentAttachment, PageProps } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    appointmentId: number;
    comments: Comment[];
}>();

const page = usePage<PageProps>();

const body = ref('');
const pendingFiles = ref<File[]>([]);
const processing = ref(false);
const errors = ref<{ body?: string; files?: string }>({});

const fileInputRef = ref<HTMLInputElement | null>(null);
const scrollRef = ref<HTMLDivElement | null>(null);
const textareaRef = ref<HTMLTextAreaElement | null>(null);
const previewAttachment = ref<AppointmentAttachment | null>(null);

function openPreview(att: AppointmentAttachment) {
    previewAttachment.value = att;
}

function closePreview() {
    previewAttachment.value = null;
}

const sortedComments = computed(() =>
    [...props.comments].sort(
        (a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime(),
    ),
);

const currentUserId = computed(() => page.props.auth.user.id);

function isMine(comment: Comment): boolean {
    return comment.user_id === currentUserId.value;
}

function userInitials(comment: Comment): string {
    const u = comment.user;
    if (!u) return '?';
    return `${u.first_name?.[0] ?? ''}${u.last_name?.[0] ?? ''}`.toUpperCase() || '?';
}

function userName(comment: Comment): string {
    if (!comment.user) return t('Unknown');
    return `${comment.user.first_name} ${comment.user.last_name}`;
}

function timeAgo(dateStr: string): string {
    return formatDistanceToNow(new Date(dateStr), { addSuffix: true, locale: de });
}

function fullTime(dateStr: string): string {
    return format(parseISO(dateStr), 'dd.MM.yyyy HH:mm', { locale: de });
}

function openFilePicker() {
    fileInputRef.value?.click();
}

function onFilesPicked(e: Event) {
    const input = e.target as HTMLInputElement;
    if (!input.files) return;
    pendingFiles.value = [...pendingFiles.value, ...Array.from(input.files)];
    input.value = '';
}

function removePendingFile(index: number) {
    pendingFiles.value = pendingFiles.value.filter((_, i) => i !== index);
}

function canSubmit(): boolean {
    return !processing.value && (body.value.trim().length > 0 || pendingFiles.value.length > 0);
}

function submit() {
    if (!canSubmit()) return;
    const fd = new FormData();
    fd.append('body', body.value);
    pendingFiles.value.forEach(file => fd.append('files[]', file, file.name));

    processing.value = true;
    errors.value = {};
    router.post(route('comments.store', props.appointmentId), fd, {
        preserveScroll: true,
        forceFormData: true,
        onError: e => { errors.value = e as typeof errors.value; },
        onSuccess: () => {
            body.value = '';
            pendingFiles.value = [];
            nextTick(scrollToBottom);
        },
        onFinish: () => { processing.value = false; },
    });
}

function deleteComment(commentId: number) {
    if (!confirm(t('Delete this message?'))) return;
    router.delete(route('comments.destroy', commentId), {
        preserveScroll: true,
    });
}

function canDelete(comment: Comment): boolean {
    const user = page.props.auth.user;
    return comment.user_id === user.id || user.role === 'owner';
}

function autoResize() {
    const el = textareaRef.value;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = `${Math.min(el.scrollHeight, 140)}px`;
}

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        submit();
    }
}

function scrollToBottom() {
    const el = scrollRef.value;
    if (!el) return;
    el.scrollTop = el.scrollHeight;
}

watch(() => props.comments.length, () => {
    nextTick(scrollToBottom);
});

watch(body, autoResize);

onMounted(() => {
    nextTick(scrollToBottom);
});
</script>

<template>
    <div class="flex h-full flex-col">
        <!-- Messages -->
        <div ref="scrollRef" class="flex-1 overflow-y-auto px-4 py-4">
            <Empty v-if="sortedComments.length === 0" class="py-10">
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </EmptyMedia>
                    <EmptyTitle>{{ t('No messages yet') }}</EmptyTitle>
                    <EmptyDescription>{{ t('Start the conversation by sending the first message.') }}</EmptyDescription>
                </EmptyHeader>
            </Empty>

            <div v-else class="space-y-3">
                <div
                    v-for="comment in sortedComments"
                    :key="comment.id"
                    class="group flex items-end gap-2"
                    :class="isMine(comment) ? 'flex-row-reverse' : 'flex-row'"
                >
                    <div
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold"
                        :class="isMine(comment) ? 'bg-primary text-primary-foreground' : 'bg-muted text-foreground'"
                    >
                        {{ userInitials(comment) }}
                    </div>

                    <div class="flex max-w-[75%] flex-col gap-1" :class="isMine(comment) ? 'items-end' : 'items-start'">
                        <div
                            class="rounded-2xl px-3 py-2 text-sm shadow-sm"
                            :class="isMine(comment)
                                ? 'rounded-br-sm bg-primary text-primary-foreground'
                                : 'rounded-bl-sm bg-muted text-foreground'"
                        >
                            <div v-if="!isMine(comment)" class="mb-0.5 text-[11px] font-semibold opacity-70">
                                {{ userName(comment) }}
                            </div>

                            <p v-if="comment.body" class="whitespace-pre-wrap break-words leading-snug">{{ comment.body }}</p>

                            <div v-if="comment.attachments && comment.attachments.length" class="mt-2 space-y-1.5">
                                <template v-for="att in comment.attachments" :key="att.id">
                                    <button
                                        v-if="isImageMime(att.mime_type)"
                                        type="button"
                                        class="block w-full overflow-hidden rounded-lg border bg-background"
                                        @click="openPreview(att)"
                                    >
                                        <img :src="att.url" :alt="att.original_name" class="h-auto max-h-48 w-full object-cover" />
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        class="flex w-full items-center gap-2 rounded-lg border px-2.5 py-1.5 text-left text-xs transition hover:bg-background/40"
                                        :class="isMine(comment)
                                            ? 'border-primary-foreground/30 bg-primary-foreground/10 text-primary-foreground hover:bg-primary-foreground/15'
                                            : 'border-border bg-background text-foreground'"
                                        @click="openPreview(att)"
                                    >
                                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md"
                                            :class="isMine(comment) ? 'bg-primary-foreground/20' : 'bg-muted-foreground/10'">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path v-if="fileIconKey(att.mime_type) === 'pdf'" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                                <polyline v-if="fileIconKey(att.mime_type) === 'pdf'" points="14 2 14 8 20 8"/>
                                                <path v-if="fileIconKey(att.mime_type) !== 'pdf'" d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                                <polyline v-if="fileIconKey(att.mime_type) !== 'pdf'" points="7 10 12 15 17 10"/>
                                                <line v-if="fileIconKey(att.mime_type) !== 'pdf'" x1="12" y1="15" x2="12" y2="3"/>
                                            </svg>
                                        </span>
                                        <span class="flex min-w-0 flex-col">
                                            <span class="truncate font-medium">{{ att.original_name }}</span>
                                            <span class="text-[10px] opacity-70">{{ formatBytes(att.size) }}</span>
                                        </span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 px-1">
                            <span class="text-[10px] text-muted-foreground" :title="fullTime(comment.created_at)">
                                {{ timeAgo(comment.created_at) }}
                            </span>
                            <button
                                v-if="canDelete(comment)"
                                type="button"
                                class="hidden text-[10px] text-muted-foreground transition hover:text-destructive group-hover:inline-flex"
                                @click="deleteComment(comment.id)"
                            >
                                {{ t('Delete') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Composer -->
        <div class="border-t bg-background px-3 py-3">
            <div v-if="pendingFiles.length" class="mb-2 flex flex-wrap gap-2">
                <div
                    v-for="(f, i) in pendingFiles"
                    :key="i"
                    class="flex items-center gap-2 rounded-lg border bg-muted/40 px-2 py-1 text-xs"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                    <span class="max-w-[160px] truncate">{{ f.name }}</span>
                    <span class="text-muted-foreground">{{ formatBytes(f.size) }}</span>
                    <button
                        type="button"
                        class="text-muted-foreground hover:text-destructive"
                        @click="removePendingFile(i)"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
            </div>

            <form @submit.prevent="submit" class="flex items-end gap-2">
                <input
                    ref="fileInputRef"
                    type="file"
                    multiple
                    class="hidden"
                    @change="onFilesPicked"
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="h-9 w-9 shrink-0"
                    :title="t('Attach file')"
                    @click="openFilePicker"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                </Button>

                <textarea
                    ref="textareaRef"
                    v-model="body"
                    :placeholder="t('Write a message...')"
                    rows="1"
                    maxlength="1000"
                    class="min-h-9 max-h-[140px] flex-1 resize-none rounded-2xl border border-input bg-background px-3 py-2 text-sm leading-snug ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    @keydown="onKeydown"
                    @input="autoResize"
                />

                <Button
                    type="submit"
                    size="icon"
                    class="h-9 w-9 shrink-0 rounded-full"
                    :disabled="!canSubmit()"
                    :title="t('Send')"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                </Button>
            </form>
            <p v-if="errors.body" class="mt-1 text-xs text-destructive">{{ errors.body }}</p>
            <p v-if="errors.files" class="mt-1 text-xs text-destructive">{{ errors.files }}</p>
        </div>

        <AttachmentPreview :attachment="previewAttachment" @update:open="(v) => !v && closePreview()" />
    </div>
</template>
