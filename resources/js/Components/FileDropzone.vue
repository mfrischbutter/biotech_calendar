<script setup lang="ts">
import { ref, computed } from 'vue';
import { UploadCloud } from 'lucide-vue-next';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();

const props = defineProps<{
    /** Extensions without the dot, e.g. ['png', 'jpg']. Shown and enforced. */
    formats: string[];
    maxSizeKb: number;
    /** Name of the file the user already picked, shown under the prompt. */
    selectedName?: string | null;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    select: [file: File];
    reject: [reason: string];
}>();

const inputRef = ref<HTMLInputElement>();
const dragging = ref(false);

const accept = computed(() => props.formats.map(f => `.${f}`).join(','));

const formatHint = computed(() =>
    t('Accepted: :formats · max. :size', {
        formats: props.formats.map(f => f.toUpperCase()).join(', '),
        size: `${Math.round(props.maxSizeKb / 1024)} MB`,
    }),
);

function extensionOf(name: string): string {
    const dot = name.lastIndexOf('.');
    return dot === -1 ? '' : name.slice(dot + 1).toLowerCase();
}

function accepts(file: File): boolean {
    return props.formats.includes(extensionOf(file.name));
}

function handleFile(file: File | undefined | null) {
    if (!file) return;
    if (!accepts(file)) {
        emit('reject', t('This file type is not supported.'));
        return;
    }
    if (file.size > props.maxSizeKb * 1024) {
        emit('reject', t('This file is too large.'));
        return;
    }
    emit('select', file);
}

function onDrop(event: DragEvent) {
    dragging.value = false;
    if (props.disabled) return;
    handleFile(event.dataTransfer?.files?.[0]);
}

function onChange(event: Event) {
    const input = event.target as HTMLInputElement;
    handleFile(input.files?.[0]);
    // Allows re-picking the same file after a rejection.
    input.value = '';
}

function openPicker() {
    if (props.disabled) return;
    inputRef.value?.click();
}
</script>

<template>
    <div
        data-testid="file-dropzone"
        role="button"
        tabindex="0"
        class="flex flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed px-6 py-8 text-center transition-colors"
        :class="[
            dragging ? 'border-primary bg-primary/5' : 'border-navy-edge bg-navy-wash/40 hover:border-primary',
            disabled ? 'pointer-events-none opacity-60' : 'cursor-pointer',
        ]"
        @click="openPicker"
        @keydown.enter.prevent="openPicker"
        @keydown.space.prevent="openPicker"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="onDrop"
    >
        <UploadCloud class="h-6 w-6 text-muted-foreground" />
        <p class="text-sm font-medium text-foreground">{{ t('Drop a file here or click to choose') }}</p>
        <p class="text-xs text-muted-foreground">{{ formatHint }}</p>
        <p v-if="selectedName" data-testid="dropzone-selection" class="mt-1 text-xs font-medium text-navy">
            {{ selectedName }}
        </p>

        <input
            ref="inputRef"
            type="file"
            class="sr-only"
            :accept="accept"
            :disabled="disabled"
            @change="onChange"
        />
    </div>
</template>
