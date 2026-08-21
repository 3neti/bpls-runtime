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
                        or review why an official report is not yet available.
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
                                <Badge
                                    v-if="report.availability === 'working'"
                                    variant="outline"
                                    class="shrink-0 border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-200"
                                >
                                    <CircleCheck aria-hidden="true" />
                                    Available
                                </Badge>
                                <Badge
                                    v-else
                                    variant="outline"
                                    class="shrink-0 border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100"
                                >
                                    <ShieldAlert aria-hidden="true" />
                                    Awaiting confirmation
                                </Badge>
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
                <strong>Report availability.</strong>
                Reports marked available use the records included in this
                preview. Reports marked awaiting municipal confirmation do not
                generate official rows or exports until their required format,
                classification, Treasury coverage, and authority are confirmed.
            </section>
        </main>
    </div>
</template>
