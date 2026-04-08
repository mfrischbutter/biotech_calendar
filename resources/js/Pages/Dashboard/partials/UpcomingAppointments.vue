<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Empty, EmptyHeader, EmptyMedia, EmptyTitle } from '@/Components/ui/empty';
import { useTrans } from '@/lib/use-trans';
import { format } from 'date-fns';
import type { Appointment } from '@/types';

const { t } = useTrans();

defineProps<{
    appointments: Appointment[];
}>();

function formatTime(dateStr: string): string {
    return format(new Date(dateStr), 'HH:mm');
}
</script>

<template>
    <Card class="flex flex-col">
        <CardHeader>
            <CardTitle class="text-base">{{ t('Upcoming Today') }}</CardTitle>
        </CardHeader>
        <CardContent class="flex-1">
            <Empty v-if="appointments.length === 0" class="py-8">
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" /></svg>
                    </EmptyMedia>
                    <EmptyTitle>{{ t('No upcoming appointments') }}</EmptyTitle>
                </EmptyHeader>
            </Empty>
            <ul v-else class="space-y-3">
                <li
                    v-for="appointment in appointments"
                    :key="appointment.id"
                    class="flex items-center gap-3 rounded-md border px-3 py-2"
                >
                    <div
                        class="h-2 w-2 shrink-0 rounded-full"
                        :style="{ backgroundColor: appointment.status?.color ?? 'hsl(var(--muted-foreground))' }"
                    />
                    <span class="shrink-0 text-sm font-medium text-muted-foreground">
                        {{ formatTime(appointment.start_at) }}
                    </span>
                    <span class="min-w-0 flex-1 truncate text-sm font-medium">
                        {{ appointment.title }}
                    </span>
                    <span v-if="appointment.client" class="shrink-0 truncate text-sm text-muted-foreground">
                        {{ appointment.client.name }}
                    </span>
                </li>
            </ul>
        </CardContent>
    </Card>
</template>
