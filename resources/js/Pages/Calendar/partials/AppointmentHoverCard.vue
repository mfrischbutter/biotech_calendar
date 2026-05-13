<script setup lang="ts">
import { computed } from 'vue';
import { format } from 'date-fns';
import { de } from 'date-fns/locale';
import { HoverCard, HoverCardContent, HoverCardTrigger } from '@/Components/ui/hover-card';
import { getStatusDotStyle } from '@/lib/status-colors';
import { appointmentLabel } from '@/lib/appointment-label';
import { useTrans } from '@/lib/use-trans';
import type { Appointment } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    appointment: Appointment;
    disabled?: boolean;
}>();

const kindLabel = computed(() => {
    const kind = props.appointment.contract?.kind;
    if (!kind) return null;
    return kind === 'kundentermin' ? t('Client appointment') : t('Without appointment');
});

const timeLabel = computed(() => {
    const start = new Date(props.appointment.start_at);
    const end = new Date(props.appointment.end_at);
    return `${format(start, 'HH:mm')} – ${format(end, 'HH:mm')}`;
});

const dateLabel = computed(() => {
    const start = new Date(props.appointment.start_at);
    return format(start, 'EEEE, dd. MMM yyyy', { locale: de });
});

const address = computed(() => {
    const c = props.appointment.contract;
    if (!c) return null;
    const parts = [
        c.street,
        [c.zip, c.city].filter(Boolean).join(' '),
    ].filter(Boolean);
    return parts.length > 0 ? parts.join(', ') : null;
});

const clientNames = computed(() => {
    return props.appointment.contract?.clients?.map(c => c.name).join(', ') ?? '';
});
</script>

<template>
    <HoverCard v-if="!disabled" :open-delay="200" :close-delay="100">
        <HoverCardTrigger as-child>
            <slot />
        </HoverCardTrigger>
        <HoverCardContent
            side="right"
            :side-offset="8"
            class="w-72 p-0"
            @pointerdown.stop
        >
            <div class="p-3 space-y-2">
                <!-- Kind -->
                <div v-if="kindLabel" class="text-[11px] text-muted-foreground uppercase tracking-wide">
                    {{ kindLabel }}
                </div>

                <!-- Title -->
                <div class="font-semibold text-sm text-foreground leading-snug">
                    {{ appointmentLabel(appointment) }}
                </div>

                <!-- Clients -->
                <div v-if="clientNames" class="flex items-center gap-1.5 text-sm text-muted-foreground">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/></svg>
                    <span class="truncate">{{ clientNames }}</span>
                </div>

                <!-- Address -->
                <div v-if="address" class="flex items-start gap-1.5 text-sm text-muted-foreground">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <span>{{ address }}</span>
                </div>

                <!-- Status -->
                <div v-if="appointment.status" class="flex items-center gap-1.5 text-sm">
                    <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="getStatusDotStyle(appointment.status.color)" />
                    <span class="text-foreground">{{ appointment.status.name }}</span>
                </div>
            </div>

            <div class="border-t px-3 py-2 space-y-1.5">
                <!-- Date & Time -->
                <div class="flex items-center justify-between text-sm">
                    <span class="text-foreground font-medium">{{ dateLabel }}</span>
                    <span class="text-muted-foreground">{{ timeLabel }}</span>
                </div>

                <!-- Workers -->
                <div v-if="appointment.workers.length > 0" class="flex items-center gap-1.5 text-sm text-muted-foreground">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span>{{ appointment.workers.map(w => w.name).join(', ') }}</span>
                </div>

                <!-- Series indicator -->
                <div v-if="appointment.parent_id !== null || appointment.recurrence_type !== null" class="flex items-center gap-1.5 text-sm text-muted-foreground">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                    <span>{{ t('Series') }}</span>
                </div>
            </div>
        </HoverCardContent>
    </HoverCard>
    <slot v-else />
</template>
