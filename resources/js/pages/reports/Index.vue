<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { ArrowRight, ChartColumn, CircleCheck, ShieldAlert } from '@lucide/vue';
import { index } from '@/actions/App/Http/Controllers/Staff/ReportCatalogController';
import { Badge } from '@/components/ui/badge';
import { reportFamilyDetails, reportsForFamily } from '@/lib/reportCatalog';
import type { ReportFamily } from '@/lib/reportCatalog';
import type { BreadcrumbItem } from '@/types';

const families: ReportFamily[] = [
    'operational',
    'management',
    'authority_pending',
];

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Reports', href: index() }];

setLayoutProps({ breadcrumbs });
</script>

<template>
    <div class="contents">
        <Head title="Reports" />

        <main class="flex h-full min-w-0 flex-1 flex-col gap-6 p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div class="max-w-3xl">
                    <h1 class="text-xl font-semibold text-foreground">
                        Reports
                    </h1>
                    <p class="text-sm leading-6 text-muted-foreground">
                        Open the implemented operational and management reports,
                        or inspect an authority-pending contract to understand
                        exactly why official output is refused.
                    </p>
                </div>
                <Badge variant="outline">
                    <ChartColumn aria-hidden="true" />
                    Report catalog
                </Badge>
            </section>

            <section
                v-for="family in families"
                :key="family"
                class="grid gap-3"
                :data-report-family="family"
            >
                <div>
                    <h2 class="text-base font-semibold text-foreground">
                        {{ reportFamilyDetails[family].title }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{ reportFamilyDetails[family].description }}
                    </p>
                </div>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <Link
                        v-for="report in reportsForFamily(family)"
                        :key="report.key"
                        :href="report.href"
                        class="group flex min-h-40 flex-col justify-between gap-4 rounded-lg border border-sidebar-border/70 bg-background p-4 transition-colors outline-none hover:bg-accent/50 focus-visible:ring-2 focus-visible:ring-ring dark:border-sidebar-border"
                        :data-report-key="report.key"
                    >
                        <div class="grid gap-2">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="font-semibold text-foreground">
                                    {{ report.title }}
                                </h3>
                                <CircleCheck
                                    v-if="report.availability === 'working'"
                                    class="size-5 shrink-0 text-emerald-600 dark:text-emerald-400"
                                    aria-label="Working within current scope"
                                />
                                <ShieldAlert
                                    v-else
                                    class="size-5 shrink-0 text-amber-600 dark:text-amber-400"
                                    aria-label="Policy or authority pending"
                                />
                            </div>
                            <p class="text-sm leading-6 text-muted-foreground">
                                {{ report.description }}
                            </p>
                        </div>

                        <span
                            class="inline-flex items-center gap-2 text-sm font-medium text-foreground"
                        >
                            {{
                                report.key === 'billing-group-abstract'
                                    ? 'Choose billing group'
                                    : 'Open report'
                            }}
                            <ArrowRight
                                class="size-4 transition-transform group-hover:translate-x-0.5"
                                aria-hidden="true"
                            />
                        </span>
                    </Link>
                </div>
            </section>

            <section
                class="rounded-lg border border-amber-500/40 bg-amber-50 p-4 text-sm leading-6 text-amber-950 dark:bg-amber-950/30 dark:text-amber-100"
            >
                <strong>Catalog boundary.</strong>
                This page organizes routes already implemented in the current
                system. It is not a claim that every TOR report, official
                format, municipal classification, Treasury domain, or policy
                decision is complete.
            </section>
        </main>
    </div>
</template>
