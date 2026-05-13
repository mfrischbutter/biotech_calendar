<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Separator } from '@/Components/ui/separator';
import { useTrans } from '@/lib/use-trans';
import { format } from 'date-fns';
import { de } from 'date-fns/locale';
import type { Contract } from '@/types';
import ContractDetailsCard from './ContractDetailsCard.vue';
import ContractClientsCard from './ContractClientsCard.vue';
import ContractAddressCard from './ContractAddressCard.vue';
import ContractDescriptionCard from './ContractDescriptionCard.vue';

const { t } = useTrans();

type ClientOption = { id: number; first_name: string; last_name: string; company_name: string | null; name: string };

defineProps<{
    contract: Contract;
    clients: ClientOption[];
    stats: {
        totalAppointments: number;
        upcomingAppointments: number;
        lastAppointment: string | null;
        nextAppointment: string | null;
    };
}>();

function formatDate(dateStr: string | null): string {
    if (!dateStr) return '–';
    return format(new Date(dateStr), 'dd. MMM yyyy', { locale: de });
}
</script>

<template>
    <div class="grid gap-4 md:grid-cols-3">
        <div class="space-y-4 md:col-span-2">
            <ContractDetailsCard :contract="contract" />
            <ContractClientsCard :contract="contract" :clients="clients" />
            <ContractAddressCard :contract="contract" />
            <ContractDescriptionCard :contract="contract" />
        </div>

        <div class="space-y-4">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ t('Statistics') }}</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
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
