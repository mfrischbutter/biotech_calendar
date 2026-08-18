import { ref, type Ref } from 'vue';

export interface OptimisticHandle {
    /** The server agreed: keep showing this until fresh data arrives. */
    commit: () => void;
    /** The server refused: put back whatever was on screen before. */
    rollback: () => void;
    /**
     * Whether this write has already been committed or rolled back. Callers use
     * it to catch outcomes that reach neither — a 403, a 500 or a dropped
     * connection never produces an Inertia error response.
     */
    isSettled: () => boolean;
}

export interface OptimisticStore<V> {
    /** id → the value the UI should draw instead of the server's. */
    values: Ref<Map<number, V>>;
    get: (id: number) => V | undefined;
    has: (id: number) => boolean;
    /** Show `value` for `id` right now, and hand back the way out. */
    apply: (id: number, value: V) => OptimisticHandle;
    clear: () => void;
    size: () => number;
}

/**
 * A tiny register of "what the screen is claiming while the server catches up".
 *
 * Every optimistic write is paired with a handle, so a failed request can undo
 * exactly its own change — a later drag of the same block is never clobbered by
 * a rollback that arrives after it.
 */
export function useOptimistic<V>(): OptimisticStore<V> {
    const values = ref(new Map<number, V>()) as Ref<Map<number, V>>;

    /**
     * id → the sequence number of the newest write. Reading the value back out
     * of a reactive Map hands you a proxy, so identity is no way to tell whose
     * write is currently on screen; a stamp is.
     */
    const latest = new Map<number, number>();
    let sequence = 0;

    function apply(id: number, value: V): OptimisticHandle {
        const had = values.value.has(id);
        const previous = values.value.get(id);
        const stamp = ++sequence;

        latest.set(id, stamp);
        values.value.set(id, value);

        let settled = false;

        return {
            commit(): void {
                settled = true;
            },
            rollback(): void {
                if (settled) return;
                settled = true;
                // Someone else has since written here; their value wins.
                if (latest.get(id) !== stamp) return;

                if (had) values.value.set(id, previous as V);
                else {
                    values.value.delete(id);
                    latest.delete(id);
                }
            },
            isSettled: () => settled,
        };
    }

    return {
        values,
        get: (id) => values.value.get(id),
        has: (id) => values.value.has(id),
        apply,
        clear(): void {
            values.value = new Map();
            latest.clear();
        },
        size: () => values.value.size,
    };
}
