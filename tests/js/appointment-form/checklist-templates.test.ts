import { describe, expect, it } from 'vitest';
import { applicableTemplates, checklistProgress, templateToChecklist } from '@/lib/checklist-templates';
import type { ChecklistTemplate } from '@/types';

function tpl(overrides: Partial<ChecklistTemplate> = {}): ChecklistTemplate {
    return {
        id: 1,
        name: 'Routinekontrolle',
        kind: 'kundentermin',
        items: ['Köderboxen prüfen'],
        ...overrides,
    };
}

describe('applicableTemplates', () => {
    it('offers the matching service type before the universal ones', () => {
        const matching = tpl({ id: 1, kind: 'kundentermin' });
        const universal = tpl({ id: 2, kind: null });
        const other = tpl({ id: 3, kind: 'ohne_termin' });

        expect(applicableTemplates([universal, other, matching], 'kundentermin').map(t => t.id))
            .toEqual([1, 2]);
    });

    it('leaves only the universal templates when the contract has no kind', () => {
        const universal = tpl({ id: 2, kind: null });

        expect(applicableTemplates([tpl({ id: 1 }), universal], null).map(t => t.id)).toEqual([2]);
    });

    it('returns nothing when no template fits', () => {
        expect(applicableTemplates([tpl({ kind: 'ohne_termin' })], 'kundentermin')).toEqual([]);
    });
});

describe('templateToChecklist', () => {
    it('turns items into fresh, unchecked entries', () => {
        expect(templateToChecklist(tpl({ items: ['A', 'B'] }))).toEqual([
            { text: 'A', checked: false },
            { text: 'B', checked: false },
        ]);
    });
});

describe('checklistProgress', () => {
    it('counts what is done against the total', () => {
        expect(checklistProgress([
            { text: 'A', checked: true },
            { text: 'B', checked: false },
            { text: 'C', checked: true },
        ])).toEqual({ done: 2, total: 3 });
    });

    it('treats an absent checklist as empty', () => {
        expect(checklistProgress(null)).toEqual({ done: 0, total: 0 });
    });
});
