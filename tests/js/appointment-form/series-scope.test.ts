import { beforeEach, describe, expect, it } from 'vitest';
import SeriesScopeChoice from '@/Pages/Calendar/partials/SeriesScopeChoice.vue';
import AppointmentFormHeader from '@/Pages/Calendar/partials/AppointmentFormHeader.vue';
import { mountComponent, setAuthedOwner } from '../helpers';
import type { Contract, SeriesScope } from '@/types';

function contract(overrides: Partial<Contract> = {}): Contract {
    return {
        id: 7,
        contract_number: 'A-1042',
        title: 'Routinekontrolle',
        kind: 'kundentermin',
        description: null,
        street: null,
        zip: null,
        city: null,
        latitude: null,
        longitude: null,
        place_id: null,
        clients: [
            { id: 1, name: 'Klaus Bergmann', first_name: 'Klaus', last_name: 'Bergmann', company_name: null },
        ],
        ...overrides,
    };
}

describe('SeriesScopeChoice', () => {
    beforeEach(() => setAuthedOwner());

    it('defaults the highlight to the single occurrence', () => {
        const wrapper = mountComponent(SeriesScopeChoice, { props: { modelValue: 'single' as SeriesScope } });

        expect(wrapper.get('[data-scope="single"]').attributes('aria-pressed')).toBe('true');
        expect(wrapper.get('[data-scope="series"]').attributes('aria-pressed')).toBe('false');
    });

    it('makes the whole-series choice explicit rather than implied', async () => {
        const wrapper = mountComponent(SeriesScopeChoice, { props: { modelValue: 'single' as SeriesScope } });

        expect(wrapper.text()).toContain('This appointment only');
        expect(wrapper.text()).toContain('Whole series');

        await wrapper.get('[data-scope="series"]').trigger('click');
        expect(wrapper.emitted('update:modelValue')).toEqual([['series']]);
    });
});

describe('AppointmentFormHeader', () => {
    beforeEach(() => setAuthedOwner());

    it('names the contract and its client above the form', () => {
        const wrapper = mountComponent(AppointmentFormHeader, {
            props: { contract: contract(), isSeries: false },
        });

        expect(wrapper.get('[data-testid="contract-context"]').text()).toContain('A-1042');
        expect(wrapper.get('[data-testid="contract-context"]').text()).toContain('Routinekontrolle');
        expect(wrapper.get('[data-testid="client-context"]').text()).toContain('Klaus Bergmann');
    });

    it('flags a series so the scope choice is never a surprise', () => {
        const wrapper = mountComponent(AppointmentFormHeader, {
            props: { contract: contract(), isSeries: true },
        });

        expect(wrapper.get('[data-testid="series-badge"]').text()).toContain('Part of a series');
    });

    it('stays silent for a one-off appointment', () => {
        const wrapper = mountComponent(AppointmentFormHeader, {
            props: { contract: contract(), isSeries: false },
        });

        expect(wrapper.find('[data-testid="series-badge"]').exists()).toBe(false);
    });

    it('renders nothing before a contract is picked', () => {
        const wrapper = mountComponent(AppointmentFormHeader, {
            props: { contract: null, isSeries: false },
        });

        expect(wrapper.find('[data-testid="contract-context"]').exists()).toBe(false);
    });
});
