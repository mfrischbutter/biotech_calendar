<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { useTrans } from '@/lib/use-trans';
import type { CalendarSettings } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    settings: CalendarSettings;
}>();

const form = useForm({
    show_weekends: props.settings.show_weekends,
    start_hour: props.settings.start_hour.toString(),
    end_hour: props.settings.end_hour.toString(),
});

const hourOptions = Array.from({ length: 25 }, (_, i) => ({
    value: i.toString(),
    label: `${String(i).padStart(2, '0')}:00`,
}));

function submit() {
    form.transform((data) => ({
        show_weekends: data.show_weekends,
        start_hour: parseInt(data.start_hour),
        end_hour: parseInt(data.end_hour),
    })).put(route('settings.calendar.update'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <div>
            <h3 class="text-base font-medium text-foreground">{{ t('Calendar Settings') }}</h3>
            <p class="text-sm text-muted-foreground mt-1">{{ t('Configure your calendar display preferences.') }}</p>
        </div>

        <div class="flex items-center justify-between">
            <div>
                <Label>{{ t('Show Weekends') }}</Label>
                <p class="text-sm text-muted-foreground">{{ t('Display Saturday and Sunday in the calendar.') }}</p>
            </div>
            <Switch v-model:checked="form.show_weekends" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
                <Label>{{ t('Start Hour') }}</Label>
                <Select v-model="form.start_hour">
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="opt in hourOptions.filter(o => parseInt(o.value) < parseInt(form.end_hour))"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="space-y-2">
                <Label>{{ t('End Hour') }}</Label>
                <Select v-model="form.end_hour">
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="opt in hourOptions.filter(o => parseInt(o.value) > parseInt(form.start_hour))"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <Button type="submit" :disabled="form.processing">
                {{ form.processing ? t('Saving...') : t('Save') }}
            </Button>
            <span v-if="form.recentlySuccessful" class="text-sm text-muted-foreground">{{ t('Saved.') }}</span>
        </div>
    </form>
</template>
