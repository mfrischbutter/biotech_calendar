import { beforeEach, describe, expect, it, vi } from 'vitest';
import { inertiaRouterMock } from '../setup';
import ClientIdentityHeader from '@/Pages/Clients/partials/ClientIdentityHeader.vue';
import ContractIdentityHeader from '@/Pages/Contracts/partials/ContractIdentityHeader.vue';
import { mountComponent, setAuthedOwner } from '../helpers';
import type { Client, ClientFacts, Contract, ContractFacts } from '@/types';

const client = {
    id: 7,
    salutation: 'Herr',
    first_name: 'Klaus',
    last_name: 'Bergmann',
    name: 'Klaus Bergmann',
    company_name: 'Baeckerei Bergmann',
    billing_name: null,
    street: 'Sendlinger Str. 45',
    zip: '80331',
    city: 'Muenchen',
    latitude: null,
    longitude: null,
    place_id: null,
    phone: '+49 89 234567',
    email: 'bergmann@example.de',
    notes: null,
    access_notes: null,
} as Client;

const clientFacts: ClientFacts = {
    since: '2024-03-04T09:00:00.000Z',
    address: 'Sendlinger Str. 45, 80331 Muenchen',
    map_url: 'https://www.google.com/maps/dir/?api=1&destination=Sendlinger',
    access_notes: null,
    badges: [
        { key: 'active_contract', tone: 'success' },
        { key: 'kundentermin', tone: 'muted' },
    ],
    stats: { contracts: 2, open_contracts: 1, appointments: 9, upcoming: 1, last: null, next: '2026-04-10T08:00:00.000Z' },
    next_appointment: null,
    series: null,
};

const contract = {
    id: 3,
    contract_number: 'A-1000',
    title: 'Routinekontrolle',
    kind: 'kundentermin',
    description: null,
    access_notes: null,
    street: null,
    zip: null,
    city: null,
    latitude: null,
    longitude: null,
    place_id: null,
    clients: [{ id: 7, name: 'Klaus Bergmann', first_name: 'Klaus', last_name: 'Bergmann', company_name: 'Baeckerei Bergmann' }],
} as Contract;

const contractFacts: ContractFacts = {
    since: '2026-03-02T09:00:00.000Z',
    address: null,
    map_url: null,
    access_notes: null,
    stage: 'ready_to_invoice',
    progress: { done: 3, total: 4, percent: 75 },
    team: [{ id: 2, name: 'Markus Weber' }],
    badges: [{ key: 'kundentermin', tone: 'muted' }],
    stats: { appointments: 4, upcoming: 1, clients: 1, last: null, next: null },
    next_appointment: null,
    series: null,
};

describe('ClientIdentityHeader', () => {
    beforeEach(() => {
        setAuthedOwner();
        inertiaRouterMock.delete.mockClear();
    });

    it('puts the five actions someone came for in the header', () => {
        const wrapper = mountComponent(ClientIdentityHeader, { props: { client, facts: clientFacts } });

        expect(wrapper.find('[data-testid="action-plan"]').attributes('href')).toContain('/calendar/index');
        expect(wrapper.find('[data-testid="action-call"]').attributes('href')).toBe('tel:+49 89 234567');
        expect(wrapper.find('[data-testid="action-email"]').attributes('href')).toBe('mailto:bergmann@example.de');
        expect(wrapper.find('[data-testid="action-route"]').attributes('href')).toBe(clientFacts.map_url);
        expect(wrapper.find('[data-testid="action-report"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="action-more"]').exists()).toBe(true);
    });

    it('hides the contact actions a customer has no details for', () => {
        const wrapper = mountComponent(ClientIdentityHeader, {
            props: { client: { ...client, phone: null, email: null }, facts: { ...clientFacts, map_url: null } },
        });

        expect(wrapper.find('[data-testid="action-call"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="action-email"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="action-route"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="action-plan"]').exists()).toBe(true);
    });

    it('shows identity, tenure and three compact figures', () => {
        const wrapper = mountComponent(ClientIdentityHeader, { props: { client, facts: clientFacts } });

        expect(wrapper.text()).toContain('Klaus Bergmann');
        expect(wrapper.text()).toContain('Baeckerei Bergmann');
        expect(wrapper.find('[data-testid="record-tenure"]').text()).toContain('März 2024');
        expect(wrapper.findAll('[data-testid="record-badge"]').map((b) => b.text())).toEqual([
            'Active contract',
            'On-site work',
        ]);

        const stats = wrapper.find('[data-testid="record-stats"]');
        expect(stats.text()).toContain('2');
        expect(stats.text()).toContain('9');
        expect(stats.text()).toContain('10. Apr. 2026');
    });

    it('prints the dossier without touching the server', () => {
        const print = vi.fn();
        vi.stubGlobal('print', print);
        window.print = print;

        const wrapper = mountComponent(ClientIdentityHeader, { props: { client, facts: clientFacts } });
        wrapper.find('[data-testid="action-report"]').trigger('click');

        expect(print).toHaveBeenCalled();
        expect(inertiaRouterMock.delete).not.toHaveBeenCalled();
    });
});

describe('ContractIdentityHeader', () => {
    beforeEach(() => setAuthedOwner());

    it('leads with the stage and shows the progress bar', () => {
        const wrapper = mountComponent(ContractIdentityHeader, { props: { contract, facts: contractFacts } });

        expect(wrapper.findAll('[data-testid="record-badge"]').map((b) => b.text())).toEqual([
            'Ready to invoice',
            'On-site work',
        ]);
        expect(wrapper.find('[data-testid="contract-progress"]').text()).toContain('75%');
        expect(wrapper.find('[data-testid="record-stats"]').text()).toContain('3/4');
    });

    it('names the contract number and its clients', () => {
        const wrapper = mountComponent(ContractIdentityHeader, { props: { contract, facts: contractFacts } });

        expect(wrapper.text()).toContain('Routinekontrolle');
        expect(wrapper.text()).toContain('A-1000 · Baeckerei Bergmann');
    });

    it('drops the route action when there is no address', () => {
        const wrapper = mountComponent(ContractIdentityHeader, { props: { contract, facts: contractFacts } });

        expect(wrapper.find('[data-testid="action-route"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="action-plan"]').attributes('href')).toContain('/calendar/index');
    });
});
