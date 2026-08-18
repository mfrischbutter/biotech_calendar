<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { useTrans } from '@/lib/use-trans';
import ShortcutHelpList from './ShortcutHelpList.vue';

const { t } = useTrans();

const open = defineModel<boolean>('open', { default: false });
</script>

<template>
    <Dialog :open="open" @update:open="open = $event">
        <DialogContent class="sm:max-w-sm" data-testid="shortcut-help">
            <DialogHeader>
                <DialogTitle>{{ t('Keyboard shortcuts') }}</DialogTitle>
                <DialogDescription>
                    {{ t('Every shortcut has a button on screen — nothing here is the only way.') }}
                </DialogDescription>
            </DialogHeader>

            <ShortcutHelpList />
        </DialogContent>
    </Dialog>
</template>
