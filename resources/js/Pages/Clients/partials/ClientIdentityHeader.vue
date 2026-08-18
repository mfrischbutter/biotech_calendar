<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { CalendarDays, CalendarPlus, FileText, Mail, MoreHorizontal, Navigation, Phone, Trash2 } from 'lucide-vue-next';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import ConfirmDeleteDialog from '@/Components/ConfirmDeleteDialog.vue';
import { RecordHeader } from '@/Components/record';
import { monthAndYear, shortDate } from '@/lib/date-utils';
import { useTrans } from '@/lib/use-trans';
import { initials } from '@/lib/utils';
import type { Client, ClientFacts, RecordStat } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    client: Client;
    facts: ClientFacts;
}>();

const confirmDelete = ref(false);

/** The calendar, with a fresh appointment form open for this customer. */
const planUrl = computed(() => route('calendar.index', { new: 1, client: props.client.id }));

const tenure = computed(() => {
    const since = monthAndYear(props.facts.since);

    return since ? t('Customer since :date', { date: since }) : null;
});

const stats = computed<RecordStat[]>(() => [
    { key: 'contracts', label: t('Contracts'), value: String(props.facts.stats.contracts) },
    { key: 'appointments', label: t('Appointments'), value: String(props.facts.stats.appointments) },
    { key: 'next', label: t('Next'), value: shortDate(props.facts.stats.next) ?? '–' },
]);

/** The dossier as it stands, on paper — no server round trip needed. */
function createReport() {
    if (typeof window.print === 'function') window.print();
}

function destroy() {
    confirmDelete.value = false;
    router.delete(route('clients.destroy', props.client.id), { data: { redirect: 'index' } });
}
</script>

<template>
    <RecordHeader
        :back-href="route('clients.index')"
        :back-label="t('Clients')"
        :title="client.name"
        :subtitle="client.company_name"
        :address="facts.address"
        :map-url="facts.map_url"
        :tenure="tenure"
        :badges="facts.badges"
        :stats="stats"
    >
        <template #avatar>
            <Avatar size="lg" class="bg-navy-wash">
                <AvatarFallback class="bg-navy-wash font-semibold text-navy">
                    {{ initials(client.name) }}
                </AvatarFallback>
            </Avatar>
        </template>

        <template #actions>
            <Button as="a" :href="planUrl" data-testid="action-plan">
                <CalendarPlus class="mr-2 h-4 w-4" />
                {{ t('Schedule appointment') }}
            </Button>

            <Button
                v-if="client.phone"
                variant="outline"
                size="icon"
                as="a"
                :href="`tel:${client.phone}`"
                :aria-label="t('Call')"
                :title="t('Call')"
                data-testid="action-call"
            >
                <Phone class="h-4 w-4" />
            </Button>

            <Button
                v-if="client.email"
                variant="outline"
                size="icon"
                as="a"
                :href="`mailto:${client.email}`"
                :aria-label="t('Email')"
                :title="t('Email')"
                data-testid="action-email"
            >
                <Mail class="h-4 w-4" />
            </Button>

            <Button
                v-if="facts.map_url"
                variant="outline"
                size="icon"
                as="a"
                :href="facts.map_url"
                target="_blank"
                rel="noopener"
                :aria-label="t('Route')"
                :title="t('Route')"
                data-testid="action-route"
            >
                <Navigation class="h-4 w-4" />
            </Button>

            <Button variant="outline" data-testid="action-report" @click="createReport">
                <FileText class="mr-2 h-4 w-4" />
                {{ t('Create report') }}
            </Button>

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button variant="ghost" size="icon" :aria-label="t('More')" data-testid="action-more">
                        <MoreHorizontal class="h-4 w-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56">
                    <DropdownMenuItem as="a" :href="route('calendar.index')">
                        <CalendarDays class="mr-2 h-4 w-4" />
                        {{ t('Show in calendar') }}
                    </DropdownMenuItem>
                    <DropdownMenuItem as="a" :href="route('contracts.index')">
                        <FileText class="mr-2 h-4 w-4" />
                        {{ t('Contracts') }}
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem class="text-destructive" data-testid="action-delete" @select="confirmDelete = true">
                        <Trash2 class="mr-2 h-4 w-4" />
                        {{ t('Delete') }}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <ConfirmDeleteDialog
                :open="confirmDelete"
                :title="t('Delete :name?', { name: client.name })"
                :description="t('This client will be permanently deleted. Linked appointments will keep their data but lose the client reference.')"
                @update:open="confirmDelete = $event"
                @confirm="destroy"
            />
        </template>
    </RecordHeader>
</template>
