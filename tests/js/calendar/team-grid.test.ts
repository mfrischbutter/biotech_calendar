import { beforeEach, describe, expect, it } from 'vitest';
import TeamGrid from '@/Pages/Calendar/partials/TeamGrid.vue';
import { mountComponent, setAuthedOwner, makeAppointment, makeWorker } from '../helpers';
import type { Appointment, CalendarEmployee, CalendarTotals } from '@/types';

const DATES = ['2026-04-06', '2026-04-07', '2026-04-08'];

const anna = makeWorker({ id: 7, first_name: 'Anna', last_name: 'Berg', name: 'Anna Berg' });
const max = makeWorker({ id: 9, first_name: 'Max', last_name: 'Kern', name: 'Max Kern' });

const employees: CalendarEmployee[] = [
    { id: 7, first_name: 'Anna', last_name: 'Berg', name: 'Anna Berg' },
    { id: 9, first_name: 'Max', last_name: 'Kern', name: 'Max Kern' },
];

const totals: CalendarTotals = {
    perEmployee: [
        { id: null, appointments: 1, minutes: 60 },
        { id: 7, appointments: 2, minutes: 150 },
        { id: 9, appointments: 0, minutes: 0 },
    ],
    perDay: [
        { date: '2026-04-06', appointments: 2, minutes: 150 },
        { date: '2026-04-07', appointments: 0, minutes: 0 },
        { date: '2026-04-08', appointments: 1, minutes: 60 },
    ],
};

function mountTeam(appointments: Appointment[], overrides: Record<string, unknown> = {}) {
    return mountComponent(TeamGrid, {
        props: { appointments, employees, showUnassigned: true, dates: DATES, totals, ...overrides },
    });
}

describe('TeamGrid', () => {
    beforeEach(() => setAuthedOwner());

    it('puts unowned work in the first row, where it cannot be missed', () => {
        const wrapper = mountTeam([]);
        const rowHeaders = wrapper.findAll('tbody td:first-child');

        expect(rowHeaders[0].text()).toContain('Unassigned');
        expect(rowHeaders[1].text()).toContain('Anna Berg');
    });

    it('prints how much each person carries', () => {
        const wrapper = mountTeam([]);

        expect(wrapper.get('[data-testid="row-total-7"]').text()).toBe('2 · 2,5 h');
        expect(wrapper.get('[data-testid="row-total-unassigned"]').text()).toBe('1 · 1 h');
    });

    it('shows a dash for a person with nothing booked', () => {
        expect(mountTeam([]).get('[data-testid="row-total-9"]').text()).toBe('–');
    });

    it('prints how loaded each day is', () => {
        const wrapper = mountTeam([]);

        expect(wrapper.get('[data-testid="column-total-2026-04-06"]').text()).toBe('2 · 2,5 h');
        expect(wrapper.get('[data-testid="column-total-2026-04-07"]').text()).toBe('–');
    });

    it('keeps the employee column pinned while the days scroll', () => {
        const wrapper = mountTeam([]);

        expect(wrapper.get('thead th:first-child').classes()).toContain('sticky');
        expect(wrapper.get('tbody td:first-child').classes()).toContain('sticky');
    });

    it('shows an appointment in every assigned technician row', () => {
        const shared = makeAppointment({ id: 1, date: '2026-04-06', workers: [anna, max] });
        const wrapper = mountTeam([shared]);

        expect(wrapper.findAll('[data-appointment-id="1"]')).toHaveLength(2);
    });

    it('flags a double booking', () => {
        const appts = [
            makeAppointment({ id: 1, date: '2026-04-06', start: '09:00', end: '10:00', workers: [anna] }),
            makeAppointment({ id: 2, date: '2026-04-06', start: '09:30', end: '10:30', workers: [anna] }),
        ];
        const wrapper = mountTeam(appts, { conflicts: { 1: [2], 2: [1] } });

        expect(wrapper.findAll('[data-conflict="true"]')).toHaveLength(2);
        expect(wrapper.findAll('[data-conflict="true"]')[0].classes().join(' ')).toContain('ring-danger');
    });

    it('marks a repeating appointment', () => {
        const series = {
            ...makeAppointment({ id: 3, date: '2026-04-06', workers: [anna] }),
            parent_id: 99,
        } as Appointment;

        expect(mountTeam([series]).findAll('[data-testid="recurring-marker"]').length).toBeGreaterThan(0);
    });

    it('leads with the customer', () => {
        const wrapper = mountTeam([makeAppointment({ id: 4, date: '2026-04-06', title: 'Nachkontrolle', workers: [anna] })]);
        const card = wrapper.get('[data-appointment-id="4"]');

        expect(card.text().indexOf('Baeckerei Bergmann')).toBeLessThan(card.text().indexOf('Nachkontrolle'));
    });

    it('drops the unassigned row when the filter did not ask for it', () => {
        const wrapper = mountTeam([], { showUnassigned: false });
        const rowHeaders = wrapper.findAll('tbody td:first-child');

        expect(rowHeaders[0].text()).toContain('Anna Berg');
        expect(wrapper.text()).not.toContain('Unassigned');
    });

    it('draws a row only for the people it was handed', () => {
        const wrapper = mountTeam([], { employees: [employees[0]], showUnassigned: false });

        expect(wrapper.findAll('tbody tr')).toHaveLength(1);
        expect(wrapper.text()).not.toContain('Max Kern');
    });

    /*
     * A job shared by a filtered-in and a filtered-out technician still belongs
     * on the board once — in the row that survived, not silently duplicated or
     * dropped along with the other person.
     */
    it('keeps a shared job in the surviving row', () => {
        const shared = makeAppointment({ id: 1, date: '2026-04-06', workers: [anna, max] });
        const wrapper = mountTeam([shared], { employees: [employees[0]], showUnassigned: false });

        expect(wrapper.findAll('[data-appointment-id="1"]')).toHaveLength(1);
    });

    it('offers a scroll affordance once the days run past the edge', async () => {
        const wrapper = mountTeam([]);
        const scroller = wrapper.get('div.overflow-auto');

        // jsdom reports every element as 0x0, so the grid starts out "at the end".
        expect(wrapper.find('[data-testid="scroll-affordance"]').exists()).toBe(false);

        Object.defineProperty(scroller.element, 'scrollWidth', { value: 1200, configurable: true });
        Object.defineProperty(scroller.element, 'clientWidth', { value: 600, configurable: true });
        await scroller.trigger('scroll');

        expect(wrapper.find('[data-testid="scroll-affordance"]').exists()).toBe(true);
    });
});
