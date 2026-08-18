import { beforeEach, describe, expect, it } from 'vitest';
import AccessNotesCard from '@/Components/record/AccessNotesCard.vue';
import NextAppointmentCard from '@/Components/record/NextAppointmentCard.vue';
import SeriesCard from '@/Components/record/SeriesCard.vue';
import ContractTeamCard from '@/Pages/Contracts/partials/ContractTeamCard.vue';
import { mountComponent, setAuthedOwner } from '../helpers';
import type { NextAppointmentFact, SeriesFact } from '@/types';

describe('AccessNotesCard', () => {
    beforeEach(() => setAuthedOwner());

    it('pins the access details where a technician will see them', () => {
        const wrapper = mountComponent(AccessNotesCard, {
            props: { notes: 'Schluesseltresor am Tor, Code 4711.' },
        });

        expect(wrapper.find('[data-testid="access-notes-card"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Schluesseltresor am Tor, Code 4711.');
    });

    it('does not exist at all when there are no access notes', () => {
        expect(mountComponent(AccessNotesCard, { props: { notes: null } }).html()).toBe('<!--v-if-->');
        expect(mountComponent(AccessNotesCard, { props: { notes: '' } }).html()).toBe('<!--v-if-->');
    });
});

describe('NextAppointmentCard', () => {
    beforeEach(() => setAuthedOwner());

    const next: NextAppointmentFact = {
        id: 12,
        title: 'Routinekontrolle',
        contract_number: 'A-1000',
        start_at: '2026-04-10T08:00:00',
        end_at: '2026-04-10T09:30:00',
        workers: [{ id: 2, name: 'Markus Weber' }],
        status: { name: 'Erste Massnahme', color: '#F59E0B', stage: 'active' },
        url: '/calendar?appointment=12',
    };

    it('states when, who and in what state', () => {
        const wrapper = mountComponent(NextAppointmentCard, { props: { appointment: next } });

        expect(wrapper.find('a').attributes('href')).toBe('/calendar?appointment=12');
        expect(wrapper.text()).toContain('08:00–09:30');
        expect(wrapper.text()).toContain('Markus Weber');
        expect(wrapper.text()).toContain('Erste Massnahme');
    });

    it('says so when nothing is booked', () => {
        const wrapper = mountComponent(NextAppointmentCard, { props: { appointment: null } });

        expect(wrapper.text()).toContain('No appointment scheduled');
    });

    it('calls out work nobody is on', () => {
        const wrapper = mountComponent(NextAppointmentCard, {
            props: { appointment: { ...next, workers: [] } },
        });

        expect(wrapper.text()).toContain('Unassigned');
    });
});

describe('SeriesCard', () => {
    beforeEach(() => setAuthedOwner());

    const series: SeriesFact = {
        appointment_id: 4,
        recurrence_type: 'biweekly',
        recurrence_interval: 2,
        recurrence_end: '2026-06-30',
        started_at: '2026-04-10T08:00:00',
        occurrences: 6,
        url: '/calendar?appointment=4',
    };

    it('spells out the rule and when it stops', () => {
        const wrapper = mountComponent(SeriesCard, { props: { series } });

        expect(wrapper.text()).toContain('Every 2 weeks');
        expect(wrapper.text()).toContain('30. Juni 2026');
        expect(wrapper.text()).toContain('6');
    });

    it('calls an open series open-ended', () => {
        const wrapper = mountComponent(SeriesCard, {
            props: { series: { ...series, recurrence_end: null } },
        });

        expect(wrapper.text()).toContain('Open-ended');
    });

    it('is absent for a one-off appointment', () => {
        expect(mountComponent(SeriesCard, { props: { series: null } }).html()).toBe('<!--v-if-->');
    });
});

describe('ContractTeamCard', () => {
    beforeEach(() => setAuthedOwner());

    it('lists whoever is booked on the job', () => {
        const wrapper = mountComponent(ContractTeamCard, {
            props: { team: [{ id: 2, name: 'Markus Weber' }] },
        });

        expect(wrapper.text()).toContain('Markus Weber');
        expect(wrapper.text()).toContain('MW');
    });

    it('admits when nobody is on it', () => {
        const wrapper = mountComponent(ContractTeamCard, { props: { team: [] } });

        expect(wrapper.text()).toContain('Nobody assigned yet');
    });
});
