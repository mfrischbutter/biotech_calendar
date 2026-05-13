<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Pencil, X } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import AddressAutocomplete from '@/Components/AddressAutocomplete.vue';
import { useTrans } from '@/lib/use-trans';
import type { Contract, PlaceSuggestion } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    contract: Contract;
}>();

const editing = ref(false);

const form = useForm({
    street: props.contract.street ?? '',
    zip: props.contract.zip ?? '',
    city: props.contract.city ?? '',
    latitude: props.contract.latitude,
    longitude: props.contract.longitude,
    place_id: props.contract.place_id,
});

function resetForm() {
    form.street = props.contract.street ?? '';
    form.zip = props.contract.zip ?? '';
    form.city = props.contract.city ?? '';
    form.latitude = props.contract.latitude;
    form.longitude = props.contract.longitude;
    form.place_id = props.contract.place_id;
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

function onPlaceSelected(place: PlaceSuggestion) {
    form.street = place.street;
    form.zip = place.zip;
    form.city = place.city;
    form.latitude = place.latitude;
    form.longitude = place.longitude;
    form.place_id = place.placeId;
}

function submit() {
    const payload = {
        contract_number: props.contract.contract_number,
        title: props.contract.title,
        kind: props.contract.kind,
        description: props.contract.description,
        street: form.street || null,
        zip: form.zip || null,
        city: form.city || null,
        latitude: form.latitude,
        longitude: form.longitude,
        place_id: form.place_id,
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
            <CardTitle class="text-base">{{ t('Address') }}</CardTitle>
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
                <p v-if="contract.street || contract.city" class="text-sm">
                    <span v-if="contract.street">{{ contract.street }}<br></span>
                    <span v-if="contract.zip || contract.city">{{ [contract.zip, contract.city].filter(Boolean).join(' ') }}</span>
                </p>
                <p v-else class="text-sm text-muted-foreground">{{ t('No address') }}</p>
            </template>
            <form v-else class="space-y-3" @submit.prevent="submit">
                <div class="space-y-1.5">
                    <Label for="contract-address-street">{{ t('Street') }}</Label>
                    <AddressAutocomplete
                        id="contract-address-street"
                        v-model="form.street"
                        :placeholder="t('Street and house number')"
                        @place-selected="onPlaceSelected"
                    />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label for="contract-address-zip">{{ t('ZIP') }}</Label>
                        <Input id="contract-address-zip" v-model="form.zip" type="text" :placeholder="t('ZIP')" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="contract-address-city">{{ t('City') }}</Label>
                        <Input id="contract-address-city" v-model="form.city" type="text" :placeholder="t('City')" />
                    </div>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
