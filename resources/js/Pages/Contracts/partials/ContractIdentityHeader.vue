<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { CalendarDays, CalendarPlus, FileText, MoreHorizontal, Navigation, Trash2 } from 'lucide-vue-next';
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
import { STAGE_BADGE_TONE } from '@/lib/record-badges';
import { useTrans } from '@/lib/use-trans';
import type { Contract, ContractFacts, RecordBadge, RecordStat } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    contract: Contract;
    facts: ContractFacts;
}>();

const confirmDelete = ref(false);

const planUrl = computed(() => route('calendar.index', { new: 1, contract: props.contract.id }));

/** The stage leads the badge row: it is the one fact everyone asks about. */
const badges = computed<RecordBadge[]>(() => {
    const stage = props.facts.stage;

    return stage
        ? [{ key: stage, tone: STAGE_BADGE_TONE[stage] }, ...props.facts.badges]
        : props.facts.badges;
});

const tenure = computed(() => {
    const since = monthAndYear(props.facts.since);

    return since ? t('Created :date', { date: since }) : null;
});

const clientNames = computed(() => props.contract.clients.map((client) => client.company_name || client.name).join(', '));

/** Contract number first — it is how the office refers to the job. */
const subtitle = computed(() => [props.contract.contract_number, clientNames.value].filter(Boolean).join(' · '));

const stats = computed<RecordStat[]>(() => [
    { key: 'appointments', label: t('Appointments'), value: String(props.facts.stats.appointments) },
    {
        key: 'progress',
        label: t('Progress'),
        value: `${props.facts.progress.done}/${props.facts.progress.total}`,
    },
    { key: 'next', label: t('Next'), value: shortDate(props.facts.stats.next) ?? '–' },
]);

function createReport() {
    if (typeof window.print === 'function') window.print();
}

function destroy() {
    confirmDelete.value = false;
    router.delete(route('contracts.destroy', props.contract.id), { data: { redirect: 'index' } });
}
</script>

<template>
    <RecordHeader
        :back-href="route('contracts.index')"
        :back-label="t('Contracts')"
        :title="contract.title"
        :subtitle="subtitle"
        :address="facts.address"
        :map-url="facts.map_url"
        :tenure="tenure"
        :badges="badges"
        :stats="stats"
    >
        <template #avatar>
            <Avatar size="lg" shape="square" class="bg-navy-wash">
                <AvatarFallback class="bg-navy-wash text-navy">
                    <FileText class="h-6 w-6" />
                </AvatarFallback>
            </Avatar>
        </template>

        <template #actions>
            <Button as="a" :href="planUrl" data-testid="action-plan">
                <CalendarPlus class="mr-2 h-4 w-4" />
                {{ t('Schedule appointment') }}
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
                    <DropdownMenuSeparator />
                    <DropdownMenuItem class="text-destructive" data-testid="action-delete" @select="confirmDelete = true">
                        <Trash2 class="mr-2 h-4 w-4" />
                        {{ t('Delete') }}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <ConfirmDeleteDialog
                :open="confirmDelete"
                :title="t('Delete :name?', { name: contract.contract_number })"
                :description="t('This contract and its link to appointments will be permanently deleted.')"
                @update:open="confirmDelete = $event"
                @confirm="destroy"
            />
        </template>

        <template #footer>
            <div v-if="facts.progress.total > 0" class="mt-4" data-testid="contract-progress">
                <div class="mb-1 flex items-center justify-between text-xs text-muted-foreground">
                    <span>{{ t('Progress') }}</span>
                    <span>{{ facts.progress.percent }}%</span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                    <div class="h-full rounded-full bg-primary" :style="{ width: `${facts.progress.percent}%` }" />
                </div>
            </div>
        </template>
    </RecordHeader>
</template>
