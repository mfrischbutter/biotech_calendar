import { ref, watch, type Ref } from 'vue';
import type { SearchGroup, SearchResponse } from '@/types';

export interface UseGlobalSearch {
    query: Ref<string>;
    groups: Ref<SearchGroup[]>;
    loading: Ref<boolean>;
    error: Ref<boolean>;
    /** Flat list of items in display order — the basis for arrow-key movement. */
    flatItems: Ref<SearchGroup['items']>;
    activeIndex: Ref<number>;
    move: (delta: number) => void;
    reset: () => void;
    run: (value: string) => Promise<void>;
}

/**
 * Fetches grouped search results for the top-bar search field.
 *
 * Requests are debounced and the response is discarded if a newer query has
 * been issued in the meantime, so fast typing can never leave stale results
 * on screen.
 */
export function useGlobalSearch(endpoint = '/search', debounceMs = 200): UseGlobalSearch {
    const query = ref('');
    const groups = ref<SearchGroup[]>([]);
    const loading = ref(false);
    const error = ref(false);
    const flatItems = ref<SearchGroup['items']>([]);
    const activeIndex = ref(-1);

    let timer: ReturnType<typeof setTimeout> | undefined;
    let latest = 0;

    async function run(value: string): Promise<void> {
        const request = ++latest;
        loading.value = true;
        error.value = false;

        try {
            const response = await fetch(`${endpoint}?q=${encodeURIComponent(value)}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) throw new Error(String(response.status));

            const payload = (await response.json()) as SearchResponse;

            if (request !== latest) return; // a newer query already went out

            groups.value = payload.groups ?? [];
            flatItems.value = groups.value.flatMap((group) => group.items);
            activeIndex.value = flatItems.value.length > 0 ? 0 : -1;
        } catch {
            if (request !== latest) return;
            error.value = true;
            groups.value = [];
            flatItems.value = [];
            activeIndex.value = -1;
        } finally {
            if (request === latest) loading.value = false;
        }
    }

    watch(query, (value) => {
        clearTimeout(timer);
        timer = setTimeout(() => void run(value), debounceMs);
    });

    function move(delta: number): void {
        const count = flatItems.value.length;
        if (count === 0) {
            activeIndex.value = -1;
            return;
        }
        activeIndex.value = (activeIndex.value + delta + count) % count;
    }

    function reset(): void {
        clearTimeout(timer);
        latest++;
        query.value = '';
        groups.value = [];
        flatItems.value = [];
        activeIndex.value = -1;
        loading.value = false;
        error.value = false;
    }

    return { query, groups, loading, error, flatItems, activeIndex, move, reset, run };
}
