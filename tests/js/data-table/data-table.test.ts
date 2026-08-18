import { beforeEach, describe, expect, it } from 'vitest';
import { h } from 'vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import { mountComponent, setAuthedOwner } from '../helpers';
import type { DataTableColumn, SortState } from '@/types';

interface Row {
    id: number;
    name: string;
    city: string;
}

const columns: DataTableColumn[] = [
    { key: 'name', label: 'Kunde', sortKey: 'name', locked: true },
    { key: 'city', label: 'Ort', sortKey: 'city' },
    { key: 'phone', label: 'Telefon' },
];

const rows: Row[] = [
    { id: 1, name: 'Klaus Bergmann', city: 'München' },
    { id: 2, name: 'Anna Zimmer', city: 'Augsburg' },
    { id: 3, name: 'Markus Weber', city: 'Kempten' },
];

const sort: SortState = { key: 'name', dir: 'asc' };

function mountTable(overrides: Record<string, unknown> = {}, slots: Record<string, unknown> = {}) {
    return mountComponent(DataTable, {
        props: {
            rows,
            columns,
            visibleColumns: ['name', 'city'],
            sort,
            selectable: true,
            ...overrides,
        },
        slots: {
            'cell-name': (props: { row: Row }) => h('span', props.row.name),
            'cell-city': (props: { row: Row }) => h('span', props.row.city),
            ...slots,
        },
    });
}

describe('DataTable', () => {
    beforeEach(() => setAuthedOwner());

    it('renders only the columns that are currently visible', () => {
        const wrapper = mountTable();

        expect(wrapper.text()).toContain('Kunde');
        expect(wrapper.text()).toContain('Ort');
        expect(wrapper.text()).not.toContain('Telefon');
    });

    it('emits the sort key of the header that was clicked', async () => {
        const wrapper = mountTable();

        await wrapper.find('[data-testid="sort-city"]').trigger('click');

        expect(wrapper.emitted('sort')).toEqual([['city']]);
    });

    it('marks the active sort column with an aria-sort direction', () => {
        const wrapper = mountTable();

        expect(wrapper.find('[data-testid="sort-name"]').attributes('aria-sort')).toBe('ascending');
        expect(wrapper.find('[data-testid="sort-city"]').attributes('aria-sort')).toBe('none');
    });

    it('emits the clicked row', async () => {
        const wrapper = mountTable();

        await wrapper.find('[data-testid="row-2"]').trigger('click');

        expect(wrapper.emitted('rowClick')?.[0]).toEqual([rows[1]]);
    });

    it('selects a single row and reports the selection', async () => {
        const wrapper = mountTable();

        await wrapper.find('[data-testid="row-select-2"]').trigger('click');

        expect(wrapper.emitted('selectionChange')?.at(-1)).toEqual([[2]]);
    });

    it('selects every row from the header checkbox and clears it again', async () => {
        const wrapper = mountTable();
        const selectAll = wrapper.find('[data-testid="select-all"]');

        await selectAll.trigger('click');
        expect(wrapper.emitted('selectionChange')?.at(-1)).toEqual([[1, 2, 3]]);

        await selectAll.trigger('click');
        expect(wrapper.emitted('selectionChange')?.at(-1)).toEqual([[]]);
    });

    it('shows the selection bar with a count once something is selected', async () => {
        const wrapper = mountTable();

        expect(wrapper.find('[data-testid="selection-bar"]').exists()).toBe(false);

        await wrapper.find('[data-testid="row-select-1"]').trigger('click');

        const bar = wrapper.find('[data-testid="selection-bar"]');
        expect(bar.exists()).toBe(true);
        expect(bar.text()).toContain('1');
    });

    it('clears the selection from the selection bar', async () => {
        const wrapper = mountTable();
        await wrapper.find('[data-testid="row-select-1"]').trigger('click');

        await wrapper.find('[data-testid="clear-selection"]').trigger('click');

        expect(wrapper.emitted('selectionChange')?.at(-1)).toEqual([[]]);
        expect(wrapper.find('[data-testid="selection-bar"]').exists()).toBe(false);
    });

    it('drops the selection when the rows are replaced by a reload', async () => {
        const wrapper = mountTable();
        await wrapper.find('[data-testid="row-select-1"]').trigger('click');

        await wrapper.setProps({ rows: [{ id: 9, name: 'Neu', city: 'Ulm' }] });

        expect(wrapper.emitted('selectionChange')?.at(-1)).toEqual([[]]);
        expect(wrapper.find('[data-testid="selection-bar"]').exists()).toBe(false);
    });

    it('renders the empty slot instead of the table when there are no rows', () => {
        const wrapper = mountTable({ rows: [] }, {
            empty: () => h('p', { 'data-testid': 'empty-state' }, 'Keine Kunden'),
        });

        expect(wrapper.find('[data-testid="empty-state"]').exists()).toBe(true);
        expect(wrapper.find('table').exists()).toBe(false);
    });

    it('shows a skeleton instead of rows while a reload is in flight', () => {
        const wrapper = mountTable({ loading: true });

        expect(wrapper.findAll('[data-testid="table-skeleton-row"]').length).toBeGreaterThan(0);
        expect(wrapper.find('[data-testid="row-1"]').exists()).toBe(false);
    });

    it('keeps the empty slot hidden while loading, so it cannot flash', () => {
        const wrapper = mountTable({ rows: [], loading: true }, {
            empty: () => h('p', { 'data-testid': 'empty-state' }, 'Keine Kunden'),
        });

        expect(wrapper.find('[data-testid="empty-state"]').exists()).toBe(false);
    });

    it('highlights the row that is open in the side peek', () => {
        const wrapper = mountTable({ activeId: 2 });

        expect(wrapper.find('[data-testid="row-2"]').classes()).toContain('bg-navy-wash');
        expect(wrapper.find('[data-testid="row-1"]').classes()).not.toContain('bg-navy-wash');
    });
});
