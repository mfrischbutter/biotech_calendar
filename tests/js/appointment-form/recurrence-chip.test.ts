import { beforeEach, describe, expect, it } from 'vitest';
import RecurrenceChip from '@/Pages/Calendar/partials/RecurrenceChip.vue';
import { mountComponent, setAuthedOwner } from '../helpers';

function mountChip(overrides: Record<string, unknown> = {}) {
    return mountComponent(RecurrenceChip, {
        props: {
            isRecurring: false,
            recurrenceType: 'weekly',
            recurrenceInterval: 1,
            recurrenceEnd: '',
            ...overrides,
        },
    });
}

describe('RecurrenceChip', () => {
    beforeEach(() => setAuthedOwner());

    it('states the rule instead of hiding it behind an ellipsis', () => {
        const chip = mountChip({ isRecurring: true, recurrenceType: 'custom', recurrenceInterval: 4 })
            .get('[data-testid="recurrence-chip"]');

        expect(chip.text()).toContain('Every 4 weeks');
        expect(chip.text()).not.toContain('...');
    });

    it('says a one-off appointment does not repeat', () => {
        expect(mountChip().get('[data-testid="recurrence-chip"]').text()).toContain('Does not repeat');
    });

    it('names each fixed rule', () => {
        expect(mountChip({ isRecurring: true, recurrenceType: 'biweekly' })
            .get('[data-testid="recurrence-chip"]').text()).toContain('Every 2 weeks');
        expect(mountChip({ isRecurring: true, recurrenceType: 'monthly' })
            .get('[data-testid="recurrence-chip"]').text()).toContain('Monthly');
    });

    it('is an editable control while the appointment is still being created', () => {
        const chip = mountChip({ isRecurring: true });

        expect(chip.get('[data-testid="recurrence-chip"]').element.tagName).toBe('BUTTON');
    });

    it('shows an existing series rule as a read-only badge', () => {
        const wrapper = mountChip({ isRecurring: true, recurrenceType: 'monthly', readonly: true });
        const chip = wrapper.get('[data-testid="recurrence-chip"]');

        expect(chip.element.tagName).not.toBe('BUTTON');
        expect(chip.text()).toContain('Monthly');
    });
});
