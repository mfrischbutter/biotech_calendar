import { beforeEach, describe, expect, it } from 'vitest';
import FileDropzone from '@/Components/FileDropzone.vue';
import { mountComponent, setAuthedOwner } from '../helpers';

function mountZone(overrides: Record<string, unknown> = {}) {
    return mountComponent(FileDropzone, {
        props: { formats: ['png', 'jpg'], maxSizeKb: 2048, ...overrides },
    });
}

function file(name: string, sizeBytes: number): File {
    const f = new File(['x'], name, { type: 'image/png' });
    Object.defineProperty(f, 'size', { value: sizeBytes });

    return f;
}

function drop(wrapper: ReturnType<typeof mountZone>, dropped: File) {
    return wrapper.get('[data-testid="file-dropzone"]').trigger('drop', {
        dataTransfer: { files: [dropped] },
    });
}

describe('FileDropzone', () => {
    beforeEach(() => setAuthedOwner());

    it('replaces the bare file input with a drop target', () => {
        const wrapper = mountZone();

        expect(wrapper.find('[data-testid="file-dropzone"]').exists()).toBe(true);
        // The native input is still there for the click path, but out of sight.
        expect(wrapper.get('input[type="file"]').classes()).toContain('sr-only');
    });

    it('states the accepted formats and the size ceiling', () => {
        const text = mountZone().text();

        expect(text).toContain('PNG, JPG');
        expect(text).toContain('2 MB');
    });

    it('restricts the native picker to the same formats', () => {
        expect(mountZone().get('input[type="file"]').attributes('accept')).toBe('.png,.jpg');
    });

    it('accepts a dropped file of the right type and size', async () => {
        const wrapper = mountZone();
        const dropped = file('logo.png', 1000);

        await drop(wrapper, dropped);

        expect(wrapper.emitted('select')).toEqual([[dropped]]);
        expect(wrapper.emitted('reject')).toBeUndefined();
    });

    it('refuses a file whose type is not offered', async () => {
        const wrapper = mountZone();

        await drop(wrapper, file('brand.pdf', 1000));

        expect(wrapper.emitted('select')).toBeUndefined();
        expect(wrapper.emitted('reject')?.[0][0]).toBe('This file type is not supported.');
    });

    it('refuses a file over the advertised limit', async () => {
        const wrapper = mountZone();

        await drop(wrapper, file('huge.png', 2048 * 1024 + 1));

        expect(wrapper.emitted('select')).toBeUndefined();
        expect(wrapper.emitted('reject')?.[0][0]).toBe('This file is too large.');
    });

    it('names the file the user picked', () => {
        expect(mountZone({ selectedName: 'logo.png' }).get('[data-testid="dropzone-selection"]').text())
            .toBe('logo.png');
    });

    it('is reachable without a mouse', () => {
        const zone = mountZone().get('[data-testid="file-dropzone"]');

        expect(zone.attributes('role')).toBe('button');
        expect(zone.attributes('tabindex')).toBe('0');
    });
});
