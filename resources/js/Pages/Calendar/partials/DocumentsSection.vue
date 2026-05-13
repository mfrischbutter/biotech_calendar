<script setup lang="ts">
import { ref, computed } from 'vue';
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
import { formatBytes, fileIconKey, isImageMime } from '@/lib/file-utils';
import { format, parseISO } from 'date-fns';
import { de } from 'date-fns/locale';
import AttachmentPreview from '@/Components/AttachmentPreview.vue';
import type { AppointmentAttachment, Comment, PageProps } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    appointmentId: number;
    documents: AppointmentAttachment[];
    comments: Comment[];
}>();

const page = usePage<PageProps>();

const dragOver = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);
const processing = ref(false);
const errors = ref<{ files?: string }>({});
const previewAttachment = ref<AppointmentAttachment | null>(null);

function openPreview(att: AppointmentAttachment) {
    previewAttachment.value = att;
}

function closePreview() {
    previewAttachment.value = null;
}

const allAttachments = computed<(AppointmentAttachment & { source: 'document' | 'comment' })[]>(() => {
    const direct: (AppointmentAttachment & { source: 'document' | 'comment' })[] = (props.documents ?? []).map(d => ({
        ...d,
        source: 'document' as const,
    }));
    const fromComments: (AppointmentAttachment & { source: 'document' | 'comment' })[] = (props.comments ?? [])
        .flatMap(c => c.attachments ?? [])
        .map(a => ({ ...a, source: 'comment' as const }));
    return [...direct, ...fromComments].sort(
        (a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime(),
    );
});

function uploaderName(att: AppointmentAttachment): string {
    if (!att.user) return t('Unknown');
    return `${att.user.first_name} ${att.user.last_name}`;
}

function fullDate(dateStr: string): string {
    return format(parseISO(dateStr), 'dd.MM.yyyy HH:mm', { locale: de });
}

function canDelete(att: AppointmentAttachment): boolean {
    const u = page.props.auth.user;
    return att.user_id === u.id || u.role === 'owner';
}

function pickFiles() {
    fileInputRef.value?.click();
}

function onFilesPicked(e: Event) {
    const input = e.target as HTMLInputElement;
    if (!input.files || input.files.length === 0) return;
    uploadFiles(Array.from(input.files));
    input.value = '';
}

function onDrop(e: DragEvent) {
    dragOver.value = false;
    if (!e.dataTransfer?.files?.length) return;
    uploadFiles(Array.from(e.dataTransfer.files));
}

function uploadFiles(files: File[]) {
    if (files.length === 0) return;
    const fd = new FormData();
    files.forEach(file => fd.append('files[]', file, file.name));

    processing.value = true;
    errors.value = {};
    router.post(route('attachments.store', props.appointmentId), fd, {
        preserveScroll: true,
        forceFormData: true,
        onError: e => { errors.value = e as typeof errors.value; },
        onFinish: () => { processing.value = false; },
    });
}

function deleteAttachment(att: AppointmentAttachment) {
    if (!confirm(t('Delete this document?'))) return;
    router.delete(route('attachments.destroy', att.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="flex h-full flex-col">
        <div class="flex-1 overflow-y-auto px-4 py-4">
            <!-- Drop zone -->
            <div
                class="mb-4 flex flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-4 py-6 text-center transition"
                :class="dragOver ? 'border-primary bg-primary/5' : 'border-border bg-muted/30'"
                @dragover.prevent="dragOver = true"
                @dragenter.prevent="dragOver = true"
                @dragleave.prevent="dragOver = false"
                @drop.prevent="onDrop"
            >
                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-background text-muted-foreground">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                </div>
                <p class="text-sm font-medium text-foreground">{{ t('Drop files here or click to upload') }}</p>
                <p class="text-xs text-muted-foreground">{{ t('Up to 10 MB per file') }}</p>
                <Button type="button" variant="outline" size="sm" :disabled="processing" @click="pickFiles">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mr-1.5 h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    {{ processing ? t('Uploading...') : t('Choose files') }}
                </Button>
                <input
                    ref="fileInputRef"
                    type="file"
                    multiple
                    class="hidden"
                    @change="onFilesPicked"
                />
            </div>

            <p v-if="errors.files" class="mb-2 text-xs text-destructive">{{ errors.files }}</p>

            <Empty v-if="allAttachments.length === 0" class="py-8">
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </EmptyMedia>
                    <EmptyTitle>{{ t('No documents yet') }}</EmptyTitle>
                    <EmptyDescription>{{ t('Upload files or send them in a comment to see them here.') }}</EmptyDescription>
                </EmptyHeader>
            </Empty>

            <ul v-else class="space-y-2">
                <li
                    v-for="att in allAttachments"
                    :key="`${att.source}-${att.id}`"
                    class="group flex items-center gap-3 rounded-lg border bg-background px-3 py-2 transition hover:bg-muted/40"
                >
                    <button
                        type="button"
                        class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-md bg-muted"
                        :title="t('Preview')"
                        @click="openPreview(att)"
                    >
                        <img
                            v-if="isImageMime(att.mime_type)"
                            :src="att.url"
                            :alt="att.original_name"
                            class="h-full w-full object-cover"
                        />
                        <svg
                            v-else-if="fileIconKey(att.mime_type) === 'pdf'"
                            xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-destructive" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <svg
                            v-else-if="fileIconKey(att.mime_type) === 'sheet'"
                            xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg>
                        <svg
                            v-else
                            xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </button>

                    <div class="flex min-w-0 flex-1 flex-col">
                        <button
                            type="button"
                            class="truncate text-left text-sm font-medium text-foreground hover:underline"
                            @click="openPreview(att)"
                        >
                            {{ att.original_name }}
                        </button>
                        <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                            <span>{{ formatBytes(att.size) }}</span>
                            <span>·</span>
                            <span>{{ uploaderName(att) }}</span>
                            <span>·</span>
                            <span :title="fullDate(att.created_at)">{{ fullDate(att.created_at) }}</span>
                            <span
                                v-if="att.source === 'comment'"
                                class="ml-1 inline-flex items-center gap-1 rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                {{ t('Comment') }}
                            </span>
                        </div>
                    </div>

                    <a
                        :href="`${att.url}?download=1`"
                        class="flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground"
                        :title="t('Download')"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    </a>
                    <button
                        v-if="canDelete(att)"
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive"
                        :title="t('Delete')"
                        @click="deleteAttachment(att)"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                </li>
            </ul>
        </div>

        <AttachmentPreview :attachment="previewAttachment" @update:open="(v) => !v && closePreview()" />
    </div>
</template>
