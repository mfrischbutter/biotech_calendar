import { describe, expect, it } from 'vitest';
import { recurrenceDescriptor } from '@/lib/recurrence';

describe('recurrenceDescriptor', () => {
    it('says so plainly when nothing repeats', () => {
        expect(recurrenceDescriptor(false, 'weekly', 1)).toEqual({ key: 'Does not repeat' });
    });

    it('names the fixed rules', () => {
        expect(recurrenceDescriptor(true, 'weekly', 1).key).toBe('Weekly');
        expect(recurrenceDescriptor(true, 'biweekly', 1).key).toBe('Every 2 weeks');
        expect(recurrenceDescriptor(true, 'monthly', 1).key).toBe('Monthly');
    });

    it('spells out a custom interval in weeks', () => {
        expect(recurrenceDescriptor(true, 'custom', 4)).toEqual({
            key: 'Every :count weeks',
            params: { count: '4' },
        });
    });

    it('collapses custom intervals onto the rule that already has a name', () => {
        expect(recurrenceDescriptor(true, 'custom', 1).key).toBe('Weekly');
        expect(recurrenceDescriptor(true, 'custom', 2).key).toBe('Every 2 weeks');
    });

    it('treats a missing or nonsensical interval as weekly', () => {
        expect(recurrenceDescriptor(true, 'custom', 0).key).toBe('Weekly');
        expect(recurrenceDescriptor(true, 'custom', -3).key).toBe('Weekly');
    });

    it('falls back rather than inventing a rule for an unknown type', () => {
        expect(recurrenceDescriptor(true, 'yearly', 1).key).toBe('Does not repeat');
    });
});
