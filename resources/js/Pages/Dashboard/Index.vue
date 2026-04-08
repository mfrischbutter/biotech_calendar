<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useTrans } from '@/lib/use-trans';
import type { Appointment, ActivityLog } from '@/types';
import StatsCards from './partials/StatsCards.vue';
import UpcomingAppointments from './partials/UpcomingAppointments.vue';
import ActivityFeed from './partials/ActivityFeed.vue';

const { t } = useTrans();

defineProps<{
    totalClients: number;
    todayAppointments: number;
    thisWeekAppointments: number;
    thisMonthAppointments: number;
    upcomingAppointments: Appointment[];
    recentActivity: ActivityLog[];
    recentComments: ActivityLog[];
    unreadCommentsCount: number;
}>();
</script>

<template>
    <Head :title="t('Dashboard')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-foreground">
                {{ t('Dashboard') }}
            </h2>
        </template>

        <div class="space-y-6">
            <StatsCards
                :total-clients="totalClients"
                :today-appointments="todayAppointments"
                :this-week-appointments="thisWeekAppointments"
                :this-month-appointments="thisMonthAppointments"
            />

            <div class="grid gap-6 lg:grid-cols-2">
                <UpcomingAppointments :appointments="upcomingAppointments" />
                <ActivityFeed
                    :activities="recentActivity"
                    :comments="recentComments"
                    :unread-comments-count="unreadCommentsCount"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
