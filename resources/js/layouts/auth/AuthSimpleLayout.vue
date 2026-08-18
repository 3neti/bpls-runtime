<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <main
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-muted/30 p-6 md:p-10"
    >
        <div class="w-full max-w-sm">
            <div
                class="flex flex-col gap-8 rounded-xl border bg-background p-6 shadow-sm sm:p-8"
            >
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="rounded-md text-center outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-4"
                        aria-label="Municipality of Ipil BPLS home"
                    >
                        <span class="block text-sm font-semibold"
                            >Municipality of Ipil</span
                        >
                        <span class="block text-xs text-muted-foreground"
                            >Business Permit and Licensing System</span
                        >
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-semibold">{{ title }}</h1>
                        <p class="text-sm leading-6 text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </main>
</template>
