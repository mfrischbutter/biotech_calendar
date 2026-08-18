<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import {
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/Components/ui/drawer';
import ConfirmDiscardDialog from '@/Components/ConfirmDiscardDialog.vue';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/Components/ui/popover';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/Components/ui/command';
import { Checkbox } from '@/Components/ui/checkbox';
import AddressAutocomplete from '@/Components/AddressAutocomplete.vue';
import { useTrans } from '@/lib/use-trans';
import type { AppointmentKind, Contract, PlaceSuggestion } from '@/types';

const { t } = useTrans();

type ClientOption = { id: number; first_name: string; last_name: string; company_name: string | null; name: string };

const props = defineProps<{
    contract?: Contract;
    clients: ClientOption[];
}>();

const emit = defineEmits<{
    created: [contractNumber: string];
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    contract_number: props.contract?.contract_number ?? '',
    title: props.contract?.title ?? '',
    kind: (props.contract?.kind ?? 'none') as string,
    description: props.contract?.description ?? '',
    street: props.contract?.street ?? '',
    zip: props.contract?.zip ?? '',
    city: props.contract?.city ?? '',
    latitude: props.contract?.latitude ?? null,
    longitude: props.contract?.longitude ?? null,
    place_id: props.contract?.place_id ?? null,
    client_ids: (props.contract?.clients?.map(c => c.id) ?? []) as number[],
});

const KINDS: { value: AppointmentKind; label: string; prefix: string }[] = [
    { value: 'kundentermin', label: t('Client appointment'), prefix: 'T' },
    { value: 'ohne_termin', label: t('Without appointment'), prefix: 'OT' },
];

const kindPrefix = computed(() => {
    return KINDS.find((k) => k.value === form.kind)?.prefix ?? '';
});

function onPlaceSelected(place: PlaceSuggestion) {
    form.street = place.street;
    form.zip = place.zip;
    form.city = place.city;
    form.latitude = place.latitude;
    form.longitude = place.longitude;
    form.place_id = place.placeId;
}

const isEditing = !!props.contract;

const confirmDiscardOpen = ref(false);
const initialSnapshot = ref('');
const skipDirtyOnClose = ref(false);

function takeSnapshot(): string {
    return JSON.stringify({
        contract_number: form.contract_number,
        title: form.title,
        kind: form.kind,
        description: form.description,
        street: form.street,
        zip: form.zip,
        city: form.city,
        latitude: form.latitude,
        longitude: form.longitude,
        place_id: form.place_id,
        client_ids: [...form.client_ids].sort(),
    });
}

function isDirty(): boolean {
    return takeSnapshot() !== initialSnapshot.value;
}

function requestClose() {
    if (initialSnapshot.value && isDirty()) {
        confirmDiscardOpen.value = true;
    } else {
        forceClose();
    }
}

function forceClose() {
    skipDirtyOnClose.value = true;
    open.value = false;
}

function handleOpenChange(value: boolean) {
    if (value) {
        open.value = true;
    } else if (skipDirtyOnClose.value) {
        skipDirtyOnClose.value = false;
        open.value = false;
    } else {
        requestClose();
    }
}

function populateFromContract() {
    form.contract_number = props.contract?.contract_number ?? '';
    form.title = props.contract?.title ?? '';
    form.kind = props.contract?.kind ?? 'none';
    form.description = props.contract?.description ?? '';
    form.street = props.contract?.street ?? '';
    form.zip = props.contract?.zip ?? '';
    form.city = props.contract?.city ?? '';
    form.latitude = props.contract?.latitude ?? null;
    form.longitude = props.contract?.longitude ?? null;
    form.place_id = props.contract?.place_id ?? null;
    form.client_ids = props.contract?.clients?.map(c => c.id) ?? [];
}

watch(open, (value) => {
    if (value) {
        if (isEditing) {
            populateFromContract();
        }
        nextTick(() => {
            initialSnapshot.value = takeSnapshot();
        });
    }
    if (!value && initialSnapshot.value) {
        form.clearErrors();
        if (isEditing) {
            populateFromContract();
        } else {
            form.reset();
        }
        skipDirtyOnClose.value = false;
    }
});

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

function handlePointerDownOutside(e: Event) {
    e.preventDefault();
}

function submit() {
    const payload = {
        ...form.data(),
        kind: form.kind && form.kind !== 'none' ? form.kind : null,
        description: form.description || null,
        street: form.street || null,
        zip: form.zip || null,
        city: form.city || null,
    };

    if (isEditing) {
        form.transform(() => payload).put(route('contracts.update', props.contract!.id), {
            preserveScroll: true,
            onSuccess: () => {
                skipDirtyOnClose.value = true;
                open.value = false;
            },
        });
    } else {
        form.transform(() => payload).post(route('contracts.store'), {
            preserveScroll: true,
            onSuccess: () => {
                emit('created', form.contract_number);
                skipDirtyOnClose.value = true;
                open.value = false;
            },
        });
    }
}
</script>

<template>
    <div @click="open = true">
        <slot />
    </div>
    <Drawer :open="open" direction="right" @update:open="handleOpenChange">
        <DrawerContent
            class="fixed inset-y-0 right-0 left-auto mt-0 flex h-full w-full flex-col rounded-l-[10px] rounded-t-none sm:max-w-[520px]"
        >
            <DrawerHeader class="relative">
                <DrawerTitle>{{ isEditing ? t('Edit Contract') : t('New Contract') }}</DrawerTitle>
                <DrawerDescription>
                    {{ isEditing ? t('Update contract details.') : t('Create a new contract.') }}
                </DrawerDescription>
                <button
                    type="button"
                    class="absolute right-4 top-4 rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                    @click="requestClose"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    <span class="sr-only">Close</span>
                </button>
            </DrawerHeader>

            <div class="flex flex-1 flex-col overflow-hidden">
                <div class="no-scrollbar flex-1 overflow-y-auto px-4 pb-4">
                    <form id="contract-form" @submit.prevent="submit" class="space-y-4">
                        <!-- Kind prefix + Title -->
                        <div class="flex items-center gap-2">
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <button
                                        type="button"
                                        class="shrink-0 rounded border px-1.5 py-0.5 text-xs font-semibold text-muted-foreground hover:bg-muted transition-colors"
                                        :class="kindPrefix ? 'border-border' : 'border-dashed border-muted-foreground/40'"
                                    >
                                        {{ kindPrefix || '...' }}
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="start" class="w-48">
                                    <DropdownMenuItem @click="form.kind = 'none'">
                                        <span class="text-muted-foreground">{{ t('No selection') }}</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem v-for="k in KINDS" :key="k.value" @click="form.kind = k.value">
                                        <span class="mr-2 inline-block w-6 text-center text-xs font-semibold text-muted-foreground">[{{ k.prefix }}]</span>
                                        {{ k.label }}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                            <input
                                v-model="form.title"
                                type="text"
                                :placeholder="t('Title')"
                                required
                                class="min-w-0 flex-1 bg-transparent text-lg font-medium border-0 outline-none ring-0 focus:outline-none focus:ring-0 p-0 text-foreground placeholder:text-muted-foreground"
                            />
                        </div>
                        <p v-if="form.errors.title" class="text-xs text-destructive">{{ form.errors.title }}</p>

                        <!-- Contract number -->
                        <div class="space-y-2">
                            <Label for="contract-number">{{ t('Contract number') }} *</Label>
                            <Input
                                id="contract-number"
                                v-model="form.contract_number"
                                type="text"
                                :placeholder="t('Contract number')"
                                required
                            />
                            <p v-if="form.errors.contract_number" class="text-sm text-destructive">
                                {{ form.errors.contract_number }}
                            </p>
                        </div>

                        <!-- Clients multi-select -->
                        <div class="space-y-2">
                            <Label>{{ t('Clients') }}</Label>
                            <Popover v-model:open="clientPopoverOpen">
                                <PopoverTrigger as-child>
                                    <Button type="button" variant="outline" class="w-full justify-start h-auto py-2 font-normal">
                                        <span v-if="selectedClientNames" class="truncate">{{ selectedClientNames }}</span>
                                        <span v-else class="text-muted-foreground">{{ t('Select clients...') }}</span>
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-[--reka-popover-trigger-width] p-0" @pointer-down-outside="handlePointerDownOutside">
                                    <Command>
                                        <CommandInput v-model="clientSearch" :placeholder="t('Search clients...')" class="ring-0 focus:ring-0 focus:outline-none border-0 shadow-none" />
                                        <CommandList>
                                            <CommandEmpty>{{ t('No client found.') }}</CommandEmpty>
                                            <CommandGroup class="[&>*]:cursor-pointer">
                                                <CommandItem
                                                    v-for="client in filteredClients"
                                                    :key="client.id"
                                                    :value="client.name"
                                                    @select.prevent="toggleClient(client.id)"
                                                    class="flex items-center gap-2"
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

                        <!-- Address -->
                        <div class="space-y-2">
                            <Label for="contract-street">{{ t('Street') }}</Label>
                            <AddressAutocomplete
                                id="contract-street"
                                v-model="form.street"
                                :placeholder="t('Street and house number')"
                                @place-selected="onPlaceSelected"
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="contract-zip">{{ t('ZIP') }}</Label>
                                <Input id="contract-zip" v-model="form.zip" type="text" :placeholder="t('ZIP')" />
                            </div>
                            <div class="space-y-2">
                                <Label for="contract-city">{{ t('City') }}</Label>
                                <Input id="contract-city" v-model="form.city" type="text" :placeholder="t('City')" />
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="space-y-2">
                            <Label for="contract-description">{{ t('Description') }}</Label>
                            <Textarea
                                id="contract-description"
                                v-model="form.description"
                                :placeholder="t('Add a description...')"
                                rows="3"
                            />
                        </div>
                    </form>
                </div>

                <DrawerFooter class="border-t">
                    <Button type="submit" form="contract-form" :disabled="form.processing">
                        {{ form.processing ? t('Saving...') : (isEditing ? t('Update') : t('Create')) }}
                    </Button>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>

    <ConfirmDiscardDialog v-model:open="confirmDiscardOpen" @discard="forceClose" />
</template>
