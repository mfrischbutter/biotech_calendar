<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue';
import { ChevronDown, ChevronUp, ExternalLink, X } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();

const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        subtitle?: string | null;
        /** Wording for the "go to the full record" button. */
        openLabel?: string;
    }>(),
    { subtitle: null, openLabel: '' },
);

const emit = defineEmits<{
    close: [];
    next: [];
    prev: [];
    openRecord: [];
}>();

/** Typing in a field owns the arrow keys; the panel must not steal them. */
function isTyping(target: EventTarget | null): boolean {
    if (!(target instanceof HTMLElement)) return false;

    return (
        target.tagName === 'INPUT' ||
        target.tagName === 'TEXTAREA' ||
        target.tagName === 'SELECT' ||
        target.isContentEditable
    );
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        emit('close');

        return;
    }

    if (isTyping(event.target)) return;

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        emit('next');
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        emit('prev');
    } else if (event.key === 'Enter') {
        event.preventDefault();
        emit('openRecord');
    }
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            window.addEventListener('keydown', onKeydown);
        } else {
            window.removeEventListener('keydown', onKeydown);
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Transition
        enter-active-class="transition-transform duration-200"
        enter-from-class="translate-x-full"
        leave-active-class="transition-transform duration-150"
        leave-to-class="translate-x-full"
    >
        <aside
            v-if="open"
            class="fixed inset-y-0 right-0 z-40 flex w-full max-w-md flex-col border-l border-border bg-background shadow-xl"
            role="complementary"
            :aria-label="title"
            data-testid="side-peek"
        >
            <header class="flex items-start gap-2 border-b border-border p-4">
                <div class="min-w-0 flex-1">
                    <h2 class="truncate text-base font-semibold text-foreground">{{ title }}</h2>
                    <p v-if="subtitle" class="truncate text-sm text-muted-foreground">{{ subtitle }}</p>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="t('Previous record')"
                        data-testid="peek-prev"
                        @click="emit('prev')"
                    >
                        <ChevronUp class="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="t('Next record')"
                        data-testid="peek-next"
                        @click="emit('next')"
                    >
                        <ChevronDown class="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="t('Close')"
                        data-testid="peek-close"
                        @click="emit('close')"
                    >
                        <X class="h-4 w-4" />
                    </Button>
                </div>
            </header>

            <div class="flex-1 space-y-5 overflow-y-auto p-4">
                <div v-if="$slots.actions" class="flex flex-wrap gap-2">
                    <slot name="actions" />
                </div>
                <slot />
            </div>

            <footer class="flex items-center justify-between gap-2 border-t border-border p-3">
                <p class="text-xs text-muted-foreground">{{ t('↑ ↓ to browse · Esc to close') }}</p>
                <Button size="sm" data-testid="peek-open" @click="emit('openRecord')">
                    <ExternalLink class="mr-2 h-4 w-4" />
                    {{ openLabel || t('Open record') }}
                </Button>
            </footer>
        </aside>
    </Transition>
</template>
