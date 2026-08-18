import { ref, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';

export interface UseBulkDelete {
    processing: Ref<boolean>;
    destroy: (ids: number[]) => Promise<void>;
}

/**
 * Delete the selected rows through the existing per-row destroy route.
 *
 * Requests are sent one at a time on purpose: each delete can fail on its own
 * (a client with appointments, an employee who is an owner) and the server
 * decides, not the browser. The list is reloaded once at the end rather than
 * after every request.
 */
export function useBulkDelete(routeName: string): UseBulkDelete {
    const processing = ref(false);

    async function destroy(ids: number[]): Promise<void> {
        if (ids.length === 0 || processing.value) return;

        processing.value = true;

        for (const id of ids) {
            await new Promise<void>((resolve) => {
                router.delete(route(routeName, id), {
                    preserveScroll: true,
                    preserveState: true,
                    onFinish: () => resolve(),
                });
            });
        }

        processing.value = false;
        router.reload();
    }

    return { processing, destroy };
}
