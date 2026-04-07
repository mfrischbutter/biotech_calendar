<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import type { FormDataConvertible } from '@inertiajs/core';
import { format, parseISO, addWeeks, subWeeks, addDays, subDays, addMonths, subMonths, startOfWeek } from 'date-fns';
import { de } from 'date-fns/locale';
import { Button } from '@/Components/ui/button';
import { getStatusDotStyle } from '@/lib/status-colors';
import { localToISO } from '@/lib/date-utils';
import { useTrans } from '@/lib/use-trans';
import type { Appointment, Status } from '@/types';
import TimeGrid from './partials/TimeGrid.vue';
import MonthGrid from './partials/MonthGrid.vue';
import TeamGrid from './partials/TeamGrid.vue';
import AppointmentFormDialog from './partials/AppointmentFormDialog.vue';

const { t } = useTrans();

type CalendarView = 'day' | 'week' | 'month' | 'team-day' | 'team-week';

const props = defineProps<{
    appointments: Appointment[];
    clients: { id: number; first_name: string; last_name: string; company_name: string | null; name: string; street: string | null; zip: string | null; city: string | null }[];
    employees: { id: number; first_name: string; last_name: string; name: string }[];
    statuses: Status[];
    currentDate: string;
    view: CalendarView;
    showWeekends: boolean;
    startHour: number;
    endHour: number;
}>();

const createDialogOpen = ref(false);
const editDialogOpen = ref(false);
const selectedAppointment = ref<Appointment | undefined>();
const defaultDate = ref('');
const defaultStartTime = ref('');
const defaultEndTime = ref('');

let drawerCloseGuard = false;
watch(editDialogOpen, (val) => {
    if (!val) {
        drawerCloseGuard = true;
        setTimeout(() => { drawerCloseGuard = false; }, 300);
    }
}, { flush: 'sync' });

const currentDateObj = computed(() => parseISO(props.currentDate));

const gridDates = computed(() => {
    if (props.view === 'day' || props.view === 'team-day') {
        return [props.currentDate];
    }
    const monday = startOfWeek(currentDateObj.value, { weekStartsOn: 1 });
    const count = props.showWeekends ? 7 : 5;
    return Array.from({ length: count }, (_, i) => format(addDays(monday, i), 'yyyy-MM-dd'));
});

const headerTitle = computed(() => {
    const d = currentDateObj.value;
    if (props.view === 'day' || props.view === 'team-day') {
        return format(d, 'EEEE, d. MMMM yyyy', { locale: de });
    }
    if (props.view === 'month') {
        return format(d, 'MMMM yyyy', { locale: de });
    }
    const weekStart = startOfWeek(d, { weekStartsOn: 1 });
    const weekEnd = addDays(weekStart, props.showWeekends ? 6 : 4);
    const startStr = format(weekStart, 'd. MMM', { locale: de });
    const endStr = format(weekEnd, 'd. MMM yyyy', { locale: de });
    return `${startStr} – ${endStr}`;
});

function navigate(direction: number) {
    const d = currentDateObj.value;
    let newDate: Date;
    if (props.view === 'day' || props.view === 'team-day') {
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

const STORAGE_KEY = 'biotech-calendar-view';

function switchView(view: CalendarView) {
    localStorage.setItem(STORAGE_KEY, view);
    router.get(route('calendar.index'), {
        view,
        date: props.currentDate,
    }, { preserveState: true, preserveScroll: true });
}

onMounted(() => {
    // Restore saved view on initial load (no explicit ?view= in URL)
    const params = new URLSearchParams(window.location.search);
    if (!params.has('view')) {
        const saved = localStorage.getItem(STORAGE_KEY) as CalendarView | null;
        if (saved && saved !== props.view) {
            router.get(route('calendar.index'), {
                view: saved,
                date: props.currentDate,
            }, { preserveState: true, preserveScroll: true, replace: true });
        }
    }
});

function handleDayClick(date: string) {
    router.get(route('calendar.index'), {
        view: 'day',
        date,
    }, { preserveState: true, preserveScroll: true });
}

function openCreateDialog(date?: string, startTime?: string, endTime?: string) {
    defaultDate.value = date || props.currentDate;
    defaultStartTime.value = startTime || '09:00';
    defaultEndTime.value = endTime || '10:00';
    selectedAppointment.value = undefined;
    createDialogOpen.value = true;
}

function openEditDialog(appointment: Appointment) {
    if (drawerCloseGuard) return;
    selectedAppointment.value = appointment;
    editDialogOpen.value = true;
}

const defaultEmployeeId = ref<number | null>(null);

function handleCreateAppointment(date: string, startTime: string, endTime: string, employeeId?: number | null) {
    defaultEmployeeId.value = employeeId ?? null;
    openCreateDialog(date, startTime, endTime);
}

function buildAppointmentPayload(appointment: Appointment, overrides: Record<string, unknown>) {
    return {
        title: appointment.title,
        client_id: appointment.client?.id ?? null,
        employee_id: appointment.employee?.id ?? null,
        status_id: appointment.status?.id ?? null,
        kind: appointment.kind,
        notes: appointment.notes,
        street: appointment.street,
        zip: appointment.zip,
        city: appointment.city,
        checklist: appointment.checklist,
        ...overrides,
    } as Record<string, FormDataConvertible>;
}

function handleMoveAppointment(appointment: Appointment, date: string, startTime: string, endTime: string) {
    router.put(route('appointments.update', appointment.id), buildAppointmentPayload(appointment, {
        start_at: localToISO(date, startTime),
        end_at: localToISO(date, endTime),
    }), { preserveScroll: true });
}

function handleResizeAppointment(appointment: Appointment, endTime: string) {
    const date = new Date(appointment.start_at);
    const dateStr = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    router.put(route('appointments.update', appointment.id), buildAppointmentPayload(appointment, {
        start_at: appointment.start_at,
        end_at: localToISO(dateStr, endTime),
    }), { preserveScroll: true });
}

function handleTeamMoveAppointment(appointment: Appointment, date: string, startTime: string, endTime: string, employeeId: number | null) {
    router.put(route('appointments.update', appointment.id), buildAppointmentPayload(appointment, {
        start_at: localToISO(date, startTime),
        end_at: localToISO(date, endTime),
        employee_id: employeeId,
    }), { preserveScroll: true });
}

const isTeamView = computed(() => props.view === 'team-day' || props.view === 'team-week');
const teamSubView = computed(() => props.view === 'team-day' ? 'day' : 'week');

const mainViews: { key: CalendarView; label: string }[] = [
    { key: 'day', label: t('Day') },
    { key: 'week', label: t('Week') },
    { key: 'month', label: t('Month') },
];

function switchToTeam() {
    switchView(isTeamView.value ? 'week' : 'team-week');
}

function switchTeamSub(sub: 'day' | 'week') {
    switchView(sub === 'day' ? 'team-day' : 'team-week');
}
</script>

<template>
    <Head :title="t('Calendar')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <!-- Row 1: nav + title -->
                <div class="flex items-center gap-2 md:gap-4">
                    <div class="flex items-center gap-1">
                        <Button variant="outline" size="icon" class="h-8 w-8" @click="navigate(-1)">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6" /></svg>
                        </Button>
                        <Button variant="outline" size="icon" class="h-8 w-8" @click="navigate(1)">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6" /></svg>
                        </Button>
                    </div>
                    <Button variant="outline" size="sm" @click="goToToday">{{ t('Today') }}</Button>
                    <h2 class="text-sm font-semibold text-foreground md:text-lg">
                        {{ headerTitle }}
                    </h2>
                </div>

                <!-- Row 2: view switcher + actions -->
                <div class="flex items-center gap-2 md:gap-3">
                    <!-- View switcher -->
                    <div class="flex rounded-md border overflow-hidden">
                        <button
                            v-for="v in mainViews"
                            :key="v.key"
                            class="px-2 py-1 text-xs font-medium transition-colors md:px-3 md:py-1.5 md:text-sm"
                            :class="view === v.key
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted'"
                            @click="switchView(v.key)"
                        >
                            {{ v.label }}
                        </button>
                    </div>

                    <!-- Team view toggle -->
                    <button
                        class="rounded-md border px-2 py-1 text-xs font-medium transition-colors md:px-3 md:py-1.5 md:text-sm"
                        :class="isTeamView
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted'"
                        @click="switchToTeam"
                    >
                        {{ t('Team') }}
                    </button>

                    <!-- Team sub-toggle (day/week) -->
                    <div v-if="isTeamView" class="flex rounded-md border overflow-hidden">
                        <button
                            class="px-2 py-1 text-xs font-medium transition-colors md:px-2.5 md:py-1.5"
                            :class="teamSubView === 'day' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'"
                            @click="switchTeamSub('day')"
                        >
                            {{ t('Day') }}
                        </button>
                        <button
                            class="px-2 py-1 text-xs font-medium transition-colors md:px-2.5 md:py-1.5"
                            :class="teamSubView === 'week' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'"
                            @click="switchTeamSub('week')"
                        >
                            {{ t('Week') }}
                        </button>
                    </div>

                    <Button size="sm" class="ml-auto md:ml-0 md:size-default" @click="openCreateDialog()">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 md:mr-1.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <span class="hidden md:inline">{{ t('New Appointment') }}</span>
                    </Button>
                </div>
            </div>
        </template>

        <!-- Legend (only on time grid / team views) -->
        <div v-if="view !== 'month' && statuses.length > 0" class="mb-2 flex flex-wrap items-center gap-2 md:mb-3 md:gap-4">
            <div
                v-for="status in statuses"
                :key="status.id"
                class="flex items-center gap-1.5 text-xs text-muted-foreground"
            >
                <span class="h-2 w-2 rounded-full" :style="getStatusDotStyle(status.color)" />
                {{ status.name }}
            </div>
        </div>

        <!-- Calendar content -->
        <div class="h-[calc(100vh-260px)] md:h-[calc(100vh-200px)]">
            <!-- Team view -->
            <TeamGrid
                v-if="isTeamView"
                :appointments="appointments"
                :employees="employees"
                :dates="gridDates"
                :view="view as 'team-day' | 'team-week'"
                :start-hour="startHour"
                :end-hour="endHour"
                @appointment-click="openEditDialog"
                @create-appointment="handleCreateAppointment"
                @move-appointment="handleTeamMoveAppointment"
            />

            <!-- Day / Week view -->
            <TimeGrid
                v-else-if="view === 'day' || view === 'week'"
                :appointments="appointments"
                :dates="gridDates"
                :show-day-header="view === 'week' || view === 'day'"
                :start-hour="startHour"
                :end-hour="endHour"
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
            :statuses="statuses"
            :default-date="defaultDate"
            :default-start-time="defaultStartTime"
            :default-end-time="defaultEndTime"
            :default-employee-id="defaultEmployeeId"
        />

        <!-- Edit dialog -->
        <AppointmentFormDialog
            v-if="selectedAppointment"
            v-model:open="editDialogOpen"
            :clients="clients"
            :employees="employees"
            :statuses="statuses"
            :appointment="selectedAppointment"
        />
    </AuthenticatedLayout>
</template>
