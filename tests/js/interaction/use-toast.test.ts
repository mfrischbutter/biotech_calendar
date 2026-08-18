import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

interface ToastCall {
    message: string;
    options: {
        duration?: number;
        description?: string;
        action?: { label: string; onClick: () => void };
    };
}

const sonner = vi.hoisted(() => {
    const calls: ToastCall[] = [];
    let sequence = 0;
    const dismiss = vi.fn();
    const success = vi.fn();
    const error = vi.fn();

    const toast = Object.assign(
        (message: string, options: ToastCall['options'] = {}) => {
            calls.push({ message, options });

            return ++sequence;
        },
        { success, error, dismiss },
    );

    return { calls, toast, dismiss, success, error };
});

vi.mock('vue-sonner', () => ({ toast: sonner.toast }));

import {
    UNDO_TOAST_DURATION,
    dismissPendingUndos,
    hasPendingUndo,
    toastError,
    toastSuccess,
    toastUndoable,
    undoToast,
} from '@/lib/use-toast';

function lastCall(): ToastCall {
    return sonner.calls[sonner.calls.length - 1];
}

describe('use-toast', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        sonner.calls.length = 0;
        sonner.dismiss.mockClear();
        sonner.success.mockClear();
        sonner.error.mockClear();
        dismissPendingUndos();
        sonner.dismiss.mockClear();
    });

    afterEach(() => {
        dismissPendingUndos();
        vi.useRealTimers();
    });

    it('fires a toast carrying the message and the undo action', () => {
        toastUndoable({
            message: 'Termin verschoben',
            description: 'Mi, 8. Apr · 09:00 – 10:00',
            undoLabel: 'Rückgängig',
            onUndo: () => undefined,
        });

        const call = lastCall();
        expect(call.message).toBe('Termin verschoben');
        expect(call.options.description).toBe('Mi, 8. Apr · 09:00 – 10:00');
        expect(call.options.duration).toBe(UNDO_TOAST_DURATION);
        expect(call.options.action?.label).toBe('Rückgängig');
    });

    it('restores the previous state when the action is clicked, and only once', () => {
        let time = '11:00';
        const key = toastUndoable({
            message: 'Termin verschoben',
            undoLabel: 'Rückgängig',
            onUndo: () => {
                time = '09:00';
            },
        });

        lastCall().options.action?.onClick();

        expect(time).toBe('09:00');
        expect(sonner.dismiss).toHaveBeenCalledTimes(1);

        time = 'touched again';
        expect(undoToast(key)).toBe(false);
        expect(time).toBe('touched again');
    });

    it('auto-dismisses: the offer expires with the toast', () => {
        const onUndo = vi.fn();
        const key = toastUndoable({ message: 'Termin verschoben', undoLabel: 'Rückgängig', onUndo });

        expect(hasPendingUndo(key)).toBe(true);

        vi.advanceTimersByTime(UNDO_TOAST_DURATION);

        expect(hasPendingUndo(key)).toBe(false);
        expect(undoToast(key)).toBe(false);
        expect(onUndo).not.toHaveBeenCalled();
    });

    it('honours a custom duration', () => {
        const key = toastUndoable({
            message: 'Termin verschoben',
            undoLabel: 'Rückgängig',
            onUndo: () => undefined,
            duration: 1000,
        });

        expect(lastCall().options.duration).toBe(1000);

        vi.advanceTimersByTime(999);
        expect(hasPendingUndo(key)).toBe(true);

        vi.advanceTimersByTime(1);
        expect(hasPendingUndo(key)).toBe(false);
    });

    it('keeps two offers apart', () => {
        const first = vi.fn();
        const second = vi.fn();
        const firstKey = toastUndoable({ message: 'A', undoLabel: 'Z', onUndo: first });
        const secondKey = toastUndoable({ message: 'B', undoLabel: 'Z', onUndo: second });

        expect(undoToast(secondKey)).toBe(true);
        expect(second).toHaveBeenCalledTimes(1);
        expect(first).not.toHaveBeenCalled();
        expect(hasPendingUndo(firstKey)).toBe(true);
    });

    it('drops every outstanding offer when the screen goes away', () => {
        const onUndo = vi.fn();
        const key = toastUndoable({ message: 'A', undoLabel: 'Z', onUndo });

        dismissPendingUndos();

        expect(hasPendingUndo(key)).toBe(false);
        expect(onUndo).not.toHaveBeenCalled();
        expect(sonner.dismiss).toHaveBeenCalled();
    });

    it('delegates the plain variants to sonner', () => {
        toastSuccess('Gespeichert', 'Alles gut');
        toastError('Fehlgeschlagen');

        expect(sonner.success).toHaveBeenCalledWith('Gespeichert', { description: 'Alles gut' });
        expect(sonner.error).toHaveBeenCalledWith('Fehlgeschlagen', { description: undefined });
    });
});
