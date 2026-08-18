import { beforeEach, describe, expect, it } from 'vitest';
import DataTableColumnChooser from '@/Components/DataTable/DataTableColumnChooser.vue';
import { useColumnVisibility } from '@/lib/use-column-visibility';
import { mountComponent, setAuthedOwner } from '../helpers';
import type { DataTableColumn } from '@/types';

const columns: DataTableColumn[] = [
    { key: 'name', label: 'Kunde', locked: true },
    { key: 'city', label: 'Ort' },
    { key: 'phone', label: 'Telefon', hidden: true },
];

describe('column chooser', () => {
    beforeEach(() => {
        setAuthedOwner();
        window.localStorage.clear();
    });

    // jsdom cannot synthesise the pointer event reka opens the menu with, so the
    // menu is opened through its `open` model instead.
    // jsdom cannot synthesise the pointer event reka opens the menu with, so the
    // menu is opened through its `open` model. The menu content is portalled to
    // the document body, so teleport must render for real here.
    async function openChooser() {
        const wrapper = mountComponent(DataTableColumnChooser, {
            props: { columns, visible: ['name', 'city'], open: true },
            global: { stubs: { teleport: false } },
            attachTo: document.body,
        });
        await wrapper.vm.$nextTick();

        return wrapper;
    }

    function item(key: string): HTMLElement | null {
        return document.body.querySelector(`[data-testid="column-toggle-${key}"]`);
    }

    it('offers every column except the locked identity column', async () => {
        const wrapper = await openChooser();

        expect(item('city')).not.toBeNull();
        expect(item('phone')).not.toBeNull();
        expect(item('name')).toBeNull();

        wrapper.unmount();
    });

    it('adds a column that is currently off', async () => {
        const wrapper = await openChooser();

        item('phone')?.click();
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:visible')?.at(-1)).toEqual([['name', 'city', 'phone']]);
        wrapper.unmount();
    });

    it('removes a column that is currently on', async () => {
        const wrapper = await openChooser();

        item('city')?.click();
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:visible')?.at(-1)).toEqual([['name']]);
        wrapper.unmount();
    });
});

describe('useColumnVisibility', () => {
    beforeEach(() => window.localStorage.clear());

    it('starts with every column that is not hidden by default', () => {
        const { visible } = useColumnVisibility('test-table', columns);

        expect(visible.value).toEqual(['name', 'city']);
    });

    it('remembers a chosen set across mounts', () => {
        useColumnVisibility('test-table', columns).setVisible(['name', 'phone']);

        expect(useColumnVisibility('test-table', columns).visible.value).toEqual(['name', 'phone']);
    });

    it('keeps the stored keys in column order, not in click order', () => {
        const { visible, setVisible } = useColumnVisibility('test-table', columns);

        setVisible(['phone', 'city', 'name']);

        expect(visible.value).toEqual(['name', 'city', 'phone']);
    });

    it('forces a locked column back in when someone tries to hide it', () => {
        const { visible, setVisible } = useColumnVisibility('test-table', columns);

        setVisible(['city']);

        expect(visible.value).toEqual(['name', 'city']);
    });

    it('drops stored keys for columns that no longer exist', () => {
        window.localStorage.setItem('test-table', JSON.stringify(['name', 'fax']));

        expect(useColumnVisibility('test-table', columns).visible.value).toEqual(['name']);
    });

    it('falls back to the defaults when the stored value is not a list', () => {
        window.localStorage.setItem('test-table', '"nonsense"');

        expect(useColumnVisibility('test-table', columns).visible.value).toEqual(['name', 'city']);
    });
});
