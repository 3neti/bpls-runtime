<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Banknote, ReceiptText } from '@lucide/vue';
import { computed } from 'vue';
import { show as paymentScheduleShow } from '@/actions/App/Http/Controllers/Staff/AssessmentPaymentScheduleController';
import { store as receiptStore } from '@/actions/App/Http/Controllers/Staff/CollectionReceiptController';
import { store as collectionStore } from '@/actions/App/Http/Controllers/Staff/PaymentScheduleCollectionController';
import { show as assessmentShow } from '@/actions/App/Http/Controllers/Staff/PermitApplicationAssessmentController';
import { show as receiptShow } from '@/actions/App/Http/Controllers/Staff/ReceiptController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import WorkflowSectionHeader from '@/components/workflow/WorkflowSectionHeader.vue';
import WorkflowStageSummary from '@/components/workflow/WorkflowStageSummary.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type PaymentScheduleLine = {
    id: number;
    assessment_line_id: number | null;
    code: string;
    name: string;
    category: string;
    due_on: string | null;
    status: string;
    amount_cents: number;
    paid_amount_cents: number;
    line_of_business: string | null;
};

type PaymentSchedule = {
    id: number;
    sequence: number;
    status: string;
    payment_mode: string;
    due_on: string | null;
    total_amount_cents: number;
    paid_amount_cents: number;
    prepared_by: string | null;
    created_at: string | null;
    assessment: {
        id: number;
        sequence: number;
        status: string;
    };
    permit_application: {
        id: number;
        application_number: string | null;
        type: string;
        status: string;
        application_year: number;
        business_name: string;
        owner_name: string;
    };
    lines: PaymentScheduleLine[];
    collections: TreasuryCollection[];
    payment_policy_boundary: PaymentPolicyBoundary;
    online_payment_boundary: OnlinePaymentBoundary;
};

type PaymentPolicyBoundary = {
    status: string;
    can_calculate_surcharge: boolean;
    can_calculate_interest: boolean;
    can_validate_pil: boolean;
    can_calculate_deficiency_tax: boolean;
    can_split_installments: boolean;
    can_assign_statutory_due_dates: boolean;
    payment_schedule_id: number;
    payment_schedule_status: string;
    supported_payment_modes: string[];
    blocked_calculations: string[];
    software_knows: {
        payment_schedule_exists: boolean;
        assessment_snapshot_total_cents: number;
        paid_amount_cents: number;
        balance_due_cents: number;
        assessment_lines_are_snapshotted: boolean;
    };
    unresolved_policy: string[];
    artifact_statement: string;
};

type OnlinePaymentBoundary = {
    status: string;
    can_pay_online: boolean;
    can_reconcile_online: boolean;
    payment_schedule_id: number;
    payment_schedule_status: string;
    blocked_transitions: string[];
    software_knows: {
        payment_schedule_exists: boolean;
        balance_due_cents: number;
        otc_collection_is_available: boolean;
        gateway_adapter_is_not_configured: boolean;
        reconciliation_policy_is_not_resolved: boolean;
    };
    unresolved_policy: string[];
    artifact_statement: string;
};

type TreasuryCollection = {
    id: number;
    status: string;
    channel: string;
    method: string;
    amount_cents: number;
    payer_name: string | null;
    reference_number: string | null;
    received_at: string;
    received_by: string | null;
    receipt: Receipt | null;
    allocations: CollectionAllocation[];
};

type Receipt = {
    id: number;
    status: string;
    numbering_authority: string;
    receipt_number: string;
    amount_cents: number;
    issued_at: string;
    issued_by: string | null;
};

type CollectionAllocation = {
    id: number;
    payment_schedule_line_id: number;
    code: string;
    name: string;
    amount_cents: number;
};

type Option = {
    label: string;
    value: string;
};

const props = defineProps<{
    paymentSchedule: PaymentSchedule;
    collectionMethods: Option[];
    can: {
        record_collections: boolean;
        view_collections: boolean;
        issue_receipts: boolean;
        view_receipts: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Payment Schedule',
        href: paymentScheduleShow(props.paymentSchedule.id),
    },
];

const balanceDueCents = computed(
    () =>
        props.paymentSchedule.total_amount_cents -
        props.paymentSchedule.paid_amount_cents,
);

function money(amountCents: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amountCents / 100);
}

function label(value: string): string {
    return value.replaceAll('_', ' ');
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Payment Schedule #${paymentSchedule.sequence}`" />

        <main class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex flex-col gap-1">
                    <Button
                        as-child
                        variant="ghost"
                        size="sm"
                        class="w-fit px-0"
                    >
                        <Link
                            :href="
                                assessmentShow(paymentSchedule.assessment.id)
                            "
                        >
                            <ArrowLeft />
                            Back
                        </Link>
                    </Button>
                    <h1 class="text-xl font-semibold text-foreground">
                        Payment Schedule #{{ paymentSchedule.sequence }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ paymentSchedule.permit_application.business_name }}
                        · {{ paymentSchedule.permit_application.owner_name }}
                    </p>
                </div>
            </div>

            <WorkflowStageSummary
                eyebrow="Current Treasury record"
                :title="`Balance ${money(balanceDueCents)}`"
                description="The payment schedule, collection, and receipt are recorded separately."
                :items="[
                    {
                        label: 'Schedule status',
                        value: label(paymentSchedule.status),
                        detail: `Total ${money(paymentSchedule.total_amount_cents)} · Paid ${money(paymentSchedule.paid_amount_cents)}`,
                    },
                    {
                        label: 'Application',
                        value:
                            paymentSchedule.permit_application
                                .application_number ??
                            `Application #${paymentSchedule.permit_application.id}`,
                        detail: `${paymentSchedule.permit_application.type} · ${paymentSchedule.permit_application.application_year}`,
                    },
                    {
                        label: 'Payment mode',
                        value: label(paymentSchedule.payment_mode),
                        detail: paymentSchedule.due_on ?? 'No due date set',
                    },
                    {
                        label: 'Current task',
                        value:
                            can.record_collections && balanceDueCents > 0
                                ? 'Record over-the-counter collection'
                                : can.issue_receipts &&
                                    paymentSchedule.collections.some(
                                        (collection) =>
                                            collection.status ===
                                                'pending_receipt' &&
                                            collection.receipt === null,
                                    )
                                  ? 'Issue pending receipt'
                                  : 'Review collection evidence',
                        detail: `Prepared by ${paymentSchedule.prepared_by ?? 'System'}`,
                    },
                ]"
            />

            <section
                v-if="can.record_collections && balanceDueCents > 0"
                class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
            >
                <WorkflowSectionHeader
                    class="mb-4"
                    eyebrow="Current authorized task"
                    title="Record collection"
                    :description="`Over-the-counter collection only. The current balance is ${money(balanceDueCents)}; receipt issuance remains a separate action below.`"
                />

                <Form
                    v-bind="collectionStore.form(paymentSchedule.id)"
                    v-slot="{ errors, processing }"
                    class="grid gap-4 md:grid-cols-5"
                >
                    <div class="grid gap-2">
                        <Label for="amount_pesos">Amount</Label>
                        <Input
                            id="amount_pesos"
                            name="amount_pesos"
                            type="number"
                            min="0.01"
                            step="0.01"
                            :max="(balanceDueCents / 100).toFixed(2)"
                            required
                        />
                        <InputError :message="errors.amount_pesos" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="method">Method</Label>
                        <select
                            id="method"
                            name="method"
                            required
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background transition-colors placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option
                                v-for="method in collectionMethods"
                                :key="method.value"
                                :value="method.value"
                            >
                                {{ method.label }}
                            </option>
                        </select>
                        <InputError :message="errors.method" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="payer_name">Payer</Label>
                        <Input id="payer_name" name="payer_name" />
                        <InputError :message="errors.payer_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="reference_number">Reference</Label>
                        <Input id="reference_number" name="reference_number" />
                        <InputError :message="errors.reference_number" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="remarks">Remarks</Label>
                        <Input id="remarks" name="remarks" />
                        <InputError :message="errors.remarks" />
                    </div>
                    <div class="md:col-span-5">
                        <Button type="submit" :disabled="processing">
                            <Banknote />
                            {{
                                processing
                                    ? 'Recording...'
                                    : 'Record Collection'
                            }}
                        </Button>
                    </div>
                </Form>
            </section>

            <section
                class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
            >
                <div class="mb-4 flex items-center gap-2">
                    <Banknote class="size-4 text-muted-foreground" />
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">
                            Payment options
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Installments, statutory due dates, surcharge,
                            interest, PIL, and deficiency-tax behavior remain
                            subject to municipal confirmation.
                        </p>
                    </div>
                </div>
                <dl class="grid gap-3 text-sm md:grid-cols-4">
                    <div>
                        <dt class="text-xs text-muted-foreground">Status</dt>
                        <dd class="capitalize">
                            {{
                                paymentSchedule.payment_policy_boundary.status.replace(
                                    '_',
                                    ' ',
                                ) === 'blocked'
                                    ? 'Not available in this preview'
                                    : paymentSchedule.payment_policy_boundary.status.replace(
                                          '_',
                                          ' ',
                                      )
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Installments
                        </dt>
                        <dd>
                            {{
                                paymentSchedule.payment_policy_boundary
                                    .can_split_installments
                                    ? 'Configured'
                                    : 'Not available in this preview'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Statutory due dates
                        </dt>
                        <dd>
                            {{
                                paymentSchedule.payment_policy_boundary
                                    .can_assign_statutory_due_dates
                                    ? 'Configured'
                                    : 'Not yet confirmed'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Surcharge</dt>
                        <dd>
                            {{
                                paymentSchedule.payment_policy_boundary
                                    .can_calculate_surcharge
                                    ? 'Calculated'
                                    : 'Not yet confirmed'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Interest</dt>
                        <dd>
                            {{
                                paymentSchedule.payment_policy_boundary
                                    .can_calculate_interest
                                    ? 'Calculated'
                                    : 'Not yet confirmed'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            PIL validation
                        </dt>
                        <dd>
                            {{
                                paymentSchedule.payment_policy_boundary
                                    .can_validate_pil
                                    ? 'Active'
                                    : 'Not yet confirmed'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Deficiency tax
                        </dt>
                        <dd>
                            {{
                                paymentSchedule.payment_policy_boundary
                                    .can_calculate_deficiency_tax
                                    ? 'Active'
                                    : 'Not yet confirmed'
                            }}
                        </dd>
                    </div>
                    <div class="md:col-span-2">
                        <dt class="text-xs text-muted-foreground">
                            Supported payment modes
                        </dt>
                        <dd class="mt-2 flex flex-wrap gap-2">
                            <Badge
                                v-for="mode in paymentSchedule
                                    .payment_policy_boundary
                                    .supported_payment_modes"
                                :key="mode"
                                variant="outline"
                                class="capitalize"
                            >
                                {{ label(mode) }}
                            </Badge>
                        </dd>
                    </div>
                    <div class="md:col-span-2">
                        <dt class="text-xs text-muted-foreground">
                            Calculations not active
                        </dt>
                        <dd class="mt-2 flex flex-wrap gap-2">
                            <Badge
                                v-for="calculation in paymentSchedule
                                    .payment_policy_boundary
                                    .blocked_calculations"
                                :key="calculation"
                                variant="secondary"
                                class="capitalize"
                            >
                                {{ label(calculation) }}
                            </Badge>
                        </dd>
                    </div>
                    <div class="md:col-span-2">
                        <dt class="text-xs text-muted-foreground">
                            Needs municipal confirmation
                        </dt>
                        <dd class="mt-2">
                            <ul class="grid gap-1">
                                <li
                                    v-for="gap in paymentSchedule
                                        .payment_policy_boundary
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
                    This preview uses the payment arrangement recorded for this
                    sample. Other payment arrangements remain unavailable until
                    the municipality confirms how they should work.
                </p>
            </section>

            <section
                class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
            >
                <div class="mb-4 flex items-center gap-2">
                    <Banknote class="size-4 text-muted-foreground" />
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">
                            Online payment availability
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Electronic payment is not active in this preview.
                        </p>
                    </div>
                </div>
                <dl class="grid gap-3 text-sm md:grid-cols-4">
                    <div>
                        <dt class="text-xs text-muted-foreground">Status</dt>
                        <dd class="capitalize">
                            {{
                                paymentSchedule.online_payment_boundary
                                    .status === 'blocked'
                                    ? 'Not available in this preview'
                                    : paymentSchedule.online_payment_boundary.status.replace(
                                          '_',
                                          ' ',
                                      )
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Online payment in this preview
                        </dt>
                        <dd>
                            {{
                                paymentSchedule.online_payment_boundary
                                    .can_pay_online
                                    ? 'Available'
                                    : 'Not available'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Online payment matching
                        </dt>
                        <dd>
                            {{
                                paymentSchedule.online_payment_boundary
                                    .can_reconcile_online
                                    ? 'Available'
                                    : 'Not available'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Payment service
                        </dt>
                        <dd>Not configured</dd>
                    </div>
                    <div class="md:col-span-2">
                        <dt class="text-xs text-muted-foreground">
                            Actions not active
                        </dt>
                        <dd class="mt-2 flex flex-wrap gap-2">
                            <Badge
                                v-for="transition in paymentSchedule
                                    .online_payment_boundary
                                    .blocked_transitions"
                                :key="transition"
                                variant="secondary"
                                class="capitalize"
                            >
                                {{ label(transition) }}
                            </Badge>
                        </dd>
                    </div>
                    <div class="md:col-span-2">
                        <dt class="text-xs text-muted-foreground">
                            Needs municipal confirmation
                        </dt>
                        <dd class="mt-2">
                            <ul class="grid gap-1">
                                <li
                                    v-for="gap in paymentSchedule
                                        .online_payment_boundary
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
                    No online payment is accepted or recorded by this preview.
                </p>
            </section>

            <section
                class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background dark:border-sidebar-border"
            >
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[920px] text-sm">
                        <thead
                            class="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3 font-medium">Code</th>
                                <th class="px-4 py-3 font-medium">Item</th>
                                <th class="px-4 py-3 font-medium">Category</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Paid
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Amount
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="line in paymentSchedule.lines"
                                :key="line.id"
                                class="border-b last:border-b-0"
                            >
                                <td
                                    class="px-4 py-3 align-top font-mono text-xs"
                                >
                                    {{ line.code }}
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="font-medium">
                                        {{ line.name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{
                                            line.line_of_business ??
                                            'Application-wide'
                                        }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top capitalize">
                                    {{ line.category }}
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <Badge variant="outline" class="capitalize">
                                        {{ line.status.replace('_', ' ') }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-right align-top">
                                    {{ money(line.paid_amount_cents) }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right align-top font-medium"
                                >
                                    {{ money(line.amount_cents) }}
                                </td>
                            </tr>
                            <tr v-if="paymentSchedule.lines.length === 0">
                                <td
                                    colspan="6"
                                    class="px-4 py-10 text-center text-muted-foreground"
                                >
                                    No payment schedule lines have been
                                    prepared.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                v-if="
                    can.view_collections ||
                    can.record_collections ||
                    can.view_receipts ||
                    can.issue_receipts
                "
                class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background dark:border-sidebar-border"
            >
                <div class="border-b px-4 py-3">
                    <WorkflowSectionHeader
                        eyebrow="Recorded after scheduling"
                        title="Collection and receipt evidence"
                        description="Review recorded collections and use the existing receipt action only where it is available."
                    />
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px] text-sm">
                        <thead
                            class="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3 font-medium">Date</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium">Method</th>
                                <th class="px-4 py-3 font-medium">Payer</th>
                                <th class="px-4 py-3 font-medium">Reference</th>
                                <th class="px-4 py-3 font-medium">
                                    Received by
                                </th>
                                <th class="px-4 py-3 font-medium">Receipt</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Amount
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="collection in paymentSchedule.collections"
                                :key="collection.id"
                                class="border-b last:border-b-0"
                            >
                                <td class="px-4 py-3 align-top">
                                    {{ collection.received_at }}
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <Badge variant="outline" class="capitalize">
                                        {{
                                            collection.status.replace('_', ' ')
                                        }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 align-top capitalize">
                                    {{ collection.method.replace('_', ' ') }}
                                </td>
                                <td class="px-4 py-3 align-top">
                                    {{
                                        collection.payer_name ?? 'Not recorded'
                                    }}
                                </td>
                                <td class="px-4 py-3 align-top">
                                    {{
                                        collection.reference_number ??
                                        'Not recorded'
                                    }}
                                </td>
                                <td class="px-4 py-3 align-top">
                                    {{ collection.received_by ?? 'System' }}
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div v-if="collection.receipt">
                                        <Badge
                                            variant="secondary"
                                            class="capitalize"
                                        >
                                            {{
                                                collection.receipt.status.replace(
                                                    '_',
                                                    ' ',
                                                )
                                            }}
                                        </Badge>
                                        <Link
                                            v-if="can.view_receipts"
                                            :href="
                                                receiptShow(
                                                    collection.receipt.id,
                                                )
                                            "
                                            class="mt-1 block w-fit font-mono text-xs text-primary underline-offset-4 hover:underline"
                                        >
                                            {{
                                                collection.receipt
                                                    .receipt_number
                                            }}
                                        </Link>
                                        <div
                                            v-else
                                            class="mt-1 font-mono text-xs"
                                        >
                                            {{
                                                collection.receipt
                                                    .receipt_number
                                            }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                collection.receipt
                                                    .numbering_authority
                                            }}
                                            ·
                                            {{
                                                collection.receipt.issued_by ??
                                                'System'
                                            }}
                                        </div>
                                    </div>
                                    <Form
                                        v-else-if="
                                            can.issue_receipts &&
                                            collection.status ===
                                                'pending_receipt'
                                        "
                                        v-bind="
                                            receiptStore.form(collection.id)
                                        "
                                        v-slot="{ errors, processing }"
                                        class="grid min-w-72 gap-2"
                                    >
                                        <Label
                                            :for="`receipt_number_${collection.id}`"
                                        >
                                            Manual receipt number
                                        </Label>
                                        <div class="flex gap-2">
                                            <Input
                                                :id="`receipt_number_${collection.id}`"
                                                name="receipt_number"
                                                required
                                            />
                                            <Button
                                                type="submit"
                                                size="sm"
                                                :disabled="processing"
                                            >
                                                <ReceiptText />
                                                {{
                                                    processing
                                                        ? 'Issuing...'
                                                        : 'Issue'
                                                }}
                                            </Button>
                                        </div>
                                        <InputError
                                            :message="errors.receipt_number"
                                        />
                                    </Form>
                                    <span
                                        v-else
                                        class="text-xs text-muted-foreground"
                                    >
                                        Pending receipt
                                    </span>
                                </td>
                                <td
                                    class="px-4 py-3 text-right align-top font-medium"
                                >
                                    {{ money(collection.amount_cents) }}
                                </td>
                            </tr>
                            <tr v-if="paymentSchedule.collections.length === 0">
                                <td
                                    colspan="8"
                                    class="px-4 py-10 text-center text-muted-foreground"
                                >
                                    No collections have been recorded.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
