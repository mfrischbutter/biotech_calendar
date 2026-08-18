import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useListQuery } from '@/lib/use-list-query';
import { inertiaRouterMock } from '../setup';
import type { ListFilters } from '@/types';

const filters: ListFilters = { search: null, sort: 'name', dir: 'asc', view: 'all' };

function lastVisit(): { url: string; query: Record<string, string>; options: Record<string, unknown> } {
    const call = inertiaRouterMock.get.mock.calls.at(-1) as [string, Record<string, string>, Record<string, unknown>];

    return { url: call[0], query: call[1], options: call[2] };
}

describe('useListQuery', () => {
    beforeEach(() => {
        vi.useRealTimers();
        inertiaRouterMock.get.mockClear();
    });

    it('sorts by a new key ascending', () => {
        const list = useListQuery('clients.index', filters);

        list.toggleSort('city');

        expect(list.sort.value).toEqual({ key: 'city', dir: 'asc' });
        expect(lastVisit().query).toEqual({ sort: 'city' });
    });

    it('flips the direction when the same key is clicked again', () => {
        const list = useListQuery('clients.index', filters);

        list.toggleSort('name');

        expect(list.sort.value).toEqual({ key: 'name', dir: 'desc' });
        expect(lastVisit().query).toEqual({ sort: 'name', dir: 'desc' });
    });

    it('keeps the list in place while reloading', () => {
        const list = useListQuery('clients.index', filters);

        list.toggleSort('city');

        expect(lastVisit().options).toMatchObject({
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    });

    it('puts the saved view in the query string, but never the default one', () => {
        const list = useListQuery('clients.index', filters);

        list.setView('dormant');
        expect(lastVisit().query).toEqual({ sort: 'name', view: 'dormant' });

        list.setView('all');
        expect(lastVisit().query).toEqual({ sort: 'name' });
    });

    it('does not reload when the view is already active', () => {
        const list = useListQuery('clients.index', filters);

        list.setView('all');

        expect(inertiaRouterMock.get).not.toHaveBeenCalled();
    });

    it('carries an extra filter and drops it again when cleared', () => {
        const list = useListQuery('employees.index', { ...filters, role: 'techniker' }, ['role']);

        expect(list.params.value).toEqual({ role: 'techniker' });

        list.setParam('role', null);

        expect(lastVisit().query).toEqual({ sort: 'name' });
    });

    it('debounces the search box instead of reloading on every keystroke', async () => {
        vi.useFakeTimers();
        const list = useListQuery('clients.index', filters);

        list.search.value = 'Berg';
        await Promise.resolve();
        list.search.value = 'Bergmann';
        await Promise.resolve();

        expect(inertiaRouterMock.get).not.toHaveBeenCalled();

        vi.advanceTimersByTime(300);

        expect(inertiaRouterMock.get).toHaveBeenCalledTimes(1);
        expect(lastVisit().query).toEqual({ search: 'Bergmann', sort: 'name' });
    });

    it('restores the filters it was given from the URL', () => {
        const list = useListQuery('clients.index', {
            search: 'Bergmann',
            sort: 'city',
            dir: 'desc',
            view: 'dormant',
        });

        expect(list.search.value).toBe('Bergmann');
        expect(list.sort.value).toEqual({ key: 'city', dir: 'desc' });
        expect(list.view.value).toBe('dormant');
    });

    it('clears search, view and filters on reset', () => {
        const list = useListQuery('employees.index', { ...filters, search: 'Weber', role: 'buero' }, ['role']);

        list.reset();

        expect(lastVisit().query).toEqual({ sort: 'name' });
    });
});
