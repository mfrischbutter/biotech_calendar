<script setup lang="ts">
import { KeyRound } from 'lucide-vue-next';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();

defineProps<{
    /** Site access details. The card does not exist when there are none. */
    notes: string | null;
}>();
</script>

<template>
    <Card v-if="notes" class="border-warning/40 bg-warning-wash" data-testid="access-notes-card">
        <CardHeader class="pb-3">
            <CardTitle class="flex items-center gap-2 text-base text-warning">
                <KeyRound class="h-4 w-4" />
                {{ t('Site access') }}
            </CardTitle>
        </CardHeader>
        <CardContent>
            <p class="whitespace-pre-wrap text-sm text-foreground">{{ notes }}</p>
        </CardContent>
    </Card>
</template>
