<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Badge } from '@/Components/ui/badge';
import { useTrans } from '@/lib/use-trans';
import type { Client, Contract, Appointment, ActivityLog, ClientStats, ClientComment } from '@/types';
import ClientFormDrawer from './partials/ClientFormDrawer.vue';
import ClientOverview from './partials/ClientOverview.vue';
import ClientContracts from './partials/ClientContracts.vue';
import ClientAppointments from './partials/ClientAppointments.vue';
import ClientActivity from './partials/ClientActivity.vue';

const { t } = useTrans();

defineProps<{
    client: Client;
    contracts: Contract[];
    upcomingAppointments: Appointment[];
    pastAppointments: Appointment[];
    recentActivity: ActivityLog[];
    recentComments: ClientComment[];
    stats: ClientStats;
}>();
</script>

<template>
    <Head :title="client.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link :href="route('clients.index')" class="text-muted-foreground hover:text-foreground">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    </Link>
                    <div>
                        <h2 class="text-xl font-semibold leading-tight text-foreground">
                            {{ client.name }}
                        </h2>
                        <p v-if="client.company_name" class="mt-0.5 text-sm text-muted-foreground">
                            {{ client.company_name }}
                        </p>
                    </div>
                </div>
                <ClientFormDrawer :client="client">
                    <Button variant="outline">{{ t('Edit') }}</Button>
                </ClientFormDrawer>
            </div>
        </template>

        <Tabs default-value="overview">
            <TabsList class="mb-4">
                <TabsTrigger value="overview">{{ t('Overview') }}</TabsTrigger>
                <TabsTrigger value="contracts">
                    {{ t('Contracts') }}
                    <Badge v-if="stats.totalContracts > 0" variant="secondary" class="ml-1.5">
                        {{ stats.totalContracts }}
                    </Badge>
                </TabsTrigger>
                <TabsTrigger value="appointments">
                    {{ t('Appointments') }}
                    <Badge v-if="stats.totalAppointments > 0" variant="secondary" class="ml-1.5">
                        {{ stats.totalAppointments }}
                    </Badge>
                </TabsTrigger>
                <TabsTrigger value="activity">{{ t('Activity') }}</TabsTrigger>
            </TabsList>

            <TabsContent value="overview">
                <ClientOverview :client="client" :stats="stats" />
            </TabsContent>

            <TabsContent value="contracts">
                <ClientContracts :contracts="contracts" />
            </TabsContent>

            <TabsContent value="appointments">
                <ClientAppointments
                    :upcoming="upcomingAppointments"
                    :past="pastAppointments"
                />
            </TabsContent>

            <TabsContent value="activity">
                <ClientActivity :activities="recentActivity" :comments="recentComments" />
            </TabsContent>
        </Tabs>
    </AuthenticatedLayout>
</template>
