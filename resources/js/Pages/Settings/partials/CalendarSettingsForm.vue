<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import StickySaveBar from '@/Components/StickySaveBar.vue';
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
        onSuccess: () => form.defaults(),
    });
}
</script>

<template>
    <form id="calendar-settings-form" class="space-y-6" @submit.prevent="submit">
        <Card>
            <CardHeader>
                <CardTitle class="text-base">{{ t('Calendar Settings') }}</CardTitle>
                <CardDescription>{{ t('Configure your calendar display preferences.') }}</CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <Label>{{ t('Show Weekends') }}</Label>
                        <p class="text-sm text-muted-foreground">{{ t('Display Saturday and Sunday in the calendar.') }}</p>
                    </div>
                    <Switch :checked="form.show_weekends" @update:checked="(v: boolean) => form.show_weekends = v" />
                </div>

                <div class="grid gap-4 sm:grid-cols-[10rem_10rem]">
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
                <p v-if="form.errors.end_hour" class="text-sm text-destructive">{{ form.errors.end_hour }}</p>
            </CardContent>
        </Card>

        <StickySaveBar
            form="calendar-settings-form"
            :dirty="form.isDirty"
            :processing="form.processing"
            :saved="form.recentlySuccessful"
            @discard="form.reset()"
        />
    </form>
</template>
