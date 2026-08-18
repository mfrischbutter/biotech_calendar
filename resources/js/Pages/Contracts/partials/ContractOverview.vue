<script setup lang="ts">
import type { ClientOption, Contract } from '@/types';
import ContractDetailsCard from './ContractDetailsCard.vue';
import ContractClientsCard from './ContractClientsCard.vue';
import ContractAddressCard from './ContractAddressCard.vue';
import ContractDescriptionCard from './ContractDescriptionCard.vue';

defineProps<{
    contract: Contract;
    clients: ClientOption[];
}>();
</script>

<template>
    <div class="space-y-4">
        <ContractDetailsCard :contract="contract" />
        <ContractClientsCard :contract="contract" :clients="clients" />
        <ContractAddressCard :contract="contract" />
        <ContractDescriptionCard :contract="contract" />
    </div>
</template>
