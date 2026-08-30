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
import {
    show as showAssessment,
    store as prepareAssessment,
} from '@/actions/App/Http/Controllers/Staff/PermitApplicationAssessmentController';
import EvaluationComponentRow from '@/components/evaluations/EvaluationComponentRow.vue';
import EvaluationItemCard from '@/components/evaluations/EvaluationItemCard.vue';
import EvaluationTotalPanel from '@/components/evaluations/EvaluationTotalPanel.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    applicationTypeLabel,
    applicabilityLabel,
    dateTime,
    money,
    officeLabel,
    presentFinancialWorkingPaper,
    readinessBlockers,
    sourceLabel,
} from '@/lib/evaluationPresentation';
import type { ResponsibilityDraft } from '@/lib/evaluationPresentation';
import type {
    BreadcrumbItem,
    BusinessPermitEvaluationData,
    EvaluationCapabilities,
    EvaluationItem,
    EvaluationLineOfBusinessOption,
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

const isCitizenLens = computed(() => props.evaluation?.lens === 'citizen');

/** Presentation-only hierarchy over backend-provided charges and totals. */
const workingPaper = computed(() =>
    props.evaluation === null
        ? null
        : presentFinancialWorkingPaper(
              props.evaluation.financial_working_paper,
              props.evaluation.items,
          ),
);

const itemsById = computed<Map<number, EvaluationItem>>(
    () =>
        new Map((props.evaluation?.items ?? []).map((item) => [item.id, item])),
);

/** Non-monetary municipal work: recorded facts and office determinations. */
const responsibilityItems = computed(() =>
    [...(props.evaluation?.items ?? [])]
        .filter((item) => item.item_type !== 'charge')
        .sort((left, right) => Number(right.is_mine) - Number(left.is_mine)),
);

/** Canonical departmental charge responsibilities, including completed work. */
const departmentResponsibilities = computed(() =>
    (props.evaluation?.items ?? []).filter(
        (item) => item.item_type === 'charge' && item.requires_confirmation,
    ),
);

const completedDepartmentResponsibilityCount = computed(
    () =>
        departmentResponsibilities.value.filter(
            (item) => item.resolution === 'resolved',
        ).length,
);

function reviewStage(item: EvaluationItem): string {
    const value = item.resolved_value;
    const inspection =
        value && typeof value === 'object' && !Array.isArray(value)
            ? (value as Record<string, unknown>).inspection
            : null;

    if (
        inspection &&
        typeof inspection === 'object' &&
        !Array.isArray(inspection)
    ) {
        const review = inspection as Record<string, unknown>;

        if (review.completed === true) {
            const mode =
                typeof review.mode === 'string'
                    ? review.mode.replaceAll('_', ' ')
                    : item.inspection_required
                      ? 'office review'
                      : 'document review';

            return `${mode} complete`;
        }
    }

    if (item.inspection_required) {
        return 'Inspection/review still required';
    }

    return item.resolution === 'resolved'
        ? 'Determination recorded; no inspection required'
        : 'Department determination still required';
}

/** Work this viewer legitimately owns and has not finished. */
const myOpenWork = computed(() =>
    (props.evaluation?.items ?? []).filter(
        (item) => item.is_mine && item.resolution !== 'resolved',
    ),
);

const requiredResponsibilitiesComplete = computed(() =>
    (props.evaluation?.items ?? [])
        .filter((item) => item.is_required)
        .every((item) => item.resolution === 'resolved'),
);

const canRecordWork = computed(
    () => props.can.contribute && props.evaluation?.financial_lock === false,
);

const canViewFeeRules = computed(
    () =>
        Boolean(
            (page.props.auth as { can_view_fee_rules?: boolean } | undefined)
                ?.can_view_fee_rules,
        ) && props.evaluation?.lens === 'internal',
);

const latestAssessment = computed(() => props.evaluation?.latest_assessment);
const currentAssessmentExists = computed(
    () =>
        latestAssessment.value != null &&
        latestAssessment.value.superseded === false &&
        latestAssessment.value.consumes_current_evaluation === true,
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
        note: 'The municipal evaluations below are still open. The evaluated amount can still change.',
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
        const action = isCitizenLens.value
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

function submitResponsibility(
    item: EvaluationItem,
    draft: ResponsibilityDraft,
): void {
    if (!props.evaluation) {
        return;
    }

    // Vue casts `v-model` on a number input to a number, so the draft amount
    // arrives as either a string or a number depending on whether the office
    // typed in the field.
    const amountText = String(draft.amount ?? '').trim();

    if (
        item.item_type === 'charge' &&
        draft.applicability === 'applicable' &&
        amountText === ''
    ) {
        return;
    }

    if (
        !window.confirm(
            `Record the ${officeLabel(item.responsible_party)} determination for ${item.label}?`,
        )
    ) {
        return;
    }

    runOnce(`item-${item.id}`, () => {
        const amountCents =
            amountText === '' ? null : Math.round(Number(amountText) * 100);
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
                        {{
                            isCitizenLens
                                ? 'What you declared, what the Municipality currently evaluates, and which municipal reviews are still open.'
                                : 'How the municipal charges on this application build up, who owns each one, and what changed.'
                        }}
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

            <template v-else-if="workingPaper">
                <!-- 1. Whose application this is -->
                <section
                    class="rounded-2xl border bg-card p-5 shadow-xs sm:p-6"
                    aria-labelledby="application-identity-heading"
                >
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
                                id="application-identity-heading"
                                class="text-xl font-semibold break-words"
                            >
                                {{ evaluation.application.business_name }}
                            </h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ evaluation.application.owner_name }} ·
                                {{
                                    applicationTypeLabel(
                                        evaluation.application.type,
                                    )
                                }}
                                · {{ evaluation.application.year }}
                            </p>
                            <p class="mt-2 text-xs text-muted-foreground">
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
                                <template v-else>
                                    Application record #{{
                                        evaluation.application.id
                                    }}
                                </template>
                            </p>
                        </div>
                    </div>
                </section>

                <!-- 2. What it currently costs, and how that was assembled -->
                <EvaluationTotalPanel
                    :working-paper="workingPaper"
                    :status-label="evaluation.status_label"
                    :financial-lock="evaluation.financial_lock"
                />

                <!-- 3. The viewer's own legitimate work -->
                <section
                    v-if="canRecordWork && myOpenWork.length"
                    class="rounded-2xl border-2 border-primary/40 bg-primary/5 p-4 sm:p-5"
                    aria-labelledby="your-action-heading"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground"
                        >
                            <ClipboardCheck class="size-4" aria-hidden="true" />
                        </div>
                        <div class="min-w-0">
                            <p
                                class="text-xs font-semibold tracking-wide text-primary uppercase"
                            >
                                Your action
                            </p>
                            <h2 id="your-action-heading" class="font-semibold">
                                {{
                                    officeLabel(myOpenWork[0].responsible_party)
                                }}
                                has
                                {{ myOpenWork.length }}
                                open
                                {{
                                    myOpenWork.length === 1
                                        ? 'responsibility'
                                        : 'responsibilities'
                                }}
                                on this application
                            </h2>
                            <ul class="mt-2 grid gap-1 text-sm">
                                <li
                                    v-for="item in myOpenWork"
                                    :key="item.id"
                                    class="flex items-start gap-2"
                                >
                                    <ChevronRight
                                        class="mt-0.5 size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <span>
                                        {{ item.label }}
                                        <span class="text-muted-foreground">
                                            —
                                            {{
                                                item.item_type === 'charge'
                                                    ? 'confirm or change the proposed amount below'
                                                    : 'record your determination below'
                                            }}
                                        </span>
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- 4. The financial build-up -->
                <section class="space-y-4" aria-labelledby="build-up-heading">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            Computation / Assessment working paper
                        </p>
                        <h2
                            id="build-up-heading"
                            class="mt-1 text-lg font-semibold"
                        >
                            Charges by Line of Business
                        </h2>
                        <p class="mt-1 text-sm leading-6 text-muted-foreground">
                            The Municipality builds this Evaluation from each
                            Line of Business, its applicable charges, and any
                            charges that apply to the whole application.
                        </p>
                    </div>

                    <div
                        v-if="
                            workingPaper.lineSections.length ||
                            workingPaper.applicationSection
                        "
                        class="grid gap-4"
                    >
                        <section
                            v-for="(
                                section, index
                            ) in workingPaper.lineSections"
                            :key="section.key"
                            class="overflow-hidden rounded-2xl border bg-muted/15"
                            :aria-labelledby="`lob-section-${index}`"
                            :data-testid="`working-paper-line-${section.lineOfBusinessId}`"
                        >
                            <header
                                class="flex flex-col gap-3 border-b bg-card p-4 sm:flex-row sm:items-start sm:justify-between sm:p-5"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                    >
                                        Line of Business {{ index + 1 }}
                                    </p>
                                    <h3
                                        :id="`lob-section-${index}`"
                                        class="mt-1 text-lg font-semibold break-words"
                                    >
                                        {{ section.label }}
                                    </h3>
                                </div>
                                <div class="sm:text-right">
                                    <p class="text-xs text-muted-foreground">
                                        LOB Subtotal
                                    </p>
                                    <p
                                        class="mt-1 text-xl font-semibold tabular-nums"
                                        :data-testid="`working-paper-line-subtotal-${section.lineOfBusinessId}`"
                                    >
                                        {{ money(section.subtotalCents) }}
                                    </p>
                                </div>
                            </header>
                            <div class="grid gap-3 p-3 sm:p-4">
                                <EvaluationComponentRow
                                    v-for="component in section.charges"
                                    :key="component.key"
                                    :component="component"
                                    :item="
                                        component.itemId === null
                                            ? null
                                            : (itemsById.get(
                                                  component.itemId,
                                              ) ?? null)
                                    "
                                    :editable="
                                        canRecordWork && component.isMine
                                    "
                                    :submitting="
                                        pendingAction ===
                                        `item-${component.itemId}`
                                    "
                                    :can-view-fee-rules="canViewFeeRules"
                                    :simplified="isCitizenLens"
                                    @submit="submitResponsibility"
                                />
                            </div>
                        </section>

                        <section
                            v-if="workingPaper.applicationSection"
                            class="overflow-hidden rounded-2xl border border-dashed bg-muted/15"
                            aria-labelledby="application-charge-section"
                            data-testid="working-paper-application-section"
                        >
                            <header
                                class="flex flex-col gap-3 border-b bg-card p-4 sm:flex-row sm:items-start sm:justify-between sm:p-5"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                    >
                                        Whole application
                                    </p>
                                    <h3
                                        id="application-charge-section"
                                        class="mt-1 text-lg font-semibold"
                                    >
                                        Application-wide charges
                                    </h3>
                                </div>
                                <div class="sm:text-right">
                                    <p class="text-xs text-muted-foreground">
                                        Application-wide subtotal
                                    </p>
                                    <p
                                        class="mt-1 text-xl font-semibold tabular-nums"
                                        data-testid="working-paper-application-subtotal"
                                    >
                                        {{
                                            money(
                                                workingPaper.applicationSection
                                                    .subtotalCents,
                                            )
                                        }}
                                    </p>
                                </div>
                            </header>
                            <div class="grid gap-3 p-3 sm:p-4">
                                <EvaluationComponentRow
                                    v-for="component in workingPaper
                                        .applicationSection.charges"
                                    :key="component.key"
                                    :component="component"
                                    :item="
                                        component.itemId === null
                                            ? null
                                            : (itemsById.get(
                                                  component.itemId,
                                              ) ?? null)
                                    "
                                    :editable="
                                        canRecordWork && component.isMine
                                    "
                                    :submitting="
                                        pendingAction ===
                                        `item-${component.itemId}`
                                    "
                                    :can-view-fee-rules="canViewFeeRules"
                                    :simplified="isCitizenLens"
                                    @submit="submitResponsibility"
                                />
                            </div>
                        </section>
                    </div>
                    <p
                        v-else
                        class="rounded-xl bg-muted/40 p-4 text-sm text-muted-foreground"
                    >
                        No municipal charge applies to this application yet.
                    </p>
                </section>

                <!-- 5. What is still open -->
                <section
                    :class="['rounded-xl border p-4', statusTone]"
                    aria-labelledby="open-work-heading"
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
                            <h2 id="open-work-heading" class="font-semibold">
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

                <!-- 6. Canonical departmental responsibility evidence -->
                <section
                    v-if="
                        !isCitizenLens && departmentResponsibilities.length > 0
                    "
                    class="rounded-2xl border bg-card p-5 shadow-xs sm:p-6"
                    aria-labelledby="department-responsibilities-heading"
                    data-testid="department-responsibility-evidence"
                >
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div>
                            <p
                                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                Department work
                            </p>
                            <h2
                                id="department-responsibilities-heading"
                                class="mt-1 text-lg font-semibold"
                            >
                                Responsibilities created and resolved
                            </h2>
                            <p
                                class="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground"
                            >
                                These are the canonical Evaluation
                                responsibilities behind the charge rows above.
                                Completion comes from each responsibility's
                                recorded revision, not from whether an amount is
                                present.
                            </p>
                        </div>
                        <Badge variant="outline" class="w-fit shrink-0">
                            {{ completedDepartmentResponsibilityCount }} of
                            {{ departmentResponsibilities.length }} complete
                        </Badge>
                    </div>

                    <div class="mt-5 grid gap-3 lg:grid-cols-2">
                        <article
                            v-for="item in departmentResponsibilities"
                            :key="item.id"
                            class="rounded-xl border bg-background p-4"
                            :data-testid="`department-responsibility-${item.id}`"
                        >
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div class="min-w-0">
                                    <h3 class="font-semibold break-words">
                                        {{ item.label }}
                                    </h3>
                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        {{
                                            officeLabel(item.responsible_party)
                                        }}
                                        ·
                                        {{
                                            item.line_of_business_name ??
                                            'Whole application'
                                        }}
                                    </p>
                                </div>
                                <Badge
                                    :variant="
                                        item.resolution === 'resolved'
                                            ? 'secondary'
                                            : 'outline'
                                    "
                                    class="w-fit shrink-0"
                                >
                                    {{
                                        item.resolution === 'resolved'
                                            ? 'Complete'
                                            : 'Open'
                                    }}
                                </Badge>
                            </div>

                            <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-lg bg-muted/40 p-3">
                                    <dt class="text-xs text-muted-foreground">
                                        Applicability / determination
                                    </dt>
                                    <dd class="mt-1 font-medium">
                                        {{
                                            applicabilityLabel(
                                                item.applicability,
                                            )
                                        }}
                                    </dd>
                                    <dd
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{
                                            sourceLabel(
                                                item.source_classification,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div class="rounded-lg bg-muted/40 p-3">
                                    <dt class="text-xs text-muted-foreground">
                                        Review stage
                                    </dt>
                                    <dd class="mt-1 font-medium capitalize">
                                        {{ reviewStage(item) }}
                                    </dd>
                                </div>
                            </dl>

                            <div
                                v-if="item.department_selection_reason"
                                class="mt-3 rounded-lg border-l-2 border-primary bg-muted/30 p-3 text-sm"
                            >
                                <p class="text-xs text-muted-foreground">
                                    Why this department
                                </p>
                                <p class="mt-1">
                                    {{ item.department_selection_reason }}
                                </p>
                            </div>
                            <p
                                v-if="item.reason"
                                class="mt-3 text-sm text-muted-foreground"
                            >
                                Recorded completion: {{ item.reason }}
                            </p>
                        </article>
                    </div>
                </section>

                <!-- 7. Declaration versus municipal determination -->
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
                                The original declaration remains visible when
                                the Municipality records a correction or
                                additional activity.
                            </p>
                        </div>
                        <Badge
                            v-if="
                                can.correct_lines_of_business &&
                                !evaluation.financial_lock
                            "
                            variant="outline"
                        >
                            {{
                                isCitizenLens
                                    ? 'Your declaration'
                                    : 'Treasury correction control'
                            }}
                        </Badge>
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
                                            null
                                        "
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        Capital investment ·
                                        {{
                                            money(line.capital_investment_cents)
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
                                    <span class="font-medium">
                                        {{
                                            line.name ?? `Activity #${line.id}`
                                        }}
                                    </span>
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
                                    isCitizenLens
                                        ? 'Correct your declared activities'
                                        : 'Treasury — correct or add application activities'
                                }}
                            </legend>
                            <p
                                class="mt-1 text-sm leading-6 text-muted-foreground"
                            >
                                {{
                                    isCitizenLens
                                        ? 'Your original declaration is preserved in the history.'
                                        : 'This changes this permit application only. It does not change the legal Business registry, and it re-evaluates affected office responsibilities.'
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
                                            selectedLineIds.includes(line.id)
                                        "
                                        :value="line.id"
                                        @change="toggleLine(line.id)"
                                    />
                                    <span class="min-w-0">
                                        <span
                                            class="block font-medium break-words"
                                        >
                                            {{ line.name }}
                                        </span>
                                        <span
                                            v-if="line.code"
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ line.code }}
                                        </span>
                                    </span>
                                </label>
                            </div>
                            <div class="mt-4 grid gap-2">
                                <Label for="line-correction-reason">
                                    Reason for correction
                                    <span aria-hidden="true">*</span>
                                </Label>
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

                <!-- 8. Non-monetary municipal work -->
                <section
                    v-if="responsibilityItems.length"
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
                            Municipal reviews and recorded facts
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            These do not carry an amount, but the Assessment
                            cannot proceed until the required ones are complete.
                        </p>
                    </div>
                    <div class="grid gap-4">
                        <EvaluationItemCard
                            v-for="item in responsibilityItems"
                            :key="item.id"
                            :item="item"
                            :editable="canRecordWork && item.is_mine"
                            :submitting="pendingAction === `item-${item.id}`"
                            @submit="submitResponsibility"
                        />
                    </div>
                </section>

                <!-- 9. Role context: Treasury, Assessment Officer, Municipal Treasurer -->
                <div class="grid min-w-0 gap-4 xl:grid-cols-2">
                    <section
                        v-if="can.counter_check && !evaluation.financial_lock"
                        class="rounded-2xl border-2 border-dashed border-primary/40 bg-card p-5"
                        aria-labelledby="treasury-heading"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex size-9 items-center justify-center rounded-full bg-primary text-primary-foreground"
                            >
                                <Landmark class="size-4" aria-hidden="true" />
                            </div>
                            <div>
                                <p
                                    class="text-xs font-semibold tracking-wide text-primary uppercase"
                                >
                                    Your action — Treasury
                                </p>
                                <h2 id="treasury-heading" class="font-semibold">
                                    Counter-check this Evaluation
                                </h2>
                            </div>
                        </div>
                        <p class="mt-3 text-sm leading-6 text-muted-foreground">
                            Treasury counter-check confirms this exact
                            Evaluation version. It is separate from Municipal
                            Treasurer approval and never overrides an amount.
                        </p>
                        <template
                            v-if="evaluation.version.treasury_counter_check"
                        >
                            <p class="mt-4 text-sm font-medium">
                                Counter-check complete
                            </p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{
                                    evaluation.version.treasury_counter_check
                                        .checked_by
                                }}
                                ·
                                {{
                                    dateTime(
                                        evaluation.version
                                            .treasury_counter_check.checked_at,
                                    )
                                }}
                            </p>
                            <p
                                v-if="
                                    evaluation.version.treasury_counter_check
                                        .reason
                                "
                                class="mt-2 text-sm"
                            >
                                {{
                                    evaluation.version.treasury_counter_check
                                        .reason
                                }}
                            </p>
                        </template>
                        <form
                            v-else-if="requiredResponsibilitiesComplete"
                            class="mt-4"
                            @submit.prevent="submitCounterCheck"
                        >
                            <Label for="counter-check-reason">
                                Review note (optional)
                            </Label>
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
                            >
                                <ShieldCheck aria-hidden="true" />
                                {{
                                    pendingAction === 'counter-check'
                                        ? 'Confirming…'
                                        : 'Confirm exact-version counter-check'
                                }}
                            </Button>
                        </form>
                        <p
                            v-else
                            class="mt-4 rounded-lg bg-muted/50 p-3 text-sm text-muted-foreground"
                        >
                            Counter-check becomes available after every required
                            department responsibility above is complete.
                        </p>
                    </section>

                    <section
                        v-if="
                            can.prepare_assessment && !evaluation.financial_lock
                        "
                        class="rounded-2xl border bg-card p-5 shadow-xs"
                        aria-labelledby="assessment-action-heading"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex size-9 items-center justify-center rounded-full bg-muted"
                            >
                                <FileClock class="size-4" aria-hidden="true" />
                            </div>
                            <div>
                                <p
                                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    Your action — Assessment Officer
                                </p>
                                <h2
                                    id="assessment-action-heading"
                                    class="font-semibold"
                                >
                                    Prepare the Assessment
                                </h2>
                            </div>
                        </div>
                        <p class="mt-3 text-sm leading-6 text-muted-foreground">
                            This freezes the canonical financial build-up above
                            into an immutable Assessment. Municipal Treasurer
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
                        >
                            <PhilippinePeso aria-hidden="true" />
                            {{
                                currentAssessmentExists
                                    ? 'Assessment already prepared'
                                    : pendingAction === 'prepare-assessment'
                                      ? 'Preparing…'
                                      : 'Prepare Assessment'
                            }}
                        </Button>
                        <p
                            v-if="!readiness?.ready"
                            class="mt-2 text-xs text-muted-foreground"
                        >
                            Available once the open municipal evaluations above
                            are resolved.
                        </p>
                    </section>

                    <section
                        class="rounded-2xl border bg-card p-5 shadow-xs"
                        aria-labelledby="assessment-trace-heading"
                    >
                        <h2 id="assessment-trace-heading" class="font-semibold">
                            Assessment of record
                        </h2>
                        <template v-if="latestAssessment">
                            <component
                                :is="isCitizenLens ? 'div' : Link"
                                v-bind="
                                    isCitizenLens
                                        ? {}
                                        : {
                                              href: showAssessment(
                                                  latestAssessment.id,
                                              ),
                                          }
                                "
                                class="mt-3 flex items-start gap-3 rounded-xl bg-muted/40 p-3 outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                                :class="isCitizenLens ? '' : 'hover:bg-muted'"
                            >
                                <CheckCircle2
                                    v-if="currentAssessmentExists"
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
                                            latestAssessment.sequence
                                        }}
                                        ·
                                        {{
                                            money(
                                                latestAssessment.total_amount_cents,
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        {{
                                            latestAssessment.superseded
                                                ? 'Superseded after the Evaluation changed'
                                                : latestAssessment.consumes_current_evaluation
                                                  ? 'Prepared from this exact Evaluation version'
                                                  : 'Prepared from an earlier Evaluation version'
                                        }}
                                    </p>
                                    <p
                                        v-if="!isCitizenLens"
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        Municipal Treasurer approval and return
                                        for correction are recorded on the
                                        Assessment itself.
                                    </p>
                                </div>
                                <ArrowRight
                                    v-if="!isCitizenLens"
                                    class="mt-1 size-4 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            </component>
                        </template>
                        <p
                            v-else
                            class="mt-2 text-sm leading-6 text-muted-foreground"
                        >
                            {{
                                isCitizenLens
                                    ? 'The Municipality has not issued an assessment for this application yet, so the amount above can still change.'
                                    : 'No Assessment consumes this Evaluation yet.'
                            }}
                        </p>
                    </section>
                </div>

                <!-- 9. Audit detail, deliberately last -->
                <details class="group rounded-2xl border bg-card shadow-xs">
                    <summary
                        class="flex cursor-pointer list-none items-center justify-between gap-3 p-5 outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                    >
                        <span class="flex items-center gap-2 font-semibold">
                            <History class="size-4" aria-hidden="true" />
                            Audit detail
                        </span>
                        <ChevronRight
                            class="size-4 transition-transform group-open:rotate-90"
                            aria-hidden="true"
                        />
                    </summary>
                    <div class="border-t p-5 text-sm">
                        <dl class="grid gap-3">
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Evaluation version
                                </dt>
                                <dd class="mt-1 font-medium">
                                    Version {{ evaluation.version.sequence }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Version integrity
                                </dt>
                                <dd class="mt-1 font-medium">
                                    {{
                                        evaluation.version.fingerprint_current
                                            ? 'Current'
                                            : 'Dependencies changed — refresh required'
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Exact reference
                                </dt>
                                <dd class="mt-1 font-mono text-xs break-all">
                                    {{ evaluation.version.fingerprint }}
                                </dd>
                            </div>
                            <div v-if="evaluation.pricing_issues.length">
                                <dt class="text-xs text-muted-foreground">
                                    Municipal pricing needs review
                                </dt>
                                <dd class="mt-1">
                                    {{ evaluation.pricing_issues.length }}
                                    recorded pricing issue(s)
                                </dd>
                            </div>
                        </dl>
                        <Button
                            v-if="can.initialize && !evaluation.financial_lock"
                            variant="outline"
                            class="mt-4 w-full sm:w-auto"
                            :disabled="pendingAction !== null"
                            @click="submitRefresh"
                        >
                            <RefreshCw aria-hidden="true" />
                            {{
                                pendingAction === 'refresh'
                                    ? 'Refreshing…'
                                    : 'Refresh dependencies'
                            }}
                        </Button>
                    </div>
                </details>
            </template>
        </main>
    </AppLayout>
</template>
