<script setup lang="ts">
import { ref, nextTick } from 'vue';
import { Checkbox } from '@/Components/ui/checkbox';
import { useTrans } from '@/lib/use-trans';
import type { ChecklistItem } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    modelValue: ChecklistItem[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: ChecklistItem[]];
}>();

const newItemText = ref('');
const itemRefs = ref<HTMLInputElement[]>([]);
const dragIndex = ref<number | null>(null);
const dropTargetIndex = ref<number | null>(null);

function updateItem(index: number, updates: Partial<ChecklistItem>) {
    const items = [...props.modelValue];
    items[index] = { ...items[index], ...updates };
    emit('update:modelValue', items);
}

function removeItem(index: number) {
    const items = props.modelValue.filter((_, i) => i !== index);
    emit('update:modelValue', items);
}

function handleItemBlur(index: number) {
    if (!props.modelValue[index]?.text.trim()) {
        removeItem(index);
    }
}

function addItem() {
    const text = newItemText.value.trim();
    if (!text) return;
    emit('update:modelValue', [...props.modelValue, { text, checked: false }]);
    newItemText.value = '';
}

function handleNewItemKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter') {
        e.preventDefault();
        addItem();
    }
}

function handleItemKeydown(e: KeyboardEvent, index: number) {
    if (e.key === 'Enter') {
        e.preventDefault();
        newItemText.value = '';
        nextTick(() => {
            const newInput = document.querySelector<HTMLInputElement>('[data-checklist-new-input]');
            newInput?.focus();
        });
    }
    if (e.key === 'Backspace' && !props.modelValue[index].text) {
        e.preventDefault();
        removeItem(index);
        nextTick(() => {
            const inputs = document.querySelectorAll<HTMLInputElement>('[data-checklist-item-input]');
            if (index > 0) inputs[index - 1]?.focus();
        });
    }
}

function onDragStart(e: DragEvent, index: number) {
    dragIndex.value = index;
    if (e.dataTransfer) {
        e.dataTransfer.effectAllowed = 'move';
    }
}

function onDragOver(e: DragEvent, index: number) {
    e.preventDefault();
    if (e.dataTransfer) {
        e.dataTransfer.dropEffect = 'move';
    }
    dropTargetIndex.value = index;
}

function onDragLeave() {
    dropTargetIndex.value = null;
}

function onDrop(e: DragEvent, toIndex: number) {
    e.preventDefault();
    const fromIndex = dragIndex.value;
    if (fromIndex === null || fromIndex === toIndex) {
        dragIndex.value = null;
        dropTargetIndex.value = null;
        return;
    }
    const items = [...props.modelValue];
    const [moved] = items.splice(fromIndex, 1);
    items.splice(toIndex, 0, moved);
    emit('update:modelValue', items);
    dragIndex.value = null;
    dropTargetIndex.value = null;
}

function onDragEnd() {
    dragIndex.value = null;
    dropTargetIndex.value = null;
}
</script>

<template>
    <div class="space-y-1">
        <div
            v-for="(item, index) in modelValue"
            :key="index"
            draggable="true"
            @dragstart="(e) => onDragStart(e, index)"
            @dragover="(e) => onDragOver(e, index)"
            @dragleave="onDragLeave"
            @drop="(e) => onDrop(e, index)"
            @dragend="onDragEnd"
            class="flex items-center gap-2 group rounded transition-colors"
            :class="{
                'opacity-50': dragIndex === index,
                'border-t-2 border-primary': dropTargetIndex === index && dragIndex !== null && dragIndex !== index,
            }"
        >
            <div class="shrink-0 cursor-grab opacity-0 group-hover:opacity-100 text-muted-foreground transition-opacity">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="19" r="1"/></svg>
            </div>
            <Checkbox
                :checked="item.checked"
                @update:checked="(val: boolean) => updateItem(index, { checked: val })"
                class="shrink-0"
            />
            <input
                ref="itemRefs"
                data-checklist-item-input
                :value="item.text"
                @input="(e) => updateItem(index, { text: (e.target as HTMLInputElement).value })"
                @blur="handleItemBlur(index)"
                @keydown="(e) => handleItemKeydown(e, index)"
                class="flex-1 bg-transparent text-sm border-0 outline-none ring-0 focus:outline-none focus:ring-0 p-0 text-foreground placeholder:text-muted-foreground"
                :class="{ 'line-through text-muted-foreground': item.checked }"
            />
            <button
                type="button"
                @click="removeItem(index)"
                class="opacity-0 group-hover:opacity-100 text-muted-foreground hover:text-foreground transition-opacity shrink-0"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="flex items-center gap-2" :class="modelValue.length > 0 ? 'pl-[22px]' : ''">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-muted-foreground shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <input
                data-checklist-new-input
                v-model="newItemText"
                @keydown="handleNewItemKeydown"
                :placeholder="t('Add checklist item...')"
                class="flex-1 bg-transparent text-sm border-0 outline-none ring-0 focus:outline-none focus:ring-0 p-0 text-foreground placeholder:text-muted-foreground"
            />
        </div>
    </div>
</template>
