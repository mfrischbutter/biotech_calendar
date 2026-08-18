<script setup lang="ts">
import FieldError from '@/Components/FieldError.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();

const user = usePage().props.auth.user;

const form = useForm({
    first_name: user.first_name,
    last_name: user.last_name,
    email: user.email,
    locale: user.locale ?? 'de',
});

const locales: { value: 'de' | 'en'; label: string }[] = [
    { value: 'de', label: 'Deutsch' },
    { value: 'en', label: 'English' },
];
</script>

<template>
    <section>
        <header>
            <h2 class="text-base font-semibold text-foreground">
                {{ t('Profile Information') }}
            </h2>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ t("Update your account's profile information and email address.") }}
            </p>
        </header>

        <form class="mt-6 space-y-5" @submit.prevent="form.patch(route('profile.update'))">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <Label for="first_name">{{ t('First name') }}</Label>
                    <Input
                        id="first_name"
                        v-model="form.first_name"
                        type="text"
                        class="mt-1.5"
                        required
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
                    required
                    autocomplete="username"
                />
                <FieldError :message="form.errors.email" />
            </div>

            <div>
                <Label>{{ t('Language') }}</Label>
                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                    <button
                        v-for="locale in locales"
                        :key="locale.value"
                        type="button"
                        class="rounded-md border px-3 py-2 text-sm transition-colors"
                        :class="form.locale === locale.value
                            ? 'border-navy bg-navy-wash font-medium text-navy'
                            : 'border-input text-muted-foreground hover:bg-accent hover:text-foreground'"
                        @click="form.locale = locale.value"
                    >
                        {{ locale.label }}
                    </button>
                </div>
                <FieldError :message="form.errors.locale" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null" class="text-sm">
                <p class="text-foreground">
                    {{ t('Your email address is unverified.') }}
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="font-medium text-navy hover:underline"
                    >
                        {{ t('Click here to re-send the verification email.') }}
                    </Link>
                </p>
                <p v-show="status === 'verification-link-sent'" class="mt-2 font-medium text-success-foreground">
                    {{ t('A new verification link has been sent to your email address.') }}
                </p>
            </div>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="form.processing">{{ t('Save') }}</Button>

                <Transition
                    enter-active-class="transition-opacity"
                    enter-from-class="opacity-0"
                    leave-active-class="transition-opacity"
                    leave-to-class="opacity-0"
                >
                    <p v-if="form.recentlySuccessful" class="text-sm text-muted-foreground">
                        {{ t('Saved.') }}
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
