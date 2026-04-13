<script setup lang="ts">
import { Button } from '@/Components/ui/button';
import { useTrans } from '@/lib/use-trans';
import { router } from '@inertiajs/vue3';
import type { Paginated } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    pagination: Paginated<unknown>;
}>();

function goToPage(url: string | null) {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
}

const prevLink = () => props.pagination.links[0];
const nextLink = () => props.pagination.links[props.pagination.links.length - 1];
</script>

<template>
    <div v-if="pagination.last_page > 1" class="flex items-center justify-between px-4 py-3">
        <p class="text-sm text-muted-foreground">
            {{ t(':from–:to of :total', { from: String(pagination.from ?? 0), to: String(pagination.to ?? 0), total: String(pagination.total) }) }}
        </p>
        <div class="flex items-center gap-1">
            <Button
                variant="outline"
                size="sm"
                :disabled="!prevLink().url"
                @click="goToPage(prevLink().url)"
            >
                {{ t('Previous') }}
            </Button>
            <template v-for="link in pagination.links.slice(1, -1)" :key="link.label">
                <Button
                    :variant="link.active ? 'default' : 'outline'"
                    size="sm"
                    class="min-w-[2rem]"
                    :disabled="!link.url"
                    @click="goToPage(link.url)"
                >
                    {{ link.label }}
                </Button>
            </template>
            <Button
                variant="outline"
                size="sm"
                :disabled="!nextLink().url"
                @click="goToPage(nextLink().url)"
            >
                {{ t('Next') }}
            </Button>
        </div>
    </div>
</template>
