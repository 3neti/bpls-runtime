<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { BadgeCheck, FileText, Landmark, LockKeyhole } from '@lucide/vue';
import StakeholderPreviewBanner from '@/components/StakeholderPreviewBanner.vue';
import { Badge } from '@/components/ui/badge';
import AuthorityBoundaryPanel from '@/components/workflow/AuthorityBoundaryPanel.vue';

type VerificationBoundary = {
    reference: string;
    url: string;
    view_url: string;
    status: string;
    can_verify_release: boolean;
    released: boolean;
    legal_release_confirmed: boolean;
    legal_effect_confirmed: boolean;
    policy_note: string;
};

type PermitSummary = {
    application_number: string | null;
    application_year: number;
    application_status: string;
    business_name: string;
    trade_name: string | null;
};

type ReleaseReadiness = {
    ready_for_authority_review: boolean;
    can_release: boolean;
    blocked_by: string[];
    authority_boundary: {
        label: string;
        status: string;
        software_knows: Record<string, boolean>;
        human_authority_decides: string[];
        software_records: string[];
        artifact_statement: string;
    };
    reason: string;
};

type PreviewCompletion = {
    semantic_classification: 'provisional_uat';
    status: string;
    permit_number: string | null;
    signature_applied: boolean;
    released_in_preview: boolean;
    production_authority: false;
};

type ReleaseStatus = {
    preview_sample: {
        available: boolean;
        completed: boolean;
        status: string;
    };
    municipal_legal_release: {
        confirmed: false;
        status: 'not_confirmed';
    };
    legal_effect: {
        confirmed: false;
        status: 'not_confirmed';
    };
};

defineProps<{
    verification: VerificationBoundary;
    permit: PermitSummary;
    releaseReadiness: ReleaseReadiness;
    previewCompletion: PreviewCompletion | null;
    releaseStatus: ReleaseStatus;
}>();

function label(value: string): string {
    return value.replaceAll('_', ' ');
}
</script>

<template>
    <Head title="Permit verification" />

    <main
        class="min-h-screen bg-zinc-50 text-zinc-950 dark:bg-zinc-950 dark:text-zinc-50"
    >
        <StakeholderPreviewBanner />
        <div
            class="mx-auto flex max-w-5xl flex-col gap-5 px-4 py-6 sm:px-6 lg:px-8"
        >
            <header
                class="flex flex-col gap-4 border-b border-zinc-200 pb-5 sm:flex-row sm:items-start sm:justify-between dark:border-zinc-800"
            >
                <div class="space-y-2">
                    <div
                        class="flex flex-wrap items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300"
                    >
                        <Landmark class="size-4" />
                        <span>Municipality of Ipil BPLS</span>
                    </div>
                    <h1 class="text-2xl font-semibold tracking-normal">
                        Permit reference check
                    </h1>
                    <p
                        class="max-w-2xl text-sm text-zinc-600 dark:text-zinc-300"
                    >
                        This page confirms a generated permit document
                        reference. It does not confirm municipal release,
                        validity, or legal effect.
                    </p>
                </div>
                <Badge
                    variant="secondary"
                    class="w-fit border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/70 dark:bg-amber-950 dark:text-amber-100"
                >
                    Document reference only
                </Badge>
            </header>

            <section
                class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <div class="mb-4 flex items-center gap-2">
                    <BadgeCheck
                        class="size-4 text-emerald-700 dark:text-emerald-300"
                    />
                    <h2 class="text-base font-semibold">
                        Document reference found
                    </h2>
                </div>
                <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">
                            Reference
                        </dt>
                        <dd class="font-mono text-xs break-all">
                            {{ verification.reference }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">
                            Reference status
                        </dt>
                        <dd class="capitalize">
                            {{ label(verification.status) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">
                            Municipal release
                        </dt>
                        <dd>Not confirmed</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">
                            Legal effect
                        </dt>
                        <dd>Not confirmed</dd>
                    </div>
                </dl>
            </section>

            <section
                class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]"
            >
                <div
                    class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                    data-testid="public-permit-data-card"
                >
                    <p
                        class="mb-3 text-[0.65rem] font-semibold tracking-wide text-amber-700 uppercase dark:text-amber-300"
                    >
                        Preview · Sample Data
                    </p>
                    <div class="mb-4 flex items-center gap-2">
                        <FileText class="size-4 text-zinc-500" />
                        <h2 class="text-base font-semibold">
                            Generated permit document
                        </h2>
                    </div>
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt
                                class="text-xs text-zinc-500 dark:text-zinc-400"
                            >
                                Application number
                            </dt>
                            <dd class="font-medium">
                                {{
                                    permit.application_number ??
                                    'Unnumbered application'
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs text-zinc-500 dark:text-zinc-400"
                            >
                                Business
                            </dt>
                            <dd class="font-medium">
                                {{ permit.business_name }}
                            </dd>
                            <dd
                                v-if="permit.trade_name"
                                class="text-zinc-600 dark:text-zinc-300"
                            >
                                Trade name: {{ permit.trade_name }}
                            </dd>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <dt
                                    class="text-xs text-zinc-500 dark:text-zinc-400"
                                >
                                    Year
                                </dt>
                                <dd>{{ permit.application_year }}</dd>
                            </div>
                            <div>
                                <dt
                                    class="text-xs text-zinc-500 dark:text-zinc-400"
                                >
                                    Application status
                                </dt>
                                <dd class="capitalize">
                                    {{ label(permit.application_status) }}
                                </dd>
                            </div>
                        </div>
                    </dl>
                </div>

                <AuthorityBoundaryPanel
                    title="Municipal release is not confirmed"
                    :status="releaseReadiness.authority_boundary.status"
                    :statement="
                        releaseReadiness.authority_boundary.artifact_statement
                    "
                    :facts="[
                        {
                            label: 'Ready for authority review',
                            value: releaseReadiness.ready_for_authority_review
                                ? 'Yes'
                                : 'No',
                        },
                        {
                            label: 'Municipal release confirmed',
                            value: releaseStatus.municipal_legal_release
                                .confirmed
                                ? 'Yes'
                                : 'No',
                        },
                        {
                            label: 'Legal effect confirmed',
                            value: releaseStatus.legal_effect.confirmed
                                ? 'Yes'
                                : 'No',
                        },
                    ]"
                    :note="releaseReadiness.reason"
                />
            </section>

            <section
                v-if="previewCompletion"
                class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-blue-950 dark:border-blue-900/70 dark:bg-blue-950 dark:text-blue-100"
                data-test="preview-permit-completion"
            >
                <div class="mb-3 flex items-center gap-2">
                    <BadgeCheck class="size-4" />
                    <h2 class="text-base font-semibold">
                        Sample workflow result
                    </h2>
                </div>
                <dl class="grid gap-3 text-sm sm:grid-cols-4">
                    <div>
                        <dt class="text-xs opacity-70">
                            Sample workflow stage
                        </dt>
                        <dd class="capitalize">
                            {{ label(previewCompletion.status) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs opacity-70">
                            Preview permit number
                        </dt>
                        <dd>
                            {{
                                previewCompletion.permit_number ??
                                'Not assigned'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs opacity-70">Sample e-signature</dt>
                        <dd>
                            {{
                                previewCompletion.signature_applied
                                    ? 'Applied'
                                    : 'Not applied'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs opacity-70">
                            Sample workflow completed
                        </dt>
                        <dd>
                            {{
                                releaseStatus.preview_sample.completed
                                    ? 'Yes'
                                    : 'No'
                            }}
                        </dd>
                    </div>
                </dl>
                <p class="mt-3 text-xs leading-5 opacity-80">
                    This is a stakeholder-test result using sample data. It is
                    not an official permit, numbering decision, Mayor
                    credential, municipal release, or legal effect.
                </p>
            </section>

            <section
                class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-950 dark:border-amber-900/70 dark:bg-amber-950 dark:text-amber-100"
            >
                <div class="mb-3 flex items-center gap-2">
                    <LockKeyhole class="size-4" />
                    <h2 class="text-base font-semibold">
                        What this page does not do
                    </h2>
                </div>
                <p class="text-sm">
                    {{ verification.policy_note }}
                </p>
                <p class="mt-2 text-sm">
                    {{ releaseReadiness.reason }}
                </p>
            </section>
        </div>
    </main>
</template>
