<script setup lang="ts">
type SummaryItem = {
    label: string;
    value: string | number | boolean;
    detail?: string | null;
};

withDefaults(
    defineProps<{
        eyebrow?: string;
        title: string;
        description?: string;
        items: SummaryItem[];
    }>(),
    {
        eyebrow: 'Current record',
        description: undefined,
    },
);
</script>

<template>
    <section
        class="rounded-xl border border-sidebar-border/70 bg-background shadow-xs dark:border-sidebar-border"
        :aria-label="title"
    >
        <div
            class="flex flex-col gap-4 border-b border-sidebar-border/70 p-4 sm:flex-row sm:items-start sm:justify-between dark:border-sidebar-border"
        >
            <div class="min-w-0 space-y-1">
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    {{ eyebrow }}
                </p>
                <h2 class="text-lg font-semibold text-foreground">
                    {{ title }}
                </h2>
                <p
                    v-if="description"
                    class="max-w-3xl text-sm leading-6 text-muted-foreground"
                >
                    {{ description }}
                </p>
            </div>
            <div v-if="$slots.actions" class="flex flex-wrap gap-2">
                <slot name="actions" />
            </div>
        </div>

        <dl
            class="grid gap-px bg-sidebar-border/70 sm:grid-cols-2 lg:grid-cols-4 dark:bg-sidebar-border"
        >
            <div
                v-for="item in items"
                :key="item.label"
                class="min-w-0 bg-background p-4"
            >
                <dt class="text-xs text-muted-foreground">{{ item.label }}</dt>
                <dd class="mt-1 font-medium break-words text-foreground">
                    {{ item.value }}
                </dd>
                <dd
                    v-if="item.detail"
                    class="mt-1 text-xs leading-5 text-muted-foreground"
                >
                    {{ item.detail }}
                </dd>
            </div>
        </dl>
    </section>
</template>
