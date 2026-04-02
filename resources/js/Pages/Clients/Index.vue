<script setup lang="ts">
import { ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Card } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
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
import ClientFormDialog from './partials/ClientFormDialog.vue';

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
    <Head title="Kunden" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-foreground">
                        Kunden
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Kundenstamm verwalten und durchsuchen.
                    </p>
                </div>
                <ClientFormDialog>
                    <Button>Neuer Kunde</Button>
                </ClientFormDialog>
            </div>
        </template>

        <div class="mb-4">
            <Input
                v-model="search"
                type="text"
                placeholder="Kunden suchen..."
                class="max-w-sm"
            />
        </div>

        <div v-if="clients.length === 0" class="text-center py-12 text-muted-foreground">
            {{ search ? 'Keine Kunden gefunden.' : 'Noch keine Kunden vorhanden. Klicken Sie auf "Neuer Kunde" um zu beginnen.' }}
        </div>

        <Card v-else>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-[250px]">Name</TableHead>
                        <TableHead>Ort</TableHead>
                        <TableHead>Telefon</TableHead>
                        <TableHead>E-Mail</TableHead>
                        <TableHead class="text-right">Aktionen</TableHead>
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
                                <ClientFormDialog :client="client">
                                    <Button variant="ghost" size="sm">Bearbeiten</Button>
                                </ClientFormDialog>
                                <AlertDialog>
                                    <AlertDialogTrigger as-child>
                                        <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive">
                                            Löschen
                                        </Button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>{{ client.name }} löschen?</AlertDialogTitle>
                                            <AlertDialogDescription>
                                                Dieser Kunde wird dauerhaft gelöscht. Verknüpfte Termine behalten ihre Daten, verlieren aber die Kundenreferenz.
                                            </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel>Abbrechen</AlertDialogCancel>
                                            <AlertDialogAction
                                                class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                                @click="deleteClient(client)"
                                            >
                                                Löschen
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
