<script setup lang="ts">
type BoundaryFact = {
    label: string;
    value: string | number | boolean;
};

defineProps<{
    title: string;
    status: string;
    statement: string;
    facts?: BoundaryFact[];
    note?: string;
}>();

function displayValue(value: string | number | boolean): string | number {
    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    return value;
}

function displayStatus(status: string): string {
    if (status === 'blocked') {
        return 'Not available in this preview';
    }

    if (status === 'unresolved' || status === 'policy_unresolved') {
        return 'Not yet confirmed';
    }

    return status.replaceAll('_', ' ');
}
</script>

<template>
    <section
        class="rounded-xl border border-amber-300/80 bg-amber-50/80 p-4 text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
    >
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="min-w-0 space-y-1">
                <p
                    class="text-xs font-medium tracking-wide text-amber-800 uppercase dark:text-amber-200"
                >
                    Current availability
                </p>
                <h2 class="text-base font-semibold">{{ title }}</h2>
                <p class="max-w-3xl text-sm leading-6">{{ statement }}</p>
            </div>
            <span
                class="w-fit rounded-full border border-amber-400/80 bg-white/70 px-3 py-1 text-xs font-medium capitalize dark:border-amber-700 dark:bg-amber-950/50"
            >
                {{ displayStatus(status) }}
            </span>
        </div>

        <dl
            v-if="facts?.length"
            class="mt-4 grid gap-3 border-t border-amber-300/70 pt-4 sm:grid-cols-2 lg:grid-cols-4 dark:border-amber-800"
        >
            <div v-for="fact in facts" :key="fact.label">
                <dt class="text-xs text-amber-800 dark:text-amber-200">
                    {{ fact.label }}
                </dt>
                <dd class="mt-1 font-medium">
                    {{ displayValue(fact.value) }}
                </dd>
            </div>
        </dl>

        <div
            v-if="$slots.default"
            class="mt-4 border-t border-amber-300/70 pt-4 dark:border-amber-800"
        >
            <slot />
        </div>

        <p
            v-if="note"
            class="mt-4 border-t border-amber-300/70 pt-4 text-xs leading-5 dark:border-amber-800"
        >
            {{ note }}
        </p>
    </section>
</template>
