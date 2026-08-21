<script setup lang="ts">
import { CircleAlert, Eye, LockKeyhole } from '@lucide/vue';

defineProps<{
    available: string;
    evidence: string;
    unavailable: string;
}>();
</script>

<template>
    <section
        class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background dark:border-sidebar-border"
        aria-labelledby="administration-scope-heading"
        data-testid="administration-scope"
    >
        <div class="border-b border-sidebar-border/70 px-4 py-3">
            <p
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                Administration scope
            </p>
            <h2
                id="administration-scope-heading"
                class="mt-1 font-semibold text-foreground"
            >
                What this workspace means
            </h2>
        </div>

        <div class="grid md:grid-cols-3">
            <article class="flex min-w-0 gap-3 p-4">
                <Eye
                    class="mt-0.5 size-5 shrink-0 text-emerald-700 dark:text-emerald-400"
                    aria-hidden="true"
                />
                <div class="min-w-0">
                    <h3 class="text-sm font-medium text-foreground">
                        Available now
                    </h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ available }}
                    </p>
                </div>
            </article>

            <article
                class="flex min-w-0 gap-3 border-t border-sidebar-border/70 p-4 md:border-t-0 md:border-l"
            >
                <CircleAlert
                    class="mt-0.5 size-5 shrink-0 text-amber-700 dark:text-amber-400"
                    aria-hidden="true"
                />
                <div class="min-w-0">
                    <h3 class="text-sm font-medium text-foreground">
                        What this means
                    </h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ evidence }}
                    </p>
                </div>
            </article>

            <article
                class="flex min-w-0 gap-3 border-t border-sidebar-border/70 p-4 md:border-t-0 md:border-l"
            >
                <LockKeyhole
                    class="mt-0.5 size-5 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
                <div class="min-w-0">
                    <h3 class="text-sm font-medium text-foreground">
                        Unavailable here
                    </h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ unavailable }}
                    </p>
                </div>
            </article>
        </div>
    </section>
</template>
