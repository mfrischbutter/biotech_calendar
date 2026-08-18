<script setup lang="ts">
import { computed } from 'vue';
import { CalendarPlus, Mail, Phone } from 'lucide-vue-next';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Button } from '@/Components/ui/button';
import SidePeek from '@/Components/SidePeek.vue';
import { shortDate } from '@/lib/date-utils';
import { useTrans } from '@/lib/use-trans';
import { initials } from '@/lib/utils';
import type { ClientListRow } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    client: ClientListRow | null;
}>();

defineEmits<{
    close: [];
    next: [];
    prev: [];
    openRecord: [];
}>();

const address = computed(() => {
    const client = props.client;
    if (!client) return null;

    const parts = [client.street, [client.zip, client.city].filter(Boolean).join(' ')].filter(Boolean);

    return parts.length > 0 ? parts.join(', ') : null;
});

const facts = computed(() => {
    const client = props.client;
    if (!client) return [];

    return [
        { key: 'address', label: t('Address'), value: address.value },
        { key: 'billing', label: t('Billing Name'), value: client.billing_name },
        { key: 'open', label: t('Open contracts'), value: String(client.open_contracts) },
        { key: 'next', label: t('Next Appointment'), value: shortDate(client.next_appointment) },
    ];
});
</script>

<template>
    <SidePeek
        :open="client !== null"
        :title="client?.name ?? ''"
        :subtitle="client?.company_name ?? null"
        @close="$emit('close')"
        @next="$emit('next')"
        @prev="$emit('prev')"
        @open-record="$emit('openRecord')"
    >
        <template #actions>
            <!-- The customer on screen is the one the appointment is for. -->
            <Button
                v-if="client"
                size="sm"
                as="a"
                :href="route('calendar.index', { new: 1, client: client.id })"
                data-testid="peek-new-appointment"
            >
                <CalendarPlus class="mr-2 h-4 w-4" />
                {{ t('New Appointment') }}
            </Button>
            <Button v-if="client?.phone" size="sm" variant="outline" as="a" :href="`tel:${client.phone}`">
                <Phone class="mr-2 h-4 w-4" />
                {{ t('Call') }}
            </Button>
            <Button v-if="client?.email" size="sm" variant="outline" as="a" :href="`mailto:${client.email}`">
                <Mail class="mr-2 h-4 w-4" />
                {{ t('Email') }}
            </Button>
        </template>

        <div v-if="client" class="flex items-center gap-3">
            <Avatar size="base" class="bg-navy-wash">
                <AvatarFallback class="bg-navy-wash text-sm font-semibold text-navy">
                    {{ initials(client.name) }}
                </AvatarFallback>
            </Avatar>
            <div class="min-w-0">
                <p class="truncate font-medium text-foreground">{{ client.name }}</p>
                <p class="truncate text-sm text-muted-foreground">
                    {{ client.email || client.phone || t('No contact details') }}
                </p>
            </div>
        </div>

        <dl v-if="client" class="space-y-3 text-sm">
            <div v-for="fact in facts" :key="fact.key" class="grid grid-cols-3 gap-2">
                <dt class="text-muted-foreground">{{ fact.label }}</dt>
                <dd class="col-span-2 text-foreground">{{ fact.value || '–' }}</dd>
            </div>
        </dl>
    </SidePeek>
</template>
