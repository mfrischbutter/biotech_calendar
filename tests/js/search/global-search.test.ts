import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import GlobalSearch from '@/Components/GlobalSearch.vue';
import { mountComponent, setPageProps } from '../helpers';
import { inertiaRouterMock } from '../setup';

const RESPONSE = {
    query: 'bergm',
    groups: [
        {
            key: 'clients',
            label: 'Kunden',
            items: [
                {
                    id: 1,
                    type: 'client',
                    title: 'Klaus Bergmann',
                    subtitle: 'Baeckerei Bergmann · Muenchen',
                    url: '/clients/1',
                },
            ],
        },
        {
            key: 'actions',
            label: 'Aktionen',
            items: [
                {
                    id: 'new-appointment',
                    type: 'action',
                    title: 'Neuer Termin',
                    subtitle: 'Kalender oeffnen',
                    url: '/calendar?new=1',
                    icon: 'calendar-plus',
                },
            ],
        },
    ],
};

function stubFetch(body: unknown = RESPONSE) {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: async () => body });
    vi.stubGlobal('fetch', fetchMock);
    return fetchMock;
}

describe('GlobalSearch', () => {
    beforeEach(() => {
        setPageProps({ translations: {} });
        inertiaRouterMock.visit.mockClear();
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('renders a visible, labelled search field rather than a hidden shortcut', () => {
        stubFetch();
        const wrapper = mountComponent(GlobalSearch);
        const input = wrapper.find('input[type="search"]');

        expect(input.exists()).toBe(true);
        expect(input.attributes('placeholder')).toContain('Search clients');
        expect(input.attributes('role')).toBe('combobox');
    });

    it('loads the available actions as soon as the field is focused', async () => {
        const fetchMock = stubFetch();
        const wrapper = mountComponent(GlobalSearch);

        await wrapper.find('input').trigger('focus');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith('/search?q=', expect.anything());
        expect(wrapper.text()).toContain('Neuer Termin');
    });

    it('shows results grouped under their headings', async () => {
        stubFetch();
        const wrapper = mountComponent(GlobalSearch);

        await wrapper.find('input').trigger('focus');
        await flushPromises();

        expect(wrapper.text()).toContain('Kunden');
        expect(wrapper.text()).toContain('Klaus Bergmann');
        expect(wrapper.text()).toContain('Aktionen');
    });

    it('navigates when a result is clicked', async () => {
        stubFetch();
        const wrapper = mountComponent(GlobalSearch);

        await wrapper.find('input').trigger('focus');
        await flushPromises();
        await wrapper.findAll('button')[0].trigger('click');

        expect(inertiaRouterMock.visit).toHaveBeenCalledWith('/clients/1');
    });

    it('clears the field after navigating, so the next search starts fresh', async () => {
        stubFetch();
        const wrapper = mountComponent(GlobalSearch);
        const input = wrapper.find('input');

        await input.setValue('bergm');
        await input.trigger('focus');
        await flushPromises();
        await wrapper.findAll('button')[0].trigger('click');

        expect((wrapper.find('input').element as HTMLInputElement).value).toBe('');
    });

    it('supports arrow keys and Enter for people who prefer the keyboard', async () => {
        stubFetch();
        const wrapper = mountComponent(GlobalSearch);
        const input = wrapper.find('input');

        await input.trigger('focus');
        await flushPromises();

        await input.trigger('keydown', { key: 'ArrowDown' });
        await input.trigger('keydown', { key: 'Enter' });

        expect(inertiaRouterMock.visit).toHaveBeenCalledWith('/calendar?new=1');
    });

    it('closes on Escape', async () => {
        stubFetch();
        const wrapper = mountComponent(GlobalSearch);
        const input = wrapper.find('input');

        await input.trigger('focus');
        await flushPromises();
        expect(wrapper.text()).toContain('Klaus Bergmann');

        await input.trigger('keydown', { key: 'Escape' });
        expect(wrapper.text()).not.toContain('Klaus Bergmann');
    });

    it('tells the user when nothing matched', async () => {
        stubFetch({ query: 'zzz', groups: [] });
        const wrapper = mountComponent(GlobalSearch);

        await wrapper.find('input').setValue('zzz');
        await wrapper.find('input').trigger('focus');
        await flushPromises();
        await new Promise((r) => setTimeout(r, 250));
        await flushPromises();

        expect(wrapper.text()).toContain('Nothing found');
    });

    it('surfaces a failure instead of silently showing nothing', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));
        const wrapper = mountComponent(GlobalSearch);

        await wrapper.find('input').trigger('focus');
        await flushPromises();

        expect(wrapper.text()).toContain('Search is unavailable');
    });

    it('matches the committed markup with results open', async () => {
        stubFetch();
        const wrapper = mountComponent(GlobalSearch);

        await wrapper.find('input').trigger('focus');
        await flushPromises();

        expect(wrapper.html()).toMatchSnapshot();
    });
});
