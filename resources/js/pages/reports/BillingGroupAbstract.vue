<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    FileLock2,
    ReceiptText,
    ShieldAlert,
    TableProperties,
} from '@lucide/vue';
import { index } from '@/actions/App/Http/Controllers/Staff/BillingGroupAbstractReportController';
import { show as billingGroupShow } from '@/actions/App/Http/Controllers/Staff/BillingGroupController';
import ReportFamilyBanner from '@/components/reports/ReportFamilyBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type BoundaryItem = {
    key: string;
    label: string;
    status: string;
    reason: string;
};

type ContractColumn = {
    position: number;
    key: string;
    label: string;
    source_status: string;
};

const props = defineProps<{
    status: 'blocked';
    can_generate: false;
    can_export: false;
    official_row_count: number;
    billing_group: {
        id: number;
        name: string;
        description: string | null;
        acceptance_status: string;
        is_active: boolean;
        field_count: number;
        record_count: number;
        draft_record_count: number;
    };
    report: {
        key: string;
        title: string;
        scope: string;
        date_basis: string;
        grain: string;
    };
    base_columns: ContractColumn[];
    readiness: BoundaryItem[];
    current_reconciliation: {
        version: number;
        status: string;
        execution_status: string;
    } | null;
    blocked_by: string[];
    legacy_evidence: {
        source: string;
        field_inference: string;
        amount_handling: string;
        date_handling: string;
    };
    scope_note: string;
    policy_note: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: props.billing_group.name,
        href: billingGroupShow(props.billing_group.id),
    },
    { title: 'Abstract Report', href: index(props.billing_group.id) },
];

function displayLabel(value: string): string {
    const labels: Record<string, string> = {
        accepted: 'Confirmed',
        recorded: 'Recorded',
        proposed: 'Provisional',
        not_recorded: 'Not recorded',
        blocked: 'Awaiting confirmation',
    };

    return labels[value] ?? value.replaceAll('_', ' ');
}

function statusClass(status: string): string {
    if (status === 'recorded') {
        return 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-200';
    }

    return 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100';
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="report.title" />

        <main class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-xl font-semibold text-foreground">
                        {{ report.title }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Official collection summary for the selected provisional
                        billing group.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Badge
                        variant="outline"
                        class="border-amber-400 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100"
                        data-testid="billing-group-abstract-status"
                    >
                        <ShieldAlert /> Awaiting municipal confirmation
                    </Badge>
                    <Button as-child variant="outline">
                        <Link :href="billingGroupShow(billing_group.id)">
                            <ArrowLeft /> Billing group
                        </Link>
                    </Button>
                </div>
            </section>

            <ReportFamilyBanner
                family="authority_pending"
                availability="policy_bound"
            />

            <section
                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                aria-label="Billing group abstract status"
            >
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Official rows
                    </div>
                    <div
                        class="mt-2 text-xl font-semibold"
                        data-testid="billing-group-abstract-official-row-count"
                    >
                        {{ official_row_count }}
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        Drafts are excluded
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Draft records
                    </div>
                    <div
                        class="mt-2 text-xl font-semibold"
                        data-testid="billing-group-abstract-draft-count"
                    >
                        {{ billing_group.draft_record_count }}
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        No financial effect
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Acceptance
                    </div>
                    <div class="mt-2 text-lg font-semibold capitalize">
                        {{ displayLabel(billing_group.acceptance_status) }}
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{
                            current_reconciliation
                                ? 'Review information recorded'
                                : 'No review information recorded'
                        }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Export
                    </div>
                    <div class="mt-2 flex items-center gap-2 font-semibold">
                        <FileLock2 class="size-5" /> Unavailable
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        Official export not yet available
                    </div>
                </div>
            </section>

            <section
                class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100"
                data-testid="billing-group-abstract-boundary"
            >
                <div class="flex items-start gap-3">
                    <ShieldAlert class="mt-0.5 size-5 shrink-0" />
                    <div>
                        <h2 class="font-semibold">
                            Why this report is unavailable
                        </h2>
                        <p class="mt-1">
                            Draft billing-group records are visible for review,
                            but they do not establish a collection, receipt, or
                            financial effect.
                        </p>
                        <p class="mt-2">
                            Official rows and exports remain unavailable until
                            Treasury confirms the group definition, fee fields,
                            collection rules, and receipt rules.
                        </p>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_24rem]">
                <div class="min-w-0">
                    <div class="mb-3 flex items-center gap-2">
                        <ReceiptText class="size-4" />
                        <h2 class="text-sm font-semibold">
                            Requirements for availability
                        </h2>
                    </div>
                    <div
                        class="grid gap-2 sm:grid-cols-2"
                        data-testid="billing-group-abstract-readiness"
                    >
                        <article
                            v-for="requirement in readiness"
                            :key="requirement.key"
                            class="rounded-lg border border-sidebar-border/70 bg-background p-3 dark:border-sidebar-border"
                            data-testid="billing-group-abstract-readiness-item"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="text-sm font-medium">
                                    {{ requirement.label }}
                                </h3>
                                <span
                                    class="rounded border px-2 py-1 text-[10px] uppercase"
                                    :class="statusClass(requirement.status)"
                                >
                                    {{ displayLabel(requirement.status) }}
                                </span>
                            </div>
                            <p class="mt-2 text-xs text-muted-foreground">
                                This information has not yet been confirmed for
                                official reporting.
                            </p>
                        </article>
                    </div>
                </div>

                <aside class="space-y-4">
                    <section class="border-l-2 border-sidebar-border pl-4">
                        <h2 class="text-sm font-semibold">
                            Selected definition
                        </h2>
                        <dl class="mt-2 grid gap-2 text-sm">
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Billing group
                                </dt>
                                <dd class="font-medium">
                                    {{ billing_group.name }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Configured fields
                                </dt>
                                <dd>{{ billing_group.field_count }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Definition state
                                </dt>
                                <dd class="capitalize">
                                    {{
                                        displayLabel(
                                            billing_group.acceptance_status,
                                        )
                                    }}
                                </dd>
                            </div>
                        </dl>
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

            <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_24rem]">
                <div class="min-w-0">
                    <div class="mb-3 flex items-center gap-2">
                        <TableProperties class="size-4" />
                        <h2 class="text-sm font-semibold">
                            Required report fields
                        </h2>
                    </div>
                    <div
                        class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background dark:border-sidebar-border"
                    >
                        <table class="w-full table-fixed text-sm">
                            <thead
                                class="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase"
                            >
                                <tr>
                                    <th class="w-14 px-3 py-3 font-medium">
                                        No.
                                    </th>
                                    <th class="px-3 py-3 font-medium">Field</th>
                                    <th class="w-44 px-3 py-3 font-medium">
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
                                    <td
                                        class="px-3 py-3 text-xs text-muted-foreground capitalize"
                                    >
                                        {{ displayLabel(column.source_status) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <aside class="border-l-2 border-sidebar-border pl-4">
                    <h2 class="text-sm font-semibold">Source-data notes</h2>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Earlier source fields are retained for internal review.
                    </p>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Required report fields need municipal confirmation.
                    </p>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Amount and total handling need Treasury confirmation.
                    </p>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Collection-date rules need Treasury confirmation.
                    </p>
                </aside>
            </section>
        </main>
    </AppLayout>
</template>
