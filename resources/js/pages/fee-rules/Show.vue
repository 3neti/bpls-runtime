<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, History, PencilLine } from '@lucide/vue';
import { ref } from 'vue';
import {
    index,
    proposeRevision,
    show,
} from '@/actions/App/Http/Controllers/Staff/FeeRuleController';
import AdministrationScopePanel from '@/components/administration/AdministrationScopePanel.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    family: string;
    family_label: string;
    currency: 'PHP';
    responsible_office: string | null;
    calculation_type: string;
    basis: string;
    amount_cents: number;
    rate_basis_points: number | null;
    effective_from: string | null;
    effective_until: string | null;
    is_active: boolean;
    legal_basis: string | null;
    legacy_source_id: string | null;
    revenue_code: string | null;
    owner: string;
    amount_basis: string;
    display_status: string;
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
    revisions: {
        id: number;
        version: number;
        status: string;
        currency: 'PHP';
        previous_amount_minor: number | null;
        proposed_amount_minor: number | null;
        effective_from: string;
        effective_until: string | null;
        reason: string;
        authority: string;
        proposed_by: string | null;
        proposed_at: string;
        executable: false;
    }[];
    audit_events: {
        id: number;
        event: string;
        actor: string | null;
        occurred_at: string;
        snapshot: Record<string, unknown>;
    }[];
};

const props = defineProps<{
    feeRule: FeeRule;
    scopeNote: string;
    canProposeRevision: boolean;
}>();

const showRevisionForm = ref(false);
const revisionForm = useForm({
    proposed_amount_minor: props.feeRule.amount_cents,
    effective_from: '',
    effective_until: null as string | null,
    reason: '',
    authority: '',
});

function submitRevision(): void {
    revisionForm.post(proposeRevision(props.feeRule.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            showRevisionForm.value = false;
            revisionForm.reset();
        },
    });
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Fee and Rule Catalog',
        href: index(),
    },
    {
        title: props.feeRule.name,
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
        <Head :title="feeRule.name" />

        <main class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1
                            class="text-xl font-semibold break-words text-foreground"
                        >
                            {{ feeRule.name }}
                        </h1>
                        <Badge
                            :variant="feeRule.is_active ? 'default' : 'outline'"
                        >
                            {{ feeRule.display_status }}
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
                        Revenue code:
                        {{ feeRule.revenue_code ?? 'Not recorded' }} ·
                        {{ feeRule.owner }} · {{ feeRule.amount_basis }}
                    </p>
                </div>

                <Button as-child variant="outline">
                    <Link :href="index()">
                        <ArrowLeft />
                        Back to catalog
                    </Link>
                </Button>
                <Button
                    v-if="canProposeRevision"
                    data-testid="propose-fee-revision"
                    @click="showRevisionForm = !showRevisionForm"
                >
                    <PencilLine />
                    Propose Change
                </Button>
            </section>

            <section
                v-if="showRevisionForm"
                class="rounded-xl border-2 border-primary/30 bg-card p-5"
                data-testid="fee-revision-form"
            >
                <h2 class="font-semibold">Propose a new fee revision</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    This records an append-only proposal. It cannot affect an
                    Assessment until a separately commissioned activation
                    authority exists.
                </p>
                <form
                    class="mt-4 grid gap-4 sm:grid-cols-2"
                    @submit.prevent="submitRevision"
                >
                    <div class="grid gap-2">
                        <Label for="proposed_amount_minor"
                            >Proposed amount (minor units)</Label
                        >
                        <Input
                            id="proposed_amount_minor"
                            v-model="revisionForm.proposed_amount_minor"
                            type="number"
                            min="0"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="effective_from">Effective from</Label>
                        <Input
                            id="effective_from"
                            v-model="revisionForm.effective_from"
                            type="date"
                            required
                        />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="revision_reason">Reason</Label>
                        <textarea
                            id="revision_reason"
                            v-model="revisionForm.reason"
                            required
                            rows="3"
                            class="rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="revision_authority"
                            >Authority / legal basis</Label
                        >
                        <textarea
                            id="revision_authority"
                            v-model="revisionForm.authority"
                            required
                            rows="3"
                            class="rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>
                    <Button
                        type="submit"
                        class="sm:w-fit"
                        :disabled="revisionForm.processing"
                    >
                        Record proposed revision
                    </Button>
                </form>
            </section>

            <section
                class="rounded-xl border bg-card p-5"
                data-testid="fee-policy-history"
            >
                <div class="flex items-center gap-2">
                    <History class="size-5" aria-hidden="true" />
                    <h2 class="font-semibold">Fee Policy History</h2>
                </div>
                <p
                    v-if="!feeRule.revisions.length"
                    class="mt-3 text-sm text-muted-foreground"
                >
                    No proposed revisions have been recorded. The current fee
                    version remains unchanged.
                </p>
                <ol v-else class="mt-4 grid gap-3">
                    <li
                        v-for="revision in feeRule.revisions"
                        :key="revision.id"
                        class="rounded-lg border p-4"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-2"
                        >
                            <p class="font-semibold">
                                Version {{ revision.version }} ·
                                {{ money(revision.previous_amount_minor ?? 0) }}
                                →
                                {{ money(revision.proposed_amount_minor ?? 0) }}
                            </p>
                            <Badge variant="outline"
                                >Proposed — not executable</Badge
                            >
                        </div>
                        <p class="mt-2 text-sm">{{ revision.reason }}</p>
                        <dl
                            class="mt-3 grid gap-2 text-xs text-muted-foreground sm:grid-cols-2"
                        >
                            <div>
                                <dt>Authority</dt>
                                <dd>{{ revision.authority }}</dd>
                            </div>
                            <div>
                                <dt>Effective</dt>
                                <dd>{{ revision.effective_from }}</dd>
                            </div>
                            <div>
                                <dt>Proposed by</dt>
                                <dd>
                                    {{
                                        revision.proposed_by ?? 'Recorded actor'
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt>Recorded</dt>
                                <dd>{{ revision.proposed_at }}</dd>
                            </div>
                        </dl>
                    </li>
                </ol>
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
                                {{ feeRule.family_label }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Responsible Office
                            </dt>
                            <dd class="mt-1">
                                {{
                                    feeRule.responsible_office ?? 'Municipality'
                                }}
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
