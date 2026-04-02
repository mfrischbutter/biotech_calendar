<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Switch } from '@/Components/ui/switch';
import { Badge } from '@/Components/ui/badge';
import { ref, watch } from 'vue';
import type { Employee } from '@/types';
import { useTrans } from '@/lib/use-trans';
import { usePermissionGroups, permissionGroupLabels } from '@/lib/use-permission-groups';

const { t } = useTrans();

const props = defineProps<{
    employee: Employee;
    availablePermissions: Record<string, string>;
}>();

const localPermissions = ref<string[]>([...props.employee.permissions]);
let saveTimer: ReturnType<typeof setTimeout> | null = null;

watch(
    () => props.employee.permissions,
    (newPerms) => {
        if (!saveTimer) {
            localPermissions.value = [...newPerms];
        }
    },
);

const { permissionGroups } = usePermissionGroups(() => props.availablePermissions);

function hasPermission(permission: string): boolean {
    return localPermissions.value.includes(permission);
}

function scheduleSync() {
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(() => {
        saveTimer = null;
        router.put(
            route('employees.permissions.update', props.employee.id),
            { permissions: localPermissions.value },
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => {
                    localPermissions.value = [...props.employee.permissions];
                },
            },
        );
    }, 300);
}

function togglePermission(permission: string) {
    localPermissions.value = hasPermission(permission)
        ? localPermissions.value.filter((p) => p !== permission)
        : [...localPermissions.value, permission];

    scheduleSync();
}

function toggleGroup(groupPerms: { key: string }[]) {
    const keys = groupPerms.map((p) => p.key);
    const allEnabled = keys.every((k) => hasPermission(k));
    localPermissions.value = allEnabled
        ? localPermissions.value.filter((p) => !keys.includes(p))
        : [...new Set([...localPermissions.value, ...keys])];

    scheduleSync();
}

function groupEnabledCount(perms: { key: string }[]): number {
    return perms.filter((p) => hasPermission(p.key)).length;
}
</script>

<template>
    <div class="py-4 px-6 space-y-5">
        <div
            v-for="(perms, groupKey) in permissionGroups"
            :key="groupKey"
        >
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <h4 class="text-sm font-semibold text-foreground">
                        {{ t(permissionGroupLabels[groupKey] ?? groupKey) }}
                    </h4>
                    <Badge variant="outline" class="text-xs font-normal">
                        {{ groupEnabledCount(perms) }} / {{ perms.length }}
                    </Badge>
                </div>
                <button
                    class="text-xs font-medium text-muted-foreground hover:text-foreground transition-colors"
                    @click="toggleGroup(perms)"
                >
                    {{ perms.every((p) => hasPermission(p.key)) ? t('Revoke all') : t('Grant all') }}
                </button>
            </div>
            <div class="rounded-lg border bg-background">
                <div
                    v-for="(perm, index) in perms"
                    :key="perm.key"
                    class="flex items-center justify-between px-4 py-3 cursor-pointer select-none hover:bg-muted/50"
                    :class="{ 'border-t': index > 0 }"
                    @click="togglePermission(perm.key)"
                >
                    <span class="text-sm">{{ perm.label }}</span>
                    <Switch
                        :checked="hasPermission(perm.key)"
                        class="pointer-events-none"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
