<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { AlertTriangle, UserX, Clock, Receipt, Gauge } from 'lucide-vue-next';
import { useTrans } from '@/lib/use-trans';
import type { AttentionCounts } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    attention: AttentionCounts;
}>();

/**
 * Exceptions, not totals: each card is a number somebody has to do something
 * about, and links straight to the list already narrowed to those items.
 *
 * Every href below has to name a parameter the target screen actually reads —
 * `filter=unassigned` and `stage=ready_to_invoice` named nothing, so all five
 * cards quietly opened the same unfiltered list.
 */
const cards = computed(() => [
    {
        key: 'conflicts',
        label: t('Scheduling conflicts'),
        value: props.attention.conflicts,
        cta: t('Resolve'),
        href: route('calendar.index', { conflicts: 1 }),
        icon: AlertTriangle,
        tone: 'danger',
        hideWhenZero: true,
    },
    {
        key: 'unassigned',
        label: t('Without an employee'),
        value: props.attention.unassigned,
        cta: t('Assign'),
        href: route('calendar.index', { unassigned: 1 }),
        icon: UserX,
        tone: 'danger',
    },
    {
        key: 'overdue',
        label: t('Overdue follow-up'),
        value: props.attention.overdue,
        cta: t('Review'),
        href: route('contracts.index', { view: 'overdue' }),
        icon: Clock,
        tone: 'warning',
    },
    {
        key: 'ready',
        label: t('Ready to invoice'),
        value: props.attention.readyToInvoice,
        cta: t('Invoice'),
        href: route('contracts.index', { view: 'ready_to_invoice' }),
        icon: Receipt,
        tone: 'success',
    },
    {
        // The team board prints booked hours per person; the employee screen is
        // permissions, which is not what this number is about.
        key: 'utilisation',
        label: t('Utilisation this week'),
        value: props.attention.utilisation,
        suffix: '%',
        href: route('calendar.index', { view: 'team-week' }),
        icon: Gauge,
        tone: 'neutral',
    },
].filter((card) => !(card.hideWhenZero && card.value === 0)));

const tones: Record<string, { wrap: string; text: string }> = {
    danger: { wrap: 'border-danger/40 bg-danger-wash', text: 'text-danger' },
    warning: { wrap: 'border-warning/40 bg-warning-wash', text: 'text-warning' },
    success: { wrap: 'border-success/40 bg-success-wash', text: 'text-success-foreground' },
    neutral: { wrap: 'border-border bg-card', text: 'text-foreground' },
};

/** A zero is good news: drop the alarm styling so red always means "act". */
function toneFor(card: { tone: string; value: number }) {
    return card.value === 0 ? tones.neutral : tones[card.tone] ?? tones.neutral;
}
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
        <Link
            v-for="card in cards"
            :key="card.key"
            :href="card.href"
            :data-testid="`attention-${card.key}`"
            class="flex flex-col gap-1 rounded-lg border p-4 transition-shadow hover:shadow-sm"
            :class="toneFor(card).wrap"
        >
            <div class="flex items-center gap-2">
                <component
                    :is="card.icon"
                    class="h-4 w-4"
                    :class="toneFor(card).text"
                    aria-hidden="true"
                />
                <span class="text-sm" :class="toneFor(card).text">{{ card.label }}</span>
            </div>
            <span class="tabular text-3xl font-semibold" :class="toneFor(card).text">
                {{ card.value }}<span v-if="card.suffix" class="text-lg">{{ card.suffix }}</span>
            </span>
            <span v-if="card.cta && card.value > 0" class="text-xs font-medium" :class="toneFor(card).text">
                {{ card.cta }} &rarr;
            </span>
        </Link>
    </div>
</template>
