<script setup lang="ts">
import { computed } from 'vue';

type PaymentRequest = {
    state: string;
    pay_code: string | null;
    external_reference: string;
    target_amount_cents: number;
    collected_total_cents: number;
    confirmed_at: string | null;
    collection_id: number | null;
    collection_reference: string | null;
    official_receipt_id: number | null;
    active_attempt: {
        reference: string | null;
        status: string;
        provider: string | null;
        amount_cents: number;
        expires_at: string | null;
        qr_data_url: string | null;
    } | null;
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
const isCollected = computed(
    () =>
        paymentRequest.value?.state === 'collected' &&
        paymentRequest.value.collection_id !== null,
);
const receipt = computed(
    () => props.application.official_receipts?.[0] ?? null,
);

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
                    <strong class="mt-1 text-xs uppercase sm:mt-0 sm:text-right"
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
                        <template v-if="application.financial.assessment">
                            No. {{ application.financial.assessment.sequence }}
                        </template>
                    </dd>
                </div>
                <div class="p-2">
                    <dt class="text-[9px] font-black uppercase">
                        Approved amount
                    </dt>
                    <dd class="min-h-5 font-bold">
                        {{ money(payable?.total_amount_cents) }}
                    </dd>
                </div>
            </dl>

            <section
                class="grid gap-0 border-b-2 border-stone-900 sm:grid-cols-[minmax(0,1fr)_18rem] dark:border-stone-400"
            >
                <div
                    class="border-b-2 border-stone-900 p-4 sm:border-r-2 sm:border-b-0 sm:p-5 dark:border-stone-400"
                >
                    <h3 class="text-xs font-black uppercase">
                        A. Payment request
                    </h3>
                    <dl class="mt-4 grid gap-4 text-xs sm:grid-cols-2">
                        <div>
                            <dt class="text-[9px] font-black uppercase">
                                Pay Code
                            </dt>
                            <dd
                                data-testid="application-payment-pay-code"
                                class="mt-1 font-mono text-2xl font-black tracking-[0.16em] break-all"
                            >
                                {{ paymentRequest?.pay_code ?? '' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[9px] font-black uppercase">
                                Payment state
                            </dt>
                            <dd class="mt-1 font-black uppercase">
                                {{ label(paymentRequest?.state) }}
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-[9px] font-black uppercase">
                                External reference
                            </dt>
                            <dd class="mt-1 font-mono text-[10px] break-all">
                                {{ paymentRequest?.external_reference ?? '' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[9px] font-black uppercase">
                                Amount due
                            </dt>
                            <dd class="mt-1 text-lg font-black">
                                {{ money(payable?.balance_amount_cents) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[9px] font-black uppercase">
                                QR valid until
                            </dt>
                            <dd class="mt-1 font-bold">
                                {{
                                    dateTime(
                                        paymentRequest?.active_attempt
                                            ?.expires_at,
                                    )
                                }}
                            </dd>
                        </div>
                    </dl>

                    <button
                        v-if="statusUrl && !isCollected"
                        type="button"
                        data-testid="application-check-payment"
                        class="mt-5 w-full border-2 border-stone-900 bg-amber-300 px-4 py-2 text-xs font-black uppercase hover:bg-amber-200 disabled:cursor-wait disabled:opacity-60 dark:border-stone-300 dark:text-stone-950 print:hidden"
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
                        class="mt-2 text-xs font-bold"
                        aria-live="polite"
                    >
                        {{ checkMessage }}
                    </p>
                </div>

                <div
                    class="relative grid min-h-72 place-items-center p-4 sm:p-5"
                >
                    <img
                        v-if="paymentRequest?.active_attempt?.qr_data_url"
                        :src="paymentRequest.active_attempt.qr_data_url"
                        alt="QR Ph payment code"
                        class="aspect-square w-full max-w-60 object-contain"
                        data-testid="application-payment-qr"
                    />
                    <div
                        v-else
                        class="grid aspect-square w-full max-w-60 place-items-center border-2 border-dashed border-stone-400 p-4 text-center text-xs font-bold uppercase"
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
                        class="pointer-events-none absolute inset-x-2 top-1/2 -rotate-12 border-4 border-emerald-700 bg-white/90 px-2 py-2 text-center text-3xl font-black tracking-[0.12em] text-emerald-800 uppercase"
                    >
                        Collected
                    </div>
                </div>
            </section>

            <section
                class="grid border-b-2 border-stone-900 text-xs sm:grid-cols-4 dark:border-stone-400"
            >
                <div
                    class="border-b border-stone-400 p-3 sm:border-r sm:border-b-0"
                >
                    <p class="text-[9px] font-black uppercase">
                        Collected amount
                    </p>
                    <strong>{{
                        money(paymentRequest?.collected_total_cents)
                    }}</strong>
                </div>
                <div
                    class="border-b border-stone-400 p-3 sm:border-r sm:border-b-0"
                >
                    <p class="text-[9px] font-black uppercase">Confirmed</p>
                    <strong>{{
                        dateTime(paymentRequest?.confirmed_at)
                    }}</strong>
                </div>
                <div
                    class="border-b border-stone-400 p-3 sm:border-r sm:border-b-0"
                >
                    <p class="text-[9px] font-black uppercase">
                        Collection reference
                    </p>
                    <strong class="break-all">{{
                        paymentRequest?.collection_reference ?? ''
                    }}</strong>
                </div>
                <div class="p-3">
                    <p class="text-[9px] font-black uppercase">
                        Official Receipt no.
                    </p>
                    <strong class="font-mono text-red-700">{{
                        receipt?.receipt_number ?? ''
                    }}</strong>
                </div>
            </section>

            <section class="p-4 text-xs sm:p-5">
                <h3 class="font-black uppercase">B. Treasury record</h3>
                <div
                    class="mt-3 grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-4"
                >
                    <div>
                        <span class="block text-[9px] uppercase"
                            >Collection ID</span
                        ><strong>{{
                            paymentRequest?.collection_id ?? ''
                        }}</strong>
                    </div>
                    <div>
                        <span class="block text-[9px] uppercase">Method</span
                        ><strong>{{
                            label(application.payment.collections?.[0]?.method)
                        }}</strong>
                    </div>
                    <div>
                        <span class="block text-[9px] uppercase">Channel</span
                        ><strong>{{
                            label(application.payment.collections?.[0]?.channel)
                        }}</strong>
                    </div>
                    <div>
                        <span class="block text-[9px] uppercase"
                            >Receipt status</span
                        ><strong>{{ receipt ? 'Issued' : 'Pending' }}</strong>
                    </div>
                </div>
                <p
                    v-if="isCollected"
                    class="mt-5 border-t border-stone-400 pt-3 font-black uppercase"
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
