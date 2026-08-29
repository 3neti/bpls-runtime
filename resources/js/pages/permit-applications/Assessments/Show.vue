<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    CreditCard,
    FileText,
    ReceiptText,
    RotateCcw,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import WorkflowSectionHeader from '@/components/workflow/WorkflowSectionHeader.vue';
import WorkflowStageSummary from '@/components/workflow/WorkflowStageSummary.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
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

type Assessment = {
    id: number;
    sequence: number;
    status: string;
    assessed_at: string | null;
    assessed_by: string | null;
    total_amount_cents: number;
    snapshot_hash: string;
    business_permit_evaluation: {
        evaluation_id: number;
        version_id: number;
        version_sequence: number;
        fingerprint: string;
        view_url: string;
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

const props = defineProps<{
    assessment: Assessment;
    can: {
        prepare_payment_schedule: boolean;
        approve_assessment: boolean;
        view_payment_schedules: boolean;
        view_assessment_documents: boolean;
    };
    assessmentDocumentGaps: string[];
}>();

const page = usePage();

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
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Assessment #${assessment.sequence}`" />

        <main class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex flex-col gap-1">
                    <Button
                        as-child
                        variant="ghost"
                        size="sm"
                        class="w-fit px-0"
                    >
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
                </div>
            </div>

            <WorkflowStageSummary
                eyebrow="Recorded assessment"
                :title="money(assessment.total_amount_cents)"
                description="This total and every line below are the recorded assessment reviewed for payment. This page does not recalculate liability."
                :items="[
                    {
                        label: 'Assessment status',
                        value: assessment.status.replaceAll('_', ' '),
                    },
                    {
                        label: 'Application status',
                        value: assessment.permit_application.status.replaceAll(
                            '_',
                            ' ',
                        ),
                    },
                    {
                        label: 'Prepared by Assessment Officer',
                        value: assessment.assessed_by ?? 'System',
                        detail: assessment.assessed_at ?? 'No timestamp',
                    },
                    {
                        label: 'Current task',
                        value: assessment.latest_payment_schedule
                            ? 'Review payment schedule'
                            : assessment.decision?.action ===
                                'returned_for_correction'
                              ? 'Prepare corrected assessment'
                              : !assessment.decision
                                ? 'Municipal Treasurer approval'
                                : !assessment.payment_schedule_available
                                  ? 'Approval no longer matches snapshot'
                                  : can.prepare_payment_schedule
                                    ? 'Prepare payment schedule'
                                    : 'Review assessment evidence',
                    },
                ]"
            >
                <template #actions>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-if="can.view_assessment_documents"
                            as-child
                            variant="outline"
                            size="sm"
                        >
                            <a
                                :href="assessmentPdf.url(assessment.id)"
                                target="_blank"
                            >
                                <FileText />
                                PDF
                            </a>
                        </Button>
                        <Button
                            v-if="
                                assessment.latest_payment_schedule &&
                                can.view_payment_schedules
                            "
                            as-child
                            variant="outline"
                            size="sm"
                        >
                            <Link
                                :href="
                                    paymentScheduleShow(
                                        assessment.latest_payment_schedule.id,
                                    )
                                "
                            >
                                <ReceiptText />
                                Payment Schedule
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
                            :class="buttonVariants({ size: 'sm' })"
                        >
                            <CreditCard />
                            Prepare Payment
                        </Link>
                    </div>
                </template>
            </WorkflowStageSummary>

            <section
                v-if="assessment.business_permit_evaluation"
                class="rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-xs dark:border-sidebar-border"
                data-testid="assessment-evaluation-trace"
                aria-labelledby="assessment-evaluation-trace-title"
            >
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="min-w-0">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Evaluation traceability
                        </p>
                        <h2
                            id="assessment-evaluation-trace-title"
                            class="mt-1 font-semibold"
                        >
                            Exact Evaluation version consumed
                        </h2>
                        <p
                            class="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground"
                        >
                            This immutable Assessment was prepared from Business
                            Permit Evaluation version
                            {{
                                assessment.business_permit_evaluation
                                    .version_sequence
                            }}. Later Evaluation changes do not alter this
                            Assessment.
                        </p>
                    </div>
                    <Button as-child variant="outline" size="sm">
                        <Link
                            :href="
                                assessment.business_permit_evaluation.view_url
                            "
                        >
                            <FileText aria-hidden="true" />
                            Inspect Evaluation
                        </Link>
                    </Button>
                </div>
                <details class="group mt-4 border-t pt-4">
                    <summary
                        class="cursor-pointer text-sm font-medium outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                    >
                        Exact audit reference
                    </summary>
                    <p
                        class="mt-2 font-mono text-xs break-all text-muted-foreground"
                    >
                        {{ assessment.business_permit_evaluation.fingerprint }}
                    </p>
                </details>
            </section>

            <section
                v-if="assessment.decision"
                class="rounded-lg border p-4"
                :class="
                    assessment.decision.action === 'approved'
                        ? 'border-emerald-300 bg-emerald-50 text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-100'
                        : 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100'
                "
                data-testid="assessment-decision"
                :data-decision-action="assessment.decision.action"
                :data-assessment-snapshot-hash="
                    assessment.decision.assessment_snapshot_hash
                "
            >
                <div class="flex items-start gap-3">
                    <CheckCircle2
                        v-if="assessment.decision.action === 'approved'"
                        class="mt-0.5 size-5 shrink-0"
                    />
                    <RotateCcw v-else class="mt-0.5 size-5 shrink-0" />
                    <div class="grid gap-1">
                        <h2 class="font-semibold">
                            {{
                                assessment.decision.action === 'approved'
                                    ? 'Approved by Municipal Treasurer'
                                    : 'Returned for correction'
                            }}
                        </h2>
                        <p class="text-sm">
                            {{
                                assessment.decision.decided_by ??
                                'Recorded actor'
                            }}
                            · {{ assessment.decision.decided_at }}
                        </p>
                        <p v-if="assessment.decision.reason" class="text-sm">
                            {{ assessment.decision.reason }}
                        </p>
                        <p class="text-xs opacity-80">
                            Approved amount ·
                            {{ money(assessment.decision.total_amount_cents) }}
                        </p>
                    </div>
                </div>
            </section>

            <section
                v-else
                class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
                data-testid="assessment-awaiting-approval"
            >
                <WorkflowSectionHeader
                    eyebrow="Treasurer review"
                    title="Awaiting Municipal Treasurer approval"
                    description="The Assessment Officer prepared this recorded amount. Payment remains unavailable until the Municipal Treasurer approves this exact version."
                />

                <div v-if="can.approve_assessment" class="mt-4 grid gap-3">
                    <div class="flex flex-wrap gap-2">
                        <Link
                            :href="approveAssessment(assessment.id)"
                            method="post"
                            as="button"
                            :data="{
                                assessment_snapshot_hash:
                                    assessment.snapshot_hash,
                            }"
                            :class="buttonVariants({ size: 'sm' })"
                        >
                            <CheckCircle2 />
                            Approve amount for payment
                        </Link>
                    </div>

                    <form
                        class="grid max-w-xl gap-2"
                        @submit.prevent="submitReturnForCorrection"
                    >
                        <label for="return-reason" class="text-sm font-medium">
                            Correction note (optional)
                        </label>
                        <textarea
                            id="return-reason"
                            v-model="returnForm.reason"
                            name="reason"
                            rows="3"
                            maxlength="1000"
                            class="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground"
                            placeholder="Describe what the Assessment Officer should correct."
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
                            size="sm"
                            class="w-fit"
                            :disabled="returnForm.processing"
                        >
                            <RotateCcw />
                            Return for correction
                        </Button>
                    </form>
                    <p
                        v-if="page.props.errors.assessment_decision"
                        class="text-sm text-destructive"
                    >
                        {{ page.props.errors.assessment_decision }}
                    </p>
                </div>
            </section>

            <section class="grid gap-3 md:grid-cols-3">
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Application
                    </div>
                    <div class="mt-1 font-medium">
                        {{
                            assessment.permit_application.application_number ??
                            `Application #${assessment.permit_application.id}`
                        }}
                    </div>
                    <div class="text-sm text-muted-foreground capitalize">
                        {{ assessment.permit_application.type }} ·
                        {{ assessment.permit_application.application_year }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Status
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <Badge variant="secondary" class="capitalize">{{
                            assessment.status
                        }}</Badge>
                        <Badge variant="outline" class="capitalize">
                            {{
                                assessment.permit_application.status.replace(
                                    '_',
                                    ' ',
                                )
                            }}
                        </Badge>
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Assessed by
                    </div>
                    <div class="mt-1 font-medium">
                        {{ assessment.assessed_by ?? 'System' }}
                    </div>
                    <div class="text-sm text-muted-foreground">
                        {{ assessment.assessed_at ?? 'No timestamp' }}
                    </div>
                </div>
            </section>

            <section
                class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background dark:border-sidebar-border"
            >
                <div
                    class="border-b border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <WorkflowSectionHeader
                        eyebrow="Supporting evidence"
                        title="Assessment lines"
                        description="Amounts, bases, and classifications are displayed exactly as recorded for this assessment."
                    />
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px] text-sm">
                        <thead
                            class="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3 font-medium">Code</th>
                                <th class="px-4 py-3 font-medium">Fee / Tax</th>
                                <th class="px-4 py-3 font-medium">Category</th>
                                <th class="px-4 py-3 font-medium">Basis</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Basis amount
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Amount
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="line in assessment.lines"
                                :key="line.id"
                                class="border-b last:border-b-0"
                                data-testid="assessment-line"
                                :data-line-code="line.code"
                            >
                                <td
                                    class="px-4 py-3 align-top font-mono text-xs"
                                >
                                    {{ line.code }}
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="font-medium">
                                        {{ line.name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{
                                            line.line_of_business ??
                                            'Application-wide'
                                        }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top capitalize">
                                    {{ line.category }}
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="capitalize">
                                        {{ line.calculation_type }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ line.basis.replaceAll('_', ' ') }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right align-top">
                                    {{ money(line.basis_amount_cents) }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right align-top font-medium"
                                >
                                    {{ money(line.amount_cents) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
            >
                <WorkflowSectionHeader
                    eyebrow="Still to confirm"
                    title="Assessment document questions"
                    description="These document and policy details have not yet been confirmed for this assessment."
                />
                <ul
                    class="list-disc space-y-1 pl-5 text-sm text-muted-foreground"
                >
                    <li v-for="gap in assessmentDocumentGaps" :key="gap">
                        {{ gap }}
                    </li>
                </ul>
            </section>
        </main>
    </AppLayout>
</template>
