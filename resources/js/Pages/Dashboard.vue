<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();

const stats = [
    { title: t('Total Clients'), value: '0', icon: 'users' },
    { title: t("Today's Appointments"), value: '0', icon: 'calendar-today' },
    { title: t('This Week'), value: '0', icon: 'calendar-week' },
    { title: t('Completed'), value: '0', icon: 'check' },
];
</script>

<template>
    <Head :title="t('Dashboard')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-foreground">
                {{ t('Dashboard') }}
            </h2>
        </template>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card
                v-for="(stat, i) in stats"
                :key="stat.title"
                v-motion
                :initial="{ opacity: 0, y: 16 }"
                :visible-once="{ opacity: 1, y: 0, transition: { delay: i * 80, duration: 300 } }"
            >
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        {{ stat.title }}
                    </CardTitle>
                    <!-- Users icon -->
                    <svg v-if="stat.icon === 'users'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                    <!-- Calendar today -->
                    <svg v-if="stat.icon === 'calendar-today'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" />
                    </svg>
                    <!-- Calendar week -->
                    <svg v-if="stat.icon === 'calendar-week'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" /><line x1="11" y1="14" x2="13" y2="14" />
                    </svg>
                    <!-- Check icon -->
                    <svg v-if="stat.icon === 'check'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" />
                    </svg>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ stat.value }}</div>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
