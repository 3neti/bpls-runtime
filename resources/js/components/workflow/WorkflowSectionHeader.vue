<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
    eyebrow?: string;
}>();
</script>

<template>
    <div
        class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
    >
        <div class="min-w-0 space-y-1">
            <p
                v-if="eyebrow"
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                {{ eyebrow }}
            </p>
            <h2 class="text-base font-semibold text-foreground">
                {{ title }}
            </h2>
            <p
                v-if="description"
                class="max-w-3xl text-sm leading-6 text-muted-foreground"
            >
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
