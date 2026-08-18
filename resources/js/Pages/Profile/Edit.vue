<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Card, CardContent } from '@/Components/ui/card';
import { Head } from '@inertiajs/vue3';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();
</script>

<template>
    <Head :title="t('Profile')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-foreground">
                {{ t('Profile') }}
            </h2>
        </template>

        <div class="mx-auto max-w-2xl space-y-6">
            <Card>
                <CardContent class="pt-6">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                    />
                </CardContent>
            </Card>

            <Card>
                <CardContent class="pt-6">
                    <UpdatePasswordForm />
                </CardContent>
            </Card>

            <Card class="border-destructive/30">
                <CardContent class="pt-6">
                    <DeleteUserForm />
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
