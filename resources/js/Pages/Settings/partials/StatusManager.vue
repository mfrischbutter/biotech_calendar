<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/Components/ui/empty';
import { STATUS_COLORS, getStatusDotStyle } from '@/lib/status-colors';
import { useTrans } from '@/lib/use-trans';
import type { Status } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    statuses: Status[];
}>();

const showForm = ref(false);
const editingStatus = ref<Status | null>(null);
const deleteTarget = ref<Status | null>(null);

const form = useForm({
    name: '',
    color: STATUS_COLORS[0],
});

function openCreate() {
    editingStatus.value = null;
    form.name = '';
    form.color = STATUS_COLORS[0];
    showForm.value = true;
}

function openEdit(status: Status) {
    editingStatus.value = status;
    form.name = status.name;
    form.color = status.color;
    showForm.value = true;
}

function cancelForm() {
    showForm.value = false;
    editingStatus.value = null;
    form.reset();
    form.clearErrors();
}

function submitForm() {
    if (editingStatus.value) {
        form.put(route('statuses.update', editingStatus.value.id), {
            preserveScroll: true,
            onSuccess: () => { cancelForm(); },
        });
    } else {
        form.post(route('statuses.store'), {
            preserveScroll: true,
            onSuccess: () => { cancelForm(); },
        });
    }
}

function confirmDelete(status: Status) {
    deleteTarget.value = status;
}

function executeDelete() {
    if (!deleteTarget.value) return;
    router.delete(route('statuses.destroy', deleteTarget.value.id), {
        preserveScroll: true,
    });
    deleteTarget.value = null;
}

// Drag reorder
const dragIndex = ref<number | null>(null);

function handleDragStart(index: number) {
    dragIndex.value = index;
}

function handleDragOver(e: DragEvent, index: number) {
    e.preventDefault();
    if (dragIndex.value === null || dragIndex.value === index) return;
}

function handleDrop(e: DragEvent, targetIndex: number) {
    e.preventDefault();
    if (dragIndex.value === null || dragIndex.value === targetIndex) return;

    const reordered = [...props.statuses];
    const [moved] = reordered.splice(dragIndex.value, 1);
    reordered.splice(targetIndex, 0, moved);

    const payload = reordered.map((status, i) => ({ id: status.id, sort_order: i }));
    router.put(route('statuses.reorder'), { statuses: payload }, { preserveScroll: true });

    dragIndex.value = null;
}

function handleDragEnd() {
    dragIndex.value = null;
}

const canEdit = computed(() => {
    // Will be handled server-side via permissions
    return true;
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-base font-medium text-foreground">{{ t('Status Management') }}</h3>
                <p class="text-sm text-muted-foreground mt-1">{{ t('Create and organize statuses for your appointments.') }}</p>
            </div>
            <Button v-if="!showForm" size="sm" @click="openCreate">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                {{ t('Add Status') }}
            </Button>
        </div>

        <!-- Inline form -->
        <div v-if="showForm" class="rounded-lg border bg-background p-4 space-y-4">
            <div class="space-y-2">
                <Label>{{ t('Name') }} *</Label>
                <Input v-model="form.name" type="text" required :placeholder="t('Status name')" />
                <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
            </div>

            <div class="space-y-2">
                <Label>{{ t('Color') }} *</Label>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="color in STATUS_COLORS"
                        :key="color"
                        type="button"
                        class="w-7 h-7 rounded-full border-2 transition-all"
                        :class="form.color === color ? 'border-foreground scale-110' : 'border-transparent hover:scale-105'"
                        :style="{ backgroundColor: color }"
                        @click="form.color = color"
                    />
                </div>
                <p v-if="form.errors.color" class="text-sm text-destructive">{{ form.errors.color }}</p>
            </div>

            <div class="flex items-center gap-2">
                <Button size="sm" :disabled="form.processing" @click="submitForm">
                    {{ form.processing ? t('Saving...') : (editingStatus ? t('Update') : t('Create')) }}
                </Button>
                <Button variant="outline" size="sm" @click="cancelForm">{{ t('Cancel') }}</Button>
            </div>
        </div>

        <!-- Status list -->
        <div v-if="statuses.length > 0" class="space-y-1">
            <div
                v-for="(status, index) in statuses"
                :key="status.id"
                class="flex items-center gap-3 rounded-md border bg-background px-3 py-2 group"
                draggable="true"
                @dragstart="handleDragStart(index)"
                @dragover="handleDragOver($event, index)"
                @drop="handleDrop($event, index)"
                @dragend="handleDragEnd"
            >
                <!-- Drag handle -->
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-muted-foreground cursor-grab shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="19" r="1"/></svg>

                <span class="w-3 h-3 rounded-full shrink-0" :style="getStatusDotStyle(status.color)" />
                <span class="flex-1 text-sm text-foreground">{{ status.name }}</span>

                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    <Button variant="ghost" size="icon" class="h-7 w-7" @click="openEdit(status)">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </Button>
                    <Button variant="ghost" size="icon" class="h-7 w-7 text-destructive" @click="confirmDelete(status)">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </Button>
                </div>
            </div>
        </div>

        <Empty v-else-if="!showForm" class="py-8">
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" /><line x1="7" y1="7" x2="7.01" y2="7" /></svg>
                </EmptyMedia>
                <EmptyTitle>{{ t('Statuses') }}</EmptyTitle>
                <EmptyDescription>
                    {{ t('No statuses yet. Click "Add Status" to get started.') }}
                </EmptyDescription>
            </EmptyHeader>
            <EmptyContent>
                <Button size="sm" @click="openCreate">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    {{ t('Add Status') }}
                </Button>
            </EmptyContent>
        </Empty>

        <!-- Delete confirmation -->
        <AlertDialog :open="!!deleteTarget" @update:open="(v: boolean) => { if (!v) deleteTarget = null }">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{{ t('Delete Status') }}?</AlertDialogTitle>
                    <AlertDialogDescription>
                        {{ t('This status will be removed. Appointments using this status will keep their data but lose the status reference.') }}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>{{ t('Cancel') }}</AlertDialogCancel>
                    <AlertDialogAction @click="executeDelete">{{ t('Delete') }}</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
