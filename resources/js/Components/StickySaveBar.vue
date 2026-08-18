<script setup lang="ts">
import { Button } from '@/Components/ui/button';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();

defineProps<{
    dirty: boolean;
    processing: boolean;
    /** Show the confirmation after a successful save. */
    saved?: boolean;
    /** Form element id the submit button belongs to. */
    form?: string;
}>();

const emit = defineEmits<{
    discard: [];
}>();
</script>

<template>
    <div
        data-testid="save-bar"
        class="sticky bottom-0 z-10 -mx-1 mt-6 flex items-center justify-between gap-3 rounded-lg border bg-background/95 px-4 py-3 backdrop-blur"
        :class="dirty ? 'border-warning/50 shadow-sm' : 'border-border'"
    >
        <p
            v-if="dirty"
            data-testid="save-bar-state"
            class="flex items-center gap-2 text-sm font-medium text-warning-foreground"
        >
            <span class="h-2 w-2 shrink-0 rounded-full bg-warning" />
            {{ t('Unsaved changes') }}
        </p>
        <p
            v-else-if="saved"
            data-testid="save-bar-state"
            class="text-sm text-muted-foreground"
        >{{ t('Saved.') }}</p>
        <p v-else data-testid="save-bar-state" class="text-sm text-muted-foreground">
            {{ t('All changes saved') }}
        </p>

        <div class="flex items-center gap-2">
            <Button
                v-if="dirty"
                type="button"
                variant="ghost"
                size="sm"
                data-testid="save-bar-discard"
                @click="emit('discard')"
            >{{ t('Discard') }}</Button>
            <Button
                type="submit"
                size="sm"
                :form="form"
                data-testid="save-bar-submit"
                :disabled="processing || !dirty"
            >
                {{ processing ? t('Saving...') : t('Save') }}
            </Button>
        </div>
    </div>
</template>
