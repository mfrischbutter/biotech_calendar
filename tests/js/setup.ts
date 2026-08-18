import { vi } from 'vitest';
import { config } from '@vue/test-utils';

/**
 * Shared, mutable Inertia page state for tests.
 * Tests can write to this via `setPageProps()` from ./helpers.
 */
export const pageState: { props: Record<string, unknown> } = {
    props: {
        auth: { user: null },
        translations: {},
        locale: 'de',
        companyBranding: null,
    },
};

// Ziggy's global `route()` helper is installed as a Vue plugin at runtime;
// in tests we stub it to a predictable string so assertions stay readable.
const routeStub = (name?: string, params?: unknown) => {
    if (name === undefined) {
        return { current: (_pattern?: string) => false };
    }
    const suffix =
        params === undefined || params === null
            ? ''
            : typeof params === 'object'
              ? `/${Object.values(params as Record<string, unknown>).join('/')}`
              : `/${String(params)}`;
    return `/${name.replace(/\./g, '/')}${suffix}`;
};

// `route()` is called both as `route('x')` and as `route().current('x')`.
const route = Object.assign(routeStub, {
    current: (_pattern?: string) => false,
});

// eslint-disable-next-line @typescript-eslint/no-explicit-any
(globalThis as any).route = route;

// ZiggyVue installs `route` on `app.config.globalProperties`, which is how
// templates resolve it. Mirror that so `:href="route(...)"` works in tests.
config.global.mocks = { ...config.global.mocks, route };
config.global.provide = { ...config.global.provide };

export const inertiaRouterMock = {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    visit: vi.fn(),
    reload: vi.fn(),
    on: vi.fn(() => () => {}),
};

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h, reactive } = await import('vue');

    const Link = defineComponent({
        name: 'Link',
        props: { href: { type: String, default: '' }, as: { type: String, default: 'a' } },
        setup: (props, { slots, attrs }) =>
            () => h('a', { href: props.href, ...attrs }, slots.default?.()),
    });

    const Head = defineComponent({
        name: 'Head',
        props: { title: { type: String, default: '' } },
        setup: () => () => null,
    });

    function useForm<T extends Record<string, unknown>>(data: T) {
        const form = reactive({
            ...data,
            errors: {} as Record<string, string>,
            processing: false,
            recentlySuccessful: false,
            isDirty: false,
            get: vi.fn(),
            post: vi.fn(),
            put: vi.fn(),
            patch: vi.fn(),
            delete: vi.fn(),
            reset: vi.fn(),
            clearErrors: vi.fn(),
            defaults: vi.fn(),
            transform: vi.fn(() => form),
        });
        return form;
    }

    return {
        Link,
        Head,
        useForm,
        usePage: () => pageState,
        router: inertiaRouterMock,
    };
});
