<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { ArrowRight, CheckCircle2, FileClock, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import { index } from '@/actions/App/Http/Controllers/Staff/ReportCatalogController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { reportCatalog, reportFamilyDetails } from '@/lib/reportCatalog';
import type { ReportAvailability, ReportFamily } from '@/lib/reportCatalog';
import type { BreadcrumbItem } from '@/types';

type AvailabilityFilter = 'all' | ReportAvailability;

const families: ReportFamily[] = [
    'operational',
    'management',
    'authority_pending',
];
const search = ref('');
const availability = ref<AvailabilityFilter>('all');

const filteredReports = computed(() => {
    const query = search.value.trim().toLocaleLowerCase();

    return reportCatalog.filter((report) => {
        const matchesAvailability =
            availability.value === 'all' ||
            report.availability === availability.value;
        const matchesSearch =
            query === '' ||
            `${report.title} ${report.description}`
                .toLocaleLowerCase()
                .includes(query);

        return matchesAvailability && matchesSearch;
    });
});

const counts = {
    total: reportCatalog.length,
    working: reportCatalog.filter((report) => report.availability === 'working')
        .length,
    policyBound: reportCatalog.filter(
        (report) => report.availability === 'policy_bound',
    ).length,
};

function reportsForFamily(family: ReportFamily) {
    return filteredReports.value.filter((report) => report.family === family);
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Report Templates', href: index() },
];

setLayoutProps({ breadcrumbs });
</script>

<template>
    <div class="contents">
        <Head title="Report Templates" />

        <main class="flex h-full min-w-0 flex-1 flex-col gap-5 p-4">
            <section class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <div class="text-xs font-medium text-muted-foreground">
                        Reports
                    </div>
                    <h1 class="text-xl font-semibold text-foreground">
                        Report Templates
                    </h1>
                </div>
                <dl class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
                    <div>
                        <dt class="text-xs text-muted-foreground">Total</dt>
                        <dd class="font-semibold">{{ counts.total }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Available</dt>
                        <dd
                            class="font-semibold text-emerald-700 dark:text-emerald-300"
                        >
                            {{ counts.working }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Awaiting confirmation
                        </dt>
                        <dd
                            class="font-semibold text-amber-700 dark:text-amber-300"
                        >
                            {{ counts.policyBound }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section
                class="flex flex-col gap-3 rounded-lg border border-sidebar-border/70 bg-background p-3 sm:flex-row sm:items-center dark:border-sidebar-border"
                aria-label="Report filters"
            >
                <label class="relative min-w-0 flex-1">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <span class="sr-only">Search reports</span>
                    <Input
                        v-model="search"
                        class="pl-9"
                        placeholder="Search reports"
                    />
                </label>
                <div
                    class="flex flex-wrap gap-2"
                    role="group"
                    aria-label="Availability"
                >
                    <Button
                        v-for="filter in [
                            { value: 'all', label: 'All' },
                            { value: 'working', label: 'Available' },
                            {
                                value: 'policy_bound',
                                label: 'Awaiting confirmation',
                            },
                        ]"
                        :key="filter.value"
                        size="sm"
                        :variant="
                            availability === filter.value
                                ? 'default'
                                : 'outline'
                        "
                        type="button"
                        @click="
                            availability = filter.value as AvailabilityFilter
                        "
                    >
                        {{ filter.label }}
                    </Button>
                </div>
            </section>

            <template v-for="family in families" :key="family">
                <section
                    v-if="reportsForFamily(family).length > 0"
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
                            class="group flex min-h-32 flex-col justify-between gap-3 rounded-lg border border-sidebar-border/70 bg-background p-4 transition-colors outline-none hover:bg-accent/50 focus-visible:ring-2 focus-visible:ring-ring dark:border-sidebar-border"
                            :data-report-key="report.key"
                        >
                            <div class="grid gap-2">
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <h3 class="font-semibold text-foreground">
                                        {{ report.title }}
                                    </h3>
                                    <Badge
                                        variant="outline"
                                        class="shrink-0"
                                        :class="
                                            report.availability === 'working'
                                                ? 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-200'
                                                : 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100'
                                        "
                                    >
                                        <CheckCircle2
                                            v-if="
                                                report.availability ===
                                                'working'
                                            "
                                            aria-hidden="true"
                                        />
                                        <FileClock v-else aria-hidden="true" />
                                        {{
                                            report.availability === 'working'
                                                ? 'Available'
                                                : 'Review'
                                        }}
                                    </Badge>
                                </div>
                                <p
                                    class="line-clamp-2 text-sm text-muted-foreground"
                                >
                                    {{ report.description }}
                                </p>
                            </div>
                            <span
                                class="inline-flex items-center gap-2 text-sm font-medium"
                            >
                                {{
                                    report.availability === 'working'
                                        ? 'Open report'
                                        : 'Review template'
                                }}
                                <ArrowRight
                                    class="size-4 transition-transform group-hover:translate-x-0.5"
                                    aria-hidden="true"
                                />
                            </span>
                        </Link>
                    </div>
                </section>
            </template>

            <div
                v-if="filteredReports.length === 0"
                class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                No reports match this search.
            </div>
        </main>
    </div>
</template>
