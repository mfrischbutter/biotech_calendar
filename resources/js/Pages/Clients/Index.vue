<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Building2, Plus, Trash2, UserRound } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
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
import ConfirmDeleteDialog from '@/Components/ConfirmDeleteDialog.vue';
import { DataTable, DataTableRowActions, DataTableToolbar } from '@/Components/DataTable';
import NewClientDialog from './partials/NewClientDialog.vue';
import ClientPeek from './partials/ClientPeek.vue';
import { shortDate } from '@/lib/date-utils';
import { useBulkDelete } from '@/lib/use-bulk-delete';
import { useColumnVisibility } from '@/lib/use-column-visibility';
import { useListQuery } from '@/lib/use-list-query';
import { useSidePeek } from '@/lib/use-side-peek';
import { useTrans } from '@/lib/use-trans';
import { initials } from '@/lib/utils';
import type { ClientListRow, DataTableColumn, ListFilters, Paginated, SavedView } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    clients: Paginated<ClientListRow>;
    filters: ListFilters;
}>();

const columns: DataTableColumn[] = [
    { key: 'client', label: t('Client'), sortKey: 'name', locked: true },
    { key: 'city', label: t('City'), sortKey: 'city' },
    { key: 'open_contracts', label: t('Open contracts'), sortKey: 'open_contracts' },
    { key: 'next_appointment', label: t('Next Appointment'), sortKey: 'next_appointment' },
    { key: 'phone', label: t('Phone'), hidden: true },
    { key: 'email', label: t('Email'), hidden: true },
];

const views: SavedView[] = [
    { key: 'all', label: t('All clients') },
    { key: 'active_contracts', label: t('With open contracts') },
    { key: 'dormant', label: t('No appointment in 90 days') },
];

const list = useListQuery('clients.index', props.filters);
const { visible, setVisible } = useColumnVisibility('biotech-clients-columns', columns);
const peek = useSidePeek<ClientListRow>(() => props.clients.data);
const bulk = useBulkDelete('clients.destroy');

const deleteTarget = ref<ClientListRow | null>(null);
const bulkTarget = ref<number[]>([]);

const rows = computed(() => props.clients.data);

/** More than a handful of open jobs is a workload signal, not just a number. */
function contractPill(count: number): string {
    if (count === 0) return 'bg-muted text-muted-foreground';
    if (count >= 3) return 'bg-warning-wash text-warning';

    return 'bg-success-wash text-success-foreground';
}

function openClient(client: ClientListRow): void {
    router.visit(route('clients.show', client.id));
}

function confirmDelete(): void {
    const target = deleteTarget.value;
    const ids = bulkTarget.value;
    deleteTarget.value = null;
    bulkTarget.value = [];

    if (target) {
        router.delete(route('clients.destroy', target.id), { preserveScroll: true });

        return;
    }

    if (ids.length > 0) void bulk.destroy(ids);
}
</script>

<template>
    <Head :title="t('Clients')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-foreground">{{ t('Clients') }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ t('Manage and search your client database.') }}
                    </p>
                </div>
                <NewClientDialog>
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        {{ t('New Client') }}
                    </Button>
                </NewClientDialog>
            </div>
        </template>

        <DataTableToolbar
            :search="list.search.value"
            :placeholder="t('Search clients...')"
            :columns="columns"
            :visible-columns="visible"
            :views="views"
            :active-view="list.view.value"
            @update:search="list.search.value = $event"
            @update:view="list.setView($event)"
            @update:visible-columns="setVisible"
        />

        <DataTable
            :rows="rows"
            :columns="columns"
            :visible-columns="visible"
            :sort="list.sort.value"
            :pagination="clients"
            :loading="list.loading.value"
            :active-id="peek.active.value?.id ?? null"
            selectable
            @sort="list.toggleSort($event)"
            @row-click="peek.open($event)"
        >
            <template #cell-client="{ row }">
                <div class="flex items-center gap-3">
                    <Avatar class="h-8 w-8 bg-navy-wash">
                        <AvatarFallback class="bg-navy-wash text-xs font-semibold text-navy">
                            {{ initials(row.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="min-w-0">
                        <div class="truncate font-medium text-foreground">{{ row.name }}</div>
                        <div v-if="row.company_name" class="flex items-center gap-1 truncate text-xs text-muted-foreground">
                            <Building2 class="h-3 w-3 shrink-0" />
                            {{ row.company_name }}
                        </div>
                    </div>
                </div>
            </template>

            <template #cell-city="{ row }">
                <span v-if="row.city">{{ row.zip ? `${row.zip} ` : '' }}{{ row.city }}</span>
                <span v-else class="text-muted-foreground">–</span>
            </template>

            <template #cell-open_contracts="{ row }">
                <span
                    class="inline-flex min-w-6 justify-center rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums"
                    :class="contractPill(row.open_contracts)"
                >{{ row.open_contracts }}</span>
            </template>

            <template #cell-next_appointment="{ row }">
                <span v-if="row.next_appointment">{{ shortDate(row.next_appointment) }}</span>
                <span v-else class="text-muted-foreground">–</span>
            </template>

            <template #cell-phone="{ row }">{{ row.phone || '–' }}</template>
            <template #cell-email="{ row }">{{ row.email || '–' }}</template>

            <template #row-actions="{ row }">
                <DataTableRowActions>
                    <DropdownMenuItem @select="openClient(row)">{{ t('Open record') }}</DropdownMenuItem>
                    <DropdownMenuItem @select="peek.open(row)">{{ t('Quick view') }}</DropdownMenuItem>
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
                            <UserRound class="h-6 w-6" />
                        </EmptyMedia>
                        <EmptyTitle>
                            {{ list.search.value ? t('No clients found.') : t('Clients') }}
                        </EmptyTitle>
                        <EmptyDescription>
                            {{
                                list.search.value
                                    ? t('Nothing matches this search. Try another term or clear the filters.')
                                    : t('No clients yet. Click "New Client" to get started.')
                            }}
                        </EmptyDescription>
                    </EmptyHeader>
                    <EmptyContent v-if="!list.search.value">
                        <NewClientDialog>
                            <Button size="sm">
                                <Plus class="mr-2 h-4 w-4" />
                                {{ t('New Client') }}
                            </Button>
                        </NewClientDialog>
                    </EmptyContent>
                </Empty>
            </template>
        </DataTable>

        <ClientPeek
            :client="peek.active.value"
            @close="peek.close()"
            @next="peek.next()"
            @prev="peek.prev()"
            @open-record="peek.active.value && openClient(peek.active.value)"
        />

        <ConfirmDeleteDialog
            :open="deleteTarget !== null || bulkTarget.length > 0"
            :title="
                deleteTarget
                    ? t('Delete :name?', { name: deleteTarget.name })
                    : t('Delete :count clients?', { count: String(bulkTarget.length) })
            "
            :description="t('This client will be permanently deleted. Linked appointments will keep their data but lose the client reference.')"
            @update:open="!$event && ((deleteTarget = null), (bulkTarget = []))"
            @confirm="confirmDelete"
        />
    </AuthenticatedLayout>
</template>
