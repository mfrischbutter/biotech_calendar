<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { ListChecks, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import ConfirmDeleteDialog from '@/Components/ConfirmDeleteDialog.vue';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/Components/ui/empty';
import { useTrans } from '@/lib/use-trans';
import type { ChecklistTemplate } from '@/types';
import ChecklistTemplateEditor from './ChecklistTemplateEditor.vue';

const { t } = useTrans();

defineProps<{
    templates: ChecklistTemplate[];
}>();

const KIND_LABELS: Record<string, string> = {
    kundentermin: t('Client appointment'),
    ohne_termin: t('Without appointment'),
};

const editing = ref<ChecklistTemplate | null>(null);
const creating = ref(false);
const deleteTarget = ref<ChecklistTemplate | null>(null);

function startCreate() {
    editing.value = null;
    creating.value = true;
}

function startEdit(template: ChecklistTemplate) {
    creating.value = false;
    editing.value = template;
}

function closeEditor() {
    creating.value = false;
    editing.value = null;
}

function executeDelete() {
    if (!deleteTarget.value) return;
    router.delete(route('checklist-templates.destroy', deleteTarget.value.id), { preserveScroll: true });
    deleteTarget.value = null;
}
</script>

<template>
    <div class="space-y-6">
        <Card>
            <CardHeader class="flex-row items-start justify-between gap-4 space-y-0">
                <div class="space-y-1.5">
                    <CardTitle class="text-base">{{ t('Checklists') }}</CardTitle>
                    <CardDescription>
                        {{ t('Reusable on-site checklists your team can apply to an appointment.') }}
                    </CardDescription>
                </div>
                <Button v-if="!creating && !editing" size="sm" data-testid="add-template" @click="startCreate">
                    <Plus class="mr-1.5 h-4 w-4" />
                    {{ t('New template') }}
                </Button>
            </CardHeader>
            <CardContent class="space-y-4">
                <ChecklistTemplateEditor
                    v-if="creating || editing"
                    :key="editing?.id ?? 'new'"
                    :template="editing"
                    @done="closeEditor"
                />

                <div v-if="templates.length > 0" class="space-y-2">
                    <div
                        v-for="template in templates"
                        :key="template.id"
                        data-testid="template-row"
                        class="group flex items-center gap-3 rounded-md border bg-background px-3 py-2"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-foreground">{{ template.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ t(':count items', { count: String(template.items.length) }) }}
                            </p>
                        </div>
                        <Badge variant="secondary" class="shrink-0 font-normal">
                            {{ template.kind ? KIND_LABELS[template.kind] : t('All service types') }}
                        </Badge>
                        <div class="flex shrink-0 items-center gap-1">
                            <Button variant="ghost" size="icon-sm" @click="startEdit(template)">
                                <Pencil class="h-3.5 w-3.5" />
                                <span class="sr-only">{{ t('Edit') }}</span>
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                class="text-destructive"
                                @click="deleteTarget = template"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                                <span class="sr-only">{{ t('Delete') }}</span>
                            </Button>
                        </div>
                    </div>
                </div>

                <Empty v-else-if="!creating" class="py-10">
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <ListChecks class="h-6 w-6" />
                        </EmptyMedia>
                        <EmptyTitle>{{ t('No checklist templates yet') }}</EmptyTitle>
                        <EmptyDescription>
                            {{ t('Save a checklist once and your team can apply it to every appointment of that type.') }}
                        </EmptyDescription>
                    </EmptyHeader>
                    <EmptyContent>
                        <Button size="sm" @click="startCreate">
                            <Plus class="mr-1.5 h-4 w-4" />
                            {{ t('New template') }}
                        </Button>
                    </EmptyContent>
                </Empty>
            </CardContent>
        </Card>

        <ConfirmDeleteDialog
            :open="!!deleteTarget"
            :title="t('Delete template?')"
            :description="t('Checklists already added to an appointment are not affected.')"
            @update:open="(open: boolean) => { if (!open) deleteTarget = null }"
            @confirm="executeDelete"
        />
    </div>
</template>
