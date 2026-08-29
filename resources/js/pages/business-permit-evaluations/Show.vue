<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    BriefcaseBusiness,
    Check,
    CheckCircle2,
    ChevronRight,
    CircleDashed,
    ClipboardCheck,
    FileClock,
    FileSearch,
    History,
    Landmark,
    LockKeyhole,
    PhilippinePeso,
    RefreshCw,
    Scale,
    ShieldCheck,
    UserRound,
} from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { correctLinesOfBusiness as correctCitizenLinesOfBusiness } from '@/actions/App/Http/Controllers/Citizen/BusinessPermitEvaluationController';
import {
    confirmResponsibility,
    correctLinesOfBusiness as correctStaffLinesOfBusiness,
    counterCheck,
    initialize,
    refresh,
} from '@/actions/App/Http/Controllers/Staff/BusinessPermitEvaluationController';
import { show as showFeeRule } from '@/actions/App/Http/Controllers/Staff/FeeRuleController';
import {
    show as showAssessment,
    store as prepareAssessment,
} from '@/actions/App/Http/Controllers/Staff/PermitApplicationAssessmentController';
import EvaluationItemCard from '@/components/evaluations/EvaluationItemCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    applicationTypeLabel,
    officeLabel,
    pricingBasisSummary,
    readinessBlockers,
} from '@/lib/evaluationPresentation';
import type {
    BreadcrumbItem,
    BusinessPermitEvaluationData,
    EvaluationApplicability,
    EvaluationCapabilities,
    EvaluationItem,
    EvaluationLineOfBusinessOption,
    EvaluationProjectedCharge,
} from '@/types';

type Evaluation = BusinessPermitEvaluationData;
type LineOfBusiness = EvaluationLineOfBusinessOption;

const props = defineProps<{
    evaluation: Evaluation | null;
    application: { id: number; application_number: string | null };
    lineOfBusinesses: LineOfBusiness[];
    can: EvaluationCapabilities;
}>();

const pendingAction = ref<string | null>(null);

const page = usePage();
const evaluationError = computed(() =>
    typeof page.props.errors?.evaluation === 'string'
        ? page.props.errors.evaluation
        : undefined,
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Permit applications', href: '#' },
    { title: 'Business Permit Evaluator', href: '#' },
];

const selectedLineIds = reactive<number[]>(
    props.evaluation?.lens === 'internal'
        ? (props.evaluation.municipal_resolved_lines.map((line) => line.id) ??
              [])
        : (props.evaluation?.applicant_declaration.map(
              (line) => line.line_of_business_id,
          ) ?? []),
);
const lineCorrectionReason = reactive({ value: '' });
const counterCheckReason = reactive({ value: '' });

type ItemDraft = {
    applicability: EvaluationApplicability;
    amount: string;
    reason: string;
    inspectionMode: '' | 'physical' | 'virtual' | 'document_review';
    inspectionCompleted: boolean;
    findings: string;
};

const orderedItems = computed(() =>
    [...(props.evaluation?.items ?? [])].sort(
        (left, right) => Number(right.is_mine) - Number(left.is_mine),
    ),
);
const myItems = computed(() =>
    orderedItems.value.filter((item) => item.is_mine),
);
const otherItems = computed(() =>
    orderedItems.value.filter((item) => !item.is_mine),
);
const canViewFeeRules = computed(
    () =>
        Boolean(
            (page.props.auth as { can_view_fee_rules?: boolean } | undefined)
                ?.can_view_fee_rules,
        ) && props.evaluation?.lens === 'internal',
);
const currentAssessmentExists = computed(
    () =>
        props.evaluation?.latest_assessment !== null &&
        props.evaluation?.latest_assessment.superseded === false &&
        props.evaluation?.latest_assessment.consumes_current_evaluation ===
            true,
);
/**
 * Readiness stays canonical: `ready` and the issue list come straight from
 * `BusinessPermitEvaluationReadiness`. Only the wording is municipal —
 * `readinessBlockers()` rewrites each canonical issue 1:1 so no internal
 * item key or status token reaches a stakeholder.
 */
const readiness = computed(() => {
    const evaluation = props.evaluation;

    if (!evaluation) {
        return null;
    }

    const blockersFor = (issues: string[]): string[] =>
        readinessBlockers(
            issues,
            evaluation.items,
            evaluation.projected_charges,
        );

    if (evaluation.readiness.commissioned.ready) {
        return {
            ready: true,
            label: 'Ready for Assessment',
            note: 'All commissioned readiness checks are satisfied.',
            blockers: [] as string[],
        };
    }

    if (evaluation.readiness.provisional_uat.ready) {
        return {
            ready: true,
            label: 'Ready for Assessment — UAT only',
            note: 'This sample uses provisional UAT evidence and does not establish production liability.',
            blockers: blockersFor(evaluation.readiness.commissioned.issues),
        };
    }

    return {
        ready: false,
        label: 'Not ready for assessment',
        note: 'Resolve the responsibilities and pricing issues below before preparing an Assessment.',
        blockers: blockersFor(evaluation.readiness.provisional_uat.issues),
    };
});
const statusTone = computed(() => {
    const status = props.evaluation?.status_label ?? '';

    if (status === 'Ready for Assessment' || status === 'Assessment Prepared') {
        return 'border-emerald-300 bg-emerald-50 text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-100';
    }

    if (status.includes('Locked')) {
        return 'border-slate-300 bg-slate-50 text-slate-950 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100';
    }

    return 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100';
});

function money(amountCents: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amountCents / 100);
}

function dateTime(value: string | null): string {
    if (!value) {
        return 'Not recorded';
    }

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

/**
 * A fee rule with no configured basis has no basis amount to show. Its
 * structural zero is never paired with the basis label, while the charge's
 * own resolved amount is always rendered — an accepted ₱0.00 stays real.
 */
function pricingBasis(charge: EvaluationProjectedCharge): string {
    const summary = pricingBasisSummary(charge);

    if (summary.amountCents === null) {
        return summary.label;
    }

    return `${summary.label} · ${money(summary.amountCents)}`;
}

function toggleLine(id: number): void {
    const index = selectedLineIds.indexOf(id);

    if (index === -1) {
        selectedLineIds.push(id);
    } else {
        selectedLineIds.splice(index, 1);
    }
}

/**
 * A single in-flight-mutation guard shared by every action on this page.
 * Buttons disable themselves while their own key is pending, which also
 * prevents accidentally firing a second mutation (e.g. a counter-check
 * and a line correction) against the same Evaluation version at once.
 */
function runOnce(key: string, action: () => void): void {
    if (pendingAction.value !== null) {
        return;
    }

    pendingAction.value = key;
    action();
}

function submitLineCorrection(): void {
    if (
        !props.evaluation ||
        !window.confirm(
            'Record this correction and re-evaluate affected responsibilities?',
        )
    ) {
        return;
    }

    runOnce('line-correction', () => {
        const form = useForm({
            line_of_business_ids: [...selectedLineIds],
            reason: lineCorrectionReason.value,
            expected_version_sequence: props.evaluation!.version.sequence,
            expected_fingerprint: props.evaluation!.version.fingerprint,
            idempotency_key: crypto.randomUUID(),
        });
        const action =
            props.evaluation!.lens === 'citizen'
                ? correctCitizenLinesOfBusiness(props.application.id)
                : correctStaffLinesOfBusiness(props.application.id);
        form.post(action.url, {
            preserveScroll: true,
            onFinish: () => {
                pendingAction.value = null;
            },
        });
    });
}

function submitResponsibility(item: EvaluationItem, draft: ItemDraft): void {
    if (!props.evaluation) {
        return;
    }

    if (
        item.item_type === 'charge' &&
        draft.applicability === 'applicable' &&
        draft.amount.trim() === ''
    ) {
        return;
    }

    if (
        !window.confirm(
            `Record the ${item.responsible_party} determination for ${item.label}?`,
        )
    ) {
        return;
    }

    runOnce(`item-${item.id}`, () => {
        const amountCents =
            draft.amount.trim() === ''
                ? null
                : Math.round(Number(draft.amount) * 100);
        const form = useForm({
            expected_version_sequence: props.evaluation!.version.sequence,
            expected_fingerprint: props.evaluation!.version.fingerprint,
            idempotency_key: crypto.randomUUID(),
            applicability: draft.applicability,
            amount_cents: amountCents,
            reason: draft.reason || null,
            inspection_mode: draft.inspectionMode || null,
            inspection_completed: draft.inspectionCompleted,
            findings: draft.findings || null,
        });
        form.post(confirmResponsibility([props.application.id, item.id]).url, {
            preserveScroll: true,
            onFinish: () => {
                pendingAction.value = null;
            },
        });
    });
}

function submitCounterCheck(): void {
    if (
        !props.evaluation ||
        !window.confirm(
            'Confirm Treasury counter-check for this exact evaluation version?',
        )
    ) {
        return;
    }

    runOnce('counter-check', () => {
        useForm({
            reason: counterCheckReason.value || null,
            expected_version_sequence: props.evaluation!.version.sequence,
            expected_fingerprint: props.evaluation!.version.fingerprint,
        }).post(counterCheck(props.application.id).url, {
            preserveScroll: true,
            onFinish: () => {
                pendingAction.value = null;
            },
        });
    });
}

function submitRefresh(): void {
    if (!props.evaluation) {
        return;
    }

    runOnce('refresh', () => {
        useForm({
            expected_version_sequence: props.evaluation!.version.sequence,
            expected_fingerprint: props.evaluation!.version.fingerprint,
        }).post(refresh(props.application.id).url, {
            preserveScroll: true,
            onFinish: () => {
                pendingAction.value = null;
            },
        });
    });
}

function submitPrepareAssessment(): void {
    if (
        !props.evaluation ||
        !window.confirm(
            'Prepare an immutable Assessment from this exact resolved evaluation?',
        )
    ) {
        return;
    }

    runOnce('prepare-assessment', () => {
        useForm({
            evaluation_version_id: props.evaluation!.version.id,
            evaluation_fingerprint: props.evaluation!.version.fingerprint,
            idempotency_key: crypto.randomUUID(),
        }).post(prepareAssessment(props.application.id).url, {
            preserveScroll: true,
            onFinish: () => {
                pendingAction.value = null;
            },
        });
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Business Permit Evaluator" />

        <main class="flex min-w-0 flex-1 flex-col gap-5 p-4 sm:p-6 lg:p-8">
            <header
                class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="min-w-0 space-y-1">
                    <p
                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Municipal evaluation working paper
                    </p>
                    <h1 class="text-2xl font-semibold tracking-tight">
                        Business Permit Evaluator
                    </h1>
                    <p
                        class="max-w-3xl text-sm leading-6 text-muted-foreground"
                    >
                        One shared view of the applicant declaration, governed
                        proposals, office determinations, and the exact state
                        that can enter Assessment.
                    </p>
                </div>
                <Badge
                    v-if="evaluation"
                    variant="outline"
                    class="self-start px-3 py-1.5 text-sm"
                >
                    {{ evaluation.status_label }}
                </Badge>
            </header>

            <div
                v-if="evaluationError"
                role="alert"
                class="flex items-start gap-3 rounded-xl border border-destructive/40 bg-destructive/5 p-4 text-sm text-destructive"
            >
                <AlertTriangle
                    class="mt-0.5 size-5 shrink-0"
                    aria-hidden="true"
                />
                <div>
                    <p class="font-semibold">
                        The evaluation changed or could not be updated.
                    </p>
                    <p class="mt-1">
                        {{ evaluationError }} Review the latest version before
                        trying again.
                    </p>
                </div>
            </div>

            <section
                v-if="!evaluation"
                class="rounded-2xl border bg-card p-6 shadow-xs"
                aria-labelledby="evaluation-empty-title"
            >
                <div class="max-w-2xl space-y-3">
                    <div
                        class="flex size-10 items-center justify-center rounded-full bg-muted"
                    >
                        <FileSearch class="size-5" aria-hidden="true" />
                    </div>
                    <h2
                        id="evaluation-empty-title"
                        class="text-lg font-semibold"
                    >
                        No Evaluation history yet
                    </h2>
                    <p class="text-sm leading-6 text-muted-foreground">
                        This application predates, or has not yet entered, the
                        Business Permit Evaluator. Existing Assessment and
                        payment records remain authoritative; no synthetic
                        history is created.
                    </p>
                    <Link
                        v-if="can.initialize"
                        :href="initialize(application.id)"
                        method="post"
                        as="button"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground outline-none hover:bg-primary/90 focus-visible:ring-3 focus-visible:ring-ring/50"
                    >
                        <ClipboardCheck class="size-4" aria-hidden="true" />
                        Start Evaluation
                    </Link>
                </div>
            </section>

            <template v-else>
                <section
                    class="overflow-hidden rounded-2xl border bg-card shadow-xs"
                    aria-label="Application evaluation summary"
                >
                    <div
                        class="grid gap-5 p-5 sm:p-6 lg:grid-cols-[minmax(0,1fr)_auto]"
                    >
                        <div class="min-w-0">
                            <div class="flex items-start gap-3">
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                                >
                                    <BriefcaseBusiness
                                        class="size-5"
                                        aria-hidden="true"
                                    />
                                </div>
                                <div class="min-w-0">
                                    <h2
                                        class="text-xl font-semibold break-words"
                                    >
                                        {{
                                            evaluation.application.business_name
                                        }}
                                    </h2>
                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        {{ evaluation.application.owner_name }}
                                        ·
                                        {{
                                            applicationTypeLabel(
                                                evaluation.application.type,
                                            )
                                        }}
                                        · {{ evaluation.application.year }}
                                    </p>
                                    <p
                                        class="mt-2 text-xs text-muted-foreground"
                                    >
                                        <template
                                            v-if="
                                                evaluation.application
                                                    .application_number
                                            "
                                        >
                                            Application
                                            {{
                                                evaluation.application
                                                    .application_number
                                            }}
                                        </template>
                                        <template
                                            v-else-if="
                                                evaluation.application
                                                    .tracking_reference
                                            "
                                        >
                                            Tracking reference
                                            {{
                                                evaluation.application
                                                    .tracking_reference
                                            }}
                                        </template>
                                        <template v-else
                                            >Application record #{{
                                                evaluation.application.id
                                            }}</template
                                        >
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div
                            class="rounded-xl bg-primary px-5 py-4 text-primary-foreground lg:min-w-64"
                        >
                            <p class="text-xs font-medium opacity-80">
                                Current evaluated amount
                            </p>
                            <p class="mt-1 text-2xl font-semibold tabular-nums">
                                {{
                                    money(
                                        evaluation.current_evaluated_amount_cents,
                                    )
                                }}
                            </p>
                            <p class="mt-2 text-xs leading-5 opacity-80">
                                Derived from resolved charge items. The total is
                                never directly editable.
                            </p>
                        </div>
                    </div>
                    <div class="grid border-t bg-muted/20 sm:grid-cols-3">
                        <div class="border-b p-4 sm:border-r sm:border-b-0">
                            <p class="text-xs text-muted-foreground">
                                Office work
                            </p>
                            <p class="mt-1 font-semibold">
                                {{
                                    evaluation.items.filter(
                                        (item) =>
                                            item.resolution === 'resolved',
                                    ).length
                                }}
                                of {{ evaluation.items.length }} complete
                            </p>
                        </div>
                        <div class="border-b p-4 sm:border-r sm:border-b-0">
                            <p class="text-xs text-muted-foreground">
                                Treasury counter-check
                            </p>
                            <p class="mt-1 font-semibold">
                                {{
                                    evaluation.version.treasury_counter_check
                                        ? 'Complete'
                                        : 'Awaiting Treasury Review'
                                }}
                            </p>
                        </div>
                        <div class="p-4">
                            <p class="text-xs text-muted-foreground">
                                Assessment
                            </p>
                            <p class="mt-1 font-semibold">
                                {{
                                    evaluation.latest_assessment
                                        ? evaluation.latest_assessment
                                              .superseded
                                            ? 'Previous Assessment superseded'
                                            : 'Assessment Prepared'
                                        : readiness?.label
                                }}
                            </p>
                        </div>
                    </div>
                </section>

                <section
                    :class="['rounded-xl border p-4', statusTone]"
                    aria-labelledby="next-action-title"
                >
                    <div class="flex items-start gap-3">
                        <LockKeyhole
                            v-if="evaluation.financial_lock"
                            class="mt-0.5 size-5 shrink-0"
                            aria-hidden="true"
                        />
                        <CheckCircle2
                            v-else-if="readiness?.ready"
                            class="mt-0.5 size-5 shrink-0"
                            aria-hidden="true"
                        />
                        <CircleDashed
                            v-else
                            class="mt-0.5 size-5 shrink-0"
                            aria-hidden="true"
                        />
                        <div class="min-w-0 flex-1">
                            <h2 id="next-action-title" class="font-semibold">
                                {{
                                    evaluation.financial_lock
                                        ? 'Payment locked after Assessment'
                                        : readiness?.label
                                }}
                            </h2>
                            <p class="mt-1 text-sm leading-6">
                                {{
                                    evaluation.financial_lock
                                        ? 'A Payment Schedule exists. Evaluation is read-only and cannot rewrite financial liability.'
                                        : readiness?.note
                                }}
                            </p>
                            <ul
                                v-if="
                                    !evaluation.financial_lock &&
                                    readiness?.blockers.length
                                "
                                class="mt-2 grid gap-1 text-sm"
                            >
                                <li
                                    v-for="blocker in readiness.blockers"
                                    :key="blocker"
                                    class="flex items-start gap-2"
                                >
                                    <ChevronRight
                                        class="mt-0.5 size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <span>{{ blocker }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </section>

                <div
                    class="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1.45fr)_minmax(20rem,0.75fr)]"
                >
                    <div class="min-w-0 space-y-5">
                        <section
                            class="rounded-2xl border bg-card p-5 shadow-xs sm:p-6"
                            aria-labelledby="declaration-heading"
                        >
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div>
                                    <p
                                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                    >
                                        Business activities
                                    </p>
                                    <h2
                                        id="declaration-heading"
                                        class="mt-1 text-lg font-semibold"
                                    >
                                        Applicant declaration and municipal
                                        determination
                                    </h2>
                                    <p
                                        class="mt-1 text-sm leading-6 text-muted-foreground"
                                    >
                                        The original declaration remains visible
                                        when the Municipality records a
                                        correction or additional activity.
                                    </p>
                                </div>
                                <Badge
                                    v-if="
                                        can.correct_lines_of_business &&
                                        !evaluation.financial_lock
                                    "
                                    variant="outline"
                                    >{{
                                        evaluation.lens === 'citizen'
                                            ? 'Your declaration'
                                            : 'Treasury correction control'
                                    }}</Badge
                                >
                            </div>

                            <div class="mt-5 grid gap-4 md:grid-cols-2">
                                <div class="rounded-xl border bg-muted/20 p-4">
                                    <div class="flex items-center gap-2">
                                        <UserRound
                                            class="size-4 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <h3 class="font-semibold">
                                            Applicant declared
                                        </h3>
                                    </div>
                                    <ul class="mt-3 grid gap-3">
                                        <li
                                            v-for="line in evaluation.applicant_declaration"
                                            :key="line.line_of_business_id"
                                            class="rounded-lg bg-background p-3"
                                        >
                                            <p class="font-medium">
                                                {{
                                                    line.line_of_business_name ??
                                                    `Activity #${line.line_of_business_id}`
                                                }}
                                            </p>
                                            <p
                                                v-if="
                                                    line.declared_gross_sales_cents !==
                                                        undefined &&
                                                    line.declared_gross_sales_cents !==
                                                        null
                                                "
                                                class="mt-1 text-xs text-muted-foreground"
                                            >
                                                Declared gross sales ·
                                                {{
                                                    money(
                                                        line.declared_gross_sales_cents,
                                                    )
                                                }}
                                            </p>
                                            <p
                                                v-if="
                                                    line.capital_investment_cents !==
                                                        undefined &&
                                                    line.capital_investment_cents !==
                                                        null
                                                "
                                                class="mt-1 text-xs text-muted-foreground"
                                            >
                                                Capital investment ·
                                                {{
                                                    money(
                                                        line.capital_investment_cents,
                                                    )
                                                }}
                                            </p>
                                        </li>
                                    </ul>
                                </div>
                                <div
                                    class="rounded-xl border border-primary/30 bg-primary/5 p-4"
                                >
                                    <div class="flex items-center gap-2">
                                        <Landmark
                                            class="size-4 text-primary"
                                            aria-hidden="true"
                                        />
                                        <h3 class="font-semibold">
                                            Municipal resolved activities
                                        </h3>
                                    </div>
                                    <ul class="mt-3 grid gap-2">
                                        <li
                                            v-for="line in evaluation.municipal_resolved_lines"
                                            :key="line.id"
                                            class="flex items-start gap-2 rounded-lg bg-background p-3"
                                        >
                                            <Check
                                                class="mt-0.5 size-4 shrink-0 text-primary"
                                                aria-hidden="true"
                                            />
                                            <span class="font-medium">{{
                                                line.name ??
                                                `Activity #${line.id}`
                                            }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <form
                                v-if="
                                    can.correct_lines_of_business &&
                                    !evaluation.financial_lock
                                "
                                class="mt-5 rounded-xl border-2 border-dashed border-primary/30 p-4"
                                @submit.prevent="submitLineCorrection"
                            >
                                <fieldset>
                                    <legend class="font-semibold">
                                        {{
                                            evaluation.lens === 'citizen'
                                                ? 'Correct your declared activities'
                                                : 'Treasury — correct or add application activities'
                                        }}
                                    </legend>
                                    <p
                                        class="mt-1 text-sm leading-6 text-muted-foreground"
                                    >
                                        {{
                                            evaluation.lens === 'citizen'
                                                ? 'Your original declaration is preserved in the history.'
                                                : 'This changes this permit application only. It does not change the legal Business registry.'
                                        }}
                                    </p>
                                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                                        <label
                                            v-for="line in lineOfBusinesses"
                                            :key="line.id"
                                            class="flex min-w-0 cursor-pointer items-start gap-3 rounded-lg border p-3 outline-none has-[:focus-visible]:ring-3 has-[:focus-visible]:ring-ring/50"
                                        >
                                            <input
                                                type="checkbox"
                                                class="mt-1 size-4"
                                                :checked="
                                                    selectedLineIds.includes(
                                                        line.id,
                                                    )
                                                "
                                                :value="line.id"
                                                @change="toggleLine(line.id)"
                                            />
                                            <span class="min-w-0"
                                                ><span
                                                    class="block font-medium break-words"
                                                    >{{ line.name }}</span
                                                ><span
                                                    v-if="line.code"
                                                    class="text-xs text-muted-foreground"
                                                    >{{ line.code }}</span
                                                ></span
                                            >
                                        </label>
                                    </div>
                                    <div class="mt-4 grid gap-2">
                                        <Label for="line-correction-reason"
                                            >Reason for correction
                                            <span aria-hidden="true"
                                                >*</span
                                            ></Label
                                        >
                                        <textarea
                                            id="line-correction-reason"
                                            v-model="lineCorrectionReason.value"
                                            required
                                            rows="3"
                                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        class="mt-4 w-full sm:w-auto"
                                        :disabled="
                                            selectedLineIds.length === 0 ||
                                            pendingAction !== null
                                        "
                                    >
                                        <RefreshCw aria-hidden="true" />
                                        {{
                                            pendingAction === 'line-correction'
                                                ? 'Recording…'
                                                : 'Record correction and re-evaluate'
                                        }}
                                    </Button>
                                </fieldset>
                            </form>
                        </section>

                        <section
                            class="space-y-4"
                            aria-labelledby="responsibilities-heading"
                        >
                            <div>
                                <p
                                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    Concerned offices
                                </p>
                                <h2
                                    id="responsibilities-heading"
                                    class="mt-1 text-lg font-semibold"
                                >
                                    Evaluation responsibilities
                                </h2>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Applicable work is authoritative
                                    responsibility, not an informal message.
                                </p>
                            </div>

                            <div
                                v-if="myItems.length"
                                class="rounded-2xl border-2 border-primary/40 bg-primary/5 p-4 sm:p-5"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex size-9 items-center justify-center rounded-full bg-primary text-primary-foreground"
                                    >
                                        <ClipboardCheck
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                    </div>
                                    <div>
                                        <p
                                            class="text-xs font-semibold tracking-wide text-primary uppercase"
                                        >
                                            Your action
                                        </p>
                                        <h3 class="font-semibold">
                                            {{
                                                officeLabel(
                                                    myItems[0]
                                                        .responsible_party,
                                                )
                                            }}
                                            responsibilities
                                        </h3>
                                    </div>
                                </div>
                                <div class="mt-4 grid gap-4">
                                    <EvaluationItemCard
                                        v-for="item in myItems"
                                        :key="item.id"
                                        :item="item"
                                        :editable="
                                            can.contribute &&
                                            !evaluation.financial_lock
                                        "
                                        :submitting="
                                            pendingAction === `item-${item.id}`
                                        "
                                        @submit="submitResponsibility"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-4">
                                <EvaluationItemCard
                                    v-for="item in otherItems"
                                    :key="item.id"
                                    :item="item"
                                    :editable="false"
                                    :submitting="false"
                                    @submit="submitResponsibility"
                                />
                            </div>
                        </section>

                        <section
                            class="rounded-2xl border bg-card p-5 shadow-xs sm:p-6"
                            aria-labelledby="charges-heading"
                        >
                            <div class="flex items-start gap-3">
                                <div
                                    class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted"
                                >
                                    <Scale class="size-4" aria-hidden="true" />
                                </div>
                                <div>
                                    <h2
                                        id="charges-heading"
                                        class="text-lg font-semibold"
                                    >
                                        Governed system proposals
                                    </h2>
                                    <p
                                        class="mt-1 text-sm leading-6 text-muted-foreground"
                                    >
                                        These charges come from the same
                                        governed pricing path used by Services &
                                        Fees and Assessment. Office-resolved
                                        charges are not duplicated here.
                                    </p>
                                </div>
                            </div>
                            <div
                                v-if="evaluation.projected_charges.length"
                                class="mt-5 grid gap-3"
                            >
                                <article
                                    v-for="charge in evaluation.projected_charges"
                                    :key="charge.key"
                                    class="grid gap-3 rounded-xl border p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
                                >
                                    <div class="min-w-0">
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <h3 class="font-semibold">
                                                {{ charge.name }}
                                            </h3>
                                            <Badge variant="outline">{{
                                                charge.code
                                            }}</Badge>
                                        </div>
                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            {{ pricingBasis(charge) }}
                                        </p>
                                        <p
                                            v-if="charge.legal_basis"
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{ charge.legal_basis }}
                                        </p>
                                    </div>
                                    <div
                                        class="flex items-center justify-between gap-4 sm:block sm:text-right"
                                    >
                                        <p
                                            class="text-lg font-semibold tabular-nums"
                                        >
                                            {{ money(charge.amount_cents) }}
                                        </p>
                                        <Link
                                            v-if="canViewFeeRules"
                                            :href="
                                                showFeeRule(charge.fee_rule_id)
                                            "
                                            class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-primary underline-offset-4 hover:underline focus-visible:ring-3 focus-visible:ring-ring/50"
                                        >
                                            Pricing basis
                                            <ArrowRight
                                                class="size-3"
                                                aria-hidden="true"
                                            />
                                        </Link>
                                        <p
                                            v-else
                                            class="text-xs text-muted-foreground"
                                        >
                                            Municipal pricing basis
                                        </p>
                                    </div>
                                </article>
                            </div>
                            <p
                                v-else
                                class="mt-4 rounded-xl bg-muted/40 p-4 text-sm text-muted-foreground"
                            >
                                No governed system proposal currently applies.
                            </p>
                        </section>
                    </div>

                    <aside class="min-w-0 space-y-5">
                        <section
                            v-if="
                                can.counter_check && !evaluation.financial_lock
                            "
                            class="rounded-2xl border-2 border-dashed border-primary/40 bg-card p-5"
                            aria-labelledby="treasury-heading"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex size-9 items-center justify-center rounded-full bg-primary text-primary-foreground"
                                >
                                    <Landmark
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </div>
                                <div>
                                    <p
                                        class="text-xs font-semibold tracking-wide text-primary uppercase"
                                    >
                                        Your action — Treasury
                                    </p>
                                    <h2
                                        id="treasury-heading"
                                        class="font-semibold"
                                    >
                                        Counter-check Evaluation
                                    </h2>
                                </div>
                            </div>
                            <template
                                v-if="evaluation.version.treasury_counter_check"
                            >
                                <p class="mt-4 text-sm font-medium">
                                    Counter-check complete
                                </p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{
                                        evaluation.version
                                            .treasury_counter_check.checked_by
                                    }}
                                    ·
                                    {{
                                        dateTime(
                                            evaluation.version
                                                .treasury_counter_check
                                                .checked_at,
                                        )
                                    }}
                                </p>
                                <p
                                    v-if="
                                        evaluation.version
                                            .treasury_counter_check.reason
                                    "
                                    class="mt-2 text-sm"
                                >
                                    {{
                                        evaluation.version
                                            .treasury_counter_check.reason
                                    }}
                                </p>
                            </template>
                            <form
                                v-else
                                class="mt-4"
                                @submit.prevent="submitCounterCheck"
                            >
                                <Label for="counter-check-reason"
                                    >Review note (optional)</Label
                                >
                                <textarea
                                    id="counter-check-reason"
                                    v-model="counterCheckReason.value"
                                    rows="3"
                                    class="mt-2 w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                                />
                                <Button
                                    type="submit"
                                    class="mt-3 w-full"
                                    :disabled="pendingAction !== null"
                                    ><ShieldCheck aria-hidden="true" />{{
                                        pendingAction === 'counter-check'
                                            ? 'Confirming…'
                                            : 'Confirm exact-version counter-check'
                                    }}</Button
                                >
                            </form>
                        </section>

                        <section
                            v-if="
                                can.prepare_assessment &&
                                !evaluation.financial_lock
                            "
                            class="rounded-2xl border bg-card p-5 shadow-xs"
                            aria-labelledby="assessment-action-heading"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex size-9 items-center justify-center rounded-full bg-muted"
                                >
                                    <FileClock
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </div>
                                <h2
                                    id="assessment-action-heading"
                                    class="font-semibold"
                                >
                                    Assessment Officer
                                </h2>
                            </div>
                            <p
                                class="mt-3 text-sm leading-6 text-muted-foreground"
                            >
                                Prepare an immutable Assessment from this exact
                                resolved Evaluation. Municipal Treasurer
                                approval remains a separate decision.
                            </p>
                            <Button
                                class="mt-4 w-full"
                                :disabled="
                                    !readiness?.ready ||
                                    currentAssessmentExists ||
                                    pendingAction !== null
                                "
                                @click="submitPrepareAssessment"
                                ><PhilippinePeso aria-hidden="true" />{{
                                    currentAssessmentExists
                                        ? 'Assessment already prepared'
                                        : pendingAction === 'prepare-assessment'
                                          ? 'Preparing…'
                                          : 'Prepare Assessment'
                                }}</Button
                            >
                        </section>

                        <section
                            class="rounded-2xl border bg-card p-5 shadow-xs"
                            aria-labelledby="assessment-trace-heading"
                        >
                            <h2
                                id="assessment-trace-heading"
                                class="font-semibold"
                            >
                                Assessment traceability
                            </h2>
                            <template v-if="evaluation.latest_assessment">
                                <Link
                                    v-if="evaluation.lens === 'internal'"
                                    :href="
                                        showAssessment(
                                            evaluation.latest_assessment.id,
                                        )
                                    "
                                    class="mt-3 flex items-start gap-3 rounded-xl bg-muted/40 p-3 outline-none hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50"
                                >
                                    <CheckCircle2
                                        v-if="
                                            evaluation.latest_assessment
                                                .consumes_current_evaluation &&
                                            !evaluation.latest_assessment
                                                .superseded
                                        "
                                        class="mt-0.5 size-5 shrink-0 text-emerald-600"
                                        aria-hidden="true"
                                    />
                                    <AlertTriangle
                                        v-else
                                        class="mt-0.5 size-5 shrink-0 text-amber-600"
                                        aria-hidden="true"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium">
                                            Assessment #{{
                                                evaluation.latest_assessment
                                                    .sequence
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            {{
                                                money(
                                                    evaluation.latest_assessment
                                                        .total_amount_cents,
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{
                                                evaluation.latest_assessment
                                                    .superseded
                                                    ? 'Superseded after Evaluation changed'
                                                    : evaluation
                                                            .latest_assessment
                                                            .consumes_current_evaluation
                                                      ? 'Consumes the current exact Evaluation'
                                                      : 'Consumes an earlier Evaluation version'
                                            }}
                                        </p>
                                    </div>
                                    <ArrowRight
                                        class="mt-1 size-4 shrink-0 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                </Link>
                                <div
                                    v-else
                                    class="mt-3 flex items-start gap-3 rounded-xl bg-muted/40 p-3"
                                >
                                    <CheckCircle2
                                        v-if="
                                            evaluation.latest_assessment
                                                .consumes_current_evaluation &&
                                            !evaluation.latest_assessment
                                                .superseded
                                        "
                                        class="mt-0.5 size-5 shrink-0 text-emerald-600"
                                        aria-hidden="true"
                                    />
                                    <AlertTriangle
                                        v-else
                                        class="mt-0.5 size-5 shrink-0 text-amber-600"
                                        aria-hidden="true"
                                    />
                                    <div>
                                        <p class="font-medium">
                                            Assessment #{{
                                                evaluation.latest_assessment
                                                    .sequence
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            {{
                                                money(
                                                    evaluation.latest_assessment
                                                        .total_amount_cents,
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </template>
                            <p
                                v-else
                                class="mt-2 text-sm leading-6 text-muted-foreground"
                            >
                                No Assessment consumes this Evaluation yet.
                            </p>
                        </section>

                        <details
                            class="group rounded-2xl border bg-card shadow-xs"
                        >
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between gap-3 p-5 outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                            >
                                <span
                                    class="flex items-center gap-2 font-semibold"
                                    ><History
                                        class="size-4"
                                        aria-hidden="true"
                                    />Audit details</span
                                >
                                <ChevronRight
                                    class="size-4 transition-transform group-open:rotate-90"
                                    aria-hidden="true"
                                />
                            </summary>
                            <div class="border-t p-5 text-sm">
                                <dl class="grid gap-3">
                                    <div>
                                        <dt
                                            class="text-xs text-muted-foreground"
                                        >
                                            Evaluation version
                                        </dt>
                                        <dd class="mt-1 font-medium">
                                            Version
                                            {{ evaluation.version.sequence }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt
                                            class="text-xs text-muted-foreground"
                                        >
                                            Version integrity
                                        </dt>
                                        <dd class="mt-1 font-medium">
                                            {{
                                                evaluation.version
                                                    .fingerprint_current
                                                    ? 'Current'
                                                    : 'Changed — refresh required'
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt
                                            class="text-xs text-muted-foreground"
                                        >
                                            Exact reference
                                        </dt>
                                        <dd
                                            class="mt-1 font-mono text-xs break-all"
                                        >
                                            {{ evaluation.version.fingerprint }}
                                        </dd>
                                    </div>
                                </dl>
                                <Button
                                    v-if="
                                        can.initialize &&
                                        !evaluation.financial_lock
                                    "
                                    variant="outline"
                                    class="mt-4 w-full"
                                    :disabled="pendingAction !== null"
                                    @click="submitRefresh"
                                    ><RefreshCw aria-hidden="true" />{{
                                        pendingAction === 'refresh'
                                            ? 'Refreshing…'
                                            : 'Refresh dependencies'
                                    }}</Button
                                >
                            </div>
                        </details>
                    </aside>
                </div>
            </template>
        </main>
    </AppLayout>
</template>
