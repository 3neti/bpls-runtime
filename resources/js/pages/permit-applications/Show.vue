<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Calculator,
    CircleX,
    Download,
    FileText,
    History,
    LinkIcon,
    ListChecks,
    LockKeyhole,
    Paperclip,
    Upload,
    WalletCards,
} from '@lucide/vue';
import { show as showPaymentSchedule } from '@/actions/App/Http/Controllers/Staff/AssessmentPaymentScheduleController';
import {
    show as showAssessment,
    store as assess,
} from '@/actions/App/Http/Controllers/Staff/PermitApplicationAssessmentController';
import {
    applicationFormPdf,
    cancel,
    completeClearance,
    index,
    permitPdf,
    release,
    show,
} from '@/actions/App/Http/Controllers/Staff/PermitApplicationController';
import { store as storeSupportingDocument } from '@/actions/App/Http/Controllers/Staff/PermitApplicationDocumentController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthorityBoundaryPanel from '@/components/workflow/AuthorityBoundaryPanel.vue';
import WorkflowStageSummary from '@/components/workflow/WorkflowStageSummary.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type PermitApplication = {
    id: number;
    application_number: string | null;
    type: string;
    status: string;
    application_year: number;
    submitted_at: string | null;
    business: {
        name: string;
        trade_name: string | null;
        registration_number: string | null;
        address: string | null;
        barangay: string | null;
        ownership_type: string | null;
        organization_name: string | null;
        occupancy: string | null;
        building_name: string | null;
        property_index_number: string | null;
        business_area_square_meters: string | null;
        male_employee_count: number | null;
        female_employee_count: number | null;
        contact_number: string | null;
        email: string | null;
        established_on: string | null;
        started_on: string | null;
        registered_on: string | null;
        owner: {
            name: string;
            email: string | null;
            phone: string | null;
            address: string | null;
        };
    };
    lines: {
        id: number;
        line_of_business: {
            name: string | null;
            code: string | null;
        };
        declared_gross_sales_cents: number;
        capital_investment_cents: number;
        quantity: number;
        started_on: string | null;
    }[];
    latest_assessment: {
        id: number;
        sequence: number;
        status: string;
        total_amount_cents: number;
        assessed_at: string | null;
    } | null;
    latest_payment_schedule: {
        id: number;
        sequence: number;
        status: string;
        total_amount_cents: number;
        paid_amount_cents: number;
    } | null;
    terminal_state: {
        status: string;
        is_terminal: boolean;
        can_continue: boolean;
        reason: string;
        occurred_at: string;
    } | null;
    renewal_policy_boundary: {
        status: string;
        application_type: string;
        software_knows: Record<string, boolean>;
        unresolved_policy: string[];
        artifact_statement: string;
    } | null;
    amendment_policy_boundary: {
        status: string;
        application_type: string;
        software_knows: Record<string, boolean>;
        unresolved_policy: string[];
        artifact_statement: string;
    } | null;
    transfer_policy_boundary: {
        status: string;
        application_type: string;
        software_knows: Record<string, boolean>;
        legal_evidence: {
            source_id: string;
            section_references: string[];
            ordinance_facts: string[];
            execution_status: string;
        };
        unresolved_policy: string[];
        artifact_statement: string;
    } | null;
    retirement_policy_boundary: {
        status: string;
        application_type: string;
        software_knows: Record<string, boolean>;
        legal_evidence: {
            source_id: string;
            section_references: string[];
            ordinance_facts: string[];
            execution_status: string;
        };
        unresolved_policy: string[];
        artifact_statement: string;
    } | null;
    release_policy_boundary: {
        status: string;
        payment_schedule_id: number | null;
        payment_schedule_status: string | null;
        is_paid: boolean;
        receipt_count: number;
        blocked_transition: string;
        reason: string;
        occurred_at: string;
    } | null;
    permit_artifact: {
        label: string;
        status: string;
        available: boolean;
        ready_for_authority_review: boolean;
        can_issue: boolean;
        can_release: boolean;
        can_make_legally_effective: boolean;
        permit_pdf_url: string;
        verification_reference: string;
        verification_status: string;
        authority_boundary_status: string;
        artifact_statement: string;
        policy_note: string;
        blocked_by: string[];
    };
    release_readiness: {
        ready_for_authority_review: boolean;
        can_release: boolean;
        status: string;
        prerequisites: {
            payment_schedule_paid: boolean;
            receipt_issued: boolean;
            clearances_completed: boolean;
            permit_artifact_available: boolean;
        };
        payment_schedule_id: number | null;
        payment_schedule_status: string | null;
        receipt_count: number;
        clearances_completed: number;
        clearances_total: number;
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
    verification_boundary: {
        reference: string;
        url: string;
        view_url: string;
        status: string;
        can_verify_release: boolean;
        released: boolean;
        policy_note: string;
    };
    clearance_summary: {
        completed: number;
        total: number;
        all_completed: boolean;
        policy_note: string;
    };
    clearances: {
        id: number;
        code: string;
        label: string;
        status: string;
        completed_at: string | null;
        completed_by: {
            id: number;
            name: string;
        } | null;
        remarks: string | null;
        policy_note: string | null;
    }[];
    timeline: {
        key: string;
        category: string;
        title: string;
        description: string;
        status: string;
        actor: {
            id: number;
            name: string;
        } | null;
        occurred_at: string | null;
        source: {
            type: string;
            id: number;
        };
    }[];
    documents: {
        id: number;
        label: string;
        original_name: string;
        mime_type: string;
        size_bytes: number;
        remarks: string | null;
        uploaded_at: string;
        uploaded_by: string | null;
        download_url: string;
        policy_note: string | null;
    }[];
    can_continue: boolean;
};

const props = defineProps<{
    permitApplication: PermitApplication;
    can: {
        assess_permit_applications: boolean;
        update_permit_application_status: boolean;
        view_permit_documents: boolean;
        attempt_release: boolean;
        complete_clearances: boolean;
        upload_documents: boolean;
    };
    permitDocumentGaps: string[];
}>();

const documentForm = useForm({
    label: '',
    file: null as File | null,
    remarks: '',
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Permit Applications',
        href: index(),
    },
    {
        title:
            props.permitApplication.application_number ??
            `Application #${props.permitApplication.id}`,
        href: show(props.permitApplication.id),
    },
];

function money(amountCents: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amountCents / 100);
}

function label(value: string): string {
    return value.replaceAll(/[_-]/g, ' ');
}

function dateTime(value: string | null): string {
    if (value === null) {
        return 'Time not recorded';
    }

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function booleanEntries(
    values: Record<string, boolean>,
): { key: string; value: boolean }[] {
    return Object.entries(values).map(([key, value]) => ({ key, value }));
}

function selectDocument(event: Event): void {
    const input = event.target as HTMLInputElement;
    documentForm.file = input.files?.[0] ?? null;
}

function uploadDocument(): void {
    documentForm.post(storeSupportingDocument.url(props.permitApplication.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => documentForm.reset(),
    });
}

function fileSize(sizeBytes: number): string {
    if (sizeBytes < 1024) {
        return `${sizeBytes} B`;
    }

    return `${(sizeBytes / 1024).toFixed(1)} KB`;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Permit Application" />

        <main class="flex h-full flex-1 flex-col gap-4 p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        {{
                            permitApplication.application_number ??
                            `Application #${permitApplication.id}`
                        }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ permitApplication.business.name }}
                    </p>
                </div>
            </section>

            <WorkflowStageSummary
                eyebrow="Current application record"
                :title="label(permitApplication.status)"
                description="Use only the actions currently available for this record. Financial, clearance, permit document, and approval details are shown separately below."
                :items="[
                    {
                        label: 'Application type',
                        value: label(permitApplication.type),
                        detail: String(permitApplication.application_year),
                    },
                    {
                        label: 'Latest assessment',
                        value: permitApplication.latest_assessment
                            ? `Assessment #${permitApplication.latest_assessment.sequence}`
                            : 'Not assessed',
                        detail:
                            permitApplication.latest_assessment?.status ?? null,
                    },
                    {
                        label: 'Payment schedule',
                        value: permitApplication.latest_payment_schedule
                            ? `Schedule #${permitApplication.latest_payment_schedule.sequence}`
                            : 'Not prepared',
                        detail: permitApplication.latest_payment_schedule
                            ? `${label(permitApplication.latest_payment_schedule.status)} · Balance ${money(permitApplication.latest_payment_schedule.total_amount_cents - permitApplication.latest_payment_schedule.paid_amount_cents)}`
                            : null,
                    },
                    {
                        label: 'Authority review',
                        value: permitApplication.release_readiness
                            .ready_for_authority_review
                            ? 'Ready for Authority Review'
                            : 'Not ready for authority review',
                        detail: permitApplication.release_readiness.can_release
                            ? 'Release action available'
                            : 'Release remains unavailable',
                    },
                ]"
            >
                <template #actions>
                    <div class="flex flex-wrap gap-2 sm:justify-end">
                        <Button
                            v-if="can.view_permit_documents"
                            as-child
                            variant="outline"
                        >
                            <a
                                :href="
                                    applicationFormPdf.url(permitApplication.id)
                                "
                                target="_blank"
                            >
                                <FileText />
                                Application PDF
                            </a>
                        </Button>
                        <Button
                            v-if="can.view_permit_documents"
                            as-child
                            variant="outline"
                        >
                            <a
                                :href="permitPdf.url(permitApplication.id)"
                                target="_blank"
                            >
                                <FileText />
                                Permit PDF
                            </a>
                        </Button>
                        <Button
                            v-if="permitApplication.latest_assessment"
                            as-child
                            variant="outline"
                        >
                            <Link
                                :href="
                                    showAssessment(
                                        permitApplication.latest_assessment.id,
                                    )
                                "
                            >
                                <ListChecks />
                                Assessment
                            </Link>
                        </Button>
                        <Button
                            v-if="permitApplication.latest_payment_schedule"
                            as-child
                            variant="outline"
                        >
                            <Link
                                :href="
                                    showPaymentSchedule(
                                        permitApplication
                                            .latest_payment_schedule.id,
                                    )
                                "
                            >
                                <WalletCards />
                                Payment Schedule
                            </Link>
                        </Button>
                        <Link
                            v-if="
                                can.assess_permit_applications &&
                                permitApplication.can_continue
                            "
                            :href="assess(permitApplication.id)"
                            method="post"
                            as="button"
                            :class="buttonVariants()"
                        >
                            <Calculator />
                            Assess
                        </Link>
                        <Link
                            v-if="
                                can.update_permit_application_status &&
                                permitApplication.can_continue
                            "
                            :href="cancel(permitApplication.id)"
                            method="post"
                            as="button"
                            :data="{
                                reason: 'Cancelled from staff review surface.',
                            }"
                            :class="buttonVariants({ variant: 'destructive' })"
                        >
                            <CircleX />
                            Cancel
                        </Link>
                        <Link
                            v-if="
                                can.attempt_release &&
                                permitApplication.latest_payment_schedule
                            "
                            :href="release(permitApplication.id)"
                            method="post"
                            as="button"
                            title="Records a refused release attempt as evidence. The permit is not released or issued."
                            :class="buttonVariants({ variant: 'outline' })"
                        >
                            <LockKeyhole />
                            Release unavailable
                        </Link>
                    </div>
                </template>
            </WorkflowStageSummary>

            <section
                class="grid gap-4 rounded-lg border border-sidebar-border/70 bg-background p-4 md:grid-cols-3 dark:border-sidebar-border"
            >
                <div>
                    <div class="text-xs text-muted-foreground">Type</div>
                    <div class="capitalize">{{ permitApplication.type }}</div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Status</div>
                    <Badge variant="secondary" class="capitalize">
                        {{ permitApplication.status.replace('_', ' ') }}
                    </Badge>
                    <div
                        v-if="permitApplication.terminal_state"
                        class="mt-2 text-xs text-muted-foreground"
                    >
                        Terminal state. Further processing is unavailable.
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Year</div>
                    <div>{{ permitApplication.application_year }}</div>
                </div>
            </section>

            <section
                v-if="permitApplication.latest_payment_schedule"
                class="grid gap-4 rounded-lg border border-sidebar-border/70 bg-background p-4 md:grid-cols-3 dark:border-sidebar-border"
            >
                <div>
                    <div class="text-xs text-muted-foreground">
                        Payment schedule
                    </div>
                    <div>
                        Sequence
                        {{ permitApplication.latest_payment_schedule.sequence }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">
                        Schedule status
                    </div>
                    <Badge variant="secondary" class="capitalize">
                        {{
                            permitApplication.latest_payment_schedule.status.replace(
                                '_',
                                ' ',
                            )
                        }}
                    </Badge>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Amount due</div>
                    <div>
                        {{
                            money(
                                permitApplication.latest_payment_schedule
                                    .total_amount_cents -
                                    permitApplication.latest_payment_schedule
                                        .paid_amount_cents,
                            )
                        }}
                    </div>
                </div>
            </section>

            <section
                class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                aria-labelledby="permit-artifact-heading"
            >
                <div
                    class="mb-3 flex flex-wrap items-start justify-between gap-3"
                >
                    <div class="flex items-center gap-2">
                        <FileText class="size-4 text-muted-foreground" />
                        <h2
                            id="permit-artifact-heading"
                            class="text-sm font-semibold text-foreground"
                        >
                            Generated permit document
                        </h2>
                    </div>
                    <Button
                        v-if="
                            can.view_permit_documents &&
                            permitApplication.permit_artifact.available
                        "
                        as-child
                        variant="outline"
                    >
                        <a
                            :href="
                                permitApplication.permit_artifact.permit_pdf_url
                            "
                            target="_blank"
                        >
                            <FileText />
                            Open document
                        </a>
                    </Button>
                </div>

                <dl class="grid gap-3 text-sm md:grid-cols-4">
                    <div>
                        <dt class="text-xs text-muted-foreground">Document</dt>
                        <dd>{{ permitApplication.permit_artifact.label }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Status</dt>
                        <dd class="capitalize">
                            {{
                                label(permitApplication.permit_artifact.status)
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Authority review
                        </dt>
                        <dd>
                            {{
                                permitApplication.permit_artifact
                                    .ready_for_authority_review
                                    ? 'Ready'
                                    : 'Not ready'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Legal effect
                        </dt>
                        <dd>
                            {{
                                permitApplication.permit_artifact
                                    .can_make_legally_effective
                                    ? 'Effective'
                                    : 'Not legally effective'
                            }}
                        </dd>
                    </div>
                    <div class="md:col-span-2">
                        <dt class="text-xs text-muted-foreground">
                            Verification reference
                        </dt>
                        <dd class="font-mono text-xs break-all">
                            {{
                                permitApplication.permit_artifact
                                    .verification_reference
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Verification status
                        </dt>
                        <dd class="capitalize">
                            {{
                                label(
                                    permitApplication.permit_artifact
                                        .verification_status,
                                )
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Can issue</dt>
                        <dd>
                            {{
                                permitApplication.permit_artifact.can_issue
                                    ? 'Yes'
                                    : 'No'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Can release
                        </dt>
                        <dd>
                            {{
                                permitApplication.permit_artifact.can_release
                                    ? 'Yes'
                                    : 'No'
                            }}
                        </dd>
                    </div>
                </dl>

                <p class="mt-3 text-sm text-muted-foreground">
                    {{ permitApplication.permit_artifact.policy_note }}
                </p>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ permitApplication.permit_artifact.artifact_statement }}
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <Badge
                        v-for="blocker in permitApplication.permit_artifact
                            .blocked_by"
                        :key="blocker"
                        variant="outline"
                        class="capitalize"
                    >
                        {{ label(blocker) }}
                    </Badge>
                </div>
            </section>

            <section
                class="grid gap-4 rounded-lg border border-sidebar-border/70 bg-background p-4 md:grid-cols-2 dark:border-sidebar-border"
            >
                <div>
                    <h2 class="mb-3 text-sm font-semibold text-foreground">
                        Business
                    </h2>
                    <dl class="grid gap-2 text-sm">
                        <div>
                            <dt class="text-xs text-muted-foreground">Name</dt>
                            <dd>{{ permitApplication.business.name }}</dd>
                        </div>
                        <div v-if="permitApplication.business.trade_name">
                            <dt class="text-xs text-muted-foreground">
                                Trade name
                            </dt>
                            <dd>{{ permitApplication.business.trade_name }}</dd>
                        </div>
                        <div
                            v-if="
                                permitApplication.business.registration_number
                            "
                        >
                            <dt class="text-xs text-muted-foreground">
                                Registration
                            </dt>
                            <dd>
                                {{
                                    permitApplication.business
                                        .registration_number
                                }}
                            </dd>
                        </div>
                        <div v-if="permitApplication.business.barangay">
                            <dt class="text-xs text-muted-foreground">
                                Barangay
                            </dt>
                            <dd>{{ permitApplication.business.barangay }}</dd>
                        </div>
                        <div v-if="permitApplication.business.address">
                            <dt class="text-xs text-muted-foreground">
                                Address
                            </dt>
                            <dd>{{ permitApplication.business.address }}</dd>
                        </div>
                    </dl>
                </div>
                <div>
                    <h2 class="mb-3 text-sm font-semibold text-foreground">
                        Owner
                    </h2>
                    <dl class="grid gap-2 text-sm">
                        <div>
                            <dt class="text-xs text-muted-foreground">Name</dt>
                            <dd>{{ permitApplication.business.owner.name }}</dd>
                        </div>
                        <div v-if="permitApplication.business.owner.email">
                            <dt class="text-xs text-muted-foreground">Email</dt>
                            <dd>
                                {{ permitApplication.business.owner.email }}
                            </dd>
                        </div>
                        <div v-if="permitApplication.business.owner.phone">
                            <dt class="text-xs text-muted-foreground">Phone</dt>
                            <dd>
                                {{ permitApplication.business.owner.phone }}
                            </dd>
                        </div>
                        <div v-if="permitApplication.business.owner.address">
                            <dt class="text-xs text-muted-foreground">
                                Address
                            </dt>
                            <dd>
                                {{ permitApplication.business.owner.address }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section
                data-testid="permit-establishment-profile"
                class="grid gap-4 rounded-lg border border-sidebar-border/70 bg-background p-4 sm:grid-cols-2 lg:grid-cols-4 dark:border-sidebar-border"
            >
                <div class="sm:col-span-2 lg:col-span-4">
                    <h2 class="text-sm font-semibold text-foreground">
                        Establishment profile
                    </h2>
                </div>
                <div v-if="permitApplication.business.ownership_type">
                    <dt class="text-xs text-muted-foreground">
                        Ownership type
                    </dt>
                    <dd data-testid="establishment-ownership-type">
                        {{ label(permitApplication.business.ownership_type) }}
                    </dd>
                </div>
                <div v-if="permitApplication.business.organization_name">
                    <dt class="text-xs text-muted-foreground">
                        Organization/company
                    </dt>
                    <dd>{{ permitApplication.business.organization_name }}</dd>
                </div>
                <div v-if="permitApplication.business.occupancy">
                    <dt class="text-xs text-muted-foreground">Occupancy</dt>
                    <dd data-testid="establishment-occupancy">
                        {{ label(permitApplication.business.occupancy) }}
                    </dd>
                </div>
                <div v-if="permitApplication.business.building_name">
                    <dt class="text-xs text-muted-foreground">Building</dt>
                    <dd>{{ permitApplication.business.building_name }}</dd>
                </div>
                <div v-if="permitApplication.business.property_index_number">
                    <dt class="text-xs text-muted-foreground">
                        Property index number
                    </dt>
                    <dd>
                        {{ permitApplication.business.property_index_number }}
                    </dd>
                </div>
                <div
                    v-if="
                        permitApplication.business.business_area_square_meters
                    "
                >
                    <dt class="text-xs text-muted-foreground">Business area</dt>
                    <dd data-testid="establishment-business-area">
                        {{
                            permitApplication.business
                                .business_area_square_meters
                        }}
                        m²
                    </dd>
                </div>
                <div
                    v-if="
                        permitApplication.business.male_employee_count !==
                            null ||
                        permitApplication.business.female_employee_count !==
                            null
                    "
                >
                    <dt class="text-xs text-muted-foreground">Employees</dt>
                    <dd data-testid="establishment-employee-counts">
                        Male
                        {{
                            permitApplication.business.male_employee_count ?? 0
                        }}
                        · Female
                        {{
                            permitApplication.business.female_employee_count ??
                            0
                        }}
                    </dd>
                </div>
                <div v-if="permitApplication.business.contact_number">
                    <dt class="text-xs text-muted-foreground">Contact</dt>
                    <dd>{{ permitApplication.business.contact_number }}</dd>
                </div>
                <div v-if="permitApplication.business.email">
                    <dt class="text-xs text-muted-foreground">Email</dt>
                    <dd class="break-all">
                        {{ permitApplication.business.email }}
                    </dd>
                </div>
                <div v-if="permitApplication.business.established_on">
                    <dt class="text-xs text-muted-foreground">Established</dt>
                    <dd>{{ permitApplication.business.established_on }}</dd>
                </div>
                <div v-if="permitApplication.business.started_on">
                    <dt class="text-xs text-muted-foreground">
                        Operations started
                    </dt>
                    <dd data-testid="establishment-started-on">
                        {{ permitApplication.business.started_on }}
                    </dd>
                </div>
                <div v-if="permitApplication.business.registered_on">
                    <dt class="text-xs text-muted-foreground">Registered</dt>
                    <dd>{{ permitApplication.business.registered_on }}</dd>
                </div>
            </section>

            <section
                data-testid="permit-business-activities"
                class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background dark:border-sidebar-border"
            >
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead
                            class="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3 font-medium">
                                    Line of business
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Gross sales
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Capital
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Quantity
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="line in permitApplication.lines"
                                :key="line.id"
                                data-testid="permit-business-activity-row"
                                :data-business-activity-id="line.id"
                                :data-business-activity-code="
                                    line.line_of_business.code
                                "
                                :data-business-activity-name="
                                    line.line_of_business.name
                                "
                                :data-declared-gross-sales-cents="
                                    line.declared_gross_sales_cents
                                "
                                :data-capital-investment-cents="
                                    line.capital_investment_cents
                                "
                                :data-quantity="line.quantity"
                                :data-started-on="line.started_on"
                                class="border-b last:border-b-0"
                            >
                                <td class="px-4 py-3">
                                    <div>
                                        {{
                                            line.line_of_business.name ??
                                            'Unclassified'
                                        }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ line.line_of_business.code }}
                                    </div>
                                    <div
                                        v-if="line.started_on"
                                        class="text-xs text-muted-foreground"
                                    >
                                        Started {{ line.started_on }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{ money(line.declared_gross_sales_cents) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{ money(line.capital_investment_cents) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{ line.quantity }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                data-testid="permit-supporting-documents"
                class="grid gap-4 rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
            >
                <div class="flex items-start gap-2">
                    <Paperclip class="mt-0.5 size-4 text-muted-foreground" />
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">
                            Supporting documents
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Received evidence only. Uploading a document does
                            not establish statutory sufficiency or approval.
                        </p>
                    </div>
                </div>

                <form
                    v-if="can.upload_documents"
                    class="grid gap-3 border-y border-border py-4 md:grid-cols-2"
                    enctype="multipart/form-data"
                    @submit.prevent="uploadDocument"
                >
                    <div class="grid gap-2">
                        <Label for="document-label">Document label</Label>
                        <Input
                            id="document-label"
                            v-model="documentForm.label"
                            maxlength="120"
                            required
                        />
                        <InputError :message="documentForm.errors.label" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="document-file">File</Label>
                        <Input
                            id="document-file"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            required
                            @change="selectDocument"
                        />
                        <p class="text-xs text-muted-foreground">
                            PDF, JPG, or PNG up to 10 MB.
                        </p>
                        <InputError :message="documentForm.errors.file" />
                    </div>
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="document-remarks">Remarks</Label>
                        <textarea
                            id="document-remarks"
                            v-model="documentForm.remarks"
                            rows="2"
                            maxlength="1000"
                            class="flex min-h-16 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs ring-offset-background transition-colors placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        />
                        <InputError :message="documentForm.errors.remarks" />
                    </div>
                    <div class="md:col-span-2">
                        <Button
                            type="submit"
                            :disabled="documentForm.processing"
                        >
                            <Upload />
                            Record document
                        </Button>
                    </div>
                </form>

                <div
                    v-if="permitApplication.documents.length === 0"
                    class="py-2 text-sm text-muted-foreground"
                >
                    No supporting documents recorded.
                </div>
                <ul v-else class="divide-y divide-border">
                    <li
                        v-for="document in permitApplication.documents"
                        :key="document.id"
                        data-testid="permit-supporting-document"
                        :data-document-id="document.id"
                        class="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium break-words">
                                {{ document.label }}
                            </p>
                            <p class="text-xs break-all text-muted-foreground">
                                {{ document.original_name }} ·
                                {{ fileSize(document.size_bytes) }}
                            </p>
                            <p
                                v-if="document.remarks"
                                class="mt-1 text-sm break-words text-muted-foreground"
                            >
                                {{ document.remarks }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ dateTime(document.uploaded_at) }}
                                <template v-if="document.uploaded_by">
                                    · {{ document.uploaded_by }}
                                </template>
                            </p>
                        </div>
                        <Button as-child variant="outline" size="sm">
                            <a :href="document.download_url">
                                <Download />
                                Download
                            </a>
                        </Button>
                    </li>
                </ul>
            </section>

            <section
                data-testid="permit-timeline"
                class="border-y border-sidebar-border/70 bg-background py-4 dark:border-sidebar-border"
            >
                <div class="mb-4 flex items-center gap-2 px-1">
                    <History class="size-4 text-muted-foreground" />
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">
                            Application timeline
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Chronological activity from application, assessment,
                            Treasury, clearance, and final-review records.
                        </p>
                    </div>
                </div>

                <ol class="relative ml-3 border-l border-border pl-6">
                    <li
                        v-for="event in permitApplication.timeline"
                        :key="event.key"
                        data-testid="permit-timeline-event"
                        :data-timeline-key="event.key"
                        class="relative pb-5 last:pb-0"
                    >
                        <span
                            class="absolute top-1 -left-[1.77rem] size-3 rounded-full border-2 border-background bg-primary"
                        />
                        <div
                            class="flex flex-wrap items-start justify-between gap-2"
                        >
                            <div class="min-w-0 flex-1">
                                <h3
                                    class="text-sm font-medium break-words text-foreground"
                                >
                                    {{ event.title }}
                                </h3>
                                <p
                                    class="mt-1 text-sm break-words text-muted-foreground"
                                >
                                    {{ event.description }}
                                </p>
                            </div>
                            <Badge variant="secondary" class="capitalize">
                                {{ label(event.status) }}
                            </Badge>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ dateTime(event.occurred_at) }}
                            <template v-if="event.actor">
                                · {{ event.actor.name }}
                            </template>
                        </p>
                    </li>
                </ol>
            </section>

            <section
                v-if="permitApplication.renewal_policy_boundary"
                class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
            >
                <h2 class="mb-3 text-sm font-semibold text-foreground">
                    Renewal processing not yet confirmed
                </h2>
                <dl class="grid gap-3 text-sm md:grid-cols-3">
                    <div>
                        <dt class="text-xs text-muted-foreground">Status</dt>
                        <dd class="capitalize">
                            {{
                                permitApplication.renewal_policy_boundary.status.replace(
                                    '_',
                                    ' ',
                                )
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Application type
                        </dt>
                        <dd class="capitalize">
                            {{
                                permitApplication.renewal_policy_boundary.application_type.replace(
                                    '_',
                                    ' ',
                                )
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Policy state
                        </dt>
                        <dd>Unresolved</dd>
                    </div>
                    <div class="md:col-span-3">
                        <dt class="text-xs text-muted-foreground">
                            Recorded application facts
                        </dt>
                        <dd class="mt-2 flex flex-wrap gap-2">
                            <Badge
                                v-for="entry in booleanEntries(
                                    permitApplication.renewal_policy_boundary
                                        .software_knows,
                                )"
                                :key="entry.key"
                                variant="secondary"
                                class="capitalize"
                            >
                                {{ label(entry.key) }}:
                                {{ entry.value ? 'yes' : 'no' }}
                            </Badge>
                        </dd>
                    </div>
                    <div class="md:col-span-3">
                        <dt class="text-xs text-muted-foreground">
                            Unresolved renewal policy
                        </dt>
                        <dd class="mt-2">
                            <ul class="grid gap-1">
                                <li
                                    v-for="gap in permitApplication
                                        .renewal_policy_boundary
                                        .unresolved_policy"
                                    :key="gap"
                                >
                                    {{ gap }}
                                </li>
                            </ul>
                        </dd>
                    </div>
                </dl>
                <p class="mt-3 text-sm text-muted-foreground">
                    {{
                        permitApplication.renewal_policy_boundary
                            .artifact_statement
                    }}
                </p>
            </section>

            <section
                v-if="permitApplication.amendment_policy_boundary"
                class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
            >
                <h2 class="mb-3 text-sm font-semibold text-foreground">
                    Amendment processing not yet confirmed
                </h2>
                <dl class="grid gap-3 text-sm md:grid-cols-3">
                    <div>
                        <dt class="text-xs text-muted-foreground">Status</dt>
                        <dd class="capitalize">
                            {{
                                permitApplication.amendment_policy_boundary.status.replace(
                                    '_',
                                    ' ',
                                )
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Application type
                        </dt>
                        <dd class="capitalize">
                            {{
                                permitApplication.amendment_policy_boundary.application_type.replace(
                                    '_',
                                    ' ',
                                )
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Policy state
                        </dt>
                        <dd>Unresolved</dd>
                    </div>
                    <div class="md:col-span-3">
                        <dt class="text-xs text-muted-foreground">
                            Recorded application facts
                        </dt>
                        <dd class="mt-2 flex flex-wrap gap-2">
                            <Badge
                                v-for="entry in booleanEntries(
                                    permitApplication.amendment_policy_boundary
                                        .software_knows,
                                )"
                                :key="entry.key"
                                variant="secondary"
                                class="capitalize"
                            >
                                {{ label(entry.key) }}:
                                {{ entry.value ? 'yes' : 'no' }}
                            </Badge>
                        </dd>
                    </div>
                    <div class="md:col-span-3">
                        <dt class="text-xs text-muted-foreground">
                            Unresolved amendment policy
                        </dt>
                        <dd class="mt-2">
                            <ul class="grid gap-1">
                                <li
                                    v-for="gap in permitApplication
                                        .amendment_policy_boundary
                                        .unresolved_policy"
                                    :key="gap"
                                >
                                    {{ gap }}
                                </li>
                            </ul>
                        </dd>
                    </div>
                </dl>
                <p class="mt-3 text-sm text-muted-foreground">
                    {{
                        permitApplication.amendment_policy_boundary
                            .artifact_statement
                    }}
                </p>
            </section>

            <section
                v-if="permitApplication.transfer_policy_boundary"
                class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
            >
                <h2 class="mb-3 text-sm font-semibold text-foreground">
                    Transfer processing not yet confirmed
                </h2>
                <dl class="grid gap-3 text-sm md:grid-cols-3">
                    <div>
                        <dt class="text-xs text-muted-foreground">Status</dt>
                        <dd class="capitalize">
                            {{
                                permitApplication.transfer_policy_boundary.status.replace(
                                    '_',
                                    ' ',
                                )
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Application type
                        </dt>
                        <dd class="capitalize">
                            {{
                                permitApplication.transfer_policy_boundary.application_type.replace(
                                    '_',
                                    ' ',
                                )
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Policy state
                        </dt>
                        <dd>Unresolved</dd>
                    </div>
                    <div class="md:col-span-3">
                        <dt class="text-xs text-muted-foreground">
                            Recorded application facts
                        </dt>
                        <dd class="mt-2 flex flex-wrap gap-2">
                            <Badge
                                v-for="entry in booleanEntries(
                                    permitApplication.transfer_policy_boundary
                                        .software_knows,
                                )"
                                :key="entry.key"
                                variant="secondary"
                                class="capitalize"
                            >
                                {{ label(entry.key) }}:
                                {{ entry.value ? 'yes' : 'no' }}
                            </Badge>
                        </dd>
                    </div>
                    <div class="md:col-span-3">
                        <dt class="text-xs text-muted-foreground">
                            Ordinance evidence
                        </dt>
                        <dd class="mt-2 grid gap-2">
                            <div class="flex flex-wrap gap-2">
                                <Badge variant="secondary">
                                    {{
                                        permitApplication
                                            .transfer_policy_boundary
                                            .legal_evidence.source_id
                                    }}
                                </Badge>
                                <Badge variant="outline" class="capitalize">
                                    {{
                                        permitApplication.transfer_policy_boundary.legal_evidence.execution_status.replaceAll(
                                            '_',
                                            ' ',
                                        )
                                    }}
                                </Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    permitApplication.transfer_policy_boundary.legal_evidence.section_references.join(
                                        ' · ',
                                    )
                                }}
                            </p>
                            <ul class="grid list-disc gap-1 pl-5">
                                <li
                                    v-for="fact in permitApplication
                                        .transfer_policy_boundary.legal_evidence
                                        .ordinance_facts"
                                    :key="fact"
                                >
                                    {{ fact }}
                                </li>
                            </ul>
                        </dd>
                    </div>
                    <div class="md:col-span-3">
                        <dt class="text-xs text-muted-foreground">
                            Unresolved transfer policy
                        </dt>
                        <dd class="mt-2">
                            <ul class="grid gap-1">
                                <li
                                    v-for="gap in permitApplication
                                        .transfer_policy_boundary
                                        .unresolved_policy"
                                    :key="gap"
                                >
                                    {{ gap }}
                                </li>
                            </ul>
                        </dd>
                    </div>
                </dl>
                <p class="mt-3 text-sm text-muted-foreground">
                    {{
                        permitApplication.transfer_policy_boundary
                            .artifact_statement
                    }}
                </p>
            </section>

            <section
                v-if="permitApplication.retirement_policy_boundary"
                class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
            >
                <h2 class="mb-3 text-sm font-semibold text-foreground">
                    Retirement processing not yet confirmed
                </h2>
                <dl class="grid gap-3 text-sm md:grid-cols-3">
                    <div>
                        <dt class="text-xs text-muted-foreground">Status</dt>
                        <dd class="capitalize">
                            {{
                                permitApplication.retirement_policy_boundary.status.replace(
                                    '_',
                                    ' ',
                                )
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Application type
                        </dt>
                        <dd class="capitalize">
                            {{
                                permitApplication.retirement_policy_boundary.application_type.replace(
                                    '_',
                                    ' ',
                                )
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Policy state
                        </dt>
                        <dd>Unresolved</dd>
                    </div>
                    <div class="md:col-span-3">
                        <dt class="text-xs text-muted-foreground">
                            Recorded application facts
                        </dt>
                        <dd class="mt-2 flex flex-wrap gap-2">
                            <Badge
                                v-for="entry in booleanEntries(
                                    permitApplication.retirement_policy_boundary
                                        .software_knows,
                                )"
                                :key="entry.key"
                                variant="secondary"
                                class="capitalize"
                            >
                                {{ label(entry.key) }}:
                                {{ entry.value ? 'yes' : 'no' }}
                            </Badge>
                        </dd>
                    </div>
                    <div class="md:col-span-3">
                        <dt class="text-xs text-muted-foreground">
                            Ordinance evidence
                        </dt>
                        <dd class="mt-2 grid gap-2">
                            <div class="flex flex-wrap gap-2">
                                <Badge variant="secondary">
                                    {{
                                        permitApplication
                                            .retirement_policy_boundary
                                            .legal_evidence.source_id
                                    }}
                                </Badge>
                                <Badge variant="outline" class="capitalize">
                                    {{
                                        permitApplication.retirement_policy_boundary.legal_evidence.execution_status.replaceAll(
                                            '_',
                                            ' ',
                                        )
                                    }}
                                </Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    permitApplication.retirement_policy_boundary.legal_evidence.section_references.join(
                                        ' · ',
                                    )
                                }}
                            </p>
                            <ul class="grid list-disc gap-1 pl-5">
                                <li
                                    v-for="fact in permitApplication
                                        .retirement_policy_boundary
                                        .legal_evidence.ordinance_facts"
                                    :key="fact"
                                >
                                    {{ fact }}
                                </li>
                            </ul>
                        </dd>
                    </div>
                    <div class="md:col-span-3">
                        <dt class="text-xs text-muted-foreground">
                            Unresolved retirement policy
                        </dt>
                        <dd class="mt-2">
                            <ul class="grid gap-1">
                                <li
                                    v-for="gap in permitApplication
                                        .retirement_policy_boundary
                                        .unresolved_policy"
                                    :key="gap"
                                >
                                    {{ gap }}
                                </li>
                            </ul>
                        </dd>
                    </div>
                </dl>
                <p class="mt-3 text-sm text-muted-foreground">
                    {{
                        permitApplication.retirement_policy_boundary
                            .artifact_statement
                    }}
                </p>
            </section>

            <section
                v-if="permitApplication.terminal_state"
                class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
            >
                <h2 class="mb-2 text-sm font-semibold text-foreground">
                    Terminal status evidence
                </h2>
                <dl class="grid gap-2 text-sm md:grid-cols-3">
                    <div>
                        <dt class="text-xs text-muted-foreground">Status</dt>
                        <dd class="capitalize">
                            {{
                                permitApplication.terminal_state.status.replace(
                                    '_',
                                    ' ',
                                )
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Can continue
                        </dt>
                        <dd>
                            {{
                                permitApplication.terminal_state.can_continue
                                    ? 'Yes'
                                    : 'No'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Reason</dt>
                        <dd>{{ permitApplication.terminal_state.reason }}</dd>
                    </div>
                </dl>
            </section>

            <section
                class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
            >
                <div
                    class="mb-3 flex flex-wrap items-center justify-between gap-3"
                >
                    <div class="flex items-center gap-2">
                        <LinkIcon class="size-4 text-muted-foreground" />
                        <h2 class="text-sm font-semibold text-foreground">
                            Public permit reference
                        </h2>
                    </div>
                    <Button as-child variant="outline">
                        <a
                            :href="
                                permitApplication.verification_boundary.view_url
                            "
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <LinkIcon />
                            Open public page
                        </a>
                    </Button>
                </div>
                <dl class="grid gap-3 text-sm md:grid-cols-4">
                    <div>
                        <dt class="text-xs text-muted-foreground">Reference</dt>
                        <dd class="font-mono text-xs break-all">
                            {{
                                permitApplication.verification_boundary
                                    .reference
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Status</dt>
                        <dd class="capitalize">
                            {{
                                permitApplication.verification_boundary.status.replace(
                                    '_',
                                    ' ',
                                )
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Municipal release
                        </dt>
                        <dd>
                            {{
                                permitApplication.verification_boundary
                                    .can_verify_release
                                    ? 'Confirmed'
                                    : 'Not confirmed'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Legal effect
                        </dt>
                        <dd>Not confirmed</dd>
                    </div>
                    <div class="md:col-span-4">
                        <dt class="text-xs text-muted-foreground">
                            Public page
                        </dt>
                        <dd class="font-mono text-xs break-all">
                            {{
                                permitApplication.verification_boundary.view_url
                            }}
                        </dd>
                    </div>
                </dl>
                <p class="mt-3 text-sm text-muted-foreground">
                    {{ permitApplication.verification_boundary.policy_note }}
                </p>
            </section>

            <section
                class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
            >
                <div
                    class="mb-3 flex flex-wrap items-center justify-between gap-3"
                >
                    <div class="flex items-center gap-2">
                        <ListChecks class="size-4 text-muted-foreground" />
                        <h2 class="text-sm font-semibold text-foreground">
                            Clearance checklist
                        </h2>
                    </div>
                    <Badge
                        :variant="
                            permitApplication.clearance_summary.all_completed
                                ? 'default'
                                : 'secondary'
                        "
                        class="capitalize"
                    >
                        {{ permitApplication.clearance_summary.completed }} of
                        {{ permitApplication.clearance_summary.total }}
                        checklist items complete
                    </Badge>
                </div>
                <p class="mb-4 text-sm text-muted-foreground">
                    {{ permitApplication.clearance_summary.policy_note }}
                </p>
                <div class="grid gap-3">
                    <div
                        v-for="clearance in permitApplication.clearances"
                        :key="clearance.id"
                        class="grid gap-3 rounded-md border border-sidebar-border/70 p-3 md:grid-cols-[1fr_auto] md:items-center dark:border-sidebar-border"
                    >
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-sm font-medium text-foreground">
                                    {{ clearance.label }}
                                </h3>
                                <Badge variant="secondary" class="capitalize">
                                    <span class="sr-only"
                                        >Clearance status: </span
                                    >{{ clearance.status.replace('_', ' ') }}
                                </Badge>
                            </div>
                            <p
                                v-if="clearance.policy_note"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{ clearance.policy_note }}
                            </p>
                            <p
                                v-if="clearance.completed_by"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                Completed by
                                {{ clearance.completed_by.name }}
                            </p>
                            <p
                                v-if="clearance.remarks"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{ clearance.remarks }}
                            </p>
                        </div>
                        <Link
                            v-if="
                                can.complete_clearances &&
                                clearance.status !== 'completed'
                            "
                            :href="
                                completeClearance([
                                    permitApplication.id,
                                    clearance.id,
                                ])
                            "
                            method="post"
                            as="button"
                            :data="{
                                remarks: 'Completed from staff review surface.',
                            }"
                            :class="buttonVariants({ variant: 'outline' })"
                        >
                            <ListChecks />
                            Mark complete
                        </Link>
                    </div>
                </div>
            </section>

            <AuthorityBoundaryPanel
                title="Ready for municipal review — not released"
                :status="
                    permitApplication.release_readiness.authority_boundary
                        .status
                "
                :statement="'Payment, receipt, and checklist completion can prepare an application for review. Municipal release and legal effect are not confirmed by this preview.'"
                :facts="[
                    {
                        label: 'Ready for authority review',
                        value: permitApplication.release_readiness
                            .ready_for_authority_review
                            ? 'Yes'
                            : 'No',
                    },
                    {
                        label: 'Paid schedule',
                        value: permitApplication.release_readiness.prerequisites
                            .payment_schedule_paid
                            ? 'Yes'
                            : 'No',
                    },
                    {
                        label: 'Receipt issued',
                        value: permitApplication.release_readiness.prerequisites
                            .receipt_issued
                            ? 'Yes'
                            : 'No',
                    },
                    {
                        label: 'Municipal release confirmed',
                        value: permitApplication.release_readiness.can_release
                            ? 'Yes'
                            : 'No',
                    },
                ]"
                :note="permitApplication.release_readiness.reason"
            />

            <section
                class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
            >
                <h2 class="mb-2 text-sm font-semibold text-foreground">
                    Permit document gaps
                </h2>
                <ul
                    class="list-disc space-y-1 pl-5 text-sm text-muted-foreground"
                >
                    <li v-for="gap in permitDocumentGaps" :key="gap">
                        {{ gap }}
                    </li>
                </ul>
            </section>
        </main>
    </AppLayout>
</template>
