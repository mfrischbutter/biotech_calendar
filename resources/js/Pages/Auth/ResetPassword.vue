<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import FieldError from '@/Components/FieldError.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Head, useForm } from '@inertiajs/vue3';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();

const props = defineProps<{
    email: string;
    token: string;
}>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head :title="t('Reset Password')" />

    <GuestLayout :title="t('Reset Password')" :description="t('Choose a new password for your account.')">
        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <Label for="email">{{ t('Email') }}</Label>
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1.5"
                    required
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
                    autofocus
                    autocomplete="new-password"
                />
                <FieldError :message="form.errors.password" />
            </div>

            <div>
                <Label for="password_confirmation">{{ t('Confirm Password') }}</Label>
                <Input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="mt-1.5"
                    required
                    autocomplete="new-password"
                />
                <FieldError :message="form.errors.password_confirmation" />
            </div>

            <Button type="submit" class="w-full" :disabled="form.processing">
                {{ t('Reset Password') }}
            </Button>
        </form>
    </GuestLayout>
</template>
