<script setup lang="ts">
import { Card, CardContent, CardHeader } from '@/Components/ui/card';
import { Skeleton } from '@/Components/ui/skeleton';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();
</script>

<template>
    <div class="space-y-6" data-testid="dashboard-skeleton" role="status" :aria-label="t('Loading dashboard')">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <Card v-for="card in 5" :key="`attention-${card}`">
                <CardContent class="space-y-2 p-4">
                    <Skeleton class="h-4 w-24 bg-muted" />
                    <Skeleton class="h-8 w-12 bg-muted" />
                </CardContent>
            </Card>
        </div>

        <Skeleton class="h-14 w-full bg-muted" />

        <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
            <Card>
                <CardHeader><Skeleton class="h-5 w-40 bg-muted" /></CardHeader>
                <CardContent class="space-y-3">
                    <div v-for="item in 6" :key="`row-${item}`" class="flex items-center gap-3">
                        <Skeleton class="h-9 w-9 shrink-0 rounded-full bg-muted" />
                        <div class="flex-1 space-y-1.5">
                            <Skeleton class="h-4 w-2/3 bg-muted" />
                            <Skeleton class="h-3 w-1/3 bg-muted" />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="space-y-6">
                <Card v-for="panel in 2" :key="`panel-${panel}`">
                    <CardHeader><Skeleton class="h-5 w-32 bg-muted" /></CardHeader>
                    <CardContent class="space-y-3">
                        <Skeleton v-for="line in 4" :key="`line-${panel}-${line}`" class="h-4 w-full bg-muted" />
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
