<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Separator } from '@/Components/ui/separator';
import { useTrans } from '@/lib/use-trans';
import { format } from 'date-fns';
import { de } from 'date-fns/locale';
import type { Client, ClientStats } from '@/types';

const { t } = useTrans();

defineProps<{
    client: Client;
    stats: ClientStats;
}>();

function formatDate(dateStr: string | null): string {
    if (!dateStr) return '–';
    return format(new Date(dateStr), 'dd. MMM yyyy', { locale: de });
}

const SALUTATION_LABELS: Record<string, string> = {
    Herr: 'Mr',
    Frau: 'Mrs',
};
</script>

<template>
    <div class="grid gap-4 md:grid-cols-3">
        <div class="space-y-4 md:col-span-2">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ t('Contact Information') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl class="grid gap-3 sm:grid-cols-2">
                        <div v-if="client.salutation">
                            <dt class="text-sm text-muted-foreground">{{ t('Salutation') }}</dt>
                            <dd class="text-sm font-medium">{{ t(SALUTATION_LABELS[client.salutation] ?? client.salutation) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-muted-foreground">{{ t('Name') }}</dt>
                            <dd class="text-sm font-medium">{{ client.first_name }} {{ client.last_name }}</dd>
                        </div>
                        <div v-if="client.company_name">
                            <dt class="text-sm text-muted-foreground">{{ t('Company name') }}</dt>
                            <dd class="text-sm font-medium">{{ client.company_name }}</dd>
                        </div>
                        <div v-if="client.billing_name">
                            <dt class="text-sm text-muted-foreground">{{ t('Billing Name') }}</dt>
                            <dd class="text-sm font-medium">{{ client.billing_name }}</dd>
                        </div>
                        <div v-if="client.email">
                            <dt class="text-sm text-muted-foreground">{{ t('Email') }}</dt>
                            <dd class="text-sm font-medium">
                                <a :href="'mailto:' + client.email" class="text-primary hover:underline">{{ client.email }}</a>
                            </dd>
                        </div>
                        <div v-if="client.phone">
                            <dt class="text-sm text-muted-foreground">{{ t('Phone') }}</dt>
                            <dd class="text-sm font-medium">
                                <a :href="'tel:' + client.phone" class="text-primary hover:underline">{{ client.phone }}</a>
                            </dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <Card v-if="client.street || client.city">
                <CardHeader>
                    <CardTitle class="text-base">{{ t('Address') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-sm">
                        <span v-if="client.street">{{ client.street }}<br></span>
                        <span v-if="client.zip || client.city">{{ [client.zip, client.city].filter(Boolean).join(' ') }}</span>
                    </p>
                </CardContent>
            </Card>

            <Card v-if="client.notes">
                <CardHeader>
                    <CardTitle class="text-base">{{ t('Notes') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="whitespace-pre-wrap text-sm text-muted-foreground">{{ client.notes }}</p>
                </CardContent>
            </Card>
        </div>

        <div class="space-y-4">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ t('Statistics') }}</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">{{ t('Contracts') }}</span>
                        <span class="text-sm font-medium">{{ stats.totalContracts }}</span>
                    </div>
                    <Separator />
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">{{ t('Total Appointments') }}</span>
                        <span class="text-sm font-medium">{{ stats.totalAppointments }}</span>
                    </div>
                    <Separator />
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">{{ t('Upcoming') }}</span>
                        <span class="text-sm font-medium">{{ stats.upcomingAppointments }}</span>
                    </div>
                    <Separator />
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">{{ t('Last Appointment') }}</span>
                        <span class="text-sm font-medium">{{ formatDate(stats.lastAppointment) }}</span>
                    </div>
                    <Separator />
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">{{ t('Next Appointment') }}</span>
                        <span class="text-sm font-medium">{{ formatDate(stats.nextAppointment) }}</span>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
