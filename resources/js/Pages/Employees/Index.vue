<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Plus, Trash2, Users } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { DropdownMenuItem, DropdownMenuSeparator } from '@/Components/ui/dropdown-menu';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/Components/ui/empty';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import ConfirmDeleteDialog from '@/Components/ConfirmDeleteDialog.vue';
import { DataTable, DataTableRowActions, DataTableToolbar } from '@/Components/DataTable';
import AddEmployeeDialog from './partials/AddEmployeeDialog.vue';
import EmployeePeek from './partials/EmployeePeek.vue';
import { useBulkDelete } from '@/lib/use-bulk-delete';
import { useColumnVisibility } from '@/lib/use-column-visibility';
import { useListQuery } from '@/lib/use-list-query';
import { useSidePeek } from '@/lib/use-side-peek';
import { useTrans } from '@/lib/use-trans';
import { initials } from '@/lib/utils';
import type {
    DataTableColumn,
    Employee,
    FilterChip,
    ListFilters,
    Paginated,
    StaffRole,
} from '@/types';

const { t } = useTrans();

const props = defineProps<{
    employees: Paginated<Employee>;
    availablePermissions: Record<string, string>;
    roles: StaffRole[];
    filters: ListFilters;
}>();

const columns: DataTableColumn[] = [
    { key: 'employee', label: t('Employee'), sortKey: 'name', locked: true },
    { key: 'role', label: t('Role') },
    { key: 'appointments', label: t('Appointments this week'), sortKey: 'appointments' },
    { key: 'utilisation', label: t('Utilisation'), headerClass: 'w-40' },
    { key: 'email', label: t('Email'), sortKey: 'email', hidden: true },
];

const list = useListQuery('employees.index', props.filters, ['role']);
const { visible, setVisible } = useColumnVisibility('biotech-employees-columns', columns);
const peek = useSidePeek<Employee>(() => props.employees.data);
const bulk = useBulkDelete('employees.destroy');

const deleteTarget = ref<Employee | null>(null);
const bulkTarget = ref<number[]>([]);

const rows = computed(() => props.employees.data);

const roleFilter = computed(() => list.params.value.role ?? 'all');

const chips = computed<FilterChip[]>(() => {
    const slug = list.params.value.role;
    if (!slug) return [];

    const name = slug === 'none' ? t('No role') : props.roles.find((role) => role.slug === slug)?.name ?? slug;

    return [{ key: 'role', label: t('Role: :name', { name }) }];
});

function utilisationClass(percent: number): string {
    if (percent >= 90) return 'bg-danger';
    if (percent >= 70) return 'bg-navy';

    return 'bg-success';
}

function openWeek(): void {
    router.visit(route('calendar.index', { view: 'team-week' }));
}

function confirmDelete(): void {
    const target = deleteTarget.value;
    const ids = bulkTarget.value;
    deleteTarget.value = null;
    bulkTarget.value = [];

    if (target) {
        router.delete(route('employees.destroy', target.id), { preserveScroll: true });

        return;
    }

    if (ids.length > 0) void bulk.destroy(ids);
}
</script>

<template>
    <Head :title="t('Employees')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-foreground">{{ t('Employees') }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ t('Manage your team and their access. All employees have mobile app access by default.') }}
                    </p>
                </div>
                <AddEmployeeDialog :available-permissions="availablePermissions">
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        {{ t('Add Employee') }}
                    </Button>
                </AddEmployeeDialog>
            </div>
        </template>

        <DataTableToolbar
            :search="list.search.value"
            :placeholder="t('Search employees...')"
            :columns="columns"
            :visible-columns="visible"
            :chips="chips"
            @update:search="list.search.value = $event"
            @update:visible-columns="setVisible"
            @remove-chip="list.setParam('role', null)"
        >
            <template #filters>
                <Select
                    :model-value="roleFilter"
                    @update:model-value="list.setParam('role', String($event) === 'all' ? null : String($event))"
                >
                    <SelectTrigger class="w-48" data-testid="role-filter">
                        <SelectValue :placeholder="t('All roles')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">{{ t('All roles') }}</SelectItem>
                        <SelectItem value="none">{{ t('No role') }}</SelectItem>
                        <SelectItem v-for="role in roles" :key="role.id" :value="role.slug">
                            {{ role.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </template>
        </DataTableToolbar>

        <DataTable
            :rows="rows"
            :columns="columns"
            :visible-columns="visible"
            :sort="list.sort.value"
            :pagination="employees"
            :loading="list.loading.value"
            :active-id="peek.active.value?.id ?? null"
            selectable
            @sort="list.toggleSort($event)"
            @row-click="peek.open($event)"
        >
            <template #cell-employee="{ row }">
                <div class="flex items-center gap-3">
                    <Avatar class="h-8 w-8 bg-navy-wash">
                        <AvatarFallback class="bg-navy-wash text-xs font-semibold text-navy">
                            {{ initials(row.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="min-w-0">
                        <div class="truncate font-medium text-foreground">{{ row.name }}</div>
                        <div class="truncate text-xs text-muted-foreground">{{ row.email }}</div>
                    </div>
                </div>
            </template>

            <template #cell-role="{ row }">
                <div class="flex items-center gap-1.5">
                    <span v-if="row.staff_role">{{ row.staff_role.name }}</span>
                    <span v-else class="text-muted-foreground">{{ t('No role') }}</span>
                    <Badge v-if="row.has_custom_permissions" variant="secondary" class="text-[10px]">
                        {{ t('Customised') }}
                    </Badge>
                </div>
            </template>

            <template #cell-appointments="{ row }">
                <span class="tabular-nums">{{ row.appointments_this_week }}</span>
            </template>

            <template #cell-utilisation="{ row }">
                <div class="flex items-center gap-2">
                    <span class="h-2 w-24 overflow-hidden rounded-full bg-muted">
                        <span
                            class="block h-full rounded-full transition-all"
                            :class="utilisationClass(row.utilisation)"
                            :style="{ width: `${row.utilisation}%` }"
                        />
                    </span>
                    <span class="text-xs tabular-nums text-muted-foreground">{{ row.utilisation }}%</span>
                </div>
            </template>

            <template #cell-email="{ row }">{{ row.email }}</template>

            <template #row-actions="{ row }">
                <DataTableRowActions>
                    <DropdownMenuItem @select="peek.open(row)">{{ t('Permissions') }}</DropdownMenuItem>
                    <DropdownMenuItem @select="openWeek()">{{ t('Open week') }}</DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem class="text-destructive" @select="deleteTarget = row">
                        <Trash2 class="mr-2 h-4 w-4" />
                        {{ t('Delete') }}
                    </DropdownMenuItem>
                </DataTableRowActions>
            </template>

            <template #bulk-actions="{ ids }">
                <button
                    type="button"
                    class="rounded px-2 py-1 font-medium transition-colors hover:bg-navy-hover"
                    data-testid="bulk-delete"
                    @click="bulkTarget = ids"
                >
                    {{ t('Delete') }}
                </button>
            </template>

            <template #empty>
                <Empty class="py-12">
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <Users class="h-6 w-6" />
                        </EmptyMedia>
                        <EmptyTitle>
                            {{ list.search.value ? t('No employees found.') : t('Employees') }}
                        </EmptyTitle>
                        <EmptyDescription>
                            {{
                                list.search.value
                                    ? t('Nothing matches this search. Try another term or clear the filters.')
                                    : t('No employees yet. Click "Add Employee" to get started.')
                            }}
                        </EmptyDescription>
                    </EmptyHeader>
                    <EmptyContent v-if="!list.search.value">
                        <AddEmployeeDialog :available-permissions="availablePermissions">
                            <Button size="sm">
                                <Plus class="mr-2 h-4 w-4" />
                                {{ t('Add Employee') }}
                            </Button>
                        </AddEmployeeDialog>
                    </EmptyContent>
                </Empty>
            </template>
        </DataTable>

        <EmployeePeek
            :employee="peek.active.value"
            :roles="roles"
            :available-permissions="availablePermissions"
            @close="peek.close()"
            @next="peek.next()"
            @prev="peek.prev()"
            @open-record="openWeek()"
        />

        <ConfirmDeleteDialog
            :open="deleteTarget !== null || bulkTarget.length > 0"
            :title="
                deleteTarget
                    ? t('Delete :name?', { name: deleteTarget.name })
                    : t('Delete :count employees?', { count: String(bulkTarget.length) })
            "
            :description="t('This will permanently delete this employee account and all their permissions. This action cannot be undone.')"
            @update:open="!$event && ((deleteTarget = null), (bulkTarget = []))"
            @confirm="confirmDelete"
        />
    </AuthenticatedLayout>
</template>
