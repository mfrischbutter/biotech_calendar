<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import {
    Empty,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/Components/ui/empty';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Button } from '@/Components/ui/button';
import { Link } from '@inertiajs/vue3';
import { useTrans } from '@/lib/use-trans';
import { format } from 'date-fns';
import { de } from 'date-fns/locale';
import type { Appointment } from '@/types';

const { t } = useTrans();

defineProps<{
    upcoming: Appointment[];
    past: Appointment[];
}>();

function formatDateShort(dateStr: string): string {
    return format(new Date(dateStr), 'dd.MM.yyyy', { locale: de });
}

function formatTime(dateStr: string): string {
    return format(new Date(dateStr), 'HH:mm');
}
</script>

<template>
    <div class="space-y-6">
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    {{ t('Upcoming Appointments') }}
                    <Badge v-if="upcoming.length > 0" variant="secondary">{{ upcoming.length }}</Badge>
                </CardTitle>
            </CardHeader>
            <CardContent>
                <Empty v-if="upcoming.length === 0" class="py-8">
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" /></svg>
                        </EmptyMedia>
                        <EmptyTitle>{{ t('No upcoming appointments') }}</EmptyTitle>
                    </EmptyHeader>
                </Empty>
                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{{ t('Date') }}</TableHead>
                            <TableHead>{{ t('Time') }}</TableHead>
                            <TableHead>{{ t('Status') }}</TableHead>
                            <TableHead>{{ t('Workers') }}</TableHead>
                            <TableHead class="text-right">{{ t('Actions') }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="appt in upcoming" :key="appt.id">
                            <TableCell class="whitespace-nowrap text-sm">
                                {{ formatDateShort(appt.start_at) }}
                            </TableCell>
                            <TableCell class="whitespace-nowrap text-sm text-muted-foreground">
                                {{ formatTime(appt.start_at) }} – {{ formatTime(appt.end_at) }}
                            </TableCell>
                            <TableCell>
                                <Badge
                                    v-if="appt.status"
                                    variant="outline"
                                    class="border-transparent"
                                    :style="{ backgroundColor: appt.status.color + '20', color: appt.status.color }"
                                >
                                    {{ appt.status.name }}
                                </Badge>
                                <span v-else class="text-muted-foreground">–</span>
                            </TableCell>
                            <TableCell class="text-sm text-muted-foreground">
                                {{ appt.workers.map(w => w.name).join(', ') || '–' }}
                            </TableCell>
                            <TableCell class="text-right">
                                <Link :href="route('calendar.index', { appointment: appt.id })">
                                    <Button variant="ghost" size="sm">{{ t('Open in calendar') }}</Button>
                                </Link>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    {{ t('Past Appointments') }}
                    <Badge v-if="past.length > 0" variant="secondary">{{ past.length }}</Badge>
                </CardTitle>
            </CardHeader>
            <CardContent>
                <Empty v-if="past.length === 0" class="py-8">
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </EmptyMedia>
                        <EmptyTitle>{{ t('No past appointments') }}</EmptyTitle>
                    </EmptyHeader>
                </Empty>
                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{{ t('Date') }}</TableHead>
                            <TableHead>{{ t('Time') }}</TableHead>
                            <TableHead>{{ t('Status') }}</TableHead>
                            <TableHead>{{ t('Workers') }}</TableHead>
                            <TableHead class="text-right">{{ t('Actions') }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="appt in past" :key="appt.id">
                            <TableCell class="whitespace-nowrap text-sm">
                                {{ formatDateShort(appt.start_at) }}
                            </TableCell>
                            <TableCell class="whitespace-nowrap text-sm text-muted-foreground">
                                {{ formatTime(appt.start_at) }} – {{ formatTime(appt.end_at) }}
                            </TableCell>
                            <TableCell>
                                <Badge
                                    v-if="appt.status"
                                    variant="outline"
                                    class="border-transparent"
                                    :style="{ backgroundColor: appt.status.color + '20', color: appt.status.color }"
                                >
                                    {{ appt.status.name }}
                                </Badge>
                                <span v-else class="text-muted-foreground">–</span>
                            </TableCell>
                            <TableCell class="text-sm text-muted-foreground">
                                {{ appt.workers.map(w => w.name).join(', ') || '–' }}
                            </TableCell>
                            <TableCell class="text-right">
                                <Link :href="route('calendar.index', { appointment: appt.id })">
                                    <Button variant="ghost" size="sm">{{ t('Open in calendar') }}</Button>
                                </Link>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </div>
</template>
