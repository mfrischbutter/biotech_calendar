<script setup lang="ts">
import { Card } from '@/Components/ui/card';
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
import type { Contract } from '@/types';

const { t } = useTrans();

defineProps<{
    contracts: Contract[];
}>();

const KIND_LABELS: Record<string, string> = {
    kundentermin: 'T',
    ohne_termin: 'OT',
};

function formatAddress(c: { street: string | null; zip: string | null; city: string | null }): string {
    const parts: string[] = [];
    if (c.street) parts.push(c.street);
    if (c.zip || c.city) parts.push([c.zip, c.city].filter(Boolean).join(' '));
    return parts.join(', ');
}
</script>

<template>
    <Empty v-if="contracts.length === 0" class="py-12">
        <EmptyHeader>
            <EmptyMedia variant="icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 12h4"/><path d="M10 16h4"/></svg>
            </EmptyMedia>
            <EmptyTitle>{{ t('No contracts for this client') }}</EmptyTitle>
        </EmptyHeader>
    </Empty>

    <Card v-else>
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead class="w-[120px]">{{ t('Contract number') }}</TableHead>
                    <TableHead>{{ t('Title') }}</TableHead>
                    <TableHead>{{ t('Kind') }}</TableHead>
                    <TableHead>{{ t('Address') }}</TableHead>
                    <TableHead class="text-right">{{ t('Actions') }}</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="contract in contracts" :key="contract.id">
                    <TableCell class="font-mono text-sm">{{ contract.contract_number }}</TableCell>
                    <TableCell class="font-medium">{{ contract.title }}</TableCell>
                    <TableCell>
                        <Badge v-if="contract.kind" variant="outline">
                            {{ KIND_LABELS[contract.kind] ?? contract.kind }}
                        </Badge>
                        <span v-else class="text-muted-foreground">–</span>
                    </TableCell>
                    <TableCell>
                        <span v-if="formatAddress(contract)">{{ formatAddress(contract) }}</span>
                        <span v-else class="text-muted-foreground">–</span>
                    </TableCell>
                    <TableCell class="text-right">
                        <Link :href="route('contracts.show', contract.id)">
                            <Button variant="ghost" size="sm">{{ t('View') }}</Button>
                        </Link>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </Card>
</template>
