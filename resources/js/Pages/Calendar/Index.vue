<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { format, parseISO, addDays, startOfWeek } from 'date-fns';
import { de } from 'date-fns/locale';
import { useTrans } from '@/lib/use-trans';
import {
    useCalendarQuery,
    CALENDAR_DENSITY_STORAGE_KEY,
    CALENDAR_VIEW_STORAGE_KEY,
} from '@/lib/use-calendar-query';
import { calendarBoardRows } from '@/lib/calendar-rows';
import { wantsCreateForm } from '@/lib/create-intent';
import { useAppointmentDialogs } from '@/lib/use-appointment-dialogs';
import { useCalendarAppointments } from '@/lib/use-calendar-appointments';
import { useCalendarShortcuts } from '@/lib/use-calendar-shortcuts';
import { usePageLoading } from '@/lib/use-page-loading';
import { dismissPendingUndos } from '@/lib/use-toast';
import type {
    Appointment,
    CalendarDensity,
    CalendarEmployee,
    CalendarFilters,
    CalendarTotals,
    CalendarCreateDefaults,
    CalendarView,
    ChecklistTemplate,
    ClientOption,
    ConflictMap,
    Contract,
    Status,
} from '@/types';
import CalendarToolbar from './partials/CalendarToolbar.vue';
import CalendarFilterRail from './partials/CalendarFilterRail.vue';
import CalendarSkeleton from './partials/CalendarSkeleton.vue';
import ShortcutHelpDialog from './partials/ShortcutHelpDialog.vue';
import TimeGrid from './partials/TimeGrid.vue';
import MonthGrid from './partials/MonthGrid.vue';
import TeamGrid from './partials/TeamGrid.vue';
import TeamWeekDetailGrid from './partials/TeamWeekDetailGrid.vue';
import AppointmentFormDialog from './partials/AppointmentFormDialog.vue';

const { t } = useTrans();

const props = defineProps<{
    appointments: Appointment[];
    contracts: Contract[];
    clients: ClientOption[];
    checklistTemplates: ChecklistTemplate[];
    employees: CalendarEmployee[];
    statuses: Status[];
    currentDate: string;
    view: CalendarView;
    density: CalendarDensity | null;
    showWeekends: boolean;
    startHour: number;
    endHour: number;
    openAppointmentId?: number | null;
    /** What a `?new=1` link brought along, or null when this is a plain visit. */
    createDefaults: CalendarCreateDefaults | null;
    filters: CalendarFilters;
    conflicts: ConflictMap;
    conflictCount: number;
    totals: CalendarTotals;
}>();

// The URL wins when it says something; otherwise this user's last choice does.
const density = ref<CalendarDensity>(
    props.density ?? (localStorage.getItem(CALENDAR_DENSITY_STORAGE_KEY) === 'true' ? 'detailed' : 'compact'),
);
watch(() => props.density, (value) => {
    if (value) density.value = value;
});

const query = useCalendarQuery({
    view: () => props.view,
    date: () => props.currentDate,
    filters: () => props.filters,
    density: () => density.value,
});
const mutations = useCalendarAppointments({
    appointments: () => props.appointments,
    employees: () => props.employees,
});
const loading = usePageLoading();

/** What the grids draw: server truth, plus any drag the server has not answered yet. */
const shownAppointments = computed(() => props.appointments.map(mutations.effective));

/** Which people the team board draws a row for under the current filter. */
const board = computed(() => calendarBoardRows(props.employees, props.filters));

// ---------------------------------------------------------------------------
// Dialogs and keyboard — every shortcut mirrors a button visible on screen
// ---------------------------------------------------------------------------
const helpOpen = ref(false);
const dialogs = useAppointmentDialogs({
    appointments: () => props.appointments,
    fallbackDate: () => props.currentDate,
});

useCalendarShortcuts(
    {
        today: () => query.goToToday(),
        navigate: (direction) => query.navigate(direction),
        switchView: (view) => query.switchView(view),
        create: () => dialogs.openCreate(),
        toggleHelp: () => { helpOpen.value = !helpOpen.value; },
    },
    () => !dialogs.anyOpen.value && !helpOpen.value,
);

// An undo offer only means anything on the board it was raised from.
onUnmounted(() => dismissPendingUndos());

// ---------------------------------------------------------------------------
// Grid geometry
// ---------------------------------------------------------------------------
const currentDateObj = computed(() => parseISO(props.currentDate));
const isTeamView = computed(() => props.view === 'team-week');
const isDetailed = computed(() => density.value === 'detailed');

const gridDates = computed(() => {
    if (props.view === 'day') {
        return [props.currentDate];
    }
    const monday = startOfWeek(currentDateObj.value, { weekStartsOn: 1 });
    const count = props.showWeekends ? 7 : 5;
    return Array.from({ length: count }, (_, i) => format(addDays(monday, i), 'yyyy-MM-dd'));
});

const headerTitle = computed(() => {
    const d = currentDateObj.value;
    if (props.view === 'day') {
        return format(d, 'EEEE, d. MMMM yyyy', { locale: de });
    }
    if (props.view === 'month') {
        return format(d, 'MMMM yyyy', { locale: de });
    }
    const weekStart = startOfWeek(d, { weekStartsOn: 1 });
    const weekEnd = addDays(weekStart, props.showWeekends ? 6 : 4);
    return `${format(weekStart, 'd. MMM', { locale: de })} – ${format(weekEnd, 'd. MMM yyyy', { locale: de })}`;
});

function toggleDetailed() {
    density.value = isDetailed.value ? 'compact' : 'detailed';
    query.setDensity(density.value);
}

const KNOWN_VIEWS: CalendarView[] = ['day', 'week', 'month', 'team-week'];

onMounted(() => {
    // The team board is the default, but a deliberate choice outlives it.
    const params = new URLSearchParams(window.location.search);
    if (!params.has('view')) {
        const saved = localStorage.getItem(CALENDAR_VIEW_STORAGE_KEY) as CalendarView | null;
        if (saved && saved !== props.view && KNOWN_VIEWS.includes(saved)) {
            router.get(route('calendar.index'), { view: saved, date: props.currentDate }, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }
    }

    if (props.openAppointmentId) {
        const appt = props.appointments.find(a => a.id === props.openAppointmentId);
        if (appt) dialogs.openEdit(appt);
    }

    // "Termin planen" on a detail page (and the global search action) links here
    // with ?new=1, and names the job or at least the customer it is for.
    if (wantsCreateForm()) {
        dialogs.openCreateFor({
            contractId: props.createDefaults?.contractId,
            clientName: props.createDefaults?.clientName,
        });
    }
});

function handleDayClick(date: string) {
    query.switchView('day', date);
}
</script>

<template>
    <Head :title="t('Calendar')" />

    <AuthenticatedLayout>
        <template #header>
            <CalendarToolbar
                :view="view"
                :title="headerTitle"
                :conflict-count="conflictCount"
                :conflicts-active="filters.conflicts"
                :detailed="isDetailed"
                @navigate="query.navigate"
                @today="query.goToToday"
                @switch-view="query.switchView"
                @toggle-detailed="toggleDetailed"
                @toggle-conflicts="query.toggleConflicts"
                @create="dialogs.openCreate()"
                @help="helpOpen = true"
            />
        </template>

        <CalendarFilterRail
            :employees="employees"
            :statuses="statuses"
            :filters="query.filters.value"
            :has-filters="query.hasFilters.value"
            @toggle-employee="query.toggleEmployee"
            @toggle-status="query.toggleStatus"
            @toggle-unassigned="query.toggleUnassigned"
            @clear-employees="query.clearEmployees"
            @clear-statuses="query.clearStatuses"
            @reset="query.resetFilters"
        />

        <div class="relative h-[calc(100vh-300px)] md:h-[calc(100vh-240px)]">
            <!-- Laid over the board rather than replacing it, so the grid keeps
                 its scroll position across a week step. -->
            <CalendarSkeleton
                v-if="loading"
                class="absolute inset-0 z-40"
                :columns="gridDates.length"
            />

            <TeamWeekDetailGrid
                v-if="isTeamView && isDetailed"
                :appointments="shownAppointments"
                :employees="board.employees"
                :show-unassigned="board.showUnassigned"
                :dates="gridDates"
                :start-hour="startHour"
                :end-hour="endHour"
                :conflicts="conflicts"
                @appointment-click="dialogs.openEdit"
                @create-appointment="dialogs.openCreateForSlot"
                @move-appointment="mutations.teamMove"
            />

            <TeamGrid
                v-else-if="isTeamView"
                :appointments="shownAppointments"
                :employees="board.employees"
                :show-unassigned="board.showUnassigned"
                :dates="gridDates"
                :totals="totals"
                :conflicts="conflicts"
                @appointment-click="dialogs.openEdit"
                @create-appointment="dialogs.openCreateForSlot"
                @move-appointment="mutations.teamMove"
            />

            <TimeGrid
                v-else-if="view === 'day' || view === 'week'"
                :appointments="shownAppointments"
                :dates="gridDates"
                :start-hour="startHour"
                :end-hour="endHour"
                :conflicts="conflicts"
                @appointment-click="dialogs.openEdit"
                @create-appointment="dialogs.openCreateForSlot"
                @move-appointment="mutations.move"
                @resize-appointment="mutations.resize"
                @expand-day="handleDayClick"
            />

            <MonthGrid
                v-else
                :appointments="shownAppointments"
                :current-date="currentDate"
                :show-weekends="showWeekends"
                :day-totals="totals.perDay"
                :conflicts="conflicts"
                @appointment-click="dialogs.openEdit"
                @day-click="handleDayClick"
            />
        </div>

        <ShortcutHelpDialog v-model:open="helpOpen" />

        <AppointmentFormDialog
            v-model:open="dialogs.createOpen.value"
            :contracts="contracts"
            :clients="clients"
            :employees="employees"
            :statuses="statuses"
            :checklist-templates="checklistTemplates"
            :default-date="dialogs.defaults.value.date"
            :default-start-time="dialogs.defaults.value.startTime"
            :default-end-time="dialogs.defaults.value.endTime"
            :default-worker-ids="dialogs.defaults.value.workerIds"
            :default-contract-id="dialogs.defaults.value.contractId"
            :default-client-name="dialogs.defaults.value.clientName"
        />

        <AppointmentFormDialog
            v-if="dialogs.selected.value"
            v-model:open="dialogs.editOpen.value"
            :contracts="contracts"
            :clients="clients"
            :employees="employees"
            :statuses="statuses"
            :checklist-templates="checklistTemplates"
            :appointment="dialogs.selected.value"
        />
    </AuthenticatedLayout>
</template>
