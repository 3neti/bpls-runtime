<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useHttp } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, QrCode, RefreshCw } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import { show as paymentScheduleShow } from '@/actions/App/Http/Controllers/Citizen/PaymentScheduleController';
import { show as permitApplicationShow } from '@/actions/App/Http/Controllers/Citizen/PermitApplicationController';
import {
    initiate as initiateQrPh,
    status as qrPhStatus,
} from '@/actions/App/Http/Controllers/Citizen/QrPhPaymentController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AuthorityBoundaryPanel from '@/components/workflow/AuthorityBoundaryPanel.vue';
import WorkflowSectionHeader from '@/components/workflow/WorkflowSectionHeader.vue';
import WorkflowStageSummary from '@/components/workflow/WorkflowStageSummary.vue';
import type { BreadcrumbItem } from '@/types';

type PaymentScheduleLine = {
    id: number;
    code: string;
    name: string;
    category: string;
    status: string;
    due_on: string | null;
    amount_cents: number;
    paid_amount_cents: number;
    balance_amount_cents: number;
    line_of_business: string | null;
};

type CollectionAllocation = {
    id: number;
    payment_schedule_line_id: number;
    code: string;
    name: string;
    amount_cents: number;
};

type Receipt = {
    id: number;
    status: string;
    numbering_authority: string;
    receipt_number: string;
    amount_cents: number;
    issued_at: string;
};

type TreasuryCollection = {
    id: number;
    status: string;
    channel: string;
    method: string;
    amount_cents: number;
    received_at: string;
    receipt: Receipt | null;
    allocations: CollectionAllocation[];
};

type PaymentPolicyBoundary = {
    status: string;
    can_calculate_surcharge: boolean;
    can_calculate_interest: boolean;
    can_validate_pil: boolean;
    can_calculate_deficiency_tax: boolean;
    can_split_installments: boolean;
    can_assign_statutory_due_dates: boolean;
    supported_payment_modes: string[];
    blocked_calculations: string[];
    artifact_statement: string;
};

type OnlinePaymentBoundary = {
    status: string;
    can_pay_online: boolean;
    can_reconcile_online: boolean;
    payment_status: string | null;
    attempt_status: string | null;
    attempt_expires_at: string | null;
    blocked_transitions: string[];
    artifact_statement: string;
};

type QrPhAttempt = {
    amount_cents: number;
    status: string;
    expires_at: string;
    qr_data_url: string;
};

type QrPhStatus = {
    paid: boolean;
    status: string;
    collection_id: number | null;
    receipt_id: number | null;
};

type PaymentSchedule = {
    id: number;
    sequence: number;
    status: string;
    payment_mode: string;
    due_on: string | null;
    total_amount_cents: number;
    paid_amount_cents: number;
    balance_amount_cents: number;
    created_at: string | null;
    assessment: {
        id: number;
        sequence: number;
        status: string;
        total_amount_cents: number;
    };
    permit_application: {
        id: number;
        display_reference: string;
        application_number: string | null;
        type: string;
        status: string;
        application_year: number;
        business_name: string;
        trade_name: string | null;
    };
    lines: PaymentScheduleLine[];
    collections: TreasuryCollection[];
    payment_policy_boundary: PaymentPolicyBoundary;
    online_payment_boundary: OnlinePaymentBoundary;
    artifact_statement: string;
};

const props = defineProps<{
    paymentSchedule: PaymentSchedule;
}>();

const initiateRequest = useHttp({});
const statusRequest = useHttp({});
const qrAttempt = ref<QrPhAttempt | null>(null);
const paymentMessage = ref<string | null>(null);
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

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'My Permit Applications',
        href: permitApplicationShow(
            props.paymentSchedule.permit_application.id,
        ),
    },
    {
        title: `Payment Schedule #${props.paymentSchedule.sequence}`,
        href: paymentScheduleShow(props.paymentSchedule.id),
    },
];

setLayoutProps({ breadcrumbs });

function money(amountCents: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amountCents / 100);
}

function label(value: string): string {
    return value.replaceAll('_', ' ');
}

function dateTime(value: string | null): string {
    if (value === null) {
        return 'Not recorded';
    }

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function stopPaymentChecks(): void {
    if (countdownTimer !== null) {
        clearInterval(countdownTimer);
        countdownTimer = null;
    }

    if (pollTimer !== null) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

async function checkPayment(): Promise<void> {
    if (statusRequest.processing || qrAttempt.value === null) {
        return;
    }

    try {
        const result = (await statusRequest.submit(
            qrPhStatus(props.paymentSchedule.id),
        )) as QrPhStatus;

        if (result.paid) {
            stopPaymentChecks();
            paymentMessage.value =
                'Payment confirmed. Your municipal collection is now recorded.';
            qrAttempt.value = null;
            router.reload({ only: ['paymentSchedule'] });
        } else if (result.status === 'expired') {
            stopPaymentChecks();
            paymentMessage.value =
                'This QR has expired without payment. Generate a fresh QR to continue.';
        }
    } catch {
        paymentMessage.value =
            'We could not check the payment yet. We will keep trying while this page is open.';
    }
}

function startPaymentChecks(): void {
    stopPaymentChecks();
    currentTime.value = Date.now();
    countdownTimer = setInterval(() => {
        currentTime.value = Date.now();

        if (secondsRemaining.value === 0) {
            stopPaymentChecks();
            paymentMessage.value =
                'This QR has expired without payment. Generate a fresh QR to continue.';
        }
    }, 1000);
    pollTimer = setInterval(() => void checkPayment(), 4000);
}

async function generateQrPh(): Promise<void> {
    paymentMessage.value = null;

    try {
        const result = (await initiateRequest.submit(
            initiateQrPh(props.paymentSchedule.id),
        )) as QrPhAttempt;

        if (
            result.amount_cents !== props.paymentSchedule.balance_amount_cents
        ) {
            paymentMessage.value =
                'The returned payment amount did not match this obligation. Nothing was marked paid.';

            return;
        }

        qrAttempt.value = result;
        startPaymentChecks();
    } catch {
        paymentMessage.value =
            'QR Ph is temporarily unavailable. Your obligation is unchanged; please try again.';
    }
}

onBeforeUnmount(stopPaymentChecks);
</script>

<template>
    <div class="contents">
        <Head :title="`Payment Schedule #${paymentSchedule.sequence}`" />

        <main class="flex h-full flex-1 flex-col gap-5 p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <Button
                        as-child
                        variant="ghost"
                        size="sm"
                        class="mb-2 px-0"
                    >
                        <Link
                            :href="
                                permitApplicationShow(
                                    paymentSchedule.permit_application.id,
                                )
                            "
                        >
                            <ArrowLeft />
                            Back to application
                        </Link>
                    </Button>
                    <h1
                        class="text-xl font-semibold break-words text-foreground"
                    >
                        Payment Schedule #{{ paymentSchedule.sequence }}
                    </h1>
                    <p class="text-sm break-words text-muted-foreground">
                        {{
                            paymentSchedule.permit_application.display_reference
                        }}
                        · {{ paymentSchedule.permit_application.business_name }}
                    </p>
                </div>
                <Badge variant="secondary" class="capitalize">
                    {{ label(paymentSchedule.status) }}
                </Badge>
            </section>

            <WorkflowStageSummary
                data-testid="citizen-payment-detail"
                :data-payment-schedule-id="paymentSchedule.id"
                :data-payment-status="paymentSchedule.status"
                :data-payment-total-cents="paymentSchedule.total_amount_cents"
                :data-payment-paid-cents="paymentSchedule.paid_amount_cents"
                :data-payment-balance-cents="
                    paymentSchedule.balance_amount_cents
                "
                eyebrow="Current payment evidence"
                :title="`Balance ${money(paymentSchedule.balance_amount_cents)}`"
                :description="paymentSchedule.artifact_statement"
                :items="[
                    {
                        label: 'Schedule status',
                        value: label(paymentSchedule.status),
                    },
                    {
                        label: 'Total assessed',
                        value: money(paymentSchedule.total_amount_cents),
                        detail: `Assessment #${paymentSchedule.assessment.sequence}`,
                    },
                    {
                        label: 'Paid',
                        value: money(paymentSchedule.paid_amount_cents),
                        detail: `${label(paymentSchedule.payment_mode)} schedule`,
                    },
                    {
                        label: 'Due date',
                        value: paymentSchedule.due_on ?? 'Policy not resolved',
                    },
                ]"
            />

            <section class="grid gap-3">
                <WorkflowSectionHeader
                    eyebrow="Supporting evidence"
                    title="Assessed lines"
                    description="Persisted schedule lines; amounts are not recalculated here."
                />
                <div class="overflow-x-auto border-y border-border">
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <thead
                            class="bg-muted/40 text-xs text-muted-foreground"
                        >
                            <tr>
                                <th class="px-3 py-2 font-medium">Code</th>
                                <th class="px-3 py-2 font-medium">
                                    Assessment line
                                </th>
                                <th class="px-3 py-2 font-medium">Status</th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Amount
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Paid
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Balance
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="line in paymentSchedule.lines"
                                :key="line.id"
                                data-testid="citizen-payment-line"
                                :data-line-code="line.code"
                                :data-line-status="line.status"
                                :data-line-amount-cents="line.amount_cents"
                                :data-line-paid-cents="line.paid_amount_cents"
                            >
                                <td class="px-3 py-3 font-mono text-xs">
                                    {{ line.code }}
                                </td>
                                <td class="px-3 py-3">
                                    <p class="font-medium">{{ line.name }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ label(line.category)
                                        }}<template
                                            v-if="line.line_of_business"
                                        >
                                            ·
                                            {{
                                                line.line_of_business
                                            }}</template
                                        >
                                    </p>
                                </td>
                                <td class="px-3 py-3">
                                    <Badge
                                        variant="secondary"
                                        class="capitalize"
                                    >
                                        {{ label(line.status) }}
                                    </Badge>
                                </td>
                                <td class="px-3 py-3 text-right tabular-nums">
                                    {{ money(line.amount_cents) }}
                                </td>
                                <td class="px-3 py-3 text-right tabular-nums">
                                    {{ money(line.paid_amount_cents) }}
                                </td>
                                <td class="px-3 py-3 text-right tabular-nums">
                                    {{ money(line.balance_amount_cents) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="grid gap-3">
                <WorkflowSectionHeader
                    eyebrow="Recorded after scheduling"
                    title="Treasury evidence"
                    description="Recorded collections, allocations, and receipt identity remain separate facts."
                />
                <p
                    v-if="paymentSchedule.collections.length === 0"
                    class="border border-dashed border-sidebar-border p-5 text-sm text-muted-foreground"
                >
                    No collection has been recorded for this schedule.
                </p>
                <article
                    v-for="collection in paymentSchedule.collections"
                    v-else
                    :key="collection.id"
                    data-testid="citizen-payment-collection"
                    :data-collection-id="collection.id"
                    :data-collection-status="collection.status"
                    :data-collection-amount-cents="collection.amount_cents"
                    class="grid gap-4 rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <h3 class="text-sm font-semibold text-foreground">
                                Collection #{{ collection.id }}
                            </h3>
                            <p class="text-xs text-muted-foreground capitalize">
                                {{ label(collection.channel) }} ·
                                {{ label(collection.method) }} ·
                                {{ dateTime(collection.received_at) }}
                            </p>
                        </div>
                        <div class="text-right">
                            <Badge variant="secondary" class="capitalize">
                                {{ label(collection.status) }}
                            </Badge>
                            <p class="mt-1 text-sm font-medium tabular-nums">
                                {{ money(collection.amount_cents) }}
                            </p>
                        </div>
                    </div>
                    <ul
                        class="divide-y divide-border border-y border-border text-sm"
                    >
                        <li
                            v-for="allocation in collection.allocations"
                            :key="allocation.id"
                            data-testid="citizen-payment-allocation"
                            :data-allocation-code="allocation.code"
                            :data-allocation-amount-cents="
                                allocation.amount_cents
                            "
                            class="flex flex-wrap items-center justify-between gap-2 py-2"
                        >
                            <span>
                                <span class="font-mono text-xs">{{
                                    allocation.code
                                }}</span>
                                · {{ allocation.name }}
                            </span>
                            <span class="font-medium tabular-nums">
                                {{ money(allocation.amount_cents) }}
                            </span>
                        </li>
                    </ul>
                    <dl
                        v-if="collection.receipt"
                        data-testid="citizen-payment-receipt"
                        :data-receipt-id="collection.receipt.id"
                        :data-receipt-status="collection.receipt.status"
                        :data-receipt-number="collection.receipt.receipt_number"
                        class="grid gap-3 text-sm sm:grid-cols-4"
                    >
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Receipt
                            </dt>
                            <dd class="font-medium break-all">
                                {{ collection.receipt.receipt_number }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Status
                            </dt>
                            <dd class="font-medium capitalize">
                                {{ label(collection.receipt.status) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Amount
                            </dt>
                            <dd class="font-medium tabular-nums">
                                {{ money(collection.receipt.amount_cents) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Issued
                            </dt>
                            <dd class="font-medium">
                                {{ dateTime(collection.receipt.issued_at) }}
                            </dd>
                        </div>
                    </dl>
                </article>
            </section>

            <section
                v-if="
                    paymentSchedule.online_payment_boundary.can_pay_online ||
                    paymentSchedule.online_payment_boundary.status === 'paid'
                "
                data-testid="citizen-qr-ph-payment"
                :data-payment-status="
                    paymentSchedule.online_payment_boundary.status
                "
                class="grid gap-5 rounded-xl border border-primary/25 bg-primary/5 p-4 sm:p-6"
            >
                <div class="flex items-start gap-3">
                    <div class="rounded-full bg-primary/10 p-2 text-primary">
                        <QrCode v-if="paymentSchedule.status !== 'paid'" />
                        <CheckCircle2 v-else />
                    </div>
                    <div class="min-w-0">
                        <p
                            class="text-xs font-medium tracking-wide text-primary uppercase"
                        >
                            Secure municipal payment
                        </p>
                        <h2 class="text-xl font-semibold text-foreground">
                            Pay with QR Ph
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Scan using a participating bank or e-wallet. Payment
                            is recorded only after confirmation.
                        </p>
                    </div>
                </div>

                <div
                    class="grid gap-1 rounded-lg bg-background p-4 text-center"
                >
                    <span class="text-xs text-muted-foreground"
                        >Amount due</span
                    >
                    <strong
                        data-testid="qr-ph-amount"
                        :data-amount-cents="
                            paymentSchedule.balance_amount_cents
                        "
                        class="text-3xl text-foreground tabular-nums"
                    >
                        {{ money(paymentSchedule.balance_amount_cents) }}
                    </strong>
                </div>

                <div v-if="qrAttempt" class="grid justify-items-center gap-3">
                    <img
                        data-testid="qr-ph-image"
                        :src="qrAttempt.qr_data_url"
                        alt="QR Ph payment code"
                        class="aspect-square w-full max-w-80 bg-white object-contain p-3"
                    />
                    <p class="text-sm font-medium text-foreground">
                        QR expires in
                        <span
                            data-testid="qr-ph-countdown"
                            class="tabular-nums"
                            >{{ countdown }}</span
                        >
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Waiting for payment confirmation…
                    </p>
                </div>

                <p
                    v-if="paymentMessage"
                    data-testid="qr-ph-message"
                    class="rounded-md border border-border bg-background p-3 text-sm text-foreground"
                >
                    {{ paymentMessage }}
                </p>

                <Button
                    v-if="
                        paymentSchedule.status !== 'paid' &&
                        (qrAttempt === null || secondsRemaining === 0)
                    "
                    type="button"
                    class="w-full sm:w-auto sm:justify-self-center"
                    :disabled="initiateRequest.processing"
                    data-testid="qr-ph-generate"
                    @click="generateQrPh"
                >
                    <RefreshCw v-if="qrAttempt" />
                    <QrCode v-else />
                    {{
                        initiateRequest.processing
                            ? 'Preparing QR…'
                            : qrAttempt && secondsRemaining === 0
                              ? 'Generate fresh QR'
                              : 'Pay with QR Ph'
                    }}
                </Button>
            </section>

            <AuthorityBoundaryPanel
                v-if="!paymentSchedule.online_payment_boundary.can_pay_online"
                data-testid="citizen-payment-policy-boundary"
                :data-policy-status="
                    paymentSchedule.payment_policy_boundary.status
                "
                :data-can-split-installments="
                    paymentSchedule.payment_policy_boundary
                        .can_split_installments
                "
                title="Other payment arrangements"
                :status="paymentSchedule.payment_policy_boundary.status"
                :statement="'This preview shows the payment arrangement currently recorded. Installment rules and statutory dates are not yet confirmed.'"
                :facts="[
                    {
                        label: 'Installments in this preview',
                        value: paymentSchedule.payment_policy_boundary
                            .can_split_installments
                            ? 'Available'
                            : 'Not available',
                    },
                    {
                        label: 'Statutory dates',
                        value: paymentSchedule.payment_policy_boundary
                            .can_assign_statutory_due_dates
                            ? 'Available'
                            : 'Not yet confirmed',
                    },
                ]"
            >
                <div class="flex flex-wrap gap-2">
                    <Badge
                        v-for="calculation in paymentSchedule
                            .payment_policy_boundary.blocked_calculations"
                        :key="calculation"
                        variant="outline"
                        class="capitalize"
                    >
                        {{ label(calculation) }}
                    </Badge>
                </div>
            </AuthorityBoundaryPanel>

            <AuthorityBoundaryPanel
                data-testid="citizen-online-payment-detail-boundary"
                :data-online-payment-status="
                    paymentSchedule.online_payment_boundary.status
                "
                :data-can-pay-online="
                    paymentSchedule.online_payment_boundary.can_pay_online
                "
                :data-can-reconcile-online="
                    paymentSchedule.online_payment_boundary.can_reconcile_online
                "
                title="Online payment availability"
                :status="paymentSchedule.online_payment_boundary.status"
                :statement="
                    paymentSchedule.online_payment_boundary.artifact_statement
                "
                :facts="[
                    {
                        label: 'Online payment in this preview',
                        value: paymentSchedule.online_payment_boundary
                            .can_pay_online
                            ? 'Available'
                            : 'Not available',
                    },
                    {
                        label: 'Online payment matching',
                        value: paymentSchedule.online_payment_boundary
                            .can_reconcile_online
                            ? 'Available'
                            : 'Not available',
                    },
                ]"
            >
                <div class="flex flex-wrap gap-2">
                    <Badge
                        v-for="transition in paymentSchedule
                            .online_payment_boundary.blocked_transitions"
                        :key="transition"
                        variant="outline"
                        class="capitalize"
                    >
                        {{ label(transition) }}
                    </Badge>
                </div>
            </AuthorityBoundaryPanel>
        </main>
    </div>
</template>
