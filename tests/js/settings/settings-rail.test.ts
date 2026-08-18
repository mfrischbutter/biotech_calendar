import { beforeEach, describe, expect, it } from 'vitest';
import SettingsSectionRail from '@/Pages/Settings/partials/SettingsSectionRail.vue';
import ChecklistTemplateManager from '@/Pages/Settings/partials/ChecklistTemplateManager.vue';
import { mountComponent, setAuthedOwner } from '../helpers';
import type { ChecklistTemplate, SettingsSection } from '@/types';

function mountRail(modelValue: SettingsSection = 'company') {
    return mountComponent(SettingsSectionRail, { props: { modelValue } });
}

describe('SettingsSectionRail', () => {
    beforeEach(() => setAuthedOwner());

    it('lists every section as a vertical rail, not three tabs', () => {
        const sections = mountRail().findAll('[data-section]').map(s => s.attributes('data-section'));

        expect(sections).toEqual(['company', 'branding', 'calendar', 'statuses', 'checklists']);
    });

    it('groups the sections by what they govern', () => {
        const text = mountRail().text();

        expect(text).toContain('Company');
        expect(text).toContain('Operations');
    });

    it('marks the open section for assistive tech, not just by colour', () => {
        const wrapper = mountRail('statuses');

        expect(wrapper.get('[data-section="statuses"]').attributes('aria-current')).toBe('page');
        expect(wrapper.get('[data-section="company"]').attributes('aria-current')).toBeUndefined();
    });

    it('asks the page to switch sections', async () => {
        const wrapper = mountRail();
        await wrapper.get('[data-section="checklists"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([['checklists']]);
    });
});

describe('ChecklistTemplateManager', () => {
    beforeEach(() => setAuthedOwner());

    const templates: ChecklistTemplate[] = [
        { id: 1, name: 'Routinekontrolle', kind: 'kundentermin', items: ['A', 'B'] },
        { id: 2, name: 'Erstbegehung', kind: null, items: ['C'] },
    ];

    it('lists the saved templates with their size and scope', () => {
        const rows = mountComponent(ChecklistTemplateManager, { props: { templates } })
            .findAll('[data-testid="template-row"]');

        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('Routinekontrolle');
        expect(rows[0].text()).toContain('2 items');
        expect(rows[1].text()).toContain('All service types');
    });

    it('offers an empty state rather than a bare list', () => {
        const wrapper = mountComponent(ChecklistTemplateManager, { props: { templates: [] } });

        expect(wrapper.find('[data-testid="template-row"]').exists()).toBe(false);
        expect(wrapper.text()).toContain('No checklist templates yet');
    });

    it('opens the editor from the empty state', async () => {
        const wrapper = mountComponent(ChecklistTemplateManager, { props: { templates: [] } });
        await wrapper.get('[data-testid="add-template"]').trigger('click');

        expect(wrapper.text()).toContain('Checklist items');
    });
});
