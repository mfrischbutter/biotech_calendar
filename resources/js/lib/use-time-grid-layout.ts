import { computed, type ComputedRef } from 'vue';
import { localMinutes } from '@/lib/date-utils';
import { computeOverlapLayout, type DayLayout, type OverflowGroup } from '@/lib/overlap-layout';
import type { Appointment } from '@/types';

export interface TimeGridLayoutSource {
    appointments: () => Appointment[];
    dates: () => string[];
    startHour: () => number;
    hourHeight: number;
}

export interface UseTimeGridLayout {
    layoutByDay: ComputedRef<Record<string, DayLayout>>;
    /** Only the appointments that actually get a block; the rest are in `overflow`. */
    visibleByDay: ComputedRef<Record<string, Appointment[]>>;
    overflowByDay: ComputedRef<Record<string, OverflowGroup[]>>;
    dayOf: (appt: Appointment) => string;
    minutesOf: (appt: Appointment) => { start: number; end: number };
    /** Absolute box for one appointment inside its day column. */
    positionStyle: (appt: Appointment, dayDate: string) => Record<string, string>;
    /** Absolute box for an overflow chip, right-aligned in its day column. */
    overflowStyle: (group: OverflowGroup) => Record<string, string>;
    isShort: (appt: Appointment) => boolean;
}

const MIN_BLOCK_HEIGHT = 22;

/** Above this many concurrent appointments a week column collapses its tail. */
const WEEK_MAX_CONCURRENT = 3;

function localDayKey(iso: string): string {
    const d = new Date(iso);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

/**
 * Placement for the day/week time grid: which appointment sits where, and which
 * ones are folded into a "+N weitere" chip.
 *
 * A single day column is wide enough for everything, so collapsing only kicks in
 * when several days share the width.
 *
 * Provisional drag positions never reach here: the page hands the grid the
 * appointments as they should currently be drawn, so this stays pure placement.
 */
export function useTimeGridLayout(source: TimeGridLayoutSource): UseTimeGridLayout {
    function dayOf(appt: Appointment): string {
        return localDayKey(appt.start_at);
    }

    function minutesOf(appt: Appointment): { start: number; end: number } {
        return { start: localMinutes(appt.start_at), end: localMinutes(appt.end_at) };
    }

    const appointmentsByDay = computed(() => {
        const byDay: Record<string, Appointment[]> = {};
        for (const date of source.dates()) {
            byDay[date] = [];
        }
        for (const appt of source.appointments()) {
            const key = dayOf(appt);
            byDay[key]?.push(appt);
        }
        return byDay;
    });

    const layoutByDay = computed(() => {
        const dates = source.dates();
        const maxConcurrent = dates.length > 1 ? WEEK_MAX_CONCURRENT : Infinity;

        const result: Record<string, DayLayout> = {};
        for (const date of dates) {
            result[date] = computeOverlapLayout(appointmentsByDay.value[date] ?? [], undefined, {
                maxConcurrent,
            });
        }
        return result;
    });

    const visibleByDay = computed(() => {
        const result: Record<string, Appointment[]> = {};
        for (const date of source.dates()) {
            const slots = layoutByDay.value[date]?.slots;
            result[date] = (appointmentsByDay.value[date] ?? []).filter((appt) => slots?.has(appt.id));
        }
        return result;
    });

    const overflowByDay = computed(() => {
        const result: Record<string, OverflowGroup[]> = {};
        for (const date of source.dates()) {
            result[date] = layoutByDay.value[date]?.overflow ?? [];
        }
        return result;
    });

    function topFor(startMinutes: number): number {
        return ((startMinutes - source.startHour() * 60) / 60) * source.hourHeight;
    }

    function positionStyle(appt: Appointment, dayDate: string): Record<string, string> {
        const { start, end } = minutesOf(appt);
        const top = topFor(start);
        const height = Math.max(((end - start) / 60) * source.hourHeight, MIN_BLOCK_HEIGHT);

        const slot = layoutByDay.value[dayDate]?.slots.get(appt.id);
        if (slot && slot.totalColumns > 1) {
            const columnWidth = 100 / slot.totalColumns;
            const left = slot.column * columnWidth;
            const width = slot.columnSpan * columnWidth;
            return {
                top: `${top}px`,
                height: `${height}px`,
                left: `calc(${left}% + 4px)`,
                right: `calc(${100 - left - width}% + 4px)`,
                zIndex: `${10 + slot.column}`,
            };
        }

        return { top: `${top}px`, height: `${height}px`, left: '4px', right: '4px' };
    }

    function overflowStyle(group: OverflowGroup): Record<string, string> {
        return { top: `${topFor(group.startMinutes) + 2}px`, right: '6px' };
    }

    function isShort(appt: Appointment): boolean {
        const { start, end } = minutesOf(appt);
        return end - start <= 30;
    }

    return {
        layoutByDay,
        visibleByDay,
        overflowByDay,
        dayOf,
        minutesOf,
        positionStyle,
        overflowStyle,
        isShort,
    };
}
