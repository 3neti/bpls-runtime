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
        'Open All Applications and coordinate clearances for a received application.',
        'Inspect Taxes & Fees and confirm what the Assessment Officer will select.',
        'Confirm that municipal release is still shown as not confirmed.',
    ],
    assessment_officer: [
        'Open Assessment Work and consolidate the exact assessment for a received application.',
        'Inspect the confirmed and recorded rules that make up the amount.',
        'Prepare its payment schedule for Treasurer approval.',
    ],
    treasury: [
        'Open Applications for Treasury Review and counter-check the current Evaluation version.',
        'Confirm that Treasury cannot approve the Assessment prepared from that Evaluation.',
        'Open Payment Schedules and inspect the financial result after Municipal Treasurer approval.',
        'Review its collection and receipt details.',
        'Open Daily Collections and Revenue Sources.',
    ],
    municipal_treasurer: [
        'Open Assessment Work and inspect the exact immutable amount prepared by the Assessment Officer.',
        'Approve or return that exact Assessment snapshot without performing the earlier Treasury counter-check.',
        'Confirm that payment remains bound to the approved Assessment and cannot mutate the Evaluation.',
    ],
    cashier: [
        'Open Payment Schedules and locate an authorized, approved schedule.',
        'Record the payment collection against that schedule.',
        'Issue the official receipt and inspect its recorded detail.',
    ],
    management: [
        'Open the Report Catalog and representative management reports.',
        'Inspect the User Directory, Municipal Access Administration, and Municipal Configuration.',
        'Review the Fee and Rule Catalog and the provisional Other Collections setup.',
    ],
    engineering: [
        'Open the Engineering queue.',
        'Inspect the application documents and recorded establishment details.',
        'Confirm or enter only the Engineering charge.',
    ],
    mpdo: [
        'Open the MPDO queue.',
        'Inspect the application documents and recorded establishment details.',
        'Confirm or enter only the MPDO charge.',
    ],
    assessor: [
        'Open the Assessor queue.',
        'Inspect the application documents and recorded establishment details.',
        'Confirm or enter only the Assessor charge.',
    ],
    health: [
        'Open the Health queue.',
        'Inspect the application documents and recorded establishment details.',
        'Confirm or enter only the Health charge.',
    ],
    menro: [
        'Open the MENRO queue.',
        'Inspect the application documents and recorded establishment details.',
        'Confirm or enter only the MENRO charge.',
    ],
    mayor_office: [
        'Open Final Permit Review.',
        'Inspect payment and clearance status.',
        'Try the preview permit decision.',
    ],
    releasing: [
        'Open the Permit Release Queue.',
        'Confirm the preview go decision.',
        'Complete the sample permit workflow in the preview.',
    ],
};
</script>

<template>
    <Head title="Board / Stakeholder Walkthrough" />

    <main class="min-h-svh bg-background text-foreground">
        <div
            class="border-b border-amber-800 bg-amber-300 px-5 py-2 text-center text-xs font-semibold tracking-wide text-amber-950"
        >
            Preview Environment · Sample Data
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
                        Quick Start
                    </h1>
                    <p
                        class="max-w-3xl text-base leading-7 text-muted-foreground"
                    >
                        Follow the perspectives in order, or open any role
                        directly to explore its common tasks in the Business
                        Permit and Licensing System.
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
                Preview roles are for evaluation only. Final municipal access,
                payment policy, permit numbering, release, and legal effect
                still require separate municipal confirmation.
            </p>
        </div>
    </main>
</template>
