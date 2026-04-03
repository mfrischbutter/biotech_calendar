<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import type { FormDataConvertible } from '@inertiajs/core';
import { format, parseISO, addWeeks, subWeeks, addDays, subDays, addMonths, subMonths, startOfWeek } from 'date-fns';
import { de } from 'date-fns/locale';
import { Button } from '@/Components/ui/button';
import { getTagDotStyle } from '@/lib/tag-colors';
import { localToISO } from '@/lib/date-utils';
import { useTrans } from '@/lib/use-trans';
import type { Appointment, Tag } from '@/types';
import TimeGrid from './partials/TimeGrid.vue';
import MonthGrid from './partials/MonthGrid.vue';
import AppointmentFormDialog from './partials/AppointmentFormDialog.vue';

const { t } = useTrans();

type CalendarView = 'day' | 'week' | 'month';

const props = defineProps<{
    appointments: Appointment[];
    clients: { id: number; name: string }[];
    employees: { id: number; name: string }[];
    tags: Tag[];
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

let dialogCloseGuard = false;
watch(editDialogOpen, (val) => {
    if (!val) {
        dialogCloseGuard = true;
        setTimeout(() => { dialogCloseGuard = false; }, 300);
    }
}, { flush: 'sync' });

const currentDateObj = computed(() => parseISO(props.currentDate));

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
    const startStr = format(weekStart, 'd. MMM', { locale: de });
    const endStr = format(weekEnd, 'd. MMM yyyy', { locale: de });
    return `${startStr} – ${endStr}`;
});

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
    if (dialogCloseGuard) return;
    selectedAppointment.value = appointment;
    editDialogOpen.value = true;
}

function handleCreateAppointment(date: string, startTime: string, endTime: string) {
    openCreateDialog(date, startTime, endTime);
}

function buildAppointmentPayload(appointment: Appointment, overrides: Record<string, unknown>) {
    return {
        title: appointment.title,
        client_id: appointment.client?.id ?? null,
        employee_id: appointment.employee?.id ?? null,
        tag_id: appointment.tag?.id ?? null,
        notes: appointment.notes,
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

const viewLabels: Record<CalendarView, string> = {
    day: t('Day'),
    week: t('Week'),
    month: t('Month'),
};
</script>

<template>
    <Head :title="t('Calendar')" />

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
                    <Button variant="outline" size="sm" @click="goToToday">{{ t('Today') }}</Button>
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
                        {{ t('New Appointment') }}
                    </Button>
                </div>
            </div>
        </template>

        <!-- Legend (only on time grid views) -->
        <div v-if="view !== 'month' && tags.length > 0" class="mb-3 flex flex-wrap items-center gap-4">
            <div
                v-for="tag in tags"
                :key="tag.id"
                class="flex items-center gap-1.5 text-xs text-muted-foreground"
            >
                <span class="h-2 w-2 rounded-full" :style="getTagDotStyle(tag.color)" />
                {{ tag.name }}
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
            :tags="tags"
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
            :tags="tags"
            :appointment="selectedAppointment"
        />
    </AuthenticatedLayout>
</template>
