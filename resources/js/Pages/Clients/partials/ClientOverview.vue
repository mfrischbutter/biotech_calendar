<script setup lang="ts">
import type { Client } from '@/types';
import ClientContactCard from './ClientContactCard.vue';
import ClientAddressCard from './ClientAddressCard.vue';
import ClientNotesCard from './ClientNotesCard.vue';

defineProps<{
    client: Client;
}>();
</script>

<template>
    <div class="space-y-4">
        <ClientContactCard :client="client" />
        <ClientAddressCard :client="client" />
        <ClientNotesCard :client="client" />
    </div>
</template>
