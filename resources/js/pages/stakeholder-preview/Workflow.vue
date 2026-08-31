<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Building2,
    CheckCircle2,
    FileText,
    Landmark,
    LockKeyhole,
} from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import {
    recordPermitDecision,
    storeOfficeCharge,
} from '@/actions/App/Http/Controllers/StakeholderPreviewWorkflowController';
import AppLayout from '@/layouts/AppLayout.vue';

type Completion = {
    status: string;
    permit_number: string | null;
    signature_applied: boolean;
    released_in_preview: boolean;
    production_authority: false;
};

type Application = {
    id: number;
    reference: string;
    type: string;
    status: string;
    business_name: string;
    owner_name: string;
    ownership_type: string | null;
    office_facts?: { label: string; value: string }[];
    documents: { label: string; remarks: string | null }[];
    office_contribution: {
        is_applicable: boolean;
        status: string;
        amount_cents: number | null;
        submitted_by: string | null;
    } | null;
    office_contributions: {
        office_label: string;
        is_applicable: boolean;
        amount_cents: number | null;
        status: string;
    }[];
    evaluation_responsibilities: {
        label: string;
        line_of_business: string | null;
        reason: string;
        applicability: string;
        resolution: string;
        amount_cents: number | null;
        source_classification: string;
    }[];
    charge_locked: boolean;
    latest_assessment_total_cents: number | null;
    payment_confirmed: boolean;
    clearances_complete: boolean;
    completion: Completion | null;
};

const props = defineProps<{
    persona: { key: string; label: string; office_code: string | null };
    office: { label: string; scenario_amount_cents: number } | null;
    applications: Application[];
}>();

const forms = reactive<
    Record<number, { is_applicable: boolean; amount: string }>
>({});
const working = ref<number | null>(null);

const officeGuidance = computed(() => {
    const guidance = {
        engineering: {
            heading: 'Engineering application review',
            description:
                'Review the establishment details and submitted documents available for this application.',
            question:
                'Confirm which establishment and occupancy details this office needs in the final system.',
        },
        mpdo: {
            heading: 'Planning application review',
            description:
                'Review the business and location details supplied with this application.',
            question:
                'Confirm which planning and zoning details this office needs in the final system.',
        },
        assessor: {
            heading: 'Assessor application review',
            description:
                'Review the recorded business, ownership, and property-related details available for this application.',
            question:
                'Confirm which property and assessment details this office needs in the final system.',
        },
        health: {
            heading: 'Health application review',
            description:
                'Review the establishment details and submitted documents available for health review.',
            question:
                'Confirm which establishment and health details this office needs in the final system.',
        },
        menro: {
            heading: 'Environmental application review',
            description:
                'Review the establishment details and submitted documents available for environmental review.',
            question:
                'Confirm which environmental details this office needs in the final system.',
        },
    } as const;

    return props.office
        ? guidance[props.persona.key as keyof typeof guidance]
        : null;
});

function formFor(application: Application): {
    is_applicable: boolean;
    amount: string;
} {
    forms[application.id] ??= {
        is_applicable: application.office_contribution?.is_applicable ?? true,
        amount:
            application.office_contribution?.amount_cents !== null &&
            application.office_contribution?.amount_cents !== undefined
                ? (application.office_contribution.amount_cents / 100).toFixed(
                      2,
                  )
                : props.office
                  ? (props.office.scenario_amount_cents / 100).toFixed(2)
                  : '',
    };

    return forms[application.id];
}

function submitOfficeCharge(application: Application): void {
    const form = formFor(application);
    working.value = application.id;
    router.post(
        storeOfficeCharge(application.id).url,
        {
            is_applicable: form.is_applicable,
            amount_cents: form.is_applicable
                ? Math.round(Number(form.amount) * 100)
                : null,
        },
        {
            onFinish: () => {
                working.value = null;
            },
        },
    );
}

function decide(
    application: Application,
    decision: 'go' | 'no_go' | 'release',
): void {
    working.value = application.id;
    router.post(
        recordPermitDecision(application.id).url,
        { decision },
        {
            onFinish: () => {
                working.value = null;
            },
        },
    );
}

function money(cents: number | null): string {
    return cents === null
        ? '—'
        : new Intl.NumberFormat('en-PH', {
              style: 'currency',
              currency: 'PHP',
          }).format(cents / 100);
}
</script>

<template>
    <AppLayout>
        <Head :title="`${persona.label} Workspace`" />
        <div class="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
            <header class="space-y-2">
                <div
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <Building2 v-if="office" class="size-4" />
                    <Landmark v-else class="size-4" />
                    <span>Preview · Sample Data</span>
                </div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ persona.label }} Workspace
                </h1>
                <p class="max-w-3xl text-sm leading-6 text-muted-foreground">
                    <template v-if="officeGuidance">
                        {{ officeGuidance.description }} Record only this
                        office's preview contribution.
                    </template>
                    <template v-else>
                        Review the sample application's payment, clearance, and
                        preview permit status before taking this office's step.
                    </template>
                </p>
                <p
                    v-if="officeGuidance"
                    class="max-w-3xl rounded-md border border-dashed px-3 py-2 text-xs leading-5 text-muted-foreground"
                >
                    For municipal validation: {{ officeGuidance.question }}
                    The details shown below are the facts currently available,
                    not a final list of office requirements.
                </p>
            </header>

            <section class="grid gap-5">
                <article
                    v-for="application in applications"
                    :key="application.id"
                    class="rounded-xl border bg-card p-5 shadow-xs"
                >
                    <div
                        class="flex flex-col gap-3 border-b pb-4 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div>
                            <p
                                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                {{ application.reference }}
                            </p>
                            <h2 class="mt-1 text-lg font-semibold">
                                {{ application.business_name }}
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                {{ application.owner_name }} ·
                                {{ application.type.replaceAll('_', ' ') }}
                            </p>
                        </div>
                        <span
                            class="w-fit rounded-full bg-muted px-3 py-1 text-xs font-semibold capitalize"
                            >{{
                                application.completion?.status ===
                                'released_in_preview'
                                    ? 'released in preview'
                                    : application.completion?.status ===
                                        'approved_for_preview_release'
                                      ? 'ready for preview release'
                                      : application.payment_confirmed
                                        ? 'payment confirmed'
                                        : application.status.replaceAll(
                                              '_',
                                              ' ',
                                          )
                            }}</span
                        >
                    </div>

                    <div class="mt-4 grid gap-5 lg:grid-cols-2">
                        <section class="space-y-5">
                            <div>
                                <h3 class="text-sm font-semibold">
                                    Application details for this review
                                </h3>
                                <dl
                                    class="mt-3 grid gap-3 rounded-lg border p-4 sm:grid-cols-2"
                                >
                                    <div
                                        v-for="fact in application.office_facts ??
                                        []"
                                        :key="fact.label"
                                        class="min-w-0"
                                    >
                                        <dt
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ fact.label }}
                                        </dt>
                                        <dd
                                            class="mt-0.5 text-sm font-medium break-words"
                                        >
                                            {{ fact.value }}
                                        </dd>
                                    </div>
                                    <div
                                        v-if="
                                            (application.office_facts ?? [])
                                                .length === 0
                                        "
                                        class="text-sm text-muted-foreground sm:col-span-2"
                                    >
                                        No additional application details are
                                        available for this review.
                                    </div>
                                </dl>
                            </div>

                            <div>
                                <h3
                                    class="flex items-center gap-2 text-sm font-semibold"
                                >
                                    <FileText class="size-4" /> Documents
                                </h3>
                                <ul class="mt-3 space-y-2 text-sm">
                                    <li
                                        v-for="document in application.documents"
                                        :key="document.label"
                                        class="rounded-md border p-3"
                                    >
                                        <span class="font-medium">{{
                                            document.label
                                        }}</span>
                                        <span
                                            v-if="document.remarks"
                                            class="mt-1 block text-xs text-muted-foreground"
                                            >{{ document.remarks }}</span
                                        >
                                    </li>
                                    <li
                                        v-if="
                                            application.documents.length === 0
                                        "
                                        class="text-muted-foreground"
                                    >
                                        No supporting documents are recorded
                                        yet.
                                    </li>
                                </ul>
                            </div>
                        </section>

                        <section
                            v-if="office"
                            class="rounded-lg border bg-muted/30 p-4"
                        >
                            <template
                                v-if="
                                    application.evaluation_responsibilities
                                        .length > 0
                                "
                            >
                                <h3 class="text-sm font-semibold">
                                    Canonical Evaluation responsibilities
                                </h3>
                                <p
                                    class="mt-1 text-xs leading-5 text-muted-foreground"
                                >
                                    This work comes directly from the
                                    application's versioned Business Permit
                                    Evaluation. Its recorded state is shown
                                    without creating a second office queue or
                                    charge.
                                </p>
                                <ul
                                    class="mt-4 space-y-3"
                                    data-testid="canonical-evaluation-responsibilities"
                                >
                                    <li
                                        v-for="responsibility in application.evaluation_responsibilities"
                                        :key="`${responsibility.label}-${responsibility.line_of_business}`"
                                        class="min-w-0 rounded-md border bg-background p-3"
                                    >
                                        <div
                                            class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                                        >
                                            <div class="min-w-0">
                                                <p
                                                    class="text-sm font-semibold break-words"
                                                >
                                                    {{ responsibility.label }}
                                                </p>
                                                <p
                                                    v-if="
                                                        responsibility.line_of_business
                                                    "
                                                    class="mt-0.5 text-xs text-muted-foreground"
                                                >
                                                    {{
                                                        responsibility.line_of_business
                                                    }}
                                                </p>
                                            </div>
                                            <span
                                                class="w-fit rounded-full border px-2 py-0.5 text-[10px] font-semibold capitalize"
                                            >
                                                {{ responsibility.resolution }}
                                            </span>
                                        </div>
                                        <p
                                            class="mt-2 text-xs leading-5 text-muted-foreground"
                                        >
                                            {{ responsibility.reason }}
                                        </p>
                                        <dl
                                            class="mt-3 grid grid-cols-2 gap-3 text-xs"
                                        >
                                            <div>
                                                <dt
                                                    class="text-muted-foreground"
                                                >
                                                    Applicability
                                                </dt>
                                                <dd class="mt-0.5 capitalize">
                                                    {{
                                                        responsibility.applicability
                                                    }}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt
                                                    class="text-muted-foreground"
                                                >
                                                    Recorded amount
                                                </dt>
                                                <dd class="mt-0.5 font-medium">
                                                    {{
                                                        money(
                                                            responsibility.amount_cents,
                                                        )
                                                    }}
                                                </dd>
                                            </div>
                                        </dl>
                                    </li>
                                </ul>
                            </template>
                            <template v-else>
                                <h3 class="text-sm font-semibold">
                                    {{
                                        officeGuidance?.heading ?? office.label
                                    }}
                                </h3>
                                <p
                                    class="mt-1 text-xs leading-5 text-muted-foreground"
                                >
                                    Applicability and amount are provisional for
                                    this sample workflow.
                                </p>
                                <div class="mt-4 space-y-4">
                                    <label
                                        class="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            v-model="
                                                formFor(application)
                                                    .is_applicable
                                            "
                                            type="checkbox"
                                            :disabled="
                                                application.charge_locked
                                            "
                                        />
                                        Include this office in the sample
                                        assessment
                                    </label>
                                    <label class="block text-sm">
                                        <span
                                            class="flex items-center justify-between gap-2 font-medium"
                                        >
                                            Preview contribution amount
                                            <span
                                                class="rounded-full border px-2 py-0.5 text-[10px] font-semibold text-muted-foreground"
                                            >
                                                Preview · Sample Data
                                            </span>
                                        </span>
                                        <input
                                            v-model="
                                                formFor(application).amount
                                            "
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            :disabled="
                                                application.charge_locked ||
                                                !formFor(application)
                                                    .is_applicable
                                            "
                                            class="mt-2 w-full rounded-md border bg-background px-3 py-2"
                                        />
                                    </label>
                                    <button
                                        type="button"
                                        :disabled="
                                            working !== null ||
                                            application.charge_locked
                                        "
                                        class="w-full rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                                        @click="submitOfficeCharge(application)"
                                    >
                                        {{
                                            working === application.id
                                                ? 'Saving contribution…'
                                                : application.office_contribution
                                                  ? 'Update preview contribution'
                                                  : 'Record preview contribution'
                                        }}
                                    </button>
                                    <p
                                        v-if="
                                            !formFor(application).is_applicable
                                        "
                                        class="text-xs text-muted-foreground"
                                    >
                                        No amount will be recorded while this
                                        office is not included in the sample
                                        assessment.
                                    </p>
                                    <p
                                        v-if="application.charge_locked"
                                        class="flex gap-2 text-xs text-muted-foreground"
                                    >
                                        <LockKeyhole class="size-4 shrink-0" />
                                        This contribution cannot change after
                                        Treasury approval or payment begins.
                                    </p>
                                </div>
                            </template>
                        </section>

                        <section
                            v-else
                            class="rounded-lg border bg-muted/30 p-4"
                        >
                            <h3 class="text-sm font-semibold">
                                Final permit processing
                            </h3>
                            <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Payment confirmed
                                    </dt>
                                    <dd>
                                        {{
                                            application.payment_confirmed
                                                ? 'Yes'
                                                : 'No'
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Clearance checklist
                                    </dt>
                                    <dd>
                                        {{
                                            application.clearances_complete
                                                ? 'Complete'
                                                : 'Not complete'
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Approved amount for payment
                                    </dt>
                                    <dd>
                                        {{
                                            money(
                                                application.latest_assessment_total_cents,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Preview permit number
                                    </dt>
                                    <dd>
                                        {{
                                            application.completion
                                                ?.permit_number ??
                                            'Not assigned'
                                        }}
                                    </dd>
                                </div>
                            </dl>
                            <div
                                class="mt-4 grid gap-2 sm:grid-cols-2"
                                v-if="persona.key === 'mayor_office'"
                            >
                                <button
                                    type="button"
                                    :disabled="
                                        working !== null ||
                                        !application.payment_confirmed ||
                                        !application.clearances_complete
                                    "
                                    class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                                    @click="decide(application, 'go')"
                                >
                                    Approve preview permit
                                </button>
                                <button
                                    type="button"
                                    :disabled="
                                        working !== null ||
                                        !application.payment_confirmed ||
                                        !application.clearances_complete
                                    "
                                    class="rounded-md border px-4 py-2 text-sm font-semibold disabled:opacity-50"
                                    @click="decide(application, 'no_go')"
                                >
                                    Return / Do not approve
                                </button>
                            </div>
                            <p
                                v-if="
                                    persona.key === 'mayor_office' &&
                                    (!application.payment_confirmed ||
                                        !application.clearances_complete)
                                "
                                class="mt-2 text-xs text-muted-foreground"
                            >
                                Preview permit review becomes available after
                                payment is confirmed and the clearance checklist
                                is complete.
                            </p>
                            <button
                                v-if="persona.key === 'releasing'"
                                type="button"
                                :disabled="
                                    working !== null ||
                                    application.completion?.status !==
                                        'approved_for_preview_release'
                                "
                                class="mt-4 w-full rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                                @click="decide(application, 'release')"
                            >
                                Record preview release
                            </button>
                            <p
                                v-if="
                                    persona.key === 'releasing' &&
                                    application.completion?.status !==
                                        'approved_for_preview_release' &&
                                    !application.completion?.released_in_preview
                                "
                                class="mt-2 text-xs text-muted-foreground"
                            >
                                Preview release becomes available after the
                                preview permit is approved.
                            </p>
                            <p
                                v-if="
                                    application.completion?.released_in_preview
                                "
                                class="mt-3 flex gap-2 text-sm text-emerald-700"
                            >
                                <CheckCircle2 class="size-4 shrink-0" />
                                Sample workflow release is complete.
                            </p>
                            <p
                                class="mt-3 text-xs leading-5 text-muted-foreground"
                            >
                                Preview only. This does not create an official
                                permit number, authorized signature, municipal
                                release, or legal effect.
                            </p>
                        </section>
                    </div>
                </article>
            </section>
        </div>
    </AppLayout>
</template>
