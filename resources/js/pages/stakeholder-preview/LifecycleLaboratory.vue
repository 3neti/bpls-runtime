<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
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
    enterActor,
    runNext,
    runToMilestone,
} from '@/actions/App/Http/Controllers/LifecycleLaboratoryController';
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

const props = defineProps<{
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
const progressPercent = computed(
    () =>
        (props.laboratory.progress.completed_scenarios /
            props.laboratory.progress.total_scenarios) *
        100,
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
                        Stakeholder Preview · Synthetic product laboratory
                    </div>
                    <div class="space-y-2">
                        <h1
                            class="text-3xl font-semibold tracking-tight sm:text-4xl"
                        >
                            Browser Lifecycle Laboratory
                        </h1>
                        <p
                            class="max-w-3xl text-sm leading-6 text-zinc-300 sm:text-base"
                        >
                            Run the certified 2025 New Business Permit and 2026
                            Renewal chronology through the same scenario driver
                            used by CLI certification.
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
                            >No permit issuance</span
                        >
                    </div>
                </div>

                <div
                    class="space-y-3 rounded-xl border border-white/10 bg-white/5 p-4"
                >
                    <div
                        class="flex items-center justify-between gap-3 text-sm"
                    >
                        <span class="font-semibold">Two-year chronology</span>
                        <span
                            >{{ laboratory.progress.completed_scenarios }}/{{
                                laboratory.progress.total_scenarios
                            }}</span
                        >
                    </div>
                    <div
                        class="h-2 overflow-hidden rounded-full bg-white/10"
                        aria-hidden="true"
                    >
                        <div
                            class="h-full rounded-full bg-amber-300 transition-all"
                            :style="{ width: `${progressPercent}%` }"
                        />
                    </div>
                    <p class="text-xs leading-5 text-zinc-400">
                        {{ laboratory.safety.execution_boundary }}
                    </p>
                </div>
            </div>
        </header>

        <section
            class="grid gap-4 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm sm:grid-cols-[1fr_auto] sm:items-end sm:p-5 dark:border-zinc-800 dark:bg-zinc-950"
        >
            <div class="space-y-2">
                <label
                    for="milestone"
                    class="text-sm font-semibold text-zinc-900 dark:text-zinc-100"
                    >Run to milestone</label
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
                    :disabled="working !== null || laboratory.progress.complete"
                    class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-zinc-950 px-4 text-sm font-semibold text-white outline-none hover:bg-zinc-800 focus-visible:ring-2 focus-visible:ring-amber-500 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-amber-300 dark:text-amber-950 dark:hover:bg-amber-200"
                    @click="runNextStep"
                >
                    <Play class="size-4" aria-hidden="true" />
                    {{
                        working === 'next'
                            ? 'Running…'
                            : laboratory.progress.complete
                              ? 'Chronology complete'
                              : 'Run Next Step'
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
                            : 'Run to Milestone'
                    }}
                </button>
            </div>
        </section>

        <section
            class="space-y-6"
            aria-label="Continuous two-year municipal timeline"
        >
            <article
                v-for="scenario in laboratory.scenarios"
                :key="scenario.id"
                :data-scenario-id="scenario.id"
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
                        <p class="font-semibold text-zinc-950 dark:text-white">
                            {{ scenario.application.business_name }}
                        </p>
                        <p class="mt-1 text-zinc-600 dark:text-zinc-400">
                            {{ scenario.application.owner_name }} ·
                            {{ scenario.application.status.replace('_', ' ') }}
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
                                        v-if="event.status === 'completed'"
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
                                        v-for="(value, label) in event.delta"
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
                                                scenario.financial_working_paper
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
                                                scenario.financial_working_paper
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
                                    Open as exact scenario actor
                                </h3>
                            </div>
                            <p class="mt-2 text-xs leading-5 text-zinc-500">
                                These are manifest-owned scenario identities,
                                separate from generic Preview personas.
                            </p>
                            <div class="mt-3 grid gap-2">
                                <button
                                    v-for="actor in scenario.actors"
                                    :key="actor.key"
                                    type="button"
                                    :disabled="working !== null"
                                    class="inline-flex min-w-0 items-center justify-between gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-left text-sm font-medium text-zinc-800 outline-none hover:bg-zinc-50 focus-visible:ring-2 focus-visible:ring-amber-500 disabled:opacity-50 dark:border-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-900"
                                    @click="openAsActor(scenario, actor.key)"
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

        <aside
            class="grid gap-3 rounded-2xl border border-amber-300 bg-amber-50 p-5 text-sm text-amber-950 sm:grid-cols-[auto_1fr] dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-200"
        >
            <ShieldCheck class="size-6" aria-hidden="true" />
            <div class="space-y-1">
                <p class="font-semibold">Fail-closed laboratory boundary</p>
                <p class="leading-6">
                    This surface is absent in production and requires the exact
                    synthetic Preview safety profile plus the exact Management
                    preview account. It offers no reset, arbitrary scenario
                    identifier, collection, permit issuance, or destructive
                    database operation.
                </p>
            </div>
        </aside>
    </div>
</template>
