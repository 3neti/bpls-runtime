<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import {
    index,
    show,
} from '@/actions/App/Http/Controllers/Staff/FeeRuleController';
import AdministrationScopePanel from '@/components/administration/AdministrationScopePanel.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type FeeRuleRange = {
    id: number;
    min_basis_cents: number;
    max_basis_cents: number | null;
    amount_cents: number;
    rate_basis_points: number | null;
};

type FeeRuleReconciliation = {
    id: number;
    version: number;
    legal_authority: string;
    evidence_reference: string;
    original_text: string;
    normalized_interpretation: string | null;
    decision_authority: string | null;
    decision_reference: string | null;
    effective_from: string;
    effective_until: string | null;
    execution_status: string;
    execution_reason: string;
    decided_at: string | null;
};

type FeeRule = {
    id: number;
    code: string;
    name: string;
    category: string;
    scope: string;
    calculation_type: string;
    basis: string;
    amount_cents: number;
    rate_basis_points: number | null;
    effective_from: string | null;
    effective_until: string | null;
    is_active: boolean;
    legal_basis: string | null;
    legacy_source_id: string | null;
    line_of_business: {
        id: number;
        code: string | null;
        name: string;
    } | null;
    range_count: number;
    catalog_status: string | null;
    application_types: string[] | null;
    policy_boundaries: string[];
    policy_note: string | null;
    reconciliation_required: boolean;
    current_reconciliation: FeeRuleReconciliation | null;
    ranges: FeeRuleRange[];
};

const props = defineProps<{
    feeRule: FeeRule;
    scopeNote: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Fee and Rule Catalog',
        href: index(),
    },
    {
        title: props.feeRule.code,
        href: show(props.feeRule.id),
    },
];

function money(amountCents: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amountCents / 100);
}

function label(value: string | null): string {
    return value ? value.replaceAll('_', ' ') : '-';
}

function availabilityLabel(status: string): string {
    return status === 'executable'
        ? 'Available for assessment'
        : 'Not yet confirmed';
}

function applicability(applicationTypes: string[] | null): string {
    if (!applicationTypes || applicationTypes.length === 0) {
        return 'All application types';
    }

    return applicationTypes.map(label).join(', ');
}

function basisRange(range: FeeRuleRange): string {
    if (range.max_basis_cents === null) {
        return `${money(range.min_basis_cents)} and above`;
    }

    return `${money(range.min_basis_cents)} to ${money(range.max_basis_cents)}`;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="feeRule.code" />

        <main class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1
                            class="text-xl font-semibold break-words text-foreground"
                        >
                            {{ feeRule.code }}
                        </h1>
                        <Badge
                            :variant="feeRule.is_active ? 'default' : 'outline'"
                        >
                            {{ feeRule.is_active ? 'Active' : 'Inactive' }}
                        </Badge>
                        <Badge v-if="feeRule.catalog_status" variant="outline">
                            {{ label(feeRule.catalog_status) }}
                        </Badge>
                        <Badge
                            v-if="feeRule.current_reconciliation"
                            :variant="
                                feeRule.current_reconciliation
                                    .execution_status === 'executable'
                                    ? 'default'
                                    : 'destructive'
                            "
                            data-testid="fee-rule-execution-status"
                        >
                            {{
                                availabilityLabel(
                                    feeRule.current_reconciliation
                                        .execution_status,
                                )
                            }}
                        </Badge>
                    </div>
                    <p
                        class="mt-1 max-w-4xl text-sm break-words text-muted-foreground"
                    >
                        {{ feeRule.name }}
                    </p>
                </div>

                <Button as-child variant="outline">
                    <Link :href="index()">
                        <ArrowLeft />
                        Back to catalog
                    </Link>
                </Button>
            </section>

            <AdministrationScopePanel
                available="Review this recorded rule, its source and legal basis, calculation inputs, effective dates, and current assessment availability."
                evidence="The source text, municipal decision, and whether the rule may be used are recorded separately."
                unavailable="Editing the rule, accepting financial policy, or activating a candidate that still needs municipal confirmation."
            />

            <section
                class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100"
            >
                <p class="font-medium">Municipal confirmation required</p>
                <p class="mt-1">
                    This page is read-only. A recorded Revenue Code rule can be
                    used for assessment only after the Municipality confirms the
                    rule and its interpretation.
                </p>
            </section>

            <section
                v-if="feeRule.reconciliation_required"
                class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                data-testid="financial-reconciliation"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">
                            Municipal rule review
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Source text and the municipal decision are recorded
                            separately from whether this rule may be used.
                        </p>
                    </div>
                    <Badge
                        v-if="feeRule.current_reconciliation"
                        :variant="
                            feeRule.current_reconciliation.execution_status ===
                            'executable'
                                ? 'default'
                                : 'destructive'
                        "
                    >
                        {{
                            availabilityLabel(
                                feeRule.current_reconciliation.execution_status,
                            )
                        }}
                    </Badge>
                </div>

                <div
                    v-if="feeRule.current_reconciliation"
                    class="mt-4 grid gap-4 lg:grid-cols-2"
                >
                    <div class="grid gap-4">
                        <div>
                            <h3
                                class="text-xs font-medium text-muted-foreground uppercase"
                            >
                                Original Ordinance Text
                            </h3>
                            <p class="mt-1 text-sm break-words">
                                {{
                                    feeRule.current_reconciliation.original_text
                                }}
                            </p>
                        </div>
                        <div>
                            <h3
                                class="text-xs font-medium text-muted-foreground uppercase"
                            >
                                Recorded Interpretation
                            </h3>
                            <p class="mt-1 text-sm break-words">
                                {{
                                    feeRule.current_reconciliation
                                        .normalized_interpretation ??
                                    'No interpretation recorded'
                                }}
                            </p>
                        </div>
                        <div>
                            <h3
                                class="text-xs font-medium text-muted-foreground uppercase"
                            >
                                Availability Note
                            </h3>
                            <p
                                class="mt-1 text-sm break-words"
                                data-testid="fee-rule-execution-reason"
                            >
                                {{
                                    feeRule.current_reconciliation
                                        .execution_reason
                                }}
                            </p>
                        </div>
                    </div>

                    <dl class="grid gap-4 text-sm">
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Legal Authority
                            </dt>
                            <dd class="mt-1 break-words">
                                {{
                                    feeRule.current_reconciliation
                                        .legal_authority
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Source Reference
                            </dt>
                            <dd class="mt-1 break-words">
                                {{
                                    feeRule.current_reconciliation
                                        .evidence_reference
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Decision Authority
                            </dt>
                            <dd class="mt-1 break-words">
                                {{
                                    feeRule.current_reconciliation
                                        .decision_authority ?? '-'
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Decision Reference
                            </dt>
                            <dd class="mt-1 break-words">
                                {{
                                    feeRule.current_reconciliation
                                        .decision_reference ?? '-'
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Effective Period
                            </dt>
                            <dd class="mt-1">
                                {{
                                    feeRule.current_reconciliation
                                        .effective_from
                                }}
                                to
                                {{
                                    feeRule.current_reconciliation
                                        .effective_until ?? 'No end date'
                                }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <p
                    v-else
                    class="mt-4 text-sm font-medium text-destructive"
                    data-testid="fee-rule-reconciliation-missing"
                >
                    This rule is not available because no municipal review
                    decision is recorded.
                </p>
            </section>

            <section class="grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <h2 class="text-sm font-semibold text-foreground">
                        Rule Summary
                    </h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Category
                            </dt>
                            <dd class="mt-1">
                                {{ label(feeRule.category) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Scope
                            </dt>
                            <dd class="mt-1">
                                {{ label(feeRule.scope) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Calculation
                            </dt>
                            <dd class="mt-1">
                                {{ label(feeRule.calculation_type) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Basis
                            </dt>
                            <dd class="mt-1">
                                {{ label(feeRule.basis) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Amount
                            </dt>
                            <dd class="mt-1 font-medium">
                                {{ money(feeRule.amount_cents) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Rate Basis Points
                            </dt>
                            <dd class="mt-1">
                                {{ feeRule.rate_basis_points ?? '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Effective From
                            </dt>
                            <dd class="mt-1">
                                {{ feeRule.effective_from ?? '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Effective Until
                            </dt>
                            <dd class="mt-1">
                                {{ feeRule.effective_until ?? 'No end date' }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <h2 class="text-sm font-semibold text-foreground">
                        Applicability and Evidence
                    </h2>
                    <dl class="mt-4 grid gap-4">
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Line of Business
                            </dt>
                            <dd class="mt-1 break-words">
                                {{
                                    feeRule.line_of_business?.name ??
                                    'Application-wide'
                                }}
                            </dd>
                            <dd
                                v-if="feeRule.line_of_business?.code"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{ feeRule.line_of_business.code }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Application Types
                            </dt>
                            <dd class="mt-1">
                                {{ applicability(feeRule.application_types) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Legal Basis
                            </dt>
                            <dd class="mt-1 break-words">
                                {{ feeRule.legal_basis ?? '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Technical Source Reference
                            </dt>
                            <dd class="mt-1 break-words">
                                {{ feeRule.legacy_source_id ?? '-' }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section
                v-if="
                    feeRule.policy_note || feeRule.policy_boundaries.length > 0
                "
                class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
            >
                <h2 class="text-sm font-semibold text-foreground">
                    Items Requiring Confirmation
                </h2>
                <p
                    v-if="feeRule.policy_note"
                    class="mt-3 text-sm text-muted-foreground"
                >
                    {{ feeRule.policy_note }}
                </p>
                <div
                    v-if="feeRule.policy_boundaries.length > 0"
                    class="mt-3 flex flex-wrap gap-2"
                >
                    <Badge
                        v-for="boundary in feeRule.policy_boundaries"
                        :key="boundary"
                        variant="outline"
                    >
                        {{ label(boundary) }}
                    </Badge>
                </div>
            </section>

            <section
                class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background dark:border-sidebar-border"
            >
                <div class="border-b p-4">
                    <h2 class="text-sm font-semibold text-foreground">
                        Ranges
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Recorded amount ranges for this rule. Unresolved formula
                        meaning remains unavailable until confirmed.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[680px] table-fixed text-sm">
                        <thead
                            class="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="w-[45%] px-3 py-3 font-medium">
                                    Basis Range
                                </th>
                                <th
                                    class="w-[25%] px-3 py-3 text-right font-medium"
                                >
                                    Amount
                                </th>
                                <th
                                    class="w-[30%] px-3 py-3 text-right font-medium"
                                >
                                    Rate Basis Points
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="feeRule.ranges.length === 0">
                                <td
                                    colspan="3"
                                    class="px-3 py-8 text-center text-muted-foreground"
                                >
                                    This rule has no recorded range brackets.
                                </td>
                            </tr>
                            <tr
                                v-for="range in feeRule.ranges"
                                :key="range.id"
                                class="border-b last:border-0"
                            >
                                <td class="px-3 py-3">
                                    {{ basisRange(range) }}
                                </td>
                                <td class="px-3 py-3 text-right font-medium">
                                    {{ money(range.amount_cents) }}
                                </td>
                                <td class="px-3 py-3 text-right">
                                    {{ range.rate_basis_points ?? '-' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
