import { computed, shallowRef, watch, type ComputedRef, type ShallowRef } from 'vue';

export interface UseSidePeek<T> {
    active: ShallowRef<T | null>;
    isOpen: ComputedRef<boolean>;
    open: (row: T) => void;
    close: () => void;
    /** Move to the next row in the list without closing the panel. */
    next: () => void;
    prev: () => void;
}

/**
 * Row preview state for a list screen. Clicking a row shows it in a side panel
 * instead of navigating away, and arrow keys walk the list from inside the
 * panel — the reader keeps their place, their scroll and their filters.
 *
 * `rows` is a getter so the panel follows the current page of results: when a
 * reload replaces the rows, a peeked record that is gone closes itself.
 */
export function useSidePeek<T extends { id: number }>(rows: () => T[]): UseSidePeek<T> {
    const active = shallowRef(null) as ShallowRef<T | null>;

    function indexOfActive(): number {
        const current = active.value;
        if (!current) return -1;

        return rows().findIndex((row) => row.id === current.id);
    }

    function move(delta: number): void {
        const list = rows();
        const index = indexOfActive();
        if (index === -1) return;

        const target = list[index + delta];
        if (target) active.value = target;
    }

    watch(rows, (list) => {
        const current = active.value;
        if (!current) return;

        active.value = list.find((row) => row.id === current.id) ?? null;
    });

    return {
        active,
        isOpen: computed(() => active.value !== null),
        open: (row: T) => {
            active.value = row;
        },
        close: () => {
            active.value = null;
        },
        next: () => move(1),
        prev: () => move(-1),
    };
}
