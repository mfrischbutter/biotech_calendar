<script setup lang="ts">
import { ref } from 'vue';
import FieldError from '@/Components/FieldError.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/Components/ui/dialog';
import { useForm } from '@inertiajs/vue3';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();

const open = ref(false);

const form = useForm({
    password: '',
});

function deleteUser() {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeDialog(),
        onFinish: () => form.reset(),
    });
}

function closeDialog() {
    open.value = false;
    form.clearErrors();
    form.reset();
}
</script>

<template>
    <section>
        <header>
            <h2 class="text-base font-semibold text-foreground">
                {{ t('Delete Account') }}
            </h2>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ t('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
            </p>
        </header>

        <Dialog v-model:open="open">
            <DialogTrigger as-child>
                <Button variant="destructive" class="mt-6">{{ t('Delete Account') }}</Button>
            </DialogTrigger>

            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ t('Are you sure you want to delete your account?') }}</DialogTitle>
                    <DialogDescription>
                        {{ t('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                    </DialogDescription>
                </DialogHeader>

                <div>
                    <Label for="delete_password" class="sr-only">{{ t('Password') }}</Label>
                    <Input
                        id="delete_password"
                        v-model="form.password"
                        type="password"
                        :placeholder="t('Password')"
                        autocomplete="current-password"
                        @keyup.enter="deleteUser"
                    />
                    <FieldError :message="form.errors.password" />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="closeDialog">{{ t('Cancel') }}</Button>
                    <Button variant="destructive" :disabled="form.processing" @click="deleteUser">
                        {{ t('Delete Account') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </section>
</template>
