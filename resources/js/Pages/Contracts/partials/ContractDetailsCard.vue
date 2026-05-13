<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Pencil, X } from 'lucide-vue-next';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { useTrans } from '@/lib/use-trans';
import type { AppointmentKind, Contract } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    contract: Contract;
}>();

const KINDS: { value: AppointmentKind; label: string; prefix: string }[] = [
    { value: 'kundentermin', label: t('Client appointment'), prefix: 'T' },
    { value: 'ohne_termin', label: t('Without appointment'), prefix: 'OT' },
];

const KIND_LABELS: Record<string, string> = {
    kundentermin: 'T',
    ohne_termin: 'OT',
};

const editing = ref(false);

const form = useForm({
    contract_number: props.contract.contract_number,
    title: props.contract.title,
    kind: (props.contract.kind ?? 'none') as string,
});

const kindPrefix = computed(() => {
    return KINDS.find((k) => k.value === form.kind)?.prefix ?? '';
});

function resetForm() {
    form.contract_number = props.contract.contract_number;
    form.title = props.contract.title;
    form.kind = props.contract.kind ?? 'none';
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

function submit() {
    const payload = {
        contract_number: form.contract_number,
        title: form.title,
        kind: form.kind && form.kind !== 'none' ? form.kind : null,
        description: props.contract.description,
        street: props.contract.street,
        zip: props.contract.zip,
        city: props.contract.city,
        latitude: props.contract.latitude,
        longitude: props.contract.longitude,
        place_id: props.contract.place_id,
        client_ids: props.contract.clients.map(c => c.id),
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
            <CardTitle class="text-base">{{ t('Details') }}</CardTitle>
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
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm text-muted-foreground">{{ t('Contract number') }}</dt>
                        <dd class="font-mono text-sm font-medium">{{ contract.contract_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">{{ t('Title') }}</dt>
                        <dd class="text-sm font-medium">{{ contract.title }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">{{ t('Kind') }}</dt>
                        <dd>
                            <Badge v-if="contract.kind" variant="outline">
                                {{ KIND_LABELS[contract.kind] ?? contract.kind }}
                            </Badge>
                            <span v-else class="text-sm text-muted-foreground">–</span>
                        </dd>
                    </div>
                </dl>
            </template>
            <form v-else class="space-y-4" @submit.prevent="submit">
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
                    <Input
                        v-model="form.title"
                        type="text"
                        :placeholder="t('Title')"
                        required
                        class="flex-1"
                    />
                </div>
                <p v-if="form.errors.title" class="text-xs text-destructive">{{ form.errors.title }}</p>

                <div class="space-y-1.5">
                    <Label for="details-contract-number">{{ t('Contract number') }} *</Label>
                    <Input
                        id="details-contract-number"
                        v-model="form.contract_number"
                        type="text"
                        :placeholder="t('Contract number')"
                        required
                    />
                    <p v-if="form.errors.contract_number" class="text-sm text-destructive">
                        {{ form.errors.contract_number }}
                    </p>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
