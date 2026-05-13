<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Pencil, X } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Textarea } from '@/Components/ui/textarea';
import { useTrans } from '@/lib/use-trans';
import type { Client } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    client: Client;
}>();

const editing = ref(false);

const form = useForm({
    notes: props.client.notes ?? '',
});

function resetForm() {
    form.notes = props.client.notes ?? '';
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
        salutation: props.client.salutation,
        first_name: props.client.first_name,
        last_name: props.client.last_name,
        company_name: props.client.company_name,
        billing_name: props.client.billing_name,
        phone: props.client.phone,
        email: props.client.email,
        street: props.client.street,
        zip: props.client.zip,
        city: props.client.city,
        latitude: props.client.latitude,
        longitude: props.client.longitude,
        place_id: props.client.place_id,
        notes: form.notes || null,
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
            <CardTitle class="text-base">{{ t('Notes') }}</CardTitle>
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
                <p v-if="client.notes" class="whitespace-pre-wrap text-sm text-muted-foreground">{{ client.notes }}</p>
                <p v-else class="text-sm text-muted-foreground">{{ t('No notes yet') }}</p>
            </template>
            <form v-else @submit.prevent="submit">
                <Textarea
                    id="notes-textarea"
                    v-model="form.notes"
                    :placeholder="t('Additional information...')"
                    rows="4"
                />
            </form>
        </CardContent>
    </Card>
</template>
