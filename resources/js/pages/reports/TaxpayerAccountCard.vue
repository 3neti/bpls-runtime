<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { FileClock, FileLock2 } from '@lucide/vue';
import { index } from '@/actions/App/Http/Controllers/Staff/TaxpayerAccountCardReportController';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

defineProps<{
    status: 'blocked';
    can_generate: false;
    can_export: false;
    row_count: number;
    report: { key: string; title: string; grain: string };
    sections: Array<{ title: string; fields: string[] }>;
    blocked_by: string[];
    policy_note: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Taxpayer Account Card', href: index() },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Taxpayer Account Card" />

        <main class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="text-xs font-medium text-muted-foreground">
                        Reports
                    </div>
                    <h1 class="text-xl font-semibold">Taxpayer Account Card</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ report.grain }}
                    </p>
                </div>
                <Badge
                    variant="outline"
                    class="border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100"
                    data-testid="taxpayer-account-card-status"
                >
                    <FileClock />
                    Awaiting confirmation
                </Badge>
            </section>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="section in sections"
                    :key="section.title"
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <h2 class="font-semibold">{{ section.title }}</h2>
                    <ul class="mt-3 grid gap-2 text-sm text-muted-foreground">
                        <li v-for="field in section.fields" :key="field">
                            {{ field }}
                        </li>
                    </ul>
                </article>
            </section>

            <section
                class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100"
                data-testid="taxpayer-account-card-boundary"
            >
                <div class="flex items-start gap-3">
                    <FileLock2 class="mt-0.5 size-5 shrink-0" />
                    <div>
                        <h2 class="font-semibold">Template only</h2>
                        <p class="mt-1">{{ policy_note }}</p>
                    </div>
                </div>
            </section>

            <details
                class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
            >
                <summary class="cursor-pointer text-sm font-semibold">
                    Rules awaiting confirmation
                </summary>
                <ul
                    class="mt-3 grid gap-2 text-sm text-muted-foreground sm:grid-cols-2"
                >
                    <li v-for="blocker in blocked_by" :key="blocker">
                        {{ blocker.replaceAll('_', ' ') }}
                    </li>
                </ul>
            </details>
        </main>
    </AppLayout>
</template>
