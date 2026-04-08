<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();

const props = defineProps<{
    totalClients: number;
    todayAppointments: number;
    thisWeekAppointments: number;
    thisMonthAppointments: number;
}>();

const stats = [
    { key: 'clients', title: t('Total Clients'), icon: 'clients' },
    { key: 'today', title: t("Today's Appointments"), icon: 'today' },
    { key: 'week', title: t('This Week'), icon: 'week' },
    { key: 'month', title: t('This Month'), icon: 'month' },
] as const;

function getValue(key: string): number {
    switch (key) {
        case 'clients': return props.totalClients;
        case 'today': return props.todayAppointments;
        case 'week': return props.thisWeekAppointments;
        case 'month': return props.thisMonthAppointments;
        default: return 0;
    }
}
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Card v-for="stat in stats" :key="stat.key">
            <CardHeader class="flex flex-row items-center justify-between pb-2">
                <CardTitle class="text-sm font-medium text-muted-foreground">
                    {{ stat.title }}
                </CardTitle>
                <!-- Clients icon -->
                <svg v-if="stat.icon === 'clients'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/>
                </svg>
                <!-- Today icon -->
                <svg v-else-if="stat.icon === 'today'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" />
                </svg>
                <!-- Week icon -->
                <svg v-else-if="stat.icon === 'week'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" /><line x1="11" y1="14" x2="13" y2="14" />
                </svg>
                <!-- Month icon -->
                <svg v-else-if="stat.icon === 'month'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" /><line x1="8" y1="14" x2="8" y2="14" /><line x1="12" y1="14" x2="12" y2="14" /><line x1="16" y1="14" x2="16" y2="14" /><line x1="8" y1="18" x2="8" y2="18" /><line x1="12" y1="18" x2="12" y2="18" />
                </svg>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ getValue(stat.key) }}</div>
            </CardContent>
        </Card>
    </div>
</template>
