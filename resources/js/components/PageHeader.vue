<script setup lang="ts">
defineProps<{
    eyebrow?: string;
    title: string;
    description: string;
}>();
</script>

<template>
    <header class="space-y-2">
        <p v-if="eyebrow" class="text-sm font-medium text-muted-foreground">
            {{ eyebrow }}
        </p>
        <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
            {{ title }}
        </h1>
        <p
            class="max-w-3xl text-sm leading-6 text-muted-foreground sm:text-base"
        >
            {{ description }}
        </p>
    </header>
</template>
