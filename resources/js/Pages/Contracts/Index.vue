<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { FileText, Plus, Trash2 } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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
import ContractFormDrawer from './partials/ContractFormDrawer.vue';
import ContractPeek from './partials/ContractPeek.vue';
import { CONTRACT_VIEW_LABELS, STAGE_LABELS, STAGE_PILL } from '@/lib/pipeline-stages';
import { useBulkDelete } from '@/lib/use-bulk-delete';
import { useColumnVisibility } from '@/lib/use-column-visibility';
import { useListQuery } from '@/lib/use-list-query';
import { useSidePeek } from '@/lib/use-side-peek';
import { useTrans } from '@/lib/use-trans';
import { wantsCreateForm } from '@/lib/create-intent';
import { initials } from '@/lib/utils';
import type {
    ContractListRow,
    DataTableColumn,
    ListFilters,
    Paginated,
    SavedView,
} from '@/types';

const { t } = useTrans();

const props = defineProps<{
    contracts: Paginated<ContractListRow>;
    stageCounts: { stage: string; count: number }[];
    clients: { id: number; first_name: string; last_name: string; company_name: string | null; name: string }[];
    filters: ListFilters;
}>();

/** Contract kind, abbreviated the way the office already writes it. */
const KIND_LABELS: Record<string, string> = { kundentermin: 'T', ohne_termin: 'OT' };

const columns: DataTableColumn[] = [
    { key: 'contract', label: t('Contract'), sortKey: 'contract_number', locked: true },
    { key: 'clients', label: t('Client') },
    { key: 'stage', label: t('Status') },
    { key: 'progress', label: t('Progress'), headerClass: 'w-40' },
    { key: 'team', label: t('Team') },
    { key: 'city', label: t('City'), sortKey: 'city', hidden: true },
];

const views = computed<SavedView[]>(() =>
    props.stageCounts.map((entry) => ({
        key: entry.stage,
        label: t(CONTRACT_VIEW_LABELS[entry.stage] ?? entry.stage),
        count: entry.count,
    })),
);

const list = useListQuery('contracts.index', props.filters);
const { visible, setVisible } = useColumnVisibility('biotech-contracts-columns', columns);
const peek = useSidePeek<ContractListRow>(() => props.contracts.data);
const bulk = useBulkDelete('contracts.destroy');

// "Neuer Auftrag" in the top-bar search links here with ?new=1 and expects the
// drawer to be waiting rather than another button to hunt for.
const createOpen = ref(wantsCreateForm());

const deleteTarget = ref<ContractListRow | null>(null);
const bulkTarget = ref<number[]>([]);

const rows = computed(() => props.contracts.data);

function openContract(contract: ContractListRow): void {
    router.visit(route('contracts.show', contract.id));
}

function confirmDelete(): void {
    const target = deleteTarget.value;
    const ids = bulkTarget.value;
    deleteTarget.value = null;
    bulkTarget.value = [];

    if (target) {
        router.delete(route('contracts.destroy', target.id), { preserveScroll: true });

        return;
    }

    if (ids.length > 0) void bulk.destroy(ids);
}
</script>

<template>
    <Head :title="t('Contracts')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-foreground">{{ t('Contracts') }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ t('Manage your contracts and assignments.') }}
                    </p>
                </div>
                <ContractFormDrawer v-model:open="createOpen" :clients="clients">
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        {{ t('New Contract') }}
                    </Button>
                </ContractFormDrawer>
            </div>
        </template>

        <DataTableToolbar
            :search="list.search.value"
            :placeholder="t('Search contracts...')"
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
            :pagination="contracts"
            :loading="list.loading.value"
            :active-id="peek.active.value?.id ?? null"
            selectable
            @sort="list.toggleSort($event)"
            @row-click="peek.open($event)"
        >
            <template #cell-contract="{ row }">
                <div class="min-w-0">
                    <div class="truncate font-medium text-foreground">{{ row.title }}</div>
                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <span class="font-mono">{{ row.contract_number }}</span>
                        <span v-if="row.kind" class="rounded border px-1 font-semibold">
                            {{ KIND_LABELS[row.kind] ?? row.kind }}
                        </span>
                    </div>
                </div>
            </template>

            <template #cell-clients="{ row }">
                <span v-if="row.clients.length > 0" class="truncate">
                    {{ row.clients.map((client) => client.name).join(', ') }}
                </span>
                <span v-else class="text-muted-foreground">–</span>
            </template>

            <template #cell-stage="{ row }">
                <span
                    v-if="row.stage"
                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="STAGE_PILL[row.stage]"
                >{{ t(STAGE_LABELS[row.stage]) }}</span>
                <span v-else class="text-muted-foreground">–</span>
            </template>

            <!--
                A bar over a single visit is not progress — it is the status
                pill one column to the left, drawn twice. Three quarters of the
                list is one-visit work, so the bar is kept for the jobs that
                actually run over several appointments.
            -->
            <template #cell-progress="{ row }">
                <span v-if="row.progress.total === 0" class="text-muted-foreground">–</span>
                <div v-else class="flex items-center gap-2">
                    <span v-if="row.progress.total > 1" class="h-2 w-24 overflow-hidden rounded-full bg-muted">
                        <span
                            class="block h-full rounded-full bg-primary transition-all"
                            :style="{ width: `${row.progress.percent}%` }"
                        />
                    </span>
                    <span class="text-xs tabular-nums text-muted-foreground">
                        {{ row.progress.done }}/{{ row.progress.total }}
                    </span>
                </div>
            </template>

            <template #cell-team="{ row }">
                <div v-if="row.team.length > 0" class="flex -space-x-2">
                    <span
                        v-for="worker in row.team.slice(0, 3)"
                        :key="worker.id"
                        class="grid h-6 w-6 place-content-center rounded-full border-2 border-background bg-navy-wash text-[10px] font-semibold text-navy"
                        :title="worker.name"
                    >{{ initials(worker.name) }}</span>
                    <span
                        v-if="row.team.length > 3"
                        class="grid h-6 w-6 place-content-center rounded-full border-2 border-background bg-muted text-[10px] font-semibold text-muted-foreground"
                    >+{{ row.team.length - 3 }}</span>
                </div>
                <span v-else class="text-muted-foreground">–</span>
            </template>

            <template #cell-city="{ row }">
                <span v-if="row.city">{{ row.zip ? `${row.zip} ` : '' }}{{ row.city }}</span>
                <span v-else class="text-muted-foreground">–</span>
            </template>

            <template #row-actions="{ row }">
                <DataTableRowActions>
                    <DropdownMenuItem @select="openContract(row)">{{ t('Open record') }}</DropdownMenuItem>
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
                            <FileText class="h-6 w-6" />
                        </EmptyMedia>
                        <EmptyTitle>
                            {{ list.search.value ? t('No contracts found.') : t('Contracts') }}
                        </EmptyTitle>
                        <EmptyDescription>
                            {{
                                list.search.value
                                    ? t('Nothing matches this search. Try another term or clear the filters.')
                                    : t('No contracts yet. Click "New Contract" to get started.')
                            }}
                        </EmptyDescription>
                    </EmptyHeader>
                    <EmptyContent v-if="!list.search.value">
                        <ContractFormDrawer :clients="clients">
                            <Button size="sm">
                                <Plus class="mr-2 h-4 w-4" />
                                {{ t('New Contract') }}
                            </Button>
                        </ContractFormDrawer>
                    </EmptyContent>
                </Empty>
            </template>
        </DataTable>

        <ContractPeek
            :contract="peek.active.value"
            @close="peek.close()"
            @next="peek.next()"
            @prev="peek.prev()"
            @open-record="peek.active.value && openContract(peek.active.value)"
        />

        <ConfirmDeleteDialog
            :open="deleteTarget !== null || bulkTarget.length > 0"
            :title="
                deleteTarget
                    ? t('Delete :name?', { name: deleteTarget.title })
                    : t('Delete :count contracts?', { count: String(bulkTarget.length) })
            "
            :description="t('This contract and all its appointments will be permanently deleted. This action cannot be undone.')"
            @update:open="!$event && ((deleteTarget = null), (bulkTarget = []))"
            @confirm="confirmDelete"
        />
    </AuthenticatedLayout>
</template>
