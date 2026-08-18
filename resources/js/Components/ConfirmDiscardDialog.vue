<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();

const open = defineModel<boolean>('open', { default: false });

const emit = defineEmits<{
    discard: [];
}>();
</script>

<template>
    <AlertDialog :open="open" @update:open="open = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ t('Discard changes?') }}</AlertDialogTitle>
                <AlertDialogDescription>
                    {{ t('You have unsaved changes. Are you sure you want to discard them?') }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>{{ t('Cancel') }}</AlertDialogCancel>
                <AlertDialogAction @click="emit('discard')">{{ t('Discard') }}</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
