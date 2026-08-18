<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import FieldError from '@/Components/FieldError.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Head, useForm } from '@inertiajs/vue3';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();

const form = useForm({
    password: '',
});

function submit() {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
}
</script>

<template>
    <Head :title="t('Confirm Password')" />

    <GuestLayout
        :title="t('Confirm Password')"
        :description="t('This is a secure area of the application. Please confirm your password before continuing.')"
    >
        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <Label for="password">{{ t('Password') }}</Label>
                <Input
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="mt-1.5"
                    required
                    autofocus
                    autocomplete="current-password"
                />
                <FieldError :message="form.errors.password" />
            </div>

            <Button type="submit" class="w-full" :disabled="form.processing">
                {{ t('Confirm') }}
            </Button>
        </form>
    </GuestLayout>
</template>
