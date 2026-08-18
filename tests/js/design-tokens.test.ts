import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const css = readFileSync(resolve(__dirname, '../../resources/css/app.css'), 'utf-8');

function tokensIn(selector: string): Record<string, string> {
    // Grab the first block for the selector and pull `--name: value;` pairs out of it.
    const start = css.indexOf(`${selector} {`);
    if (start === -1) throw new Error(`No block found for selector "${selector}"`);

    let depth = 0;
    let end = start;
    for (let i = css.indexOf('{', start); i < css.length; i++) {
        if (css[i] === '{') depth++;
        if (css[i] === '}') {
            depth--;
            if (depth === 0) {
                end = i;
                break;
            }
        }
    }

    const block = css.slice(start, end);
    const out: Record<string, string> = {};
    for (const m of block.matchAll(/--([a-z0-9-]+):\s*([^;]+);/g)) {
        out[m[1]] = m[2].trim();
    }
    return out;
}

const light = tokensIn(':root');
const dark = tokensIn('.dark');

describe('brand palette', () => {
    it('uses the brand green (#3B9438) as the action colour', () => {
        // #3B9438 -> hsl(118, 45%, 40%)
        expect(light.primary).toBe('118 45% 40%');
        expect(light['primary-foreground']).toBe('0 0% 100%');
    });

    it('uses the brand navy (#0E457A) for structure and selection', () => {
        // #0E457A -> hsl(209, 79%, 27%)
        expect(light.navy).toBe('209 79% 27%');
        expect(light.ring).toContain('209');
    });

    it('keeps the wordmark indigo (#30295D) available for display type', () => {
        // #30295D -> hsl(248, 39%, 26%)
        expect(light['navy-ink']).toBe('248 39% 26%');
    });

    it('has a white background, as specified', () => {
        expect(light.background).toBe('0 0% 100%');
    });

    it('no longer ships the stock shadcn near-black primary', () => {
        expect(light.primary).not.toBe('0 0% 9%');
        expect(Object.values(light)).not.toContain('0 0% 3.9%');
    });
});

describe('semantic state colours', () => {
    it('defines success / warning / danger / info independently of the accent', () => {
        for (const name of ['success', 'warning', 'danger', 'info']) {
            expect(light[name], `--${name} must be defined`).toBeTruthy();
            expect(light[`${name}-wash`], `--${name}-wash must be defined`).toBeTruthy();
            expect(light[`${name}-foreground`], `--${name}-foreground must be defined`).toBeTruthy();
        }
    });

    it('keeps danger distinct from the action colour so red only ever means danger', () => {
        expect(light.danger).not.toBe(light.primary);
    });
});

describe('theme parity', () => {
    // Geometry, not colour — deliberately shared by both themes.
    const THEME_INDEPENDENT = ['radius'];

    const lightColours = Object.keys(light).filter((k) => !THEME_INDEPENDENT.includes(k));
    const darkColours = Object.keys(dark).filter((k) => !THEME_INDEPENDENT.includes(k));

    it('defines every light colour token in the dark theme too', () => {
        const missing = lightColours.filter((k) => !(k in dark));
        expect(missing, `tokens missing from .dark: ${missing.join(', ')}`).toEqual([]);
    });

    it('does not leave any colour token defined only in the dark theme', () => {
        const extra = darkColours.filter((k) => !(k in light));
        expect(extra, `tokens missing from :root: ${extra.join(', ')}`).toEqual([]);
    });
});

describe('token golden file', () => {
    it('matches the committed light palette', () => {
        expect(light).toMatchSnapshot();
    });

    it('matches the committed dark palette', () => {
        expect(dark).toMatchSnapshot();
    });
});
