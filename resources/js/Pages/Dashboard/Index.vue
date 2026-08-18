<script setup lang="ts">
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { format, parseISO } from 'date-fns';
import { de } from 'date-fns/locale';
import { Plus } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { useTrans } from '@/lib/use-trans';
import AttentionCards from './partials/AttentionCards.vue';
import PipelineStrip from './partials/PipelineStrip.vue';
import TodaySchedule from './partials/TodaySchedule.vue';
import AttentionPanel from './partials/AttentionPanel.vue';
import WorkloadPanel from './partials/WorkloadPanel.vue';
import type {
    AttentionCounts,
    AttentionNotification,
    PipelineStage,
    ScheduleGroup,
    WorkloadRow,
} from '@/types';

const { t } = useTrans();

const props = defineProps<{
    today: string;
    greetingName: string;
    attention: AttentionCounts;
    schedule: ScheduleGroup[];
    pipeline: PipelineStage[];
    workload: WorkloadRow[];
    notifications: AttentionNotification[];
}>();

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 11) return t('Good morning, :name', { name: props.greetingName });
    if (hour < 18) return t('Good afternoon, :name', { name: props.greetingName });
    return t('Good evening, :name', { name: props.greetingName });
});

const appointmentsToday = computed(() =>
    props.schedule.reduce((total, group) => total + group.items.length, 0),
);

const staffOnDuty = computed(
    () => props.schedule.filter((group) => group.worker_id !== null).length,
);

const summary = computed(() =>
    [
        format(parseISO(props.today), 'EEEE, d. MMMM', { locale: de }),
        t(':count appointments', { count: String(appointmentsToday.value) }),
        t(':count employees on duty', { count: String(staffOnDuty.value) }),
    ].join(' · '),
);
</script>

<template>
    <Head :title="t('Dashboard')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-foreground">{{ greeting }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">{{ summary }}</p>
                </div>
                <Button as-child>
                    <Link :href="route('calendar.index', { new: 1 })">
                        <Plus class="h-4 w-4" aria-hidden="true" />
                        {{ t('New Appointment') }}
                    </Link>
                </Button>
            </div>
        </template>

        <div class="space-y-6">
            <AttentionCards :attention="attention" />

            <section>
                <h3 class="mb-2 text-sm font-semibold text-muted-foreground">
                    {{ t('Job pipeline') }}
                </h3>
                <PipelineStrip :stages="pipeline" />
            </section>

            <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
                <TodaySchedule :groups="schedule" />

                <div class="space-y-6">
                    <AttentionPanel :notifications="notifications" />
                    <WorkloadPanel :rows="workload" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
