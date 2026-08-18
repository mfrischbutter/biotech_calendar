<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { format, parseISO } from 'date-fns';
import { de } from 'date-fns/locale';
import { RotateCw } from 'lucide-vue-next';
import type { Appointment, CalendarEmployee, CalendarTotals, ConflictMap } from '@/types';
import { useTrans } from '@/lib/use-trans';
import { appointmentClientName, appointmentService, formatHours, isRecurring } from '@/lib/appointment-label';
import { getStatusStyle } from '@/lib/status-colors';
import AppointmentHoverCard from './AppointmentHoverCard.vue';
import { isoToLocalParts } from '@/lib/date-utils';

const { t } = useTrans();

interface EmployeeRow {
    id: number | null;
    name: string;
    unassigned: boolean;
}

const props = defineProps<{
    appointments: Appointment[];
    /** Only the people the assignee filter left standing. */
    employees: CalendarEmployee[];
    /** Whether the row for work nobody owns belongs on the board. */
    showUnassigned: boolean;
    dates: string[];
    totals: CalendarTotals;
    conflicts?: ConflictMap;
}>();

const emit = defineEmits<{
    appointmentClick: [appointment: Appointment];
    createAppointment: [date: string, startTime: string, endTime: string, employeeId: number | null];
    moveAppointment: [appointment: Appointment, date: string, startTime: string, endTime: string, employeeId: number | null];
}>();

const today = format(new Date(), 'yyyy-MM-dd');

const allRows = computed<EmployeeRow[]>(() => [
    ...(props.showUnassigned ? [{ id: null, name: t('Unassigned'), unassigned: true }] : []),
    ...props.employees.map((e) => ({ id: e.id, name: e.name, unassigned: false })),
]);

// An appointment shows up in every assigned technician's row; work nobody owns
// lands in the unassigned row at the top, where it is impossible to miss.
// A worker with no row on this board — filtered out, but sharing a job with
// someone who was not — is skipped rather than given a bucket nobody reads.
const byEmployee = computed(() => {
    const map = new Map<number | null, Appointment[]>();
    map.set(null, []);
    for (const emp of props.employees) map.set(emp.id, []);
    for (const appt of props.appointments) {
        if (appt.workers.length === 0) {
            map.get(null)!.push(appt);
        } else {
            for (const worker of appt.workers) {
                map.get(worker.id)?.push(appt);
            }
        }
    }
    return map;
});

function cellAppts(empId: number | null, date: string): Appointment[] {
    return (byEmployee.value.get(empId) || [])
        .filter((a) => isoToLocalParts(a.start_at).date === date)
        .sort((a, b) => a.start_at.localeCompare(b.start_at));
}

const employeeTotals = computed(() => {
    const map = new Map<number | null, { appointments: number; minutes: number }>();
    for (const row of props.totals.perEmployee) {
        map.set(row.id, { appointments: row.appointments, minutes: row.minutes });
    }
    return map;
});

const dayTotals = computed(() => {
    const map = new Map<string, { appointments: number; minutes: number }>();
    for (const row of props.totals.perDay) {
        map.set(row.date, { appointments: row.appointments, minutes: row.minutes });
    }
    return map;
});

function summary(total?: { appointments: number; minutes: number }): string {
    if (!total || total.appointments === 0) return '–';
    return `${total.appointments} · ${t(':hours h', { hours: formatHours(total.minutes) })}`;
}

function formatDayHeader(date: string): string {
    return format(parseISO(date), 'EEE d. MMM', { locale: de });
}

function getTimeLabel(appt: Appointment): string {
    return `${isoToLocalParts(appt.start_at).time} – ${isoToLocalParts(appt.end_at).time}`;
}

function isConflicted(appt: Appointment): boolean {
    return (props.conflicts?.[appt.id]?.length ?? 0) > 0;
}

// ── Drag and drop: a card keeps its time, only the day and owner change ──
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
    emit('moveAppointment', appt, date, isoToLocalParts(appt.start_at).time, isoToLocalParts(appt.end_at).time, empId);
}
function onDragEnd() {
    draggedAppt.value = null;
    dragOverCell.value = null;
}
function onDragOver(empId: number | null, date: string, e: DragEvent) {
    e.preventDefault();
    dragOverCell.value = { empId, date };
}
function isDragOver(empId: number | null, date: string) {
    return dragOverCell.value?.empId === empId && dragOverCell.value?.date === date;
}

// ── Horizontal scroll affordance: Friday used to run off the edge silently ──
const scroller = ref<HTMLElement | null>(null);
const scrolledToEnd = ref(true);

function updateScrollState() {
    const el = scroller.value;
    if (!el) return;
    scrolledToEnd.value = el.scrollWidth - el.clientWidth - el.scrollLeft <= 1;
}

onMounted(() => {
    updateScrollState();
    window.addEventListener('resize', updateScrollState);
});
onUnmounted(() => window.removeEventListener('resize', updateScrollState));
</script>

<template>
    <div class="relative h-full">
        <div ref="scroller" class="h-full select-none overflow-auto" @scroll="updateScrollState">
            <table class="w-full border-collapse">
                <thead class="sticky top-0 z-20 bg-background">
                    <tr>
                        <th class="sticky left-0 z-30 w-28 min-w-[7rem] border-b border-r bg-background p-2 text-left text-xs font-medium text-muted-foreground md:w-48 md:min-w-[12rem] md:text-sm">
                            {{ t('Employee') }}
                        </th>
                        <th
                            v-for="date in dates"
                            :key="date"
                            class="min-w-[6rem] border-b border-r p-2 text-center text-xs font-medium text-muted-foreground md:min-w-[8rem] md:text-sm"
                            :class="date === today ? 'bg-navy-wash/50 text-navy' : ''"
                        >
                            {{ formatDayHeader(date) }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in allRows" :key="row.id ?? 'unassigned'" class="border-b" :class="row.unassigned ? 'bg-muted/30' : ''">
                        <td
                            class="sticky left-0 z-10 w-28 min-w-[7rem] border-r p-2 align-top text-xs font-medium md:w-48 md:min-w-[12rem] md:text-sm"
                            :class="row.unassigned ? 'bg-muted italic text-muted-foreground' : 'bg-background'"
                        >
                            <div class="truncate">{{ row.name }}</div>
                            <div class="text-[11px] font-normal text-muted-foreground" :data-testid="`row-total-${row.id ?? 'unassigned'}`">
                                {{ summary(employeeTotals.get(row.id)) }}
                            </div>
                        </td>
                        <td
                            v-for="date in dates"
                            :key="date"
                            class="min-w-[6rem] border-r p-1 align-top transition-colors md:min-w-[8rem]"
                            :class="[isDragOver(row.id, date) ? 'bg-primary/10' : '', date === today ? 'bg-navy-wash/20' : '']"
                            @dragover="onDragOver(row.id, date, $event)"
                            @dragleave="dragOverCell = null"
                            @drop="onDrop(row.id, date, $event)"
                            @dblclick="emit('createAppointment', date, '09:00', '10:00', row.id)"
                        >
                            <AppointmentHoverCard v-for="appt in cellAppts(row.id, date)" :key="appt.id" :appointment="appt">
                                <div
                                    class="mb-1 cursor-pointer rounded border px-1.5 py-1 text-xs"
                                    :class="[
                                        isConflicted(appt) ? 'ring-2 ring-inset ring-danger' : '',
                                        appt.workers.length === 0 ? 'outline-dashed outline-2 outline-offset-[-3px] outline-navy-edge' : '',
                                    ]"
                                    :style="getStatusStyle(appt.status?.color ?? null)"
                                    :data-appointment-id="appt.id"
                                    :data-conflict="isConflicted(appt) ? 'true' : undefined"
                                    draggable="true"
                                    @dragstart="onDragStart(appt, $event)"
                                    @dragend="onDragEnd"
                                    @click.stop="emit('appointmentClick', appt)"
                                >
                                    <div class="flex items-center gap-1">
                                        <span class="truncate font-medium text-foreground">
                                            {{ appointmentClientName(appt) || appointmentService(appt) }}
                                        </span>
                                        <RotateCw v-if="isRecurring(appt)" class="h-2.5 w-2.5 shrink-0 text-muted-foreground" data-testid="recurring-marker" />
                                    </div>
                                    <div class="truncate opacity-70" style="font-size: 10px">{{ appointmentService(appt) }}</div>
                                    <div class="opacity-70" style="font-size: 10px">{{ getTimeLabel(appt) }}</div>
                                </div>
                            </AppointmentHoverCard>
                        </td>
                    </tr>
                </tbody>
                <tfoot class="sticky bottom-0 z-20 bg-background">
                    <tr>
                        <th class="sticky left-0 z-30 border-r border-t bg-background p-2 text-left text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                            {{ t('Total') }}
                        </th>
                        <td
                            v-for="date in dates"
                            :key="date"
                            :data-testid="`column-total-${date}`"
                            class="border-r border-t p-2 text-center text-[11px] font-medium text-muted-foreground"
                            :class="date === today ? 'bg-navy-wash/20' : ''"
                        >
                            {{ summary(dayTotals.get(date)) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div
            v-if="!scrolledToEnd"
            data-testid="scroll-affordance"
            class="pointer-events-none absolute inset-y-0 right-0 w-8 bg-gradient-to-l from-background to-transparent"
        />
    </div>
</template>
