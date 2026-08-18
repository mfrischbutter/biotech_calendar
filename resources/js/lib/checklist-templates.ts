import type { AppointmentKind, ChecklistItem, ChecklistTemplate } from '@/types';

/**
 * Templates offered for a contract: the ones tied to its service type plus the
 * ones that apply everywhere. Kind-specific templates come first — they are the
 * likelier pick.
 */
export function applicableTemplates(
    templates: ChecklistTemplate[],
    kind: AppointmentKind | null | undefined,
): ChecklistTemplate[] {
    const matching = templates.filter(tpl => tpl.kind !== null && tpl.kind === kind);
    const universal = templates.filter(tpl => tpl.kind === null);

    return [...matching, ...universal];
}

/** A template's items as fresh, unchecked checklist entries. */
export function templateToChecklist(template: ChecklistTemplate): ChecklistItem[] {
    return template.items.map(text => ({ text, checked: false }));
}

/** How much of a checklist is done, for the "Vor Ort 2/6" section count. */
export function checklistProgress(items: ChecklistItem[] | null | undefined): {
    done: number;
    total: number;
} {
    const list = items ?? [];

    return { done: list.filter(item => item.checked).length, total: list.length };
}
