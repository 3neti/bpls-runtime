<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

type PaymentRequest = {
    state: string;
    pay_code: string | null;
    external_reference: string;
    currency: string;
    target_amount_cents: number;
    collected_total_cents: number;
    consumer_status: string | null;
    provider_status: string | null;
    confirmed_at: string | null;
    collection_id: number | null;
    collection_reference: string | null;
    active_attempt: {
        reference: string | null;
        status: string;
        provider: string | null;
        amount_cents: number;
        expires_at: string | null;
        qr_data_url: string | null;
    } | null;
};

type ReceiptGroup = {
    key: string;
    label: string;
    allocated_amount_cents: number;
    receipt_issued: boolean;
    receipt_id: number | null;
};

type PaymentReconciliation = {
    integration: string | null;
    provider: string | null;
    payment_rail: string | null;
    channel: string | null;
    approved_amount_cents: number;
    paid_amount_cents: number;
    collected_amount_cents: number;
    remaining_balance_cents: number;
    confirmed_at: string | null;
    collection_reference: string | null;
    required_receipt_group_count: number;
    issued_receipt_group_count: number;
    receipt_groups: ReceiptGroup[];
    receipt_count: number;
    total_receipted_cents: number;
    unreceipted_amount_cents: number;
    receipt_coverage_complete: boolean;
    totals_reconciled: boolean;
    status: string;
    synthetic: boolean;
};

type OfficialReceipt = {
    receipt_group_key: string;
    receipt_group_label: string;
    receipt_number: string;
    series: string | null;
    issued_on: string;
    total_amount_minor: number;
    links: {
        view: string | null;
        pdf: string | null;
    };
    source: {
        receipt_id: number;
        treasury_collection_id: number;
    };
};

type ReceiptRow = {
    key: string;
    label: string;
    amountCents: number;
    receipt: OfficialReceipt | null;
};

const props = defineProps<{
    application: Record<string, any>;
    checking?: boolean;
    checkMessage?: string | null;
    statusUrl?: string | null;
}>();

defineEmits<{ check: [] }>();

const paymentRequest = computed<PaymentRequest | null>(
    () => props.application.payment.payment_request ?? null,
);
const payable = computed(() => props.application.payment.payable ?? null);
const reconciliation = computed<PaymentReconciliation | null>(
    () => props.application.payment.reconciliation ?? null,
);
const isCollected = computed(
    () =>
        paymentRequest.value?.state === 'collected' &&
        paymentRequest.value.collection_id !== null,
);
const receipts = computed<OfficialReceipt[]>(() => {
    const collectionId = paymentRequest.value?.collection_id;
    const allReceipts = (props.application.official_receipts ??
        []) as OfficialReceipt[];

    if (collectionId === null || collectionId === undefined) {
        return allReceipts;
    }

    return allReceipts.filter(
        (receipt) => receipt.source.treasury_collection_id === collectionId,
    );
});
const receiptRows = computed<ReceiptRow[]>(() => {
    const groups = reconciliation.value?.receipt_groups ?? [];

    if (groups.length === 0) {
        return receipts.value.map((receipt) => ({
            key: receipt.receipt_group_key,
            label: receipt.receipt_group_label,
            amountCents: receipt.total_amount_minor,
            receipt,
        }));
    }

    return groups.map((group) => ({
        key: group.key,
        label: group.label,
        amountCents: group.allocated_amount_cents,
        receipt:
            receipts.value.find(
                (receipt) => receipt.receipt_group_key === group.key,
            ) ?? null,
    }));
});

function money(amountCents: number | null | undefined): string {
    if (amountCents === null || amountCents === undefined) {
        return '';
    }

    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amountCents / 100);
}

function dateTime(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function label(value: string | null | undefined): string {
    return value ? value.replaceAll('_', ' ') : '';
}

function paymentSource(): string {
    const payment = reconciliation.value;

    if (!payment) {
        return '';
    }

    const integration = payment.integration === 'x_change' ? 'x-change' : null;
    const rail = label(payment.payment_rail);

    return (
        [integration, rail].filter(Boolean).join(' / ') ||
        label(payment.channel) ||
        'recorded collection'
    );
}
</script>

<template>
    <div
        data-testid="payment-continuation-set"
        class="bg-stone-100 p-2 text-stone-950 sm:p-4 dark:bg-stone-950 dark:text-stone-100 print:bg-white print:p-0 print:text-black"
    >
        <article
            data-testid="application-page-3"
            :data-payment-collected="isCollected"
            class="relative mx-auto min-h-[44rem] max-w-[52rem] overflow-hidden border-2 border-stone-900 bg-white shadow-sm dark:border-stone-400 dark:bg-stone-900 print:min-h-0 print:max-w-none print:shadow-none"
        >
            <header
                class="grid gap-2 border-b-2 border-stone-900 p-4 text-center sm:p-5 dark:border-stone-400"
            >
                <p class="text-[10px] font-bold tracking-[0.12em] uppercase">
                    Republic of the Philippines · Municipality of Ipil
                </p>
                <h2 class="text-lg font-black uppercase sm:text-xl">
                    Application Form for Business Permit
                </h2>
                <div
                    class="grid border-y-2 border-stone-900 py-2 sm:grid-cols-[1fr_auto_1fr] sm:items-center dark:border-stone-400"
                >
                    <span class="hidden sm:block"></span>
                    <strong class="uppercase"
                        >Payment Continuation Sheet</strong
                    >
                    <strong class="text-xs uppercase sm:text-right"
                        >Page 3</strong
                    >
                </div>
            </header>

            <dl
                class="grid grid-cols-2 border-b-2 border-stone-900 text-xs sm:grid-cols-4 dark:border-stone-400"
            >
                <div
                    class="border-r border-b border-stone-400 p-2 sm:border-b-0"
                >
                    <dt class="text-[9px] font-black uppercase">
                        Official application no.
                    </dt>
                    <dd class="min-h-5 font-bold">
                        {{ application.identity.application_number ?? '' }}
                    </dd>
                </div>
                <div
                    class="border-b border-stone-400 p-2 sm:border-r sm:border-b-0"
                >
                    <dt class="text-[9px] font-black uppercase">
                        Tracking reference
                    </dt>
                    <dd
                        class="min-h-5 font-mono text-[10px] font-bold break-all"
                    >
                        {{ application.identity.tracking_reference ?? '' }}
                    </dd>
                </div>
                <div class="border-r border-stone-400 p-2">
                    <dt class="text-[9px] font-black uppercase">Assessment</dt>
                    <dd class="min-h-5 font-bold">
                        <template v-if="application.financial.assessment"
                            >No.
                            {{
                                application.financial.assessment.sequence
                            }}</template
                        >
                    </dd>
                </div>
                <div class="p-2">
                    <dt class="text-[9px] font-black uppercase">
                        Approved amount
                    </dt>
                    <dd class="min-h-5 font-bold">
                        {{ money(reconciliation?.approved_amount_cents) }}
                    </dd>
                </div>
            </dl>

            <section
                class="grid border-b-2 border-stone-900 sm:grid-cols-[minmax(0,1fr)_14rem] dark:border-stone-400"
            >
                <div class="grid gap-4 p-4 sm:p-5">
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <h3 class="text-xs font-black uppercase">
                            A. x-change payment confirmation
                        </h3>
                        <span
                            v-if="reconciliation?.synthetic"
                            class="border border-amber-500 px-2 py-1 text-[9px] font-black text-amber-800 uppercase dark:text-amber-300"
                        >
                            Laboratory simulation · no funds moved
                        </span>
                    </div>

                    <dl class="grid gap-3 text-xs sm:grid-cols-2">
                        <div>
                            <dt class="text-[9px] font-black uppercase">
                                Pay Code
                            </dt>
                            <dd
                                data-testid="application-payment-pay-code"
                                class="font-mono text-2xl font-black tracking-[0.16em] break-all"
                            >
                                {{ paymentRequest?.pay_code ?? '' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[9px] font-black uppercase">
                                BPLS state
                            </dt>
                            <dd class="font-black uppercase">
                                {{ label(paymentRequest?.state) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[9px] font-black uppercase">
                                Provider / rail
                            </dt>
                            <dd class="font-bold">
                                {{ label(reconciliation?.provider) || '—' }} ·
                                {{ paymentSource() || '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[9px] font-black uppercase">
                                Confirmed
                            </dt>
                            <dd class="font-bold">
                                {{
                                    dateTime(reconciliation?.confirmed_at) ||
                                    '—'
                                }}
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-[9px] font-black uppercase">
                                External reference
                            </dt>
                            <dd class="font-mono text-[10px] break-all">
                                {{ paymentRequest?.external_reference ?? '' }}
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-[9px] font-black uppercase">
                                Collection reference
                            </dt>
                            <dd class="font-mono text-[10px] break-all">
                                {{ reconciliation?.collection_reference ?? '' }}
                            </dd>
                        </div>
                    </dl>

                    <button
                        v-if="statusUrl && !isCollected"
                        type="button"
                        data-testid="application-check-payment"
                        class="w-full border-2 border-stone-900 bg-amber-300 px-4 py-2 text-xs font-black uppercase hover:bg-amber-200 disabled:cursor-wait disabled:opacity-60 dark:border-stone-300 dark:text-stone-950 print:hidden"
                        :disabled="checking"
                        @click="$emit('check')"
                    >
                        {{
                            checking
                                ? 'Checking payment…'
                                : 'Check payment status'
                        }}
                    </button>
                    <p
                        v-if="checkMessage"
                        class="text-xs font-bold"
                        aria-live="polite"
                    >
                        {{ checkMessage }}
                    </p>
                </div>

                <div
                    class="relative grid min-h-56 place-items-center border-t-2 border-stone-900 p-4 sm:border-t-0 sm:border-l-2 dark:border-stone-400"
                >
                    <img
                        v-if="paymentRequest?.active_attempt?.qr_data_url"
                        :src="paymentRequest.active_attempt.qr_data_url"
                        alt="QR Ph payment code"
                        class="aspect-square w-full max-w-48 object-contain"
                        data-testid="application-payment-qr"
                    />
                    <div
                        v-else
                        class="grid aspect-square w-full max-w-48 place-items-center border-2 border-dashed border-stone-400 p-4 text-center text-xs font-bold uppercase"
                    >
                        {{
                            payable
                                ? 'QR Ph not generated or expired'
                                : 'Payment schedule pending'
                        }}
                    </div>
                    <div
                        v-if="isCollected"
                        data-testid="application-payment-collected-stamp"
                        class="pointer-events-none absolute inset-x-2 top-1/2 -rotate-12 border-4 border-emerald-700 bg-white/90 px-2 py-2 text-center text-2xl font-black tracking-[0.12em] text-emerald-800 uppercase"
                    >
                        Collected
                    </div>
                </div>
            </section>

            <dl
                class="grid grid-cols-2 border-b-2 border-stone-900 text-xs sm:grid-cols-4 dark:border-stone-400"
            >
                <div
                    class="border-r border-b border-stone-400 p-3 sm:border-b-0"
                >
                    <dt class="text-[9px] font-black uppercase">Approved</dt>
                    <dd class="font-black">
                        {{ money(reconciliation?.approved_amount_cents) }}
                    </dd>
                </div>
                <div
                    class="border-b border-stone-400 p-3 sm:border-r sm:border-b-0"
                >
                    <dt class="text-[9px] font-black uppercase">Collected</dt>
                    <dd class="font-black">
                        {{ money(reconciliation?.collected_amount_cents) }}
                    </dd>
                </div>
                <div class="border-r border-stone-400 p-3">
                    <dt class="text-[9px] font-black uppercase">
                        Remaining balance
                    </dt>
                    <dd class="font-black">
                        {{ money(reconciliation?.remaining_balance_cents) }}
                    </dd>
                </div>
                <div class="p-3">
                    <dt class="text-[9px] font-black uppercase">
                        Collection ID
                    </dt>
                    <dd class="font-black">
                        {{ paymentRequest?.collection_id ?? '' }}
                    </dd>
                </div>
            </dl>

            <section class="grid gap-3 p-4 text-xs sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="font-black uppercase">
                        B. Official Receipt packet · AF No. 51
                    </h3>
                    <strong
                        data-testid="receipt-reconciliation-status"
                        :class="
                            reconciliation?.totals_reconciled
                                ? 'text-emerald-800 dark:text-emerald-300'
                                : 'text-amber-800 dark:text-amber-300'
                        "
                        class="uppercase"
                    >
                        {{
                            reconciliation?.totals_reconciled
                                ? 'Fully reconciled'
                                : 'Pending receipt coverage'
                        }}
                    </strong>
                </div>

                <div
                    data-testid="official-receipt-packet"
                    class="border border-stone-500"
                >
                    <div
                        class="hidden grid-cols-[minmax(0,1.6fr)_minmax(5rem,0.8fr)_minmax(4rem,0.6fr)_minmax(6rem,0.8fr)_4rem] bg-stone-900 px-3 py-2 text-[9px] font-black text-white uppercase sm:grid dark:bg-stone-200 dark:text-stone-950"
                    >
                        <span>Receipt group</span>
                        <span>OR number</span>
                        <span>Series</span>
                        <span class="text-right">Amount</span>
                        <span class="text-right print:hidden">Action</span>
                    </div>
                    <div
                        v-for="row in receiptRows"
                        :key="row.key"
                        data-testid="official-receipt-group-row"
                        class="grid gap-2 border-t border-stone-300 p-3 first:border-t-0 sm:grid-cols-[minmax(0,1.6fr)_minmax(5rem,0.8fr)_minmax(4rem,0.6fr)_minmax(6rem,0.8fr)_4rem] sm:items-center dark:border-stone-700"
                    >
                        <div>
                            <span
                                class="block text-[9px] font-black uppercase sm:hidden"
                                >Receipt group</span
                            >
                            <strong>{{ row.label }}</strong>
                        </div>
                        <div>
                            <span
                                class="block text-[9px] font-black uppercase sm:hidden"
                                >OR number</span
                            >
                            <strong
                                class="font-mono text-red-700 dark:text-red-300"
                                >{{
                                    row.receipt?.receipt_number ?? 'Pending'
                                }}</strong
                            >
                        </div>
                        <div>
                            <span
                                class="block text-[9px] font-black uppercase sm:hidden"
                                >Series</span
                            >
                            <span>{{ row.receipt?.series ?? '—' }}</span>
                        </div>
                        <div class="sm:text-right">
                            <span
                                class="block text-[9px] font-black uppercase sm:hidden"
                                >Amount</span
                            >
                            <strong>{{ money(row.amountCents) }}</strong>
                        </div>
                        <div class="sm:text-right print:hidden">
                            <Link
                                v-if="row.receipt?.links.view"
                                :href="row.receipt.links.view"
                                class="font-black text-sky-800 underline underline-offset-2 dark:text-sky-300"
                            >
                                View
                            </Link>
                            <span v-else class="text-stone-400">—</span>
                        </div>
                    </div>
                    <p
                        v-if="receiptRows.length === 0"
                        class="p-4 font-bold text-stone-500"
                    >
                        Receipt groups will appear after the Collection is
                        allocated.
                    </p>
                </div>

                <dl
                    class="grid grid-cols-2 border border-stone-500 sm:grid-cols-4"
                >
                    <div
                        class="border-r border-b border-stone-300 p-3 sm:border-b-0 dark:border-stone-700"
                    >
                        <dt class="text-[9px] font-black uppercase">
                            Receipt groups
                        </dt>
                        <dd class="font-black">
                            {{
                                reconciliation?.issued_receipt_group_count ?? 0
                            }}
                            of
                            {{
                                reconciliation?.required_receipt_group_count ??
                                0
                            }}
                            issued
                        </dd>
                    </div>
                    <div
                        class="border-b border-stone-300 p-3 sm:border-r sm:border-b-0 dark:border-stone-700"
                    >
                        <dt class="text-[9px] font-black uppercase">
                            Collected total
                        </dt>
                        <dd class="font-black">
                            {{ money(reconciliation?.collected_amount_cents) }}
                        </dd>
                    </div>
                    <div
                        class="border-r border-stone-300 p-3 dark:border-stone-700"
                    >
                        <dt class="text-[9px] font-black uppercase">
                            Total receipted
                        </dt>
                        <dd class="font-black">
                            {{ money(reconciliation?.total_receipted_cents) }}
                        </dd>
                    </div>
                    <div class="p-3">
                        <dt class="text-[9px] font-black uppercase">
                            Unreceipted
                        </dt>
                        <dd class="font-black">
                            {{
                                money(reconciliation?.unreceipted_amount_cents)
                            }}
                        </dd>
                    </div>
                </dl>

                <p
                    v-if="isCollected"
                    class="border-t border-stone-400 pt-3 font-black uppercase"
                >
                    Payment recorded. Do not scan this QR again.
                </p>
            </section>
        </article>
    </div>
</template>

<style scoped>
@media (max-width: 389px) {
    [data-testid='application-payment-pay-code'] {
        font-size: 1.125rem;
        letter-spacing: 0.08em;
    }
}
</style>
