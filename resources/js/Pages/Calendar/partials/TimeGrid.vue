<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { format, parseISO } from 'date-fns';
import { de } from 'date-fns/locale';
import { useCalendarDrag } from '@/lib/use-calendar-drag';
import { getStatusStyle } from '@/lib/status-colors';
import { useTimeGridLayout } from '@/lib/use-time-grid-layout';
import { useTrans } from '@/lib/use-trans';
import { appointmentLabel } from '@/lib/appointment-label';
import AppointmentHoverCard from './AppointmentHoverCard.vue';
import TimeGridEvent from './TimeGridEvent.vue';
import type { Appointment, ConflictMap } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    appointments: Appointment[];
    dates: string[];
    showDayHeader?: boolean;
    startHour?: number;
    endHour?: number;
    conflicts?: ConflictMap;
}>();

const emit = defineEmits<{
    appointmentClick: [appointment: Appointment];
    createAppointment: [date: string, startTime: string, endTime: string];
    moveAppointment: [appointment: Appointment, date: string, startTime: string, endTime: string];
    resizeAppointment: [appointment: Appointment, endTime: string];
    expandDay: [date: string];
}>();

const HOUR_HEIGHT = 60;
const START_HOUR = computed(() => props.startHour ?? 0);
const END_HOUR = computed(() => props.endHour ?? 24);
const hours = computed(() => Array.from({ length: END_HOUR.value - START_HOUR.value }, (_, i) => START_HOUR.value + i));

const { drag, startCreate, startMove, startResize, onMouseMove, endDrag, formatMinutes, getDragPreviewStyle, getDragTimeLabel } =
    useCalendarDrag(HOUR_HEIGHT, START_HOUR);

const { visibleByDay, overflowByDay, minutesOf, positionStyle, overflowStyle, isShort } = useTimeGridLayout({
    appointments: () => props.appointments,
    dates: () => props.dates,
    startHour: () => START_HOUR.value,
    hourHeight: HOUR_HEIGHT,
});

const scrollContainer = ref<HTMLElement | null>(null);
const columnRefs = ref<Record<string, HTMLElement>>({});

function setColumnRef(date: string, el: HTMLElement | null) {
    if (el) columnRefs.value[date] = el;
}

const today = format(new Date(), 'yyyy-MM-dd');

const days = computed(() =>
    props.dates.map((dateStr) => {
        const date = parseISO(dateStr);
        return {
            date: dateStr,
            dayLabel: format(date, 'EEE', { locale: de }).toUpperCase().replace('.', ''),
            dayNum: format(date, 'd'),
            isToday: dateStr === today,
        };
    }),
);

// ---------------------------------------------------------------------------
// Current time indicator
// ---------------------------------------------------------------------------
const now = ref(new Date());
let timeInterval: ReturnType<typeof setInterval>;

onMounted(() => {
    timeInterval = setInterval(() => { now.value = new Date(); }, 60000);
    nextTick(() => {
        if (scrollContainer.value) {
            scrollContainer.value.scrollTop = Math.max(0, START_HOUR.value * HOUR_HEIGHT - 20);
        }
    });
    document.addEventListener('mousemove', handleGlobalMouseMove);
    document.addEventListener('mouseup', handleGlobalMouseUp);
});
onUnmounted(() => {
    clearInterval(timeInterval);
    document.removeEventListener('mousemove', handleGlobalMouseMove);
    document.removeEventListener('mouseup', handleGlobalMouseUp);
});

const currentTimeStyle = computed(() => {
    const totalMin = now.value.getHours() * 60 + now.value.getMinutes();
    return { top: `${((totalMin - START_HOUR.value * 60) / 60) * HOUR_HEIGHT}px` };
});
const currentTimeVisible = computed(() => {
    const h = now.value.getHours();
    return h >= START_HOUR.value && h < END_HOUR.value;
});

// ---------------------------------------------------------------------------
// Card presentation
// ---------------------------------------------------------------------------
function isConflicted(appt: Appointment): boolean {
    return (props.conflicts?.[appt.id]?.length ?? 0) > 0;
}

function getTimeLabel(appt: Appointment): string {
    const { start, end } = minutesOf(appt);
    return `${formatMinutes(start)} – ${formatMinutes(end)}`;
}

function apptCardStyle(appt: Appointment, dayDate: string) {
    const statusStyle = getStatusStyle(appt.status?.color ?? null);
    return {
        ...positionStyle(appt, dayDate),
        backgroundColor: statusStyle.backgroundColor,
        borderLeftColor: statusStyle.borderColor,
    };
}

// ---------------------------------------------------------------------------
// Drag handlers
// ---------------------------------------------------------------------------
const suppressClick = ref(false);

function handleMouseDown(e: MouseEvent, dayDate: string) {
    if (e.button !== 0) return;
    const col = columnRefs.value[dayDate];
    if (!col) return;
    startCreate(dayDate, e.clientY, col.getBoundingClientRect().top);
    e.preventDefault();
}

function handleAppointmentMouseDown(e: MouseEvent, appt: Appointment, dayDate: string) {
    if (e.button !== 0) return;
    e.stopPropagation();
    const col = columnRefs.value[dayDate];
    if (!col) return;
    startMove(appt, dayDate, e.clientY, col.getBoundingClientRect().top);
    e.preventDefault();
}

function handleResizeMouseDown(e: MouseEvent, appt: Appointment, dayDate: string) {
    if (e.button !== 0) return;
    e.stopPropagation();
    e.preventDefault();
    startResize(appt, dayDate);
}

function handleGlobalMouseMove(e: MouseEvent) {
    if (!drag.value.active) return;
    for (const [date, col] of Object.entries(columnRefs.value)) {
        const rect = col.getBoundingClientRect();
        if (e.clientX >= rect.left && e.clientX <= rect.right) {
            onMouseMove(e.clientY, rect.top, date);
            return;
        }
    }
    const col = columnRefs.value[drag.value.dayDate];
    if (col) onMouseMove(e.clientY, col.getBoundingClientRect().top);
}

function handleGlobalMouseUp() {
    const clickedAppointment = drag.value.active && drag.value.mode === 'move' ? drag.value.appointment : null;

    const result = endDrag();
    if (!result) {
        if (clickedAppointment) emit('appointmentClick', clickedAppointment);
        return;
    }

    suppressClick.value = true;
    setTimeout(() => { suppressClick.value = false; }, 50);

    if (result.mode === 'create') {
        emit('createAppointment', result.dayDate, formatMinutes(result.startMinutes), formatMinutes(result.endMinutes));
    } else if (result.mode === 'move' && result.appointment) {
        // The page applies the move optimistically and hands the block back
        // already in its new place, so nothing is tracked here.
        emit('moveAppointment', result.appointment, result.dayDate, formatMinutes(result.startMinutes), formatMinutes(result.endMinutes));
    } else if (result.mode === 'resize' && result.appointment) {
        emit('resizeAppointment', result.appointment, formatMinutes(result.endMinutes));
    }
}

function handleAppointmentClick(appt: Appointment) {
    if (suppressClick.value) return;
    emit('appointmentClick', appt);
}

function isDragTarget(appt: Appointment) {
    return drag.value.active && drag.value.totalMovement >= 4
        && (drag.value.mode === 'move' || drag.value.mode === 'resize')
        && drag.value.appointment?.id === appt.id;
}

function dragPreviewStyle() {
    const base = getDragPreviewStyle();
    if (!base) return null;
    if (drag.value.mode !== 'create' && drag.value.appointment) {
        const statusStyle = getStatusStyle(drag.value.appointment.status?.color ?? null);
        return { ...base, backgroundColor: statusStyle.backgroundColor, borderLeftColor: statusStyle.borderColor, opacity: '0.8' };
    }
    return base;
}
</script>

<template>
    <div class="flex h-full flex-col overflow-hidden rounded-lg border bg-background" :class="{ 'select-none': drag.active }">
        <!-- Day headers -->
        <div
            v-if="showDayHeader !== false"
            class="grid shrink-0 border-b bg-background"
            :style="{ gridTemplateColumns: `56px repeat(${dates.length}, 1fr)` }"
        >
            <div class="border-r" />
            <div
                v-for="day in days"
                :key="day.date"
                class="border-r py-2 text-center last:border-r-0"
                :class="day.isToday ? 'bg-navy-wash/60' : ''"
            >
                <div class="text-[11px] font-medium tracking-wide" :class="day.isToday ? 'text-navy' : 'text-muted-foreground'">
                    {{ day.dayLabel }}
                </div>
                <div
                    class="mt-0.5 inline-flex items-center justify-center text-base font-normal leading-none md:text-[26px]"
                    :class="day.isToday
                        ? 'h-[30px] w-[30px] rounded-full bg-navy text-navy-foreground md:h-[46px] md:w-[46px]'
                        : 'text-foreground'"
                >
                    {{ day.dayNum }}
                </div>
            </div>
        </div>

        <!-- Scrollable time grid -->
        <div ref="scrollContainer" class="flex-1 overflow-y-auto overflow-x-hidden">
            <div class="relative grid" :style="{ gridTemplateColumns: `56px repeat(${dates.length}, 1fr)` }">
                <!-- Time gutter -->
                <div class="border-r">
                    <div v-for="hour in hours" :key="hour" class="relative" :style="{ height: `${HOUR_HEIGHT}px` }">
                        <span v-if="hour !== START_HOUR" class="absolute -top-[9px] right-2 select-none text-[11px] text-muted-foreground">
                            {{ String(hour).padStart(2, '0') }}:00
                        </span>
                    </div>
                </div>

                <!-- Day columns -->
                <div
                    v-for="day in days"
                    :key="day.date"
                    :ref="(el) => setColumnRef(day.date, el as HTMLElement)"
                    class="relative border-r last:border-r-0"
                    :class="day.isToday ? 'bg-navy-wash/30' : ''"
                    @mousedown="handleMouseDown($event, day.date)"
                >
                    <!-- Hour lines -->
                    <div v-for="hour in hours" :key="hour" class="border-b border-border/50" :style="{ height: `${HOUR_HEIGHT}px` }">
                        <div class="h-1/2 border-b border-border/25" />
                    </div>

                    <!-- Current time indicator -->
                    <div
                        v-if="day.isToday && currentTimeVisible"
                        data-testid="current-time-line"
                        class="pointer-events-none absolute left-0 right-0 z-20"
                        :style="currentTimeStyle"
                    >
                        <div class="relative">
                            <div class="absolute -left-[5px] -top-[5px] h-[10px] w-[10px] rounded-full bg-danger" />
                            <div class="h-[2px] bg-danger" />
                        </div>
                    </div>

                    <!-- Appointments -->
                    <AppointmentHoverCard
                        v-for="appt in visibleByDay[day.date]"
                        :key="appt.id"
                        :appointment="appt"
                        :disabled="drag.active"
                    >
                        <TimeGridEvent
                            :appointment="appt"
                            :box-style="apptCardStyle(appt, day.date)"
                            :conflicted="isConflicted(appt)"
                            :short="isShort(appt)"
                            :time-label="getTimeLabel(appt)"
                            :dragging="isDragTarget(appt)"
                            :interactive="!(drag.active && drag.totalMovement >= 4)"
                            @select="handleAppointmentClick(appt)"
                            @move-start="handleAppointmentMouseDown($event, appt, day.date)"
                            @resize-start="handleResizeMouseDown($event, appt, day.date)"
                        />
                    </AppointmentHoverCard>

                    <!-- Collapsed tail: a readable block beats a complete but illegible one -->
                    <button
                        v-for="group in overflowByDay[day.date]"
                        :key="group.key"
                        type="button"
                        data-testid="overflow-chip"
                        class="absolute z-30 rounded-full border border-navy-edge bg-navy-wash px-2 py-0.5 text-[10px] font-semibold text-navy shadow-sm transition-colors hover:bg-navy hover:text-navy-foreground"
                        :style="overflowStyle(group)"
                        @mousedown.stop
                        @click.stop="emit('expandDay', day.date)"
                    >
                        +{{ group.count }} {{ t('more') }}
                    </button>

                    <!-- Drag preview ghost -->
                    <div
                        v-if="drag.active && drag.dayDate === day.date && dragPreviewStyle()"
                        class="pointer-events-none absolute z-30 rounded-md border-l-[4px] shadow-lg"
                        :class="drag.mode === 'create' ? 'border-primary bg-primary/15' : ''"
                        :style="{ ...dragPreviewStyle()!, left: '4px', right: '4px' }"
                    >
                        <div class="px-2 py-1 text-xs">
                            <div v-if="drag.appointment" class="truncate font-medium text-foreground">
                                {{ appointmentLabel(drag.appointment) }}
                            </div>
                            <div class="font-medium" :class="drag.mode === 'create' ? 'text-primary' : 'text-muted-foreground'">
                                {{ getDragTimeLabel()?.start }} – {{ getDragTimeLabel()?.end }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
