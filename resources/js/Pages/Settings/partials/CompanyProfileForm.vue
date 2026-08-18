<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import AddressAutocomplete from '@/Components/AddressAutocomplete.vue';
import StickySaveBar from '@/Components/StickySaveBar.vue';
import { useTrans } from '@/lib/use-trans';
import type { Company, PlaceSuggestion } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    company: Company;
}>();

const form = useForm({
    name: props.company.name,
    street: props.company.street ?? '',
    zip: props.company.zip ?? '',
    city: props.company.city ?? '',
    phone: props.company.phone ?? '',
    email: props.company.email ?? '',
    latitude: props.company.latitude ?? null,
    longitude: props.company.longitude ?? null,
    place_id: props.company.place_id ?? null,
});

function onPlaceSelected(place: PlaceSuggestion) {
    form.street = place.street;
    form.zip = place.zip;
    form.city = place.city;
    form.latitude = place.latitude;
    form.longitude = place.longitude;
    form.place_id = place.placeId;
}

function submit() {
    form.put(route('settings.company.update'), {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
}
</script>

<template>
    <form id="company-profile-form" class="space-y-6" @submit.prevent="submit">
        <Card>
            <CardHeader>
                <CardTitle class="text-base">{{ t('Company Profile') }}</CardTitle>
                <CardDescription>{{ t('The name and contact details that identify your company.') }}</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <Label for="company-name">{{ t('Name') }} *</Label>
                    <Input id="company-name" v-model="form.name" type="text" required />
                    <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="company-phone">{{ t('Phone') }}</Label>
                        <Input id="company-phone" v-model="form.phone" type="text" />
                        <p v-if="form.errors.phone" class="text-sm text-destructive">{{ form.errors.phone }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="company-email">{{ t('Email') }}</Label>
                        <Input id="company-email" v-model="form.email" type="email" />
                        <p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">{{ t('Address') }}</CardTitle>
                <CardDescription>{{ t('Used as the starting point for routes and invoices.') }}</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <Label for="company-street">{{ t('Street') }}</Label>
                    <AddressAutocomplete
                        id="company-street"
                        v-model="form.street"
                        @place-selected="onPlaceSelected"
                    />
                </div>

                <!-- Short field next to the long one it belongs with. -->
                <div class="grid gap-4 sm:grid-cols-[8rem_minmax(0,1fr)]">
                    <div class="space-y-2">
                        <Label for="company-zip">{{ t('ZIP') }}</Label>
                        <Input id="company-zip" v-model="form.zip" type="text" inputmode="numeric" />
                    </div>
                    <div class="space-y-2">
                        <Label for="company-city">{{ t('City') }}</Label>
                        <Input id="company-city" v-model="form.city" type="text" />
                    </div>
                </div>
            </CardContent>
        </Card>

        <StickySaveBar
            form="company-profile-form"
            :dirty="form.isDirty"
            :processing="form.processing"
            :saved="form.recentlySuccessful"
            @discard="form.reset()"
        />
    </form>
</template>
