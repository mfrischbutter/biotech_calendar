import { beforeEach, describe, expect, it } from 'vitest';
import FormSectionHeader from '@/Components/FormSectionHeader.vue';
import AppointmentFormSections from '@/Pages/Calendar/partials/AppointmentFormSections.vue';
import { mountComponent, setAuthedOwner, makeStatus } from '../helpers';
import type { ChecklistItem, ChecklistTemplate } from '@/types';

const template: ChecklistTemplate = {
    id: 3,
    name: 'Routinekontrolle',
    kind: 'kundentermin',
    items: ['Köderboxen prüfen', 'Befall dokumentieren'],
};

function mountSections(overrides: Record<string, unknown> = {}) {
    return mountComponent(AppointmentFormSections, {
        props: {
            contracts: [],
            clients: [],
            employees: [],
            statuses: [makeStatus()],
            checklistTemplates: [template],
            isEditing: false,
            isSeries: false,
            contractId: '',
            workerIds: [],
            statusId: 'none',
            startDate: '2026-04-08',
            startTime: '09:00',
            endDate: '2026-04-08',
            endTime: '10:00',
            notes: '',
            checklist: [] as ChecklistItem[],
            isRecurring: false,
            recurrenceType: 'weekly',
            recurrenceInterval: 1,
            recurrenceEnd: '',
            errors: {},
            ...overrides,
        },
    });
}

describe('FormSectionHeader', () => {
    beforeEach(() => setAuthedOwner());

    it('labels a section and states how full it is', () => {
        const wrapper = mountComponent(FormSectionHeader, { props: { label: 'Vor Ort', count: '2/6' } });

        expect(wrapper.text()).toContain('Vor Ort');
        expect(wrapper.get('[data-testid="section-count"]').text()).toBe('2/6');
    });

    it('omits the count when there is nothing to count', () => {
        const wrapper = mountComponent(FormSectionHeader, { props: { label: 'Notizen' } });

        expect(wrapper.find('[data-testid="section-count"]').exists()).toBe(false);
    });
});

describe('AppointmentFormSections', () => {
    beforeEach(() => setAuthedOwner());

    it('labels the notes and on-site sections', () => {
        const text = mountSections().text();

        expect(text).toContain('Notes');
        expect(text).toContain('On site');
    });

    it('counts the checked items against the whole checklist', () => {
        const wrapper = mountSections({
            checklist: [
                { text: 'A', checked: true },
                { text: 'B', checked: false },
                { text: 'C', checked: true },
            ],
        });

        expect(wrapper.get('[data-testid="section-count"]').text()).toBe('2/3');
    });

    it('shows no count while the checklist is empty', () => {
        expect(mountSections().find('[data-testid="section-count"]').exists()).toBe(false);
    });

    it('shows the recurrence rule as a chip on the form itself', () => {
        const wrapper = mountSections({ isRecurring: true, recurrenceType: 'biweekly' });

        expect(wrapper.get('[data-testid="recurrence-chip"]').text()).toContain('Every 2 weeks');
    });

    it('locks the rule to a read-only chip once the series exists', () => {
        const wrapper = mountSections({ isEditing: true, isSeries: true, recurrenceType: 'monthly' });
        const chip = wrapper.get('[data-testid="recurrence-chip"]');

        expect(chip.element.tagName).not.toBe('BUTTON');
        expect(chip.text()).toContain('Monthly');
    });
});
