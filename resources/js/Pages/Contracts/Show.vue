<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Badge } from '@/Components/ui/badge';
import { useTrans } from '@/lib/use-trans';
import type { Contract, Appointment } from '@/types';
import ContractOverview from './partials/ContractOverview.vue';
import ContractAppointments from './partials/ContractAppointments.vue';

const { t } = useTrans();

type ClientOption = { id: number; first_name: string; last_name: string; company_name: string | null; name: string };

defineProps<{
    contract: Contract;
    clients: ClientOption[];
    upcomingAppointments: Appointment[];
    pastAppointments: Appointment[];
    stats: {
        totalAppointments: number;
        upcomingAppointments: number;
        lastAppointment: string | null;
        nextAppointment: string | null;
    };
}>();
</script>

<template>
    <Head :title="contract.title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('contracts.index')" class="text-muted-foreground hover:text-foreground">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                </Link>
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-foreground">
                        {{ contract.title }}
                    </h2>
                    <p class="mt-0.5 font-mono text-sm text-muted-foreground">
                        {{ contract.contract_number }}
                    </p>
                </div>
            </div>
        </template>

        <Tabs default-value="overview">
            <TabsList class="mb-4">
                <TabsTrigger value="overview">{{ t('Overview') }}</TabsTrigger>
                <TabsTrigger value="appointments">
                    {{ t('Appointments') }}
                    <Badge v-if="stats.totalAppointments > 0" variant="secondary" class="ml-1.5">
                        {{ stats.totalAppointments }}
                    </Badge>
                </TabsTrigger>
            </TabsList>

            <TabsContent value="overview">
                <ContractOverview :contract="contract" :clients="clients" :stats="stats" />
            </TabsContent>

            <TabsContent value="appointments">
                <ContractAppointments :upcoming="upcomingAppointments" :past="pastAppointments" />
            </TabsContent>
        </Tabs>
    </AuthenticatedLayout>
</template>
