<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { format, parseISO, addWeeks, subWeeks, addDays, subDays, addMonths, subMonths, startOfWeek, endOfWeek, startOfMonth, endOfMonth } from 'date-fns';
import { de } from 'date-fns/locale';
import { Button } from '@/Components/ui/button';
import { appointmentTypes } from '@/lib/appointment-types';
import { localToISO } from '@/lib/use-calendar-drag';
import type { Appointment } from '@/types';
import TimeGrid from './partials/TimeGrid.vue';
import MonthGrid from './partials/MonthGrid.vue';
import AppointmentFormDialog from './partials/AppointmentFormDialog.vue';

type CalendarView = 'day' | 'week' | 'month';

const props = defineProps<{
    appointments: Appointment[];
    clients: { id: number; name: string }[];
    employees: { id: number; name: string }[];
    currentDate: string;
    view: CalendarView;
    showWeekends: boolean;
}>();

const createDialogOpen = ref(false);
const editDialogOpen = ref(false);
const selectedAppointment = ref<Appointment | undefined>();
const defaultDate = ref('');
const defaultStartTime = ref('');
const defaultEndTime = ref('');

// Guard against click-through: when dialog overlay is clicked to close,
// the click can pass through to the appointment card underneath and reopen it.
// flush:'sync' ensures the guard is set immediately, before the click event fires.
let dialogCloseGuard = false;
watch(editDialogOpen, (val) => {
    if (!val) {
        dialogCloseGuard = true;
        setTimeout(() => { dialogCloseGuard = false; }, 300);
    }
}, { flush: 'sync' });

const currentDateObj = computed(() => parseISO(props.currentDate));

// View-dependent dates for the time grid
const gridDates = computed(() => {
    if (props.view === 'day') {
        return [props.currentDate];
    }
    // week
    const monday = startOfWeek(currentDateObj.value, { weekStartsOn: 1 });
    const count = props.showWeekends ? 7 : 5;
    return Array.from({ length: count }, (_, i) => format(addDays(monday, i), 'yyyy-MM-dd'));
});

// Header title
const headerTitle = computed(() => {
    const d = currentDateObj.value;
    if (props.view === 'day') {
        return format(d, 'EEEE, d. MMMM yyyy', { locale: de });
    }
    if (props.view === 'month') {
        return format(d, 'MMMM yyyy', { locale: de });
    }
    // week
    const weekStart = startOfWeek(d, { weekStartsOn: 1 });
    const weekEnd = addDays(weekStart, props.showWeekends ? 6 : 4);
    const startStr = format(weekStart, 'd. MMM', { locale: de });
    const endStr = format(weekEnd, 'd. MMM yyyy', { locale: de });
    return `${startStr} – ${endStr}`;
});

// Navigation
function navigate(direction: number) {
    const d = currentDateObj.value;
    let newDate: Date;
    if (props.view === 'day') {
        newDate = direction > 0 ? addDays(d, 1) : subDays(d, 1);
    } else if (props.view === 'month') {
        newDate = direction > 0 ? addMonths(d, 1) : subMonths(d, 1);
    } else {
        newDate = direction > 0 ? addWeeks(d, 1) : subWeeks(d, 1);
    }
    router.get(route('calendar.index'), {
        view: props.view,
        date: format(newDate, 'yyyy-MM-dd'),
    }, { preserveState: true, preserveScroll: true });
}

function goToToday() {
    router.get(route('calendar.index'), {
        view: props.view,
    }, { preserveState: true, preserveScroll: true });
}

function switchView(view: CalendarView) {
    router.get(route('calendar.index'), {
        view,
        date: props.currentDate,
    }, { preserveState: true, preserveScroll: true });
}

// Month grid → day click switches to day view
function handleDayClick(date: string) {
    router.get(route('calendar.index'), {
        view: 'day',
        date,
    }, { preserveState: true, preserveScroll: true });
}

// Create / Edit
function openCreateDialog(date?: string, startTime?: string, endTime?: string) {
    defaultDate.value = date || props.currentDate;
    defaultStartTime.value = startTime || '09:00';
    defaultEndTime.value = endTime || '10:00';
    selectedAppointment.value = undefined;
    createDialogOpen.value = true;
}

function openEditDialog(appointment: Appointment) {
    if (dialogCloseGuard) return;
    selectedAppointment.value = appointment;
    editDialogOpen.value = true;
}

// Drag-to-create
function handleCreateAppointment(date: string, startTime: string, endTime: string) {
    openCreateDialog(date, startTime, endTime);
}

// Drag-to-move — convert local times to UTC ISO before sending
function handleMoveAppointment(appointment: Appointment, date: string, startTime: string, endTime: string) {
    router.put(route('appointments.update', appointment.id), {
        title: appointment.title,
        client_id: appointment.client?.id ?? null,
        employee_id: appointment.employee?.id ?? null,
        start_at: localToISO(date, startTime),
        end_at: localToISO(date, endTime),
        type: appointment.type,
        notes: appointment.notes,
    }, { preserveScroll: true });
}

// Drag-to-resize — convert local end time to UTC ISO
function handleResizeAppointment(appointment: Appointment, endTime: string) {
    const date = new Date(appointment.start_at);
    const dateStr = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    router.put(route('appointments.update', appointment.id), {
        title: appointment.title,
        client_id: appointment.client?.id ?? null,
        employee_id: appointment.employee?.id ?? null,
        start_at: appointment.start_at,
        end_at: localToISO(dateStr, endTime),
        type: appointment.type,
        notes: appointment.notes,
    }, { preserveScroll: true });
}

const viewLabels: Record<CalendarView, string> = {
    day: 'Tag',
    week: 'Woche',
    month: 'Monat',
};
</script>

<template>
    <Head title="Kalender" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <!-- Navigation -->
                    <div class="flex items-center gap-1">
                        <Button variant="outline" size="icon" class="h-8 w-8" @click="navigate(-1)">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6" /></svg>
                        </Button>
                        <Button variant="outline" size="icon" class="h-8 w-8" @click="navigate(1)">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6" /></svg>
                        </Button>
                    </div>
                    <Button variant="outline" size="sm" @click="goToToday">Heute</Button>
                    <h2 class="text-lg font-semibold text-foreground">
                        {{ headerTitle }}
                    </h2>
                </div>

                <div class="flex items-center gap-3">
                    <!-- View switcher -->
                    <div class="flex rounded-md border overflow-hidden">
                        <button
                            v-for="v in (['day', 'week', 'month'] as CalendarView[])"
                            :key="v"
                            class="px-3 py-1.5 text-sm font-medium transition-colors"
                            :class="view === v
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted'"
                            @click="switchView(v)"
                        >
                            {{ viewLabels[v] }}
                        </button>
                    </div>

                    <Button @click="openCreateDialog()">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Neuer Termin
                    </Button>
                </div>
            </div>
        </template>

        <!-- Legend (only on time grid views) -->
        <div v-if="view !== 'month'" class="mb-3 flex flex-wrap items-center gap-4">
            <div
                v-for="(config, key) in appointmentTypes"
                :key="key"
                class="flex items-center gap-1.5 text-xs text-muted-foreground"
            >
                <span class="h-2 w-2 rounded-full" :class="config.dotColor" />
                {{ config.label }}
            </div>
        </div>

        <!-- Calendar content -->
        <div class="h-[calc(100vh-200px)]">
            <!-- Day / Week view -->
            <TimeGrid
                v-if="view === 'day' || view === 'week'"
                :appointments="appointments"
                :dates="gridDates"
                :show-day-header="view === 'week' || view === 'day'"
                @appointment-click="openEditDialog"
                @create-appointment="handleCreateAppointment"
                @move-appointment="handleMoveAppointment"
                @resize-appointment="handleResizeAppointment"
            />

            <!-- Month view -->
            <MonthGrid
                v-else
                :appointments="appointments"
                :current-date="currentDate"
                @appointment-click="openEditDialog"
                @day-click="handleDayClick"
            />
        </div>

        <!-- Create dialog -->
        <AppointmentFormDialog
            v-model:open="createDialogOpen"
            :clients="clients"
            :employees="employees"
            :default-date="defaultDate"
            :default-start-time="defaultStartTime"
            :default-end-time="defaultEndTime"
        />

        <!-- Edit dialog -->
        <AppointmentFormDialog
            v-if="selectedAppointment"
            v-model:open="editDialogOpen"
            :clients="clients"
            :employees="employees"
            :appointment="selectedAppointment"
        />
    </AuthenticatedLayout>
</template>
