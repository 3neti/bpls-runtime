<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { FileLock2, ShieldAlert, TableProperties } from '@lucide/vue';
import { index } from '@/actions/App/Http/Controllers/Staff/CmciLdcsReportController';
import ReportFamilyBanner from '@/components/reports/ReportFamilyBanner.vue';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type CmciColumn = {
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
        eligible_application_types: string[];
        date_basis: string;
        grain: string;
    };
    municipality_evidence: {
        name: string;
        province: string;
        legacy_lgu: string;
        legacy_region: string;
        legacy_classification: string;
        legacy_lgu_type: string;
        acceptance_status: string;
    };
    columns: CmciColumn[];
    blocked_by: string[];
    authority_boundary: {
        artifact_is_not_issued_permit: boolean;
        released_status_alone_is_not_sufficient: boolean;
        reason: string;
    };
    scope_note: string;
    policy_note: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CMCI LDCS Annex B', href: index() },
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
        <Head title="CMCI LDCS Annex B" />

        <main class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-xl font-semibold text-foreground">
                        CMCI LDCS Annex B
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Local Data Capture Sheet for released New and Renewal
                        business permits.
                    </p>
                </div>
                <Badge
                    variant="outline"
                    class="border-amber-400 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100"
                    data-testid="cmci-boundary-status"
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
                aria-label="CMCI report status"
            >
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Official rows
                    </div>
                    <div
                        class="mt-2 text-xl font-semibold"
                        data-testid="cmci-official-row-count"
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
                        data-testid="cmci-column-count"
                    >
                        {{ columns.length }}
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        Annex B field order shown
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Eligible types
                    </div>
                    <div class="mt-2 text-xl font-semibold">
                        {{ report.eligible_application_types.length }}
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{ report.eligible_application_types.join(' and ') }}
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
                data-testid="cmci-authority-boundary"
            >
                <div class="flex items-start gap-3">
                    <ShieldAlert class="mt-0.5 size-5 shrink-0" />
                    <div>
                        <h2 class="font-semibold">
                            Why this report is unavailable
                        </h2>
                        <p class="mt-1">
                            This report requires confirmed permit issuance,
                            release, numbering, classifications, and municipal
                            information. A generated permit document or workflow
                            status does not confirm those facts.
                        </p>
                        <p class="mt-2">
                            Official rows and exports remain unavailable until
                            the municipality confirms them.
                        </p>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="min-w-0">
                    <div class="mb-3 flex items-center gap-2">
                        <TableProperties class="size-4" />
                        <h2 class="text-sm font-semibold">
                            Annex B required fields
                        </h2>
                    </div>

                    <div class="grid gap-2 md:hidden" aria-label="CMCI fields">
                        <article
                            v-for="column in columns"
                            :key="`mobile-${column.key}`"
                            class="rounded-lg border border-sidebar-border/70 bg-background p-3 dark:border-sidebar-border"
                            data-testid="cmci-mobile-column"
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
                                    <th class="w-[44%] px-3 py-3 font-medium">
                                        Evidence state
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-sidebar-border/70">
                                <tr
                                    v-for="column in columns"
                                    :key="column.key"
                                    data-testid="cmci-column"
                                >
                                    <td class="px-3 py-3 text-muted-foreground">
                                        {{ column.position }}
                                    </td>
                                    <td class="px-3 py-3 font-medium">
                                        {{ column.label }}
                                    </td>
                                    <td class="px-3 py-3">
                                        <span
                                            class="inline-flex rounded border px-2 py-1 text-[10px] uppercase"
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

                <aside class="min-w-0 space-y-4">
                    <section
                        class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                    >
                        <h2 class="text-sm font-semibold">Official scope</h2>
                        <dl class="mt-3 grid gap-3 text-sm">
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    One row per
                                </dt>
                                <dd class="mt-1">{{ report.grain }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Records dated by
                                </dt>
                                <dd class="mt-1">{{ report.date_basis }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Municipality evidence
                                </dt>
                                <dd class="mt-1 break-words">
                                    {{ municipality_evidence.legacy_lgu }},
                                    {{ municipality_evidence.province }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section
                        class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                    >
                        <h2 class="text-sm font-semibold">
                            Required decisions
                        </h2>
                        <ul class="mt-3 grid gap-2 text-xs">
                            <li
                                v-for="blocker in blocked_by"
                                :key="blocker"
                                class="break-words text-muted-foreground"
                            >
                                {{ displayLabel(blocker) }}
                            </li>
                        </ul>
                    </section>
                </aside>
            </section>
        </main>
    </AppLayout>
</template>
