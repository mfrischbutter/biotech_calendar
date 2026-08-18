<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, MapPin } from 'lucide-vue-next';
import { badgeLabelKey, badgeToneClass } from '@/lib/record-badges';
import { useTrans } from '@/lib/use-trans';
import type { RecordBadge, RecordStat } from '@/types';

const { t } = useTrans();

defineProps<{
    /** Where the back chevron goes, and what it is called. */
    backHref: string;
    backLabel: string;
    title: string;
    subtitle?: string | null;
    /** Address line, rendered as a route link when `mapUrl` is given. */
    address?: string | null;
    mapUrl?: string | null;
    /** "Kunde seit März 2024". */
    tenure?: string | null;
    badges?: RecordBadge[];
    /** The two or three figures worth having in the header. */
    stats?: RecordStat[];
}>();
</script>

<template>
    <section class="rounded-xl border bg-card p-4 md:p-6" data-testid="record-header">
        <Link
            :href="backHref"
            class="mb-3 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <ChevronLeft class="h-4 w-4" />
            {{ backLabel }}
        </Link>

        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex min-w-0 items-start gap-4">
                <slot name="avatar" />

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="truncate text-xl font-semibold text-foreground md:text-2xl">{{ title }}</h1>
                        <span
                            v-for="badge in badges ?? []"
                            :key="badge.key"
                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="badgeToneClass(badge)"
                            data-testid="record-badge"
                        >
                            {{ t(badgeLabelKey(badge)) }}
                        </span>
                    </div>

                    <p v-if="subtitle" class="mt-0.5 truncate text-sm text-muted-foreground">{{ subtitle }}</p>

                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
                        <a
                            v-if="address && mapUrl"
                            :href="mapUrl"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-1 hover:text-navy hover:underline"
                            data-testid="record-address"
                        >
                            <MapPin class="h-3.5 w-3.5" />
                            {{ address }}
                        </a>
                        <span v-else-if="address" class="inline-flex items-center gap-1">
                            <MapPin class="h-3.5 w-3.5" />
                            {{ address }}
                        </span>
                        <span v-if="tenure" data-testid="record-tenure">{{ tenure }}</span>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                <slot name="actions" />
            </div>
        </div>

        <dl
            v-if="(stats ?? []).length > 0"
            class="mt-5 grid grid-cols-3 gap-4 border-t pt-4"
            data-testid="record-stats"
        >
            <div v-for="stat in stats" :key="stat.key">
                <dt class="text-xs uppercase tracking-wide text-muted-foreground">{{ stat.label }}</dt>
                <dd class="mt-0.5 text-lg font-semibold text-foreground">{{ stat.value }}</dd>
            </div>
        </dl>

        <slot name="footer" />
    </section>
</template>
