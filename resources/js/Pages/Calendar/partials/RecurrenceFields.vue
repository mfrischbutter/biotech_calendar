<script setup lang="ts">
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();

const props = defineProps<{
    isRecurring: boolean;
    recurrenceType: string;
    recurrenceInterval: number;
    recurrenceEnd: string;
}>();

const emit = defineEmits<{
    'update:isRecurring': [value: boolean];
    'update:recurrenceType': [value: string];
    'update:recurrenceInterval': [value: number];
    'update:recurrenceEnd': [value: string];
}>();

const recurrenceLabels: Record<string, string> = {
    weekly: t('Weekly'),
    biweekly: t('Biweekly'),
    monthly: t('Monthly'),
    custom: t('Custom'),
};
</script>

<template>
    <div class="space-y-3">
        <label class="flex items-center gap-2 text-sm cursor-pointer">
            <Checkbox
                :checked="isRecurring"
                @update:checked="(val: boolean) => emit('update:isRecurring', val)"
            />
            {{ t('Recurring Appointment') }}
        </label>

        <div v-if="isRecurring" class="space-y-3 rounded-md border p-3">
            <div class="space-y-2">
                <Label>{{ t('Interval') }}</Label>
                <Select
                    :model-value="recurrenceType"
                    @update:model-value="(val) => emit('update:recurrenceType', String(val))"
                >
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="(label, key) in recurrenceLabels"
                            :key="key"
                            :value="key"
                        >
                            {{ label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div v-if="recurrenceType === 'custom'" class="space-y-2">
                <Label for="appt-interval">{{ t('Every X Weeks') }}</Label>
                <Input
                    id="appt-interval"
                    :model-value="recurrenceInterval"
                    type="number"
                    min="1"
                    max="52"
                    @update:model-value="(val: string | number) => emit('update:recurrenceInterval', Number(val))"
                />
            </div>

            <div class="space-y-2">
                <Label for="appt-recurrence-end">{{ t('Series End Date') }} *</Label>
                <Input
                    id="appt-recurrence-end"
                    :model-value="recurrenceEnd"
                    type="date"
                    required
                    @update:model-value="(val: string | number) => emit('update:recurrenceEnd', String(val))"
                />
            </div>
        </div>
    </div>
</template>
