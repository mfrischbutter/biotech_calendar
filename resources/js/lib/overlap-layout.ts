import { localMinutes } from '@/lib/date-utils';
import type { Appointment } from '@/types';

export interface LayoutSlot {
    appointment: Appointment;
    column: number;
    columnSpan: number;
    totalColumns: number;
}

export interface DayLayout {
    slots: Map<number, LayoutSlot>;
}

/**
 * Compute Google Calendar-style overlap layout for appointments on a single day.
 * All appointments are always visible — each one expands forward to fill unused columns.
 */
export function computeOverlapLayout(
    appointments: Appointment[],
    optimisticPositions?: Map<number, { startMinutes: number; endMinutes: number }>,
): DayLayout {
    if (appointments.length === 0) {
        return { slots: new Map() };
    }

    function getMinutes(appt: Appointment): { start: number; end: number } {
        const ov = optimisticPositions?.get(appt.id);
        if (ov) return { start: ov.startMinutes, end: ov.endMinutes };
        return { start: localMinutes(appt.start_at), end: localMinutes(appt.end_at) };
    }

    // Sort by start time, then longer duration first
    const sorted = [...appointments].sort((a, b) => {
        const aM = getMinutes(a);
        const bM = getMinutes(b);
        if (aM.start !== bM.start) return aM.start - bM.start;
        return (bM.end - bM.start) - (aM.end - aM.start);
    });

    // Find overlapping clusters
    const clusters: Appointment[][] = [];
    let currentCluster: Appointment[] = [];
    let clusterEnd = -1;

    for (const appt of sorted) {
        const m = getMinutes(appt);
        if (currentCluster.length === 0 || m.start < clusterEnd) {
            currentCluster.push(appt);
            clusterEnd = Math.max(clusterEnd, m.end);
        } else {
            clusters.push(currentCluster);
            currentCluster = [appt];
            clusterEnd = m.end;
        }
    }
    if (currentCluster.length > 0) {
        clusters.push(currentCluster);
    }

    const slots = new Map<number, LayoutSlot>();

    for (const cluster of clusters) {
        // Greedy column assignment
        const columns: { end: number }[] = [];
        const assignments = new Map<number, number>();

        for (const appt of cluster) {
            const m = getMinutes(appt);
            let placed = false;
            for (let col = 0; col < columns.length; col++) {
                if (columns[col].end <= m.start) {
                    columns[col].end = m.end;
                    assignments.set(appt.id, col);
                    placed = true;
                    break;
                }
            }
            if (!placed) {
                assignments.set(appt.id, columns.length);
                columns.push({ end: m.end });
            }
        }

        // Google Calendar-style: every event extends from its column to the right edge.
        // Higher-column events render on top, creating a cascading overlap effect.
        for (const appt of cluster) {
            const col = assignments.get(appt.id)!;
            slots.set(appt.id, {
                appointment: appt,
                column: col,
                columnSpan: columns.length - col,
                totalColumns: columns.length,
            });
        }
    }

    return { slots };
}
