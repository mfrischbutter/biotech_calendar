import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Login from '@/Pages/Auth/Login.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { mountComponent, setPageProps } from '../helpers';

// GuestLayout stamps the current year into its footer, so the committed markup
// would go stale on 1 January. Freeze the clock for every test in this file.
const FROZEN_NOW = new Date('2026-04-08T12:00:00');

describe('Login screen', () => {
    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(FROZEN_NOW);
        setPageProps({ translations: {} });
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('renders email and password fields with correct autocomplete hints', () => {
        const wrapper = mountComponent(Login, { props: { canResetPassword: true } });

        const email = wrapper.find('#email');
        const password = wrapper.find('#password');

        expect(email.exists()).toBe(true);
        expect(email.attributes('type')).toBe('email');
        expect(email.attributes('autocomplete')).toBe('username');

        expect(password.exists()).toBe(true);
        expect(password.attributes('type')).toBe('password');
        expect(password.attributes('autocomplete')).toBe('current-password');
    });

    it('offers the password reset link only when the backend allows it', () => {
        const withReset = mountComponent(Login, { props: { canResetPassword: true } });
        expect(withReset.text()).toContain('Forgot your password?');

        const withoutReset = mountComponent(Login, { props: { canResetPassword: false } });
        expect(withoutReset.text()).not.toContain('Forgot your password?');
    });

    it('shows the status banner when the backend passes one', () => {
        const wrapper = mountComponent(Login, {
            props: { status: 'Passwort zurueckgesetzt.' },
        });
        expect(wrapper.text()).toContain('Passwort zurueckgesetzt.');
    });

    it('translates its copy through the shared translations prop', () => {
        setPageProps({ translations: { 'Log in': 'Anmelden', 'Welcome back.': 'Willkommen zurueck.' } });
        const wrapper = mountComponent(Login, {});

        expect(wrapper.text()).toContain('Anmelden');
        expect(wrapper.text()).toContain('Willkommen zurueck.');
    });

    it('carries no Breeze-era hardcoded greys or indigo focus ring', () => {
        const html = mountComponent(Login, { props: { canResetPassword: true } }).html();

        expect(html).not.toMatch(/text-gray-\d/);
        expect(html).not.toMatch(/bg-gray-\d/);
        expect(html).not.toMatch(/indigo/);
    });

    it('uses the brand action colour for the submit button', () => {
        const wrapper = mountComponent(Login, {});
        const submit = wrapper.find('button[type="submit"]');

        expect(submit.exists()).toBe(true);
        // shadcn's default Button variant maps to --primary, which is the brand green.
        expect(submit.classes().join(' ')).toContain('bg-primary');
    });

    it('matches the committed markup', () => {
        const wrapper = mountComponent(Login, { props: { canResetPassword: true } });
        expect(wrapper.html()).toMatchSnapshot();
    });
});

describe('GuestLayout', () => {
    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(FROZEN_NOW);
        setPageProps({ translations: {} });
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('renders the navy brand panel alongside the form', () => {
        const wrapper = mountComponent(GuestLayout, {
            props: { title: 'Log in', description: 'Welcome back.' },
            slots: { default: '<p>form goes here</p>' },
        });

        expect(wrapper.find('aside').classes()).toContain('bg-navy');
        expect(wrapper.text()).toContain('form goes here');
        expect(wrapper.find('h2').text()).toBe('Log in');
    });

    it('omits the description paragraph when none is given', () => {
        const wrapper = mountComponent(GuestLayout, {
            props: { title: 'Log in' },
            slots: { default: '<p>x</p>' },
        });

        expect(wrapper.find('header p').exists()).toBe(false);
    });
});
