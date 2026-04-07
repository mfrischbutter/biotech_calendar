<script setup lang="ts">
import { computed, ref, onUnmounted } from 'vue';
import { format, parseISO } from 'date-fns';
import { de } from 'date-fns/locale';
import type { Appointment } from '@/types';
import { useTrans } from '@/lib/use-trans';
import { appointmentLabel } from '@/lib/appointment-label';
import { getStatusStyle } from '@/lib/status-colors';
import { localMinutes, isoToLocalParts } from '@/lib/date-utils';

const { t } = useTrans();

interface EmployeeRow { id: number | null; name: string; unassigned: boolean }

const props = defineProps<{
    appointments: Appointment[];
    employees: { id: number; first_name: string; last_name: string; name: string }[];
    dates: string[];
    view: 'team-day' | 'team-week';
    startHour: number;
    endHour: number;
}>();

const emit = defineEmits<{
    appointmentClick: [appointment: Appointment];
    createAppointment: [date: string, startTime: string, endTime: string, employeeId: number | null];
    moveAppointment: [appointment: Appointment, date: string, startTime: string, endTime: string, employeeId: number | null];
}>();

const isWeek = computed(() => props.view === 'team-week');

const allRows = computed<EmployeeRow[]>(() => [
    { id: null, name: t('Unassigned'), unassigned: true },
    ...props.employees.map(e => ({ id: e.id, name: e.name, unassigned: false })),
]);

// Group appointments by employee id (null = unassigned)
const byEmployee = computed(() => {
    const map = new Map<number | null, Appointment[]>();
    map.set(null, []);
    for (const emp of props.employees) map.set(emp.id, []);
    for (const appt of props.appointments) {
        const key = appt.employee?.id ?? null;
        const list = map.get(key);
        if (list) list.push(appt);
        else map.set(key, [appt]);
    }
    return map;
});

function cellAppts(empId: number | null, date: string): Appointment[] {
    return (byEmployee.value.get(empId) || [])
        .filter(a => isoToLocalParts(a.start_at).date === date)
        .sort((a, b) => a.start_at.localeCompare(b.start_at));
}

// Day-mode constants & helpers
const HOUR_WIDTH = 120;
const LANE_HEIGHT = 44;
const MIN_DRAG_PX = 4;
const hours = computed(() => Array.from({ length: props.endHour - props.startHour }, (_, i) => props.startHour + i));
const totalHours = computed(() => props.endHour - props.startHour);
const timelineWidth = computed(() => totalHours.value * HOUR_WIDTH);
const PX_PER_MIN = computed(() => HOUR_WIDTH / 60);

function apptDayStyle(appt: Appointment): Record<string, string> {
    const start = localMinutes(appt.start_at);
    const end = localMinutes(appt.end_at);
    const offset = Math.max(start - props.startHour * 60, 0);
    const dur = Math.min(end, props.endHour * 60) - Math.max(start, props.startHour * 60);
    return { left: `${offset * PX_PER_MIN.value}px`, width: `${Math.max(dur * PX_PER_MIN.value, 20)}px` };
}

const dayLanesByRow = computed(() => {
    if (isWeek.value) return new Map<number | null, Map<number, { lane: number; total: number }>>();
    const date = props.dates[0];
    const result = new Map<number | null, Map<number, { lane: number; total: number }>>();
    for (const row of allRows.value) {
        const appts = cellAppts(row.id, date);
        const lanes: { end: number }[] = [];
        const assign = new Map<number, number>();
        for (const a of appts) {
            const s = localMinutes(a.start_at), e = localMinutes(a.end_at);
            let placed = false;
            for (let i = 0; i < lanes.length; i++) {
                if (lanes[i].end <= s) { lanes[i].end = e; assign.set(a.id, i); placed = true; break; }
            }
            if (!placed) { assign.set(a.id, lanes.length); lanes.push({ end: e }); }
        }
        const m = new Map<number, { lane: number; total: number }>();
        for (const a of appts) m.set(a.id, { lane: assign.get(a.id)!, total: lanes.length || 1 });
        result.set(row.id, m);
    }
    return result;
});

function rowHeight(empId: number | null): number {
    const total = dayLanesByRow.value.get(empId)?.values().next().value?.total ?? 1;
    return Math.max(total, 1) * LANE_HEIGHT + 8;
}

function getTimeLabel(appt: Appointment): string {
    return `${isoToLocalParts(appt.start_at).time} – ${isoToLocalParts(appt.end_at).time}`;
}
function formatDayHeader(date: string): string {
    return format(parseISO(date), 'EEE d. MMM', { locale: de });
}
function fmtMin(m: number): string {
    return `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;
}

// ── Week-mode HTML5 drag (cell-based) ──
const draggedAppt = ref<Appointment | null>(null);
const dragOverCell = ref<{ empId: number | null; date: string } | null>(null);

function onDragStart(appt: Appointment, e: DragEvent) {
    draggedAppt.value = appt;
    e.dataTransfer!.effectAllowed = 'move';
    e.dataTransfer!.setData('text/plain', String(appt.id));
}
function onDrop(empId: number | null, date: string, e: DragEvent) {
    e.preventDefault();
    dragOverCell.value = null;
    const appt = draggedAppt.value;
    draggedAppt.value = null;
    if (!appt) return;
    const p = isoToLocalParts(appt.start_at), ep = isoToLocalParts(appt.end_at);
    emit('moveAppointment', appt, date, p.time, ep.time, empId);
}
function onDragEnd() { draggedAppt.value = null; dragOverCell.value = null; }
function onDragOver(empId: number | null, date: string, e: DragEvent) { e.preventDefault(); dragOverCell.value = { empId, date }; }
function isDragOver(empId: number | null, date: string) { return dragOverCell.value?.empId === empId && dragOverCell.value?.date === date; }

// ── Day-mode custom mouse drag (pixel-precise with preview) ──
interface DayDrag {
    active: boolean;
    appointment: Appointment | null;
    targetEmpId: number | null;
    startMin: number;
    duration: number;
    grabOffset: number;
    totalMovement: number;
}
const dayDrag = ref<DayDrag>({ active: false, appointment: null, targetEmpId: null, startMin: 0, duration: 0, grabOffset: 0, totalMovement: 0 });
let lastX = 0, lastY = 0;

const rowRefs = ref<Record<string, HTMLElement>>({});
function setRowRef(empId: number | null, el: unknown) { if (el) rowRefs.value[String(empId)] = el as HTMLElement; }

function minutesFromClientX(clientX: number): number {
    // Use any row ref to compute — they all share the same horizontal coordinate space
    const el = Object.values(rowRefs.value)[0];
    if (!el) return 0;
    const rect = el.getBoundingClientRect();
    const relX = clientX - rect.left;
    const raw = (relX / (totalHours.value * HOUR_WIDTH)) * totalHours.value * 60 + props.startHour * 60;
    return Math.round(raw / 15) * 15;
}

function empIdFromClientY(clientY: number): number | null {
    for (const row of allRows.value) {
        const el = rowRefs.value[String(row.id)];
        if (!el) continue;
        const r = el.getBoundingClientRect();
        if (clientY >= r.top && clientY < r.bottom) return row.id;
    }
    return dayDrag.value.targetEmpId;
}

function handleDayMouseDown(appt: Appointment, empId: number | null, e: MouseEvent) {
    if (e.button !== 0) return;
    e.preventDefault();
    e.stopPropagation();
    const startMin = localMinutes(appt.start_at);
    const endMin = localMinutes(appt.end_at);
    const mouseMin = minutesFromClientX(e.clientX);
    dayDrag.value = { active: true, appointment: appt, targetEmpId: empId, startMin, duration: endMin - startMin, grabOffset: mouseMin - startMin, totalMovement: 0 };
    lastX = e.clientX; lastY = e.clientY;
    window.addEventListener('mousemove', handleDayMouseMove);
    window.addEventListener('mouseup', handleDayMouseUp);
}

function handleDayMouseMove(e: MouseEvent) {
    if (!dayDrag.value.active) return;
    dayDrag.value.totalMovement += Math.abs(e.clientX - lastX) + Math.abs(e.clientY - lastY);
    lastX = e.clientX; lastY = e.clientY;
    const mouseMin = minutesFromClientX(e.clientX);
    dayDrag.value.startMin = mouseMin - dayDrag.value.grabOffset;
    dayDrag.value.targetEmpId = empIdFromClientY(e.clientY);
}

function handleDayMouseUp() {
    window.removeEventListener('mousemove', handleDayMouseMove);
    window.removeEventListener('mouseup', handleDayMouseUp);
    const d = dayDrag.value;
    const wasDrag = d.totalMovement >= MIN_DRAG_PX;
    if (d.active && d.appointment) {
        if (wasDrag) {
            emit('moveAppointment', d.appointment, props.dates[0], fmtMin(d.startMin), fmtMin(d.startMin + d.duration), d.targetEmpId);
        } else {
            emit('appointmentClick', d.appointment);
        }
    }
    dayDrag.value = { active: false, appointment: null, targetEmpId: null, startMin: 0, duration: 0, grabOffset: 0, totalMovement: 0 };
}

onUnmounted(() => { window.removeEventListener('mousemove', handleDayMouseMove); window.removeEventListener('mouseup', handleDayMouseUp); });

function dayPreviewStyle(): Record<string, string> | null {
    if (!dayDrag.value.active || dayDrag.value.totalMovement < MIN_DRAG_PX) return null;
    const left = (dayDrag.value.startMin - props.startHour * 60) * PX_PER_MIN.value;
    const width = dayDrag.value.duration * PX_PER_MIN.value;
    return { left: `${left}px`, width: `${Math.max(width, 20)}px` };
}

function isDayDragTarget(appt: Appointment): boolean {
    return dayDrag.value.active && dayDrag.value.appointment?.id === appt.id && dayDrag.value.totalMovement >= MIN_DRAG_PX;
}
</script>

<template>
    <div class="select-none" :class="isWeek ? 'h-full overflow-auto' : 'max-h-full overflow-x-scroll overflow-y-auto scrollbar-always'">
        <!-- WEEK MODE -->
        <table v-if="isWeek" class="w-full border-collapse">
            <thead class="sticky top-0 z-20 bg-background">
                <tr>
                    <th class="w-48 min-w-[12rem] border-b border-r p-2 text-left text-sm font-medium text-muted-foreground">{{ t('Employee') }}</th>
                    <th v-for="date in dates" :key="date" class="border-b border-r p-2 text-center text-sm font-medium text-muted-foreground min-w-[8rem]">{{ formatDayHeader(date) }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in allRows" :key="row.id ?? 'unassigned'" class="border-b" :class="row.unassigned ? 'bg-muted/30' : ''">
                    <td class="sticky left-0 z-10 w-48 min-w-[12rem] border-r p-2 text-sm font-medium" :class="row.unassigned ? 'bg-muted italic text-muted-foreground' : 'bg-background'">{{ row.name }}</td>
                    <td v-for="date in dates" :key="date" class="border-r p-1 align-top transition-colors min-w-[8rem]" :class="isDragOver(row.id, date) ? 'bg-primary/10' : ''"
                        @dragover="onDragOver(row.id, date, $event)" @dragleave="dragOverCell = null" @drop="onDrop(row.id, date, $event)" @dblclick="emit('createAppointment', date, '09:00', '10:00', row.id)">
                        <div v-for="appt in cellAppts(row.id, date)" :key="appt.id" class="mb-1 cursor-pointer rounded border px-1.5 py-1 text-xs" :style="getStatusStyle(appt.status?.color ?? null)"
                             :data-appointment-id="appt.id" draggable="true" @dragstart="onDragStart(appt, $event)" @dragend="onDragEnd" @click.stop="emit('appointmentClick', appt)">
                            <div class="font-medium truncate">{{ appointmentLabel(appt) }}</div>
                            <div class="opacity-70" style="font-size: 10px">{{ getTimeLabel(appt) }}</div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- DAY MODE -->
        <div v-else class="inline-flex flex-col min-w-full">
            <div class="sticky top-0 z-20 flex border-b bg-background">
                <div class="sticky left-0 z-30 w-48 min-w-[12rem] shrink-0 border-r bg-background p-2 text-sm font-medium text-muted-foreground">{{ t('Employee') }}</div>
                <div class="flex" :style="{ width: `${timelineWidth}px` }">
                    <div v-for="hour in hours" :key="hour" class="shrink-0 border-r py-2 text-center text-xs text-muted-foreground" :style="{ width: `${HOUR_WIDTH}px` }">
                        {{ String(hour).padStart(2, '0') }}:00
                    </div>
                </div>
            </div>
            <div v-for="row in allRows" :key="row.id ?? 'unassigned'" class="flex border-b" :class="row.unassigned ? 'bg-muted/30' : ''">
                <div class="sticky left-0 z-10 w-48 min-w-[12rem] shrink-0 border-r p-2 text-sm font-medium" :class="row.unassigned ? 'bg-muted italic text-muted-foreground' : 'bg-background'">{{ row.name }}</div>
                <div :ref="(el) => setRowRef(row.id, el)" class="relative" :style="{ width: `${timelineWidth}px`, height: `${rowHeight(row.id)}px` }"
                     @dblclick="emit('createAppointment', dates[0], '09:00', '10:00', row.id)">
                    <div v-for="hour in hours" :key="hour" class="absolute inset-y-0 border-r border-dashed border-border/40" :style="{ left: `${(hour - startHour) * HOUR_WIDTH}px` }" />
                    <!-- Appointment cards -->
                    <div v-for="appt in cellAppts(row.id, dates[0])" :key="appt.id"
                         class="absolute cursor-grab rounded border px-1.5 py-1 text-xs overflow-hidden transition-opacity"
                         :class="isDayDragTarget(appt) ? 'opacity-30' : ''"
                         :data-appointment-id="appt.id"
                         :style="{ ...apptDayStyle(appt), ...getStatusStyle(appt.status?.color ?? null), top: `${(dayLanesByRow.get(row.id)?.get(appt.id)?.lane ?? 0) * LANE_HEIGHT + 4}px`, height: `${LANE_HEIGHT - 4}px` }"
                         @mousedown="handleDayMouseDown(appt, row.id, $event)" @click.stop>
                        <div class="font-medium truncate">{{ appointmentLabel(appt) }}</div>
                        <div class="opacity-70" style="font-size: 10px">{{ getTimeLabel(appt) }}</div>
                    </div>
                    <!-- Drag preview ghost -->
                    <div v-if="dayDrag.active && dayDrag.targetEmpId === row.id && dayPreviewStyle()"
                         class="absolute z-30 pointer-events-none rounded border-l-4 px-1.5 py-1 text-xs opacity-80"
                         :style="{ ...dayPreviewStyle()!, ...getStatusStyle(dayDrag.appointment?.status?.color ?? null), top: '4px', bottom: '4px' }">
                        <div class="font-medium truncate">{{ dayDrag.appointment ? appointmentLabel(dayDrag.appointment) : '' }}</div>
                        <div class="opacity-70" style="font-size: 10px">{{ fmtMin(dayDrag.startMin) }} – {{ fmtMin(dayDrag.startMin + dayDrag.duration) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.scrollbar-always {
    overflow: scroll !important;
}
.scrollbar-always::-webkit-scrollbar {
    width: 8px;
    height: 8px;
    display: block;
}
.scrollbar-always::-webkit-scrollbar-thumb {
    background: hsl(var(--border));
    border-radius: 4px;
}
.scrollbar-always::-webkit-scrollbar-track {
    background: transparent;
}
</style>
