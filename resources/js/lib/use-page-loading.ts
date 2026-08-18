import { getCurrentInstance, onBeforeUnmount, ref, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';

/** Below this, a skeleton is a flicker rather than an explanation. */
export const LOADING_DELAY_MS = 140;

interface VisitDetail {
    visit?: { method?: string };
}

/**
 * True while the screen is waiting for a fresh page payload.
 *
 * Only navigational GET visits count: saving an appointment must not blank out
 * the board the user is looking at. The flag is delayed a little so a fast
 * answer never flashes a skeleton on the way past.
 */
export function usePageLoading(delayMs: number = LOADING_DELAY_MS): Ref<boolean> {
    const loading = ref(false);
    let timer: ReturnType<typeof setTimeout> | undefined;

    const stopStart = router.on('start', (event: unknown) => {
        const method = (event as CustomEvent<VisitDetail>)?.detail?.visit?.method;
        if (method !== undefined && method.toLowerCase() !== 'get') return;

        clearTimeout(timer);
        timer = setTimeout(() => {
            loading.value = true;
        }, delayMs);
    });

    const stopFinish = router.on('finish', () => {
        clearTimeout(timer);
        loading.value = false;
    });

    function stop(): void {
        clearTimeout(timer);
        stopStart();
        stopFinish();
    }

    if (getCurrentInstance()) onBeforeUnmount(stop);

    return loading;
}
