<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Input } from '@/Components/ui/input';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();

const props = defineProps<{
    street: string;
    zip: string;
    city: string;
    clientAddress: string;
}>();

const emit = defineEmits<{
    'update:street': [value: string];
    'update:zip': [value: string];
    'update:city': [value: string];
}>();

const hasCustomAddress = computed(() => !!props.street || !!props.zip || !!props.city);
const showFields = ref(false);

watch(hasCustomAddress, (val) => {
    if (val) showFields.value = true;
}, { immediate: true });

function clearCustomAddress() {
    emit('update:street', '');
    emit('update:zip', '');
    emit('update:city', '');
    showFields.value = false;
}
</script>

<template>
    <div class="space-y-2">
        <div class="flex items-center justify-between">
            <button
                v-if="!showFields"
                type="button"
                class="text-xs text-primary hover:underline"
                @click="showFields = true"
            >
                {{ t('Custom address') }}
            </button>
            <button
                v-if="showFields && hasCustomAddress"
                type="button"
                class="text-xs text-muted-foreground hover:underline"
                @click="clearCustomAddress"
            >
                {{ t('Use client address') }}
            </button>
        </div>
        <div v-if="showFields" class="space-y-2">
            <Input
                :model-value="street"
                @update:model-value="(v: string | number) => emit('update:street', String(v))"
                :placeholder="t('Street')"
                class="h-9"
            />
            <div class="grid grid-cols-[100px_1fr] gap-2">
                <Input
                    :model-value="zip"
                    @update:model-value="(v: string | number) => emit('update:zip', String(v))"
                    :placeholder="t('ZIP')"
                    class="h-9"
                />
                <Input
                    :model-value="city"
                    @update:model-value="(v: string | number) => emit('update:city', String(v))"
                    :placeholder="t('City')"
                    class="h-9"
                />
            </div>
        </div>
    </div>
</template>
