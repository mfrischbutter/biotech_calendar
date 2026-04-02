import { ref, computed, type Ref } from 'vue';
import type { Appointment } from '@/types';

export interface DragState {
    active: boolean;
    mode: 'create' | 'move' | 'resize' | null;
    dayDate: string;
    startMinutes: number;
    currentMinutes: number;
    appointment: Appointment | null;
    originalStartMinutes: number;
    originalEndMinutes: number;
    /** Track total mouse movement to distinguish click from drag */
    totalMovement: number;
    /** The mouse‑grab offset from the appointment top (move only) */
    grabOffset: number;
}

const MIN_DRAG_DISTANCE = 4; // px of mouse movement before we consider it a drag

export function useCalendarDrag(
    hourHeight: Ref<number> | number,
    startHour: Ref<number> | number,
) {
    const hHeight = computed(() => typeof hourHeight === 'number' ? hourHeight : hourHeight.value);
    const sHour = computed(() => typeof startHour === 'number' ? startHour : startHour.value);

    const drag = ref<DragState>({
        active: false,
        mode: null,
        dayDate: '',
        startMinutes: 0,
        currentMinutes: 0,
        appointment: null,
        originalStartMinutes: 0,
        originalEndMinutes: 0,
        totalMovement: 0,
        grabOffset: 0,
    });

    /** Convert a viewport‑Y coordinate to minutes‑of‑day, snapped to 15 min */
    function minutesFromY(y: number, containerTop: number): number {
        const relativeY = y - containerTop;
        const totalMinutes = (relativeY / hHeight.value) * 60 + sHour.value * 60;
        return Math.round(totalMinutes / 15) * 15;
    }

    // -- public API -----------------------------------------------------------

    function startCreate(dayDate: string, y: number, containerTop: number) {
        const minutes = minutesFromY(y, containerTop);
        drag.value = {
            active: true,
            mode: 'create',
            dayDate,
            startMinutes: minutes,
            currentMinutes: minutes + 30, // default 30‑min block
            appointment: null,
            originalStartMinutes: 0,
            originalEndMinutes: 0,
            totalMovement: 0,
            grabOffset: 0,
        };
    }

    function startMove(appointment: Appointment, dayDate: string, y: number, containerTop: number) {
        const startMin = localMinutes(appointment.start_at);
        const endMin = localMinutes(appointment.end_at);
        const mouseMin = minutesFromY(y, containerTop);

        drag.value = {
            active: true,
            mode: 'move',
            dayDate,
            startMinutes: startMin,
            currentMinutes: mouseMin,
            appointment,
            originalStartMinutes: startMin,
            originalEndMinutes: endMin,
            totalMovement: 0,
            grabOffset: mouseMin - startMin,
        };
    }

    function startResize(appointment: Appointment, dayDate: string) {
        const startMin = localMinutes(appointment.start_at);
        const endMin = localMinutes(appointment.end_at);

        drag.value = {
            active: true,
            mode: 'resize',
            dayDate,
            startMinutes: startMin,
            currentMinutes: endMin,
            appointment,
            originalStartMinutes: startMin,
            originalEndMinutes: endMin,
            totalMovement: 0,
            grabOffset: 0,
        };
    }

    let lastClientY = 0;

    function onMouseMove(y: number, containerTop: number, dayDate?: string) {
        if (!drag.value.active) return;

        drag.value.totalMovement += Math.abs(y - (lastClientY || y));
        lastClientY = y;

        const minutes = minutesFromY(y, containerTop);

        if (drag.value.mode === 'create') {
            drag.value.currentMinutes = Math.max(minutes, drag.value.startMinutes + 15);
        } else if (drag.value.mode === 'resize') {
            drag.value.currentMinutes = Math.max(minutes, drag.value.startMinutes + 15);
        } else if (drag.value.mode === 'move') {
            // Keep the appointment anchored at the grab offset
            const newStart = minutes - drag.value.grabOffset;
            drag.value.startMinutes = newStart;
            drag.value.currentMinutes = minutes;
            if (dayDate) {
                drag.value.dayDate = dayDate;
            }
        }
    }

    /** Returns null if drag was cancelled or was just a click */
    function endDrag(): {
        mode: string;
        dayDate: string;
        startMinutes: number;
        endMinutes: number;
        appointment: Appointment | null;
    } | null {
        if (!drag.value.active) return null;

        const wasDrag = drag.value.totalMovement >= MIN_DRAG_DISTANCE;
        lastClientY = 0;

        const result = {
            mode: drag.value.mode!,
            dayDate: drag.value.dayDate,
            startMinutes: 0,
            endMinutes: 0,
            appointment: drag.value.appointment,
        };

        if (drag.value.mode === 'create') {
            result.startMinutes = Math.min(drag.value.startMinutes, drag.value.currentMinutes);
            result.endMinutes = Math.max(drag.value.startMinutes, drag.value.currentMinutes);
        } else if (drag.value.mode === 'resize') {
            result.startMinutes = drag.value.startMinutes;
            result.endMinutes = drag.value.currentMinutes;
        } else if (drag.value.mode === 'move') {
            const duration = drag.value.originalEndMinutes - drag.value.originalStartMinutes;
            result.startMinutes = drag.value.startMinutes;
            result.endMinutes = drag.value.startMinutes + duration;
        }

        // Reset state
        drag.value = {
            active: false,
            mode: null,
            dayDate: '',
            startMinutes: 0,
            currentMinutes: 0,
            appointment: null,
            originalStartMinutes: 0,
            originalEndMinutes: 0,
            totalMovement: 0,
            grabOffset: 0,
        };

        // Only emit a result if the user actually dragged
        if (!wasDrag) return null;

        return result;
    }

    function formatMinutes(minutes: number): string {
        const h = Math.floor(minutes / 60);
        const m = minutes % 60;
        return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
    }

    function getDragPreviewStyle() {
        if (!drag.value.active || drag.value.totalMovement < MIN_DRAG_DISTANCE) return null;

        let topMin: number;
        let bottomMin: number;

        if (drag.value.mode === 'create') {
            topMin = Math.min(drag.value.startMinutes, drag.value.currentMinutes);
            bottomMin = Math.max(drag.value.startMinutes, drag.value.currentMinutes);
        } else if (drag.value.mode === 'resize') {
            topMin = drag.value.startMinutes;
            bottomMin = drag.value.currentMinutes;
        } else {
            const duration = drag.value.originalEndMinutes - drag.value.originalStartMinutes;
            topMin = drag.value.startMinutes;
            bottomMin = drag.value.startMinutes + duration;
        }

        const top = ((topMin - sHour.value * 60) / 60) * hHeight.value;
        const height = ((bottomMin - topMin) / 60) * hHeight.value;

        return {
            top: `${top}px`,
            height: `${Math.max(height, 15)}px`,
        };
    }

    /** Returns the current start/end time labels for the drag preview */
    function getDragTimeLabel(): { start: string; end: string } | null {
        if (!drag.value.active || drag.value.totalMovement < MIN_DRAG_DISTANCE) return null;

        let startMin: number;
        let endMin: number;

        if (drag.value.mode === 'create') {
            startMin = Math.min(drag.value.startMinutes, drag.value.currentMinutes);
            endMin = Math.max(drag.value.startMinutes, drag.value.currentMinutes);
        } else if (drag.value.mode === 'resize') {
            startMin = drag.value.startMinutes;
            endMin = drag.value.currentMinutes;
        } else {
            const duration = drag.value.originalEndMinutes - drag.value.originalStartMinutes;
            startMin = drag.value.startMinutes;
            endMin = drag.value.startMinutes + duration;
        }

        return { start: formatMinutes(startMin), end: formatMinutes(endMin) };
    }

    return {
        drag,
        startCreate,
        startMove,
        startResize,
        onMouseMove,
        endDrag,
        formatMinutes,
        getDragPreviewStyle,
        getDragTimeLabel,
    };
}

// ---------------------------------------------------------------------------
// Timezone‑safe helpers
// ---------------------------------------------------------------------------

/** Extract hours*60+minutes in **local** time from a datetime string. */
export function localMinutes(dateStr: string): number {
    const d = new Date(dateStr);
    return d.getHours() * 60 + d.getMinutes();
}

/**
 * Build a UTC ISO string from a local date + time.
 * E.g. localToISO("2026-04-02", "14:00") in Berlin (UTC+2) → "2026-04-02T12:00:00.000Z"
 */
export function localToISO(date: string, time: string): string {
    return new Date(`${date}T${time}:00`).toISOString();
}

/**
 * Format a UTC datetime string into local "yyyy-MM-dd" and "HH:mm".
 */
export function isoToLocalParts(dateStr: string): { date: string; time: string } {
    const d = new Date(dateStr);
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    const hh = String(d.getHours()).padStart(2, '0');
    const min = String(d.getMinutes()).padStart(2, '0');
    return { date: `${yyyy}-${mm}-${dd}`, time: `${hh}:${min}` };
}
