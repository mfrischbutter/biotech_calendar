import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { PIPELINE_ORDER, STAGE_DOT, STAGE_LABELS, STAGE_PILL } from '@/lib/pipeline-stages';
import { RECORD_BADGE_LABELS, RECORD_BADGE_TONE, STAGE_BADGE_TONE } from '@/lib/record-badges';
import { TIMELINE_ACTION_LABELS, TIMELINE_LABELS, TIMELINE_TONE, TIMELINE_TYPES } from '@/lib/timeline';

/**
 * The server and the client keep two hand-written copies of the same
 * vocabularies: pipeline stages, identity badge keys and timeline event types.
 * A key the server can emit but the client has no wording for renders as a raw
 * database value ("unassigned_work") with every other test still green.
 *
 * These tests read the PHP constants directly, so adding a stage or a badge on
 * the backend fails here until the frontend has words for it.
 */

const root = resolve(__dirname, '../..');

function php(relativePath: string): string {
    return readFileSync(resolve(root, relativePath), 'utf8');
}

/** Pull the string literals out of a `const NAME = [ ... ];` block. */
function phpConstArray(source: string, name: string): string[] {
    const match = source.match(new RegExp(`const ${name} = \\[([^\\]]*)\\]`));
    if (!match) throw new Error(`Could not find PHP constant ${name}`);

    return [...match[1].matchAll(/'([^']+)'/g)].map((m) => m[1]);
}

/** Resolve `self::STAGE_ACTIVE` style references to their literal values. */
function phpConstRefs(source: string, name: string): string[] {
    const match = source.match(new RegExp(`const ${name} = \\[([^\\]]*)\\]`));
    if (!match) throw new Error(`Could not find PHP constant ${name}`);

    return [...match[1].matchAll(/self::([A-Z_]+)/g)].map((m) => {
        const literal = source.match(new RegExp(`const ${m[1]} = '([^']+)'`));
        if (!literal) throw new Error(`Could not resolve self::${m[1]}`);

        return literal[1];
    });
}

describe('pipeline stages mirror App\\Models\\Status', () => {
    const status = php('app/Models/Status.php');
    const allStages = phpConstRefs(status, 'STAGES');
    const pipelineStages = phpConstRefs(status, 'PIPELINE_STAGES');

    it('has a label, a dot colour and a pill for every stage the server can emit', () => {
        expect(Object.keys(STAGE_LABELS).sort()).toEqual([...allStages].sort());
        expect(Object.keys(STAGE_DOT).sort()).toEqual([...allStages].sort());
        expect(Object.keys(STAGE_PILL).sort()).toEqual([...allStages].sort());
        expect(Object.keys(STAGE_BADGE_TONE).sort()).toEqual([...allStages].sort());
    });

    it('orders the pipeline exactly as the server does', () => {
        expect(PIPELINE_ORDER).toEqual(pipelineStages);
    });

    it('names no stage the server does not have', () => {
        for (const stage of Object.keys(STAGE_LABELS)) {
            expect(allStages, `STAGE_LABELS has a dead entry: ${stage}`).toContain(stage);
        }
    });
});

describe('record badges mirror the detail queries', () => {
    /** Every `['key' => 'x', ...]` the two detail queries can put in a badge. */
    const emitted = new Set<string>();

    for (const file of ['app/Queries/ClientDetailQuery.php', 'app/Queries/ContractDetailQuery.php']) {
        for (const match of php(file).matchAll(/'key' => '([a-z_]+)'/g)) {
            emitted.add(match[1]);
        }
    }

    // Both queries also emit `$contract->kind` / `$kind` verbatim.
    for (const kind of phpConstArray(php('app/Models/Contract.php'), 'KINDS')) {
        emitted.add(kind);
    }

    it('has wording for every badge key the server can emit', () => {
        for (const key of emitted) {
            expect(
                Object.keys(RECORD_BADGE_LABELS),
                `RECORD_BADGE_LABELS is missing "${key}", so the raw key would render`,
            ).toContain(key);
        }
    });

    it('has a class for every tone the server can emit', () => {
        const tones = new Set<string>();

        for (const file of ['app/Queries/ClientDetailQuery.php', 'app/Queries/ContractDetailQuery.php']) {
            for (const match of php(file).matchAll(/'tone' => '([a-z]+)'/g)) {
                tones.add(match[1]);
            }
        }

        expect(tones.size).toBeGreaterThan(0);
        for (const tone of tones) {
            expect(Object.keys(RECORD_BADGE_TONE)).toContain(tone);
        }
    });

    it('carries no wording for a badge nobody emits', () => {
        const known = new Set([...emitted, ...Object.keys(STAGE_LABELS)]);

        for (const key of Object.keys(RECORD_BADGE_LABELS)) {
            expect(known, `RECORD_BADGE_LABELS has a dead entry: ${key}`).toContain(key);
        }
    });
});

describe('timeline vocabulary mirrors App\\Queries\\RecordTimeline', () => {
    const timeline = php('app/Queries/RecordTimeline.php');

    it('has a label and a tone for every event type', () => {
        expect(Object.keys(TIMELINE_LABELS).sort()).toEqual([...TIMELINE_TYPES].sort());
        expect(Object.keys(TIMELINE_TONE).sort()).toEqual([...TIMELINE_TYPES].sort());
    });

    it('has wording for every event type the server emits', () => {
        // The query emits `'type' => self::APPOINTMENT` etc; resolve the refs.
        const emitted = new Set(
            [...timeline.matchAll(/'type' => self::([A-Z_]+)/g)].map((m) => {
                const literal = timeline.match(new RegExp(`const ${m[1]} = '([^']+)'`));
                if (!literal) throw new Error(`Could not resolve self::${m[1]}`);

                return literal[1];
            }),
        );

        expect(emitted.size).toBeGreaterThan(0);
        for (const type of emitted) {
            expect(TIMELINE_TYPES).toContain(type);
        }
    });

    it('has wording for every activity action the observer records', () => {
        const actions = new Set(
            [...php('app/Observers/AppointmentObserver.php').matchAll(/'action' => '([a-z]+)'/g)]
                .map((m) => m[1]),
        );

        expect(actions.size).toBeGreaterThan(0);
        for (const action of actions) {
            expect(Object.keys(TIMELINE_ACTION_LABELS)).toContain(action);
        }
    });
});
