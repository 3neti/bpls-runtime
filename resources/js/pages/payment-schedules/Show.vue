<script setup lang="ts">
import { Form, Head, Link, router, useHttp } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Banknote,
    CheckCircle2,
    QrCode,
    ReceiptText,
    RefreshCw,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import { show as paymentScheduleShow } from '@/actions/App/Http/Controllers/Staff/AssessmentPaymentScheduleController';
import { store as receiptStore } from '@/actions/App/Http/Controllers/Staff/CollectionReceiptController';
import { store as collectionStore } from '@/actions/App/Http/Controllers/Staff/PaymentScheduleCollectionController';
import { show as assessmentShow } from '@/actions/App/Http/Controllers/Staff/PermitApplicationAssessmentController';
import { show as permitApplicationShow } from '@/actions/App/Http/Controllers/Staff/PermitApplicationController';
import {
    initiate as initiateQrPh,
    status as qrPhStatus,
} from '@/actions/App/Http/Controllers/Staff/QrPhPaymentController';
import { show as receiptShow } from '@/actions/App/Http/Controllers/Staff/ReceiptController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    payment_status: string | null;
    attempt_status: string | null;
    attempt_expires_at: string | null;
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

type QrPhAttempt = {
    amount_cents: number;
    status: string;
    expires_at: string;
    qr_data_url: string | null;
};

type ClassicPaymentHandoff = {
    payment_id: number;
    pay_code: string | null;
    external_reference: string;
    amount_cents: number;
    currency: string;
    status: string;
    is_current: boolean;
    attempt: QrPhAttempt & {
        id: number;
        reference: string | null;
        provider: string | null;
    };
};

type QrPhStatus = {
    paid: boolean;
    status: string;
    collection_id: number | null;
    receipt_id: number | null;
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
    receipts: Receipt[];
    allocations: CollectionAllocation[];
};

type Receipt = {
    id: number;
    status: string;
    numbering_authority: string;
    receipt_group_key: string | null;
    receipt_group_label: string | null;
    receipt_number: string;
    series: string | null;
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
    receipt_group_key: string | null;
    receipt_group_label: string | null;
    receipt_id: number | null;
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
        initiate_qr_ph: boolean;
        simulate_classic_payment: boolean;
    };
    classicPaymentHandoff: ClassicPaymentHandoff | null;
    classicPaymentSimulationUrl: string | null;
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

const pendingReceiptCount = computed(() =>
    props.paymentSchedule.collections.reduce(
        (count, collection) => count + pendingReceiptGroups(collection).length,
        0,
    ),
);

const canGenerateQr = computed(
    () =>
        props.paymentSchedule.online_payment_boundary.can_pay_online &&
        props.can.initiate_qr_ph &&
        props.paymentSchedule.status !== 'paid' &&
        balanceDueCents.value > 0,
);

const workspaceState = computed(() => {
    if (props.paymentSchedule.status === 'paid') {
        return pendingReceiptCount.value > 0
            ? 'Receipts required'
            : 'Payment complete';
    }

    if (qrAttempt.value !== null && secondsRemaining.value > 0) {
        return 'Awaiting QR Ph payment';
    }

    if (canGenerateQr.value) {
        return 'Ready for QR Ph';
    }

    if (props.can.record_collections && balanceDueCents.value > 0) {
        return 'Ready for collection';
    }

    return 'Payment review';
});

const initiateRequest = useHttp({});
const statusRequest = useHttp({});
const qrAttempt = ref<QrPhAttempt | null>(
    props.classicPaymentHandoff?.is_current
        ? props.classicPaymentHandoff.attempt
        : null,
);
const qrMessage = ref<string | null>(null);
const currentTime = ref(Date.now());
let countdownTimer: ReturnType<typeof setInterval> | null = null;
let pollTimer: ReturnType<typeof setInterval> | null = null;

const secondsRemaining = computed(() => {
    if (qrAttempt.value === null) {
        return 0;
    }

    return Math.max(
        0,
        Math.floor(
            (new Date(qrAttempt.value.expires_at).getTime() -
                currentTime.value) /
                1000,
        ),
    );
});

const countdown = computed(() => {
    const minutes = Math.floor(secondsRemaining.value / 60)
        .toString()
        .padStart(2, '0');
    const seconds = (secondsRemaining.value % 60).toString().padStart(2, '0');

    return `${minutes}:${seconds}`;
});

function money(amountCents: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amountCents / 100);
}

function label(value: string): string {
    return value.replaceAll('_', ' ');
}

function pendingReceiptGroups(collection: TreasuryCollection) {
    const groups = new Map<
        string,
        { key: string; label: string; amount_cents: number }
    >();

    for (const allocation of collection.allocations) {
        if (allocation.receipt_id !== null) {
            continue;
        }

        const key = allocation.receipt_group_key ?? 'municipal_consolidated';
        const current = groups.get(key);
        groups.set(key, {
            key,
            label:
                allocation.receipt_group_label ??
                'Municipal Consolidated Collection',
            amount_cents:
                (current?.amount_cents ?? 0) + allocation.amount_cents,
        });
    }

    return [...groups.values()];
}

function stopQrChecks(): void {
    if (countdownTimer !== null) {
        clearInterval(countdownTimer);
        countdownTimer = null;
    }

    if (pollTimer !== null) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

async function checkQrPayment(): Promise<void> {
    if (statusRequest.processing || qrAttempt.value === null) {
        return;
    }

    try {
        const result = (await statusRequest.submit(
            qrPhStatus(props.paymentSchedule.id),
        )) as QrPhStatus;

        if (result.paid) {
            stopQrChecks();
            qrMessage.value =
                'Payment confirmed. The municipal collection is now recorded.';
            qrAttempt.value = null;
            router.reload({ only: ['paymentSchedule'] });
        } else if (result.status === 'expired') {
            stopQrChecks();
            qrMessage.value =
                'This QR expired without payment. Generate a fresh QR to continue.';
        }
    } catch {
        qrMessage.value =
            'Payment confirmation is temporarily unavailable. No collection was recorded.';
    }
}

function startQrChecks(pollForPayment = true): void {
    stopQrChecks();
    currentTime.value = Date.now();
    countdownTimer = setInterval(() => {
        currentTime.value = Date.now();

        if (secondsRemaining.value === 0) {
            stopQrChecks();
            qrMessage.value =
                'This QR expired without payment. Generate a fresh QR to continue.';
        }
    }, 1000);

    if (pollForPayment) {
        pollTimer = setInterval(() => void checkQrPayment(), 4000);
    }
}

async function generateQrPh(): Promise<void> {
    qrMessage.value = null;

    try {
        const result = (await initiateRequest.submit(
            initiateQrPh(props.paymentSchedule.id),
        )) as QrPhAttempt;

        if (result.amount_cents !== balanceDueCents.value) {
            qrMessage.value =
                'The returned amount does not match this Payment Schedule. Nothing was changed.';

            return;
        }

        qrAttempt.value = result;
        startQrChecks();
    } catch {
        qrMessage.value =
            'QR Ph is temporarily unavailable. The Payment Schedule is unchanged.';
    }
}

if (qrAttempt.value !== null) {
    startQrChecks(props.classicPaymentHandoff === null);
}

onBeforeUnmount(stopQrChecks);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Payment Schedule #${paymentSchedule.sequence}`" />

        <main
            class="mx-auto flex h-full w-full max-w-[1500px] flex-1 flex-col gap-4 overflow-x-hidden p-4"
        >
            <header class="flex flex-wrap items-end justify-between gap-3">
                <div class="grid gap-1">
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
                            Back to Assessment
                        </Link>
                    </Button>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-xl font-semibold text-foreground">
                            Payment Schedule #{{ paymentSchedule.sequence }}
                        </h1>
                        <Badge variant="outline">{{ workspaceState }}</Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ paymentSchedule.permit_application.business_name }}
                        · {{ paymentSchedule.permit_application.owner_name }}
                    </p>
                </div>
                <Link
                    :href="
                        permitApplicationShow(
                            paymentSchedule.permit_application.id,
                        )
                    "
                    class="text-sm font-medium text-primary hover:underline"
                >
                    {{
                        paymentSchedule.permit_application.application_number ??
                        `Application #${paymentSchedule.permit_application.id}`
                    }}
                </Link>
            </header>

            <div class="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <div
                    class="order-2 grid min-w-0 content-start gap-4 xl:order-1"
                >
                    <section
                        data-testid="payment-schedule-items"
                        class="overflow-hidden rounded-xl border bg-background"
                    >
                        <div
                            class="flex flex-wrap items-end justify-between gap-4 border-b p-4"
                        >
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Payment items
                                </p>
                                <h2 class="text-base font-semibold">
                                    Approved municipal charges
                                </h2>
                            </div>
                            <dl class="flex gap-5 text-right text-sm">
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Total
                                    </dt>
                                    <dd class="font-semibold tabular-nums">
                                        {{
                                            money(
                                                paymentSchedule.total_amount_cents,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Paid
                                    </dt>
                                    <dd class="font-semibold tabular-nums">
                                        {{
                                            money(
                                                paymentSchedule.paid_amount_cents,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Balance
                                    </dt>
                                    <dd class="font-semibold tabular-nums">
                                        {{ money(balanceDueCents) }}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div class="hidden sm:block">
                            <table class="w-full text-sm">
                                <thead
                                    class="border-b bg-muted/30 text-left text-xs text-muted-foreground"
                                >
                                    <tr>
                                        <th class="px-4 py-3 font-medium">
                                            Payment item
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            Source / Line of Business
                                        </th>
                                        <th
                                            class="px-4 py-3 text-right font-medium"
                                        >
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
                                        <td class="px-4 py-3 font-medium">
                                            {{ line.name }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-muted-foreground"
                                        >
                                            {{
                                                line.line_of_business ??
                                                'Application-wide'
                                            }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right font-medium tabular-nums"
                                        >
                                            {{ money(line.amount_cents) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="divide-y sm:hidden">
                            <div
                                v-for="line in paymentSchedule.lines"
                                :key="line.id"
                                class="grid grid-cols-[minmax(0,1fr)_auto] gap-3 p-4"
                            >
                                <div class="min-w-0">
                                    <p class="font-medium">{{ line.name }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{
                                            line.line_of_business ??
                                            'Application-wide'
                                        }}
                                    </p>
                                </div>
                                <p class="font-medium tabular-nums">
                                    {{ money(line.amount_cents) }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <section
                        v-if="paymentSchedule.collections.length > 0"
                        class="rounded-xl border bg-background p-4"
                    >
                        <div class="mb-4 flex items-center gap-2">
                            <ReceiptText class="size-4 text-muted-foreground" />
                            <h2 class="text-sm font-semibold">
                                Collections and Official Receipts
                            </h2>
                        </div>
                        <div class="grid gap-3">
                            <article
                                v-for="collection in paymentSchedule.collections"
                                :key="collection.id"
                                class="rounded-lg border p-3"
                            >
                                <div
                                    class="flex flex-wrap items-start justify-between gap-3"
                                >
                                    <div>
                                        <p class="font-medium tabular-nums">
                                            {{ money(collection.amount_cents) }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ label(collection.method) }} ·
                                            {{ collection.received_at }}
                                        </p>
                                    </div>
                                    <Badge variant="outline" class="capitalize">
                                        {{ label(collection.status) }}
                                    </Badge>
                                </div>

                                <div
                                    v-if="collection.receipts.length > 0"
                                    class="mt-3 grid gap-2"
                                >
                                    <div
                                        v-for="receipt in collection.receipts"
                                        :key="receipt.id"
                                        class="flex flex-wrap items-center justify-between gap-2 rounded-md bg-muted/30 px-3 py-2 text-sm"
                                    >
                                        <span>
                                            {{ receipt.receipt_group_label }} ·
                                            {{ money(receipt.amount_cents) }}
                                        </span>
                                        <Link
                                            v-if="can.view_receipts"
                                            :href="receiptShow(receipt.id)"
                                            class="font-mono text-xs text-primary hover:underline"
                                        >
                                            OR {{ receipt.receipt_number }}
                                        </Link>
                                        <span v-else class="font-mono text-xs">
                                            OR {{ receipt.receipt_number }}
                                        </span>
                                    </div>
                                </div>

                                <Form
                                    v-for="group in can.issue_receipts
                                        ? pendingReceiptGroups(collection)
                                        : []"
                                    :key="group.key"
                                    v-bind="receiptStore.form(collection.id)"
                                    v-slot="{ errors, processing }"
                                    class="mt-3 grid gap-2 rounded-md border p-3"
                                >
                                    <input
                                        type="hidden"
                                        name="receipt_group_key"
                                        :value="group.key"
                                    />
                                    <Label
                                        :for="`receipt_number_${collection.id}_${group.key}`"
                                    >
                                        {{ group.label }} ·
                                        {{ money(group.amount_cents) }}
                                    </Label>
                                    <div class="flex gap-2">
                                        <Input
                                            :id="`receipt_number_${collection.id}_${group.key}`"
                                            name="receipt_number"
                                            inputmode="numeric"
                                            pattern="[0-9]{7}"
                                            minlength="7"
                                            maxlength="7"
                                            placeholder="0000000"
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
                            </article>
                        </div>
                    </section>

                    <details
                        data-testid="payment-details"
                        class="rounded-xl border bg-background p-4"
                    >
                        <summary class="cursor-pointer text-sm font-medium">
                            Payment details
                        </summary>
                        <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Schedule status
                                </dt>
                                <dd class="capitalize">
                                    {{ label(paymentSchedule.status) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Payment mode
                                </dt>
                                <dd class="capitalize">
                                    {{ label(paymentSchedule.payment_mode) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Due date
                                </dt>
                                <dd>
                                    {{ paymentSchedule.due_on ?? 'Not set' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Prepared by
                                </dt>
                                <dd>
                                    {{
                                        paymentSchedule.prepared_by ?? 'System'
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Assessment
                                </dt>
                                <dd>
                                    <Link
                                        :href="
                                            assessmentShow(
                                                paymentSchedule.assessment.id,
                                            )
                                        "
                                        class="text-primary hover:underline"
                                    >
                                        Assessment #{{
                                            paymentSchedule.assessment.sequence
                                        }}
                                    </Link>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Transaction
                                </dt>
                                <dd class="capitalize">
                                    {{
                                        paymentSchedule.permit_application.type
                                    }}
                                    ·
                                    {{
                                        paymentSchedule.permit_application
                                            .application_year
                                    }}
                                </dd>
                            </div>
                        </dl>
                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full min-w-[560px] text-xs">
                                <thead
                                    class="border-b text-left text-muted-foreground"
                                >
                                    <tr>
                                        <th class="py-2 pr-3 font-medium">
                                            Code
                                        </th>
                                        <th class="py-2 pr-3 font-medium">
                                            Category
                                        </th>
                                        <th class="py-2 pr-3 font-medium">
                                            Status
                                        </th>
                                        <th class="py-2 text-right font-medium">
                                            Paid
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="line in paymentSchedule.lines"
                                        :key="line.id"
                                        class="border-b last:border-0"
                                    >
                                        <td class="py-2 pr-3 font-mono">
                                            {{ line.code }}
                                        </td>
                                        <td class="py-2 pr-3 capitalize">
                                            {{ line.category }}
                                        </td>
                                        <td class="py-2 pr-3 capitalize">
                                            {{ label(line.status) }}
                                        </td>
                                        <td
                                            class="py-2 text-right tabular-nums"
                                        >
                                            {{ money(line.paid_amount_cents) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </details>

                    <details
                        data-testid="payment-policy"
                        class="rounded-xl border bg-background p-4"
                    >
                        <summary class="cursor-pointer text-sm font-medium">
                            Payment policy
                        </summary>
                        <div class="mt-4 grid gap-4 text-sm">
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Supported payment modes
                                </p>
                                <div class="mt-2 flex flex-wrap gap-2">
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
                                </div>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Calculations not active
                                </p>
                                <div class="mt-2 flex flex-wrap gap-2">
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
                                </div>
                            </div>
                            <ul class="grid gap-1 text-muted-foreground">
                                <li
                                    v-for="gap in paymentSchedule
                                        .payment_policy_boundary
                                        .unresolved_policy"
                                    :key="gap"
                                >
                                    {{ gap }}
                                </li>
                            </ul>
                        </div>
                    </details>
                </div>

                <aside
                    data-testid="payment-action-rail"
                    class="order-1 grid content-start gap-4 xl:sticky xl:top-4 xl:order-2"
                >
                    <section
                        data-testid="staff-qr-ph-payment"
                        class="rounded-xl border border-primary/30 bg-background p-4 shadow-sm"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="rounded-full bg-primary/10 p-2 text-primary"
                            >
                                <CheckCircle2
                                    v-if="paymentSchedule.status === 'paid'"
                                    class="size-5"
                                />
                                <QrCode v-else class="size-5" />
                            </div>
                            <div>
                                <p
                                    class="text-xs font-medium text-primary uppercase"
                                >
                                    Current task
                                </p>
                                <h2 class="font-semibold">
                                    {{ workspaceState }}
                                </h2>
                            </div>
                        </div>

                        <div
                            class="mt-5 rounded-lg bg-muted/30 p-4 text-center"
                        >
                            <p class="text-xs text-muted-foreground">Balance</p>
                            <p
                                data-testid="staff-qr-ph-amount"
                                :data-amount-cents="balanceDueCents"
                                class="text-3xl font-semibold tabular-nums"
                            >
                                {{ money(balanceDueCents) }}
                            </p>
                        </div>

                        <p
                            v-if="qrMessage"
                            data-testid="staff-qr-ph-message"
                            class="mt-4 rounded-md border bg-muted/30 p-3 text-sm"
                        >
                            {{ qrMessage }}
                        </p>

                        <Button
                            v-if="
                                canGenerateQr &&
                                (qrAttempt === null || secondsRemaining === 0)
                            "
                            type="button"
                            class="mt-4 w-full"
                            :disabled="initiateRequest.processing"
                            data-testid="staff-qr-ph-generate"
                            @click="generateQrPh"
                        >
                            <RefreshCw
                                v-if="qrAttempt && secondsRemaining === 0"
                            />
                            <QrCode v-else />
                            {{
                                initiateRequest.processing
                                    ? 'Preparing QR…'
                                    : qrAttempt && secondsRemaining === 0
                                      ? 'Generate fresh QR'
                                      : 'Generate QR Ph'
                            }}
                        </Button>

                        <div
                            v-if="qrAttempt"
                            class="mt-4 grid justify-items-center gap-2 rounded-lg border bg-white p-3"
                        >
                            <img
                                v-if="qrAttempt.qr_data_url"
                                data-testid="staff-qr-ph-image"
                                :src="qrAttempt.qr_data_url"
                                alt="QR Ph payment code"
                                class="aspect-square w-full object-contain"
                            />
                            <p class="text-sm font-medium text-slate-900">
                                Expires in
                                <span class="tabular-nums">{{
                                    countdown
                                }}</span>
                            </p>
                            <p class="text-center text-xs text-slate-600">
                                Awaiting payment confirmation
                            </p>
                        </div>

                        <dl
                            v-if="classicPaymentHandoff"
                            data-testid="classic-payment-handoff"
                            class="mt-3 grid gap-2 rounded-lg border bg-muted/20 p-3 text-sm sm:grid-cols-2"
                        >
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Pay Code
                                </dt>
                                <dd class="font-medium">
                                    {{ classicPaymentHandoff.pay_code ?? '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Provider / rail
                                </dt>
                                <dd class="font-medium">
                                    {{
                                        classicPaymentHandoff.attempt
                                            .provider ?? 'QR Ph'
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    External reference
                                </dt>
                                <dd class="font-medium break-all">
                                    {{
                                        classicPaymentHandoff.external_reference
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Attempt reference
                                </dt>
                                <dd class="font-medium break-all">
                                    {{
                                        classicPaymentHandoff.attempt
                                            .reference ?? '—'
                                    }}
                                </dd>
                            </div>
                        </dl>

                        <Form
                            v-if="
                                can.simulate_classic_payment &&
                                classicPaymentSimulationUrl &&
                                classicPaymentHandoff?.is_current
                            "
                            :action="classicPaymentSimulationUrl"
                            method="post"
                            v-slot="{ processing }"
                            class="mt-3"
                        >
                            <Button
                                type="submit"
                                variant="secondary"
                                class="w-full"
                                :disabled="processing"
                                data-testid="classic-payment-simulate"
                            >
                                <Banknote />
                                {{
                                    processing
                                        ? 'Recording payment…'
                                        : 'Simulate QR Ph payment'
                                }}
                            </Button>
                        </Form>

                        <p
                            v-else-if="
                                !canGenerateQr &&
                                paymentSchedule.status !== 'paid'
                            "
                            class="mt-4 text-sm text-muted-foreground"
                        >
                            {{
                                paymentSchedule.online_payment_boundary
                                    .artifact_statement
                            }}
                        </p>

                        <p
                            v-if="canGenerateQr && qrAttempt === null"
                            class="mt-3 text-xs text-muted-foreground"
                        >
                            Generates a request only. Collection and Official
                            Receipts remain separate records.
                        </p>
                    </section>

                    <details
                        v-if="can.record_collections && balanceDueCents > 0"
                        class="rounded-xl border bg-background p-4"
                    >
                        <summary class="cursor-pointer text-sm font-medium">
                            Record over-the-counter payment
                        </summary>
                        <Form
                            v-bind="collectionStore.form(paymentSchedule.id)"
                            v-slot="{ errors, processing }"
                            class="mt-4 grid gap-3"
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
                                    class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
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
                                <Input
                                    id="reference_number"
                                    name="reference_number"
                                />
                                <InputError
                                    :message="errors.reference_number"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="remarks">Remarks</Label>
                                <Input id="remarks" name="remarks" />
                                <InputError :message="errors.remarks" />
                            </div>
                            <Button type="submit" :disabled="processing">
                                <Banknote />
                                {{
                                    processing
                                        ? 'Recording...'
                                        : 'Record Collection'
                                }}
                            </Button>
                        </Form>
                    </details>
                </aside>
            </div>
        </main>
    </AppLayout>
</template>
