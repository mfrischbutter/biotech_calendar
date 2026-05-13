<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Pencil, X } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { useTrans } from '@/lib/use-trans';
import type { Client } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    client: Client;
}>();

const SALUTATION_LABELS: Record<string, string> = {
    Herr: 'Mr',
    Frau: 'Mrs',
};

const editing = ref(false);

const form = useForm({
    salutation: props.client.salutation ?? 'none',
    first_name: props.client.first_name,
    last_name: props.client.last_name,
    company_name: props.client.company_name ?? '',
    billing_name: props.client.billing_name ?? '',
    phone: props.client.phone ?? '',
    email: props.client.email ?? '',
});

function resetForm() {
    form.salutation = props.client.salutation ?? 'none';
    form.first_name = props.client.first_name;
    form.last_name = props.client.last_name;
    form.company_name = props.client.company_name ?? '';
    form.billing_name = props.client.billing_name ?? '';
    form.phone = props.client.phone ?? '';
    form.email = props.client.email ?? '';
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

function submit() {
    const payload = {
        salutation: form.salutation && form.salutation !== 'none' ? form.salutation : null,
        first_name: form.first_name,
        last_name: form.last_name,
        company_name: form.company_name || null,
        billing_name: form.billing_name || null,
        phone: form.phone || null,
        email: form.email || null,
        street: props.client.street,
        zip: props.client.zip,
        city: props.client.city,
        latitude: props.client.latitude,
        longitude: props.client.longitude,
        place_id: props.client.place_id,
        notes: props.client.notes,
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
            <CardTitle class="text-base">{{ t('Contact Information') }}</CardTitle>
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
                    <div v-if="client.salutation">
                        <dt class="text-sm text-muted-foreground">{{ t('Salutation') }}</dt>
                        <dd class="text-sm font-medium">{{ t(SALUTATION_LABELS[client.salutation] ?? client.salutation) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">{{ t('Name') }}</dt>
                        <dd class="text-sm font-medium">{{ client.first_name }} {{ client.last_name }}</dd>
                    </div>
                    <div v-if="client.company_name">
                        <dt class="text-sm text-muted-foreground">{{ t('Company name') }}</dt>
                        <dd class="text-sm font-medium">{{ client.company_name }}</dd>
                    </div>
                    <div v-if="client.billing_name">
                        <dt class="text-sm text-muted-foreground">{{ t('Billing Name') }}</dt>
                        <dd class="text-sm font-medium">{{ client.billing_name }}</dd>
                    </div>
                    <div v-if="client.email">
                        <dt class="text-sm text-muted-foreground">{{ t('Email') }}</dt>
                        <dd class="text-sm font-medium">
                            <a :href="'mailto:' + client.email" class="text-primary hover:underline">{{ client.email }}</a>
                        </dd>
                    </div>
                    <div v-if="client.phone">
                        <dt class="text-sm text-muted-foreground">{{ t('Phone') }}</dt>
                        <dd class="text-sm font-medium">
                            <a :href="'tel:' + client.phone" class="text-primary hover:underline">{{ client.phone }}</a>
                        </dd>
                    </div>
                </dl>
            </template>
            <form v-else class="space-y-4" @submit.prevent="submit">
                <div class="space-y-1.5">
                    <Label for="contact-salutation">{{ t('Salutation') }}</Label>
                    <Select v-model="form.salutation">
                        <SelectTrigger id="contact-salutation">
                            <SelectValue :placeholder="t('Select...')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">{{ t('No selection') }}</SelectItem>
                            <SelectItem value="Herr">{{ t('Mr') }}</SelectItem>
                            <SelectItem value="Frau">{{ t('Mrs') }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label for="contact-first-name">{{ t('First name') }} *</Label>
                        <Input id="contact-first-name" v-model="form.first_name" type="text" required />
                        <p v-if="form.errors.first_name" class="text-sm text-destructive">{{ form.errors.first_name }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="contact-last-name">{{ t('Last name') }} *</Label>
                        <Input id="contact-last-name" v-model="form.last_name" type="text" required />
                        <p v-if="form.errors.last_name" class="text-sm text-destructive">{{ form.errors.last_name }}</p>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <Label for="contact-company-name">{{ t('Company name') }}</Label>
                    <Input id="contact-company-name" v-model="form.company_name" type="text" />
                    <p v-if="form.errors.company_name" class="text-sm text-destructive">{{ form.errors.company_name }}</p>
                </div>

                <div class="space-y-1.5">
                    <Label for="contact-billing-name">{{ t('Billing Name') }}</Label>
                    <Input id="contact-billing-name" v-model="form.billing_name" type="text" :placeholder="t('If different')" />
                    <p v-if="form.errors.billing_name" class="text-sm text-destructive">{{ form.errors.billing_name }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label for="contact-phone">{{ t('Phone') }}</Label>
                        <Input id="contact-phone" v-model="form.phone" type="tel" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="contact-email">{{ t('Email') }}</Label>
                        <Input id="contact-email" v-model="form.email" type="email" placeholder="email@example.com" />
                        <p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p>
                    </div>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
