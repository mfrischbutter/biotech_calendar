import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative, resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

/**
 * CLAUDE.md rule 4 — "every user-facing string goes through t()/__() and every
 * key exists in lang/de.json" — had no automated backing, and could not get any
 * from the component tests: those mount with `translations: {}`, so a key that
 * is missing from de.json renders as its own English text and every assertion
 * still passes.
 *
 * This walks the source for literal translation keys and checks them against
 * lang/de.json, and walks de.json for entries nothing refers to any more.
 */

const root = resolve(__dirname, '../..');

function walk(dir: string, extensions: string[]): string[] {
    const found: string[] = [];

    for (const entry of readdirSync(dir)) {
        if (entry === 'node_modules' || entry === 'vendor') continue;

        const path = join(dir, entry);
        if (statSync(path).isDirectory()) found.push(...walk(path, extensions));
        else if (extensions.some((extension) => entry.endsWith(extension))) found.push(path);
    }

    return found;
}

const german: Record<string, string> = JSON.parse(
    readFileSync(join(root, 'lang/de.json'), 'utf8'),
);

interface Usage {
    key: string;
    file: string;
}

function usages(): Usage[] {
    const found: Usage[] = [];

    const sources: Array<{ files: string[]; patterns: RegExp[] }> = [
        {
            // `t('…')` and `t("…")`, but not `format(`, `.t(` or similar.
            files: walk(join(root, 'resources/js'), ['.vue', '.ts']),
            patterns: [
                /(?<![\w$.])t\(\s*'((?:[^'\\]|\\.)*)'/g,
                /(?<![\w$.])t\(\s*"((?:[^"\\]|\\.)*)"/g,
            ],
        },
        {
            files: walk(join(root, 'app'), ['.php']),
            patterns: [
                /__\(\s*'((?:[^'\\]|\\.)*)'/g,
                /__\(\s*"((?:[^"\\]|\\.)*)"/g,
            ],
        },
    ];

    for (const { files, patterns } of sources) {
        for (const file of files) {
            const source = readFileSync(file, 'utf8');

            for (const pattern of patterns) {
                for (const match of source.matchAll(pattern)) {
                    found.push({
                        key: match[1].replace(/\\'/g, "'").replace(/\\"/g, '"'),
                        file: relative(root, file),
                    });
                }
            }
        }
    }

    return found;
}

/**
 * `__('auth.password')` resolves from lang/de/auth.php, not lang/de.json — the
 * two lookups are separate namespaces in Laravel.
 */
function isNamespacedPhpKey(key: string): boolean {
    return /^[a-z_]+\.[a-z_]+$/.test(key);
}

describe('lang/de.json covers every key the app asks for', () => {
    const all = usages();

    it('finds translation calls to check at all', () => {
        // A regex that silently stops matching would make this whole file pass.
        expect(all.length).toBeGreaterThan(200);
    });

    it('has a German translation for every literal key', () => {
        const missing = all
            .filter(({ key }) => !isNamespacedPhpKey(key) && !(key in german))
            .map(({ key, file }) => `${file}: ${JSON.stringify(key)}`);

        expect(missing, `Add these to lang/de.json:\n${missing.join('\n')}`).toEqual([]);
    });

    /** Words that really are spelled the same in German. */
    const sameInBothLanguages = new Set([
        'Branding', 'Dashboard', 'Details', 'Route', 'Start', 'Status',
    ]);

    it('never leaves a key untranslated by echoing the English back', () => {
        const untranslated = Object.entries(german)
            .filter(([key, value]) => key === value && /[a-z]{4}/.test(key))
            .map(([key]) => key)
            .filter((key) => !sameInBothLanguages.has(key));

        expect(untranslated, 'These entries are placeholders, not translations').toEqual([]);
    });

    /**
     * RecordTimeline sends field *labels*, not column names, and the client
     * hands each straight to t(). They never appear inside a literal `t('…')`,
     * so the sweep above cannot see them.
     */
    it('has a German translation for every timeline field label', () => {
        const source = readFileSync(join(root, 'app/Queries/RecordTimeline.php'), 'utf8');
        const block = source.match(/const FIELD_LABELS = \[([^\]]*)\]/);
        expect(block, 'FIELD_LABELS moved or was renamed').not.toBeNull();

        const labels = [...block![1].matchAll(/=> '([^']+)'/g)].map((match) => match[1]);
        expect(labels.length).toBeGreaterThan(5);

        for (const label of labels) {
            expect(german, `FIELD_LABELS emits ${JSON.stringify(label)}`).toHaveProperty(label);
        }
    });

    it('resolves namespaced PHP keys from a German language file', () => {
        const namespaced = all.filter(({ key }) => isNamespacedPhpKey(key));

        for (const { key, file } of namespaced) {
            const [namespace, entry] = key.split('.');
            const php = readFileSync(join(root, `lang/de/${namespace}.php`), 'utf8');

            expect(php, `${file} asks for ${key}`).toContain(`'${entry}' =>`);
        }
    });
});

describe('lang/de.json carries no dead weight', () => {
    /**
     * Some keys are looked up through a map (`t(STAGE_LABELS[stage])`), so a
     * usable "is this key referenced" check is whether the string appears
     * anywhere in the source at all, not whether it sits inside a `t(` call.
     */
    const corpus = [
        ...walk(join(root, 'resources/js'), ['.vue', '.ts']),
        ...walk(join(root, 'app'), ['.php']),
    ]
        .map((file) => readFileSync(file, 'utf8'))
        .join('\n');

    it('has no entry the source never mentions', () => {
        const dead = Object.keys(german).filter((key) => !corpus.includes(key));

        expect(dead, `Remove these from lang/de.json:\n${dead.join('\n')}`).toEqual([]);
    });
});
