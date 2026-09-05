<script setup lang="ts">
const props = defineProps<{ receipt: any; fallbackProfile: any }>();
const frozenProfile = props.receipt.source_snapshot?.official_receipt_profile;
const profile = (
    frozenProfile?.profile_key ? frozenProfile : props.fallbackProfile
) as any;
const frozenAf51 = props.receipt.source_snapshot?.af51;
const af51 = (
    frozenAf51?.agency ? frozenAf51 : (profile.defaults ?? {})
) as any;
const frozenIssuer = props.receipt.source_snapshot?.issuer;
const issuer = (
    frozenIssuer?.printed_name
        ? frozenIssuer
        : (profile.collecting_officer ?? {})
) as any;

function amount(cents: number): string {
    return new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2 }).format(
        cents / 100,
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
            <div class="p-3 text-center">
                <strong class="text-base tracking-widest">{{
                    profile.form?.copy_designation
                }}</strong>
                <div class="mt-1 font-mono text-2xl font-bold text-rose-600">
                    {{ receipt.receipt_number }}
                </div>
            </div>
        </div>
        <dl class="grid grid-cols-[5rem_1fr] border-b border-sky-900">
            <dt class="p-2 font-bold">DATE</dt>
            <dd class="p-2">
                {{
                    new Date(receipt.issued_at).toLocaleDateString('en-PH', {
                        dateStyle: 'long',
                    })
                }}
            </dd>
        </dl>
        <div class="grid grid-cols-2 border-b border-sky-900">
            <p class="p-2"><strong>AGENCY</strong><br />{{ af51.agency }}</p>
            <p class="border-l border-sky-900 p-2">
                <strong>FUND</strong><br />{{ af51.fund }}
            </p>
        </div>
        <p class="border-b border-sky-900 p-2">
            <strong>PAYOR</strong><br />{{
                receipt.collection.payer_name ?? receipt.business.owner.name
            }}
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
                    v-for="row in receipt.allocations"
                    :key="row.id"
                    class="border-b border-sky-900"
                >
                    <td class="p-2">{{ row.name }}</td>
                    <td class="border-l border-sky-900 p-2 font-mono">
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
                        ₱ {{ amount(receipt.amount_cents) }}
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
                <p>
                    {{ receipt.collection.method === 'cash' ? '☒' : '☐' }} Cash
                </p>
                <p>
                    {{ receipt.collection.method === 'check' ? '☒' : '☐' }}
                    Check
                </p>
                <p>
                    {{
                        receipt.collection.method === 'money_order' ? '☒' : '☐'
                    }}
                    Money Order
                </p>
                <p>
                    {{ receipt.collection.method === 'qr_ph' ? '☒' : '☐' }} QR
                    Ph
                </p>
            </div>
            <div class="grid grid-cols-2">
                <p class="border-r border-sky-900 p-3">
                    <strong>NUMBER / REFERENCE</strong><br /><span
                        class="font-mono break-all"
                        >{{
                            receipt.collection.reference_number ??
                            'Not recorded'
                        }}</span
                    >
                </p>
                <p class="p-3">
                    <strong>DATE</strong><br />{{
                        new Date(
                            receipt.collection.received_at,
                        ).toLocaleDateString('en-PH')
                    }}
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
