<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, CheckCircle2, Landmark } from '@lucide/vue';
import {
    enter,
    index,
} from '@/actions/App/Http/Controllers/StakeholderPreviewController';
import type { StakeholderPreviewPersona } from '@/types';

defineProps<{
    personas: StakeholderPreviewPersona[];
}>();

const steps: Record<StakeholderPreviewPersona['key'], string[]> = {
    citizen: [
        'Open My Permit Applications and inspect the prepared application.',
        'Follow its processing, payment, clearance, and notice trail.',
        'Observe where the workflow stops at authority review.',
    ],
    bplo: [
        'Open All Applications and the assessment work queue.',
        'Inspect the Assessment Officer-prepared amount, application evidence, and clearances.',
        'Confirm issuance and release remain visibly authority-bound.',
    ],
    treasury: [
        'Open Assessment Work and inspect the exact amount approved by the Municipal Treasurer.',
        'Confirm the prepared and approved actors remain separate audit facts.',
        'Open Payment Schedules and inspect the paid schedule made available after approval.',
        'Review its collection and receipt evidence.',
        'Open Daily Collections and Revenue Sources.',
    ],
    management: [
        'Open the Report Catalog and representative management reports.',
        'Inspect Users, Roles & Permissions, and Municipality & Officials.',
        'Review Taxes & Fees and Billing Groups as evidence and policy-bound surfaces.',
    ],
};
</script>

<template>
    <Head title="Board / Stakeholder Walkthrough" />

    <main class="min-h-svh bg-background text-foreground">
        <div
            class="border-b border-amber-800 bg-amber-300 px-5 py-3 text-center text-sm font-extrabold tracking-wide text-amber-950"
        >
            STAKEHOLDER PREVIEW / UAT — SYNTHETIC DATA — NOT PRODUCTION
        </div>

        <div
            class="mx-auto flex max-w-5xl flex-col gap-8 px-5 py-10 sm:px-8 sm:py-14"
        >
            <header class="space-y-5">
                <Link
                    :href="index()"
                    class="inline-flex items-center gap-2 text-sm font-semibold text-muted-foreground outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <ArrowLeft class="size-4" aria-hidden="true" />
                    Back to role launcher
                </Link>
                <div
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <Landmark class="size-5" aria-hidden="true" />
                    Municipality of Ipil
                </div>
                <div class="space-y-3">
                    <h1
                        class="text-3xl font-semibold tracking-tight sm:text-4xl"
                    >
                        Board / Stakeholder Walkthrough
                    </h1>
                    <p
                        class="max-w-3xl text-base leading-7 text-muted-foreground"
                    >
                        Follow the four perspectives in order, or enter any role
                        directly. This is concise guidance over the real
                        BPLS—not a separate demo application.
                    </p>
                </div>
            </header>

            <ol class="grid gap-4">
                <li
                    v-for="(persona, indexValue) in personas"
                    :key="persona.key"
                    class="grid gap-5 rounded-2xl border bg-card p-5 shadow-xs sm:grid-cols-[3rem_1fr_auto] sm:items-start"
                >
                    <div
                        class="flex size-11 items-center justify-center rounded-full bg-primary font-bold text-primary-foreground"
                    >
                        {{ indexValue + 1 }}
                    </div>
                    <div class="space-y-3">
                        <div>
                            <h2 class="text-xl font-semibold">
                                {{ persona.label }}
                            </h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ persona.description }}
                            </p>
                        </div>
                        <ul class="space-y-2 text-sm">
                            <li
                                v-for="step in steps[persona.key]"
                                :key="step"
                                class="flex gap-2"
                            >
                                <CheckCircle2
                                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <span>{{ step }}</span>
                            </li>
                        </ul>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-semibold outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
                        @click="router.post(enter(persona.key).url)"
                    >
                        Enter as {{ persona.label }}
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </button>
                </li>
            </ol>

            <p
                class="rounded-xl border bg-muted/40 p-4 text-sm leading-6 text-muted-foreground"
            >
                Preview roles are evaluation bundles only. Final municipal role
                mapping, fiscal policy, numbering, issuance, release, and legal
                effect remain separate acceptance and authority exercises.
            </p>
        </div>
    </main>
</template>
