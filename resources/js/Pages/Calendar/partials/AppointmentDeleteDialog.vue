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

defineProps<{
    isRecurringSeries: boolean;
}>();

const open = defineModel<boolean>('open', { default: false });

const emit = defineEmits<{
    delete: [mode: 'single' | 'future' | 'series'];
}>();
</script>

<template>
    <AlertDialog v-model:open="open">
        <AlertDialogContent @pointer-down-outside="open = false">
            <AlertDialogHeader>
                <AlertDialogTitle>{{ t('Delete Appointment') }}?</AlertDialogTitle>
                <AlertDialogDescription>
                    {{ isRecurringSeries
                        ? t('This appointment is part of a series. How would you like to proceed?')
                        : t('This appointment will be permanently deleted. This action cannot be undone.')
                    }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter class="flex-col sm:flex-col gap-2">
                <div v-if="isRecurringSeries" class="flex flex-col gap-2 w-full">
                    <AlertDialogAction
                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        @click="emit('delete', 'single')"
                    >
                        {{ t('Delete This Only') }}
                    </AlertDialogAction>
                    <AlertDialogAction
                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        @click="emit('delete', 'future')"
                    >
                        {{ t('Delete All Future Appointments') }}
                    </AlertDialogAction>
                    <AlertDialogAction
                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        @click="emit('delete', 'series')"
                    >
                        {{ t('Delete Entire Series') }}
                    </AlertDialogAction>
                </div>
                <AlertDialogAction
                    v-else
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90 w-full"
                    @click="emit('delete', 'single')"
                >
                    {{ t('Delete') }}
                </AlertDialogAction>
                <AlertDialogCancel class="w-full">{{ t('Cancel') }}</AlertDialogCancel>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
