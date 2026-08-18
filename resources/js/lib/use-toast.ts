import { toast as sonner } from 'vue-sonner';

/**
 * How long an undoable toast stays on screen. Long enough to notice a mistake
 * on a busy board, short enough that it never queues up behind the next drag.
 */
export const UNDO_TOAST_DURATION = 8000;

export interface UndoableToast {
    message: string;
    description?: string;
    undoLabel: string;
    /** Reverts whatever the toast just announced. Runs at most once. */
    onUndo: () => void;
    duration?: number;
}

interface PendingUndo {
    onUndo: () => void;
    toastId?: string | number;
    timer?: ReturnType<typeof setTimeout>;
}

/**
 * Outstanding undo offers, keyed by the handle `toastUndoable` hands back.
 * The registry is what makes "undo" a promise we can keep: the offer expires
 * with the toast instead of lingering as a stale closure.
 */
const pending = new Map<number, PendingUndo>();
let sequence = 0;

export function toastSuccess(message: string, description?: string): void {
    sonner.success(message, { description });
}

export function toastError(message: string, description?: string): void {
    sonner.error(message, { description });
}

export function toastInfo(message: string, description?: string): void {
    sonner(message, { description });
}

/** Announce a change and offer to take it back. Returns the offer's handle. */
export function toastUndoable(options: UndoableToast): number {
    const key = ++sequence;
    const duration = options.duration ?? UNDO_TOAST_DURATION;
    const entry: PendingUndo = { onUndo: options.onUndo };
    pending.set(key, entry);

    entry.toastId = sonner(options.message, {
        description: options.description,
        duration,
        action: {
            label: options.undoLabel,
            onClick: () => {
                undoToast(key);
            },
        },
    });

    // The button disappears with the toast, so the offer has to expire with it.
    entry.timer = setTimeout(() => forget(key), duration);

    return key;
}

/** Take back the change behind `key`. Returns false once the offer has expired. */
export function undoToast(key: number): boolean {
    const entry = pending.get(key);
    if (!entry) return false;

    forget(key);
    if (entry.toastId !== undefined) sonner.dismiss(entry.toastId);
    entry.onUndo();

    return true;
}

export function hasPendingUndo(key: number): boolean {
    return pending.has(key);
}

/** Drop every outstanding offer — used when leaving the screen it belonged to. */
export function dismissPendingUndos(): void {
    for (const key of [...pending.keys()]) {
        const entry = pending.get(key);
        if (entry?.toastId !== undefined) sonner.dismiss(entry.toastId);
        forget(key);
    }
}

function forget(key: number): void {
    const entry = pending.get(key);
    if (!entry) return;
    if (entry.timer) clearTimeout(entry.timer);
    pending.delete(key);
}
