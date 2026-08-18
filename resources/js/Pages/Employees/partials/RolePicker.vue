<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Badge } from '@/Components/ui/badge';
import { Label } from '@/Components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { useTrans } from '@/lib/use-trans';
import type { Employee, StaffRole } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    employee: Employee;
    roles: StaffRole[];
}>();

const current = computed(() => (props.employee.staff_role ? String(props.employee.staff_role.id) : 'none'));

const description = computed(
    () => props.roles.find((role) => role.id === props.employee.staff_role?.id)?.description ?? null,
);

/**
 * Applying a preset replaces the individual permissions on the server, which is
 * why this is a PUT and not a local toggle.
 */
function assign(value: string): void {
    if (value === 'none' || value === current.value) return;

    router.put(
        route('employees.role.update', props.employee.id),
        { staff_role_id: Number(value) },
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <div class="space-y-2">
        <div class="flex items-center gap-2">
            <Label for="staff-role">{{ t('Role') }}</Label>
            <Badge v-if="employee.has_custom_permissions" variant="secondary" data-testid="custom-permissions-badge">
                {{ t('Customised') }}
            </Badge>
        </div>
        <Select :model-value="current" @update:model-value="assign(String($event))">
            <SelectTrigger id="staff-role" data-testid="role-picker">
                <SelectValue :placeholder="t('No role')" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="none" disabled>{{ t('No role') }}</SelectItem>
                <SelectItem v-for="role in roles" :key="role.id" :value="String(role.id)">
                    {{ role.name }}
                </SelectItem>
            </SelectContent>
        </Select>
        <p v-if="description" class="text-xs text-muted-foreground">{{ description }}</p>
    </div>
</template>
