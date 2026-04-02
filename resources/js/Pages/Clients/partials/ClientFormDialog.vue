<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/Components/ui/dialog';
import type { Client } from '@/types';

const props = defineProps<{
    client?: Client;
}>();

const open = ref(false);

const form = useForm({
    name: props.client?.name ?? '',
    billing_name: props.client?.billing_name ?? '',
    street: props.client?.street ?? '',
    zip: props.client?.zip ?? '',
    city: props.client?.city ?? '',
    phone: props.client?.phone ?? '',
    email: props.client?.email ?? '',
    notes: props.client?.notes ?? '',
});

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
    if (isEditing) {
        form.put(route('clients.update', props.client!.id), {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
        });
    } else {
        form.post(route('clients.store'), {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
        });
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <slot />
        </DialogTrigger>
        <DialogContent class="sm:max-w-[520px]">
            <DialogHeader>
                <DialogTitle>{{ isEditing ? 'Kunde bearbeiten' : 'Neuer Kunde' }}</DialogTitle>
                <DialogDescription>
                    {{ isEditing ? 'Kundendaten aktualisieren.' : 'Neuen Kunden anlegen.' }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2 col-span-2">
                        <Label for="client-name">Name *</Label>
                        <Input
                            id="client-name"
                            v-model="form.name"
                            type="text"
                            placeholder="Kundenname"
                            required
                        />
                        <p v-if="form.errors.name" class="text-sm text-destructive">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div class="space-y-2 col-span-2">
                        <Label for="client-billing-name">Rechnungsname</Label>
                        <Input
                            id="client-billing-name"
                            v-model="form.billing_name"
                            type="text"
                            placeholder="Falls abweichend"
                        />
                        <p v-if="form.errors.billing_name" class="text-sm text-destructive">
                            {{ form.errors.billing_name }}
                        </p>
                    </div>

                    <div class="space-y-2 col-span-2">
                        <Label for="client-street">Straße</Label>
                        <Input
                            id="client-street"
                            v-model="form.street"
                            type="text"
                            placeholder="Straße und Hausnummer"
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="client-zip">PLZ</Label>
                        <Input
                            id="client-zip"
                            v-model="form.zip"
                            type="text"
                            placeholder="PLZ"
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="client-city">Ort</Label>
                        <Input
                            id="client-city"
                            v-model="form.city"
                            type="text"
                            placeholder="Ort"
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="client-phone">Telefon</Label>
                        <Input
                            id="client-phone"
                            v-model="form.phone"
                            type="tel"
                            placeholder="Telefonnummer"
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="client-email">E-Mail</Label>
                        <Input
                            id="client-email"
                            v-model="form.email"
                            type="email"
                            placeholder="email@beispiel.de"
                        />
                        <p v-if="form.errors.email" class="text-sm text-destructive">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div class="space-y-2 col-span-2">
                        <Label for="client-notes">Notizen</Label>
                        <Textarea
                            id="client-notes"
                            v-model="form.notes"
                            placeholder="Zusätzliche Informationen..."
                            rows="3"
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Speichern...' : (isEditing ? 'Aktualisieren' : 'Erstellen') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
