<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import AddressAutocomplete from '@/Components/AddressAutocomplete.vue';
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
    logo: null as File | null,
});

function onPlaceSelected(place: PlaceSuggestion) {
    form.street = place.street;
    form.zip = place.zip;
    form.city = place.city;
    form.latitude = place.latitude;
    form.longitude = place.longitude;
    form.place_id = place.placeId;
}

const logoPreview = ref<string | null>(
    props.company.logo_path ? `/storage/${props.company.logo_path}` : null,
);

function handleLogoChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) {
        form.logo = file;
        logoPreview.value = URL.createObjectURL(file);
    }
}

function submit() {
    form.post(route('settings.company.update'), {
        preserveScroll: true,
        forceFormData: true,
        headers: { 'X-HTTP-Method-Override': 'PUT' },
    });
}
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <div>
            <h3 class="text-base font-medium text-foreground">{{ t('Company Profile') }}</h3>
            <p class="text-sm text-muted-foreground mt-1">{{ t('Update your company information.') }}</p>
        </div>

        <!-- Logo -->
        <div class="space-y-2">
            <Label>{{ t('Logo') }}</Label>
            <div class="flex items-center gap-4">
                <div
                    v-if="logoPreview"
                    class="w-16 h-16 rounded-lg border overflow-hidden bg-muted"
                >
                    <img :src="logoPreview" alt="Logo" class="w-full h-full object-cover" />
                </div>
                <div v-else class="w-16 h-16 rounded-lg border bg-muted flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                </div>
                <Input type="file" accept="image/*" @change="handleLogoChange" class="max-w-xs" />
            </div>
            <p v-if="form.errors.logo" class="text-sm text-destructive">{{ form.errors.logo }}</p>
        </div>

        <div class="space-y-2">
            <Label for="company-name">{{ t('Name') }} *</Label>
            <Input id="company-name" v-model="form.name" type="text" required />
            <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
        </div>

        <div class="space-y-2">
            <Label for="company-street">{{ t('Street') }}</Label>
            <AddressAutocomplete
                id="company-street"
                v-model="form.street"
                @place-selected="onPlaceSelected"
            />
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div class="space-y-2">
                <Label for="company-zip">{{ t('ZIP') }}</Label>
                <Input id="company-zip" v-model="form.zip" type="text" />
            </div>
            <div class="col-span-2 space-y-2">
                <Label for="company-city">{{ t('City') }}</Label>
                <Input id="company-city" v-model="form.city" type="text" />
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
                <Label for="company-phone">{{ t('Phone') }}</Label>
                <Input id="company-phone" v-model="form.phone" type="text" />
            </div>
            <div class="space-y-2">
                <Label for="company-email">{{ t('Email') }}</Label>
                <Input id="company-email" v-model="form.email" type="email" />
            </div>
        </div>

        <div class="flex items-center gap-3">
            <Button type="submit" :disabled="form.processing">
                {{ form.processing ? t('Saving...') : t('Save') }}
            </Button>
            <span v-if="form.recentlySuccessful" class="text-sm text-muted-foreground">{{ t('Saved.') }}</span>
        </div>
    </form>
</template>
