import { ref, type Ref } from 'vue';
import type { DataTableColumn } from '@/types';

export interface UseColumnVisibility {
    /** Keys of the columns currently shown, in column order. */
    visible: Ref<string[]>;
    setVisible: (keys: string[]) => void;
}

/**
 * Which columns a reader wants to see, remembered per table in localStorage.
 * Columns marked `locked` are always shown; columns marked `hidden` start off.
 */
export function useColumnVisibility(
    storageKey: string,
    columns: DataTableColumn[],
): UseColumnVisibility {
    const defaults = columns.filter((column) => !column.hidden).map((column) => column.key);
    const known = new Set(columns.map((column) => column.key));
    const locked = columns.filter((column) => column.locked).map((column) => column.key);

    const visible = ref<string[]>(normalise(read() ?? defaults));

    function read(): string[] | null {
        try {
            const raw = window.localStorage.getItem(storageKey);
            if (!raw) return null;
            const parsed: unknown = JSON.parse(raw);

            return Array.isArray(parsed) ? parsed.filter((key): key is string => typeof key === 'string') : null;
        } catch {
            return null;
        }
    }

    /** Drop keys that no longer exist and force locked columns back in. */
    function normalise(keys: string[]): string[] {
        const wanted = new Set([...keys.filter((key) => known.has(key)), ...locked]);

        return columns.filter((column) => wanted.has(column.key)).map((column) => column.key);
    }

    function setVisible(keys: string[]): void {
        visible.value = normalise(keys);
        try {
            window.localStorage.setItem(storageKey, JSON.stringify(visible.value));
        } catch {
            // A private-mode browser refusing storage must not break the table.
        }
    }

    return { visible, setVisible };
}
