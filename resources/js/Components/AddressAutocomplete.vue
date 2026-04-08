<script setup lang="ts">
import { ref } from 'vue';
import { Input } from '@/Components/ui/input';
import { useGooglePlaces } from '@/lib/use-google-places';
import { useTrans } from '@/lib/use-trans';
import type { PlaceSuggestion } from '@/types';

const { t } = useTrans();

defineProps<{
    modelValue: string;
    placeholder?: string;
    id?: string;
    inputClass?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
    'place-selected': [place: PlaceSuggestion];
}>();

const { suggestions, loading, search, selectPlace, clearSuggestions, isAvailable } = useGooglePlaces();
const dropdownOpen = ref(false);
const focused = ref(false);

function handleInput(value: string | number) {
    const str = String(value);
    emit('update:modelValue', str);
    if (isAvailable && str.length >= 3) {
        search(str);
        dropdownOpen.value = true;
    } else {
        dropdownOpen.value = false;
        clearSuggestions();
    }
}

function handleFocus() {
    focused.value = true;
    if (suggestions.value.length > 0) {
        dropdownOpen.value = true;
    }
}

function handleBlur() {
    focused.value = false;
    setTimeout(() => {
        dropdownOpen.value = false;
    }, 200);
}

async function handleSelect(index: number) {
    dropdownOpen.value = false;
    const item = suggestions.value[index];
    if (!item) return;
    const place = await selectPlace(item);
    if (place) {
        emit('update:modelValue', place.street);
        emit('place-selected', place);
    }
    clearSuggestions();
}
</script>

<template>
    <div class="relative" data-testid="address-autocomplete">
        <Input
            :id="id"
            :model-value="modelValue"
            type="text"
            :placeholder="placeholder"
            :class="inputClass"
            autocomplete="off"
            @update:model-value="handleInput"
            @focus="handleFocus"
            @blur="handleBlur"
        />
        <div
            v-if="isAvailable && dropdownOpen && (loading || suggestions.length > 0)"
            data-testid="address-suggestions"
            class="absolute z-50 mt-1 w-full rounded-md border bg-popover text-popover-foreground shadow-md"
        >
            <div v-if="loading && suggestions.length === 0" class="px-3 py-2 text-sm text-muted-foreground">
                {{ t('Searching...') }}
            </div>
            <ul v-else class="max-h-48 overflow-y-auto py-1">
                <li
                    v-for="(suggestion, index) in suggestions"
                    :key="suggestion.placeId"
                    class="cursor-pointer px-3 py-2 text-sm hover:bg-accent rounded-sm mx-1"
                    @mousedown.prevent="handleSelect(index)"
                >
                    {{ suggestion.description }}
                </li>
            </ul>
        </div>
    </div>
</template>
