<script setup lang="ts">
import { RotateCw } from 'lucide-vue-next';
import { useTrans } from '@/lib/use-trans';
import { appointmentClientName, appointmentLabel, isRecurring } from '@/lib/appointment-label';
import type { Appointment } from '@/types';

const { t } = useTrans();

defineProps<{
    appointment: Appointment;
    /** Absolute box plus the status tint, computed by the grid. */
    boxStyle: Record<string, string>;
    /** Double-booked: this technician is expected in two places at once. */
    conflicted: boolean;
    /** Too short to fit more than one line. */
    short: boolean;
    timeLabel: string;
    /** Hidden because it is the one currently being dragged. */
    dragging: boolean;
    /** False while a drag is in flight, so the card does not swallow the mouse. */
    interactive: boolean;
}>();

const emit = defineEmits<{
    select: [];
    moveStart: [event: MouseEvent];
    resizeStart: [event: MouseEvent];
}>();
</script>

<template>
    <div
        :data-appointment-id="appointment.id"
        :data-conflict="conflicted ? 'true' : undefined"
        :data-unassigned="appointment.workers.length === 0 ? 'true' : undefined"
        class="absolute overflow-hidden rounded-md border-l-[4px]"
        :class="[
            dragging ? 'opacity-0 !z-0' : '',
            interactive ? 'cursor-pointer transition-shadow hover:shadow-md' : 'pointer-events-none',
            conflicted ? 'ring-2 ring-inset ring-danger' : '',
            appointment.workers.length === 0 ? 'outline-dashed outline-2 outline-offset-[-3px] outline-navy-edge' : '',
        ]"
        :style="boxStyle"
        @mousedown.stop="emit('moveStart', $event)"
        @click.stop="emit('select')"
    >
        <div class="h-full px-2 py-1" :class="short ? 'flex items-center gap-2' : ''">
            <div class="truncate text-xs font-medium text-foreground">
                {{ appointmentClientName(appointment) || appointmentLabel(appointment) }}
            </div>
            <template v-if="!short">
                <div class="truncate text-[11px] text-muted-foreground">{{ appointmentLabel(appointment) }}</div>
                <div class="truncate text-[11px] text-muted-foreground">{{ timeLabel }}</div>
                <div v-if="appointment.workers.length > 0" class="truncate text-[11px] text-muted-foreground">
                    {{ appointment.workers.map(w => w.name).join(', ') }}
                </div>
                <div v-if="isRecurring(appointment)" class="flex items-center gap-0.5 text-[10px] text-muted-foreground">
                    <RotateCw class="h-3 w-3" data-testid="recurring-marker" />
                    {{ t('Series') }}
                </div>
            </template>
        </div>

        <div
            class="absolute bottom-0 left-0 right-0 h-2 cursor-s-resize rounded-b-md hover:bg-black/10"
            @mousedown.stop="emit('resizeStart', $event)"
        />
    </div>
</template>
