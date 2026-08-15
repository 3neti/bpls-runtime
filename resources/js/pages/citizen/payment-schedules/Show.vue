<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { ArrowLeft, Banknote, ReceiptText, ShieldAlert } from '@lucide/vue';
import { show as paymentScheduleShow } from '@/actions/App/Http/Controllers/Citizen/PaymentScheduleController';
import { show as permitApplicationShow } from '@/actions/App/Http/Controllers/Citizen/PermitApplicationController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
    blocked_transitions: string[];
    artifact_statement: string;
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

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'My Permit Applications',
        href: permitApplicationShow(props.paymentSchedule.permit_application.id),
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
</script>

<template>
    <div class="contents">
        <Head :title="`Payment Schedule #${paymentSchedule.sequence}`" />

        <main class="flex h-full flex-1 flex-col gap-5 p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <Button as-child variant="ghost" size="sm" class="mb-2 px-0">
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
                    <h1 class="text-xl font-semibold break-words text-foreground">
                        Payment Schedule #{{ paymentSchedule.sequence }}
                    </h1>
                    <p class="text-sm break-words text-muted-foreground">
                        {{ paymentSchedule.permit_application.display_reference }}
                        · {{ paymentSchedule.permit_application.business_name }}
                    </p>
                </div>
                <Badge variant="secondary" class="capitalize">
                    {{ label(paymentSchedule.status) }}
                </Badge>
            </section>

            <section
                data-testid="citizen-payment-detail"
                :data-payment-schedule-id="paymentSchedule.id"
                :data-payment-status="paymentSchedule.status"
                :data-payment-total-cents="paymentSchedule.total_amount_cents"
                :data-payment-paid-cents="paymentSchedule.paid_amount_cents"
                :data-payment-balance-cents="paymentSchedule.balance_amount_cents"
                class="grid gap-4 border-y border-sidebar-border/70 bg-background py-4 dark:border-sidebar-border"
            >
                <div class="flex items-start gap-2">
                    <Banknote class="mt-0.5 size-4 text-muted-foreground" />
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">
                            Authoritative payment evidence
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Assessment #{{ paymentSchedule.assessment.sequence }} ·
                            {{ label(paymentSchedule.payment_mode) }} schedule
                        </p>
                    </div>
                </div>
                <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <dt class="text-xs text-muted-foreground">Total</dt>
                        <dd class="font-medium tabular-nums">
                            {{ money(paymentSchedule.total_amount_cents) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Paid</dt>
                        <dd class="font-medium tabular-nums">
                            {{ money(paymentSchedule.paid_amount_cents) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Balance</dt>
                        <dd class="font-medium tabular-nums">
                            {{ money(paymentSchedule.balance_amount_cents) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Due date</dt>
                        <dd class="font-medium">
                            {{ paymentSchedule.due_on ?? 'Policy not resolved' }}
                        </dd>
                    </div>
                </dl>
                <p
                    class="border-l-4 border-sky-500 bg-sky-50 px-4 py-3 text-sm text-sky-950 dark:bg-sky-950/30 dark:text-sky-100"
                >
                    {{ paymentSchedule.artifact_statement }}
                </p>
            </section>

            <section class="grid gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-foreground">
                        Assessed lines
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        Persisted schedule lines; amounts are not recalculated here.
                    </p>
                </div>
                <div class="overflow-x-auto border-y border-border">
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <thead class="bg-muted/40 text-xs text-muted-foreground">
                            <tr>
                                <th class="px-3 py-2 font-medium">Code</th>
                                <th class="px-3 py-2 font-medium">Assessment line</th>
                                <th class="px-3 py-2 font-medium">Status</th>
                                <th class="px-3 py-2 text-right font-medium">Amount</th>
                                <th class="px-3 py-2 text-right font-medium">Paid</th>
                                <th class="px-3 py-2 text-right font-medium">Balance</th>
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
                                        {{ label(line.category) }}<template v-if="line.line_of_business"> · {{ line.line_of_business }}</template>
                                    </p>
                                </td>
                                <td class="px-3 py-3">
                                    <Badge variant="secondary" class="capitalize">
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
                <div class="flex items-start gap-2">
                    <ReceiptText class="mt-0.5 size-4 text-muted-foreground" />
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">
                            Treasury evidence
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Recorded collections, allocations, and receipt identity.
                        </p>
                    </div>
                </div>
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
                    <div class="flex flex-wrap items-start justify-between gap-3">
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
                    <ul class="divide-y divide-border border-y border-border text-sm">
                        <li
                            v-for="allocation in collection.allocations"
                            :key="allocation.id"
                            data-testid="citizen-payment-allocation"
                            :data-allocation-code="allocation.code"
                            :data-allocation-amount-cents="allocation.amount_cents"
                            class="flex flex-wrap items-center justify-between gap-2 py-2"
                        >
                            <span>
                                <span class="font-mono text-xs">{{ allocation.code }}</span>
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
                            <dt class="text-xs text-muted-foreground">Receipt</dt>
                            <dd class="font-medium break-all">
                                {{ collection.receipt.receipt_number }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Status</dt>
                            <dd class="font-medium capitalize">
                                {{ label(collection.receipt.status) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Amount</dt>
                            <dd class="font-medium tabular-nums">
                                {{ money(collection.receipt.amount_cents) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Issued</dt>
                            <dd class="font-medium">
                                {{ dateTime(collection.receipt.issued_at) }}
                            </dd>
                        </div>
                    </dl>
                </article>
            </section>

            <section
                data-testid="citizen-payment-policy-boundary"
                :data-policy-status="paymentSchedule.payment_policy_boundary.status"
                :data-can-split-installments="
                    paymentSchedule.payment_policy_boundary.can_split_installments
                "
                class="grid gap-3 border-l-4 border-amber-500 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:bg-amber-950/30 dark:text-amber-100"
            >
                <div class="flex items-start gap-2">
                    <ShieldAlert class="mt-0.5 size-4" />
                    <div>
                        <h2 class="font-medium">Payment policy boundary</h2>
                        <p class="mt-1">
                            {{ paymentSchedule.payment_policy_boundary.artifact_statement }}
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Badge
                        v-for="calculation in paymentSchedule.payment_policy_boundary
                            .blocked_calculations"
                        :key="calculation"
                        variant="outline"
                        class="capitalize"
                    >
                        {{ label(calculation) }}
                    </Badge>
                </div>
            </section>

            <section
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
                class="grid gap-3 border-l-4 border-amber-500 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:bg-amber-950/30 dark:text-amber-100"
            >
                <div class="flex items-start gap-2">
                    <ShieldAlert class="mt-0.5 size-4" />
                    <div>
                        <h2 class="font-medium">Online payment boundary</h2>
                        <p class="mt-1">
                            {{ paymentSchedule.online_payment_boundary.artifact_statement }}
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Badge
                        v-for="transition in paymentSchedule.online_payment_boundary
                            .blocked_transitions"
                        :key="transition"
                        variant="outline"
                        class="capitalize"
                    >
                        {{ label(transition) }}
                    </Badge>
                </div>
            </section>
        </main>
    </div>
</template>
