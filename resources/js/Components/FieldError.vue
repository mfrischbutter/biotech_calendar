<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <Transition
        enter-active-class="transition-opacity duration-150"
        leave-active-class="transition-opacity duration-150"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
    >
        <p v-if="message" class="mt-1.5 text-sm font-medium text-destructive">
            {{ message }}
        </p>
    </Transition>
</template>
