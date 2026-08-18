<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ImageIcon } from 'lucide-vue-next';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import FileDropzone from '@/Components/FileDropzone.vue';
import StickySaveBar from '@/Components/StickySaveBar.vue';
import { useTrans } from '@/lib/use-trans';
import type { Company } from '@/types';

const { t } = useTrans();

/** Mirrors SettingController::LOGO_FORMATS / LOGO_MAX_KB. */
const LOGO_FORMATS = ['png', 'jpg', 'jpeg', 'webp'];
const LOGO_MAX_KB = 2048;

const props = defineProps<{
    company: Company;
}>();

const form = useForm({
    logo: null as File | null,
});

const localError = ref('');
const preview = ref<string | null>(
    props.company.logo_path ? `/storage/${props.company.logo_path}` : null,
);

const dirty = computed(() => form.logo !== null);

function onSelect(file: File) {
    localError.value = '';
    form.logo = file;
    preview.value = URL.createObjectURL(file);
}

function onReject(reason: string) {
    localError.value = reason;
}

function discard() {
    form.logo = null;
    localError.value = '';
    preview.value = props.company.logo_path ? `/storage/${props.company.logo_path}` : null;
}

function submit() {
    if (!form.logo) return;
    form.post(route('settings.company.update'), {
        preserveScroll: true,
        forceFormData: true,
        headers: { 'X-HTTP-Method-Override': 'PUT' },
        onSuccess: () => { form.logo = null; },
    });
}
</script>

<template>
    <form id="company-branding-form" class="space-y-6" @submit.prevent="submit">
        <Card>
            <CardHeader>
                <CardTitle class="text-base">{{ t('Branding') }}</CardTitle>
                <CardDescription>{{ t('Your logo appears in the sidebar and on printed documents.') }}</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex items-start gap-4">
                    <div class="h-20 w-20 shrink-0 overflow-hidden rounded-lg border bg-muted">
                        <img v-if="preview" :src="preview" :alt="t('Logo')" class="h-full w-full object-cover" />
                        <div v-else class="flex h-full w-full items-center justify-center">
                            <ImageIcon class="h-6 w-6 text-muted-foreground" />
                        </div>
                    </div>

                    <div class="min-w-0 flex-1">
                        <FileDropzone
                            :formats="LOGO_FORMATS"
                            :max-size-kb="LOGO_MAX_KB"
                            :selected-name="form.logo?.name ?? null"
                            @select="onSelect"
                            @reject="onReject"
                        />
                    </div>
                </div>

                <p v-if="localError" data-testid="logo-error" class="text-sm text-destructive">{{ localError }}</p>
                <p v-if="form.errors.logo" class="text-sm text-destructive">{{ form.errors.logo }}</p>
            </CardContent>
        </Card>

        <StickySaveBar
            form="company-branding-form"
            :dirty="dirty"
            :processing="form.processing"
            :saved="form.recentlySuccessful"
            @discard="discard"
        />
    </form>
</template>
