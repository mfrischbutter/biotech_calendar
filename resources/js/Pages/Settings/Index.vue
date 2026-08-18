<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useTrans } from '@/lib/use-trans';
import type { CalendarSettings, ChecklistTemplate, Company, SettingsSection, Status } from '@/types';
import SettingsSectionRail from './partials/SettingsSectionRail.vue';
import CompanyProfileForm from './partials/CompanyProfileForm.vue';
import CompanyBrandingForm from './partials/CompanyBrandingForm.vue';
import CalendarSettingsForm from './partials/CalendarSettingsForm.vue';
import StatusManager from './partials/StatusManager.vue';
import ChecklistTemplateManager from './partials/ChecklistTemplateManager.vue';

const { t } = useTrans();

defineProps<{
    company: Company;
    calendarSettings: CalendarSettings;
    statuses: Status[];
    checklistTemplates: ChecklistTemplate[];
}>();

const STORAGE_KEY = 'biotech-settings-section';
const KNOWN: SettingsSection[] = ['company', 'branding', 'calendar', 'statuses', 'checklists'];

const section = ref<SettingsSection>('company');

onMounted(() => {
    const saved = localStorage.getItem(STORAGE_KEY) as SettingsSection | null;
    if (saved && KNOWN.includes(saved)) section.value = saved;
});

watch(section, (value) => localStorage.setItem(STORAGE_KEY, value));
</script>

<template>
    <Head :title="t('Settings')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-foreground">{{ t('Settings') }}</h2>
        </template>

        <div class="grid gap-8 lg:grid-cols-[13rem_minmax(0,1fr)]">
            <SettingsSectionRail v-model="section" />

            <div class="min-w-0 max-w-4xl">
                <CompanyProfileForm v-if="section === 'company'" :company="company" />
                <CompanyBrandingForm v-else-if="section === 'branding'" :company="company" />
                <CalendarSettingsForm v-else-if="section === 'calendar'" :settings="calendarSettings" />
                <StatusManager v-else-if="section === 'statuses'" :statuses="statuses" />
                <ChecklistTemplateManager v-else :templates="checklistTemplates" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
