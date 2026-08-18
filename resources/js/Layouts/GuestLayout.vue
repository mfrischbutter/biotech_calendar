<script setup lang="ts">
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();

const appName = (import.meta.env.VITE_APP_NAME as string | undefined) ?? 'Wilhelmsen BioTec';

defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <div class="min-h-screen bg-background md:grid md:grid-cols-2">
        <!-- Brand panel -->
        <aside
            class="flex flex-col justify-between bg-navy px-6 py-8 text-navy-foreground md:px-12 md:py-14"
        >
            <p class="font-semibold tracking-tight md:text-lg">{{ appName }}</p>

            <div class="hidden md:block">
                <h1 class="max-w-sm text-3xl font-semibold leading-tight tracking-tight">
                    {{ t('Appointments, clients and contracts in one place.') }}
                </h1>
                <p class="mt-4 max-w-sm text-sm leading-relaxed text-navy-foreground/70">
                    {{ t('Plan the week, assign your team, and keep every job documented.') }}
                </p>
            </div>

            <p class="hidden text-xs text-navy-foreground/60 md:block">
                &copy; {{ new Date().getFullYear() }} {{ appName }}
            </p>
        </aside>

        <!-- Form panel -->
        <main class="flex items-center justify-center px-6 py-12 md:px-12">
            <div class="w-full max-w-sm">
                <header class="mb-6">
                    <h2 class="text-2xl font-semibold tracking-tight text-foreground">
                        {{ title }}
                    </h2>
                    <p v-if="description" class="mt-1.5 text-sm text-muted-foreground">
                        {{ description }}
                    </p>
                </header>

                <slot />
            </div>
        </main>
    </div>
</template>
