<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { Pencil, Plus } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/Components/ui/command';
import { useTrans } from '@/lib/use-trans';
import type { ClientOption, Contract } from '@/types';
import ContractFormDrawer from '@/Pages/Contracts/partials/ContractFormDrawer.vue';

const { t } = useTrans();

const props = defineProps<{
    contracts: Contract[];
    clients: ClientOption[];
    modelValue: string;
    /**
     * Set when the user arrived from "Termin für <Kunde>". Customers here carry
     * three to seven jobs, so the link cannot name one — it narrows the list to
     * that customer instead, visibly and clearably, rather than opening a blank
     * picker and dropping what the user asked for.
     */
    clientHint?: string;
    error?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const popoverOpen = ref(false);
const search = ref(props.clientHint ?? '');
const createOpen = ref(false);

// The hint arrives with the drawer, which mounts before the form is filled.
watch(
    () => props.clientHint,
    (hint) => {
        if (!props.modelValue) search.value = hint ?? '';
    },
);

const filtered = computed(() => {
    if (!search.value) return props.contracts;
    const q = search.value.toLowerCase();

    return props.contracts.filter(c =>
        c.title.toLowerCase().includes(q)
        || c.contract_number.toLowerCase().includes(q)
        || c.clients.some(cl => cl.name.toLowerCase().includes(q)),
    );
});

const selected = computed(() =>
    props.modelValue ? props.contracts.find(c => c.id.toString() === props.modelValue) ?? null : null,
);

function formatAddress(c: { street: string | null; zip: string | null; city: string | null }): string {
    const parts: string[] = [];
    if (c.street) parts.push(c.street);
    if (c.zip || c.city) parts.push([c.zip, c.city].filter(Boolean).join(' '));

    return parts.join(', ');
}

function select(id: string) {
    emit('update:modelValue', id);
    popoverOpen.value = false;
    search.value = '';
}

function openCreate() {
    popoverOpen.value = false;
    search.value = '';
    createOpen.value = true;
}

function onCreated(contractNumber: string) {
    nextTick(() => {
        const created = props.contracts.find(c => c.contract_number === contractNumber);
        if (created) emit('update:modelValue', created.id.toString());
    });
}

function keepOpen(e: Event) {
    e.preventDefault();
}
</script>

<template>
    <div class="space-y-1.5">
        <Label class="text-xs text-muted-foreground">{{ t('Contract') }} *</Label>
        <div class="flex gap-1.5">
            <Popover v-model:open="popoverOpen">
                <PopoverTrigger as-child>
                    <Button
                        type="button"
                        variant="outline"
                        class="flex h-auto flex-1 flex-col items-start justify-start gap-0 py-2 font-normal"
                    >
                        <span v-if="selected">
                            <span class="mr-1.5 font-mono text-xs text-muted-foreground">{{ selected.contract_number }}</span>
                            {{ selected.title }}
                        </span>
                        <span v-else class="text-muted-foreground">
                            {{ clientHint ? t('Select contract for :name...', { name: clientHint }) : t('Select contract...') }}
                        </span>
                        <template v-if="selected">
                            <span v-if="selected.clients.length > 0" class="text-xs text-muted-foreground">
                                {{ selected.clients.map(c => c.name).join(', ') }}
                            </span>
                            <span v-if="formatAddress(selected)" class="text-xs text-muted-foreground">
                                {{ formatAddress(selected) }}
                            </span>
                        </template>
                    </Button>
                </PopoverTrigger>
                <PopoverContent class="w-[--reka-popover-trigger-width] p-0" @pointer-down-outside="keepOpen">
                    <Command>
                        <CommandInput
                            v-model="search"
                            :placeholder="t('Search contracts...')"
                            class="border-0 shadow-none ring-0 focus:outline-none focus:ring-0"
                        />
                        <CommandList>
                            <CommandEmpty>{{ t('No contract found.') }}</CommandEmpty>
                            <CommandGroup class="[&>*]:cursor-pointer [&>*:hover]:bg-accent">
                                <CommandItem
                                    v-for="contract in filtered"
                                    :key="contract.id"
                                    :value="`${contract.contract_number} ${contract.title}`"
                                    class="flex flex-col items-start gap-0"
                                    @select="select(contract.id.toString())"
                                >
                                    <span>
                                        <span class="mr-1.5 font-mono text-xs text-muted-foreground">{{ contract.contract_number }}</span>
                                        {{ contract.title }}
                                    </span>
                                    <span v-if="contract.clients.length > 0" class="text-xs text-muted-foreground">
                                        {{ contract.clients.map(c => c.name).join(', ') }}
                                    </span>
                                    <span v-if="formatAddress(contract)" class="text-xs text-muted-foreground">
                                        {{ formatAddress(contract) }}
                                    </span>
                                </CommandItem>
                            </CommandGroup>
                        </CommandList>
                    </Command>
                    <button
                        type="button"
                        class="flex w-full cursor-pointer items-center gap-2 border-t px-3 py-2 text-sm text-primary transition-colors hover:bg-accent"
                        @click="openCreate"
                    >
                        <Plus class="h-4 w-4" />
                        {{ t('New Contract') }}
                    </button>
                </PopoverContent>
            </Popover>

            <ContractFormDrawer v-if="selected" :contract="selected" :clients="clients">
                <Button type="button" variant="outline" size="icon" class="h-9 w-9 shrink-0 self-start">
                    <Pencil class="h-4 w-4" />
                </Button>
            </ContractFormDrawer>
        </div>
        <p v-if="error" class="text-xs text-destructive">{{ error }}</p>

        <ContractFormDrawer v-model:open="createOpen" :clients="clients" @created="onCreated" />
    </div>
</template>
