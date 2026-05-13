<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Pencil, X } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Textarea } from '@/Components/ui/textarea';
import { useTrans } from '@/lib/use-trans';
import type { Contract } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    contract: Contract;
}>();

const editing = ref(false);

const form = useForm({
    description: props.contract.description ?? '',
});

function resetForm() {
    form.description = props.contract.description ?? '';
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
        contract_number: props.contract.contract_number,
        title: props.contract.title,
        kind: props.contract.kind,
        description: form.description || null,
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
            <CardTitle class="text-base">{{ t('Description') }}</CardTitle>
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
                <p v-if="contract.description" class="whitespace-pre-wrap text-sm text-muted-foreground">{{ contract.description }}</p>
                <p v-else class="text-sm text-muted-foreground">{{ t('No description yet') }}</p>
            </template>
            <form v-else @submit.prevent="submit">
                <Textarea
                    id="contract-description-textarea"
                    v-model="form.description"
                    :placeholder="t('Add a description...')"
                    rows="4"
                />
            </form>
        </CardContent>
    </Card>
</template>
