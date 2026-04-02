<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/Components/ui/dialog';
import { useTrans } from '@/lib/use-trans';

const { t } = useTrans();

const props = defineProps<{
    availablePermissions: Record<string, string>;
}>();

const open = ref(false);

const form = useForm({
    name: '',
    email: '',
    password: '',
    permissions: [] as string[],
});

const groupLabels: Record<string, string> = {
    dashboard: 'Dashboard Access',
    clients: 'Clients',
    appointments: 'Appointments',
    treatments: 'Treatments & Protocols',
    invoices: 'Invoicing & Payments',
    reports: 'Reports',
};

const permissionGroups = computed(() => {
    const groups: Record<string, { key: string; label: string }[]> = {};
    for (const [key, label] of Object.entries(props.availablePermissions)) {
        const group = key.split('.')[0];
        if (!groups[group]) groups[group] = [];
        groups[group].push({ key, label });
    }
    return groups;
});

watch(open, (value) => {
    if (!value) {
        form.reset();
        form.clearErrors();
    }
});

function togglePermission(key: string) {
    if (form.permissions.includes(key)) {
        form.permissions = form.permissions.filter((p) => p !== key);
    } else {
        form.permissions = [...form.permissions, key];
    }
}

function submit() {
    form.post(route('employees.store'), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <slot />
        </DialogTrigger>
        <DialogContent class="sm:max-w-[480px]">
            <DialogHeader>
                <DialogTitle>{{ t('Add Employee') }}</DialogTitle>
                <DialogDescription>
                    {{ t('Create a new employee account. They will be able to log in with these credentials.') }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-2">
                    <Label for="name">{{ t('Name') }}</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        type="text"
                        :placeholder="t('Full name')"
                        required
                    />
                    <p v-if="form.errors.name" class="text-sm text-destructive">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="email">{{ t('Email') }}</Label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        placeholder="mitarbeiter@beispiel.de"
                        required
                    />
                    <p v-if="form.errors.email" class="text-sm text-destructive">
                        {{ form.errors.email }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="password">{{ t('Password') }}</Label>
                    <Input
                        id="password"
                        v-model="form.password"
                        type="password"
                        :placeholder="t('Password')"
                        required
                    />
                    <p v-if="form.errors.password" class="text-sm text-destructive">
                        {{ form.errors.password }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label>{{ t('Permissions (optional)') }}</Label>
                    <div class="max-h-[240px] overflow-y-auto rounded-md border p-3 space-y-3">
                        <div v-for="(perms, groupKey) in permissionGroups" :key="groupKey">
                            <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1.5">
                                {{ t(groupLabels[groupKey] ?? groupKey) }}
                            </p>
                            <div class="space-y-1.5">
                                <label
                                    v-for="perm in perms"
                                    :key="perm.key"
                                    class="flex items-center gap-2 text-sm cursor-pointer"
                                >
                                    <Checkbox
                                        :checked="form.permissions.includes(perm.key)"
                                        @update:checked="togglePermission(perm.key)"
                                    />
                                    {{ perm.label }}
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? t('Creating...') : t('Create Employee') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
