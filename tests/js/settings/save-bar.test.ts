import { beforeEach, describe, expect, it } from 'vitest';
import StickySaveBar from '@/Components/StickySaveBar.vue';
import { mountComponent, setAuthedOwner } from '../helpers';

function mountBar(overrides: Record<string, unknown> = {}) {
    return mountComponent(StickySaveBar, {
        props: { dirty: false, processing: false, ...overrides },
    });
}

describe('StickySaveBar', () => {
    beforeEach(() => setAuthedOwner());

    it('says outright when there is something unsaved', () => {
        expect(mountBar({ dirty: true }).get('[data-testid="save-bar-state"]').text())
            .toContain('Unsaved changes');
    });

    it('reports a settled form as saved', () => {
        expect(mountBar({ dirty: false }).get('[data-testid="save-bar-state"]').text())
            .toContain('All changes saved');
    });

    it('confirms the write that just happened', () => {
        expect(mountBar({ dirty: false, saved: true }).get('[data-testid="save-bar-state"]').text())
            .toContain('Saved.');
    });

    it('keeps saving unavailable while nothing has changed', () => {
        expect(mountBar().get('[data-testid="save-bar-submit"]').attributes('disabled')).toBeDefined();
    });

    it('enables saving as soon as the form is dirty', () => {
        expect(mountBar({ dirty: true }).get('[data-testid="save-bar-submit"]').attributes('disabled'))
            .toBeUndefined();
    });

    it('blocks a second submit while one is in flight', () => {
        const bar = mountBar({ dirty: true, processing: true });

        expect(bar.get('[data-testid="save-bar-submit"]').attributes('disabled')).toBeDefined();
        expect(bar.text()).toContain('Saving...');
    });

    it('offers a way back only while there is something to discard', async () => {
        expect(mountBar().find('[data-testid="save-bar-discard"]').exists()).toBe(false);

        const dirty = mountBar({ dirty: true });
        await dirty.get('[data-testid="save-bar-discard"]').trigger('click');
        expect(dirty.emitted('discard')).toHaveLength(1);
    });

    it('submits the form it is told to, even from outside it', () => {
        expect(mountBar({ dirty: true, form: 'company-profile-form' })
            .get('[data-testid="save-bar-submit"]').attributes('form')).toBe('company-profile-form');
    });
});
