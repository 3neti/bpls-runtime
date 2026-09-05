<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        receipt: any;
        fallbackProfile?: any;
        viewUrl?: string | null;
    }>(),
    { fallbackProfile: () => ({}), viewUrl: null },
);
const profile = computed(() => {
    const frozen =
        props.receipt.presentation_profile ??
        props.receipt.source_snapshot?.official_receipt_profile;

    return frozen?.profile_key ? frozen : props.fallbackProfile;
});
const af51 = computed(() => {
    const frozen = props.receipt.source_snapshot?.af51;

    return frozen?.agency
        ? frozen
        : {
              agency: props.receipt.agency ?? profile.value.defaults?.agency,
              fund: props.receipt.fund ?? profile.value.defaults?.fund,
              amount_in_words: props.receipt.amount_in_words,
          };
});
const issuer = computed(() => {
    const frozen = props.receipt.source_snapshot?.issuer;

    return frozen?.printed_name
        ? frozen
        : {
              ...profile.value.collecting_officer,
              printed_name:
                  props.receipt.collecting_officer ??
                  profile.value.collecting_officer?.name,
          };
});
const receiptNumber = computed(() => props.receipt.receipt_number ?? '');
const issuedOn = computed(
    () => props.receipt.issued_on ?? props.receipt.issued_at,
);
const payor = computed(
    () =>
        props.receipt.payor ??
        props.receipt.collection?.payer_name ??
        props.receipt.business?.owner?.name ??
        '',
);
const rows = computed(() =>
    Array.isArray(props.receipt.collection_rows)
        ? props.receipt.collection_rows.map((row: any, index: number) => ({
              id: `${row.nature_of_collection}-${row.account_code}-${index}`,
              name: row.nature_of_collection,
              code: row.account_code,
              amount_cents: row.amount_minor,
          }))
        : (props.receipt.allocations ?? []),
);
const totalAmount = computed(
    () => props.receipt.total_amount_minor ?? props.receipt.amount_cents ?? 0,
);
const paymentMethod = computed(
    () =>
        props.receipt.payment_instrument?.type ??
        props.receipt.collection?.method ??
        '',
);
const paymentReference = computed(
    () =>
        props.receipt.payment_instrument?.number ??
        props.receipt.collection?.reference_number ??
        '',
);
const collectedOn = computed(
    () =>
        props.receipt.payment_instrument?.date ??
        props.receipt.collection?.received_at ??
        issuedOn.value,
);

function amount(cents: number): string {
    return new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2 }).format(
        cents / 100,
    );
}

function date(value: string | null | undefined, long = false): string {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleDateString(
        'en-PH',
        long ? { dateStyle: 'long' } : undefined,
    );
}
</script>

<template>
    <article
        class="relative mx-auto w-full max-w-[34rem] overflow-hidden border-2 border-sky-900 bg-[#fffef8] text-[11px] leading-tight text-zinc-950 shadow-xl print:max-w-none print:shadow-none"
        data-testid="af51-official-receipt"
    >
        <div
            v-if="profile.collecting_officer?.authority_status !== 'verified'"
            class="absolute inset-x-0 top-1/2 z-10 -rotate-12 text-center text-lg font-black tracking-widest text-rose-700/15"
        >
            {{ profile.laboratory_watermark }}
        </div>
        <header class="border-b-2 border-sky-900 px-4 py-4 text-center">
            <h2 class="text-2xl font-black tracking-tight">OFFICIAL RECEIPT</h2>
            <p>{{ profile.header?.republic }}</p>
            <p class="text-base font-black uppercase">
                {{ profile.header?.province }}
            </p>
            <p>{{ profile.header?.office }}</p>
            <p class="mt-3 border-t border-zinc-400 pt-2 text-sm font-bold">
                {{ profile.header?.municipality }}
            </p>
        </header>
        <div class="grid grid-cols-2 border-b-2 border-sky-900">
            <div class="border-r-2 border-sky-900 p-3 font-bold">
                Accountable Form No. {{ profile.form?.accountable_form_number
                }}<br /><span class="font-normal"
                    >({{ profile.form?.revision }})</span
                >
            </div>
            <div class="min-w-0 p-3 text-center">
                <strong class="text-base tracking-widest">{{
                    profile.form?.copy_designation
                }}</strong>
                <Link
                    v-if="viewUrl"
                    :href="viewUrl"
                    aria-label="Open issued Official Receipt"
                    class="mt-1 font-mono text-lg font-bold break-all text-rose-600 sm:text-2xl"
                >
                    {{ receiptNumber }}
                </Link>
                <div
                    v-else
                    class="mt-1 font-mono text-lg font-bold break-all text-rose-600 sm:text-2xl"
                >
                    {{ receiptNumber }}
                </div>
            </div>
        </div>
        <dl class="grid grid-cols-[5rem_1fr] border-b border-sky-900">
            <dt class="p-2 font-bold">DATE</dt>
            <dd class="p-2">{{ date(issuedOn, true) }}</dd>
        </dl>
        <div class="grid grid-cols-2 border-b border-sky-900">
            <p class="p-2"><strong>AGENCY</strong><br />{{ af51.agency }}</p>
            <p class="border-l border-sky-900 p-2">
                <strong>FUND</strong><br />{{ af51.fund }}
            </p>
        </div>
        <p class="border-b border-sky-900 p-2">
            <strong>PAYOR</strong><br />{{ payor }}
        </p>
        <table class="w-full table-fixed border-collapse">
            <thead>
                <tr class="border-b border-sky-900 bg-zinc-100">
                    <th class="w-[56%] p-2 text-center">
                        NATURE OF COLLECTION
                    </th>
                    <th class="w-[20%] border-l border-sky-900 p-2">
                        ACCOUNT CODE
                    </th>
                    <th class="border-l border-sky-900 p-2">AMOUNT</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="row in rows"
                    :key="row.id"
                    class="border-b border-sky-900"
                >
                    <td class="p-2 break-words">{{ row.name }}</td>
                    <td
                        class="border-l border-sky-900 p-2 font-mono text-[9px] break-all"
                    >
                        {{ row.code }}
                    </td>
                    <td class="border-l border-sky-900 p-2 text-right">
                        ₱ {{ amount(row.amount_cents) }}
                    </td>
                </tr>
            </tbody>
            <tfoot>
                <tr class="border-b-2 border-sky-900">
                    <th
                        colspan="2"
                        class="p-3 text-right text-base tracking-widest"
                    >
                        TOTAL
                    </th>
                    <th
                        class="border-l border-sky-900 p-3 text-right text-base"
                    >
                        ₱ {{ amount(totalAmount) }}
                    </th>
                </tr>
            </tfoot>
        </table>
        <div class="min-h-16 border-b-2 border-sky-900 p-2">
            <strong>AMOUNT IN WORDS</strong>
            <p class="mt-2 font-bold uppercase">{{ af51.amount_in_words }}</p>
        </div>
        <div class="grid grid-cols-[8rem_1fr] border-b border-sky-900">
            <div class="space-y-1 border-r border-sky-900 p-3">
                <p>{{ paymentMethod === 'cash' ? '☒' : '☐' }} Cash</p>
                <p>{{ paymentMethod === 'check' ? '☒' : '☐' }} Check</p>
                <p>
                    {{ paymentMethod === 'money_order' ? '☒' : '☐' }} Money
                    Order
                </p>
                <p>{{ paymentMethod === 'qr_ph' ? '☒' : '☐' }} QR Ph</p>
            </div>
            <div class="grid min-w-0 grid-cols-2">
                <p class="min-w-0 border-r border-sky-900 p-3">
                    <strong>NUMBER / REFERENCE</strong><br /><span
                        class="font-mono break-all"
                        >{{ paymentReference || 'Not recorded' }}</span
                    >
                </p>
                <p class="p-3">
                    <strong>DATE</strong><br />{{ date(collectedOn) }}
                </p>
            </div>
        </div>
        <footer class="p-4 text-right">
            <div
                class="mt-5 border-b border-zinc-600 pb-1 font-black uppercase"
            >
                {{ issuer.printed_name ?? issuer.name }}
            </div>
            <p>{{ issuer.printed_title ?? issuer.title }}</p>
            <p>{{ issuer.printed_designation ?? issuer.designation }}</p>
            <p class="mt-3 text-left text-[9px]">
                <strong>NOTE:</strong> {{ profile.footer_note }}
            </p>
        </footer>
    </article>
</template>
