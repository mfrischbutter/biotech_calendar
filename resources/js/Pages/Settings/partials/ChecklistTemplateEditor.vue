<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Plus, X } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
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
import type { AppointmentKind, ChecklistTemplate } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    template: ChecklistTemplate | null;
}>();

const emit = defineEmits<{
    done: [];
}>();

const KINDS: { value: AppointmentKind; label: string }[] = [
    { value: 'kundentermin', label: t('Client appointment') },
    { value: 'ohne_termin', label: t('Without appointment') },
];

const form = useForm({
    name: props.template?.name ?? '',
    kind: (props.template?.kind ?? 'none') as string,
    items: [...(props.template?.items ?? [''])] as string[],
});

const newItem = ref('');

function updateItem(index: number, value: string) {
    form.items = form.items.map((item, i) => (i === index ? value : item));
}

function removeItem(index: number) {
    form.items = form.items.filter((_, i) => i !== index);
}

function addItem() {
    const text = newItem.value.trim();
    if (!text) return;
    form.items = [...form.items, text];
    newItem.value = '';
}

function submit() {
    const payload = form.transform((data) => ({
        name: data.name,
        kind: data.kind === 'none' ? null : data.kind,
        items: data.items.map(item => item.trim()).filter(Boolean),
    }));

    const options = { preserveScroll: true, onSuccess: () => emit('done') };

    if (props.template) {
        payload.put(route('checklist-templates.update', props.template.id), options);
    } else {
        payload.post(route('checklist-templates.store'), options);
    }
}
</script>

<template>
    <form class="space-y-4 rounded-lg border bg-background p-4" @submit.prevent="submit">
        <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_12rem]">
            <div class="space-y-2">
                <Label for="template-name">{{ t('Name') }} *</Label>
                <Input id="template-name" v-model="form.name" type="text" required :placeholder="t('Template name')" />
                <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
            </div>
            <div class="space-y-2">
                <Label>{{ t('Kind') }}</Label>
                <Select v-model="form.kind">
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">{{ t('All service types') }}</SelectItem>
                        <SelectItem v-for="kind in KINDS" :key="kind.value" :value="kind.value">
                            {{ kind.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <div class="space-y-2">
            <Label>{{ t('Checklist items') }} *</Label>
            <div v-for="(item, index) in form.items" :key="index" class="flex items-center gap-2">
                <Input
                    :model-value="item"
                    type="text"
                    data-testid="template-item"
                    @update:model-value="(v: string | number) => updateItem(index, String(v))"
                />
                <Button type="button" variant="ghost" size="icon-sm" @click="removeItem(index)">
                    <X class="h-4 w-4" />
                    <span class="sr-only">{{ t('Remove') }}</span>
                </Button>
            </div>
            <div class="flex items-center gap-2">
                <Input
                    v-model="newItem"
                    type="text"
                    :placeholder="t('Add checklist item...')"
                    @keydown.enter.prevent="addItem"
                />
                <Button type="button" variant="ghost" size="icon-sm" @click="addItem">
                    <Plus class="h-4 w-4" />
                    <span class="sr-only">{{ t('Add') }}</span>
                </Button>
            </div>
            <p v-if="form.errors.items" class="text-sm text-destructive">{{ form.errors.items }}</p>
        </div>

        <div class="flex items-center gap-2">
            <Button type="submit" size="sm" :disabled="form.processing">
                {{ form.processing ? t('Saving...') : (template ? t('Update') : t('Create')) }}
            </Button>
            <Button type="button" variant="outline" size="sm" @click="emit('done')">{{ t('Cancel') }}</Button>
        </div>
    </form>
</template>
