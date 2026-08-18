import { beforeEach, describe, expect, it } from 'vitest';
import ContractsIndex from '@/Pages/Contracts/Index.vue';
import { mountComponent, setAuthedOwner } from '../helpers';
import type { ContractListRow } from '@/types';

/*
 * Three quarters of the contract list is one-visit work, so the progress column
 * used to be 49 identical empty bars saying nothing the Status pill next to
 * them was not already saying. The bar is kept for jobs that actually run over
 * several appointments; a single visit shows the count alone.
 */

function row(overrides: Partial<ContractListRow> = {}): ContractListRow {
    return {
        id: 1,
        contract_number: 'A-0001',
        title: 'Erstbegehung Baeckerei Bergmann',
        kind: 'kundentermin',
        description: null,
        street: null,
        zip: null,
        city: 'Muenchen',
        clients: [],
        team: [],
        stage: 'active',
        progress: { done: 0, total: 1, percent: 0 },
        ...overrides,
    };
}

function mountList(rows: ContractListRow[]) {
    return mountComponent(ContractsIndex, {
        // The real layout reaches for /notifications, which jsdom cannot fetch
        // and this test has no interest in.
        global: { stubs: { AuthenticatedLayout: { template: '<div><slot /></div>' } } },
        props: {
            contracts: { data: rows, links: [], current_page: 1, last_page: 1, per_page: 20, total: rows.length, from: 1, to: rows.length },
            stageCounts: [{ stage: 'all', count: rows.length }],
            clients: [],
            filters: { search: null, sort: 'contract_number', dir: 'asc', view: 'all' },
        },
    });
}

/** The bar is the only element in the column carrying a width style. */
function bars(wrapper: ReturnType<typeof mountList>) {
    return wrapper.findAll('td [style*="width"]');
}

describe('contract progress column', () => {
    beforeEach(() => setAuthedOwner());

    it('draws a bar for a job that runs over several visits', () => {
        const wrapper = mountList([row({ progress: { done: 1, total: 3, percent: 33 } })]);

        expect(bars(wrapper)).toHaveLength(1);
        expect(wrapper.text()).toContain('1/3');
    });

    it('leaves the bar off a single-visit job and keeps the count', () => {
        const wrapper = mountList([row({ progress: { done: 0, total: 1, percent: 0 } })]);

        expect(bars(wrapper)).toHaveLength(0);
        expect(wrapper.text()).toContain('0/1');
    });

    // Every visit called off: no outstanding work, so nothing to show progress on.
    it('shows a dash when there is nothing left to track', () => {
        const wrapper = mountList([row({ progress: { done: 0, total: 0, percent: 0 } })]);

        expect(bars(wrapper)).toHaveLength(0);
        expect(wrapper.text()).not.toContain('0/0');
    });
});
