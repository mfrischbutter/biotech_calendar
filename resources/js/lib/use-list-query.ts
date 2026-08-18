import { computed, getCurrentInstance, onBeforeUnmount, ref, watch, type ComputedRef, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { usePageLoading } from '@/lib/use-page-loading';
import type { ListFilters, SortDirection, SortState } from '@/types';

/**
 * Everything a list screen keeps in the URL: the search box, the active saved
 * view, the sort, and any extra filter. Keeping it in the query string is the
 * whole point — a filtered list can be pasted into a chat and it still means
 * the same thing when a colleague opens it.
 */
export interface UseListQuery {
    search: Ref<string>;
    sort: ComputedRef<SortState>;
    view: ComputedRef<string>;
    /** True while an Inertia visit is in flight, for the table skeleton. */
    loading: Ref<boolean>;
    /** Extra single-value filters beyond search/sort/view, e.g. `role`. */
    params: Ref<Record<string, string | null>>;
    toggleSort: (key: string) => void;
    setView: (view: string) => void;
    setParam: (key: string, value: string | null) => void;
    reset: () => void;
}

const SEARCH_DEBOUNCE_MS = 300;

export function useListQuery(
    routeName: string,
    filters: ListFilters,
    extraKeys: string[] = [],
): UseListQuery {
    const search = ref(filters.search ?? '');
    const sortKey = ref(filters.sort);
    const sortDir = ref<SortDirection>(filters.dir);
    const view = ref(filters.view ?? 'all');
    // Shared with every other screen, so a fast answer never flashes a skeleton.
    const loading = usePageLoading();

    const given = filters as unknown as Record<string, string | null | undefined>;
    const params = ref<Record<string, string | null>>(
        Object.fromEntries(extraKeys.map((key) => [key, given[key] ?? null])),
    );

    function visit(): void {
        const query: Record<string, string> = {};

        if (search.value) query.search = search.value;
        if (sortKey.value) query.sort = sortKey.value;
        if (sortDir.value !== 'asc') query.dir = sortDir.value;
        if (view.value && view.value !== 'all') query.view = view.value;

        for (const [key, value] of Object.entries(params.value)) {
            if (value) query[key] = value;
        }

        router.get(route(routeName), query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    let debounce: ReturnType<typeof setTimeout>;
    watch(search, () => {
        clearTimeout(debounce);
        debounce = setTimeout(visit, SEARCH_DEBOUNCE_MS);
    });

    function toggleSort(key: string): void {
        if (sortKey.value === key) {
            sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
        } else {
            sortKey.value = key;
            sortDir.value = 'asc';
        }
        visit();
    }

    function setView(next: string): void {
        if (view.value === next) return;
        view.value = next;
        visit();
    }

    function setParam(key: string, value: string | null): void {
        params.value = { ...params.value, [key]: value };
        visit();
    }

    function reset(): void {
        search.value = '';
        view.value = 'all';
        params.value = Object.fromEntries(Object.keys(params.value).map((key) => [key, null]));
        clearTimeout(debounce);
        visit();
    }

    if (getCurrentInstance()) {
        onBeforeUnmount(() => clearTimeout(debounce));
    }

    return {
        search,
        sort: computed(() => ({ key: sortKey.value, dir: sortDir.value })),
        view: computed(() => view.value),
        loading,
        params,
        toggleSort,
        setView,
        setParam,
        reset,
    };
}
