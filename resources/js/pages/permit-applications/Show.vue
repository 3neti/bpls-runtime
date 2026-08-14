<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Calculator, CircleX, FileText, ListChecks, LockKeyhole, WalletCards } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    applicationFormPdf,
    cancel,
    completeClearance,
    index,
    permitPdf,
    release,
    show,
} from '@/actions/App/Http/Controllers/Staff/PermitApplicationController';
import { show as showAssessment, store as assess } from '@/actions/App/Http/Controllers/Staff/PermitApplicationAssessmentController';
import { show as showPaymentSchedule } from '@/actions/App/Http/Controllers/Staff/AssessmentPaymentScheduleController';
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
        reason: string;
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
    };
    permitDocumentGaps: string[];
}>();

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
                <div class="flex gap-2">
                    <Button
                        v-if="can.view_permit_documents"
                        as-child
                        variant="outline"
                    >
                        <a
                            :href="applicationFormPdf.url(permitApplication.id)"
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
                        <a :href="permitPdf.url(permitApplication.id)" target="_blank">
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
                                    permitApplication.latest_payment_schedule.id,
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
                        :class="buttonVariants({ variant: 'outline' })"
                    >
                        <LockKeyhole />
                        Release unavailable
                    </Link>
                </div>
            </section>

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
                    <div class="text-xs text-muted-foreground">
                        Amount due
                    </div>
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
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{
                                        money(
                                            line.declared_gross_sales_cents,
                                        )
                                    }}
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
                        {{ permitApplication.clearance_summary.completed }} /
                        {{ permitApplication.clearance_summary.total }}
                        complete
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
                                    {{ clearance.status.replace('_', ' ') }}
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
                                remarks:
                                    'Completed from staff review surface.',
                            }"
                            :class="buttonVariants({ variant: 'outline' })"
                        >
                            <ListChecks />
                            Mark complete
                        </Link>
                    </div>
                </div>
            </section>

            <section
                class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
            >
                <div class="mb-3 flex items-center gap-2">
                    <LockKeyhole class="size-4 text-muted-foreground" />
                    <h2 class="text-sm font-semibold text-foreground">
                        Permit release boundary
                    </h2>
                </div>
                <p class="text-sm text-muted-foreground">
                    Permit release is unavailable until clearance completion,
                    issuance authority, signatories, QR verification, and legacy
                    Released status semantics are reconciled.
                </p>
                <div class="mt-4 grid gap-3 text-sm md:grid-cols-4">
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Authority review
                        </dt>
                        <dd>
                            {{
                                permitApplication.release_readiness
                                    .ready_for_authority_review
                                    ? 'Ready'
                                    : 'Not ready'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Paid schedule
                        </dt>
                        <dd>
                            {{
                                permitApplication.release_readiness.prerequisites
                                    .payment_schedule_paid
                                    ? 'Yes'
                                    : 'No'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Receipt issued
                        </dt>
                        <dd>
                            {{
                                permitApplication.release_readiness.prerequisites
                                    .receipt_issued
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
                                permitApplication.release_readiness.can_release
                                    ? 'Yes'
                                    : 'No'
                            }}
                        </dd>
                    </div>
                </div>
                <p class="mt-3 text-sm text-muted-foreground">
                    {{ permitApplication.release_readiness.reason }}
                </p>
                <dl
                    v-if="permitApplication.release_policy_boundary"
                    class="mt-4 grid gap-2 text-sm md:grid-cols-3"
                >
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Attempted transition
                        </dt>
                        <dd class="capitalize">
                            {{
                                permitApplication.release_policy_boundary.blocked_transition.replace(
                                    '_',
                                    ' ',
                                )
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Paid schedule
                        </dt>
                        <dd>
                            {{
                                permitApplication.release_policy_boundary.is_paid
                                    ? 'Yes'
                                    : 'No'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Receipts
                        </dt>
                        <dd>
                            {{
                                permitApplication.release_policy_boundary
                                    .receipt_count
                            }}
                        </dd>
                    </div>
                    <div class="md:col-span-3">
                        <dt class="text-xs text-muted-foreground">Reason</dt>
                        <dd>
                            {{ permitApplication.release_policy_boundary.reason }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section
                class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
            >
                <h2 class="mb-2 text-sm font-semibold text-foreground">
                    Permit document gaps
                </h2>
                <ul class="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                    <li v-for="gap in permitDocumentGaps" :key="gap">
                        {{ gap }}
                    </li>
                </ul>
            </section>
        </main>
    </AppLayout>
</template>
