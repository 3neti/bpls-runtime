<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
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
import { computed, ref } from 'vue';
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
    run: { id: number; public_id: string; status: string };
    progress: {
        completed_steps: number;
        total_steps: number;
        percent: number;
        complete: boolean;
        blocked: boolean;
        blocker: string | null;
        profile_kind:
            'pending_intake' | 'certified_two_year' | 'registry_source_replay';
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
        receipt_id: number | null;
        status: 'not_ready' | 'awaiting_simulation' | 'collected';
    };
};

const props = defineProps<{
    authorizedLegacyReview: boolean;
    cleanroom: {
        active: CleanroomState | null;
        history: {
            public_id: string;
            closed_at: string;
            new_application_id: number | null;
            renewal_application_id: number | null;
        }[];
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
const laboratoryInitialTab = computed(() => {
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
const visibleCleanroomSteps = computed(
    () =>
        props.cleanroom.active?.steps.filter(
            (step) => step.status !== 'pending',
        ) ?? [],
);
const pendingCleanroomStepCount = computed(
    () =>
        props.cleanroom.active?.steps.filter(
            (step) => step.status === 'pending',
        ).length ?? 0,
);

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
        {},
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

function closeCleanroom(): void {
    if (
        !props.cleanroom.active ||
        !window.confirm(
            'Close this cleanroom and retain all synthetic evidence? Nothing will be deleted.',
        )
    ) {
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
            class="overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-950 text-white shadow-sm dark:border-zinc-800"
        >
            <div
                class="grid gap-7 p-6 sm:p-8 lg:grid-cols-[1fr_22rem] lg:items-end"
            >
                <div class="space-y-4">
                    <div
                        class="flex items-center gap-2 text-sm font-semibold text-amber-300"
                    >
                        <FlaskConical class="size-5" aria-hidden="true" />
                        {{
                            authorizedLegacyReview
                                ? 'Private review · Authorized legacy source laboratory'
                                : 'Stakeholder Preview · Synthetic product laboratory'
                        }}
                    </div>
                    <div class="space-y-2">
                        <h1
                            class="text-3xl font-semibold tracking-tight sm:text-4xl"
                        >
                            Executable Application Laboratory
                        </h1>
                        <p
                            class="max-w-3xl text-sm leading-6 text-zinc-300 sm:text-base"
                        >
                            Watch one familiar permit Application accumulate
                            canonical municipal facts. The Laboratory conducts;
                            the Application is the stage.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs font-semibold">
                        <span
                            class="rounded-full bg-emerald-400/15 px-3 py-1.5 text-emerald-300"
                            >Local / UAT only</span
                        >
                        <span
                            class="rounded-full bg-white/10 px-3 py-1.5 text-zinc-200"
                            >No reset or migrate action</span
                        >
                        <span
                            class="rounded-full bg-white/10 px-3 py-1.5 text-zinc-200"
                            >{{
                                authorizedLegacyReview
                                    ? 'Authorized legacy source · Laboratory actions only'
                                    : 'Synthetic specimen only'
                            }}</span
                        >
                    </div>
                </div>

                <div
                    class="space-y-3 rounded-xl border border-white/10 bg-white/5 p-4"
                >
                    <div
                        class="flex items-center justify-between gap-3 text-sm"
                    >
                        <span class="font-semibold"
                            >Interactive laboratory</span
                        >
                        <span
                            class="rounded-full bg-white/10 px-2.5 py-1 text-xs font-semibold"
                        >
                            {{ cleanroom.active ? 'Active' : 'Ready to start' }}
                        </span>
                    </div>
                    <div
                        class="h-2 overflow-hidden rounded-full bg-white/10"
                        aria-hidden="true"
                    >
                        <div
                            class="h-full rounded-full bg-amber-300 transition-all"
                            :style="{
                                width: `${cleanroom.active?.progress.percent ?? 0}%`,
                            }"
                        />
                    </div>
                    <p class="text-xs leading-5 text-zinc-400">
                        {{
                            cleanroom.active
                                ? `${cleanroom.active.progress.completed_steps}/${cleanroom.active.progress.total_steps} canonical steps completed.`
                                : 'Start empty, submit the real application form, and work one Application actor by actor.'
                        }}
                    </p>
                </div>
            </div>
        </header>

        <section
            data-testid="interactive-laboratory"
            class="overflow-hidden rounded-2xl border-2 border-amber-300 bg-white shadow-sm dark:border-amber-700 dark:bg-zinc-950"
            aria-label="Interactive Laboratory"
        >
            <div
                class="border-b border-amber-200 bg-amber-50 p-5 sm:p-6 dark:border-amber-800 dark:bg-amber-950/30"
            >
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="max-w-3xl space-y-2">
                        <div
                            class="text-xs font-bold tracking-wider text-amber-700 uppercase dark:text-amber-300"
                        >
                            Interactive laboratory · real product forms
                        </div>
                        <h2
                            class="text-2xl font-semibold text-zinc-950 dark:text-white"
                        >
                            {{
                                cleanroom.active?.progress.profile_kind ===
                                'registry_source_replay'
                                    ? 'Work one source-backed Application through its real lifecycle'
                                    : 'Build one Application from its first submitted form'
                            }}
                        </h2>
                        <p
                            class="text-sm leading-6 text-zinc-700 dark:text-zinc-300"
                        >
                            Run Next Step either performs one bounded canonical
                            system action or signs you in as the exact cleanroom
                            actor and opens the real form you must complete. The
                            screen recognizes completion from persisted
                            municipal state.
                        </p>
                        <p
                            v-if="cleanroom.active?.progress.profile_statement"
                            class="text-sm leading-6 text-zinc-700 dark:text-zinc-300"
                        >
                            {{ cleanroom.active.progress.profile_statement }}
                        </p>
                    </div>
                    <button
                        v-if="!cleanroom.active"
                        type="button"
                        :disabled="working !== null"
                        class="inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-lg bg-zinc-950 px-5 text-sm font-semibold text-white disabled:opacity-50 dark:bg-amber-300 dark:text-amber-950"
                        @click="startCleanroom"
                    >
                        <FlaskConical class="size-4" /> Start Interactive
                        Laboratory
                    </button>
                </div>
            </div>

            <div
                v-if="cleanroom.active"
                class="grid gap-6 p-5 sm:p-6 lg:grid-cols-[minmax(0,1fr)_22rem]"
            >
                <div class="min-w-0 space-y-5">
                    <div class="rounded-xl bg-zinc-950 p-5 text-white">
                        <div
                            class="flex flex-wrap items-center justify-between gap-3"
                        >
                            <div>
                                <p class="text-xs text-zinc-400">
                                    Cleanroom
                                    {{ cleanroom.active.run.public_id }}
                                </p>
                                <h3 class="mt-1 text-lg font-semibold">
                                    {{
                                        cleanroom.active.progress.next_step
                                            ? `Next: ${cleanroom.active.progress.next_step.milestone}`
                                            : 'Two-year chronology complete'
                                    }}
                                </h3>
                                <p class="mt-1 text-sm leading-5 text-zinc-300">
                                    {{
                                        cleanroom.active.progress.next_step
                                            ?.description ??
                                        cleanroom.active.progress
                                            .completion_message
                                    }}
                                </p>
                            </div>
                            <span
                                class="rounded-full bg-white/10 px-3 py-1.5 text-sm font-semibold"
                                >{{
                                    cleanroom.active.progress.completed_steps
                                }}/{{
                                    cleanroom.active.progress.total_steps
                                }}
                                steps</span
                            >
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

                    <ol class="space-y-2" aria-label="Cleanroom journey so far">
                        <li
                            v-for="step in visibleCleanroomSteps"
                            :key="step.key"
                            :class="
                                step.status === 'current'
                                    ? 'border-amber-400 bg-amber-50 dark:border-amber-600 dark:bg-amber-950/20'
                                    : 'border-zinc-200 dark:border-zinc-800'
                            "
                            class="rounded-xl border p-4"
                        >
                            <div class="flex items-start gap-3">
                                <span
                                    :class="
                                        step.completed
                                            ? 'bg-emerald-600 text-white'
                                            : step.status === 'current'
                                              ? 'bg-amber-400 text-amber-950'
                                              : 'bg-zinc-100 text-zinc-400 dark:bg-zinc-800'
                                    "
                                    class="flex size-7 shrink-0 items-center justify-center rounded-full"
                                    ><Check
                                        v-if="step.completed"
                                        class="size-4" /><Play
                                        v-else-if="step.status === 'current'"
                                        class="size-3.5" /><Circle
                                        v-else
                                        class="size-3"
                                /></span>
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h4
                                            class="font-semibold text-zinc-950 dark:text-white"
                                        >
                                            {{ step.year }} ·
                                            {{
                                                step.status === 'current'
                                                    ? `Next: ${step.milestone}`
                                                    : step.label
                                            }}
                                        </h4>
                                        <span
                                            :class="
                                                step.status === 'current'
                                                    ? 'bg-amber-200 text-amber-950 dark:bg-amber-400 dark:text-amber-950'
                                                    : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300'
                                            "
                                            class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                            >{{
                                                step.status === 'current'
                                                    ? 'Next task'
                                                    : step.mode ===
                                                        'product_form'
                                                      ? 'Real form'
                                                      : step.mode ===
                                                          'system_action'
                                                        ? 'Canonical action'
                                                        : 'Boundary'
                                            }}</span
                                        >
                                    </div>
                                    <p
                                        class="mt-1 text-sm leading-5 text-zinc-600 dark:text-zinc-400"
                                    >
                                        {{ step.description }}
                                    </p>
                                    <div
                                        v-if="
                                            step.completed &&
                                            Object.keys(step.delta).length
                                        "
                                        class="mt-2 flex flex-wrap gap-2"
                                    >
                                        <span
                                            v-for="(value, label) in step.delta"
                                            :key="label"
                                            class="rounded-md bg-white px-2 py-1 text-xs shadow-sm dark:bg-zinc-900"
                                            ><span class="text-zinc-500"
                                                >{{ label }} </span
                                            ><strong>{{ value }}</strong></span
                                        >
                                    </div>
                                    <Link
                                        v-if="
                                            step.completed &&
                                            (step.key ===
                                                'evaluation_initialized' ||
                                                step.key ===
                                                    'renewal_evaluation_initialized')
                                        "
                                        :href="
                                            officeReviewsAssigned([
                                                cleanroom.active.run.id,
                                                step.year,
                                            ])
                                        "
                                        class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-amber-800 underline decoration-amber-300 underline-offset-4 hover:text-amber-950 dark:text-amber-300 dark:hover:text-amber-200"
                                    >
                                        Review office handoff
                                        <ArrowRight class="size-4" />
                                    </Link>
                                </div>
                            </div>
                        </li>
                    </ol>

                    <p
                        v-if="pendingCleanroomStepCount > 0"
                        class="rounded-lg border border-dashed border-zinc-300 px-4 py-3 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400"
                    >
                        {{ pendingCleanroomStepCount }} later
                        {{ pendingCleanroomStepCount === 1 ? 'step' : 'steps' }}
                        will appear here only when completed or ready to act on.
                    </p>
                </div>

                <aside class="space-y-4">
                    <div
                        class="sticky top-4 space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800"
                    >
                        <div
                            v-if="
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
                                        ? 'Synthetic Collection recorded. Enter as Cashier to issue and print the Official Receipt.'
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
                        <button
                            type="button"
                            :disabled="
                                working !== null ||
                                cleanroom.active.progress.complete ||
                                cleanroom.active.progress.blocked
                            "
                            class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-zinc-950 px-4 py-3 text-sm font-bold text-white disabled:opacity-50 dark:bg-amber-300 dark:text-amber-950"
                            @click="runCleanroomNext"
                        >
                            <Play class="size-4" />{{
                                working === 'cleanroom:next'
                                    ? 'Opening…'
                                    : cleanroom.active.progress.complete
                                      ? 'Cleanroom complete'
                                      : cleanroom.active.progress.blocked
                                        ? 'Cleanroom blocked'
                                        : nextCleanroomActor
                                          ? `Continue as ${nextCleanroomActor.label} — ${nextCleanroomActor.task?.label}`
                                          : 'Run Next Step'
                            }}
                        </button>
                        <div class="space-y-2">
                            <label
                                for="cleanroom-milestone"
                                class="text-sm font-semibold"
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
                        <div>
                            <h3 class="text-sm font-semibold">
                                Application actors
                            </h3>
                            <div class="mt-2 grid gap-2">
                                <button
                                    v-for="actor in cleanroom.active.actors"
                                    :key="actor.key"
                                    type="button"
                                    :disabled="working !== null"
                                    :class="
                                        actor.is_next
                                            ? 'border-amber-400 bg-amber-100 text-amber-950 ring-2 ring-amber-300 dark:border-amber-500 dark:bg-amber-400 dark:text-amber-950'
                                            : 'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-900'
                                    "
                                    class="flex items-center justify-between gap-3 rounded-lg border px-3 py-2 text-left text-sm disabled:opacity-50"
                                    :data-actor-relationship="
                                        actor.relationship
                                    "
                                    @click="activateCleanroomActor(actor)"
                                >
                                    <span class="min-w-0">
                                        <strong class="block">{{
                                            actor.label
                                        }}</strong>
                                        <span class="block text-xs opacity-75">
                                            {{
                                                actor.is_next
                                                    ? actor.task?.label
                                                    : actor.relationship_label
                                            }}
                                        </span>
                                    </span>
                                    <Play
                                        v-if="actor.is_next"
                                        class="size-4 shrink-0"
                                    />
                                    <ExternalLink
                                        v-else
                                        class="size-4 shrink-0"
                                    />
                                </button>
                            </div>
                        </div>
                        <div
                            class="rounded-lg bg-zinc-50 p-3 text-xs leading-5 text-zinc-600 dark:bg-zinc-900 dark:text-zinc-400"
                        >
                            <strong class="text-zinc-900 dark:text-white"
                                >Financial destination</strong
                            ><br />Retail Trading ₱330 + Food Service ₱540 +
                            governed Business Inspection Fee ₱350 = ₱1,220 per
                            year.
                        </div>
                        <button
                            type="button"
                            :disabled="working !== null"
                            class="w-full text-sm font-semibold text-zinc-500 underline underline-offset-4"
                            @click="closeCleanroom"
                        >
                            Close and retain evidence
                        </button>
                    </div>
                </aside>
            </div>
        </section>

        <section
            v-if="currentApplicationData"
            data-testid="interactive-application-stage"
            class="space-y-4"
            aria-label="Current executable application"
        >
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <p
                        class="text-xs font-black tracking-wider text-sky-700 uppercase dark:text-sky-300"
                    >
                        Interactive application stage
                    </p>
                    <h2 class="text-2xl font-black">
                        The same Application after every canonical act
                    </h2>
                </div>
                <div
                    class="flex max-w-full gap-2 overflow-x-auto pb-1"
                    aria-label="Open as actor"
                >
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
            class="group overflow-hidden rounded-2xl border border-zinc-300 bg-zinc-50 shadow-sm dark:border-zinc-700 dark:bg-zinc-950"
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

        <aside
            class="grid gap-3 rounded-2xl border border-amber-300 bg-amber-50 p-5 text-sm text-amber-950 sm:grid-cols-[auto_1fr] dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-200"
        >
            <ShieldCheck class="size-6" aria-hidden="true" />
            <div class="space-y-1">
                <p class="font-semibold">Fail-closed laboratory boundary</p>
                <p class="leading-6">
                    This surface is absent in production and requires the exact
                    synthetic Preview safety profile plus the exact Management
                    preview account. Collection, receipt, certification,
                    issuance, and release remain synthetic-only; it offers no
                    reset, arbitrary scenario identifier, production authority,
                    or destructive database operation.
                </p>
            </div>
        </aside>
    </div>
</template>
