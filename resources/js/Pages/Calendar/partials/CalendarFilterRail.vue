<script setup lang="ts">
import { computed } from 'vue';
import { Check } from 'lucide-vue-next';
import FilterPill from '@/Components/FilterPill.vue';
import { DropdownMenuItem, DropdownMenuSeparator } from '@/Components/ui/dropdown-menu';
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
    clearEmployees: [];
    clearStatuses: [];
    reset: [];
}>();

function employeeChecked(id: number): boolean {
    return props.filters.employees.includes(id);
}

function statusChecked(id: number): boolean {
    return props.filters.statuses.includes(id);
}

/*
 * What each pill reads. The unassigned row is an assignee like any other, so it
 * counts towards the people pill rather than earning a control of its own.
 */
const selectedEmployees = computed(() => {
    const names = props.employees.filter((e) => employeeChecked(e.id)).map((e) => e.name);

    return props.filters.unassigned ? [...names, t('Unassigned')] : names;
});

const selectedStatuses = computed(() =>
    props.statuses.filter((status) => statusChecked(status.id)).map((status) => status.name),
);
</script>

<template>
    <!--
        Every person and every status used to sit in this strip as a bare
        checkbox, which grew three rows deep and read as noise rather than as a
        control. They collapse into one pill per dimension: the pill states the
        current answer ("Alle Mitarbeiter", "Lisa Bauer", "Status 3") and only
        unfolds the full list when someone actually wants to narrow the board.
    -->
    <div
        data-testid="calendar-filter-rail"
        class="mb-2 flex flex-wrap items-center gap-2 md:mb-3"
    >
        <FilterPill
            :label="t('Employee')"
            :idle-label="t('All employees')"
            :selected="selectedEmployees"
            testid="filter-employees"
            @clear="emit('clearEmployees')"
        >
            <DropdownMenuItem
                v-for="employee in employees"
                :key="employee.id"
                class="gap-2"
                :data-testid="`filter-employee-${employee.id}`"
                :aria-checked="employeeChecked(employee.id)"
                @select.prevent="emit('toggleEmployee', employee.id)"
            >
                <Check
                    class="h-3.5 w-3.5 shrink-0 text-navy"
                    :class="employeeChecked(employee.id) ? 'opacity-100' : 'opacity-0'"
                />
                <span class="truncate">{{ employee.name }}</span>
            </DropdownMenuItem>

            <DropdownMenuSeparator />

            <DropdownMenuItem
                class="gap-2"
                data-testid="filter-unassigned"
                :aria-checked="filters.unassigned"
                @select.prevent="emit('toggleUnassigned')"
            >
                <Check
                    class="h-3.5 w-3.5 shrink-0 text-navy"
                    :class="filters.unassigned ? 'opacity-100' : 'opacity-0'"
                />
                <span class="italic text-muted-foreground">{{ t('Unassigned') }}</span>
            </DropdownMenuItem>
        </FilterPill>

        <FilterPill
            :label="t('Status')"
            :idle-label="t('All statuses')"
            :selected="selectedStatuses"
            testid="filter-statuses"
            @clear="emit('clearStatuses')"
        >
            <DropdownMenuItem
                v-for="status in statuses"
                :key="status.id"
                class="gap-2"
                :data-testid="`filter-status-${status.id}`"
                :aria-checked="statusChecked(status.id)"
                @select.prevent="emit('toggleStatus', status.id)"
            >
                <Check
                    class="h-3.5 w-3.5 shrink-0 text-navy"
                    :class="statusChecked(status.id) ? 'opacity-100' : 'opacity-0'"
                />
                <span class="h-2 w-2 shrink-0 rounded-full" :style="getStatusDotStyle(status.color)" />
                <span class="truncate">{{ status.name }}</span>
            </DropdownMenuItem>
        </FilterPill>

        <button
            v-if="hasFilters"
            type="button"
            data-testid="filter-reset"
            class="text-xs font-medium text-navy underline-offset-2 hover:underline"
            @click="emit('reset')"
        >
            {{ t('Reset filters') }}
        </button>
    </div>
</template>
