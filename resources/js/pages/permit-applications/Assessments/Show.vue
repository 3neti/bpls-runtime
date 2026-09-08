<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    ChevronRight,
    CreditCard,
    FileText,
    ReceiptText,
    RotateCcw,
    Scale,
} from '@lucide/vue';
import { computed } from 'vue';
import {
    approve as approveAssessment,
    returnForCorrection,
} from '@/actions/App/Http/Controllers/Staff/AssessmentDecisionController';
import {
    show as paymentScheduleShow,
    store as paymentScheduleStore,
} from '@/actions/App/Http/Controllers/Staff/AssessmentPaymentScheduleController';
import {
    index as assessmentIndex,
    pdf as assessmentPdf,
} from '@/actions/App/Http/Controllers/Staff/PermitApplicationAssessmentController';
import ComputationAssessmentSlip from '@/components/assessments/ComputationAssessmentSlip.vue';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type AssessmentLine = {
    id: number;
    code: string;
    name: string;
    category: string;
    calculation_type: string;
    basis: string;
    basis_amount_cents: number;
    amount_cents: number;
    line_of_business: string | null;
    legal_basis: string | null;
};

type AssessmentWorkingPaper = {
    line_sections: {
        line_of_business_id: number;
        line_of_business_name: string | null;
        charges: AssessmentLine[];
        subtotal_amount_cents: number;
    }[];
    application_charges: AssessmentLine[];
    application_subtotal_amount_cents: number;
    grand_total_amount_cents: number;
    grouped_total_amount_cents: number;
    reconciles: boolean;
};

type AssessmentSlipCharge = {
    assessment_line_id: number;
    code: string;
    name: string;
    amount_cents: number;
    source_type: 'governed_canonical_pricing' | 'paperless_payment_order';
    paperless_payment_order: {
        id: number | null;
        sequence: number | null;
        office_code: string | null;
        office_label: string | null;
        issued_at: string | null;
    } | null;
};

type Assessment = {
    id: number;
    sequence: number;
    status: string;
    display_status: string;
    assessed_at: string | null;
    assessed_by: string | null;
    total_amount_cents: number;
    currency: 'PHP';
    snapshot_hash: string;
    assessment_price_input_fingerprint: string | null;
    price_report_fingerprint: string | null;
    price_report: {
        schema_version: string;
        currency: 'PHP';
        groups: {
            key: string;
            label: string;
            currency: 'PHP';
            minor: number;
        }[];
        components: {
            exact_once_key: string;
            label: string;
            responsible_office: string | null;
            scheduled_minor: number;
            resolved_minor: number;
            applied_modifier_keys: string[];
            source: { type: string; identity: string; version: string };
        }[];
        modifiers: {
            key: string;
            type: string;
            amount_minor: number;
            reason: string;
            authority: string;
            office: string;
            occurred_at: string;
        }[];
        total: { currency: 'PHP'; minor: number };
    } | null;
    business_permit_evaluation: {
        evaluation_id: number;
        version_id: number;
        version_sequence: number;
        fingerprint: string;
        view_url: string;
    } | null;
    treasury_counter_check: {
        assessment_id: number | null;
        assessment_snapshot_hash: string | null;
        result: 'no_correction' | 'material_correction' | null;
        checked_at: string;
        checked_by: string;
        reason: string | null;
        evidence_provenance: string;
    } | null;
    payment_schedule_available: boolean;
    decision: {
        id: number;
        action: 'approved' | 'returned_for_correction';
        decided_at: string;
        decided_by: string | null;
        decided_by_role: string | null;
        reason: string | null;
        assessment_snapshot_hash: string;
        total_amount_cents: number;
    } | null;
    permit_application: {
        id: number;
        application_number: string | null;
        type: string;
        status: string;
        application_year: number;
        business_name: string;
        owner_name: string;
    };
    financial_working_paper: AssessmentWorkingPaper;
    lines: AssessmentLine[];
    latest_payment_schedule: {
        id: number;
        sequence: number;
        status: string;
        payment_mode: string;
        total_amount_cents: number;
        paid_amount_cents: number;
        created_at: string | null;
    } | null;
};

type AssessmentReconciliation = {
    status: 'exact_match' | 'difference' | 'source_evidence_invalid';
    comparable: boolean;
    source_reference: string | null;
    source_business_category?: string | null;
    source?: {
        label: string;
        status: string;
        assessed_at: string;
        total_amount_cents: number;
        component_total_amount_cents: number;
        internally_reconciles: boolean;
        evidence_hash: string;
        schedules: {
            section: number;
            status: string;
            total_amount_cents: number;
            paid_amount_cents: number;
            fee_total_amount_cents: number;
            surcharge_amount_cents: number;
            penalty_amount_cents: number;
            fees: {
                name: string;
                category: string;
                amount_cents: number;
            }[];
        }[];
    };
    computed?: {
        label: string;
        assessment_id: number;
        total_amount_cents: number;
        component_total_amount_cents: number;
        internally_reconciles: boolean;
        snapshot_hash: string;
        lines: {
            code: string;
            name: string;
            category: string;
            amount_cents: number;
        }[];
    };
    comparison?: {
        delta_amount_cents: number;
        absolute_delta_amount_cents: number;
        direction: 'new_bpls_higher' | 'legacy_source_higher' | 'equal';
        component_identity_mapping: 'not_established';
    };
    statement: string;
    operational_effect: false;
};

const props = defineProps<{
    assessment: Assessment;
    assessmentReconciliation: AssessmentReconciliation | null;
    computationAssessmentSlip: {
        institution: {
            country: string;
            province: string;
            municipality: string;
            title: string;
        };
        reference: {
            assessment_sequence: number;
            official_number: null;
            official_number_status: string;
            snapshot_hash: string;
        };
        transaction_type: string;
        owner_proprietor: string;
        business_name: string;
        business_address: string | null;
        payment_mode: string;
        line_of_businesses: {
            id: number;
            code: string | null;
            name: string | null;
        }[];
        line_sections: {
            line_of_business_id: number;
            line_of_business_name: string | null;
            charges: AssessmentSlipCharge[];
            subtotal_amount_cents: number;
        }[];
        application_charges: AssessmentSlipCharge[];
        application_subtotal_amount_cents: number;
        grand_total_amount_cents: number;
        grouped_total_amount_cents: number;
        reconciles: boolean;
        in_words: string | null;
        schedule_of_payments: {
            payment_mode: string;
            allocation_status: string;
            allocation_note: string;
            quarters: {
                section: string;
                due_date: string | null;
                amount_cents: number | null;
                balance_cents: number | null;
            }[];
        };
        prepared_by: {
            name: string | null;
            prepared_at: string | null;
            role: string;
        };
        approved_by: {
            name: string | null;
            approved_at: string;
            role: string;
            snapshot_hash: string;
        } | null;
        acknowledged_by: null;
        acknowledgement_note: string;
    };
    can: {
        prepare_payment_schedule: boolean;
        counter_check_assessment: boolean;
        approve_assessment: boolean;
        view_payment_schedules: boolean;
        view_assessment_documents: boolean;
    };
    assessmentDocumentGaps: string[];
}>();

const page = usePage();

const awaitingTreasuryCounterCheck = computed(
    () =>
        props.assessment.business_permit_evaluation !== null &&
        props.assessment.treasury_counter_check === null &&
        props.assessment.decision === null,
);

const nextActor = computed(() => {
    if (props.assessment.latest_payment_schedule) {
        return 'Treasury collection';
    }

    if (props.assessment.decision?.action === 'returned_for_correction') {
        return 'Assessment Officer';
    }

    if (props.assessment.decision?.action === 'approved') {
        return 'Payment scheduling';
    }

    if (awaitingTreasuryCounterCheck.value) {
        return 'Treasury';
    }

    return 'Municipal Treasurer';
});

const returnForm = useForm({
    assessment_snapshot_hash: props.assessment.snapshot_hash,
    reason: '',
});

function submitReturnForCorrection(): void {
    returnForm.post(returnForCorrection(props.assessment.id).url, {
        preserveScroll: true,
    });
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Permit Assessments',
        href: assessmentIndex(),
    },
    {
        title: `Assessment #${props.assessment.sequence}`,
        href: '#',
    },
];

function money(amountCents: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amountCents / 100);
}

function dateTime(value: string | null): string {
    return value
        ? new Intl.DateTimeFormat('en-PH', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : '—';
}

function reconciliationDifference(
    reconciliation: AssessmentReconciliation,
): string {
    if (!reconciliation.comparison) {
        return 'Unavailable';
    }

    if (reconciliation.comparison.direction === 'equal') {
        return 'No difference';
    }

    const side =
        reconciliation.comparison.direction === 'new_bpls_higher'
            ? 'New BPLS higher'
            : 'Legacy source higher';

    return (
        side +
        ' by ' +
        money(reconciliation.comparison.absolute_delta_amount_cents)
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="'Assessment #' + assessment.sequence" />

        <main
            class="flex h-full min-w-0 flex-1 flex-col gap-5 overflow-x-hidden p-3 sm:p-4"
        >
            <header class="grid gap-1">
                <Button as-child variant="ghost" size="sm" class="w-fit px-0">
                    <Link :href="assessmentIndex()">
                        <ArrowLeft />
                        Back
                    </Link>
                </Button>
                <h1 class="text-xl font-semibold text-foreground">
                    Assessment #{{ assessment.sequence }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ assessment.permit_application.business_name }} ·
                    {{ assessment.permit_application.owner_name }}
                </p>
            </header>

            <div
                class="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-start"
            >
                <ComputationAssessmentSlip :slip="computationAssessmentSlip" />

                <aside
                    class="order-last min-w-0 xl:sticky xl:top-4"
                    aria-labelledby="assessment-action-heading"
                    data-testid="assessment-action-rail"
                >
                    <section
                        class="overflow-hidden rounded-2xl border bg-card shadow-xs"
                    >
                        <header class="border-b p-5">
                            <p
                                class="text-xs font-semibold tracking-wide text-primary uppercase"
                            >
                                Assessment status
                            </p>
                            <h2
                                id="assessment-action-heading"
                                class="mt-1 text-xl font-semibold"
                            >
                                {{ assessment.display_status }}
                            </h2>
                        </header>

                        <div class="grid gap-4 p-5">
                            <div
                                class="rounded-xl bg-foreground p-4 text-background"
                                data-testid="assessment-grand-total"
                            >
                                <p
                                    class="text-xs font-medium tracking-wide uppercase opacity-70"
                                >
                                    Grand Total
                                </p>
                                <p
                                    class="mt-1 text-3xl font-semibold tabular-nums"
                                >
                                    {{ money(assessment.total_amount_cents) }}
                                </p>
                            </div>

                            <dl class="grid gap-3 text-sm">
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Next
                                    </dt>
                                    <dd class="font-semibold">
                                        {{ nextActor }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Prepared by
                                    </dt>
                                    <dd class="font-medium">
                                        {{ assessment.assessed_by ?? 'System' }}
                                    </dd>
                                    <dd class="text-xs text-muted-foreground">
                                        {{ dateTime(assessment.assessed_at) }}
                                    </dd>
                                </div>
                            </dl>

                            <div class="grid gap-2">
                                <Button
                                    v-if="can.view_assessment_documents"
                                    as-child
                                    variant="outline"
                                >
                                    <a
                                        :href="assessmentPdf.url(assessment.id)"
                                        target="_blank"
                                    >
                                        <FileText />
                                        View PDF
                                    </a>
                                </Button>

                                <Button
                                    v-if="
                                        awaitingTreasuryCounterCheck &&
                                        assessment.business_permit_evaluation
                                    "
                                    as-child
                                    :variant="
                                        can.counter_check_assessment
                                            ? 'default'
                                            : 'outline'
                                    "
                                >
                                    <Link
                                        :href="
                                            assessment
                                                .business_permit_evaluation
                                                .view_url
                                        "
                                    >
                                        <CheckCircle2
                                            v-if="can.counter_check_assessment"
                                        />
                                        <FileText v-else />
                                        {{
                                            can.counter_check_assessment
                                                ? 'Open Treasury counter-check'
                                                : 'View source Evaluation'
                                        }}
                                    </Link>
                                </Button>

                                <Button
                                    v-if="
                                        assessment.latest_payment_schedule &&
                                        can.view_payment_schedules
                                    "
                                    as-child
                                >
                                    <Link
                                        :href="
                                            paymentScheduleShow(
                                                assessment
                                                    .latest_payment_schedule.id,
                                            )
                                        "
                                    >
                                        <ReceiptText />
                                        View Payment Schedule
                                    </Link>
                                </Button>

                                <Link
                                    v-else-if="
                                        can.prepare_payment_schedule &&
                                        assessment.payment_schedule_available
                                    "
                                    :href="paymentScheduleStore(assessment.id)"
                                    method="post"
                                    as="button"
                                    :class="buttonVariants()"
                                >
                                    <CreditCard />
                                    Prepare Payment Schedule
                                </Link>
                            </div>

                            <div
                                v-if="assessment.decision"
                                class="rounded-lg border p-3 text-sm"
                                :class="
                                    assessment.decision.action === 'approved'
                                        ? 'border-emerald-300 bg-emerald-50 text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-100'
                                        : 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100'
                                "
                                data-testid="assessment-decision"
                                :data-decision-action="
                                    assessment.decision.action
                                "
                                :data-assessment-snapshot-hash="
                                    assessment.decision.assessment_snapshot_hash
                                "
                            >
                                <p class="font-semibold">
                                    {{
                                        assessment.decision.action ===
                                        'approved'
                                            ? 'Approved by Municipal Treasurer'
                                            : 'Returned for correction'
                                    }}
                                </p>
                                <p class="mt-1 text-xs">
                                    {{
                                        assessment.decision.decided_by ??
                                        'Recorded actor'
                                    }}
                                    · {{ assessment.decision.decided_at }}
                                </p>
                                <p
                                    v-if="assessment.decision.reason"
                                    class="mt-2"
                                >
                                    {{ assessment.decision.reason }}
                                </p>
                            </div>

                            <div
                                v-else
                                data-testid="assessment-awaiting-approval"
                            >
                                <div
                                    v-if="can.approve_assessment"
                                    class="grid gap-3 border-t pt-4"
                                >
                                    <Link
                                        :href="approveAssessment(assessment.id)"
                                        method="post"
                                        as="button"
                                        :data="{
                                            assessment_snapshot_hash:
                                                assessment.snapshot_hash,
                                        }"
                                        :class="buttonVariants()"
                                    >
                                        <CheckCircle2 />
                                        Approve for payment
                                    </Link>

                                    <form
                                        class="grid gap-2"
                                        @submit.prevent="
                                            submitReturnForCorrection
                                        "
                                    >
                                        <label
                                            for="return-reason"
                                            class="text-sm font-medium"
                                        >
                                            Correction note
                                        </label>
                                        <textarea
                                            id="return-reason"
                                            v-model="returnForm.reason"
                                            name="reason"
                                            rows="3"
                                            maxlength="1000"
                                            class="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground"
                                            placeholder="What should be corrected?"
                                        />
                                        <p
                                            v-if="returnForm.errors.reason"
                                            class="text-sm text-destructive"
                                        >
                                            {{ returnForm.errors.reason }}
                                        </p>
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            :disabled="returnForm.processing"
                                        >
                                            <RotateCcw />
                                            Return for correction
                                        </Button>
                                    </form>
                                </div>
                                <p
                                    v-if="page.props.errors.assessment_decision"
                                    class="text-sm text-destructive"
                                >
                                    {{ page.props.errors.assessment_decision }}
                                </p>
                            </div>
                        </div>
                    </section>
                </aside>
            </div>

            <details
                class="group overflow-hidden rounded-2xl border bg-card"
                data-testid="assessment-evidence"
            >
                <summary
                    class="flex cursor-pointer list-none items-center justify-between gap-3 p-4 font-semibold outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                >
                    Evidence
                    <ChevronRight
                        class="size-4 transition-transform group-open:rotate-90"
                        aria-hidden="true"
                    />
                </summary>

                <div class="grid gap-5 border-t p-4">
                    <section
                        v-if="assessment.price_report"
                        class="grid gap-3"
                        data-testid="price-report-provenance"
                    >
                        <h2 class="font-semibold">
                            Composition and provenance
                        </h2>
                        <div class="divide-y rounded-xl border">
                            <article
                                v-for="component in assessment.price_report
                                    .components"
                                :key="component.exact_once_key"
                                class="grid gap-2 p-3 sm:grid-cols-[minmax(0,1fr)_auto]"
                            >
                                <div class="min-w-0">
                                    <p class="font-medium">
                                        {{ component.label }}
                                    </p>
                                    <p
                                        class="font-mono text-xs break-all text-muted-foreground"
                                    >
                                        {{ component.source.type }} ·
                                        {{ component.source.version }}
                                    </p>
                                </div>
                                <p class="font-semibold tabular-nums">
                                    {{ money(component.resolved_minor) }}
                                </p>
                            </article>
                        </div>
                        <dl
                            class="grid gap-3 text-xs text-muted-foreground sm:grid-cols-2"
                        >
                            <div>
                                <dt class="font-semibold text-foreground">
                                    Input fingerprint
                                </dt>
                                <dd class="mt-1 font-mono break-all">
                                    {{
                                        assessment.assessment_price_input_fingerprint ??
                                        '—'
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-foreground">
                                    Report fingerprint
                                </dt>
                                <dd class="mt-1 font-mono break-all">
                                    {{
                                        assessment.price_report_fingerprint ??
                                        '—'
                                    }}
                                </dd>
                            </div>
                        </dl>

                        <details class="rounded-lg border">
                            <summary
                                class="cursor-pointer p-3 text-sm font-medium"
                            >
                                Technical line details
                            </summary>
                            <div class="divide-y border-t">
                                <article
                                    v-for="line in assessment.lines"
                                    :key="line.id"
                                    class="grid gap-2 p-3 text-sm sm:grid-cols-[minmax(0,1fr)_auto]"
                                >
                                    <div class="min-w-0">
                                        <p class="font-medium">
                                            {{ line.name }}
                                        </p>
                                        <p
                                            class="font-mono text-xs break-all text-muted-foreground"
                                        >
                                            {{ line.code }} ·
                                            {{ line.category }} ·
                                            {{
                                                line.basis.replaceAll('_', ' ')
                                            }}
                                        </p>
                                    </div>
                                    <p class="font-semibold tabular-nums">
                                        {{ money(line.amount_cents) }}
                                    </p>
                                </article>
                            </div>
                        </details>

                        <div
                            v-for="modifier in assessment.price_report
                                .modifiers"
                            :key="modifier.key"
                            class="rounded-lg border p-3 text-sm"
                        >
                            <p class="font-semibold">
                                Case override ·
                                {{ money(modifier.amount_minor) }}
                            </p>
                            <p>{{ modifier.reason }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ modifier.authority }} · {{ modifier.office }}
                            </p>
                        </div>
                    </section>

                    <section
                        v-if="assessmentReconciliation"
                        class="grid gap-3 border-t pt-5"
                        data-testid="assessment-reconciliation"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <Scale class="size-4" aria-hidden="true" />
                            <h2 class="font-semibold">Legacy comparison</h2>
                            <Badge
                                variant="outline"
                                data-testid="assessment-reconciliation-result"
                            >
                                {{
                                    assessmentReconciliation.status ===
                                    'exact_match'
                                        ? 'Exact total match'
                                        : assessmentReconciliation.status ===
                                            'difference'
                                          ? 'Difference found'
                                          : 'Source evidence invalid'
                                }}
                            </Badge>
                        </div>

                        <div
                            v-if="
                                assessmentReconciliation.comparable &&
                                assessmentReconciliation.source &&
                                assessmentReconciliation.computed &&
                                assessmentReconciliation.comparison
                            "
                            class="grid gap-3 sm:grid-cols-3"
                        >
                            <div class="rounded-lg bg-muted/40 p-3">
                                <p class="text-xs text-muted-foreground">
                                    Legacy total
                                </p>
                                <p
                                    class="mt-1 font-semibold tabular-nums"
                                    data-testid="assessment-reconciliation-source-total"
                                >
                                    {{
                                        money(
                                            assessmentReconciliation.source
                                                .total_amount_cents,
                                        )
                                    }}
                                </p>
                            </div>
                            <div class="rounded-lg bg-muted/40 p-3">
                                <p class="text-xs text-muted-foreground">
                                    BPLS total
                                </p>
                                <p
                                    class="mt-1 font-semibold tabular-nums"
                                    data-testid="assessment-reconciliation-computed-total"
                                >
                                    {{
                                        money(
                                            assessmentReconciliation.computed
                                                .total_amount_cents,
                                        )
                                    }}
                                </p>
                            </div>
                            <div class="rounded-lg bg-muted/40 p-3">
                                <p class="text-xs text-muted-foreground">
                                    Difference
                                </p>
                                <p
                                    class="mt-1 font-semibold"
                                    data-testid="assessment-reconciliation-delta"
                                >
                                    {{
                                        reconciliationDifference(
                                            assessmentReconciliation,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                        <details
                            v-if="
                                assessmentReconciliation.comparable &&
                                assessmentReconciliation.source &&
                                assessmentReconciliation.computed
                            "
                            class="rounded-lg border"
                        >
                            <summary
                                class="cursor-pointer p-3 text-sm font-medium"
                            >
                                Legacy source components
                            </summary>
                            <div class="divide-y border-t">
                                <template
                                    v-for="schedule in assessmentReconciliation
                                        .source.schedules"
                                    :key="schedule.section"
                                >
                                    <article
                                        v-for="(fee, index) in schedule.fees"
                                        :key="
                                            schedule.section +
                                            '-' +
                                            fee.name +
                                            '-' +
                                            index
                                        "
                                        class="grid gap-2 p-3 text-sm sm:grid-cols-[minmax(0,1fr)_auto]"
                                    >
                                        <div>
                                            <p class="font-medium">
                                                {{ fee.name }}
                                            </p>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ fee.category }} · Section
                                                {{ schedule.section }}
                                            </p>
                                        </div>
                                        <p class="font-semibold tabular-nums">
                                            {{ money(fee.amount_cents) }}
                                        </p>
                                    </article>
                                </template>
                            </div>
                            <dl
                                class="grid gap-3 border-t p-3 text-xs text-muted-foreground sm:grid-cols-2"
                            >
                                <div>
                                    <dt class="font-semibold text-foreground">
                                        Source evidence hash
                                    </dt>
                                    <dd class="mt-1 font-mono break-all">
                                        {{
                                            assessmentReconciliation.source
                                                .evidence_hash
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-foreground">
                                        BPLS snapshot hash
                                    </dt>
                                    <dd class="mt-1 font-mono break-all">
                                        {{
                                            assessmentReconciliation.computed
                                                .snapshot_hash
                                        }}
                                    </dd>
                                </div>
                            </dl>
                        </details>
                        <p
                            v-else
                            class="text-sm text-destructive"
                            data-testid="assessment-reconciliation-invalid-source"
                        >
                            {{ assessmentReconciliation.statement }}
                        </p>
                    </section>

                    <section
                        v-if="assessment.business_permit_evaluation"
                        class="grid gap-3 border-t pt-5"
                        data-testid="assessment-evaluation-trace"
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <h2 class="font-semibold">Source Evaluation</h2>
                                <p class="text-sm text-muted-foreground">
                                    Version
                                    {{
                                        assessment.business_permit_evaluation
                                            .version_sequence
                                    }}
                                </p>
                            </div>
                            <Button as-child variant="outline" size="sm">
                                <Link
                                    :href="
                                        assessment.business_permit_evaluation
                                            .view_url
                                    "
                                >
                                    <FileText />
                                    Inspect Evaluation
                                </Link>
                            </Button>
                        </div>
                        <p
                            class="font-mono text-xs break-all text-muted-foreground"
                        >
                            {{
                                assessment.business_permit_evaluation
                                    .fingerprint
                            }}
                        </p>
                    </section>

                    <section
                        v-if="assessment.treasury_counter_check"
                        class="grid gap-1 border-t pt-5 text-sm"
                        data-testid="assessment-treasury-counter-check"
                    >
                        <h2 class="font-semibold">Treasury counter-check</h2>
                        <p>
                            {{
                                assessment.treasury_counter_check.result ===
                                'no_correction'
                                    ? 'No correction'
                                    : 'Material correction'
                            }}
                            ·
                            {{ assessment.treasury_counter_check.checked_by }} ·
                            {{ assessment.treasury_counter_check.checked_at }}
                        </p>
                        <p
                            v-if="assessment.treasury_counter_check.reason"
                            class="text-muted-foreground"
                        >
                            {{ assessment.treasury_counter_check.reason }}
                        </p>
                    </section>

                    <section class="grid gap-2 border-t pt-5">
                        <h2 class="font-semibold">Document boundaries</h2>
                        <ul
                            class="list-disc space-y-1 pl-5 text-sm text-muted-foreground"
                        >
                            <li
                                v-for="gap in assessmentDocumentGaps"
                                :key="gap"
                            >
                                {{ gap }}
                            </li>
                        </ul>
                    </section>
                </div>
            </details>
        </main>
    </AppLayout>
</template>
