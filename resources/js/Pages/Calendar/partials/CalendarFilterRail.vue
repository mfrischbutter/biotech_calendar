<script setup lang="ts">
import { Checkbox } from '@/Components/ui/checkbox';
import { Label } from '@/Components/ui/label';
import { Separator } from '@/Components/ui/separator';
import { getStatusDotStyle } from '@/lib/status-colors';
import { useTrans } from '@/lib/use-trans';
import type { CalendarEmployee, CalendarFilters, Status } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    employees: CalendarEmployee[];
    statuses: Status[];
    filters: CalendarFilters;
    hasFilters: boolean;
}>();

const emit = defineEmits<{
    toggleEmployee: [id: number];
    toggleStatus: [id: number];
    toggleUnassigned: [];
    reset: [];
}>();

function employeeChecked(id: number): boolean {
    return props.filters.employees.includes(id);
}

function statusChecked(id: number): boolean {
    return props.filters.statuses.includes(id);
}
</script>

<template>
    <!--
        This rail replaced a static status legend. It costs the same strip of
        screen but answers the question the legend only decorated: show me this
        person's day, or everything still unconfirmed.
    -->
    <div
        data-testid="calendar-filter-rail"
        class="mb-2 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-lg border bg-background px-3 py-2 md:mb-3"
    >
        <span class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
            {{ t('Employee') }}
        </span>

        <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
            <div v-for="employee in employees" :key="employee.id" class="flex items-center gap-1.5">
                <Checkbox
                    :id="`filter-employee-${employee.id}`"
                    :data-testid="`filter-employee-${employee.id}`"
                    :model-value="employeeChecked(employee.id)"
                    @update:model-value="emit('toggleEmployee', employee.id)"
                />
                <Label :for="`filter-employee-${employee.id}`" class="cursor-pointer text-xs font-normal">
                    {{ employee.name }}
                </Label>
            </div>

            <div class="flex items-center gap-1.5">
                <Checkbox
                    id="filter-unassigned"
                    data-testid="filter-unassigned"
                    :model-value="filters.unassigned"
                    @update:model-value="emit('toggleUnassigned')"
                />
                <Label for="filter-unassigned" class="cursor-pointer text-xs font-normal italic text-muted-foreground">
                    {{ t('Unassigned') }}
                </Label>
            </div>
        </div>

        <Separator orientation="vertical" class="hidden h-5 md:block" />

        <span class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
            {{ t('Status') }}
        </span>

        <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
            <div v-for="status in statuses" :key="status.id" class="flex items-center gap-1.5">
                <Checkbox
                    :id="`filter-status-${status.id}`"
                    :data-testid="`filter-status-${status.id}`"
                    :model-value="statusChecked(status.id)"
                    @update:model-value="emit('toggleStatus', status.id)"
                />
                <Label :for="`filter-status-${status.id}`" class="flex cursor-pointer items-center gap-1.5 text-xs font-normal">
                    <span class="h-2 w-2 rounded-full" :style="getStatusDotStyle(status.color)" />
                    {{ status.name }}
                </Label>
            </div>
        </div>

        <button
            v-if="hasFilters"
            type="button"
            data-testid="filter-reset"
            class="ml-auto text-xs font-medium text-navy underline-offset-2 hover:underline"
            @click="emit('reset')"
        >
            {{ t('Reset filters') }}
        </button>
    </div>
</template>
