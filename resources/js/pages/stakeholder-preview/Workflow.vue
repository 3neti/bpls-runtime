<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Building2,
    CheckCircle2,
    FileText,
    Landmark,
    LockKeyhole,
} from '@lucide/vue';
import { reactive, ref } from 'vue';
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
                    <span>Office-specific preview workspace</span>
                </div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ persona.label }} Workspace
                </h1>
                <p class="max-w-3xl text-sm leading-6 text-muted-foreground">
                    Review the sample application and take only this office's
                    step. This workspace is a reversible stakeholder-test
                    interpretation, not final municipal role or policy mapping.
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
                        <section>
                            <h3
                                class="flex items-center gap-2 text-sm font-semibold"
                            >
                                <FileText class="size-4" /> Evidence to inspect
                                now
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
                                    v-if="application.documents.length === 0"
                                    class="text-muted-foreground"
                                >
                                    No supporting evidence is recorded yet.
                                </li>
                            </ul>
                        </section>

                        <section
                            v-if="office"
                            class="rounded-lg border bg-muted/30 p-4"
                        >
                            <h3 class="text-sm font-semibold">
                                {{ office.label }} review
                            </h3>
                            <div class="mt-4 space-y-4">
                                <label class="flex items-center gap-2 text-sm">
                                    <input
                                        v-model="
                                            formFor(application).is_applicable
                                        "
                                        type="checkbox"
                                        :disabled="application.charge_locked"
                                    />
                                    Applicable to this sample application
                                </label>
                                <label class="block text-sm">
                                    <span class="font-medium"
                                        >Office-assessed amount</span
                                    >
                                    <input
                                        v-model="formFor(application).amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        :disabled="
                                            application.charge_locked ||
                                            !formFor(application).is_applicable
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
                                        application.office_contribution
                                            ? 'Update and resubmit office assessment'
                                            : 'Submit office assessment'
                                    }}
                                </button>
                                <p
                                    v-if="application.charge_locked"
                                    class="flex gap-2 text-xs text-muted-foreground"
                                >
                                    <LockKeyhole class="size-4 shrink-0" />
                                    Locked after Treasury approval or payment
                                    processing.
                                </p>
                            </div>
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
                                        Clearances complete
                                    </dt>
                                    <dd>
                                        {{
                                            application.clearances_complete
                                                ? 'Yes'
                                                : 'No'
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Consolidated amount
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
                                        Preview number
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
                                    Go — apply sample e-signature
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
                                    No-Go
                                </button>
                            </div>
                            <button
                                v-else
                                type="button"
                                :disabled="
                                    working !== null ||
                                    application.completion?.status !==
                                        'approved_for_preview_release'
                                "
                                class="mt-4 w-full rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                                @click="decide(application, 'release')"
                            >
                                Release sample permit in preview
                            </button>
                            <p
                                v-if="
                                    application.completion?.released_in_preview
                                "
                                class="mt-3 flex gap-2 text-sm text-emerald-700"
                            >
                                <CheckCircle2 class="size-4 shrink-0" />
                                Released in the preview lifecycle.
                            </p>
                            <p
                                class="mt-3 text-xs leading-5 text-muted-foreground"
                            >
                                No official numbering, real Mayor credential,
                                issuance authority, municipal release, or legal
                                effect is created.
                            </p>
                        </section>
                    </div>
                </article>
            </section>
        </div>
    </AppLayout>
</template>
