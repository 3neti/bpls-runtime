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
    Landmark,
    LockKeyhole,
    ShieldCheck,
} from '@lucide/vue';
import { computed } from 'vue';
import {
    confirmRoutineOfficeDefaults,
    runNext as runCleanroomNextRoute,
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
        responsibilities: { label: string; status: string }[];
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
                <div class="space-y-6 p-6 sm:p-8">
                    <Link
                        :href="laboratoryIndex()"
                        class="inline-flex items-center gap-2 text-sm font-semibold text-zinc-300 hover:text-white"
                    >
                        <ArrowLeft class="size-4" />
                        Back to Lifecycle Laboratory
                    </Link>

                    <div
                        class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-end"
                    >
                        <div class="space-y-3">
                            <div
                                class="flex items-center gap-2 text-sm font-semibold text-amber-300"
                            >
                                <ClipboardList class="size-5" />
                                {{ handoff.application.year }} lifecycle
                                milestone
                            </div>
                            <h1
                                class="text-3xl font-semibold tracking-tight sm:text-4xl"
                            >
                                Office reviews assigned
                            </h1>
                            <p
                                class="max-w-3xl text-sm leading-6 text-zinc-300 sm:text-base"
                            >
                                The submitted application and BPLO’s recorded
                                routing have been turned into work for the
                                concerned offices.
                            </p>
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
                    <p class="text-sm text-muted-foreground">
                        Concerned offices
                    </p>
                    <p class="mt-1 text-2xl font-semibold">
                        {{ handoff.summary.office_count }}
                    </p>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-xs">
                    <p class="text-sm text-muted-foreground">
                        Fee responsibilities assigned
                    </p>
                    <p class="mt-1 text-2xl font-semibold">
                        {{ handoff.summary.responsibility_count }}
                    </p>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-xs">
                    <p class="text-sm text-muted-foreground">
                        Determinations completed
                    </p>
                    <p class="mt-1 text-2xl font-semibold">
                        {{ handoff.summary.resolved_count }} of
                        {{ handoff.summary.responsibility_count }}
                    </p>
                </div>
            </section>

            <section
                v-if="handoff.summary.payment_order_count === 0"
                class="flex gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-950 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-100"
            >
                <ShieldCheck class="mt-0.5 size-6 shrink-0" />
                <div>
                    <h2 class="font-semibold">No fees have been charged</h2>
                    <p class="mt-1 text-sm leading-6">
                        This handoff created office work only. Each office must
                        still confirm applicability and any amount. No Paperless
                        Payment Order or Assessment was created at this step.
                    </p>
                </div>
            </section>

            <section v-else class="flex gap-3 rounded-2xl border bg-card p-5">
                <ClipboardList class="mt-0.5 size-6 shrink-0" />
                <div>
                    <h2 class="font-semibold">Office reviews in progress</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ handoff.summary.payment_order_count }} amount-bearing
                        office record{{
                            handoff.summary.payment_order_count === 1 ? '' : 's'
                        }}
                        issued so far. No Assessment exists yet.
                    </p>
                </div>
            </section>

            <section
                v-if="handoff.summary.routine_default_count > 0"
                class="flex flex-col gap-4 rounded-2xl border border-primary/30 bg-primary/5 p-5 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2 class="font-semibold">Faster cleanroom test</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ handoff.summary.routine_default_count }} routine
                        defaults can be confirmed now.
                        <template v-if="handoff.summary.manual_review_count">
                            {{ handoff.summary.manual_review_count }} inspection
                            or exceptional determinations remain with their
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
                    Complete routine office confirmations
                </button>
            </section>

            <section class="space-y-4">
                <div>
                    <h2 class="text-xl font-semibold">Who acts next</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        <template v-if="nextOffice">
                            Continue with {{ nextOffice.label }}. Each office
                            opens only its own assigned work.
                        </template>
                        <template v-else>
                            All office determinations are complete.
                        </template>
                    </p>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <article
                        v-for="office in handoff.offices"
                        :key="`${office.code}-${office.activity}`"
                        class="rounded-2xl border bg-card p-5 shadow-xs"
                        :class="
                            office.is_next
                                ? 'border-primary/60 ring-2 ring-primary/10'
                                : ''
                        "
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex min-w-0 gap-3">
                                <span
                                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted"
                                >
                                    <Building2 class="size-5" />
                                </span>
                                <div class="min-w-0">
                                    <h3 class="font-semibold">
                                        {{ office.label }}
                                    </h3>
                                    <p
                                        v-if="office.activity"
                                        class="mt-0.5 text-sm text-muted-foreground"
                                    >
                                        {{ office.activity }}
                                    </p>
                                </div>
                            </div>
                            <span
                                class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="
                                    office.status === 'Complete'
                                        ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200'
                                        : 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200'
                                "
                            >
                                {{ office.status }}
                            </span>
                        </div>

                        <div class="mt-4 rounded-xl bg-muted/50 p-4">
                            <p
                                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                Required work
                            </p>
                            <p class="mt-1 text-sm leading-6">
                                {{ office.required_work }}
                            </p>
                        </div>

                        <ul class="mt-4 space-y-2">
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
                                    {{ responsibility.label }}
                                </span>
                                <span
                                    class="shrink-0 text-xs text-muted-foreground"
                                    >{{ responsibility.status }}</span
                                >
                            </li>
                        </ul>

                        <details class="mt-4 border-t pt-4">
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-semibold"
                            >
                                Why this office was included
                                <ChevronDown class="size-4" />
                            </summary>
                            <p
                                class="mt-3 text-sm leading-6 text-muted-foreground"
                            >
                                {{ office.reason }}
                            </p>
                        </details>

                        <Link
                            v-if="office.status !== 'Complete'"
                            :href="office.action_url"
                            method="post"
                            as="button"
                            class="mt-4 inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground"
                        >
                            {{
                                office.status === 'In progress'
                                    ? `Continue ${office.label} review`
                                    : `Open ${office.label} review`
                            }}
                            <ArrowRight class="size-4" />
                        </Link>
                        <p
                            v-else
                            class="mt-4 flex items-center gap-2 text-sm font-semibold text-emerald-700 dark:text-emerald-300"
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
                            ><FileCheck2 class="size-5" />Application facts
                            used</span
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
                            ><LockKeyhole class="size-5" />Audit details</span
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

            <section class="rounded-2xl border bg-card p-5 shadow-xs sm:p-6">
                <div
                    class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="max-w-2xl">
                        <div
                            class="flex items-center gap-2 text-sm font-semibold text-muted-foreground"
                        >
                            <Landmark class="size-5" />Next lifecycle stage
                        </div>
                        <h2 class="mt-2 text-xl font-semibold">
                            Concerned-office determination
                        </h2>
                        <p class="mt-1 text-sm leading-6 text-muted-foreground">
                            Offices record Confirm, Override, or Not Applicable.
                            Only completed amount-bearing determinations can
                            proceed toward the provisional Assessment. The
                            Assessment Officer returns after every concerned
                            office has finished.
                        </p>
                    </div>
                    <div class="flex flex-col gap-2 sm:items-end">
                        <button
                            v-if="
                                handoff.summary.resolved_count ===
                                handoff.summary.responsibility_count
                            "
                            type="button"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-primary px-5 text-sm font-semibold text-primary-foreground"
                            @click="continueOfficeReviews"
                        >
                            Continue to Assessment review
                            <ArrowRight class="size-4" />
                        </button>
                        <Link
                            :href="laboratoryIndex()"
                            class="text-center text-sm font-semibold text-muted-foreground underline underline-offset-4 hover:text-foreground"
                        >
                            Continue in Lifecycle Laboratory
                        </Link>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
