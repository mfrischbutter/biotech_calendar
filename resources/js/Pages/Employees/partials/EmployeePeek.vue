<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { ChevronDown, Mail } from 'lucide-vue-next';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Button } from '@/Components/ui/button';
import SidePeek from '@/Components/SidePeek.vue';
import PermissionToggles from './PermissionToggles.vue';
import RolePicker from './RolePicker.vue';
import { useTrans } from '@/lib/use-trans';
import { initials } from '@/lib/utils';
import type { Employee, StaffRole } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    employee: Employee | null;
    roles: StaffRole[];
    availablePermissions: Record<string, string>;
}>();

defineEmits<{
    close: [];
    next: [];
    prev: [];
    openRecord: [];
}>();

const showPermissions = ref(false);

// Moving to another employee should not carry the expanded state across.
watch(() => props.employee?.id, () => {
    showPermissions.value = false;
});

const permissionCount = computed(() => Object.keys(props.availablePermissions).length);

function utilisationClass(percent: number): string {
    if (percent >= 90) return 'bg-danger';
    if (percent >= 70) return 'bg-navy';

    return 'bg-success';
}
</script>

<template>
    <SidePeek
        :open="employee !== null"
        :title="employee?.name ?? ''"
        :subtitle="employee?.staff_role?.name ?? t('No role')"
        :open-label="t('Open week')"
        @close="$emit('close')"
        @next="$emit('next')"
        @prev="$emit('prev')"
        @open-record="$emit('openRecord')"
    >
        <template #actions>
            <Button v-if="employee?.email" size="sm" variant="outline" as="a" :href="`mailto:${employee.email}`">
                <Mail class="mr-2 h-4 w-4" />
                {{ t('Email') }}
            </Button>
        </template>

        <div v-if="employee" class="flex items-center gap-3">
            <Avatar size="base" class="bg-navy-wash">
                <AvatarFallback class="bg-navy-wash text-sm font-semibold text-navy">
                    {{ initials(employee.name) }}
                </AvatarFallback>
            </Avatar>
            <div class="min-w-0">
                <p class="truncate font-medium text-foreground">{{ employee.name }}</p>
                <p class="truncate text-sm text-muted-foreground">{{ employee.email }}</p>
            </div>
        </div>

        <div v-if="employee" class="grid grid-cols-2 gap-3">
            <div class="rounded-lg border border-border p-3">
                <p class="text-xs text-muted-foreground">{{ t('Appointments this week') }}</p>
                <p class="text-xl font-semibold tabular-nums text-foreground">
                    {{ employee.appointments_this_week }}
                </p>
            </div>
            <div class="rounded-lg border border-border p-3">
                <p class="text-xs text-muted-foreground">{{ t('Utilisation this week') }}</p>
                <p class="text-xl font-semibold tabular-nums text-foreground">{{ employee.utilisation }}%</p>
                <span class="mt-1 block h-1.5 overflow-hidden rounded-full bg-muted">
                    <span
                        class="block h-full rounded-full"
                        :class="utilisationClass(employee.utilisation)"
                        :style="{ width: `${employee.utilisation}%` }"
                    />
                </span>
            </div>
        </div>

        <RolePicker v-if="employee" :employee="employee" :roles="roles" />

        <div v-if="employee" class="rounded-lg border border-border">
            <button
                type="button"
                class="flex w-full items-center justify-between px-3 py-2 text-sm font-medium"
                data-testid="toggle-permissions"
                @click="showPermissions = !showPermissions"
            >
                <span>
                    {{ t('Show all permissions') }}
                    <span class="ml-1 text-xs font-normal text-muted-foreground">
                        {{ employee.permissions.length }} / {{ permissionCount }}
                    </span>
                </span>
                <ChevronDown class="h-4 w-4 transition-transform" :class="showPermissions ? 'rotate-180' : ''" />
            </button>
            <PermissionToggles
                v-if="showPermissions"
                :employee="employee"
                :available-permissions="availablePermissions"
            />
        </div>
    </SidePeek>
</template>
