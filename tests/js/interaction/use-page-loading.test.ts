import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, type Ref } from 'vue';
import { LOADING_DELAY_MS, usePageLoading } from '@/lib/use-page-loading';
import { inertiaRouterMock } from '../setup';
import { mountComponent } from '../helpers';

type Handler = (event: unknown) => void;

function handlersFor(name: string): Handler[] {
    return inertiaRouterMock.on.mock.calls
        .filter((call) => call[0] === name)
        .map((call) => call[1] as Handler);
}

function fire(name: string, method?: string): void {
    for (const handler of handlersFor(name)) {
        handler(method === undefined ? {} : { detail: { visit: { method } } });
    }
}

describe('usePageLoading', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        inertiaRouterMock.on.mockClear();
        inertiaRouterMock.on.mockImplementation(() => () => {});
    });

    afterEach(() => vi.useRealTimers());

    function host(): { loading: Ref<boolean>; unmount: () => void } {
        let loading!: Ref<boolean>;
        const wrapper = mountComponent(
            defineComponent({
                setup() {
                    loading = usePageLoading();

                    return () => h('div');
                },
            }),
        );

        return { loading, unmount: () => wrapper.unmount() };
    }

    it('turns on only once the visit has taken long enough to notice', () => {
        const { loading } = host();

        fire('start', 'get');
        expect(loading.value).toBe(false);

        vi.advanceTimersByTime(LOADING_DELAY_MS);
        expect(loading.value).toBe(true);
    });

    it('never flashes for a visit that finishes quickly', () => {
        const { loading } = host();

        fire('start', 'get');
        vi.advanceTimersByTime(LOADING_DELAY_MS - 1);
        fire('finish');
        vi.advanceTimersByTime(LOADING_DELAY_MS);

        expect(loading.value).toBe(false);
    });

    it('turns off again when the payload lands', () => {
        const { loading } = host();

        fire('start', 'get');
        vi.advanceTimersByTime(LOADING_DELAY_MS);
        expect(loading.value).toBe(true);

        fire('finish');
        expect(loading.value).toBe(false);
    });

    it('ignores writes, so saving never blanks out the screen', () => {
        const { loading } = host();

        for (const method of ['put', 'post', 'delete']) {
            fire('start', method);
            vi.advanceTimersByTime(LOADING_DELAY_MS * 2);
            expect(loading.value).toBe(false);
        }
    });

    it('unsubscribes when the screen goes away', () => {
        const stops = [vi.fn(), vi.fn()];
        let index = 0;
        inertiaRouterMock.on.mockImplementation(() => stops[index++] ?? (() => {}));

        const { unmount } = host();
        unmount();

        expect(stops[0]).toHaveBeenCalled();
        expect(stops[1]).toHaveBeenCalled();
    });
});
