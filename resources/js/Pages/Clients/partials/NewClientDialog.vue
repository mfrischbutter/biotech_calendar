<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();

const open = ref(false);

const form = useForm({
    first_name: '',
    last_name: '',
});

watch(open, (value) => {
    if (!value) {
        form.reset();
        form.clearErrors();
    }
});

function submit() {
    form.post(route('clients.store'), {
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <div @click="open = true">
        <slot />
    </div>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ t('New Client') }}</DialogTitle>
                <DialogDescription>
                    {{ t('Create a new client.') }}
                </DialogDescription>
            </DialogHeader>
            <form id="new-client-form" class="space-y-4" @submit.prevent="submit">
                <div class="space-y-1.5">
                    <Label for="new-client-first-name">{{ t('First name') }} *</Label>
                    <Input
                        id="new-client-first-name"
                        v-model="form.first_name"
                        type="text"
                        :placeholder="t('First name')"
                        required
                        autofocus
                    />
                    <p v-if="form.errors.first_name" class="text-sm text-destructive">
                        {{ form.errors.first_name }}
                    </p>
                </div>
                <div class="space-y-1.5">
                    <Label for="new-client-last-name">{{ t('Last name') }} *</Label>
                    <Input
                        id="new-client-last-name"
                        v-model="form.last_name"
                        type="text"
                        :placeholder="t('Last name')"
                        required
                    />
                    <p v-if="form.errors.last_name" class="text-sm text-destructive">
                        {{ form.errors.last_name }}
                    </p>
                </div>
            </form>
            <DialogFooter>
                <Button type="button" variant="ghost" :disabled="form.processing" @click="open = false">
                    {{ t('Cancel') }}
                </Button>
                <Button type="submit" form="new-client-form" :disabled="form.processing">
                    {{ form.processing ? t('Saving...') : t('Create') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
