import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useGlobalSearch } from '@/lib/use-global-search';

function payload(items: { title: string }[]) {
    return {
        query: 'x',
        groups: items.length
            ? [{ key: 'clients', label: 'Kunden', items: items.map((i, n) => ({ id: n + 1, type: 'client', ...i })) }]
            : [],
    };
}

function mockFetchOnce(body: unknown, ok = true) {
    return vi.fn().mockResolvedValue({
        ok,
        json: async () => body,
    });
}

describe('useGlobalSearch', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('debounces typing into a single request', async () => {
        const fetchMock = mockFetchOnce(payload([{ title: 'Bergmann' }]));
        vi.stubGlobal('fetch', fetchMock);

        const search = useGlobalSearch('/search', 200);
        search.query.value = 'B';
        search.query.value = 'Be';
        search.query.value = 'Ber';

        await vi.advanceTimersByTimeAsync(250);

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock.mock.calls[0][0]).toBe('/search?q=Ber');
    });

    it('url-encodes the query', async () => {
        const fetchMock = mockFetchOnce(payload([]));
        vi.stubGlobal('fetch', fetchMock);

        const search = useGlobalSearch('/search', 10);
        search.query.value = 'Müller & Co';
        await vi.advanceTimersByTimeAsync(50);

        expect(fetchMock.mock.calls[0][0]).toBe('/search?q=M%C3%BCller%20%26%20Co');
    });

    it('stores the returned groups and flattens them for keyboard movement', async () => {
        vi.stubGlobal('fetch', mockFetchOnce(payload([{ title: 'A' }, { title: 'B' }])));

        const search = useGlobalSearch('/search', 10);
        search.query.value = 'a';
        await vi.advanceTimersByTimeAsync(50);

        expect(search.groups.value).toHaveLength(1);
        expect(search.flatItems.value).toHaveLength(2);
        expect(search.activeIndex.value).toBe(0);
    });

    it('discards a slow response when a newer query has already been sent', async () => {
        let resolveFirst: (v: unknown) => void = () => {};
        const first = new Promise((resolve) => (resolveFirst = resolve));

        const fetchMock = vi
            .fn()
            .mockImplementationOnce(() => first)
            .mockResolvedValueOnce({ ok: true, json: async () => payload([{ title: 'NEW' }]) });
        vi.stubGlobal('fetch', fetchMock);

        const search = useGlobalSearch('/search', 10);

        search.query.value = 'old';
        await vi.advanceTimersByTimeAsync(20);
        search.query.value = 'new';
        await vi.advanceTimersByTimeAsync(20);

        // The stale request now finishes — it must not overwrite the newer result.
        resolveFirst({ ok: true, json: async () => payload([{ title: 'STALE' }]) });
        await vi.advanceTimersByTimeAsync(10);

        expect(search.flatItems.value[0].title).toBe('NEW');
    });

    it('flags an error and clears results when the request fails', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));

        const search = useGlobalSearch('/search', 10);
        search.query.value = 'x';
        await vi.advanceTimersByTimeAsync(50);

        expect(search.error.value).toBe(true);
        expect(search.groups.value).toEqual([]);
        expect(search.loading.value).toBe(false);
    });

    it('treats a non-2xx response as an error', async () => {
        vi.stubGlobal('fetch', mockFetchOnce({}, false));

        const search = useGlobalSearch('/search', 10);
        search.query.value = 'x';
        await vi.advanceTimersByTimeAsync(50);

        expect(search.error.value).toBe(true);
    });

    it('wraps around when moving past either end of the list', async () => {
        vi.stubGlobal('fetch', mockFetchOnce(payload([{ title: 'A' }, { title: 'B' }])));

        const search = useGlobalSearch('/search', 10);
        search.query.value = 'a';
        await vi.advanceTimersByTimeAsync(50);

        search.move(1);
        expect(search.activeIndex.value).toBe(1);
        search.move(1);
        expect(search.activeIndex.value).toBe(0, 'wraps to the start');
        search.move(-1);
        expect(search.activeIndex.value).toBe(1, 'wraps to the end');
    });

    it('keeps the active index at -1 when there is nothing to move through', () => {
        const search = useGlobalSearch('/search', 10);
        search.move(1);
        expect(search.activeIndex.value).toBe(-1);
    });

    it('reset clears the query, results and pending state', async () => {
        vi.stubGlobal('fetch', mockFetchOnce(payload([{ title: 'A' }])));

        const search = useGlobalSearch('/search', 10);
        search.query.value = 'a';
        await vi.advanceTimersByTimeAsync(50);

        search.reset();

        expect(search.query.value).toBe('');
        expect(search.groups.value).toEqual([]);
        expect(search.flatItems.value).toEqual([]);
        expect(search.activeIndex.value).toBe(-1);
    });

    it('a request in flight when reset is called cannot repopulate the list', async () => {
        let resolve: (v: unknown) => void = () => {};
        vi.stubGlobal(
            'fetch',
            vi.fn().mockImplementation(() => new Promise((r) => (resolve = r))),
        );

        const search = useGlobalSearch('/search', 10);
        search.query.value = 'a';
        await vi.advanceTimersByTimeAsync(20);

        search.reset();
        resolve({ ok: true, json: async () => payload([{ title: 'LATE' }]) });
        await vi.advanceTimersByTimeAsync(10);

        expect(search.flatItems.value).toEqual([]);
    });
});
