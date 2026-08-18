import { localMinutes } from '@/lib/date-utils';
import type { Appointment } from '@/types';

export interface LayoutSlot {
    appointment: Appointment;
    column: number;
    columnSpan: number;
    totalColumns: number;
}

/**
 * The appointments a column ran out of room for, folded into one "+N weitere"
 * affordance covering the window they share.
 */
export interface OverflowGroup {
    key: string;
    startMinutes: number;
    endMinutes: number;
    count: number;
    appointments: Appointment[];
}

export interface DayLayout {
    slots: Map<number, LayoutSlot>;
    overflow: OverflowGroup[];
}

export interface OverlapLayoutOptions {
    /**
     * Concurrency the grid still renders in full. Above it the tail collapses,
     * because four 34px slivers with truncated titles say less than two
     * readable blocks and a count. `Infinity` disables collapsing (day view).
     */
    maxConcurrent?: number;
    /** How many blocks stay on screen once a cluster collapses. */
    visibleColumns?: number;
}

const DEFAULT_MAX_CONCURRENT = 3;
const DEFAULT_VISIBLE_COLUMNS = 2;

interface Span {
    appointment: Appointment;
    start: number;
    end: number;
}

/** Group appointments into runs of transitively overlapping ones. */
function cluster(spans: Span[]): Span[][] {
    const clusters: Span[][] = [];
    let current: Span[] = [];
    let clusterEnd = -1;

    for (const span of spans) {
        if (current.length === 0 || span.start < clusterEnd) {
            current.push(span);
            clusterEnd = Math.max(clusterEnd, span.end);
        } else {
            clusters.push(current);
            current = [span];
            clusterEnd = span.end;
        }
    }
    if (current.length > 0) {
        clusters.push(current);
    }

    return clusters;
}

/**
 * Greedy left-to-right column packing. For intervals this uses exactly as many
 * columns as the cluster's peak concurrency.
 */
function assignColumns(spans: Span[]): Map<number, number> {
    const columnEnds: number[] = [];
    const assignments = new Map<number, number>();

    for (const span of spans) {
        let placed = false;
        for (let col = 0; col < columnEnds.length; col++) {
            if (columnEnds[col] <= span.start) {
                columnEnds[col] = span.end;
                assignments.set(span.appointment.id, col);
                placed = true;
                break;
            }
        }
        if (!placed) {
            assignments.set(span.appointment.id, columnEnds.length);
            columnEnds.push(span.end);
        }
    }

    return assignments;
}

/**
 * Google Calendar-style overlap layout for one day, with a readability ceiling.
 *
 * Up to `maxConcurrent` overlapping appointments every block stays on screen and
 * cascades to the right edge. Past that ceiling only the first `visibleColumns`
 * are drawn and the remainder becomes an overflow group the caller renders as a
 * "+N weitere" link.
 */
export function computeOverlapLayout(
    appointments: Appointment[],
    optimisticPositions?: Map<number, { startMinutes: number; endMinutes: number }>,
    options: OverlapLayoutOptions = {},
): DayLayout {
    const slots = new Map<number, LayoutSlot>();
    const overflow: OverflowGroup[] = [];

    if (appointments.length === 0) {
        return { slots, overflow };
    }

    const maxConcurrent = options.maxConcurrent ?? DEFAULT_MAX_CONCURRENT;
    const visibleColumns = Math.max(1, options.visibleColumns ?? DEFAULT_VISIBLE_COLUMNS);

    const spans: Span[] = appointments
        .map((appointment) => {
            const override = optimisticPositions?.get(appointment.id);
            return override
                ? { appointment, start: override.startMinutes, end: override.endMinutes }
                : {
                      appointment,
                      start: localMinutes(appointment.start_at),
                      end: localMinutes(appointment.end_at),
                  };
        })
        // Earliest first, and among equal starts the longest first so the big
        // block lands in column 0 and the short ones cascade over it.
        .sort((a, b) => (a.start !== b.start ? a.start - b.start : b.end - b.start - (a.end - a.start)));

    for (const group of cluster(spans)) {
        const assignments = assignColumns(group);
        const usedColumns = Math.max(...group.map((s) => assignments.get(s.appointment.id)!)) + 1;

        if (usedColumns <= maxConcurrent) {
            for (const span of group) {
                const column = assignments.get(span.appointment.id)!;
                slots.set(span.appointment.id, {
                    appointment: span.appointment,
                    column,
                    columnSpan: usedColumns - column,
                    totalColumns: usedColumns,
                });
            }
            continue;
        }

        const hidden: Span[] = [];

        for (const span of group) {
            const column = assignments.get(span.appointment.id)!;
            if (column >= visibleColumns) {
                hidden.push(span);
                continue;
            }
            slots.set(span.appointment.id, {
                appointment: span.appointment,
                column,
                columnSpan: visibleColumns - column,
                totalColumns: visibleColumns,
            });
        }

        // The hidden tail can span disjoint windows inside one cluster, so it
        // gets its own clustering — one chip per stretch of crowded time.
        for (const hiddenGroup of cluster(hidden)) {
            const startMinutes = Math.min(...hiddenGroup.map((s) => s.start));
            const endMinutes = Math.max(...hiddenGroup.map((s) => s.end));
            overflow.push({
                key: `overflow-${startMinutes}-${endMinutes}`,
                startMinutes,
                endMinutes,
                count: hiddenGroup.length,
                appointments: hiddenGroup.map((s) => s.appointment),
            });
        }
    }

    overflow.sort((a, b) => a.startMinutes - b.startMinutes);

    return { slots, overflow };
}
