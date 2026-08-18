<script setup lang="ts">
import FieldError from '@/Components/FieldError.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function updatePassword() {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
            }
            if (form.errors.current_password) {
                form.reset('current_password');
            }
        },
    });
}
</script>

<template>
    <section>
        <header>
            <h2 class="text-base font-semibold text-foreground">
                {{ t('Update Password') }}
            </h2>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ t('Ensure your account is using a long, random password to stay secure.') }}
            </p>
        </header>

        <form class="mt-6 space-y-5" @submit.prevent="updatePassword">
            <div>
                <Label for="current_password">{{ t('Current Password') }}</Label>
                <Input
                    id="current_password"
                    v-model="form.current_password"
                    type="password"
                    class="mt-1.5"
                    autocomplete="current-password"
                />
                <FieldError :message="form.errors.current_password" />
            </div>

            <div>
                <Label for="password">{{ t('New Password') }}</Label>
                <Input
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="mt-1.5"
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
                    autocomplete="new-password"
                />
                <FieldError :message="form.errors.password_confirmation" />
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
