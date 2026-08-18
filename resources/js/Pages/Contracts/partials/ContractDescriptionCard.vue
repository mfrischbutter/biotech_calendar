<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Pencil, X } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
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
    access_notes: props.contract.access_notes ?? '',
});

function resetForm() {
    form.description = props.contract.description ?? '';
    form.access_notes = props.contract.access_notes ?? '';
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
        access_notes: form.access_notes || null,
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
            <CardTitle class="text-base">{{ t('Description & access') }}</CardTitle>
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
        <CardContent class="space-y-4">
            <template v-if="!editing">
                <div>
                    <p class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        {{ t('Description') }}
                    </p>
                    <p v-if="contract.description" class="whitespace-pre-wrap text-sm text-muted-foreground">
                        {{ contract.description }}
                    </p>
                    <p v-else class="text-sm text-muted-foreground">{{ t('No description yet') }}</p>
                </div>
                <div>
                    <p class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ t('Site access') }}</p>
                    <p v-if="contract.access_notes" class="whitespace-pre-wrap text-sm text-muted-foreground">
                        {{ contract.access_notes }}
                    </p>
                    <p v-else class="text-sm text-muted-foreground">{{ t('No access notes yet') }}</p>
                </div>
            </template>
            <form v-else class="space-y-4" @submit.prevent="submit">
                <div class="space-y-1.5">
                    <Label for="contract-description-textarea">{{ t('Description') }}</Label>
                    <Textarea
                        id="contract-description-textarea"
                        v-model="form.description"
                        :placeholder="t('Add a description...')"
                        rows="4"
                    />
                </div>
                <div class="space-y-1.5">
                    <Label for="contract-access-notes-textarea">{{ t('Site access') }}</Label>
                    <Textarea
                        id="contract-access-notes-textarea"
                        v-model="form.access_notes"
                        :placeholder="t('Key safe, gate code, where to report on arrival...')"
                        rows="3"
                    />
                    <p class="text-xs text-muted-foreground">{{ t('Shown to the technician on the detail page.') }}</p>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
