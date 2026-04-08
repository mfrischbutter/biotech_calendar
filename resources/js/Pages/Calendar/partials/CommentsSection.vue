<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { useTrans } from '@/lib/use-trans';
import { formatDistanceToNow } from 'date-fns';
import { de } from 'date-fns/locale';
import type { Comment, PageProps } from '@/types';

const { t } = useTrans();

const props = defineProps<{
    appointmentId: number;
    comments: Comment[];
}>();

const page = usePage<PageProps>();

const form = useForm({
    body: '',
});

function submit() {
    form.post(route('comments.store', props.appointmentId), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function deleteComment(commentId: number) {
    form.delete(route('comments.destroy', commentId), {
        preserveScroll: true,
    });
}

function timeAgo(dateStr: string): string {
    return formatDistanceToNow(new Date(dateStr), { addSuffix: true, locale: de });
}

function userName(comment: Comment): string {
    if (!comment.user) return t('Unknown');
    return `${comment.user.first_name} ${comment.user.last_name}`;
}

function canDelete(comment: Comment): boolean {
    const user = page.props.auth.user;
    return comment.user_id === user.id || user.role === 'owner';
}
</script>

<template>
    <div class="mt-4 border-t pt-4">
        <h4 class="mb-3 text-sm font-medium text-foreground">{{ t('Comments') }}</h4>

        <!-- Comment list -->
        <div v-if="comments.length > 0" class="mb-3 space-y-3">
            <div
                v-for="comment in comments"
                :key="comment.id"
                class="group rounded-md bg-muted/50 px-3 py-2"
            >
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium">{{ userName(comment) }}</span>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-muted-foreground">{{ timeAgo(comment.created_at) }}</span>
                        <button
                            v-if="canDelete(comment)"
                            type="button"
                            class="hidden text-muted-foreground hover:text-destructive group-hover:inline-flex"
                            @click="deleteComment(comment.id)"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                    </div>
                </div>
                <p class="mt-1 text-sm text-foreground">{{ comment.body }}</p>
            </div>
        </div>

        <!-- New comment form -->
        <form @submit.prevent="submit" class="flex gap-2">
            <input
                v-model="form.body"
                type="text"
                :placeholder="t('Write a comment...')"
                class="min-w-0 flex-1 rounded-md border border-input bg-background px-3 py-1.5 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                maxlength="1000"
            />
            <Button type="submit" size="sm" :disabled="form.processing || !form.body.trim()">
                {{ t('Send') }}
            </Button>
        </form>
        <p v-if="form.errors.body" class="mt-1 text-xs text-destructive">{{ form.errors.body }}</p>
    </div>
</template>
