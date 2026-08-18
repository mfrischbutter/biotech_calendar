<script setup lang="ts">
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Button } from '@/Components/ui/button';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();

const props = defineProps<{
    status?: string;
}>();

const form = useForm({});

function submit() {
    form.post(route('verification.send'));
}

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <Head :title="t('Email Verification')" />

    <GuestLayout
        :title="t('Email Verification')"
        :description="t('Please verify your email address by clicking the link we just sent you.')"
    >
        <p
            v-if="verificationLinkSent"
            class="mb-5 rounded-md border border-success/30 bg-success-wash px-3 py-2 text-sm font-medium text-success-foreground"
        >
            {{ t('A new verification link has been sent to the email address you provided during registration.') }}
        </p>

        <form class="flex items-center justify-between gap-4" @submit.prevent="submit">
            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="text-sm font-medium text-navy hover:underline"
            >
                {{ t('Log Out') }}
            </Link>

            <Button type="submit" :disabled="form.processing">
                {{ t('Resend Verification Email') }}
            </Button>
        </form>
    </GuestLayout>
</template>
