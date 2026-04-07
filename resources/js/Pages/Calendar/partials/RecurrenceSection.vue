<script setup lang="ts">
import { Checkbox } from '@/Components/ui/checkbox';
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
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center gap-2">
            <Checkbox
                :checked="isRecurring"
                @update:checked="(val: boolean) => emit('update:isRecurring', val)"
            />
            <Label class="text-sm cursor-pointer" @click="emit('update:isRecurring', !isRecurring)">
                {{ t('Recurring Appointment') }}
            </Label>
        </div>

        <div v-if="isRecurring" class="space-y-3 pl-6">
            <div class="space-y-1.5">
                <Label class="text-xs text-muted-foreground">{{ t('Interval') }}</Label>
                <Select
                    :model-value="recurrenceType"
                    @update:model-value="(val) => emit('update:recurrenceType', String(val))"
                >
                    <SelectTrigger class="h-9">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="weekly">{{ t('Weekly') }}</SelectItem>
                        <SelectItem value="biweekly">{{ t('Biweekly') }}</SelectItem>
                        <SelectItem value="monthly">{{ t('Monthly') }}</SelectItem>
                        <SelectItem value="custom">{{ t('Custom') }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div v-if="recurrenceType === 'custom'" class="space-y-1.5">
                <Label class="text-xs text-muted-foreground">{{ t('Every X Weeks') }}</Label>
                <Input
                    :model-value="recurrenceInterval"
                    type="number"
                    min="1"
                    max="52"
                    class="h-9"
                    @update:model-value="(val: string | number) => emit('update:recurrenceInterval', Number(val))"
                />
            </div>

            <div class="space-y-1.5">
                <Label class="text-xs text-muted-foreground">{{ t('Series End Date') }} *</Label>
                <Input
                    :model-value="recurrenceEnd"
                    type="date"
                    required
                    class="h-9"
                    @update:model-value="(val: string | number) => emit('update:recurrenceEnd', String(val))"
                />
            </div>
        </div>
    </div>
</template>
