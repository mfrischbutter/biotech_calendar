<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { format, parseISO } from 'date-fns';
import { de } from 'date-fns/locale';
import { useCalendarDrag } from '@/lib/use-calendar-drag';
import { localMinutes } from '@/lib/date-utils';
import { appointmentTypes } from '@/lib/appointment-types';
import { useTrans } from '@/lib/use-trans';
import type { Appointment } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    appointments: Appointment[];
    dates: string[];
    showDayHeader?: boolean;
}>();

const emit = defineEmits<{
    appointmentClick: [appointment: Appointment];
    createAppointment: [date: string, startTime: string, endTime: string];
    moveAppointment: [appointment: Appointment, date: string, startTime: string, endTime: string];
    resizeAppointment: [appointment: Appointment, endTime: string];
}>();

const HOUR_HEIGHT = 60;
const START_HOUR = 0;
const END_HOUR = 24;
const hours = Array.from({ length: END_HOUR - START_HOUR }, (_, i) => START_HOUR + i);

const { drag, startCreate, startMove, startResize, onMouseMove, endDrag, formatMinutes, getDragPreviewStyle, getDragTimeLabel } = useCalendarDrag(HOUR_HEIGHT, START_HOUR);

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
// Optimistic overrides — instantly move appointment to new position on drop,
// before the server responds. Cleared when props.appointments changes.
// ---------------------------------------------------------------------------
interface OptimisticPosition {
    dayDate: string;
    startMinutes: number;
    endMinutes: number;
}
const optimistic = ref<Map<number, OptimisticPosition>>(new Map());

// Clear overrides when server data arrives (props change)
watch(() => props.appointments, () => {
    optimistic.value.clear();
});

function getOptimisticDay(appt: Appointment): string {
    const ov = optimistic.value.get(appt.id);
    if (ov) return ov.dayDate;
    // Extract local date from the appointment
    const d = new Date(appt.start_at);
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
}

// ---------------------------------------------------------------------------
// Build appointment lists per day, respecting optimistic overrides
// ---------------------------------------------------------------------------
const appointmentsByDay = computed(() => {
    const byDay: Record<string, Appointment[]> = {};
    for (const d of props.dates) {
        byDay[d] = [];
    }
    for (const appt of props.appointments) {
        const dayKey = getOptimisticDay(appt);
        if (byDay[dayKey]) {
            byDay[dayKey].push(appt);
        }
    }
    return byDay;
});

// ---------------------------------------------------------------------------
// Current time indicator
// ---------------------------------------------------------------------------
const now = ref(new Date());
let timeInterval: ReturnType<typeof setInterval>;

onMounted(() => {
    timeInterval = setInterval(() => { now.value = new Date(); }, 60000);
    nextTick(() => {
        if (scrollContainer.value) {
            scrollContainer.value.scrollTop = (8 - START_HOUR) * HOUR_HEIGHT - 20;
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
    return { top: `${((totalMin - START_HOUR * 60) / 60) * HOUR_HEIGHT}px` };
});
const currentTimeVisible = computed(() => {
    const h = now.value.getHours();
    return h >= START_HOUR && h < END_HOUR;
});

// ---------------------------------------------------------------------------
// Appointment positioning
// ---------------------------------------------------------------------------
function getAppointmentStyle(appt: Appointment) {
    const ov = optimistic.value.get(appt.id);
    let startMin: number, endMin: number;
    if (ov) {
        startMin = ov.startMinutes;
        endMin = ov.endMinutes;
    } else {
        startMin = localMinutes(appt.start_at);
        endMin = localMinutes(appt.end_at);
    }
    const duration = endMin - startMin;
    const top = ((startMin - START_HOUR * 60) / 60) * HOUR_HEIGHT;
    const height = Math.max((duration / 60) * HOUR_HEIGHT, 22);
    return { top: `${top}px`, height: `${height}px` };
}

function isShort(appt: Appointment): boolean {
    const ov = optimistic.value.get(appt.id);
    if (ov) return (ov.endMinutes - ov.startMinutes) <= 30;
    return (new Date(appt.end_at).getTime() - new Date(appt.start_at).getTime()) / 60000 <= 30;
}

function getTimeLabel(appt: Appointment): string {
    const ov = optimistic.value.get(appt.id);
    if (ov) return `${formatMinutes(ov.startMinutes)} – ${formatMinutes(ov.endMinutes)}`;
    return `${format(new Date(appt.start_at), 'HH:mm')} – ${format(new Date(appt.end_at), 'HH:mm')}`;
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
    const result = endDrag();
    if (!result) return;

    suppressClick.value = true;
    setTimeout(() => { suppressClick.value = false; }, 50);

    if (result.mode === 'create') {
        emit('createAppointment', result.dayDate, formatMinutes(result.startMinutes), formatMinutes(result.endMinutes));
    } else if (result.mode === 'move' && result.appointment) {
        // Apply optimistic position immediately — no flicker
        optimistic.value.set(result.appointment.id, {
            dayDate: result.dayDate,
            startMinutes: result.startMinutes,
            endMinutes: result.endMinutes,
        });
        emit('moveAppointment', result.appointment, result.dayDate, formatMinutes(result.startMinutes), formatMinutes(result.endMinutes));
    } else if (result.mode === 'resize' && result.appointment) {
        const startMin = localMinutes(result.appointment.start_at);
        optimistic.value.set(result.appointment.id, {
            dayDate: getOptimisticDay(result.appointment),
            startMinutes: startMin,
            endMinutes: result.endMinutes,
        });
        emit('resizeAppointment', result.appointment, formatMinutes(result.endMinutes));
    }
}

function handleAppointmentClick(e: MouseEvent, appt: Appointment) {
    if (suppressClick.value) return;
    emit('appointmentClick', appt);
}

function isDragTarget(appt: Appointment) {
    return drag.value.active && drag.value.totalMovement >= 4 && (drag.value.mode === 'move' || drag.value.mode === 'resize') && drag.value.appointment?.id === appt.id;
}
</script>

<template>
    <div
        class="flex flex-col h-full rounded-lg border bg-background overflow-hidden"
        :class="{ 'select-none': drag.active }"
    >
        <!-- Day headers -->
        <div
            v-if="showDayHeader !== false"
            class="grid border-b bg-background shrink-0"
            :style="{ gridTemplateColumns: `56px repeat(${dates.length}, 1fr)` }"
        >
            <div class="border-r" />
            <div
                v-for="day in days"
                :key="day.date"
                class="py-2 text-center border-r last:border-r-0"
            >
                <div
                    class="text-[11px] font-medium tracking-wide"
                    :class="day.isToday ? 'text-primary' : 'text-muted-foreground'"
                >
                    {{ day.dayLabel }}
                </div>
                <div
                    class="mt-0.5 inline-flex items-center justify-center text-[26px] leading-none font-normal"
                    :class="day.isToday
                        ? 'bg-primary text-primary-foreground rounded-full w-[46px] h-[46px]'
                        : 'text-foreground'"
                >
                    {{ day.dayNum }}
                </div>
            </div>
        </div>

        <!-- Scrollable time grid -->
        <div ref="scrollContainer" class="flex-1 overflow-y-auto overflow-x-hidden">
            <div
                class="grid relative"
                :style="{ gridTemplateColumns: `56px repeat(${dates.length}, 1fr)` }"
            >
                <!-- Time gutter -->
                <div class="border-r">
                    <div
                        v-for="hour in hours"
                        :key="hour"
                        class="relative"
                        :style="{ height: `${HOUR_HEIGHT}px` }"
                    >
                        <span
                            v-if="hour !== START_HOUR"
                            class="absolute -top-[9px] right-2 text-[11px] text-muted-foreground select-none"
                        >
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
                    @mousedown="handleMouseDown($event, day.date)"
                >
                    <!-- Hour lines -->
                    <div
                        v-for="hour in hours"
                        :key="hour"
                        class="border-b border-border/50"
                        :style="{ height: `${HOUR_HEIGHT}px` }"
                    >
                        <div class="h-1/2 border-b border-border/25" />
                    </div>

                    <!-- Current time indicator -->
                    <div
                        v-if="day.isToday && currentTimeVisible"
                        class="absolute left-0 right-0 z-20 pointer-events-none"
                        :style="currentTimeStyle"
                    >
                        <div class="relative">
                            <div class="absolute -left-[5px] -top-[5px] w-[10px] h-[10px] rounded-full bg-red-500" />
                            <div class="h-[2px] bg-red-500" />
                        </div>
                    </div>

                    <!-- Appointments -->
                    <div
                        v-for="appt in appointmentsByDay[day.date]"
                        :key="appt.id"
                        class="absolute left-1 right-1 rounded-md cursor-pointer overflow-hidden transition-shadow hover:shadow-md border-l-[4px]"
                        :class="[
                            appointmentTypes[appt.type].bgColor,
                            appointmentTypes[appt.type].borderColor,
                            isDragTarget(appt) ? 'opacity-0 z-0' : 'z-10',
                        ]"
                        :style="getAppointmentStyle(appt)"
                        @mousedown.stop="handleAppointmentMouseDown($event, appt, day.date)"
                        @click.stop="handleAppointmentClick($event, appt)"
                    >
                        <div class="px-2 py-1 h-full" :class="isShort(appt) ? 'flex items-center gap-2' : ''">
                            <div
                                class="font-medium text-xs truncate"
                                :class="appointmentTypes[appt.type].color"
                            >
                                {{ appt.client?.name || appt.title }}
                            </div>
                            <div v-if="!isShort(appt)" class="text-[11px] text-muted-foreground truncate">
                                {{ getTimeLabel(appt) }}
                            </div>
                            <div v-if="!isShort(appt) && appt.employee" class="text-[11px] text-muted-foreground truncate">
                                {{ appt.employee.name }}
                            </div>
                            <div v-if="!isShort(appt) && appt.parent_id !== null" class="text-[10px] text-muted-foreground flex items-center gap-0.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                                {{ t('Series') }}
                            </div>
                        </div>
                        <!-- Resize handle -->
                        <div
                            class="absolute bottom-0 left-0 right-0 h-2 cursor-s-resize hover:bg-black/10 rounded-b-md"
                            @mousedown.stop="handleResizeMouseDown($event, appt, day.date)"
                        />
                    </div>

                    <!-- Drag preview ghost -->
                    <div
                        v-if="drag.active && drag.dayDate === day.date && getDragPreviewStyle()"
                        class="absolute left-1 right-1 rounded-md z-30 pointer-events-none border-l-[4px]"
                        :class="drag.mode === 'create'
                            ? 'bg-primary/15 border-primary'
                            : drag.appointment
                                ? [appointmentTypes[drag.appointment.type].bgColor, appointmentTypes[drag.appointment.type].borderColor, 'opacity-80']
                                : 'bg-primary/15 border-primary'"
                        :style="getDragPreviewStyle()!"
                    >
                        <div class="px-2 py-1 text-xs">
                            <div v-if="drag.appointment" class="font-medium truncate" :class="appointmentTypes[drag.appointment.type].color">
                                {{ drag.appointment.client?.name || drag.appointment.title }}
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
