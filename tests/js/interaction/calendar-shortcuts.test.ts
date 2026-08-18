import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import {
    CALENDAR_SHORTCUTS,
    handleCalendarShortcut,
    isTypingTarget,
    useCalendarShortcuts,
    type CalendarShortcutActions,
} from '@/lib/use-calendar-shortcuts';
import { mountComponent } from '../helpers';

function actions(): CalendarShortcutActions & Record<string, ReturnType<typeof vi.fn>> {
    return {
        today: vi.fn(),
        navigate: vi.fn(),
        switchView: vi.fn(),
        create: vi.fn(),
        toggleHelp: vi.fn(),
    } as unknown as CalendarShortcutActions & Record<string, ReturnType<typeof vi.fn>>;
}

function keyEvent(key: string, target: EventTarget | null = null, init: KeyboardEventInit = {}): KeyboardEvent {
    const event = new KeyboardEvent('keydown', { key, cancelable: true, ...init });
    if (target) Object.defineProperty(event, 'target', { value: target, configurable: true });

    return event;
}

describe('handleCalendarShortcut', () => {
    let target: HTMLElement;

    beforeEach(() => {
        document.body.innerHTML = '';
        target = document.createElement('div');
        document.body.appendChild(target);
    });

    it.each([
        ['t', 'today', undefined],
        ['T', 'today', undefined],
        ['n', 'create', undefined],
        ['?', 'toggleHelp', undefined],
    ])('runs %s', (key, method) => {
        const handlers = actions();

        expect(handleCalendarShortcut(keyEvent(key, target), handlers)).toBe(true);
        expect(handlers[method]).toHaveBeenCalledTimes(1);
    });

    it.each([
        ['d', 'day'],
        ['w', 'week'],
        ['m', 'month'],
    ])('switches to the %s view', (key, view) => {
        const handlers = actions();

        expect(handleCalendarShortcut(keyEvent(key, target), handlers)).toBe(true);
        expect(handlers.switchView).toHaveBeenCalledWith(view);
    });

    it('steps backwards and forwards with the arrow keys', () => {
        const handlers = actions();

        handleCalendarShortcut(keyEvent('ArrowLeft', target), handlers);
        handleCalendarShortcut(keyEvent('ArrowRight', target), handlers);

        expect(handlers.navigate).toHaveBeenNthCalledWith(1, -1);
        expect(handlers.navigate).toHaveBeenNthCalledWith(2, 1);
    });

    it('ignores keys it does not know', () => {
        const handlers = actions();

        expect(handleCalendarShortcut(keyEvent('q', target), handlers)).toBe(false);
    });

    it('stays out of the way while the user is typing', () => {
        const input = document.createElement('input');
        const textarea = document.createElement('textarea');
        const editable = document.createElement('div');
        editable.setAttribute('contenteditable', 'true');
        Object.defineProperty(editable, 'isContentEditable', { value: true });
        document.body.append(input, textarea, editable);

        for (const element of [input, textarea, editable]) {
            const handlers = actions();
            expect(handleCalendarShortcut(keyEvent('n', element), handlers)).toBe(false);
            expect(handlers.create).not.toHaveBeenCalled();
        }

        expect(isTypingTarget(input)).toBe(true);
        expect(isTypingTarget(target)).toBe(false);
        expect(isTypingTarget(null)).toBe(false);
    });

    it('leaves browser and OS combinations alone', () => {
        for (const init of [{ ctrlKey: true }, { metaKey: true }, { altKey: true }]) {
            const handlers = actions();
            expect(handleCalendarShortcut(keyEvent('t', target, init), handlers)).toBe(false);
            expect(handlers.today).not.toHaveBeenCalled();
        }
    });

    it('does not act on an event something else already handled', () => {
        const handlers = actions();
        const event = keyEvent('t', target);
        event.preventDefault();

        expect(handleCalendarShortcut(event, handlers)).toBe(false);
    });

    it('lists every shortcut it implements', () => {
        expect(CALENDAR_SHORTCUTS.map((shortcut) => shortcut.keys)).toEqual([
            'T',
            '←',
            '→',
            'D',
            'W',
            'M',
            'N',
            '?',
        ]);
    });
});

describe('useCalendarShortcuts', () => {
    function host(handlers: CalendarShortcutActions, enabled: () => boolean) {
        return mountComponent(
            defineComponent({
                setup() {
                    useCalendarShortcuts(handlers, enabled);

                    return () => h('div');
                },
            }),
        );
    }

    it('binds while mounted and stands down when disabled', async () => {
        const handlers = actions();
        let enabled = true;
        const wrapper = host(handlers, () => enabled);

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 't', cancelable: true }));
        expect(handlers.today).toHaveBeenCalledTimes(1);

        enabled = false;
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 't', cancelable: true }));
        expect(handlers.today).toHaveBeenCalledTimes(1);

        enabled = true;
        wrapper.unmount();
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 't', cancelable: true }));
        expect(handlers.today).toHaveBeenCalledTimes(1);
    });

    it('swallows the keystroke it acted on', () => {
        const handlers = actions();
        host(handlers, () => true);

        const event = new KeyboardEvent('keydown', { key: 'n', cancelable: true });
        document.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(true);
    });
});
