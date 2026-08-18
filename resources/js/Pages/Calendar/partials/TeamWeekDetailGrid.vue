<script setup lang="ts">
import { computed, ref } from 'vue';
import { format, parseISO } from 'date-fns';
import { de } from 'date-fns/locale';
import { RotateCw } from 'lucide-vue-next';
import type { Appointment, CalendarEmployee, ConflictMap } from '@/types';
import { useTrans } from '@/lib/use-trans';
import { appointmentClientName, appointmentService, isRecurring } from '@/lib/appointment-label';
import { getStatusStyle } from '@/lib/status-colors';
import AppointmentHoverCard from './AppointmentHoverCard.vue';
import { localMinutes, isoToLocalParts } from '@/lib/date-utils';

const { t } = useTrans();

interface EmployeeRow { id: number | null; name: string; unassigned: boolean }

const props = defineProps<{
    appointments: Appointment[];
    employees: CalendarEmployee[];
    dates: string[];
    startHour: number;
    endHour: number;
    conflicts?: ConflictMap;
}>();

const emit = defineEmits<{
    appointmentClick: [appointment: Appointment];
    createAppointment: [date: string, startTime: string, endTime: string, employeeId: number | null];
    moveAppointment: [appointment: Appointment, date: string, startTime: string, endTime: string, employeeId: number | null];
}>();

const HOUR_HEIGHT = 60;
const totalHours = computed(() => props.endHour - props.startHour);
const cellHeight = computed(() => totalHours.value * HOUR_HEIGHT);
const hours = computed(() => Array.from({ length: totalHours.value }, (_, i) => props.startHour + i));

const allRows = computed<EmployeeRow[]>(() => [
    { id: null, name: t('Unassigned'), unassigned: true },
    ...props.employees.map(e => ({ id: e.id, name: e.name, unassigned: false })),
]);

const byEmployee = computed(() => {
    const map = new Map<number | null, Appointment[]>();
    map.set(null, []);
    for (const emp of props.employees) map.set(emp.id, []);
    for (const appt of props.appointments) {
        if (appt.workers.length === 0) {
            map.get(null)!.push(appt);
        } else {
            for (const w of appt.workers) {
                const list = map.get(w.id);
                if (list) list.push(appt);
            }
        }
    }
    return map;
});

function cellAppts(empId: number | null, date: string): Appointment[] {
    return (byEmployee.value.get(empId) || [])
        .filter(a => isoToLocalParts(a.start_at).date === date)
        .sort((a, b) => a.start_at.localeCompare(b.start_at));
}

function getApptCardStyle(appt: Appointment): Record<string, string> {
    const startMin = localMinutes(appt.start_at);
    const endMin = localMinutes(appt.end_at);
    const top = ((startMin - props.startHour * 60) / 60) * HOUR_HEIGHT;
    const height = ((endMin - startMin) / 60) * HOUR_HEIGHT;
    const tagStyle = getStatusStyle(appt.status?.color ?? null);
    return {
        top: `${Math.max(top, 0)}px`,
        height: `${Math.max(height, 22)}px`,
        left: '4px',
        right: '4px',
        backgroundColor: tagStyle.backgroundColor,
        borderLeftColor: tagStyle.borderColor,
    };
}

function getTimeLabel(appt: Appointment): string {
    return `${isoToLocalParts(appt.start_at).time} – ${isoToLocalParts(appt.end_at).time}`;
}

const today = format(new Date(), 'yyyy-MM-dd');

function isConflicted(appt: Appointment): boolean {
    return (props.conflicts?.[appt.id]?.length ?? 0) > 0;
}

function formatDayHeader(date: string): string {
    return format(parseISO(date), 'EEE d. MMM', { locale: de });
}

function fmtMin(m: number): string {
    return `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;
}

// ── HTML5 drag-and-drop with time-aware positioning ──
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
    const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();
    const relY = e.clientY - rect.top;
    const dropMinutes = Math.round((relY / HOUR_HEIGHT * 60 + props.startHour * 60) / 15) * 15;
    const duration = localMinutes(appt.end_at) - localMinutes(appt.start_at);
    emit('moveAppointment', appt, date, fmtMin(dropMinutes), fmtMin(dropMinutes + duration), empId);
}

function onDragEnd() { draggedAppt.value = null; dragOverCell.value = null; }
function onDragOver(empId: number | null, date: string, e: DragEvent) { e.preventDefault(); dragOverCell.value = { empId, date }; }
function isDragOver(empId: number | null, date: string) { return dragOverCell.value?.empId === empId && dragOverCell.value?.date === date; }

function handleDblClick(empId: number | null, date: string, e: MouseEvent) {
    const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();
    const relY = e.clientY - rect.top;
    const minutes = Math.round((relY / HOUR_HEIGHT * 60 + props.startHour * 60) / 15) * 15;
    emit('createAppointment', date, fmtMin(minutes), fmtMin(minutes + 60), empId);
}
</script>

<template>
    <div class="h-full overflow-auto select-none">
        <div class="flex flex-col min-w-full">
            <!-- Header -->
            <div class="sticky top-0 z-20 flex border-b bg-background">
                <div class="sticky left-0 z-30 w-28 min-w-[7rem] shrink-0 border-r bg-background p-2 text-xs font-medium text-muted-foreground md:w-48 md:min-w-[12rem] md:text-sm">
                    {{ t('Employee') }}
                </div>
                <div class="w-14 shrink-0 border-r bg-background" />
                <div v-for="date in dates" :key="date"
                     class="flex-1 min-w-[10rem] border-r p-2 text-center text-xs font-medium text-muted-foreground md:text-sm last:border-r-0"
                     :class="date === today ? 'bg-navy-wash/50 text-navy' : ''">
                    {{ formatDayHeader(date) }}
                </div>
            </div>

            <!-- Employee rows -->
            <div v-for="row in allRows" :key="row.id ?? 'unassigned'" class="flex border-b" :class="row.unassigned ? 'bg-muted/30' : ''">
                <!-- Employee name (sticky left) -->
                <div class="sticky left-0 z-10 w-28 min-w-[7rem] shrink-0 border-r p-2 text-xs font-medium md:w-48 md:min-w-[12rem] md:text-sm"
                     :class="row.unassigned ? 'bg-muted italic text-muted-foreground' : 'bg-background'">
                    {{ row.name }}
                </div>
                <!-- Time gutter -->
                <div class="w-14 shrink-0 border-r relative bg-background" :style="{ height: `${cellHeight}px` }">
                    <template v-for="hour in hours" :key="hour">
                        <div v-if="hour !== startHour" class="absolute right-2 text-[11px] text-muted-foreground leading-none"
                             :style="{ top: `${(hour - startHour) * HOUR_HEIGHT - 6}px` }">
                            {{ String(hour).padStart(2, '0') }}:00
                        </div>
                    </template>
                </div>
                <!-- Day columns -->
                <div v-for="date in dates" :key="date"
                     class="flex-1 min-w-[10rem] border-r last:border-r-0 relative"
                     :style="{ height: `${cellHeight}px` }"
                     :class="[isDragOver(row.id, date) ? 'bg-primary/5' : '', date === today ? 'bg-navy-wash/20' : '']"
                     @dragover="onDragOver(row.id, date, $event)"
                     @dragleave="dragOverCell = null"
                     @drop="onDrop(row.id, date, $event)"
                     @dblclick="handleDblClick(row.id, date, $event)">
                    <!-- Hour lines -->
                    <div v-for="hour in hours" :key="hour"
                         class="absolute left-0 right-0 border-b"
                         :class="hour === startHour ? 'border-transparent' : 'border-border/30'"
                         :style="{ top: `${(hour - startHour) * HOUR_HEIGHT}px` }" />
                    <!-- Appointments -->
                    <AppointmentHoverCard v-for="appt in cellAppts(row.id, date)" :key="appt.id" :appointment="appt">
                        <div class="absolute rounded-md border-l-[4px] px-2 py-1 overflow-hidden cursor-pointer transition-shadow hover:shadow-md"
                             :class="[
                                 isConflicted(appt) ? 'ring-2 ring-inset ring-danger' : '',
                                 appt.workers.length === 0 ? 'outline-dashed outline-2 outline-offset-[-3px] outline-navy-edge' : '',
                             ]"
                             :style="getApptCardStyle(appt)"
                             :data-appointment-id="appt.id"
                             :data-conflict="isConflicted(appt) ? 'true' : undefined"
                             draggable="true"
                             @dragstart="onDragStart(appt, $event)"
                             @dragend="onDragEnd"
                             @click.stop="emit('appointmentClick', appt)">
                            <div class="flex items-center gap-1">
                                <span class="font-medium text-xs truncate text-foreground">{{ appointmentClientName(appt) || appointmentService(appt) }}</span>
                                <RotateCw v-if="isRecurring(appt)" class="h-2.5 w-2.5 shrink-0 text-muted-foreground" data-testid="recurring-marker" />
                            </div>
                            <div class="text-[11px] text-muted-foreground truncate">{{ appointmentService(appt) }}</div>
                            <div class="text-[11px] text-muted-foreground truncate">{{ getTimeLabel(appt) }}</div>
                        </div>
                    </AppointmentHoverCard>
                </div>
            </div>
        </div>
    </div>
</template>
