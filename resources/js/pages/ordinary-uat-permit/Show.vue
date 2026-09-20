<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { index as workIndex } from '@/actions/App/Http/Controllers/Staff/MunicipalWorkInboxController';
import { store } from '@/actions/App/Http/Controllers/Staff/OrdinaryUatPermitController';
import ActionConfirmationDialog from '@/components/ActionConfirmationDialog.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';

type Ceremony = 'authorize' | 'issue' | 'release';
const props = defineProps<{
    application: {
        id: number;
        tracking_reference: string | null;
        business_name: string;
        year: number;
        type: string;
    };
    readiness: {
        prerequisites: Record<string, boolean>;
        receipt_total_cents: number;
        receipt_numbers: string[];
    };
    completion: null | {
        authorized_at: string | null;
        authorization_fingerprint: string | null;
        issued_at: string | null;
        released_at: string | null;
        permit_number: string | null;
    };
    action: Ceremony | null;
    applicationUrl: string;
    permitUrl: string | null;
    verificationUrl: string | null;
}>();
const confirmation = ref<InstanceType<typeof ActionConfirmationDialog> | null>(
    null,
);
const form = useForm<{ ceremony: Ceremony | null }>({ ceremony: props.action });
const labels = {
    authorize: 'Authorize Permit Issuance',
    issue: 'Issue Business Permit',
    release: 'Release Business Permit',
};
const consequences = {
    authorize: 'Records authorization. Permit issuance follows separately.',
    issue: 'Creates the Permit once. BPLO release follows separately.',
    release:
        'Makes the issued Permit available to the Citizen. This does not issue another Permit.',
};
const heading = computed(() =>
    props.completion?.released_at
        ? 'Business Permit Released'
        : props.completion?.issued_at
          ? 'Business Permit Release'
          : props.completion?.authorized_at
            ? 'Business Permit Issuance'
            : 'Mayoral Authorization',
);
const status = computed(() =>
    props.completion?.released_at
        ? 'Released'
        : props.completion?.issued_at
          ? 'Issued · Awaiting BPLO release'
          : props.completion?.authorized_at
            ? 'Authorized · Awaiting issuance'
            : 'Awaiting authorization',
);
const requirements: Record<string, string> = {
    application_eligible: 'Application eligibility',
    canonical_collection: 'Payment recorded',
    issued_official_receipts: 'Official Receipts issued',
    issued_official_receipt: 'Official Receipt issued',
    complete_receipt_coverage: 'Receipt coverage',
    official_receipt_totals_reconcile: 'Receipt totals reconcile',
    post_payment_certifications_commissioned:
        'Required certifications assigned',
    all_required_post_payment_certifications: 'Office certifications',
    synthetic_issuance_authority_available: 'Test issuance authority',
    canonical_workflow_reconciliation: 'Workflow reconciliation',
    mayoral_authorization_recorded: 'Mayoral Authorization',
};
const blockers = computed(() =>
    Object.entries(props.readiness.prerequisites).filter(
        ([, passed]) => !passed,
    ),
);
const summary = computed(
    () =>
        [
            ['Payment', props.readiness.prerequisites.canonical_collection],
            [
                'Official Receipts',
                props.readiness.prerequisites.complete_receipt_coverage &&
                    props.readiness.prerequisites
                        .official_receipt_totals_reconcile,
            ],
            [
                'Office certifications',
                props.readiness.prerequisites
                    .all_required_post_payment_certifications,
            ],
            ['Mayoral Authorization', !!props.completion?.authorized_at],
        ] as const,
);
function date(value: string | null | undefined): string {
    if (!value) {
        return 'Pending';
    }

    const parsed = new Date(value);

    return Number.isNaN(parsed.getTime())
        ? 'Date unavailable'
        : parsed.toLocaleString('en-PH', {
              timeZone: 'Asia/Manila',
              dateStyle: 'medium',
              timeStyle: 'short',
          });
}
async function submit(): Promise<void> {
    const action = props.action;

    if (!action || form.processing) {
        return;
    }

    if (
        !(await confirmation.value?.ask(
            labels[action] + '?',
            consequences[action],
            labels[action],
        ))
    ) {
        return;
    }

    if (props.action !== action || form.processing) {
        return;
    }

    form.ceremony = action;
    form.post(store(props.application.id).url, { preserveScroll: true });
}
</script>

<template>
    <Head :title="heading" />
    <AppLayout>
        <ActionConfirmationDialog
            ref="confirmation"
            :context="
                application.business_name +
                ' · ' +
                application.year +
                ' · ' +
                application.type +
                (completion?.permit_number
                    ? ' · ' + completion.permit_number
                    : '')
            "
        />
        <main class="mx-auto w-full max-w-5xl min-w-0 space-y-5 p-4 sm:p-6">
            <Link :href="workIndex()" class="text-sm underline"
                >Back to Inbox</Link
            >
            <header class="space-y-2">
                <p class="text-xs text-muted-foreground">
                    Test environment · Not valid for official use
                </p>
                <h1 class="text-2xl font-semibold">{{ heading }}</h1>
                <p class="text-lg break-words">
                    {{ application.business_name }}
                </p>
                <p class="text-sm break-words text-muted-foreground">
                    {{ application.year }} · {{ application.type }} ·
                    {{ application.tracking_reference }}
                </p>
            </header>
            <div class="grid min-w-0 gap-5 lg:grid-cols-[1fr_20rem]">
                <section
                    class="min-w-0 space-y-5 rounded-xl border p-5"
                    aria-label="Permit record"
                >
                    <p class="font-medium">{{ status }}</p>
                    <h2 class="text-2xl font-semibold break-words">
                        {{
                            completion?.permit_number ?? 'Permit not yet issued'
                        }}
                    </h2>
                    <dl class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <dt class="text-sm text-muted-foreground">
                                Authorized
                            </dt>
                            <dd>{{ date(completion?.authorized_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-muted-foreground">
                                Issued
                            </dt>
                            <dd>{{ date(completion?.issued_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-muted-foreground">
                                Released
                            </dt>
                            <dd>{{ date(completion?.released_at) }}</dd>
                        </div>
                    </dl>
                    <p class="text-xs text-muted-foreground">
                        Dates shown in Philippine time.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <Button as-child variant="outline"
                            ><Link :href="applicationUrl"
                                >View Application</Link
                            ></Button
                        >
                        <Button v-if="permitUrl" as-child variant="outline"
                            ><a
                                :href="permitUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                >View Permit</a
                            ></Button
                        >
                        <Button
                            v-if="verificationUrl"
                            as-child
                            variant="outline"
                            ><a
                                :href="verificationUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                >Verify Permit</a
                            ></Button
                        >
                    </div>
                </section>
                <aside
                    class="min-w-0 space-y-4 rounded-xl border p-5"
                    aria-label="Current action"
                >
                    <h2 class="font-semibold">
                        {{ action ? 'Your action' : 'Current status' }}
                    </h2>
                    <ul class="space-y-2 text-sm">
                        <li
                            v-for="[label, complete] in summary"
                            :key="label"
                            class="flex justify-between gap-3"
                        >
                            <span>{{ label }}</span
                            ><span>{{
                                complete ? 'Complete' : 'Pending'
                            }}</span>
                        </li>
                    </ul>
                    <p class="font-semibold">
                        Receipted
                        {{
                            (
                                readiness.receipt_total_cents / 100
                            ).toLocaleString('en-PH', {
                                style: 'currency',
                                currency: 'PHP',
                            })
                        }}
                    </p>
                    <p
                        v-for="(error, key) in form.errors"
                        :key="key"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ error }}
                    </p>
                    <Button
                        v-if="action"
                        type="button"
                        class="h-auto w-full py-3 whitespace-normal"
                        :disabled="form.processing"
                        @click="submit"
                        >{{
                            form.processing ? 'Saving…' : labels[action]
                        }}</Button
                    >
                    <p v-else class="text-sm text-muted-foreground">
                        {{
                            completion?.released_at
                                ? 'Permit released. No further release action is needed.'
                                : 'No action is currently available for this account.'
                        }}
                    </p>
                    <div v-if="blockers.length" class="space-y-1 text-sm">
                        <p class="font-medium">Still required</p>
                        <ul class="list-inside list-disc">
                            <li v-for="[key] in blockers" :key="key">
                                {{
                                    requirements[key] ??
                                    key.replaceAll('_', ' ')
                                }}
                            </li>
                        </ul>
                    </div>
                </aside>
            </div>
            <details class="min-w-0 rounded-xl border p-4">
                <summary class="cursor-pointer font-medium">Details</summary>
                <div class="mt-4 space-y-3 text-sm">
                    <p class="break-words">
                        Official Receipts:
                        {{ readiness.receipt_numbers.join(', ') || 'Pending' }}
                    </p>
                    <p class="break-all">
                        Authorization reference:
                        {{ completion?.authorization_fingerprint ?? 'Pending' }}
                    </p>
                    <ul class="space-y-1">
                        <li
                            v-for="(passed, key) in readiness.prerequisites"
                            :key="key"
                        >
                            {{ requirements[key] ?? key.replaceAll('_', ' ') }}:
                            {{ passed ? 'Complete' : 'Pending' }}
                        </li>
                    </ul>
                    <p>
                        Synthetic/UAT record. No production Permit authority,
                        statutory electronic signature or municipal legal
                        effect.
                    </p>
                </div>
            </details>
        </main>
    </AppLayout>
</template>
