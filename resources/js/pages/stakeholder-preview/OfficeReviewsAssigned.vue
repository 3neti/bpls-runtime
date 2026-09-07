<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Building2,
    CheckCircle2,
    ChevronDown,
    CircleDashed,
    ClipboardList,
    FileCheck2,
    LockKeyhole,
} from '@lucide/vue';
import { computed } from 'vue';
import {
    confirmRoutineOfficeDefaults,
    runNext as runCleanroomNextRoute,
    simulateOfficeReviews,
} from '@/actions/App/Http/Controllers/LifecycleCleanroomController';
import { index as laboratoryIndex } from '@/actions/App/Http/Controllers/LifecycleLaboratoryController';
import AppLayout from '@/layouts/AppLayout.vue';

type Handoff = {
    run: { id: number; public_id: string };
    application: {
        id: number;
        business_name: string;
        owner_name: string;
        tracking_reference: string | null;
        type: string;
        year: number;
        submitted_at: string | null;
        activities: {
            name: string;
            code: string | null;
            declared_gross_sales_cents: number;
            capital_investment_cents: number;
        }[];
    };
    summary: {
        office_count: number;
        responsibility_count: number;
        resolved_count: number;
        assessment_created: boolean;
        payment_order_count: number;
        routine_default_count: number;
        inspection_simulation_count: number;
        manual_review_count: number;
    };
    offices: {
        code: string;
        label: string;
        activity: string | null;
        reason: string;
        required_work: string;
        status: string;
        responsibility_count: number;
        resolved_count: number;
        action_url: string;
        is_next: boolean;
        responsibilities: {
            label: string;
            default_amount_cents: number | null;
            inspection_required: boolean;
            status: string;
        }[];
    }[];
    routing: {
        situational_context: string;
        determined_by: string;
        determined_at: string;
    };
    audit: {
        routing_determination_id: number;
        evaluation_id: number;
        evaluation_version: number;
        evaluation_fingerprint: string;
        responsibility_profile_version: string | null;
        classification: string;
        production_liability: false;
    };
};

const props = defineProps<{ handoff: Handoff }>();
const nextOffice = computed(() =>
    props.handoff.offices.find((office) => office.is_next),
);
const completedOfficeCount = computed(
    () =>
        props.handoff.offices.filter((office) => office.status === 'Complete')
            .length,
);
const allOfficeReviewsComplete = computed(
    () =>
        props.handoff.offices.length > 0 &&
        completedOfficeCount.value === props.handoff.offices.length,
);

const money = (amountCents: number): string =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amountCents / 100);

const dateTime = (value: string | null): string =>
    value
        ? new Intl.DateTimeFormat('en-PH', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : 'Not recorded';

function continueOfficeReviews(): void {
    router.post(runCleanroomNextRoute(props.handoff.run.id).url);
}

function completeRoutineConfirmations(): void {
    router.post(
        confirmRoutineOfficeDefaults([
            props.handoff.run.id,
            props.handoff.application.year,
        ]).url,
        {},
        { preserveScroll: true },
    );
}

function simulateRemainingOfficeReviews(): void {
    if (
        !window.confirm(
            'Simulate the remaining inspection-bearing office reviews? Every resulting audit record will state that no real inspection occurred.',
        )
    ) {
        return;
    }

    router.post(
        simulateOfficeReviews([
            props.handoff.run.id,
            props.handoff.application.year,
        ]).url,
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Office reviews assigned" />

    <AppLayout>
        <div
            class="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8"
        >
            <header
                class="overflow-hidden rounded-2xl bg-zinc-950 text-white shadow-sm"
            >
                <div class="space-y-5 p-5 sm:p-6">
                    <Link
                        :href="laboratoryIndex()"
                        class="inline-flex items-center gap-2 text-sm font-semibold text-zinc-300 hover:text-white"
                    >
                        <ArrowLeft class="size-4" />
                        Laboratory
                    </Link>

                    <div
                        class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-end"
                    >
                        <div class="space-y-2">
                            <div
                                class="flex items-center gap-2 text-sm font-semibold text-amber-300"
                            >
                                <ClipboardList class="size-5" />
                                {{ handoff.application.year }} · Office work
                            </div>
                            <h1
                                class="text-3xl font-semibold tracking-tight sm:text-4xl"
                            >
                                Office reviews
                            </h1>
                        </div>
                        <div
                            class="rounded-xl border border-white/10 bg-white/5 p-4"
                        >
                            <p
                                class="text-xs font-semibold tracking-wide text-zinc-400 uppercase"
                            >
                                Application
                            </p>
                            <p class="mt-1 font-semibold">
                                {{ handoff.application.business_name }}
                            </p>
                            <p class="mt-1 text-sm text-zinc-300">
                                {{
                                    handoff.application.type === 'new'
                                        ? 'New'
                                        : 'Renewal'
                                }}
                                · {{ handoff.application.year }}
                            </p>
                            <p
                                v-if="handoff.application.tracking_reference"
                                class="mt-2 text-xs break-all text-zinc-400"
                            >
                                {{ handoff.application.tracking_reference }}
                            </p>
                        </div>
                    </div>
                </div>
            </header>

            <section class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border bg-card p-4 shadow-xs">
                    <p class="text-sm text-muted-foreground">Offices</p>
                    <p class="mt-1 text-2xl font-semibold">
                        {{ handoff.summary.office_count }}
                    </p>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-xs">
                    <p class="text-sm text-muted-foreground">
                        Reviews complete
                    </p>
                    <p class="mt-1 text-2xl font-semibold">
                        {{ completedOfficeCount }} of
                        {{ handoff.summary.office_count }}
                    </p>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-xs">
                    <p class="text-sm text-muted-foreground">Payment Orders</p>
                    <p class="mt-1 text-2xl font-semibold">
                        {{ handoff.summary.payment_order_count }}
                    </p>
                </div>
            </section>

            <section
                v-if="handoff.summary.routine_default_count > 0"
                class="flex flex-col gap-4 rounded-2xl border border-primary/30 bg-primary/5 p-5 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2 class="font-semibold">
                        Defaults not requiring inspection
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ handoff.summary.routine_default_count }} routine
                        defaults can be confirmed now.
                        <template v-if="handoff.summary.manual_review_count">
                            {{ handoff.summary.manual_review_count }}
                            exceptional determinations remain with their
                            offices.
                        </template>
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground"
                    @click="completeRoutineConfirmations"
                >
                    <CheckCircle2 class="size-4" />
                    Confirm defaults not requiring inspection
                </button>
            </section>

            <section
                v-if="handoff.summary.inspection_simulation_count > 0"
                class="flex flex-col gap-4 rounded-2xl border border-amber-300 bg-amber-50 p-5 text-amber-950 sm:flex-row sm:items-center sm:justify-between dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100"
            >
                <div>
                    <h2 class="font-semibold">
                        Laboratory inspection shortcut
                    </h2>
                    <p class="mt-1 text-sm">
                        Complete
                        {{ handoff.summary.inspection_simulation_count }}
                        remaining reviews with explicitly synthetic inspection
                        findings.
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg bg-amber-900 px-4 text-sm font-semibold text-white dark:bg-amber-200 dark:text-amber-950"
                    @click="simulateRemainingOfficeReviews"
                >
                    <CheckCircle2 class="size-4" />
                    Simulate remaining office reviews
                </button>
            </section>

            <section class="space-y-3">
                <div
                    class="flex flex-wrap items-baseline justify-between gap-2"
                >
                    <h2 class="text-xl font-semibold">Office work</h2>
                    <p class="text-sm text-muted-foreground">
                        <template v-if="nextOffice">
                            Next: {{ nextOffice.label }}
                        </template>
                        <template v-else>
                            All office determinations are complete.
                        </template>
                    </p>
                </div>

                <div class="grid gap-3">
                    <article
                        v-for="office in handoff.offices"
                        :key="`${office.code}-${office.activity}`"
                        class="rounded-xl border bg-card p-4 shadow-xs"
                        :class="
                            office.is_next
                                ? 'border-primary/60 ring-2 ring-primary/10'
                                : ''
                        "
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <span
                                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted"
                                >
                                    <Building2 class="size-5" />
                                </span>
                                <div class="min-w-0">
                                    <h3 class="font-semibold">
                                        {{ office.label }}
                                    </h3>
                                    <p class="text-xs text-muted-foreground">
                                        {{ office.required_work }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 sm:shrink-0">
                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="
                                        office.status === 'Complete'
                                            ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200'
                                            : 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200'
                                    "
                                >
                                    {{ office.status }}
                                </span>
                                <Link
                                    v-if="office.status !== 'Complete'"
                                    :href="office.action_url"
                                    method="post"
                                    as="button"
                                    class="inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-primary px-3 text-sm font-semibold text-primary-foreground"
                                >
                                    {{
                                        office.status === 'In progress'
                                            ? 'Continue review'
                                            : 'Open review'
                                    }}
                                    <ArrowRight class="size-4" />
                                </Link>
                            </div>
                        </div>

                        <details
                            v-if="
                                office.responsibilities.length > 0 ||
                                office.reason
                            "
                            class="mt-3 border-t pt-3"
                        >
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-semibold"
                            >
                                Review details
                                <ChevronDown class="size-4" />
                            </summary>
                            <p
                                v-if="office.activity"
                                class="mt-3 text-sm text-muted-foreground"
                            >
                                {{ office.activity }}
                            </p>
                            <ul class="mt-3 space-y-2">
                                <li
                                    v-for="responsibility in office.responsibilities"
                                    :key="responsibility.label"
                                    class="flex items-start justify-between gap-3 rounded-lg border px-3 py-2.5 text-sm"
                                >
                                    <span class="flex gap-2">
                                        <CheckCircle2
                                            v-if="
                                                responsibility.status ===
                                                'Determined'
                                            "
                                            class="mt-0.5 size-4 shrink-0 text-emerald-600"
                                        />
                                        <CircleDashed
                                            v-else
                                            class="mt-0.5 size-4 shrink-0 text-amber-600"
                                        />
                                        <span>
                                            <span>{{
                                                responsibility.label
                                            }}</span>
                                            <span
                                                v-if="
                                                    responsibility.default_amount_cents !==
                                                    null
                                                "
                                                class="mt-0.5 block text-xs font-semibold text-muted-foreground tabular-nums"
                                            >
                                                Default
                                                {{
                                                    money(
                                                        responsibility.default_amount_cents,
                                                    )
                                                }}
                                            </span>
                                        </span>
                                    </span>
                                    <span
                                        class="shrink-0 text-xs text-muted-foreground"
                                        >{{ responsibility.status }}</span
                                    >
                                </li>
                            </ul>
                            <p
                                v-if="office.reason"
                                class="mt-3 text-sm text-muted-foreground"
                            >
                                {{ office.reason }}
                            </p>
                        </details>
                        <p
                            v-if="office.status === 'Complete'"
                            class="mt-3 flex items-center gap-2 text-sm font-semibold text-emerald-700 dark:text-emerald-300"
                        >
                            <CheckCircle2 class="size-4" /> Office review
                            complete
                        </p>
                    </article>
                </div>
            </section>

            <section class="grid gap-4 lg:grid-cols-2">
                <details class="rounded-2xl border bg-card p-5 shadow-xs">
                    <summary
                        class="flex cursor-pointer list-none items-center justify-between gap-3 font-semibold"
                    >
                        <span class="flex items-center gap-2"
                            ><FileCheck2 class="size-5" />Application
                            facts</span
                        >
                        <ChevronDown class="size-4" />
                    </summary>
                    <div class="mt-4 space-y-4 border-t pt-4 text-sm">
                        <dl class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <dt class="text-muted-foreground">Owner</dt>
                                <dd class="mt-1 font-semibold">
                                    {{ handoff.application.owner_name }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Submitted</dt>
                                <dd class="mt-1 font-semibold">
                                    {{
                                        dateTime(
                                            handoff.application.submitted_at,
                                        )
                                    }}
                                </dd>
                            </div>
                        </dl>
                        <div
                            v-for="activity in handoff.application.activities"
                            :key="activity.code ?? activity.name"
                            class="rounded-xl bg-muted/50 p-4"
                        >
                            <p class="font-semibold">{{ activity.name }}</p>
                            <p
                                v-if="activity.code"
                                class="mt-0.5 text-xs text-muted-foreground"
                            >
                                {{ activity.code }}
                            </p>
                            <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                                <div>
                                    <dt class="text-muted-foreground">
                                        Declared gross sales
                                    </dt>
                                    <dd class="mt-1 font-semibold">
                                        {{
                                            money(
                                                activity.declared_gross_sales_cents,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-muted-foreground">
                                        Capital investment
                                    </dt>
                                    <dd class="mt-1 font-semibold">
                                        {{
                                            money(
                                                activity.capital_investment_cents,
                                            )
                                        }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                        <div class="rounded-xl border p-4">
                            <p
                                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                BPLO situational context
                            </p>
                            <p class="mt-2 leading-6">
                                {{ handoff.routing.situational_context }}
                            </p>
                            <p class="mt-2 text-xs text-muted-foreground">
                                Recorded by
                                {{ handoff.routing.determined_by }} ·
                                {{ dateTime(handoff.routing.determined_at) }}
                            </p>
                        </div>
                    </div>
                </details>

                <details class="rounded-2xl border bg-card p-5 shadow-xs">
                    <summary
                        class="flex cursor-pointer list-none items-center justify-between gap-3 font-semibold"
                    >
                        <span class="flex items-center gap-2"
                            ><LockKeyhole class="size-5" />Audit</span
                        >
                        <ChevronDown class="size-4" />
                    </summary>
                    <dl
                        class="mt-4 grid gap-3 border-t pt-4 text-sm sm:grid-cols-2"
                    >
                        <div>
                            <dt class="text-muted-foreground">
                                Routing record
                            </dt>
                            <dd class="mt-1 font-semibold">
                                #{{ handoff.audit.routing_determination_id }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">
                                Evaluation record
                            </dt>
                            <dd class="mt-1 font-semibold">
                                #{{ handoff.audit.evaluation_id }} · version
                                {{ handoff.audit.evaluation_version }}
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-muted-foreground">
                                Responsibility profile
                            </dt>
                            <dd class="mt-1 font-mono text-xs break-all">
                                {{
                                    handoff.audit
                                        .responsibility_profile_version ??
                                    'Not recorded'
                                }}
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-muted-foreground">
                                Evaluation fingerprint
                            </dt>
                            <dd class="mt-1 font-mono text-xs break-all">
                                {{ handoff.audit.evaluation_fingerprint }}
                            </dd>
                        </div>
                        <div class="rounded-lg bg-muted/50 p-3 sm:col-span-2">
                            <dt class="text-muted-foreground">
                                Authority boundary
                            </dt>
                            <dd class="mt-1 font-semibold">
                                {{ handoff.audit.classification }} · no
                                production liability
                            </dd>
                        </div>
                    </dl>
                </details>
            </section>

            <section
                v-if="allOfficeReviewsComplete"
                class="rounded-2xl border bg-card p-5 shadow-xs"
            >
                <div
                    class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2 class="text-xl font-semibold">
                            Office reviews complete
                        </h2>
                    </div>
                    <div class="flex flex-col gap-2 sm:items-end">
                        <button
                            type="button"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-primary px-5 text-sm font-semibold text-primary-foreground"
                            @click="continueOfficeReviews"
                        >
                            Continue to Assessment
                            <ArrowRight class="size-4" />
                        </button>
                        <Link
                            :href="laboratoryIndex()"
                            class="text-center text-sm font-semibold text-muted-foreground underline underline-offset-4 hover:text-foreground"
                        >
                            Laboratory
                        </Link>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
