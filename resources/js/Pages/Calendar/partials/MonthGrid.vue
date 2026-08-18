<script setup lang="ts">
import { computed } from 'vue';
import { format, parseISO, startOfMonth, endOfMonth, startOfWeek, endOfWeek, addDays, isSameMonth } from 'date-fns';
import { de } from 'date-fns/locale';
import { RotateCw } from 'lucide-vue-next';
import { getStatusStyle, getStatusDotStyle } from '@/lib/status-colors';
import { localDateString } from '@/lib/date-utils';
import { useTrans } from '@/lib/use-trans';
import { appointmentClientName, appointmentService, appointmentWorkerInitials, isRecurring } from '@/lib/appointment-label';
import AppointmentHoverCard from './AppointmentHoverCard.vue';
import type { Appointment, CalendarDayTotal, ConflictMap } from '@/types';

const { t } = useTrans();

interface MonthDay {
    date: string;
    dayNum: string;
    isCurrentMonth: boolean;
    isToday: boolean;
}

const props = defineProps<{
    appointments: Appointment[];
    currentDate: string;
    showWeekends: boolean;
    dayTotals: CalendarDayTotal[];
    conflicts?: ConflictMap;
}>();

const emit = defineEmits<{
    appointmentClick: [appointment: Appointment];
    dayClick: [date: string];
}>();

const MAX_VISIBLE = 3;

const today = format(new Date(), 'yyyy-MM-dd');
const currentMonth = computed(() => parseISO(props.currentDate));
const columnCount = computed(() => (props.showWeekends ? 7 : 5));

const weeks = computed(() => {
    const calStart = startOfWeek(startOfMonth(currentMonth.value), { weekStartsOn: 1 });
    const calEnd = endOfWeek(endOfMonth(currentMonth.value), { weekStartsOn: 1 });

    const result: MonthDay[][] = [];
    let current = calStart;

    while (current <= calEnd) {
        const week: MonthDay[] = [];
        for (let i = 0; i < 7; i++) {
            const dateStr = format(current, 'yyyy-MM-dd');
            if (i < columnCount.value) {
                week.push({
                    date: dateStr,
                    dayNum: format(current, 'd'),
                    isCurrentMonth: isSameMonth(current, currentMonth.value),
                    isToday: dateStr === today,
                });
            }
            current = addDays(current, 1);
        }
        result.push(week);
    }
    return result;
});

/** Localised weekday abbreviations, taken from the grid itself. */
const dayHeaders = computed(() =>
    weeks.value[0].map((day) => format(parseISO(day.date), 'EEEEEE', { locale: de }).toUpperCase()),
);

const appointmentsByDay = computed(() => {
    const byDay: Record<string, Appointment[]> = {};
    for (const appt of props.appointments) {
        const dayKey = localDateString(appt.start_at);
        (byDay[dayKey] ??= []).push(appt);
    }
    return byDay;
});

const loadByDay = computed(() => {
    const minutes: Record<string, number> = {};
    for (const total of props.dayTotals) {
        minutes[total.date] = total.minutes;
    }
    const busiest = Math.max(1, ...Object.values(minutes));

    const load: Record<string, number> = {};
    for (const [date, value] of Object.entries(minutes)) {
        load[date] = value === 0 ? 0 : Math.max(0.2, value / busiest);
    }
    return load;
});

/** Empty weeks give their space to the busy ones instead of padding the month. */
const gridRows = computed(() =>
    weeks.value
        .map((week) => (week.some((day) => (appointmentsByDay.value[day.date]?.length ?? 0) > 0)
            ? 'minmax(88px, auto)'
            : 'minmax(40px, auto)'))
        .join(' '),
);

function isConflicted(appt: Appointment): boolean {
    return (props.conflicts?.[appt.id]?.length ?? 0) > 0;
}

function overflowCount(date: string): number {
    return Math.max(0, (appointmentsByDay.value[date]?.length ?? 0) - MAX_VISIBLE);
}
</script>

<template>
    <div class="flex h-full flex-col overflow-hidden rounded-lg border bg-background">
        <!-- Day headers -->
        <div class="grid shrink-0 border-b bg-background" :style="{ gridTemplateColumns: `repeat(${columnCount}, minmax(0, 1fr))` }">
            <div
                v-for="day in dayHeaders"
                :key="day"
                class="border-r py-2 text-center text-[11px] font-medium tracking-wide text-muted-foreground last:border-r-0"
            >
                {{ day }}
            </div>
        </div>

        <!-- Week rows -->
        <div class="grid flex-1 overflow-y-auto" :style="{ gridTemplateRows: gridRows }">
            <div
                v-for="(week, wi) in weeks"
                :key="wi"
                class="grid border-b last:border-b-0"
                :style="{ gridTemplateColumns: `repeat(${columnCount}, minmax(0, 1fr))` }"
            >
                <div
                    v-for="day in week"
                    :key="day.date"
                    class="relative cursor-pointer overflow-hidden border-r p-1 pb-2 transition-colors last:border-r-0 hover:bg-muted/30"
                    :class="[!day.isCurrentMonth ? 'bg-muted/10' : '', day.isToday ? 'bg-navy-wash/40' : '']"
                    @click="emit('dayClick', day.date)"
                >
                    <div class="mb-0.5 flex justify-end">
                        <span
                            class="inline-flex h-[26px] w-[26px] items-center justify-center text-sm leading-none"
                            :class="day.isToday
                                ? 'rounded-full bg-navy font-medium text-navy-foreground'
                                : day.isCurrentMonth ? 'text-foreground' : 'text-muted-foreground/50'"
                        >
                            {{ day.dayNum }}
                        </span>
                    </div>

                    <div class="space-y-0.5">
                        <AppointmentHoverCard
                            v-for="appt in (appointmentsByDay[day.date] || []).slice(0, MAX_VISIBLE)"
                            :key="appt.id"
                            :appointment="appt"
                        >
                            <div
                                :data-appointment-id="appt.id"
                                :data-conflict="isConflicted(appt) ? 'true' : undefined"
                                class="cursor-pointer rounded px-0.5 py-0.5 text-[10px] leading-tight hover:opacity-80 md:px-1 md:text-[11px]"
                                :class="[
                                    isConflicted(appt) ? 'ring-1 ring-inset ring-danger' : '',
                                    appt.workers.length === 0 ? 'outline-dashed outline-1 outline-offset-[-2px] outline-navy-edge' : '',
                                ]"
                                :style="{ backgroundColor: getStatusStyle(appt.status?.color ?? null).backgroundColor }"
                                @click.stop="emit('appointmentClick', appt)"
                            >
                                <div class="flex items-center gap-1">
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full" :style="getStatusDotStyle(appt.status?.color ?? null)" />
                                    <!-- The customer identifies the job; the service is the detail. -->
                                    <span class="truncate font-medium text-foreground">{{ appointmentClientName(appt) || appointmentService(appt) }}</span>
                                    <RotateCw v-if="isRecurring(appt)" class="h-2.5 w-2.5 shrink-0 text-muted-foreground" data-testid="recurring-marker" />
                                    <span v-if="appt.workers.length > 0" class="ml-auto shrink-0 font-medium text-muted-foreground">
                                        {{ appointmentWorkerInitials(appt).join(' ') }}
                                    </span>
                                </div>
                                <div v-if="appointmentClientName(appt)" class="truncate pl-2.5 text-muted-foreground">
                                    {{ appointmentService(appt) }}
                                </div>
                            </div>
                        </AppointmentHoverCard>

                        <button
                            v-if="overflowCount(day.date) > 0"
                            type="button"
                            data-testid="month-overflow"
                            class="pl-0.5 text-[10px] font-semibold text-navy underline-offset-2 hover:underline md:pl-1"
                            @click.stop="emit('dayClick', day.date)"
                        >
                            +{{ overflowCount(day.date) }} {{ t('more') }}
                        </button>
                    </div>

                    <!-- Load bar: how full the day is, at a glance -->
                    <div
                        v-if="(loadByDay[day.date] ?? 0) > 0"
                        data-testid="day-load"
                        class="absolute bottom-0 left-0 right-0 h-1 bg-primary"
                        :style="{ opacity: String(loadByDay[day.date]) }"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
