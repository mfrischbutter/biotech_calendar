<script setup lang="ts">
import { computed } from 'vue';
import { ClipboardList } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { applicableTemplates, templateToChecklist } from '@/lib/checklist-templates';
import { useTrans } from '@/lib/use-trans';
import type { AppointmentKind, ChecklistItem, ChecklistTemplate } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    templates: ChecklistTemplate[];
    /** Service type of the selected contract; narrows the offered templates. */
    kind: AppointmentKind | null;
}>();

const emit = defineEmits<{
    apply: [items: ChecklistItem[]];
}>();

const options = computed(() => applicableTemplates(props.templates, props.kind));
</script>

<template>
    <DropdownMenu v-if="options.length > 0">
        <DropdownMenuTrigger as-child>
            <Button
                type="button"
                variant="ghost"
                size="xs"
                data-testid="checklist-template-trigger"
                class="h-7 gap-1.5 text-xs font-medium text-navy hover:bg-navy-wash hover:text-navy"
            >
                <ClipboardList class="h-3.5 w-3.5" />
                {{ t('Apply template') }}
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-60">
            <DropdownMenuItem
                v-for="template in options"
                :key="template.id"
                :data-template-id="template.id"
                class="flex-col items-start gap-0"
                @select="emit('apply', templateToChecklist(template))"
            >
                <span class="text-sm">{{ template.name }}</span>
                <span class="text-xs text-muted-foreground">
                    {{ t(':count items', { count: String(template.items.length) }) }}
                </span>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
