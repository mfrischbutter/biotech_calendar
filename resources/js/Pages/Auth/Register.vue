<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import FieldError from '@/Components/FieldError.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head :title="t('Register')" />

    <GuestLayout :title="t('Register')" :description="t('Create your account to get started.')">
        <form class="space-y-5" @submit.prevent="submit">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <Label for="first_name">{{ t('First name') }}</Label>
                    <Input
                        id="first_name"
                        v-model="form.first_name"
                        type="text"
                        class="mt-1.5"
                        required
                        autofocus
                        autocomplete="given-name"
                    />
                    <FieldError :message="form.errors.first_name" />
                </div>

                <div>
                    <Label for="last_name">{{ t('Last name') }}</Label>
                    <Input
                        id="last_name"
                        v-model="form.last_name"
                        type="text"
                        class="mt-1.5"
                        required
                        autocomplete="family-name"
                    />
                    <FieldError :message="form.errors.last_name" />
                </div>
            </div>

            <div>
                <Label for="email">{{ t('Email') }}</Label>
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1.5"
                    placeholder="name@example.com"
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

            <div class="flex items-center justify-between gap-4">
                <Link
                    :href="route('login')"
                    class="text-sm font-medium text-navy hover:underline"
                >
                    {{ t('Already registered?') }}
                </Link>

                <Button type="submit" :disabled="form.processing">
                    {{ t('Register') }}
                </Button>
            </div>
        </form>
    </GuestLayout>
</template>
