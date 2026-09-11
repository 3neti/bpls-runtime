<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Archive,
    ArrowRight,
    CalendarDays,
    Check,
    Circle,
    ExternalLink,
    FlaskConical,
    Landmark,
    Play,
    ShieldCheck,
    WalletCards,
} from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import {
    close as closeCleanroomRoute,
    enterActor as enterCleanroomActorRoute,
    officeReviewsAssigned,
    runNext as runCleanroomNextRoute,
    runToMilestone as runCleanroomMilestoneRoute,
    start as startCleanroomRoute,
} from '@/actions/App/Http/Controllers/LifecycleCleanroomController';
import {
    enterActor,
    runNext,
    runToMilestone,
} from '@/actions/App/Http/Controllers/LifecycleLaboratoryController';
import ExecutableApplication from '@/components/permit-applications/ExecutableApplication.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LifecycleCleanroomEvidence } from '@/types/lifecycle-cleanroom';

type Event = {
    key: string;
    sequence: number;
    label: string;
    description: string;
    status: 'completed' | 'pending';
    delta: Record<string, string>;
};

type Scenario = {
    id: string;
    label: string;
    effective_date: string;
    application_year: number;
    status: 'completed' | 'ready';
    specimen_id: number | null;
    summary: string;
    milestone: string;
    events: Event[];
    financial_working_paper: {
        lines: { label: string; amount_cents: number }[];
        total_amount_cents: number;
        assessment_total_amount_cents: number | null;
        payable_balance_cents: number | null;
    };
    application: {
        id: number;
        business_id: number;
        business_name: string;
        owner_name: string;
        type: string;
        status: string;
        assessment_id: number;
        payment_schedule_id: number;
    } | null;
    actors: { key: string; label: string }[];
};

type CleanroomStep = {
    key: string;
    year: number;
    label: string;
    description: string;
    mode: 'complete_on_start' | 'product_form' | 'system_action';
    actor: string | null;
    milestone: string;
    completed: boolean;
    status: 'completed' | 'current' | 'pending';
    delta: Record<string, string>;
};

type CleanroomActor = {
    key: string;
    label: string;
    relationship: 'next' | 'waiting' | 'view_only';
    relationship_label: string;
    is_next: boolean;
    task: {
        key: string;
        label: string;
        mode: CleanroomStep['mode'];
        tab: string;
        focus: string;
    } | null;
};

type CleanroomState = {
    run: {
        id: number;
        public_id: string;
        ceremony: string;
        status: string;
        source_specimen: {
            id: string;
            calibration_id: string;
            source_specimen_sha256: string;
            chronology: string;
            external_payment_simulation_only: boolean;
        } | null;
    };
    progress: {
        completed_steps: number;
        total_steps: number;
        percent: number;
        complete: boolean;
        blocked: boolean;
        blocker: string | null;
        profile_kind:
            | 'pending_intake'
            | 'certified_two_year'
            | 'registry_source_replay'
            | 'nelson_reconciliation_v1';
        profile_statement: string | null;
        completion_message: string;
        next_step: CleanroomStep | null;
    };
    steps: CleanroomStep[];
    applications: {
        new: { current_total_amount_cents: number } | null;
        renewal: { current_total_amount_cents: number } | null;
    };
    actors: CleanroomActor[];
    application_data: any | null;
    application_document: any | null;
    payment_simulation: {
        available: boolean;
        pay_code: string | null;
        collection_id: number | null;
        receipt_ids: number[];
        receipt_coverage_complete: boolean;
        status: 'not_ready' | 'awaiting_simulation' | 'collected';
    };
    classic_ceremony: {
        registration_claimed: boolean;
        event_count: number;
        events: Array<{
            sequence: number;
            actor_key: string | null;
            event: string;
            route_name: string | null;
            canonical_step: string | null;
            completed_stage_count: number | null;
            occurred_at: string | null;
        }>;
    } | null;
    evidence_summary: LifecycleCleanroomEvidence;
};

type LifecycleStage = {
    key: string;
    label: string;
    summary: string;
    state: 'complete' | 'current' | 'pending';
};

const props = defineProps<{
    authorizedLegacyReview: boolean;
    cleanroom: {
        active: CleanroomState | null;
        history: Array<LifecycleCleanroomEvidence & { view_url: string }>;
    };
    laboratory: {
        safety: {
            classification: string;
            production_available: false;
            reset_available: false;
            execution_boundary: string;
        };
        progress: {
            completed_scenarios: number;
            total_scenarios: number;
            next_scenario_id: string | null;
            complete: boolean;
        };
        scenarios: Scenario[];
    };
}>();

defineOptions({ layout: AppLayout });

const working = ref<string | null>(null);
const selectedMilestone = ref(
    props.laboratory.progress.next_scenario_id ??
        props.laboratory.scenarios[1].id,
);
const selectedCleanroomMilestone = ref(
    props.cleanroom.active?.progress.next_step?.key ?? 'payable_created',
);
const currentApplicationData = computed(
    () => props.cleanroom.active?.application_data ?? null,
);
const nextCleanroomActor = computed(
    () => props.cleanroom.active?.actors.find((actor) => actor.is_next) ?? null,
);
const isClassicCleanroom = computed(
    () => props.cleanroom.active?.run.ceremony === 'classic_lifecycle_v1',
);
const selectedActorKey = ref(nextCleanroomActor.value?.key ?? 'citizen');
const selectedApplicationTab = ref<string | null>(null);
const laboratoryInitialTab = computed(() => {
    if (selectedApplicationTab.value) {
        return selectedApplicationTab.value;
    }

    const taskTab = nextCleanroomActor.value?.task?.tab;

    if (taskTab) {
        return taskTab;
    }

    const application = currentApplicationData.value;

    if (!application) {
        return 'application';
    }

    if (
        application.payment?.payable ||
        (application.payment?.collections?.length ?? 0) > 0 ||
        (application.official_receipts?.length ?? 0) > 0
    ) {
        return 'payment';
    }

    if (application.financial?.assessment) {
        return 'assessment';
    }

    if (
        application.routing?.status === 'determined' ||
        application.offices?.length > 0
    ) {
        return 'processing';
    }

    return 'application';
});
const lifecycleStages = computed<LifecycleStage[]>(() => {
    const steps = props.cleanroom.active?.steps ?? [];
    const application = currentApplicationData.value;
    const paymentOrderCount = (application?.offices ?? []).filter(
        (office: { paperless_payment_order_count?: number }) =>
            (office.paperless_payment_order_count ?? 0) > 0,
    ).length;
    const groups = [
        {
            key: 'application',
            label: 'Application',
            summary: application ? 'Lodged' : 'Not started',
            steps: ['citizen_intake'],
        },
        {
            key: 'bplo',
            label: 'BPLO',
            summary:
                application?.routing?.status === 'determined'
                    ? 'Routed'
                    : 'Pending',
            steps: ['bplo_routing'],
        },
        {
            key: 'offices',
            label: 'Payment Orders',
            summary: `${paymentOrderCount} of ${application?.offices?.length ?? 0} finalized`,
            steps: [
                'evaluation_initialized',
                'assessor_responsibilities',
                'engineering_responsibility',
                'health_responsibilities',
                'menro_responsibility',
            ],
        },
        {
            key: 'treasury',
            label: 'Treasury',
            summary: `${application?.business?.treasury_assigned_lines_of_business?.length ?? 0} Lines of Business`,
            steps: ['treasury_lob_classification'],
        },
        {
            key: 'assessment',
            label: 'Assessment',
            summary: application?.financial?.assessment
                ? `${pesos(application.financial.assessment.total_amount_cents)} approved`
                : 'Pending',
            steps: [
                'assessment_prepared',
                'treasury_counter_check',
                'treasurer_approved',
                'payable_created',
            ],
        },
        {
            key: 'payment',
            label: 'Payment',
            summary: application?.payment?.reconciliation
                ? `${pesos(application.payment.reconciliation.collected_amount_cents)} collected · ${application.payment.reconciliation.issued_receipt_group_count ?? 0} receipts`
                : 'Pending',
            steps: [
                'qr_payment_requested',
                'qr_payment_collected',
                'official_receipt_issued',
            ],
        },
        {
            key: 'certification',
            label: 'Certification',
            summary: `${application?.post_payment?.readiness?.certified_offices?.length ?? 0} offices`,
            steps: [
                'post_payment_certifications_commissioned',
                'assessor_post_payment_certified',
                'engineering_post_payment_certified',
                'health_post_payment_certified',
                'menro_post_payment_certified',
            ],
        },
        {
            key: 'permit',
            label: 'Permit',
            summary: application?.permit?.released
                ? 'Issued · Released'
                : application?.permit?.issued
                  ? 'Issued'
                  : 'Pending',
            steps: ['permit_ready', 'permit_issued', 'permit_released'],
        },
        {
            key: 'verification',
            label: 'Verification',
            summary: application?.permit?.released ? 'Available' : 'Pending',
            steps: ['public_verification'],
        },
    ];

    return groups.map((group) => {
        const groupSteps = steps.filter((step) =>
            group.steps.includes(step.key),
        );

        return {
            key: group.key,
            label: group.label,
            summary: group.summary,
            state: groupSteps.some((step) => step.status === 'current')
                ? 'current'
                : groupSteps.length > 0 &&
                    groupSteps.every((step) => step.completed)
                  ? 'complete'
                  : 'pending',
        };
    });
});

watch(nextCleanroomActor, (actor) => {
    if (actor) {
        selectedActorKey.value = actor.key;
    }
});

function pesos(amountCents: number | null): string {
    if (amountCents === null) {
        return 'Pending';
    }

    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amountCents / 100);
}

function evidenceDate(value: string | null): string {
    return value
        ? new Intl.DateTimeFormat('en-PH', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : 'Not recorded';
}

function runNextStep(): void {
    working.value = 'next';
    router.post(
        runNext().url,
        {},
        { preserveScroll: true, onFinish: () => (working.value = null) },
    );
}

function runToSelectedMilestone(): void {
    working.value = 'milestone';
    router.post(
        runToMilestone().url,
        { scenario_id: selectedMilestone.value },
        { preserveScroll: true, onFinish: () => (working.value = null) },
    );
}

function openAsActor(scenario: Scenario, actorKey: string): void {
    if (scenario.application === null || scenario.specimen_id === null) {
        return;
    }

    working.value = `${scenario.id}:${actorKey}`;
    router.post(enterActor([scenario.specimen_id, actorKey]).url);
}

function startCleanroom(): void {
    working.value = 'cleanroom:start';
    router.post(
        startCleanroomRoute().url,
        {
            ceremony: 'nelson_reconciliation_v1',
            source_specimen_id: 'cal-2026-001-2025-new',
        },
        { onFinish: () => (working.value = null) },
    );
}

function startClassicCleanroom(): void {
    working.value = 'cleanroom:start-classic';
    router.post(
        startCleanroomRoute().url,
        { ceremony: 'classic_lifecycle_v1' },
        { onFinish: () => (working.value = null) },
    );
}

function runCleanroomNext(): void {
    if (!props.cleanroom.active) {
        return;
    }

    working.value = 'cleanroom:next';
    const next = props.cleanroom.active.progress.next_step;
    router.post(
        runCleanroomNextRoute(props.cleanroom.active.run.id).url,
        {
            expected_step_key: next?.key ?? null,
            expected_actor_key: next?.actor ?? null,
        },
        { onFinish: () => (working.value = null) },
    );
}

function runCleanroomMilestone(): void {
    if (!props.cleanroom.active) {
        return;
    }

    working.value = 'cleanroom:milestone';
    router.post(
        runCleanroomMilestoneRoute(props.cleanroom.active.run.id).url,
        { step_key: selectedCleanroomMilestone.value },
        { onFinish: () => (working.value = null) },
    );
}

function openCleanroomActor(actor: string): void {
    if (!props.cleanroom.active) {
        return;
    }

    working.value = `cleanroom:actor:${actor}`;
    router.post(
        enterCleanroomActorRoute([props.cleanroom.active.run.id, actor]).url,
    );
}

function activateCleanroomActor(actor: CleanroomActor): void {
    if (actor.is_next) {
        runCleanroomNext();

        return;
    }

    openCleanroomActor(actor.key);
}

function openSelectedActor(): void {
    const actor = props.cleanroom.active?.actors.find(
        (candidate) => candidate.key === selectedActorKey.value,
    );

    if (actor) {
        activateCleanroomActor(actor);
    }
}

async function focusApplication(tab: string): Promise<void> {
    selectedApplicationTab.value = tab;
    await nextTick();
    document
        .querySelector('[data-testid="interactive-application-stage"]')
        ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function closeCleanroom(): void {
    if (!props.cleanroom.active) {
        return;
    }

    working.value = 'cleanroom:close';
    router.post(
        closeCleanroomRoute(props.cleanroom.active.run.id).url,
        {},
        { onFinish: () => (working.value = null) },
    );
}

function simulateQrPhPayment(): void {
    if (!props.cleanroom.active) {
        return;
    }

    if (
        !window.confirm(
            'Simulate full QR Ph payment in this synthetic cleanroom? No real funds will move.',
        )
    ) {
        return;
    }

    working.value = 'cleanroom:simulate-payment';
    router.post(
        `/stakeholder-preview/lifecycle-laboratory/cleanrooms/${props.cleanroom.active.run.id}/simulate-qr-ph-payment`,
        {},
        { preserveScroll: true, onFinish: () => (working.value = null) },
    );
}
</script>

<template>
    <Head title="Lifecycle Laboratory" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8"
    >
        <header
            class="order-1 flex flex-col gap-4 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:flex-row sm:items-end sm:justify-between sm:p-7 dark:border-zinc-800 dark:bg-zinc-950"
        >
            <div>
                <p
                    class="text-xs font-bold tracking-[0.16em] text-zinc-500 uppercase"
                >
                    Municipality of Ipil · Laboratory
                </p>
                <h1
                    class="mt-2 text-3xl font-semibold tracking-tight text-zinc-950 sm:text-4xl dark:text-white"
                >
                    Business Permit Lifecycle
                </h1>
            </div>
            <div class="flex items-center gap-3 sm:text-right">
                <span
                    class="rounded-md border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-black tracking-wide text-amber-900 uppercase dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-200"
                >
                    Laboratory specimen
                </span>
                <span v-if="cleanroom.active" class="text-sm font-semibold">
                    {{ cleanroom.active.progress.completed_steps }} of
                    {{ cleanroom.active.progress.total_steps }} completed
                </span>
            </div>
        </header>

        <section
            data-testid="interactive-laboratory"
            class="order-3 overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm md:order-2 dark:border-zinc-800 dark:bg-zinc-950"
            aria-label="Interactive Laboratory"
        >
            <div
                class="border-b border-zinc-200 p-5 sm:p-6 dark:border-zinc-800"
            >
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="max-w-3xl">
                        <div
                            class="text-xs font-bold tracking-wider text-zinc-500 uppercase"
                        >
                            Current specimen
                        </div>
                        <h2
                            class="mt-1 text-xl font-semibold text-zinc-950 dark:text-white"
                        >
                            {{
                                cleanroom.active?.run.source_specimen
                                    ? 'Source-backed Application'
                                    : 'Executable Application'
                            }}
                        </h2>
                        <p
                            v-if="cleanroom.active"
                            class="mt-2 flex flex-wrap items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300"
                        >
                            <span
                                class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-900"
                            >
                                {{ cleanroom.active.evidence_summary.status }}
                            </span>
                            <span class="font-mono text-xs break-all">{{
                                cleanroom.active.run.public_id
                            }}</span>
                        </p>
                    </div>
                    <div v-if="!cleanroom.active" class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            :disabled="working !== null"
                            class="inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-lg bg-zinc-950 px-5 text-sm font-semibold text-white disabled:opacity-50 dark:bg-amber-300 dark:text-amber-950"
                            @click="startCleanroom"
                        >
                            <FlaskConical class="size-4" /> Start laboratory
                        </button>
                        <button
                            type="button"
                            :disabled="working !== null"
                            class="inline-flex h-11 shrink-0 items-center justify-center rounded-lg border border-zinc-300 px-5 text-sm font-semibold disabled:opacity-50 dark:border-zinc-700"
                            @click="startClassicCleanroom"
                        >
                            Start classic ceremony
                        </button>
                    </div>
                </div>
                <p
                    v-if="!cleanroom.active"
                    class="mt-3 max-w-3xl text-sm leading-6 text-zinc-600 dark:text-zinc-300"
                >
                    No ceremony is active. Retained evidence remains available
                    below and a fresh Classic ceremony will create a new
                    specimen without changing earlier Applications.
                </p>
            </div>

            <div
                v-if="cleanroom.active"
                class="grid gap-6 p-5 sm:p-6 lg:grid-cols-[minmax(0,1fr)_22rem]"
            >
                <div class="min-w-0 space-y-5">
                    <div
                        class="rounded-xl bg-zinc-950 p-5 text-white"
                        data-testid="current-lifecycle-task"
                    >
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="min-w-0">
                                <p
                                    class="text-xs font-bold tracking-wider text-amber-300 uppercase"
                                >
                                    {{
                                        cleanroom.active.progress.complete
                                            ? 'Lifecycle complete'
                                            : 'Current task'
                                    }}
                                </p>
                                <h3 class="mt-1 text-xl font-semibold">
                                    {{
                                        cleanroom.active.progress.next_step
                                            ?.milestone ??
                                        'Business Permit released and verifiable'
                                    }}
                                </h3>
                                <p class="mt-1 text-sm text-zinc-300">
                                    {{
                                        nextCleanroomActor?.label ??
                                        'All required actors complete'
                                    }}
                                </p>
                            </div>
                            <button
                                v-if="
                                    !cleanroom.active.progress.complete &&
                                    !isClassicCleanroom
                                "
                                type="button"
                                :disabled="
                                    working !== null ||
                                    cleanroom.active.progress.blocked
                                "
                                class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-lg bg-amber-300 px-5 py-2 text-sm font-bold text-amber-950 disabled:opacity-50"
                                @click="runCleanroomNext"
                            >
                                <Play class="size-4" />
                                {{
                                    working === 'cleanroom:next'
                                        ? 'Opening…'
                                        : nextCleanroomActor
                                          ? `Continue as ${nextCleanroomActor.label}`
                                          : 'Continue'
                                }}
                            </button>
                            <div
                                v-else-if="!cleanroom.active.progress.complete"
                                class="max-w-sm rounded-lg border border-white/20 px-4 py-3 text-sm"
                            >
                                Sign in normally as
                                <strong>{{ nextCleanroomActor?.label }}</strong
                                >, open the Inbox, complete the task, then sign
                                out.
                            </div>
                            <div v-else class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="rounded-lg bg-white px-3 py-2 text-sm font-semibold text-zinc-950"
                                    @click="focusApplication('application')"
                                >
                                    View Application
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg bg-white px-3 py-2 text-sm font-semibold text-zinc-950"
                                    @click="focusApplication('permit')"
                                >
                                    View Permit
                                </button>
                                <a
                                    v-if="
                                        currentApplicationData?.permit
                                            ?.verification?.view_url
                                    "
                                    :href="
                                        currentApplicationData.permit
                                            .verification.view_url
                                    "
                                    class="rounded-lg border border-white/30 px-3 py-2 text-sm font-semibold"
                                >
                                    Verify
                                </a>
                            </div>
                        </div>
                        <div
                            class="mt-4 h-2 overflow-hidden rounded-full bg-white/10"
                        >
                            <div
                                class="h-full rounded-full bg-amber-300"
                                :style="{
                                    width: `${cleanroom.active.progress.percent}%`,
                                }"
                            />
                        </div>
                    </div>

                    <div
                        v-if="cleanroom.active.progress.blocker"
                        class="flex gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-950 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100"
                        role="alert"
                    >
                        <AlertTriangle class="mt-0.5 size-5 shrink-0" />
                        <div>
                            <p class="font-semibold">
                                This cleanroom cannot advance safely
                            </p>
                            <p class="mt-1 text-sm leading-6">
                                {{ cleanroom.active.progress.blocker }}
                            </p>
                        </div>
                    </div>

                    <ol
                        class="grid gap-2 sm:grid-cols-3"
                        aria-label="Business Permit lifecycle"
                        data-testid="lifecycle-stage-rail"
                    >
                        <li
                            v-for="stage in lifecycleStages"
                            :key="stage.key"
                            :data-stage="stage.key"
                            :data-stage-state="stage.state"
                            :class="
                                stage.state === 'current'
                                    ? 'border-amber-400 bg-amber-50 dark:border-amber-600 dark:bg-amber-950/20'
                                    : 'border-zinc-200 dark:border-zinc-800'
                            "
                            class="rounded-lg border p-3"
                        >
                            <div class="flex items-center gap-2">
                                <span
                                    :class="
                                        stage.state === 'complete'
                                            ? 'bg-emerald-600 text-white'
                                            : stage.state === 'current'
                                              ? 'bg-amber-400 text-amber-950'
                                              : 'bg-zinc-100 text-zinc-400 dark:bg-zinc-800'
                                    "
                                    class="flex size-6 shrink-0 items-center justify-center rounded-full"
                                    ><Check
                                        v-if="stage.state === 'complete'"
                                        class="size-3.5" /><Play
                                        v-else-if="stage.state === 'current'"
                                        class="size-3" /><Circle
                                        v-else
                                        class="size-2.5"
                                /></span>
                                <div class="min-w-0 flex-1">
                                    <h4 class="text-sm font-semibold">
                                        {{ stage.label }}
                                    </h4>
                                    <p class="truncate text-xs text-zinc-500">
                                        {{ stage.summary }}
                                    </p>
                                </div>
                            </div>
                        </li>
                    </ol>

                    <details
                        class="rounded-lg border border-zinc-200 dark:border-zinc-800"
                        data-testid="technical-lifecycle"
                    >
                        <summary
                            class="cursor-pointer px-4 py-3 text-sm font-semibold"
                        >
                            View technical lifecycle ·
                            {{ cleanroom.active.progress.completed_steps }}/{{
                                cleanroom.active.progress.total_steps
                            }}
                            steps
                        </summary>
                        <ol
                            class="grid gap-2 border-t border-zinc-200 p-3 dark:border-zinc-800"
                        >
                            <li
                                v-for="step in cleanroom.active.steps"
                                :key="step.key"
                                class="rounded-md bg-zinc-50 p-3 text-sm dark:bg-zinc-900"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <strong>{{ step.label }}</strong>
                                    <span class="text-xs capitalize">{{
                                        step.status
                                    }}</span>
                                </div>
                                <details
                                    v-if="Object.keys(step.delta).length"
                                    class="mt-2 text-xs text-zinc-600 dark:text-zinc-400"
                                >
                                    <summary
                                        class="cursor-pointer font-semibold"
                                    >
                                        Evidence
                                    </summary>
                                    <dl class="mt-2 grid gap-1">
                                        <div
                                            v-for="(value, label) in step.delta"
                                            :key="label"
                                            class="flex justify-between gap-3"
                                        >
                                            <dt>{{ label }}</dt>
                                            <dd class="font-semibold">
                                                {{ value }}
                                            </dd>
                                        </div>
                                    </dl>
                                </details>
                                <Link
                                    v-if="
                                        step.completed &&
                                        step.key === 'evaluation_initialized'
                                    "
                                    :href="
                                        officeReviewsAssigned([
                                            cleanroom.active.run.id,
                                            step.year,
                                        ])
                                    "
                                    class="mt-2 inline-flex items-center gap-1 text-xs font-semibold underline"
                                >
                                    Review office handoff
                                    <ArrowRight class="size-3.5" />
                                </Link>
                            </li>
                        </ol>
                    </details>
                </div>

                <aside class="space-y-4">
                    <div
                        class="sticky top-4 space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800"
                    >
                        <div
                            v-if="
                                !isClassicCleanroom &&
                                cleanroom.active.payment_simulation.status !==
                                    'not_ready'
                            "
                            class="rounded-xl border border-sky-300 bg-sky-50 p-4 text-sky-950 dark:border-sky-800 dark:bg-sky-950/30 dark:text-sky-100"
                            data-testid="laboratory-payment-simulator"
                        >
                            <p
                                class="text-xs font-black tracking-wide uppercase"
                            >
                                Laboratory payment simulator
                            </p>
                            <p class="mt-1 text-sm font-semibold">
                                Pay Code
                                {{
                                    cleanroom.active.payment_simulation.pay_code
                                }}
                            </p>
                            <p class="mt-1 text-xs leading-5">
                                {{
                                    cleanroom.active.payment_simulation
                                        .status === 'collected'
                                        ? cleanroom.active.payment_simulation
                                              .receipt_coverage_complete
                                            ? 'Payment and complete Official Receipt packet recorded.'
                                            : 'Payment recorded. Continue as Cashier to complete the Official Receipt packet.'
                                        : 'Simulates x-change reporting the full Pay Code amount as collected. No real funds move.'
                                }}
                            </p>
                            <button
                                v-if="
                                    cleanroom.active.payment_simulation
                                        .available
                                "
                                type="button"
                                :disabled="working !== null"
                                class="mt-3 min-h-10 w-full rounded-lg bg-sky-900 px-3 py-2 text-sm font-bold text-white disabled:opacity-50 dark:bg-sky-300 dark:text-sky-950"
                                @click="simulateQrPhPayment"
                            >
                                {{
                                    working === 'cleanroom:simulate-payment'
                                        ? 'Recording…'
                                        : 'Simulate full QR Ph payment'
                                }}
                            </button>
                        </div>
                        <details
                            v-if="!isClassicCleanroom"
                            class="rounded-lg border border-zinc-200 dark:border-zinc-800"
                        >
                            <summary
                                class="cursor-pointer px-3 py-2 text-sm font-semibold"
                            >
                                Laboratory controls
                            </summary>
                            <div
                                class="grid gap-2 border-t border-zinc-200 p-3 dark:border-zinc-800"
                            >
                                <label
                                    for="cleanroom-milestone"
                                    class="text-xs font-semibold"
                                    >Run to milestone</label
                                ><select
                                    id="cleanroom-milestone"
                                    v-model="selectedCleanroomMilestone"
                                    class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                                >
                                    <option
                                        v-for="step in cleanroom.active.steps"
                                        :key="step.key"
                                        :value="step.key"
                                    >
                                        {{ step.year }} · {{ step.milestone }}
                                    </option></select
                                ><button
                                    type="button"
                                    :disabled="
                                        working !== null ||
                                        cleanroom.active.progress.blocked
                                    "
                                    class="h-10 w-full rounded-lg border border-zinc-300 text-sm font-semibold dark:border-zinc-700"
                                    @click="runCleanroomMilestone"
                                >
                                    Continue toward milestone
                                </button>
                            </div>
                        </details>
                        <div v-if="!isClassicCleanroom" class="space-y-2">
                            <label
                                for="cleanroom-actor"
                                class="text-sm font-semibold"
                            >
                                Open Application As
                            </label>
                            <div class="flex gap-2">
                                <select
                                    id="cleanroom-actor"
                                    v-model="selectedActorKey"
                                    class="h-11 min-w-0 flex-1 rounded-lg border border-zinc-300 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                                >
                                    <option
                                        v-for="actor in cleanroom.active.actors"
                                        :key="actor.key"
                                        :value="actor.key"
                                    >
                                        {{ actor.label
                                        }}{{
                                            actor.is_next ? ' · Current' : ''
                                        }}
                                    </option>
                                </select>
                                <button
                                    type="button"
                                    :disabled="working !== null"
                                    class="h-11 rounded-lg border border-zinc-300 px-3 text-sm font-semibold dark:border-zinc-700"
                                    @click="openSelectedActor"
                                >
                                    Open
                                </button>
                            </div>
                        </div>
                        <details
                            v-if="isClassicCleanroom"
                            class="rounded-lg border border-zinc-200 dark:border-zinc-800"
                        >
                            <summary
                                class="cursor-pointer px-3 py-2 text-sm font-semibold"
                            >
                                Classic ceremony activity ·
                                {{
                                    cleanroom.active.classic_ceremony
                                        ?.event_count ?? 0
                                }}
                            </summary>
                            <ol class="grid gap-2 border-t p-3 text-xs">
                                <li
                                    v-for="event in cleanroom.active
                                        .classic_ceremony?.events ?? []"
                                    :key="event.sequence"
                                    class="flex justify-between gap-3"
                                >
                                    <span
                                        >{{ event.actor_key ?? 'system' }} ·
                                        {{
                                            event.event.replaceAll('_', ' ')
                                        }}</span
                                    >
                                    <span
                                        >{{
                                            event.completed_stage_count ?? 0
                                        }}/{{
                                            cleanroom.active.progress
                                                .total_steps
                                        }}</span
                                    >
                                </li>
                            </ol>
                        </details>
                        <button
                            type="button"
                            :disabled="working !== null"
                            class="w-full text-sm font-semibold text-zinc-500 underline underline-offset-4"
                            @click="closeCleanroom"
                        >
                            Close and retain evidence
                        </button>
                        <p class="text-xs leading-5 text-zinc-500">
                            Retaining closes this Laboratory run without
                            deleting or changing its Application. The Laboratory
                            then returns to idle so another ceremony can start.
                        </p>
                    </div>
                </aside>
            </div>
        </section>

        <section
            v-if="cleanroom.history.length > 0"
            data-testid="retained-cleanroom-history"
            class="order-4 overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950"
            aria-label="Retained evidence history"
        >
            <header
                class="border-b border-zinc-200 p-5 sm:p-6 dark:border-zinc-800"
            >
                <div class="flex items-center gap-3">
                    <Archive class="size-5 text-zinc-500" />
                    <div>
                        <p
                            class="text-xs font-bold tracking-wider text-zinc-500 uppercase"
                        >
                            Evidence history
                        </p>
                        <h2 class="mt-1 text-xl font-semibold">
                            Retained cleanrooms
                        </h2>
                    </div>
                </div>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                    These read-only specimens remain preserved independently.
                    Viewing one does not reactivate it.
                </p>
            </header>
            <ul class="divide-y divide-zinc-200 dark:divide-zinc-800">
                <li
                    v-for="item in cleanroom.history"
                    :key="item.public_id"
                    class="grid gap-4 p-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:p-6"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <strong>{{ item.ceremony_label }}</strong>
                            <span
                                class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-bold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200"
                            >
                                {{ item.status }}
                            </span>
                            <span class="text-xs text-zinc-500"
                                >{{ item.progress.completed_steps }} of
                                {{ item.progress.total_steps }}</span
                            >
                        </div>
                        <p
                            class="mt-2 font-mono text-xs break-all text-zinc-500"
                        >
                            {{ item.public_id }}
                        </p>
                        <p
                            class="mt-2 text-sm text-zinc-600 dark:text-zinc-300"
                        >
                            Application
                            {{
                                item.application
                                    ? `#${item.application.id}`
                                    : 'not created'
                            }}
                            <span v-if="item.application?.tracking_reference">
                                ·
                                {{ item.application.tracking_reference }}</span
                            >
                        </p>
                        <p class="mt-1 text-xs text-zinc-500">
                            Created
                            {{ evidenceDate(item.timestamps.created_at) }} ·
                            Completed
                            {{ evidenceDate(item.timestamps.completed_at) }} ·
                            Retained
                            {{ evidenceDate(item.timestamps.retained_at) }}
                        </p>
                    </div>
                    <Link
                        :href="item.view_url"
                        class="inline-flex min-h-11 items-center justify-center rounded-lg border border-zinc-300 px-4 py-2 text-sm font-semibold dark:border-zinc-700"
                    >
                        View evidence
                    </Link>
                </li>
            </ul>
        </section>

        <section
            v-if="currentApplicationData"
            data-testid="interactive-application-stage"
            class="order-2 scroll-mt-4 space-y-4 md:order-3"
            aria-label="Current executable application"
        >
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <p
                        class="text-xs font-bold tracking-wider text-sky-700 uppercase dark:text-sky-300"
                    >
                        Current municipal record
                    </p>
                    <h2 class="text-2xl font-semibold">
                        Executable Application
                    </h2>
                </div>
                <div class="hidden" aria-label="Open as actor">
                    <button
                        v-for="actor in cleanroom.active?.actors ?? []"
                        :key="actor.key"
                        type="button"
                        :disabled="working !== null"
                        :class="
                            actor.is_next
                                ? 'border-amber-500 bg-amber-300 text-amber-950 shadow-md ring-2 ring-amber-200'
                                : 'border-slate-300 bg-white hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900'
                        "
                        class="shrink-0 rounded-full border px-3 py-2 text-xs font-bold disabled:opacity-50"
                        :data-testid="`application-actor-${actor.key}`"
                        :data-actor-relationship="actor.relationship"
                        @click="activateCleanroomActor(actor)"
                    >
                        {{ actor.label }} ·
                        {{
                            actor.is_next
                                ? `Next: ${actor.task?.label}`
                                : actor.relationship_label
                        }}
                        <Play v-if="actor.is_next" class="ml-1 inline size-3" />
                        <ExternalLink v-else class="ml-1 inline size-3" />
                    </button>
                </div>
            </div>
            <ExecutableApplication
                :key="
                    cleanroom.active?.progress.next_step?.key ??
                    'cleanroom-complete'
                "
                :application="currentApplicationData"
                :document="cleanroom.active?.application_document"
                :initial-tab="laboratoryInitialTab"
                mode="workspace"
            />
        </section>

        <details
            data-testid="certified-regression-evidence"
            class="group order-5 overflow-hidden rounded-2xl border border-zinc-300 bg-zinc-50 shadow-sm dark:border-zinc-700 dark:bg-zinc-950"
        >
            <summary
                class="flex cursor-pointer list-none flex-col items-start justify-between gap-4 p-5 outline-none marker:hidden focus-visible:ring-2 focus-visible:ring-amber-500 sm:flex-row sm:items-center sm:p-6"
            >
                <span class="min-w-0">
                    <span
                        class="block text-xs font-black tracking-wider text-zinc-500 uppercase"
                        >Automated reference evidence</span
                    >
                    <span
                        class="mt-1 block text-xl font-black text-zinc-950 dark:text-white"
                        >Certified Regression Evidence</span
                    >
                    <span
                        class="mt-1 block max-w-3xl text-sm leading-6 text-zinc-600 dark:text-zinc-400"
                    >
                        Scenario 01 and Scenario 02 are deterministic
                        certification specimens—not the interactive Laboratory.
                        Expand only to generate or inspect reference evidence.
                    </span>
                </span>
                <span
                    class="shrink-0 rounded-full bg-zinc-200 px-3 py-1.5 text-xs font-bold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200"
                >
                    {{ laboratory.progress.completed_scenarios }}/{{
                        laboratory.progress.total_scenarios
                    }}
                    certified
                </span>
            </summary>

            <div
                class="space-y-6 border-t border-zinc-200 p-4 sm:p-6 dark:border-zinc-800"
            >
                <section
                    class="grid gap-4 rounded-xl border border-zinc-200 bg-white p-4 sm:grid-cols-[1fr_auto] sm:items-end dark:border-zinc-800 dark:bg-zinc-950"
                    aria-label="Generate certified reference evidence"
                >
                    <div class="space-y-2">
                        <p
                            class="text-xs font-bold tracking-wider text-zinc-500 uppercase"
                        >
                            Automated certification runner
                        </p>
                        <label
                            for="milestone"
                            class="text-sm font-semibold text-zinc-900 dark:text-zinc-100"
                            >Generate through reference</label
                        >
                        <select
                            id="milestone"
                            v-model="selectedMilestone"
                            :disabled="working !== null"
                            class="h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-900 outline-none focus-visible:ring-2 focus-visible:ring-amber-500 sm:max-w-md dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                        >
                            <option
                                v-for="scenario in laboratory.scenarios"
                                :key="scenario.id"
                                :value="scenario.id"
                            >
                                {{ scenario.application_year }} ·
                                {{ scenario.milestone }}
                            </option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <button
                            type="button"
                            :disabled="
                                working !== null || laboratory.progress.complete
                            "
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-zinc-950 px-4 text-sm font-semibold text-white outline-none hover:bg-zinc-800 focus-visible:ring-2 focus-visible:ring-amber-500 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-amber-300 dark:text-amber-950 dark:hover:bg-amber-200"
                            @click="runNextStep"
                        >
                            <Play class="size-4" aria-hidden="true" />
                            {{
                                working === 'next'
                                    ? 'Running…'
                                    : laboratory.progress.complete
                                      ? 'References complete'
                                      : 'Generate next certification'
                            }}
                        </button>
                        <button
                            type="button"
                            :disabled="working !== null"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-zinc-300 px-4 text-sm font-semibold text-zinc-900 outline-none hover:bg-zinc-50 focus-visible:ring-2 focus-visible:ring-amber-500 disabled:cursor-wait disabled:opacity-50 dark:border-zinc-700 dark:text-zinc-100 dark:hover:bg-zinc-900"
                            @click="runToSelectedMilestone"
                        >
                            <ArrowRight class="size-4" aria-hidden="true" />
                            {{
                                working === 'milestone'
                                    ? 'Running…'
                                    : 'Generate selected references'
                            }}
                        </button>
                    </div>
                </section>

                <section
                    class="space-y-6"
                    aria-label="Automated certified reference scenarios"
                >
                    <article
                        v-for="scenario in laboratory.scenarios"
                        :key="scenario.id"
                        :data-scenario-id="scenario.id"
                        data-classification="automated-certification-specimen"
                        class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950"
                    >
                        <div
                            class="grid gap-5 border-b border-zinc-200 p-5 sm:grid-cols-[1fr_auto] sm:items-start sm:p-6 dark:border-zinc-800"
                        >
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200"
                                    >
                                        <CalendarDays
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />{{ scenario.effective_date }}
                                    </span>
                                    <span
                                        class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-800 dark:bg-sky-400/15 dark:text-sky-300"
                                    >
                                        Automated certification specimen
                                    </span>
                                    <span
                                        :class="
                                            scenario.status === 'completed'
                                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-400/15 dark:text-emerald-300'
                                                : 'bg-amber-100 text-amber-800 dark:bg-amber-400/15 dark:text-amber-300'
                                        "
                                        class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                    >
                                        {{
                                            scenario.status === 'completed'
                                                ? 'Certified & persisted'
                                                : 'Ready to run'
                                        }}
                                    </span>
                                </div>
                                <h2
                                    class="text-xl font-semibold text-zinc-950 dark:text-white"
                                >
                                    {{ scenario.label }}
                                </h2>
                                <p
                                    class="text-sm leading-6 text-zinc-600 dark:text-zinc-400"
                                >
                                    {{ scenario.summary }}
                                </p>
                            </div>
                            <div
                                v-if="scenario.application"
                                class="rounded-xl bg-zinc-50 px-4 py-3 text-sm dark:bg-zinc-900"
                            >
                                <p
                                    class="font-semibold text-zinc-950 dark:text-white"
                                >
                                    {{ scenario.application.business_name }}
                                </p>
                                <p
                                    class="mt-1 text-zinc-600 dark:text-zinc-400"
                                >
                                    {{ scenario.application.owner_name }} ·
                                    {{
                                        scenario.application.status.replace(
                                            '_',
                                            ' ',
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="grid gap-6 p-5 sm:p-6 lg:grid-cols-[minmax(0,1fr)_21rem]"
                        >
                            <ol class="space-y-0">
                                <li
                                    v-for="event in scenario.events"
                                    :key="event.key"
                                    class="grid grid-cols-[2rem_minmax(0,1fr)] gap-3"
                                >
                                    <div class="flex flex-col items-center">
                                        <span
                                            :class="
                                                event.status === 'completed'
                                                    ? 'bg-emerald-600 text-white'
                                                    : 'border border-zinc-300 bg-white text-zinc-400 dark:border-zinc-700 dark:bg-zinc-950'
                                            "
                                            class="flex size-7 shrink-0 items-center justify-center rounded-full"
                                        >
                                            <Check
                                                v-if="
                                                    event.status === 'completed'
                                                "
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                            <Circle
                                                v-else
                                                class="size-3"
                                                aria-hidden="true"
                                            />
                                        </span>
                                        <span
                                            class="h-full min-h-5 w-px bg-zinc-200 last:hidden dark:bg-zinc-800"
                                            aria-hidden="true"
                                        />
                                    </div>
                                    <div class="min-w-0 pb-5">
                                        <h3
                                            class="text-sm font-semibold text-zinc-950 dark:text-white"
                                        >
                                            {{ event.label }}
                                        </h3>
                                        <p
                                            class="mt-1 text-sm leading-5 text-zinc-600 dark:text-zinc-400"
                                        >
                                            {{ event.description }}
                                        </p>
                                        <dl class="mt-2 flex flex-wrap gap-2">
                                            <div
                                                v-for="(
                                                    value, label
                                                ) in event.delta"
                                                :key="label"
                                                class="min-w-0 rounded-md bg-zinc-100 px-2.5 py-1.5 text-xs dark:bg-zinc-900"
                                            >
                                                <dt
                                                    class="inline font-medium text-zinc-600 dark:text-zinc-400"
                                                >
                                                    {{ label }}
                                                </dt>
                                                <dd
                                                    class="inline font-semibold text-zinc-950 dark:text-white"
                                                >
                                                    {{ value }}
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>
                                </li>
                            </ol>

                            <aside class="space-y-4">
                                <div
                                    class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800"
                                >
                                    <div class="flex items-center gap-2">
                                        <WalletCards
                                            class="size-5 text-amber-600"
                                            aria-hidden="true"
                                        />
                                        <h3
                                            class="font-semibold text-zinc-950 dark:text-white"
                                        >
                                            Financial working paper
                                        </h3>
                                    </div>
                                    <dl class="mt-4 space-y-2 text-sm">
                                        <div
                                            v-for="line in scenario
                                                .financial_working_paper.lines"
                                            :key="line.label"
                                            class="flex justify-between gap-3"
                                        >
                                            <dt
                                                class="text-zinc-600 dark:text-zinc-400"
                                            >
                                                {{ line.label }}
                                            </dt>
                                            <dd
                                                class="font-medium text-zinc-950 dark:text-white"
                                            >
                                                {{ pesos(line.amount_cents) }}
                                            </dd>
                                        </div>
                                        <div
                                            class="flex justify-between gap-3 border-t border-zinc-200 pt-2 font-semibold dark:border-zinc-800"
                                        >
                                            <dt>Total assessed</dt>
                                            <dd>
                                                {{
                                                    pesos(
                                                        scenario
                                                            .financial_working_paper
                                                            .total_amount_cents,
                                                    )
                                                }}
                                            </dd>
                                        </div>
                                        <div
                                            class="flex justify-between gap-3 text-amber-700 dark:text-amber-300"
                                        >
                                            <dt>Payable balance</dt>
                                            <dd class="font-semibold">
                                                {{
                                                    pesos(
                                                        scenario
                                                            .financial_working_paper
                                                            .payable_balance_cents,
                                                    )
                                                }}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>

                                <div
                                    v-if="scenario.actors.length > 0"
                                    class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800"
                                >
                                    <div class="flex items-center gap-2">
                                        <Landmark
                                            class="size-5 text-amber-600"
                                            aria-hidden="true"
                                        />
                                        <h3
                                            class="font-semibold text-zinc-950 dark:text-white"
                                        >
                                            Inspect reference as actor
                                        </h3>
                                    </div>
                                    <p
                                        class="mt-2 text-xs leading-5 text-zinc-500"
                                    >
                                        Reference-only inspection using
                                        manifest-owned scenario identities. This
                                        is not interactive Laboratory work.
                                    </p>
                                    <div class="mt-3 grid gap-2">
                                        <button
                                            v-for="actor in scenario.actors"
                                            :key="actor.key"
                                            type="button"
                                            :disabled="working !== null"
                                            class="inline-flex min-w-0 items-center justify-between gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-left text-sm font-medium text-zinc-800 outline-none hover:bg-zinc-50 focus-visible:ring-2 focus-visible:ring-amber-500 disabled:opacity-50 dark:border-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-900"
                                            @click="
                                                openAsActor(scenario, actor.key)
                                            "
                                        >
                                            <span class="truncate">{{
                                                actor.label
                                            }}</span>
                                            <ExternalLink
                                                class="size-4 shrink-0"
                                                aria-hidden="true"
                                            />
                                        </button>
                                    </div>
                                </div>
                            </aside>
                        </div>
                    </article>
                </section>
            </div>
        </details>

        <details
            class="order-5 rounded-xl border border-zinc-300 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-950"
            data-testid="laboratory-details"
        >
            <summary
                class="flex cursor-pointer items-center gap-2 p-4 text-sm font-semibold"
            >
                <ShieldCheck class="size-5" aria-hidden="true" />
                Laboratory details
            </summary>
            <div
                class="grid gap-3 border-t border-zinc-200 p-4 text-sm leading-6 text-zinc-600 dark:border-zinc-800 dark:text-zinc-400"
            >
                <p v-if="cleanroom.active?.run.source_specimen">
                    Registry identity from
                    {{ cleanroom.active.run.source_specimen.calibration_id }};
                    the 2025 New Application chronology is reconstructed.
                    Payment is simulated and no real funds move.
                </p>
                <p>
                    This local/UAT surface requires the synthetic Preview safety
                    profile and Management account. Collection, receipt,
                    certification, issuance, and release remain synthetic-only.
                    No reset, migration, production authority, or destructive
                    database action is available.
                </p>
            </div>
        </details>
    </div>
</template>
