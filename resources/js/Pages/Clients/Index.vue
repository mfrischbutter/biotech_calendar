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
import type { Client } from '@/types';
import ClientFormDrawer from './partials/ClientFormDrawer.vue';

const { t } = useTrans();

const props = defineProps<{
    clients: Client[];
    filters: { search: string | null };
}>();

const search = ref(props.filters.search ?? '');
let debounceTimer: ReturnType<typeof setTimeout>;

watch(search, (value) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get(route('clients.index'), { search: value || undefined }, {
            preserveState: true,
            preserveScroll: true,
        });
    }, 300);
});

function deleteClient(client: Client) {
    router.delete(route('clients.destroy', client.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="t('Clients')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-foreground">
                        {{ t('Clients') }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ t('Manage and search your client database.') }}
                    </p>
                </div>
                <ClientFormDrawer>
                    <Button>{{ t('New Client') }}</Button>
                </ClientFormDrawer>
            </div>
        </template>

        <div class="mb-4">
            <Input
                v-model="search"
                type="text"
                :placeholder="t('Search clients...')"
                class="max-w-sm"
            />
        </div>

        <Empty v-if="clients.length === 0" class="py-12">
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/></svg>
                </EmptyMedia>
                <EmptyTitle>{{ search ? t('No clients found.') : t('Clients') }}</EmptyTitle>
                <EmptyDescription>
                    {{ search ? t('Search clients...') : t('No clients yet. Click "New Client" to get started.') }}
                </EmptyDescription>
            </EmptyHeader>
            <EmptyContent v-if="!search">
                <ClientFormDrawer>
                    <Button size="sm">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        {{ t('New Client') }}
                    </Button>
                </ClientFormDrawer>
            </EmptyContent>
        </Empty>

        <Card v-else>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-[250px]">{{ t('Name') }}</TableHead>
                        <TableHead>{{ t('City') }}</TableHead>
                        <TableHead>{{ t('Phone') }}</TableHead>
                        <TableHead>{{ t('Email') }}</TableHead>
                        <TableHead class="text-right">{{ t('Actions') }}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="client in clients" :key="client.id">
                        <TableCell>
                            <div class="font-medium">{{ client.name }}</div>
                            <div v-if="client.billing_name" class="text-sm text-muted-foreground">
                                {{ client.billing_name }}
                            </div>
                        </TableCell>
                        <TableCell>
                            <span v-if="client.city">
                                {{ client.zip ? `${client.zip} ` : '' }}{{ client.city }}
                            </span>
                            <span v-else class="text-muted-foreground">–</span>
                        </TableCell>
                        <TableCell>
                            {{ client.phone || '–' }}
                        </TableCell>
                        <TableCell>
                            {{ client.email || '–' }}
                        </TableCell>
                        <TableCell class="text-right">
                            <div class="flex items-center justify-end gap-2">
                                <ClientFormDrawer :client="client">
                                    <Button variant="ghost" size="sm">{{ t('Edit') }}</Button>
                                </ClientFormDrawer>
                                <AlertDialog>
                                    <AlertDialogTrigger as-child>
                                        <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive">
                                            {{ t('Delete') }}
                                        </Button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>{{ t('Delete :name?', { name: client.name }) }}</AlertDialogTitle>
                                            <AlertDialogDescription>
                                                {{ t('This client will be permanently deleted. Linked appointments will keep their data but lose the client reference.') }}
                                            </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel>{{ t('Cancel') }}</AlertDialogCancel>
                                            <AlertDialogAction
                                                class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                                @click="deleteClient(client)"
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
        </Card>
    </AuthenticatedLayout>
</template>
