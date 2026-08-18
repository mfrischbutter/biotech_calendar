import { beforeEach, describe, expect, it } from 'vitest';
import { computed, defineComponent, h, ref } from 'vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import SidePeek from '@/Components/SidePeek.vue';
import { useSidePeek } from '@/lib/use-side-peek';
import { mountComponent, setAuthedOwner } from '../helpers';
import type { DataTableColumn } from '@/types';

interface Row {
    id: number;
    name: string;
}

const columns: DataTableColumn[] = [{ key: 'name', label: 'Kunde', sortKey: 'name', locked: true }];

const initialRows: Row[] = [
    { id: 1, name: 'Klaus Bergmann' },
    { id: 2, name: 'Anna Zimmer' },
    { id: 3, name: 'Markus Weber' },
];

/**
 * The real arrangement: a list that opens a peek panel on row click, and a
 * panel that walks the list with the arrow keys.
 */
const ListWithPeek = defineComponent({
    setup() {
        const rows = ref<Row[]>([...initialRows]);
        const opened = ref<number[]>([]);
        const peek = useSidePeek<Row>(() => rows.value);

        return () => [
            h(
                DataTable,
                {
                    rows: rows.value,
                    columns,
                    visibleColumns: ['name'],
                    sort: { key: 'name', dir: 'asc' },
                    activeId: peek.active.value?.id ?? null,
                    onRowClick: (row: Row) => peek.open(row),
                },
                { 'cell-name': (props: { row: Row }) => h('span', props.row.name) },
            ),
            h(SidePeek, {
                open: peek.isOpen.value,
                title: peek.active.value?.name ?? '',
                onClose: () => peek.close(),
                onNext: () => peek.next(),
                onPrev: () => peek.prev(),
                onOpenRecord: () => peek.active.value && opened.value.push(peek.active.value.id),
            }),
            h('span', { 'data-testid': 'opened' }, opened.value.join(',')),
            h('button', { 'data-testid': 'drop-rows', onClick: () => (rows.value = [initialRows[2]]) }, 'x'),
        ];
    },
});

function press(key: string): void {
    window.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true }));
}

describe('side peek', () => {
    beforeEach(() => setAuthedOwner());

    it('stays closed until a row is clicked', () => {
        const wrapper = mountComponent(ListWithPeek);

        expect(wrapper.find('[data-testid="side-peek"]').exists()).toBe(false);
    });

    it('opens on a row click and shows that record', async () => {
        const wrapper = mountComponent(ListWithPeek);

        await wrapper.find('[data-testid="row-2"]').trigger('click');

        const panel = wrapper.find('[data-testid="side-peek"]');
        expect(panel.exists()).toBe(true);
        expect(panel.text()).toContain('Anna Zimmer');
    });

    it('marks the peeked row as active in the table', async () => {
        const wrapper = mountComponent(ListWithPeek);

        await wrapper.find('[data-testid="row-2"]').trigger('click');

        expect(wrapper.find('[data-testid="row-2"]').classes()).toContain('bg-navy-wash');
    });

    it('moves down the list with ArrowDown without closing', async () => {
        const wrapper = mountComponent(ListWithPeek);
        await wrapper.find('[data-testid="row-1"]').trigger('click');

        press('ArrowDown');
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-testid="side-peek"]').text()).toContain('Anna Zimmer');
    });

    it('moves back up the list with ArrowUp', async () => {
        const wrapper = mountComponent(ListWithPeek);
        await wrapper.find('[data-testid="row-3"]').trigger('click');

        press('ArrowUp');
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-testid="side-peek"]').text()).toContain('Anna Zimmer');
    });

    it('stays on the last record when there is nothing further down', async () => {
        const wrapper = mountComponent(ListWithPeek);
        await wrapper.find('[data-testid="row-3"]').trigger('click');

        press('ArrowDown');
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-testid="side-peek"]').text()).toContain('Markus Weber');
    });

    it('closes on Escape', async () => {
        const wrapper = mountComponent(ListWithPeek);
        await wrapper.find('[data-testid="row-1"]').trigger('click');

        press('Escape');
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-testid="side-peek"]').exists()).toBe(false);
    });

    it('closes on the close button', async () => {
        const wrapper = mountComponent(ListWithPeek);
        await wrapper.find('[data-testid="row-1"]').trigger('click');

        await wrapper.find('[data-testid="peek-close"]').trigger('click');

        expect(wrapper.find('[data-testid="side-peek"]').exists()).toBe(false);
    });

    it('opens the full record on Enter', async () => {
        const wrapper = mountComponent(ListWithPeek);
        await wrapper.find('[data-testid="row-2"]').trigger('click');

        press('Enter');
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-testid="opened"]').text()).toBe('2');
    });

    it('ignores the arrow keys while someone is typing in a field', async () => {
        const wrapper = mountComponent(ListWithPeek, { attachTo: document.body });
        await wrapper.find('[data-testid="row-1"]').trigger('click');

        const input = document.createElement('input');
        document.body.appendChild(input);
        input.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-testid="side-peek"]').text()).toContain('Klaus Bergmann');
        input.remove();
        wrapper.unmount();
    });

    it('closes itself when the peeked record disappears from the list', async () => {
        const wrapper = mountComponent(ListWithPeek);
        await wrapper.find('[data-testid="row-1"]').trigger('click');

        await wrapper.find('[data-testid="drop-rows"]').trigger('click');

        expect(wrapper.find('[data-testid="side-peek"]').exists()).toBe(false);
    });
});

describe('useSidePeek', () => {
    it('does nothing when moving with no record open', () => {
        const peek = useSidePeek<Row>(() => initialRows);

        peek.next();

        expect(peek.active.value).toBeNull();
        expect(peek.isOpen.value).toBe(false);
    });

    it('keeps the same record when the list is reloaded with it still present', async () => {
        const rows = ref<Row[]>([...initialRows]);
        const peek = useSidePeek<Row>(() => rows.value);
        peek.open(initialRows[1]);

        rows.value = [{ id: 2, name: 'Anna Zimmer-Neu' }];
        await Promise.resolve();
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(peek.active.value?.name).toBe('Anna Zimmer-Neu');
    });

    it('reports open state from the active record', () => {
        const peek = useSidePeek<Row>(() => initialRows);
        const open = computed(() => peek.isOpen.value);

        peek.open(initialRows[0]);
        expect(open.value).toBe(true);

        peek.close();
        expect(open.value).toBe(false);
    });
});
