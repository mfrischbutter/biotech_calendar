import { getCurrentInstance, onBeforeUnmount, onMounted } from 'vue';
import type { CalendarView } from '@/types';

export interface CalendarShortcut {
    /** What the user presses, already formatted for display. */
    keys: string;
    /** Translation key for what it does. */
    label: string;
}

/**
 * The whole keyboard vocabulary of the calendar, in the order the help panel
 * lists it. Nothing here is the only way to do something — every entry mirrors
 * a button that is visible on screen.
 */
export const CALENDAR_SHORTCUTS: CalendarShortcut[] = [
    { keys: 'T', label: 'Jump to today' },
    { keys: '←', label: 'Previous period' },
    { keys: '→', label: 'Next period' },
    { keys: 'D', label: 'Day view' },
    { keys: 'W', label: 'Week view' },
    { keys: 'M', label: 'Month view' },
    { keys: 'N', label: 'New Appointment' },
    { keys: '?', label: 'Show keyboard shortcuts' },
];

export interface CalendarShortcutActions {
    today: () => void;
    navigate: (direction: number) => void;
    switchView: (view: CalendarView) => void;
    create: () => void;
    toggleHelp: () => void;
}

const EDITABLE_TAGS = ['INPUT', 'TEXTAREA', 'SELECT'];

/** True when the keystroke belongs to whatever the user is typing into. */
export function isTypingTarget(target: EventTarget | null): boolean {
    if (!(target instanceof HTMLElement)) return false;
    if (EDITABLE_TAGS.includes(target.tagName)) return true;

    return target.isContentEditable === true;
}

/**
 * Runs the action a keystroke stands for. Returns true when it did something,
 * so the caller can swallow the event.
 */
export function handleCalendarShortcut(event: KeyboardEvent, actions: CalendarShortcutActions): boolean {
    if (event.defaultPrevented) return false;
    if (event.ctrlKey || event.metaKey || event.altKey) return false;
    if (isTypingTarget(event.target)) return false;

    switch (event.key) {
        case 'ArrowLeft':
            actions.navigate(-1);

            return true;
        case 'ArrowRight':
            actions.navigate(1);

            return true;
        case '?':
            actions.toggleHelp();

            return true;
    }

    // Layout-independent letters; a capital arrives here as its lower case too.
    switch (event.key.toLowerCase()) {
        case 't':
            actions.today();

            return true;
        case 'd':
            actions.switchView('day');

            return true;
        case 'w':
            actions.switchView('week');

            return true;
        case 'm':
            actions.switchView('month');

            return true;
        case 'n':
            actions.create();

            return true;
        default:
            return false;
    }
}

/**
 * Binds the calendar shortcuts for as long as the screen is mounted. `enabled`
 * lets the page switch them off while a drawer or dialog owns the keyboard.
 */
export function useCalendarShortcuts(
    actions: CalendarShortcutActions,
    enabled: () => boolean = () => true,
): void {
    function onKeydown(event: KeyboardEvent): void {
        if (!enabled()) return;
        if (handleCalendarShortcut(event, actions)) event.preventDefault();
    }

    if (!getCurrentInstance()) return;

    onMounted(() => document.addEventListener('keydown', onKeydown));
    onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
}
