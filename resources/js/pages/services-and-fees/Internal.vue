<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    BookOpenText,
    Building2,
    CheckCircle2,
    CircleAlert,
    ExternalLink,
    Landmark,
    Scale,
    ShieldCheck,
} from '@lucide/vue';
import { computed } from 'vue';
import { index as feeRuleIndex } from '@/actions/App/Http/Controllers/Staff/FeeRuleController';
import { index as serviceCatalogIndex } from '@/actions/App/Http/Controllers/Staff/MunicipalServiceCatalogController';
import PageHeader from '@/components/PageHeader.vue';
import PricingStatusBadge from '@/components/services-and-fees/PricingStatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type {
    BreadcrumbItem,
    InternalFeeRule,
    MunicipalPriceList,
} from '@/types';

defineProps<{
    priceList: MunicipalPriceList;
}>();

const page = usePage();
const persona = computed(
    () => page.props.stakeholder_preview?.current_persona ?? null,
);
const concernedOfficePersonas = [
    'engineering',
    'mpdo',
    'assessor',
    'health',
    'menro',
];
const isConcernedOffice = computed(() =>
    persona.value ? concernedOfficePersonas.includes(persona.value) : false,
);

const audienceGuidance = computed(() => {
    if (persona.value === 'treasury') {
        return {
            icon: Landmark,
            title: 'Treasury view',
            message:
                'Confirm the exact rule version, effective dates, and legal source that enter assessment before approving a payable amount.',
        };
    }

    if (isConcernedOffice.value) {
        return {
            icon: Building2,
            title: 'Concerned-office view',
            message:
                'Office-determined charges are shown as a category only. Provisional walkthrough amounts are not municipal prices and never appear in this catalog.',
        };
    }

    if (persona.value === 'management') {
        return {
            icon: Scale,
            title: 'Management view',
            message:
                'This consolidated view shows what the Municipality tells people, what assessment selects, and which recorded rules still require confirmation.',
        };
    }

    if (page.props.auth.role === 'admin') {
        return {
            icon: ShieldCheck,
            title: 'Administration view',
            message:
                'Review the product catalog first, then open Taxes & Fees for the complete legal and reconciliation evidence surface.',
        };
    }

    return {
        icon: BookOpenText,
        title: 'BPLO and Assessment view',
        message:
            'Compare the public statement with the exact rules selected by assessment. Blocked or ambiguous rules remain visible without being published as confirmed prices.',
    };
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Services & Fees',
        href: serviceCatalogIndex(),
    },
];

function money(amountCents: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
    }).format(amountCents / 100);
}

function sourceLabel(source: string): string {
    const labels: Record<string, string> = {
        accepted_municipal_authority: 'Accepted municipal source',
        municipal_confirmation_required: 'Municipal confirmation required',
        synthetic: 'Synthetic — not publishable',
        provisional_uat: 'Provisional UAT — not publishable',
        historical: 'Historical — not publishable',
        mock: 'Mock — not publishable',
        legacy_evidence_only: 'Legacy evidence only — not publishable',
        lifecycle_test: 'Lifecycle test — not publishable',
        unclassified: 'Unclassified — not publishable',
    };

    return labels[source] ?? 'Unclassified — not publishable';
}

function recordedValue(rule: InternalFeeRule): string {
    if (rule.recorded_amount_cents !== null) {
        return money(rule.recorded_amount_cents);
    }

    if (rule.rate_basis_points !== null) {
        return `${rule.rate_basis_points / 100}% of ${rule.basis.replaceAll('_', ' ')}`;
    }

    return 'No publishable amount';
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Services & Fees" />

        <main class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <PageHeader
                    eyebrow="Municipal pricing control"
                    title="Services & Fees"
                    description="The read-only product view of what BPLS offers, what the public sees, and what the assessment path actually selects."
                />
                <Button
                    v-if="page.props.auth.can_view_fee_rules"
                    variant="outline"
                    as-child
                >
                    <Link :href="feeRuleIndex()">
                        Open Taxes & Fees
                        <ExternalLink aria-hidden="true" />
                    </Link>
                </Button>
            </div>

            <aside
                class="flex items-start gap-3 rounded-xl border bg-muted/40 p-4"
                :aria-label="audienceGuidance.title"
            >
                <component
                    :is="audienceGuidance.icon"
                    class="mt-0.5 size-5 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
                <div class="space-y-1">
                    <p class="font-semibold">{{ audienceGuidance.title }}</p>
                    <p class="text-sm leading-6 text-muted-foreground">
                        {{ audienceGuidance.message }}
                    </p>
                </div>
            </aside>

            <section
                aria-label="Price list summary"
                class="grid gap-3 sm:grid-cols-3"
            >
                <div class="rounded-xl border bg-card p-4">
                    <p class="text-sm text-muted-foreground">BPLS services</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">
                        {{ priceList.catalog.service_count }}
                    </p>
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <p class="text-sm text-muted-foreground">
                        Confirmed exact public charges
                    </p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">
                        {{ priceList.catalog.confirmed_exact_charge_count }}
                    </p>
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <p class="text-sm text-muted-foreground">Effective view</p>
                    <p class="mt-1 text-base font-semibold">
                        {{ priceList.catalog.as_of_date }}
                    </p>
                </div>
            </section>

            <section aria-label="Service pricing controls" class="space-y-4">
                <article
                    v-for="service in priceList.services"
                    :key="service.code"
                    class="min-w-0 overflow-hidden rounded-2xl border bg-card"
                >
                    <div class="space-y-5 p-5 sm:p-6">
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div class="max-w-3xl space-y-1">
                                <h2 class="text-lg font-semibold">
                                    {{ service.name }}
                                </h2>
                                <p
                                    class="text-sm leading-6 text-muted-foreground"
                                >
                                    {{ service.description }}
                                </p>
                            </div>
                            <PricingStatusBadge
                                :availability="service.availability"
                                :label="service.availability_label"
                            />
                        </div>

                        <div
                            class="grid min-w-0 gap-4 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]"
                        >
                            <div class="space-y-3 rounded-xl bg-muted/50 p-4">
                                <p
                                    class="text-xs font-semibold tracking-wide uppercase"
                                >
                                    What the public sees
                                </p>
                                <template
                                    v-if="
                                        service.pricing.confirmed_charges
                                            .length > 0
                                    "
                                >
                                    <div
                                        v-for="charge in service.pricing
                                            .confirmed_charges"
                                        :key="charge.traceability.fee_rule_id"
                                        class="space-y-1"
                                    >
                                        <p class="font-medium">
                                            {{ charge.label }}
                                        </p>
                                        <p
                                            class="text-xl font-semibold tabular-nums"
                                        >
                                            {{ money(charge.amount_cents) }} /
                                            {{ charge.cadence }}
                                        </p>
                                    </div>
                                </template>
                                <p v-else class="text-sm leading-6">
                                    Municipal confirmation is still required; no
                                    exact service price is published.
                                </p>
                                <p
                                    class="border-t pt-3 text-sm leading-6 text-muted-foreground"
                                >
                                    {{ service.pricing.other_charges_message }}
                                </p>
                            </div>

                            <div class="min-w-0 space-y-3">
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <p
                                        class="text-xs font-semibold tracking-wide uppercase"
                                    >
                                        Assessment rule selection
                                    </p>
                                    <span
                                        class="text-xs text-muted-foreground tabular-nums"
                                    >
                                        {{
                                            service.internal
                                                ?.selected_rule_count ?? 0
                                        }}
                                        rule(s)
                                    </span>
                                </div>

                                <div
                                    v-if="service.internal?.rules.length"
                                    class="space-y-3"
                                >
                                    <details
                                        v-for="rule in service.internal.rules"
                                        :key="rule.id"
                                        class="group rounded-xl border bg-background p-4"
                                        :open="
                                            service.code ===
                                                'new_business_permit' &&
                                            rule.publication_status ===
                                                'confirmed_exact'
                                        "
                                    >
                                        <summary
                                            class="cursor-pointer list-none outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            <div
                                                class="flex flex-wrap items-start justify-between gap-3"
                                            >
                                                <div class="min-w-0">
                                                    <p
                                                        class="font-medium break-words"
                                                    >
                                                        {{ rule.name }}
                                                    </p>
                                                    <p
                                                        class="mt-1 font-mono text-xs break-all text-muted-foreground"
                                                    >
                                                        {{ rule.code }}
                                                    </p>
                                                </div>
                                                <Badge
                                                    :variant="
                                                        rule.automatic_assessment_status ===
                                                        'used_by_assessment'
                                                            ? 'default'
                                                            : 'secondary'
                                                    "
                                                >
                                                    <CheckCircle2
                                                        v-if="
                                                            rule.automatic_assessment_status ===
                                                            'used_by_assessment'
                                                        "
                                                        aria-hidden="true"
                                                    />
                                                    <CircleAlert
                                                        v-else
                                                        aria-hidden="true"
                                                    />
                                                    {{
                                                        rule.automatic_assessment_label
                                                    }}
                                                </Badge>
                                            </div>
                                        </summary>

                                        <div
                                            class="mt-4 grid gap-3 border-t pt-4 text-sm sm:grid-cols-2"
                                        >
                                            <div>
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    Recorded value
                                                </p>
                                                <p class="mt-1 font-medium">
                                                    {{ recordedValue(rule) }}
                                                </p>
                                                <p
                                                    v-if="
                                                        rule.publication_status !==
                                                        'confirmed_exact'
                                                    "
                                                    class="mt-1 text-xs text-muted-foreground"
                                                >
                                                    Not published as a confirmed
                                                    charge
                                                </p>
                                            </div>
                                            <div>
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    Source classification
                                                </p>
                                                <p class="mt-1 font-medium">
                                                    {{
                                                        sourceLabel(
                                                            rule.source_classification,
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                            <div>
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    Effective period
                                                </p>
                                                <p class="mt-1">
                                                    {{ rule.effective_from }} to
                                                    {{
                                                        rule.effective_until ??
                                                        'present'
                                                    }}
                                                </p>
                                            </div>
                                            <div>
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    Evidence version
                                                </p>
                                                <p class="mt-1">
                                                    <template
                                                        v-if="
                                                            rule.reconciliation
                                                        "
                                                    >
                                                        Version
                                                        {{
                                                            rule.reconciliation
                                                                .version
                                                        }}
                                                        ·
                                                        {{
                                                            rule.reconciliation
                                                                .legal_authority
                                                        }}
                                                    </template>
                                                    <template v-else>
                                                        No accepted version
                                                        recorded
                                                    </template>
                                                </p>
                                            </div>
                                            <div class="sm:col-span-2">
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    Current status
                                                </p>
                                                <p class="mt-1 leading-6">
                                                    {{
                                                        rule.plain_language_status
                                                    }}
                                                </p>
                                            </div>
                                            <div
                                                v-if="rule.legal_basis"
                                                class="sm:col-span-2"
                                            >
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    Legal and effective basis
                                                </p>
                                                <p class="mt-1 leading-6">
                                                    {{ rule.legal_basis }}
                                                </p>
                                            </div>
                                        </div>
                                    </details>
                                </div>

                                <div
                                    class="rounded-xl border border-dashed p-4 text-sm leading-6"
                                >
                                    <p class="font-medium">
                                        Concerned municipal offices
                                    </p>
                                    <p class="mt-1 text-muted-foreground">
                                        {{
                                            service.internal?.office_determined
                                                .display
                                        }}
                                    </p>
                                    <p
                                        class="mt-2 text-xs text-muted-foreground"
                                    >
                                        No office-specific official amount is
                                        asserted here.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </section>

            <aside
                class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
            >
                <p class="font-semibold">Read-only commissioning boundary</p>
                <p class="mt-1">
                    This screen cannot edit, approve, publish, supersede, or
                    delete fee rules. Future maintenance must preserve actor,
                    before/after values, legal basis, effective date, approval,
                    audit history, and future-only effect without rewriting past
                    assessments.
                </p>
            </aside>
        </main>
    </AppLayout>
</template>
