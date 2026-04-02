<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Button } from '@/Components/ui/button';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/Components/ui/alert-dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { ref } from 'vue';
import type { Employee } from '@/types';
import AddEmployeeDialog from './partials/AddEmployeeDialog.vue';
import PermissionToggles from './partials/PermissionToggles.vue';
import { useTrans } from '@/lib/use-trans';
import { initials } from '@/lib/utils';

const { t } = useTrans();

const props = defineProps<{
    employees: Employee[];
    availablePermissions: Record<string, string>;
}>();

const expandedId = ref<number | null>(null);

function toggleExpand(id: number) {
    expandedId.value = expandedId.value === id ? null : id;
}

function hasDashboardAccess(employee: Employee): boolean {
    return employee.permissions.includes('dashboard.view');
}

function deleteEmployee(employee: Employee) {
    router.delete(route('employees.destroy', employee.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="t('Employees')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-foreground">
                        {{ t('Employees') }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ t('Manage your team and their access. All employees have mobile app access by default.') }}
                    </p>
                </div>
                <AddEmployeeDialog :available-permissions="availablePermissions">
                    <Button>{{ t('Add Employee') }}</Button>
                </AddEmployeeDialog>
            </div>
        </template>

        <div v-if="employees.length === 0" class="text-center py-12 text-muted-foreground">
            {{ t('No employees yet. Click "Add Employee" to get started.') }}
        </div>

        <Card v-else>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-[300px]">{{ t('Employee') }}</TableHead>
                        <TableHead>{{ t('Access') }}</TableHead>
                        <TableHead>{{ t('Permissions') }}</TableHead>
                        <TableHead class="text-right">{{ t('Actions') }}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-for="employee in employees" :key="employee.id">
                        <TableRow
                            class="cursor-pointer"
                            @click="toggleExpand(employee.id)"
                        >
                            <TableCell>
                                <div class="flex items-center gap-3">
                                    <Avatar class="h-8 w-8">
                                        <AvatarFallback class="text-xs">
                                            {{ initials(employee.name) }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div>
                                        <div class="font-medium">{{ employee.name }}</div>
                                        <div class="text-sm text-muted-foreground">{{ employee.email }}</div>
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center gap-1.5">
                                    <Badge variant="secondary">{{ t('Mobile') }}</Badge>
                                    <Badge v-if="hasDashboardAccess(employee)" variant="default">
                                        {{ t('Dashboard') }}
                                    </Badge>
                                </div>
                            </TableCell>
                            <TableCell>
                                <span class="text-sm text-muted-foreground">
                                    {{ employee.permissions.length }} / {{ Object.keys(availablePermissions).length }}
                                </span>
                            </TableCell>
                            <TableCell class="text-right" @click.stop>
                                <div class="flex items-center justify-end gap-2">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="toggleExpand(employee.id)"
                                    >
                                        {{ expandedId === employee.id ? t('Collapse') : t('Permissions') }}
                                    </Button>
                                    <AlertDialog>
                                        <AlertDialogTrigger as-child>
                                            <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive">
                                                {{ t('Delete') }}
                                            </Button>
                                        </AlertDialogTrigger>
                                        <AlertDialogContent>
                                            <AlertDialogHeader>
                                                <AlertDialogTitle>{{ t('Delete :name?', { name: employee.name }) }}</AlertDialogTitle>
                                                <AlertDialogDescription>
                                                    {{ t('This will permanently delete this employee account and all their permissions. This action cannot be undone.') }}
                                                </AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>{{ t('Cancel') }}</AlertDialogCancel>
                                                <AlertDialogAction
                                                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                                    @click="deleteEmployee(employee)"
                                                >
                                                    {{ t('Delete') }}
                                                </AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="expandedId === employee.id">
                            <TableCell colspan="4" class="p-0 bg-muted/30">
                                <PermissionToggles
                                    :employee="employee"
                                    :available-permissions="availablePermissions"
                                />
                            </TableCell>
                        </TableRow>
                    </template>
                </TableBody>
            </Table>
        </Card>
    </AuthenticatedLayout>
</template>
