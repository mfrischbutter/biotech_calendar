import { computed, type ComputedRef } from 'vue';
import { usePage } from '@inertiajs/vue3';

export interface UsePermissions {
    /** True when the signed-in user holds this permission. Owners hold all of them. */
    may: (permission: string) => boolean;
    isOwner: ComputedRef<boolean>;
}

/**
 * The one place that answers "is this user allowed to…".
 *
 * HandleInertiaRequests shares the permission keys on every request, and owners
 * are shared with the full list, but the owner short-circuit is kept here too so
 * a future change to that payload cannot quietly lock an owner out.
 */
export function usePermissions(): UsePermissions {
    const page = usePage();

    const user = computed(() => page.props.auth?.user ?? null);
    const isOwner = computed(() => user.value?.role === 'owner');
    const keys = computed<string[]>(() => (user.value?.permissions ?? []) as string[]);

    return {
        may: (permission: string) => isOwner.value || keys.value.includes(permission),
        isOwner,
    };
}
