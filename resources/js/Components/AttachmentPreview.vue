<script setup lang="ts">
import { computed } from 'vue';
import {
    DialogPortal,
    DialogOverlay,
    DialogRoot,
    DialogContent,
    DialogTitle,
    DialogDescription,
    VisuallyHidden,
} from 'reka-ui';
import { useTrans } from '@/lib/use-trans';
import { formatBytes, isImageMime, isPdfMime } from '@/lib/file-utils';
import type { AppointmentAttachment } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    attachment: AppointmentAttachment | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const open = computed({
    get: () => props.attachment !== null,
    set: (v: boolean) => emit('update:open', v),
});

const downloadUrl = computed(() => {
    if (!props.attachment) return '#';
    return `${props.attachment.url}?download=1`;
});
</script>

<template>
    <DialogRoot :open="open" @update:open="emit('update:open', $event)">
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
            />
            <DialogContent
                class="fixed inset-0 z-50 flex flex-col bg-background data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
            >
                <VisuallyHidden>
                    <DialogTitle>{{ attachment?.original_name ?? t('Preview') }}</DialogTitle>
                    <DialogDescription>{{ t('Document preview') }}</DialogDescription>
                </VisuallyHidden>

                <div class="flex items-center justify-between gap-3 border-b bg-background px-4 py-3">
                    <div class="flex min-w-0 flex-col">
                        <span class="truncate text-sm font-medium text-foreground">{{ attachment?.original_name }}</span>
                        <span v-if="attachment" class="text-xs text-muted-foreground">{{ formatBytes(attachment.size) }}</span>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <a
                            :href="downloadUrl"
                            class="inline-flex h-9 items-center justify-center gap-1.5 rounded-md border bg-background px-3 text-sm font-medium text-foreground transition hover:bg-muted"
                            :title="t('Download')"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            <span class="hidden sm:inline">{{ t('Download') }}</span>
                        </a>
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground"
                            :title="t('Close')"
                            @click="emit('update:open', false)"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                </div>

                <div class="flex flex-1 items-center justify-center overflow-hidden bg-muted/30">
                    <template v-if="attachment">
                        <img
                            v-if="isImageMime(attachment.mime_type)"
                            :src="attachment.url"
                            :alt="attachment.original_name"
                            class="max-h-full max-w-full object-contain"
                        />
                        <iframe
                            v-else-if="isPdfMime(attachment.mime_type)"
                            :src="attachment.url"
                            :title="attachment.original_name"
                            class="h-full w-full border-0 bg-background"
                        />
                        <div v-else class="flex flex-col items-center gap-3 px-4 text-center">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-foreground">{{ t('Preview not available') }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">{{ t('Use the download button to open this file.') }}</p>
                            </div>
                        </div>
                    </template>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
