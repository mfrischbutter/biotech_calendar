<script setup lang="ts">
import { computed } from 'vue';
import { CalendarClock, Users } from 'lucide-vue-next';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { hourMinute } from '@/lib/date-utils';
import { useTrans } from '@/lib/use-trans';
import type { NextAppointmentFact } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    appointment: NextAppointmentFact | null;
}>();

const when = computed(() => {
    if (!props.appointment) return null;

    const start = new Date(props.appointment.start_at);
    const end = new Date(props.appointment.end_at);
    if (Number.isNaN(start.getTime())) return null;

    const day = start.toLocaleDateString('de-DE', {
        weekday: 'short',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
    const from = hourMinute(props.appointment.start_at);
    const to = Number.isNaN(end.getTime()) ? null : hourMinute(props.appointment.end_at);

    return to ? `${day}, ${from}–${to}` : `${day}, ${from}`;
});

const workers = computed(() => (props.appointment?.workers ?? []).map((worker) => worker.name).join(', '));
</script>

<template>
    <Card data-testid="next-appointment-card">
        <CardHeader class="pb-3">
            <CardTitle class="flex items-center gap-2 text-base">
                <CalendarClock class="h-4 w-4 text-navy" />
                {{ t('Next Appointment') }}
            </CardTitle>
        </CardHeader>
        <CardContent>
            <template v-if="appointment">
                <a :href="appointment.url" class="text-sm font-medium text-foreground hover:text-navy hover:underline">
                    {{ appointment.title }}
                </a>
                <p class="mt-1 text-sm text-muted-foreground">{{ when }}</p>
                <p v-if="appointment.status" class="mt-2 flex items-center gap-1.5 text-sm text-muted-foreground">
                    <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: appointment.status.color }" />
                    {{ appointment.status.name }}
                </p>
                <p class="mt-2 flex items-center gap-1.5 text-sm text-muted-foreground">
                    <Users class="h-3.5 w-3.5" />
                    {{ workers || t('Unassigned') }}
                </p>
            </template>
            <p v-else class="text-sm text-muted-foreground">{{ t('No appointment scheduled') }}</p>
        </CardContent>
    </Card>
</template>
