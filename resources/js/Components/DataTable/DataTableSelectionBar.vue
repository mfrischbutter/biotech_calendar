<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();

defineProps<{
    count: number;
}>();

const emit = defineEmits<{
    clear: [];
}>();
</script>

<template>
    <div
        class="pointer-events-none fixed inset-x-0 bottom-6 z-30 flex justify-center px-4"
        data-testid="selection-bar"
    >
        <div
            class="pointer-events-auto flex items-center gap-3 rounded-full bg-navy px-4 py-2 text-sm text-navy-foreground shadow-lg"
        >
            <span class="font-medium">{{ t(':count selected', { count: String(count) }) }}</span>
            <span class="text-navy-foreground/40">·</span>
            <slot />
            <button
                type="button"
                class="rounded-full p-1 transition-colors hover:bg-navy-hover"
                :aria-label="t('Clear selection')"
                data-testid="clear-selection"
                @click="emit('clear')"
            >
                <X class="h-4 w-4" />
            </button>
        </div>
    </div>
</template>
