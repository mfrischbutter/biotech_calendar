import { describe, expect, it } from 'vitest';
import { useOptimistic } from '@/lib/use-optimistic';

interface Slot {
    start: string;
}

describe('useOptimistic', () => {
    it('applies the value immediately', () => {
        const store = useOptimistic<Slot>();

        store.apply(7, { start: '11:00' });

        expect(store.get(7)).toEqual({ start: '11:00' });
        expect(store.has(7)).toBe(true);
        expect(store.size()).toBe(1);
    });

    it('rolls back to nothing when there was nothing before', () => {
        const store = useOptimistic<Slot>();

        const handle = store.apply(7, { start: '11:00' });
        handle.rollback();

        expect(store.has(7)).toBe(false);
        expect(store.size()).toBe(0);
    });

    it('rolls back to the previous value when one was showing', () => {
        const store = useOptimistic<Slot>();

        store.apply(7, { start: '10:00' });
        const second = store.apply(7, { start: '11:00' });
        second.rollback();

        expect(store.get(7)).toEqual({ start: '10:00' });
    });

    it('ignores a rollback that arrives after the value was committed', () => {
        const store = useOptimistic<Slot>();

        const handle = store.apply(7, { start: '11:00' });
        handle.commit();
        handle.rollback();

        expect(store.get(7)).toEqual({ start: '11:00' });
    });

    it('never clobbers a newer write with a stale rollback', () => {
        const store = useOptimistic<Slot>();

        const first = store.apply(7, { start: '11:00' });
        store.apply(7, { start: '13:00' });

        // The first request fails late; the second drag is what the user sees.
        first.rollback();

        expect(store.get(7)).toEqual({ start: '13:00' });
    });

    it('keeps entries for different ids apart', () => {
        const store = useOptimistic<Slot>();

        const seven = store.apply(7, { start: '11:00' });
        store.apply(8, { start: '12:00' });
        seven.rollback();

        expect(store.has(7)).toBe(false);
        expect(store.get(8)).toEqual({ start: '12:00' });
    });

    it('clears everything when fresh server data arrives', () => {
        const store = useOptimistic<Slot>();

        store.apply(7, { start: '11:00' });
        store.apply(8, { start: '12:00' });
        store.clear();

        expect(store.size()).toBe(0);
    });
});
