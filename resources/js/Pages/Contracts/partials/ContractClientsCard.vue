<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import { Pencil, X } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/Components/ui/command';
import { Label } from '@/Components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/Components/ui/popover';
import { useTrans } from '@/lib/use-trans';
import type { Contract } from '@/types';

const { t } = useTrans();

type ClientOption = { id: number; first_name: string; last_name: string; company_name: string | null; name: string };

const props = defineProps<{
    contract: Contract;
    clients: ClientOption[];
}>();

const editing = ref(false);

const form = useForm({
    client_ids: props.contract.clients.map(c => c.id) as number[],
});

function resetForm() {
    form.client_ids = props.contract.clients.map(c => c.id);
    form.clearErrors();
}

watch(() => props.contract, resetForm, { deep: true });

function startEdit() {
    resetForm();
    editing.value = true;
}

function cancel() {
    resetForm();
    editing.value = false;
}

const clientPopoverOpen = ref(false);
const clientSearch = ref('');
const clientOrder = ref<ClientOption[]>([]);

watch(clientPopoverOpen, (isOpen) => {
    if (isOpen) {
        clientOrder.value = [...props.clients].sort((a, b) => {
            const aSelected = form.client_ids.includes(a.id) ? 0 : 1;
            const bSelected = form.client_ids.includes(b.id) ? 0 : 1;
            return aSelected - bSelected;
        });
        clientSearch.value = '';
    }
});

const filteredClients = computed(() => {
    if (!clientSearch.value) return clientOrder.value;
    const q = clientSearch.value.toLowerCase();
    return clientOrder.value.filter((c) => c.name.toLowerCase().includes(q));
});

function toggleClient(clientId: number) {
    if (form.client_ids.includes(clientId)) {
        form.client_ids = form.client_ids.filter(id => id !== clientId);
    } else {
        form.client_ids = [...form.client_ids, clientId];
    }
}

const selectedClientNames = computed(() => {
    return props.clients
        .filter(c => form.client_ids.includes(c.id))
        .map(c => c.name)
        .join(', ');
});

function submit() {
    const payload = {
        contract_number: props.contract.contract_number,
        title: props.contract.title,
        kind: props.contract.kind,
        description: props.contract.description,
        street: props.contract.street,
        zip: props.contract.zip,
        city: props.contract.city,
        latitude: props.contract.latitude,
        longitude: props.contract.longitude,
        place_id: props.contract.place_id,
        client_ids: form.client_ids,
    };

    form.transform(() => payload).put(route('contracts.update', props.contract.id), {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = false;
        },
    });
}
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-4">
            <CardTitle class="text-base">{{ t('Clients') }}</CardTitle>
            <div class="flex items-center gap-2">
                <template v-if="!editing">
                    <Button variant="ghost" size="sm" @click="startEdit">
                        <Pencil class="mr-1.5 h-3.5 w-3.5" />
                        {{ t('Edit') }}
                    </Button>
                </template>
                <template v-else>
                    <Button variant="ghost" size="sm" :disabled="form.processing" @click="cancel">
                        <X class="mr-1.5 h-3.5 w-3.5" />
                        {{ t('Cancel') }}
                    </Button>
                    <Button size="sm" :disabled="form.processing" @click="submit">
                        {{ form.processing ? t('Saving...') : t('Save') }}
                    </Button>
                </template>
            </div>
        </CardHeader>
        <CardContent>
            <template v-if="!editing">
                <ul v-if="contract.clients.length > 0" class="space-y-1">
                    <li v-for="c in contract.clients" :key="c.id">
                        <Link :href="route('clients.show', c.id)" class="text-sm text-primary hover:underline">
                            {{ c.name }}
                        </Link>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">{{ t('No clients selected') }}</p>
            </template>
            <form v-else @submit.prevent="submit">
                <div class="space-y-2">
                    <Label>{{ t('Clients') }}</Label>
                    <Popover v-model:open="clientPopoverOpen">
                        <PopoverTrigger as-child>
                            <Button type="button" variant="outline" class="w-full justify-start h-auto py-2 font-normal">
                                <span v-if="selectedClientNames" class="truncate">{{ selectedClientNames }}</span>
                                <span v-else class="text-muted-foreground">{{ t('Select clients...') }}</span>
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent class="w-[--reka-popover-trigger-width] p-0">
                            <Command>
                                <CommandInput v-model="clientSearch" :placeholder="t('Search clients...')" class="ring-0 focus:ring-0 focus:outline-none border-0 shadow-none" />
                                <CommandList>
                                    <CommandEmpty>{{ t('No client found.') }}</CommandEmpty>
                                    <CommandGroup class="[&>*]:cursor-pointer">
                                        <CommandItem
                                            v-for="client in filteredClients"
                                            :key="client.id"
                                            :value="client.name"
                                            class="flex items-center gap-2"
                                            @select.prevent="toggleClient(client.id)"
                                        >
                                            <Checkbox :model-value="form.client_ids.includes(client.id)" class="pointer-events-none" />
                                            <span>{{ client.name }}</span>
                                        </CommandItem>
                                    </CommandGroup>
                                </CommandList>
                            </Command>
                        </PopoverContent>
                    </Popover>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
