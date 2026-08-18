<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import FieldError from '@/Components/FieldError.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head :title="t('Log in')" />

    <GuestLayout :title="t('Log in')" :description="t('Welcome back.')">
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

            <div>
                <Label for="password">{{ t('Password') }}</Label>
                <Input
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="mt-1.5"
                    required
                    autocomplete="current-password"
                />
                <FieldError :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <label class="flex cursor-pointer items-center gap-2 text-sm text-foreground">
                    <Checkbox
                        id="remember"
                        :model-value="form.remember"
                        @update:model-value="(v) => (form.remember = v === true)"
                    />
                    {{ t('Remember me') }}
                </label>

                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm font-medium text-navy hover:underline"
                >
                    {{ t('Forgot your password?') }}
                </Link>
            </div>

            <Button type="submit" class="w-full" :disabled="form.processing">
                {{ t('Log in') }}
            </Button>
        </form>
    </GuestLayout>
</template>
