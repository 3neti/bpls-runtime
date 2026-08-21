<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    FileLock2,
    Landmark,
    ReceiptText,
    ShieldAlert,
    TableProperties,
} from '@lucide/vue';
import { index } from '@/actions/App/Http/Controllers/Staff/AllAbstractReportController';
import ReportFamilyBanner from '@/components/reports/ReportFamilyBanner.vue';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type EvidenceItem = {
    key: string;
    label: string;
    status: string;
};

type ContractColumn = {
    position: number;
    key: string;
    label: string;
    source_status: string;
};

defineProps<{
    status: 'blocked';
    can_generate: false;
    can_export: false;
    row_count: number;
    report: {
        key: string;
        title: string;
        scope: string;
        date_basis: string;
        grain: string;
    };
    base_columns: ContractColumn[];
    coverage: EvidenceItem[];
    reconciliation_controls: EvidenceItem[];
    blocked_by: string[];
    completeness_boundary: {
        permit_projection_available_elsewhere: boolean;
        all_sources_available: boolean;
        partial_report_may_be_labeled_all: boolean;
        reason: string;
    };
    legacy_evidence: {
        combined_sources: string;
        custom_field_inference: string;
        mixed_date_basis: string;
        cancelled_rows: string;
        classification: string;
    };
    scope_note: string;
    policy_note: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'All Abstract', href: index() },
];

function displayLabel(value: string): string {
    const labels: Record<string, string> = {
        available: 'Available',
        not_implemented: 'Not available',
        not_collected: 'Not recorded',
        authority_blocked: 'Awaiting confirmation',
    };

    return labels[value] ?? value.replaceAll('_', ' ');
}

function statusClass(status: string): string {
    if (status === 'available' || status.endsWith('_available')) {
        return 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-200';
    }

    if (
        status === 'not_implemented' ||
        status === 'not_collected' ||
        status.endsWith('_blocked')
    ) {
        return 'border-red-300 bg-red-50 text-red-800 dark:border-red-700 dark:bg-red-950/30 dark:text-red-200';
    }

    return 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-200';
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="All Abstract of Collection" />

        <main class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-xl font-semibold text-foreground">
                        All Abstract of Collection
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Consolidated Treasury collection report.
                    </p>
                </div>
                <Badge
                    variant="outline"
                    class="border-amber-400 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100"
                    data-testid="all-abstract-boundary-status"
                >
                    <ShieldAlert />
                    Awaiting municipal confirmation
                </Badge>
            </section>

            <ReportFamilyBanner
                family="authority_pending"
                availability="policy_bound"
            />

            <section
                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                aria-label="All Abstract status"
            >
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Official rows
                    </div>
                    <div
                        class="mt-2 text-xl font-semibold"
                        data-testid="all-abstract-official-row-count"
                    >
                        {{ row_count }}
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        Official rows unavailable
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Revenue domains
                    </div>
                    <div
                        class="mt-2 text-xl font-semibold"
                        data-testid="all-abstract-coverage-count"
                    >
                        {{ coverage.length }}
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        One currently available
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Reporting decisions
                    </div>
                    <div
                        class="mt-2 flex items-center gap-2 text-xl font-semibold"
                        data-testid="all-abstract-control-count"
                    >
                        <Landmark class="size-5" />
                        {{ reconciliation_controls.length }}
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        Municipal confirmation required
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Export
                    </div>
                    <div class="mt-2 flex items-center gap-2 font-semibold">
                        <FileLock2 class="size-5" />
                        Unavailable
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        Official export not yet available
                    </div>
                </div>
            </section>

            <section
                class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100"
                data-testid="all-abstract-completeness-boundary"
            >
                <div class="flex items-start gap-3">
                    <ShieldAlert class="mt-0.5 size-5 shrink-0" />
                    <div>
                        <h2 class="font-semibold">
                            Why this report is unavailable
                        </h2>
                        <p class="mt-1">
                            The municipality has not yet confirmed complete
                            Treasury coverage and the rules for combining every
                            revenue source into one official report.
                        </p>
                        <p class="mt-2">
                            Existing permit collection reports remain available.
                            This report will not show partial rows or offer an
                            official export in the meantime.
                        </p>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-2">
                <div class="min-w-0">
                    <div class="mb-3 flex items-center gap-2">
                        <ReceiptText class="size-4" />
                        <h2 class="text-sm font-semibold">
                            Collection coverage
                        </h2>
                    </div>
                    <div
                        class="space-y-2"
                        data-testid="all-abstract-coverage-list"
                    >
                        <article
                            v-for="item in coverage"
                            :key="item.key"
                            class="flex items-start justify-between gap-3 rounded-lg border border-sidebar-border/70 bg-background p-3 dark:border-sidebar-border"
                            data-testid="all-abstract-coverage-item"
                        >
                            <span
                                class="min-w-0 text-sm font-medium break-words"
                                >{{ item.label }}</span
                            >
                            <span
                                class="max-w-40 rounded border px-2 py-1 text-right text-[10px] leading-tight uppercase"
                                :class="statusClass(item.status)"
                            >
                                {{ displayLabel(item.status) }}
                            </span>
                        </article>
                    </div>
                </div>

                <div class="min-w-0">
                    <div class="mb-3 flex items-center gap-2">
                        <Landmark class="size-4" />
                        <h2 class="text-sm font-semibold">
                            Required reporting decisions
                        </h2>
                    </div>
                    <div
                        class="space-y-2"
                        data-testid="all-abstract-controls-list"
                    >
                        <article
                            v-for="control in reconciliation_controls"
                            :key="control.key"
                            class="flex items-start justify-between gap-3 rounded-lg border border-sidebar-border/70 bg-background p-3 dark:border-sidebar-border"
                            data-testid="all-abstract-control-item"
                        >
                            <span
                                class="min-w-0 text-sm font-medium break-words"
                                >{{ control.label }}</span
                            >
                            <span
                                class="max-w-40 rounded border px-2 py-1 text-right text-[10px] leading-tight uppercase"
                                :class="statusClass(control.status)"
                            >
                                {{ displayLabel(control.status) }}
                            </span>
                        </article>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="min-w-0">
                    <div class="mb-3 flex items-center gap-2">
                        <TableProperties class="size-4" />
                        <h2 class="text-sm font-semibold">
                            Required report fields
                        </h2>
                    </div>
                    <div
                        class="grid gap-2 md:hidden"
                        aria-label="All Abstract fields"
                    >
                        <article
                            v-for="column in base_columns"
                            :key="`mobile-${column.key}`"
                            class="rounded-lg border border-sidebar-border/70 bg-background p-3 dark:border-sidebar-border"
                            data-testid="all-abstract-mobile-column"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <span class="text-xs text-muted-foreground"
                                        >Field {{ column.position }}</span
                                    >
                                    <p class="font-medium break-words">
                                        {{ column.label }}
                                    </p>
                                </div>
                                <span
                                    class="max-w-40 rounded border px-2 py-1 text-right text-[10px] leading-tight uppercase"
                                    :class="statusClass(column.source_status)"
                                >
                                    {{ displayLabel(column.source_status) }}
                                </span>
                            </div>
                        </article>
                    </div>
                    <div
                        class="hidden overflow-hidden rounded-lg border border-sidebar-border/70 bg-background md:block dark:border-sidebar-border"
                    >
                        <table class="w-full table-fixed text-sm">
                            <thead
                                class="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase"
                            >
                                <tr>
                                    <th class="w-16 px-3 py-3 font-medium">
                                        No.
                                    </th>
                                    <th class="px-3 py-3 font-medium">Field</th>
                                    <th class="w-64 px-3 py-3 font-medium">
                                        Evidence state
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr
                                    v-for="column in base_columns"
                                    :key="column.key"
                                >
                                    <td class="px-3 py-3 text-muted-foreground">
                                        {{ column.position }}
                                    </td>
                                    <td
                                        class="px-3 py-3 font-medium break-words"
                                    >
                                        {{ column.label }}
                                    </td>
                                    <td class="px-3 py-3">
                                        <span
                                            class="inline-flex rounded border px-2 py-1 text-[10px] leading-tight uppercase"
                                            :class="
                                                statusClass(
                                                    column.source_status,
                                                )
                                            "
                                        >
                                            {{
                                                displayLabel(
                                                    column.source_status,
                                                )
                                            }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <aside class="space-y-4">
                    <section class="border-l-2 border-sidebar-border pl-4">
                        <h2 class="text-sm font-semibold">Source-data notes</h2>
                        <p class="mt-2 text-sm text-muted-foreground">
                            The earlier system combined permit and other
                            collection sources using different dates and fields.
                            Those differences require municipal confirmation
                            before an official consolidated report can be
                            issued.
                        </p>
                    </section>
                    <section class="border-l-2 border-sidebar-border pl-4">
                        <h2 class="text-sm font-semibold">
                            Pending municipal decisions
                        </h2>
                        <ul
                            class="mt-2 space-y-2 text-sm text-muted-foreground"
                        >
                            <li v-for="blocker in blocked_by" :key="blocker">
                                {{ displayLabel(blocker) }}
                            </li>
                        </ul>
                    </section>
                </aside>
            </section>
        </main>
    </AppLayout>
</template>
