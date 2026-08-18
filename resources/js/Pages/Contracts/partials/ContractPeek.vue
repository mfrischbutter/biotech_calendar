<script setup lang="ts">
import { computed } from 'vue';
import { CalendarPlus } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import SidePeek from '@/Components/SidePeek.vue';
import { STAGE_LABELS, STAGE_PILL } from '@/lib/pipeline-stages';
import { useTrans } from '@/lib/use-trans';
import type { ContractListRow } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    contract: ContractListRow | null;
}>();

defineEmits<{
    close: [];
    next: [];
    prev: [];
    openRecord: [];
}>();

const address = computed(() => {
    const contract = props.contract;
    if (!contract) return null;

    const parts = [contract.street, [contract.zip, contract.city].filter(Boolean).join(' ')].filter(Boolean);

    return parts.length > 0 ? parts.join(', ') : null;
});

const facts = computed(() => {
    const contract = props.contract;
    if (!contract) return [];

    return [
        { key: 'clients', label: t('Clients'), value: contract.clients.map((client) => client.name).join(', ') },
        { key: 'address', label: t('Address'), value: address.value },
        { key: 'team', label: t('Team'), value: contract.team.map((worker) => worker.name).join(', ') },
        {
            key: 'progress',
            label: t('Progress'),
            value: t(':done of :total appointments', {
                done: String(contract.progress.done),
                total: String(contract.progress.total),
            }),
        },
    ];
});
</script>

<template>
    <SidePeek
        :open="contract !== null"
        :title="contract?.title ?? ''"
        :subtitle="contract?.contract_number ?? null"
        @close="$emit('close')"
        @next="$emit('next')"
        @prev="$emit('prev')"
        @open-record="$emit('openRecord')"
    >
        <template #actions>
            <Button size="sm" as="a" :href="route('calendar.index')">
                <CalendarPlus class="mr-2 h-4 w-4" />
                {{ t('New Appointment') }}
            </Button>
        </template>

        <div v-if="contract" class="space-y-3">
            <span
                v-if="contract.stage"
                class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                :class="STAGE_PILL[contract.stage]"
            >{{ t(STAGE_LABELS[contract.stage]) }}</span>

            <div class="space-y-1">
                <div class="flex items-center justify-between text-xs text-muted-foreground">
                    <span>{{ t('Progress') }}</span>
                    <span class="tabular-nums">{{ contract.progress.percent }}%</span>
                </div>
                <span class="block h-2 overflow-hidden rounded-full bg-muted">
                    <span
                        class="block h-full rounded-full bg-primary transition-all"
                        :style="{ width: `${contract.progress.percent}%` }"
                    />
                </span>
            </div>

            <p v-if="contract.description" class="text-sm text-muted-foreground">
                {{ contract.description }}
            </p>
        </div>

        <dl v-if="contract" class="space-y-3 text-sm">
            <div v-for="fact in facts" :key="fact.key" class="grid grid-cols-3 gap-2">
                <dt class="text-muted-foreground">{{ fact.label }}</dt>
                <dd class="col-span-2 text-foreground">{{ fact.value || '–' }}</dd>
            </div>
        </dl>
    </SidePeek>
</template>
