<script setup lang="ts">
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useTrans } from '@/lib/use-trans';
import type { Company, CalendarSettings, Status } from '@/types';
import CompanyProfileForm from './partials/CompanyProfileForm.vue';
import CalendarSettingsForm from './partials/CalendarSettingsForm.vue';
import StatusManager from './partials/StatusManager.vue';

const { t } = useTrans();

defineProps<{
    company: Company;
    calendarSettings: CalendarSettings;
    statuses: Status[];
}>();

type SettingsTab = 'company' | 'calendar' | 'statuses';

const activeTab = ref<SettingsTab>('company');

const tabs: { key: SettingsTab; label: string }[] = [
    { key: 'company', label: t('Company Profile') },
    { key: 'calendar', label: t('Calendar') },
    { key: 'statuses', label: t('Statuses') },
];
</script>

<template>
    <Head :title="t('Settings')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-foreground">{{ t('Settings') }}</h2>
        </template>

        <div class="max-w-3xl">
            <!-- Tab navigation -->
            <div class="flex border-b mb-6">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors"
                    :class="activeTab === tab.key
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'"
                    @click="activeTab = tab.key"
                >
                    {{ tab.label }}
                </button>
            </div>

            <!-- Tab content -->
            <CompanyProfileForm v-if="activeTab === 'company'" :company="company" />
            <CalendarSettingsForm v-else-if="activeTab === 'calendar'" :settings="calendarSettings" />
            <StatusManager v-else :statuses="statuses" />
        </div>
    </AuthenticatedLayout>
</template>
