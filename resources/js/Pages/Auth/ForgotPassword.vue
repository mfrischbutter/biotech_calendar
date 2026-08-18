<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import FieldError from '@/Components/FieldError.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
});

function submit() {
    form.post(route('password.email'));
}
</script>

<template>
    <Head :title="t('Forgot Password')" />

    <GuestLayout
        :title="t('Forgot Password')"
        :description="t('Enter your email address and we will send you a link to choose a new password.')"
    >
        <p
            v-if="status"
            class="mb-5 rounded-md border border-success/30 bg-success-wash px-3 py-2 text-sm font-medium text-success-foreground"
        >
            {{ status }}
        </p>

        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <Label for="email">{{ t('Email') }}</Label>
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1.5"
                    placeholder="name@example.com"
                    required
                    autofocus
                    autocomplete="username"
                />
                <FieldError :message="form.errors.email" />
            </div>

            <div class="flex items-center justify-between gap-4">
                <Link :href="route('login')" class="text-sm font-medium text-navy hover:underline">
                    {{ t('Back to log in') }}
                </Link>

                <Button type="submit" :disabled="form.processing">
                    {{ t('Email Password Reset Link') }}
                </Button>
            </div>
        </form>
    </GuestLayout>
</template>
