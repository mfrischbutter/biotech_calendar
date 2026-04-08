<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import {
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/Components/ui/drawer';
import AddressAutocomplete from '@/Components/AddressAutocomplete.vue';
import { useTrans } from '@/lib/use-trans';
import type { Client, PlaceSuggestion } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    client?: Client;
}>();

const open = ref(false);

const form = useForm({
    salutation: props.client?.salutation ?? 'none',
    first_name: props.client?.first_name ?? '',
    last_name: props.client?.last_name ?? '',
    company_name: props.client?.company_name ?? '',
    billing_name: props.client?.billing_name ?? '',
    street: props.client?.street ?? '',
    zip: props.client?.zip ?? '',
    city: props.client?.city ?? '',
    phone: props.client?.phone ?? '',
    email: props.client?.email ?? '',
    notes: props.client?.notes ?? '',
    latitude: props.client?.latitude ?? null,
    longitude: props.client?.longitude ?? null,
    place_id: props.client?.place_id ?? null,
});

function onPlaceSelected(place: PlaceSuggestion) {
    form.street = place.street;
    form.zip = place.zip;
    form.city = place.city;
    form.latitude = place.latitude;
    form.longitude = place.longitude;
    form.place_id = place.placeId;
}

const isEditing = !!props.client;

watch(open, (value) => {
    if (!value) {
        form.clearErrors();
        if (!isEditing) {
            form.reset();
        }
    }
});

function submit() {
    const payload = {
        ...form.data(),
        salutation: form.salutation && form.salutation !== 'none' ? form.salutation : null,
    };

    if (isEditing) {
        form.transform(() => payload).put(route('clients.update', props.client!.id), {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
        });
    } else {
        form.transform(() => payload).post(route('clients.store'), {
            preserveScroll: true,
            onSuccess: () => {
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
    <Drawer v-model:open="open" direction="right">
        <DrawerContent
            class="fixed inset-y-0 right-0 left-auto mt-0 flex h-full w-full flex-col rounded-l-[10px] rounded-t-none sm:max-w-[520px]"
        >
            <DrawerHeader class="relative">
                <DrawerTitle>{{ isEditing ? t('Edit Client') : t('New Client') }}</DrawerTitle>
                <DrawerDescription>
                    {{ isEditing ? t('Update client details.') : t('Create a new client.') }}
                </DrawerDescription>
                <button
                    type="button"
                    class="absolute right-4 top-4 rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                    @click="open = false"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    <span class="sr-only">Close</span>
                </button>
            </DrawerHeader>

            <div class="flex flex-1 flex-col overflow-hidden">
                <div class="no-scrollbar flex-1 overflow-y-auto px-4 pb-4">
                    <form id="client-form" @submit.prevent="submit" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="client-salutation">{{ t('Salutation') }}</Label>
                                <Select v-model="form.salutation">
                                    <SelectTrigger id="client-salutation">
                                        <SelectValue :placeholder="t('Select...')" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">{{ t('No selection') }}</SelectItem>
                                        <SelectItem value="Herr">{{ t('Mr') }}</SelectItem>
                                        <SelectItem value="Frau">{{ t('Mrs') }}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div />

                            <div class="space-y-2">
                                <Label for="client-first-name">{{ t('First name') }} *</Label>
                                <Input
                                    id="client-first-name"
                                    v-model="form.first_name"
                                    type="text"
                                    :placeholder="t('First name')"
                                    required
                                />
                                <p v-if="form.errors.first_name" class="text-sm text-destructive">
                                    {{ form.errors.first_name }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="client-last-name">{{ t('Last name') }} *</Label>
                                <Input
                                    id="client-last-name"
                                    v-model="form.last_name"
                                    type="text"
                                    :placeholder="t('Last name')"
                                    required
                                />
                                <p v-if="form.errors.last_name" class="text-sm text-destructive">
                                    {{ form.errors.last_name }}
                                </p>
                            </div>

                            <div class="space-y-2 col-span-2">
                                <Label for="client-company-name">{{ t('Company name') }}</Label>
                                <Input
                                    id="client-company-name"
                                    v-model="form.company_name"
                                    type="text"
                                    :placeholder="t('Company name')"
                                />
                                <p v-if="form.errors.company_name" class="text-sm text-destructive">
                                    {{ form.errors.company_name }}
                                </p>
                            </div>

                            <div class="space-y-2 col-span-2">
                                <Label for="client-billing-name">{{ t('Billing Name') }}</Label>
                                <Input
                                    id="client-billing-name"
                                    v-model="form.billing_name"
                                    type="text"
                                    :placeholder="t('If different')"
                                />
                                <p v-if="form.errors.billing_name" class="text-sm text-destructive">
                                    {{ form.errors.billing_name }}
                                </p>
                            </div>

                            <div class="space-y-2 col-span-2">
                                <Label for="client-street">{{ t('Street') }}</Label>
                                <AddressAutocomplete
                                    id="client-street"
                                    v-model="form.street"
                                    :placeholder="t('Street and house number')"
                                    @place-selected="onPlaceSelected"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="client-zip">{{ t('ZIP') }}</Label>
                                <Input
                                    id="client-zip"
                                    v-model="form.zip"
                                    type="text"
                                    :placeholder="t('ZIP')"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="client-city">{{ t('City') }}</Label>
                                <Input
                                    id="client-city"
                                    v-model="form.city"
                                    type="text"
                                    :placeholder="t('City')"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="client-phone">{{ t('Phone') }}</Label>
                                <Input
                                    id="client-phone"
                                    v-model="form.phone"
                                    type="tel"
                                    :placeholder="t('Phone number')"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="client-email">{{ t('Email') }}</Label>
                                <Input
                                    id="client-email"
                                    v-model="form.email"
                                    type="email"
                                    placeholder="email@example.com"
                                />
                                <p v-if="form.errors.email" class="text-sm text-destructive">
                                    {{ form.errors.email }}
                                </p>
                            </div>

                            <div class="space-y-2 col-span-2">
                                <Label for="client-notes">{{ t('Notes') }}</Label>
                                <Textarea
                                    id="client-notes"
                                    v-model="form.notes"
                                    :placeholder="t('Additional information...')"
                                    rows="3"
                                />
                            </div>
                        </div>
                    </form>
                </div>

                <DrawerFooter class="border-t">
                    <Button type="submit" form="client-form" :disabled="form.processing">
                        {{ form.processing ? t('Saving...') : (isEditing ? t('Update') : t('Create')) }}
                    </Button>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>
</template>
