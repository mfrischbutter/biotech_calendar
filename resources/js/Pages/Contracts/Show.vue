<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Badge } from '@/Components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { AccessNotesCard, NextAppointmentCard, RecordTimeline, SeriesCard } from '@/Components/record';
import { useTrans } from '@/lib/use-trans';
import type { Appointment, ClientOption, Contract, ContractFacts, TimelineEvent } from '@/types';
import ContractIdentityHeader from './partials/ContractIdentityHeader.vue';
import ContractOverview from './partials/ContractOverview.vue';
import ContractAppointments from './partials/ContractAppointments.vue';
import ContractTeamCard from './partials/ContractTeamCard.vue';

const { t } = useTrans();

defineProps<{
    contract: Contract;
    clients: ClientOption[];
    upcomingAppointments: Appointment[];
    pastAppointments: Appointment[];
    timeline: TimelineEvent[];
    facts: ContractFacts;
}>();
</script>

<template>
    <Head :title="contract.title" />

    <AuthenticatedLayout>
        <div class="space-y-4">
            <ContractIdentityHeader :contract="contract" :facts="facts" />

            <div class="grid gap-4 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <Tabs default-value="timeline">
                        <TabsList class="mb-4">
                            <TabsTrigger value="timeline">{{ t('History') }}</TabsTrigger>
                            <TabsTrigger value="overview">{{ t('Overview') }}</TabsTrigger>
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
                                :empty-description="t('Appointments, comments and documents for this contract appear here.')"
                            />
                        </TabsContent>

                        <TabsContent value="overview">
                            <ContractOverview :contract="contract" :clients="clients" />
                        </TabsContent>

                        <TabsContent value="appointments">
                            <ContractAppointments :upcoming="upcomingAppointments" :past="pastAppointments" />
                        </TabsContent>
                    </Tabs>
                </div>

                <div class="space-y-4">
                    <NextAppointmentCard :appointment="facts.next_appointment" />
                    <SeriesCard :series="facts.series" />
                    <AccessNotesCard :notes="facts.access_notes" />
                    <ContractTeamCard :team="facts.team" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
