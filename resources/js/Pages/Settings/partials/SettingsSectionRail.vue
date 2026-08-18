<script setup lang="ts">
import type { Component } from 'vue';
import { Building2, CalendarDays, ImageIcon, ListChecks, Tags } from 'lucide-vue-next';
import { useTrans } from '@/lib/use-trans';
import type { SettingsSection } from '@/types';

const { t } = useTrans();

defineProps<{
    modelValue: SettingsSection;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: SettingsSection];
}>();

interface RailItem {
    key: SettingsSection;
    label: string;
    icon: Component;
}

const groups: { label: string; items: RailItem[] }[] = [
    {
        label: t('Company'),
        items: [
            { key: 'company', label: t('Company Profile'), icon: Building2 },
            { key: 'branding', label: t('Branding'), icon: ImageIcon },
        ],
    },
    {
        label: t('Operations'),
        items: [
            { key: 'calendar', label: t('Calendar'), icon: CalendarDays },
            { key: 'statuses', label: t('Statuses'), icon: Tags },
            { key: 'checklists', label: t('Checklists'), icon: ListChecks },
        ],
    },
];
</script>

<template>
    <nav data-testid="settings-rail" :aria-label="t('Settings')" class="space-y-6">
        <div v-for="group in groups" :key="group.label" class="space-y-1">
            <p class="px-2 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                {{ group.label }}
            </p>
            <button
                v-for="item in group.items"
                :key="item.key"
                type="button"
                :data-section="item.key"
                :aria-current="modelValue === item.key ? 'page' : undefined"
                class="flex w-full items-center gap-2.5 rounded-md px-2 py-1.5 text-left text-sm transition-colors"
                :class="modelValue === item.key
                    ? 'bg-navy-wash font-medium text-navy'
                    : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
                @click="emit('update:modelValue', item.key)"
            >
                <component :is="item.icon" class="h-4 w-4 shrink-0" />
                {{ item.label }}
            </button>
        </div>
    </nav>
</template>
