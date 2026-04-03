<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
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
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                {{ t('Profile Information') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                {{ t("Update your account's profile information and email address.") }}
            </p>
        </header>

        <form
            @submit.prevent="form.patch(route('profile.update'))"
            class="mt-6 space-y-6"
        >
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <InputLabel for="first_name" :value="t('First name')" />

                    <TextInput
                        id="first_name"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.first_name"
                        required
                        autofocus
                        autocomplete="given-name"
                    />

                    <InputError class="mt-2" :message="form.errors.first_name" />
                </div>

                <div>
                    <InputLabel for="last_name" :value="t('Last name')" />

                    <TextInput
                        id="last_name"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.last_name"
                        required
                        autocomplete="family-name"
                    />

                    <InputError class="mt-2" :message="form.errors.last_name" />
                </div>
            </div>

            <div>
                <InputLabel for="email" :value="t('Email')" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel :value="t('Language')" />

                <div class="mt-1 flex items-center gap-2">
                    <button
                        type="button"
                        @click="form.locale = 'de'"
                        class="flex items-center gap-2 rounded-md border px-3 py-2 text-sm transition-colors"
                        :class="form.locale === 'de'
                            ? 'border-primary bg-primary/5 text-foreground'
                            : 'border-gray-200 text-gray-400 hover:border-gray-300 hover:text-gray-600'"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="16" viewBox="0 0 5 3" class="rounded-sm">
                            <rect width="5" height="1" fill="#000" /><rect y="1" width="5" height="1" fill="#D00" /><rect y="2" width="5" height="1" fill="#FFCE00" />
                        </svg>
                        Deutsch
                    </button>
                    <button
                        type="button"
                        @click="form.locale = 'en'"
                        class="flex items-center gap-2 rounded-md border px-3 py-2 text-sm transition-colors"
                        :class="form.locale === 'en'
                            ? 'border-primary bg-primary/5 text-foreground'
                            : 'border-gray-200 text-gray-400 hover:border-gray-300 hover:text-gray-600'"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="16" viewBox="0 0 60 30" class="rounded-sm">
                            <clipPath id="s"><path d="M0 0v30h60V0z"/></clipPath>
                            <g clip-path="url(#s)"><path d="M0 0v30h60V0z" fill="#012169"/><path d="M0 0l60 30m0-30L0 30" stroke="#fff" stroke-width="6"/><path d="M0 0l60 30m0-30L0 30" stroke="#C8102E" stroke-width="4" clip-path="url(#s)"/><path d="M30 0v30M0 15h60" stroke="#fff" stroke-width="10"/><path d="M30 0v30M0 15h60" stroke="#C8102E" stroke-width="6"/></g>
                        </svg>
                        English
                    </button>
                </div>

                <InputError class="mt-2" :message="form.errors.locale" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-gray-800">
                    {{ t('Your email address is unverified.') }}
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        {{ t('Click here to re-send the verification email.') }}
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    {{ t('A new verification link has been sent to your email address.') }}
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">{{ t('Save') }}</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-gray-600"
                    >
                        {{ t('Saved.') }}
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
