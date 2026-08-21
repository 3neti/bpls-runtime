<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    FileLock2,
    ListFilter,
    ShieldAlert,
    TableProperties,
} from '@lucide/vue';
import { index } from '@/actions/App/Http/Controllers/Staff/AnnexCDnfbpReportController';
import ReportFamilyBanner from '@/components/reports/ReportFamilyBanner.vue';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type DnfbpColumn = {
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
        official_scope: string;
        date_basis: string;
        grain: string;
        section_basis: string;
    };
    columns: DnfbpColumn[];
    blocked_by: string[];
    authority_boundary: {
        artifact_is_not_issued_permit: boolean;
        released_status_alone_is_not_sufficient: boolean;
        classification_is_regulatory_assertion: boolean;
        report_is_authority_bearing: boolean;
        reason: string;
    };
    projection_boundary: {
        operational_fields_available: boolean;
        official_rows_available: boolean;
        partial_official_rows_allowed: boolean;
        reason: string;
    };
    legacy_evidence: {
        selection_driven_eligibility: string;
        unissued_business_inclusion: string;
        latest_permit_selection: string;
        period_filter: string;
    };
    scope_note: string;
    policy_note: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ANNEX C - DNFBP', href: index() },
];

function displayLabel(value: string): string {
    const labels: Record<string, string> = {
        authority_blocked: 'Awaiting confirmation',
        not_available: 'Not available',
        source_available: 'Available',
    };

    return labels[value] ?? value.replaceAll('_', ' ');
}

function sourceClass(status: string): string {
    if (status.endsWith('_available')) {
        return 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-200';
    }

    if (status === 'authority_blocked') {
        return 'border-red-300 bg-red-50 text-red-800 dark:border-red-700 dark:bg-red-950/30 dark:text-red-200';
    }

    return 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-200';
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="ANNEX C - DNFBP" />

        <main class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-xl font-semibold text-foreground">
                        ANNEX C - DNFBP
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Semestral DNFBP regulatory return.
                    </p>
                </div>
                <Badge
                    variant="outline"
                    class="border-amber-400 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100"
                    data-testid="dnfbp-boundary-status"
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
                aria-label="ANNEX C report status"
            >
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Official rows
                    </div>
                    <div
                        class="mt-2 text-xl font-semibold"
                        data-testid="dnfbp-official-row-count"
                    >
                        {{ row_count }}
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        Generation unavailable
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Required fields
                    </div>
                    <div
                        class="mt-2 text-xl font-semibold"
                        data-testid="dnfbp-column-count"
                    >
                        {{ columns.length }}
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        Existing field order shown
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Period
                    </div>
                    <div class="mt-2 flex items-center gap-2 font-semibold">
                        <ListFilter class="size-5" />
                        Semester unresolved
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        Reporting period needs confirmation
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
                        No official export route exists
                    </div>
                </div>
            </section>

            <section
                class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100"
                data-testid="dnfbp-authority-boundary"
            >
                <div class="flex items-start gap-3">
                    <ShieldAlert class="mt-0.5 size-5 shrink-0" />
                    <div>
                        <h2 class="font-semibold">
                            Why this report is unavailable
                        </h2>
                        <p class="mt-1">
                            This return requires confirmed permit authority,
                            DNFBP classification, and reporting-period rules. A
                            generated permit document or workflow status does
                            not confirm those facts.
                        </p>
                        <p class="mt-2">
                            Official rows and exports remain unavailable until
                            the municipality confirms the required information.
                        </p>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="min-w-0">
                    <div class="mb-3 flex items-center gap-2">
                        <TableProperties class="size-4" />
                        <h2 class="text-sm font-semibold">
                            ANNEX C required fields
                        </h2>
                    </div>

                    <div
                        class="grid gap-2 md:hidden"
                        aria-label="ANNEX C fields"
                    >
                        <article
                            v-for="column in columns"
                            :key="`mobile-${column.key}`"
                            class="rounded-lg border border-sidebar-border/70 bg-background p-3 dark:border-sidebar-border"
                            data-testid="dnfbp-mobile-column"
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
                                    class="max-w-[11rem] rounded border px-2 py-1 text-right text-[10px] leading-tight uppercase"
                                    :class="sourceClass(column.source_status)"
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
                                <tr v-for="column in columns" :key="column.key">
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
                                                sourceClass(
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
                            DNFBP eligibility rules need municipal confirmation.
                        </p>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Permit-issuance requirements need confirmation.
                        </p>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Permit-selection rules need confirmation.
                        </p>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Reporting-period rules need confirmation.
                        </p>
                    </section>
                    <section class="border-l-2 border-sidebar-border pl-4">
                        <h2 class="text-sm font-semibold">Blocking facts</h2>
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
