<script setup lang="ts">
import { ref, watch } from 'vue';
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
import { usePermissionGroups, permissionGroupLabels } from '@/lib/use-permission-groups';

const { t } = useTrans();

const props = defineProps<{
    availablePermissions: Record<string, string>;
}>();

const open = ref(false);

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    permissions: [] as string[],
});

const { permissionGroups } = usePermissionGroups(() => props.availablePermissions);

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
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <Label for="first_name">{{ t('First name') }}</Label>
                        <Input
                            id="first_name"
                            v-model="form.first_name"
                            type="text"
                            :placeholder="t('First name')"
                            required
                        />
                        <p v-if="form.errors.first_name" class="text-sm text-destructive">
                            {{ form.errors.first_name }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="last_name">{{ t('Last name') }}</Label>
                        <Input
                            id="last_name"
                            v-model="form.last_name"
                            type="text"
                            :placeholder="t('Last name')"
                            required
                        />
                        <p v-if="form.errors.last_name" class="text-sm text-destructive">
                            {{ form.errors.last_name }}
                        </p>
                    </div>
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
                                {{ t(permissionGroupLabels[groupKey] ?? groupKey) }}
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
