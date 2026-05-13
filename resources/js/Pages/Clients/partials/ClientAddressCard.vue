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
import type { Client, PlaceSuggestion } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    client: Client;
}>();

const editing = ref(false);

const form = useForm({
    street: props.client.street ?? '',
    zip: props.client.zip ?? '',
    city: props.client.city ?? '',
    latitude: props.client.latitude,
    longitude: props.client.longitude,
    place_id: props.client.place_id,
});

function resetForm() {
    form.street = props.client.street ?? '';
    form.zip = props.client.zip ?? '';
    form.city = props.client.city ?? '';
    form.latitude = props.client.latitude;
    form.longitude = props.client.longitude;
    form.place_id = props.client.place_id;
    form.clearErrors();
}

watch(() => props.client, resetForm, { deep: true });

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
        salutation: props.client.salutation,
        first_name: props.client.first_name,
        last_name: props.client.last_name,
        company_name: props.client.company_name,
        billing_name: props.client.billing_name,
        phone: props.client.phone,
        email: props.client.email,
        notes: props.client.notes,
        street: form.street || null,
        zip: form.zip || null,
        city: form.city || null,
        latitude: form.latitude,
        longitude: form.longitude,
        place_id: form.place_id,
    };

    form.transform(() => payload).put(route('clients.update', props.client.id), {
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
                <p v-if="client.street || client.city" class="text-sm">
                    <span v-if="client.street">{{ client.street }}<br></span>
                    <span v-if="client.zip || client.city">{{ [client.zip, client.city].filter(Boolean).join(' ') }}</span>
                </p>
                <p v-else class="text-sm text-muted-foreground">{{ t('No address') }}</p>
            </template>
            <form v-else class="space-y-3" @submit.prevent="submit">
                <div class="space-y-1.5">
                    <Label for="address-street">{{ t('Street') }}</Label>
                    <AddressAutocomplete
                        id="address-street"
                        v-model="form.street"
                        :placeholder="t('Street and house number')"
                        @place-selected="onPlaceSelected"
                    />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label for="address-zip">{{ t('ZIP') }}</Label>
                        <Input id="address-zip" v-model="form.zip" type="text" :placeholder="t('ZIP')" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="address-city">{{ t('City') }}</Label>
                        <Input id="address-city" v-model="form.city" type="text" :placeholder="t('City')" />
                    </div>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
