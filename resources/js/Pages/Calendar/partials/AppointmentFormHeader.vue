<script setup lang="ts">
import { computed } from 'vue';
import { RotateCw } from 'lucide-vue-next';
import { useTrans } from '@/lib/use-trans';
import type { Contract } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    /** The contract this appointment hangs off — the who and the what. */
    contract: Contract | null;
    isSeries: boolean;
}>();

const clientNames = computed(() =>
    (props.contract?.clients ?? []).map(c => c.name).join(', '),
);
</script>

<template>
    <div v-if="contract || isSeries" class="space-y-1.5 text-left">
        <p v-if="contract" data-testid="contract-context" class="text-sm leading-snug text-foreground">
            <span class="mr-1.5 font-mono text-xs text-muted-foreground">{{ contract.contract_number }}</span>
            <span class="font-medium">{{ contract.title }}</span>
        </p>
        <p v-if="clientNames" data-testid="client-context" class="text-xs text-muted-foreground">
            {{ clientNames }}
        </p>
        <span
            v-if="isSeries"
            data-testid="series-badge"
            class="inline-flex items-center gap-1 rounded-full bg-navy-wash px-2 py-0.5 text-[11px] font-medium text-navy"
        >
            <RotateCw class="h-3 w-3" />
            {{ t('Part of a series') }}
        </span>
    </div>
</template>
