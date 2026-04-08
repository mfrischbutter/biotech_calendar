<script setup lang="ts">
import { computed } from 'vue';
import { format, parseISO, startOfMonth, endOfMonth, startOfWeek, endOfWeek, addDays, isSameMonth } from 'date-fns';
import { de } from 'date-fns/locale';
import { getStatusStyle, getStatusDotStyle } from '@/lib/status-colors';
import { localDateString } from '@/lib/date-utils';
import { useTrans } from '@/lib/use-trans';
import { appointmentLabel } from '@/lib/appointment-label';
import AppointmentHoverCard from './AppointmentHoverCard.vue';
import type { Appointment } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    appointments: Appointment[];
    currentDate: string;
}>();

const emit = defineEmits<{
    appointmentClick: [appointment: Appointment];
    dayClick: [date: string];
}>();

const today = format(new Date(), 'yyyy-MM-dd');
const currentMonth = computed(() => parseISO(props.currentDate));

const dayHeaders = ['MO', 'DI', 'MI', 'DO', 'FR', 'SA', 'SO'];

const weeks = computed(() => {
    const monthStart = startOfMonth(currentMonth.value);
    const monthEnd = endOfMonth(currentMonth.value);
    const calStart = startOfWeek(monthStart, { weekStartsOn: 1 });
    const calEnd = endOfWeek(monthEnd, { weekStartsOn: 1 });

    const result: { date: string; dayNum: string; isCurrentMonth: boolean; isToday: boolean }[][] = [];
    let current = calStart;

    while (current <= calEnd) {
        const week: typeof result[0] = [];
        for (let i = 0; i < 7; i++) {
            const dateStr = format(current, 'yyyy-MM-dd');
            week.push({
                date: dateStr,
                dayNum: format(current, 'd'),
                isCurrentMonth: isSameMonth(current, currentMonth.value),
                isToday: dateStr === today,
            });
            current = addDays(current, 1);
        }
        result.push(week);
    }
    return result;
});

const appointmentsByDay = computed(() => {
    const byDay: Record<string, Appointment[]> = {};
    for (const appt of props.appointments) {
        const dayKey = localDateString(appt.start_at);
        if (!byDay[dayKey]) byDay[dayKey] = [];
        byDay[dayKey].push(appt);
    }
    return byDay;
});

function getTimeLabel(appt: Appointment): string {
    return format(new Date(appt.start_at), 'HH:mm');
}
</script>

<template>
    <div class="rounded-lg border bg-background overflow-hidden flex flex-col h-full">
        <!-- Day headers -->
        <div class="grid grid-cols-7 border-b bg-background shrink-0">
            <div
                v-for="day in dayHeaders"
                :key="day"
                class="py-2 text-center text-[11px] font-medium text-muted-foreground tracking-wide border-r last:border-r-0"
            >
                {{ day }}
            </div>
        </div>

        <!-- Week rows -->
        <div class="flex-1 grid" :style="{ gridTemplateRows: `repeat(${weeks.length}, 1fr)` }">
            <div
                v-for="(week, wi) in weeks"
                :key="wi"
                class="grid grid-cols-7 border-b last:border-b-0 min-h-[60px] md:min-h-[100px]"
            >
                <div
                    v-for="day in week"
                    :key="day.date"
                    class="border-r last:border-r-0 p-1 overflow-hidden cursor-pointer hover:bg-muted/30 transition-colors"
                    :class="!day.isCurrentMonth ? 'bg-muted/10' : ''"
                    @click="emit('dayClick', day.date)"
                >
                    <div class="flex justify-end mb-0.5">
                        <span
                            class="text-sm leading-none inline-flex items-center justify-center"
                            :class="day.isToday
                                ? 'bg-primary text-primary-foreground rounded-full w-[26px] h-[26px] font-medium'
                                : day.isCurrentMonth
                                    ? 'text-foreground w-[26px] h-[26px]'
                                    : 'text-muted-foreground/50 w-[26px] h-[26px]'"
                        >
                            {{ day.dayNum }}
                        </span>
                    </div>

                    <!-- Appointments for this day -->
                    <div class="space-y-0.5">
                        <AppointmentHoverCard
                            v-for="appt in (appointmentsByDay[day.date] || []).slice(0, 3)"
                            :key="appt.id"
                            :appointment="appt"
                        >
                            <div
                                class="flex items-center gap-1 rounded px-0.5 py-0.5 text-[10px] leading-tight cursor-pointer hover:opacity-80 truncate md:px-1 md:text-[11px]"
                                :style="{ backgroundColor: getStatusStyle(appt.status?.color ?? null).backgroundColor }"
                                @click.stop="emit('appointmentClick', appt)"
                            >
                                <span class="w-1.5 h-1.5 rounded-full shrink-0" :style="getStatusDotStyle(appt.status?.color ?? null)" />
                                <span class="text-muted-foreground shrink-0 hidden md:inline">{{ getTimeLabel(appt) }}</span>
                                <span class="truncate" :style="{ color: appt.status?.color ?? 'hsl(var(--muted-foreground))' }">
                                    {{ appointmentLabel(appt) }}
                                </span>
                            </div>
                        </AppointmentHoverCard>
                        <div
                            v-if="(appointmentsByDay[day.date]?.length || 0) > 3"
                            class="text-[10px] text-muted-foreground pl-0.5 md:pl-1"
                        >
                            +{{ appointmentsByDay[day.date].length - 3 }} {{ t('more') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
