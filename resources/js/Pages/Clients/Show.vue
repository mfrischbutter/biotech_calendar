<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Badge } from '@/Components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { AccessNotesCard, NextAppointmentCard, RecordTimeline, SeriesCard } from '@/Components/record';
import { useTrans } from '@/lib/use-trans';
import type { Appointment, Client, ClientFacts, Contract, TimelineEvent } from '@/types';
import ClientIdentityHeader from './partials/ClientIdentityHeader.vue';
import ClientOverview from './partials/ClientOverview.vue';
import ClientContracts from './partials/ClientContracts.vue';
import ClientAppointments from './partials/ClientAppointments.vue';

const { t } = useTrans();

defineProps<{
    client: Client;
    contracts: Contract[];
    upcomingAppointments: Appointment[];
    pastAppointments: Appointment[];
    timeline: TimelineEvent[];
    facts: ClientFacts;
}>();
</script>

<template>
    <Head :title="client.name" />

    <AuthenticatedLayout>
        <div class="space-y-4">
            <ClientIdentityHeader :client="client" :facts="facts" />

            <div class="grid gap-4 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <Tabs default-value="timeline">
                        <TabsList class="mb-4">
                            <TabsTrigger value="timeline">{{ t('History') }}</TabsTrigger>
                            <TabsTrigger value="overview">{{ t('Overview') }}</TabsTrigger>
                            <TabsTrigger value="contracts">
                                {{ t('Contracts') }}
                                <Badge v-if="facts.stats.contracts > 0" variant="secondary" class="ml-1.5">
                                    {{ facts.stats.contracts }}
                                </Badge>
                            </TabsTrigger>
                            <TabsTrigger value="appointments">
                                {{ t('Appointments') }}
                                <Badge v-if="facts.stats.appointments > 0" variant="secondary" class="ml-1.5">
                                    {{ facts.stats.appointments }}
                                </Badge>
                            </TabsTrigger>
                        </TabsList>

                        <TabsContent value="timeline">
                            <RecordTimeline
                                :events="timeline"
                                :empty-title="t('Nothing has happened yet')"
                                :empty-description="t('Appointments, comments and documents for this client appear here.')"
                            />
                        </TabsContent>

                        <TabsContent value="overview">
                            <ClientOverview :client="client" />
                        </TabsContent>

                        <TabsContent value="contracts">
                            <ClientContracts :contracts="contracts" />
                        </TabsContent>

                        <TabsContent value="appointments">
                            <ClientAppointments :upcoming="upcomingAppointments" :past="pastAppointments" />
                        </TabsContent>
                    </Tabs>
                </div>

                <div class="space-y-4">
                    <NextAppointmentCard :appointment="facts.next_appointment" />
                    <SeriesCard :series="facts.series" />
                    <AccessNotesCard :notes="facts.access_notes" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
