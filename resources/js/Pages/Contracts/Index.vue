<script setup lang="ts">
import { ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Card } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/Components/ui/empty';
import { useTrans } from '@/lib/use-trans';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
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
import type { Contract, Paginated } from '@/types';
import ContractFormDrawer from './partials/ContractFormDrawer.vue';
import TablePagination from '@/Components/TablePagination.vue';

const { t } = useTrans();

const props = defineProps<{
    contracts: Paginated<Contract>;
    clients: { id: number; first_name: string; last_name: string; company_name: string | null; name: string }[];
    filters: { search: string | null };
}>();

const search = ref(props.filters.search ?? '');
let debounceTimer: ReturnType<typeof setTimeout>;

watch(search, (value) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get(route('contracts.index'), { search: value || undefined }, {
            preserveState: true,
            preserveScroll: true,
        });
    }, 300);
});

function deleteContract(contract: Contract) {
    router.delete(route('contracts.destroy', contract.id), {
        preserveScroll: true,
    });
}

function formatAddress(c: { street: string | null; zip: string | null; city: string | null }): string {
    const parts: string[] = [];
    if (c.street) parts.push(c.street);
    if (c.zip || c.city) parts.push([c.zip, c.city].filter(Boolean).join(' '));
    return parts.join(', ');
}

const KIND_LABELS: Record<string, string> = {
    kundentermin: 'T',
    ohne_termin: 'OT',
};
</script>

<template>
    <Head :title="t('Contracts')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-foreground">
                        {{ t('Contracts') }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ t('Manage your contracts and assignments.') }}
                    </p>
                </div>
                <ContractFormDrawer :clients="clients">
                    <Button>{{ t('New Contract') }}</Button>
                </ContractFormDrawer>
            </div>
        </template>

        <div class="mb-4">
            <Input
                v-model="search"
                type="text"
                :placeholder="t('Search contracts...')"
                class="max-w-sm"
            />
        </div>

        <Empty v-if="contracts.data.length === 0" class="py-12">
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 12h4"/><path d="M10 16h4"/></svg>
                </EmptyMedia>
                <EmptyTitle>{{ search ? t('No contracts found.') : t('Contracts') }}</EmptyTitle>
                <EmptyDescription>
                    {{ search ? t('Search contracts...') : t('No contracts yet. Click "New Contract" to get started.') }}
                </EmptyDescription>
            </EmptyHeader>
            <EmptyContent v-if="!search">
                <ContractFormDrawer :clients="clients">
                    <Button size="sm">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        {{ t('New Contract') }}
                    </Button>
                </ContractFormDrawer>
            </EmptyContent>
        </Empty>

        <Card v-else>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-[120px]">{{ t('Contract number') }}</TableHead>
                        <TableHead>{{ t('Title') }}</TableHead>
                        <TableHead>{{ t('Kind') }}</TableHead>
                        <TableHead>{{ t('Clients') }}</TableHead>
                        <TableHead>{{ t('Address') }}</TableHead>
                        <TableHead class="text-right">{{ t('Actions') }}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="contract in contracts.data" :key="contract.id">
                        <TableCell class="font-mono text-sm">{{ contract.contract_number }}</TableCell>
                        <TableCell class="font-medium">{{ contract.title }}</TableCell>
                        <TableCell>
                            <span v-if="contract.kind" class="rounded border px-1.5 py-0.5 text-xs font-semibold text-muted-foreground">
                                {{ KIND_LABELS[contract.kind] ?? contract.kind }}
                            </span>
                            <span v-else class="text-muted-foreground">–</span>
                        </TableCell>
                        <TableCell>
                            <span v-if="contract.clients.length > 0">
                                {{ contract.clients.map(c => c.name).join(', ') }}
                            </span>
                            <span v-else class="text-muted-foreground">–</span>
                        </TableCell>
                        <TableCell>
                            <span v-if="formatAddress(contract)">{{ formatAddress(contract) }}</span>
                            <span v-else class="text-muted-foreground">–</span>
                        </TableCell>
                        <TableCell class="text-right">
                            <div class="flex items-center justify-end gap-2">
                                <ContractFormDrawer :contract="contract" :clients="clients">
                                    <Button variant="ghost" size="sm">{{ t('Edit') }}</Button>
                                </ContractFormDrawer>
                                <AlertDialog>
                                    <AlertDialogTrigger as-child>
                                        <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive">
                                            {{ t('Delete') }}
                                        </Button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>{{ t('Delete Contract') }}?</AlertDialogTitle>
                                            <AlertDialogDescription>
                                                {{ t('This contract and all its appointments will be permanently deleted. This action cannot be undone.') }}
                                            </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel>{{ t('Cancel') }}</AlertDialogCancel>
                                            <AlertDialogAction
                                                class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                                @click="deleteContract(contract)"
                                            >
                                                {{ t('Delete') }}
                                            </AlertDialogAction>
                                        </AlertDialogFooter>
                                    </AlertDialogContent>
                                </AlertDialog>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            <TablePagination :pagination="contracts" />
        </Card>
    </AuthenticatedLayout>
</template>
