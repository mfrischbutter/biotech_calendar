<script setup lang="ts">
/**
 * A labelled band above a form section. The count is what turns four
 * equal-weight blocks into a scannable list ("Vor Ort 2/6").
 */
defineProps<{
    label: string;
    /** Already-formatted, e.g. "2/6" or "3". Omit to show no count. */
    count?: string | null;
}>();
</script>

<template>
    <div class="flex items-center justify-between gap-2">
        <h4 class="flex items-baseline gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            <span>{{ label }}</span>
            <span
                v-if="count"
                data-testid="section-count"
                class="rounded-full bg-navy-wash px-1.5 py-0.5 text-[10px] font-medium normal-case tracking-normal text-navy"
            >{{ count }}</span>
        </h4>
        <slot name="action" />
    </div>
</template>
