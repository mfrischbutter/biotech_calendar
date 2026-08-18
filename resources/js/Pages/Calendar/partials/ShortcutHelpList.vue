<script setup lang="ts">
import { CALENDAR_SHORTCUTS } from '@/lib/use-calendar-shortcuts';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();
</script>

<template>
    <dl class="divide-y text-sm" data-testid="shortcut-list">
        <div
            v-for="shortcut in CALENDAR_SHORTCUTS"
            :key="shortcut.keys"
            class="flex items-center justify-between gap-4 py-2"
        >
            <dt class="text-muted-foreground">{{ t(shortcut.label) }}</dt>
            <dd>
                <kbd
                    class="inline-flex h-6 min-w-6 items-center justify-center rounded border border-navy-edge bg-navy-wash px-1.5 font-mono text-xs font-semibold text-navy"
                >{{ shortcut.keys }}</kbd>
            </dd>
        </div>
    </dl>
</template>
