import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import NotificationBell from '@/Components/NotificationBell.vue';
import { mountComponent, setPageProps } from '../helpers';
import { inertiaRouterMock } from '../setup';

// The bell renders relative timestamps, so both the assertions and the committed
// markup depend on "now". Freeze it — only Date, so promises still flush.
const FROZEN_NOW = new Date('2026-04-08T12:00:00');

function notification(overrides: Record<string, unknown> = {}) {
    return {
        id: 1,
        type: 'appointment_assigned',
        severity: 'info',
        actor: 'Bjoern Wilhelmsen',
        appointment_id: 42,
        appointment_title: 'Routinekontrolle Bergmann',
        appointment_start_at: '2026-04-08T09:00:00+02:00',
        url: '/calendar?appointment=42',
        data: {},
        read_at: null,
        created_at: new Date(Date.now() - 60_000).toISOString(),
        ...overrides,
    };
}

function stubFetch(notifications: unknown[], unread = notifications.length) {
    const fetchMock = vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({ notifications, unread }),
    });
    vi.stubGlobal('fetch', fetchMock);
    return fetchMock;
}

async function mountBell() {
    const wrapper = mountComponent(NotificationBell);
    await flushPromises();
    return wrapper;
}

describe('NotificationBell', () => {
    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(FROZEN_NOW);
        setPageProps({ translations: {} });
        inertiaRouterMock.visit.mockClear();
        inertiaRouterMock.post.mockClear();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('loads the unread count on mount', async () => {
        const fetchMock = stubFetch([notification(), notification({ id: 2 })], 2);
        const wrapper = await mountBell();

        expect(fetchMock).toHaveBeenCalledWith('/notifications', expect.anything());
        expect(wrapper.text()).toContain('2');
    });

    it('hides the badge when nothing is unread', async () => {
        stubFetch([notification({ read_at: '2026-04-08T10:00:00+02:00' })], 0);
        const wrapper = await mountBell();

        expect(wrapper.find('span.bg-danger').exists()).toBe(false);
    });

    it('caps the badge at 9+', async () => {
        stubFetch([notification()], 23);
        const wrapper = await mountBell();

        expect(wrapper.text()).toContain('9+');
    });

    it('writes a readable headline per notification type', async () => {
        stubFetch([
            notification({ id: 1, type: 'schedule_conflict', severity: 'critical' }),
            notification({ id: 2, type: 'comment_mention' }),
            notification({ id: 3, type: 'appointment_unassigned' }),
        ]);
        const wrapper = await mountBell();
        await wrapper.find('button').trigger('click');
        await flushPromises();

        const text = wrapper.text();
        expect(text).toContain('Double booking on Routinekontrolle Bergmann');
        expect(text).toContain('Bjoern Wilhelmsen mentioned you in Routinekontrolle Bergmann');
        expect(text).toContain('You were removed from Routinekontrolle Bergmann');
    });

    it('shows an empty state when there is nothing to do', async () => {
        stubFetch([], 0);
        const wrapper = await mountBell();
        await wrapper.find('button').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Nothing needs your attention.');
    });

    it('marks a notification read and navigates when clicked', async () => {
        stubFetch([notification()]);
        const wrapper = await mountBell();
        await wrapper.find('button').trigger('click');
        await flushPromises();

        const item = wrapper.findAll('button').find((b) => b.text().includes('Routinekontrolle'));
        await item!.trigger('click');

        expect(inertiaRouterMock.post).toHaveBeenCalled();
        expect(inertiaRouterMock.visit).toHaveBeenCalledWith('/calendar?appointment=42');
    });

    it('does not re-mark an already read notification', async () => {
        stubFetch([notification({ read_at: '2026-04-08T10:00:00+02:00' })], 0);
        const wrapper = await mountBell();
        await wrapper.find('button').trigger('click');
        await flushPromises();

        const item = wrapper.findAll('button').find((b) => b.text().includes('Routinekontrolle'));
        await item!.trigger('click');

        expect(inertiaRouterMock.post).not.toHaveBeenCalled();
        expect(inertiaRouterMock.visit).toHaveBeenCalled();
    });

    it('offers "mark all read" only while something is unread', async () => {
        stubFetch([notification()], 1);
        const unreadWrapper = await mountBell();
        await unreadWrapper.find('button').trigger('click');
        await flushPromises();
        expect(unreadWrapper.text()).toContain('Mark all read');

        vi.unstubAllGlobals();
        stubFetch([notification({ read_at: 'x' })], 0);
        const readWrapper = await mountBell();
        await readWrapper.find('button').trigger('click');
        await flushPromises();
        expect(readWrapper.text()).not.toContain('Mark all read');
    });

    it('highlights unread rows', async () => {
        stubFetch([notification()]);
        const wrapper = await mountBell();
        await wrapper.find('button').trigger('click');
        await flushPromises();

        const item = wrapper.findAll('button').find((b) => b.text().includes('Routinekontrolle'));
        expect(item!.classes().join(' ')).toContain('bg-navy-wash');
    });

    it('matches the committed markup with the panel open', async () => {
        stubFetch([
            notification({ id: 1, type: 'schedule_conflict', severity: 'critical', created_at: '2026-04-08T08:00:00+02:00' }),
        ]);
        const wrapper = await mountBell();
        await wrapper.find('button').trigger('click');
        await flushPromises();

        // The clock is frozen for this file, so the relative timestamp the bell
        // renders is itself part of the committed markup.
        expect(wrapper.html()).toContain('vor etwa 4 Stunden');
        expect(wrapper.html()).toMatchSnapshot();
    });
});
