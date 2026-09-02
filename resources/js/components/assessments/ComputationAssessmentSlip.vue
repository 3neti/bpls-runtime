<script setup lang="ts">
type Charge = {
    assessment_line_id: number;
    code: string;
    name: string;
    amount_cents: number;
    source_type: 'governed_canonical_pricing' | 'paperless_payment_order';
    paperless_payment_order: {
        id: number | null;
        sequence: number | null;
        office_code: string | null;
        office_label: string | null;
        issued_at: string | null;
    } | null;
};

defineProps<{
    slip: {
        institution: {
            country: string;
            province: string;
            municipality: string;
            title: string;
        };
        reference: {
            assessment_sequence: number;
            official_number: null;
            official_number_status: string;
            snapshot_hash: string;
        };
        transaction_type: string;
        owner_proprietor: string;
        business_name: string;
        business_address: string | null;
        payment_mode: string;
        line_of_businesses: {
            id: number;
            code: string | null;
            name: string | null;
        }[];
        line_sections: {
            line_of_business_id: number;
            line_of_business_name: string | null;
            charges: Charge[];
            subtotal_amount_cents: number;
        }[];
        application_charges: Charge[];
        application_subtotal_amount_cents: number;
        grand_total_amount_cents: number;
        grouped_total_amount_cents: number;
        reconciles: boolean;
        in_words: string | null;
        schedule_of_payments: {
            payment_mode: string;
            allocation_status: string;
            allocation_note: string;
            quarters: {
                section: string;
                due_date: string | null;
                amount_cents: number | null;
                balance_cents: number | null;
            }[];
        };
        prepared_by: {
            name: string | null;
            prepared_at: string | null;
            role: string;
        };
        approved_by: {
            name: string | null;
            approved_at: string;
            role: string;
            snapshot_hash: string;
        } | null;
        acknowledged_by: null;
        acknowledgement_note: string;
    };
}>();

function money(cents: number | null): string {
    return cents === null
        ? '—'
        : new Intl.NumberFormat('en-PH', {
              style: 'currency',
              currency: 'PHP',
          }).format(cents / 100);
}

function dateTime(value: string | null): string {
    return value
        ? new Intl.DateTimeFormat('en-PH', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : '—';
}
</script>

<template>
    <article
        class="mx-auto w-full max-w-[920px] overflow-hidden border-2 border-stone-900 bg-white text-stone-950 shadow-sm dark:border-stone-400 dark:bg-stone-950 dark:text-stone-100"
        data-testid="computation-assessment-slip"
    >
        <header
            class="relative grid gap-2 border-b-2 border-stone-900 px-4 py-6 text-center sm:px-10 dark:border-stone-400"
        >
            <p class="text-sm font-black sm:text-lg">
                {{ slip.institution.country }}
            </p>
            <p class="text-xs sm:text-sm">{{ slip.institution.province }}</p>
            <p class="text-sm font-black uppercase sm:text-lg">
                {{ slip.institution.municipality }}
            </p>
            <p
                class="mt-4 text-xl font-black underline decoration-2 underline-offset-4 sm:text-2xl"
            >
                {{ slip.institution.title }}
            </p>
            <div
                class="mt-2 text-xs sm:absolute sm:top-6 sm:right-8 sm:text-right"
            >
                <p class="font-bold">Reference: not officially assigned</p>
                <p>
                    Internal Assessment sequence
                    {{ slip.reference.assessment_sequence }}
                </p>
            </div>
        </header>

        <div class="grid min-w-0 grid-cols-[minmax(0,1fr)] gap-6 p-4 sm:p-8">
            <dl
                class="grid grid-cols-[minmax(105px,auto)_minmax(0,1fr)] gap-x-3 gap-y-1 text-sm [&>dd]:min-w-0 [&>dd]:break-words"
            >
                <dt>Transaction Type:</dt>
                <dd class="font-bold uppercase">{{ slip.transaction_type }}</dd>
                <dt>Owner/Proprietor:</dt>
                <dd class="font-bold">{{ slip.owner_proprietor }}</dd>
                <dt>Name of Business:</dt>
                <dd class="font-bold">{{ slip.business_name }}</dd>
                <dt>Address of Business:</dt>
                <dd class="font-bold">{{ slip.business_address ?? '—' }}</dd>
                <dt>Payment Mode:</dt>
                <dd class="font-bold uppercase">{{ slip.payment_mode }}</dd>
            </dl>

            <section>
                <h2 class="text-sm font-bold">Line of Business</h2>
                <div
                    class="mt-2 grid gap-x-6 gap-y-2 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <p
                        v-for="line in slip.line_of_businesses"
                        :key="line.id"
                        class="min-w-0 text-sm font-black [overflow-wrap:anywhere] uppercase"
                    >
                        <span v-if="line.code">{{ line.code }} — </span
                        >{{ line.name }}
                    </p>
                </div>
            </section>

            <section class="grid gap-5" aria-labelledby="computations-title">
                <h2
                    id="computations-title"
                    class="text-base font-black uppercase"
                >
                    Computations:
                </h2>
                <div
                    v-for="section in slip.line_sections"
                    :key="section.line_of_business_id"
                    class="break-inside-avoid"
                >
                    <h3 class="font-black uppercase">
                        {{ section.line_of_business_name }}
                    </h3>
                    <div class="mt-2 grid gap-2">
                        <div
                            v-for="charge in section.charges"
                            :key="charge.assessment_line_id"
                            class="grid grid-cols-[1fr_auto] gap-3 text-sm"
                        >
                            <div class="min-w-0 pl-3">
                                <p>{{ charge.name }}</p>
                                <p class="text-[10px] text-stone-500">
                                    {{
                                        charge.source_type ===
                                        'paperless_payment_order'
                                            ? `${charge.paperless_payment_order?.office_label} Paperless Payment Order`
                                            : 'Governed canonical pricing'
                                    }}
                                </p>
                            </div>
                            <p class="font-medium tabular-nums">
                                {{ money(charge.amount_cents) }}
                            </p>
                        </div>
                    </div>
                    <div
                        class="mt-2 grid grid-cols-[1fr_auto] border-t border-stone-900 pt-1 text-sm font-black italic dark:border-stone-400"
                    >
                        <span>SUBTOTAL</span
                        ><span class="tabular-nums">{{
                            money(section.subtotal_amount_cents)
                        }}</span>
                    </div>
                </div>

                <div
                    v-if="slip.application_charges.length"
                    class="break-inside-avoid"
                >
                    <h3 class="font-black uppercase">
                        Application-wide charges
                    </h3>
                    <div
                        v-for="charge in slip.application_charges"
                        :key="charge.assessment_line_id"
                        class="mt-2 grid grid-cols-[1fr_auto] gap-3 pl-3 text-sm"
                    >
                        <span>{{ charge.name }}</span
                        ><span class="tabular-nums">{{
                            money(charge.amount_cents)
                        }}</span>
                    </div>
                    <div
                        class="mt-2 grid grid-cols-[1fr_auto] border-t border-stone-900 pt-1 text-sm font-black italic dark:border-stone-400"
                    >
                        <span>SUBTOTAL</span
                        ><span class="tabular-nums">{{
                            money(slip.application_subtotal_amount_cents)
                        }}</span>
                    </div>
                </div>
            </section>

            <section
                class="border-y-2 border-stone-900 py-3 dark:border-stone-400"
            >
                <div class="grid grid-cols-[1fr_auto] gap-3 text-xl font-black">
                    <span>GRAND TOTAL</span
                    ><span class="tabular-nums">{{
                        money(slip.grand_total_amount_cents)
                    }}</span>
                </div>
                <p class="mt-1 text-sm">
                    <strong>IN WORDS:</strong>
                    <em>{{
                        slip.in_words ??
                        'Not safely derivable for fractional peso total'
                    }}</em>
                </p>
                <p
                    class="mt-1 text-[10px]"
                    :class="
                        slip.reconciles ? 'text-emerald-700' : 'text-red-700'
                    "
                >
                    {{
                        slip.reconciles
                            ? 'Reconciled: LOB subtotals + application-wide subtotal = Grand Total.'
                            : 'RECONCILIATION FAILURE'
                    }}
                </p>
            </section>

            <section
                class="break-inside-avoid"
                aria-labelledby="schedule-title"
            >
                <h2
                    id="schedule-title"
                    class="text-lg font-black underline underline-offset-4"
                >
                    SCHEDULE OF PAYMENTS
                </h2>
                <p class="mt-2 text-sm">
                    <strong>Mode of Payment:</strong>
                    {{ slip.schedule_of_payments.payment_mode.toUpperCase() }}
                </p>
                <div class="mt-3 overflow-x-auto">
                    <table
                        class="w-full min-w-[520px] border-collapse text-left text-sm"
                    >
                        <thead>
                            <tr
                                class="border-b border-stone-900 dark:border-stone-400"
                            >
                                <th class="p-2">Section</th>
                                <th class="p-2">Due Date</th>
                                <th class="p-2 text-right">Amount</th>
                                <th class="p-2 text-right">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="quarter in slip.schedule_of_payments
                                    .quarters"
                                :key="quarter.section"
                                class="border-b border-stone-200"
                            >
                                <td class="p-2 font-bold">
                                    {{ quarter.section }}
                                </td>
                                <td class="p-2">
                                    {{ quarter.due_date ?? '—' }}
                                </td>
                                <td class="p-2 text-right">
                                    {{ money(quarter.amount_cents) }}
                                </td>
                                <td class="p-2 text-right">
                                    {{ money(quarter.balance_cents) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p
                    class="mt-3 border border-amber-400 bg-amber-50 p-3 text-xs text-amber-950 dark:bg-amber-950/30 dark:text-amber-100"
                >
                    <strong>BLOCKED — MUNICIPAL FISCAL DECISION.</strong>
                    {{ slip.schedule_of_payments.allocation_note }} No Grand
                    Total ÷ 4 assumption is used.
                </p>
            </section>

            <footer class="grid gap-8 pt-4 text-sm sm:grid-cols-2">
                <div>
                    <p>Prepared By:</p>
                    <p
                        class="mt-5 border-b border-stone-900 pb-1 font-black dark:border-stone-400"
                    >
                        {{ slip.prepared_by.name ?? '—' }}
                    </p>
                    <p class="text-xs">
                        {{ slip.prepared_by.role }} ·
                        {{ dateTime(slip.prepared_by.prepared_at) }}
                    </p>
                </div>
                <div>
                    <p>Approved By:</p>
                    <p
                        class="mt-5 border-b border-stone-900 pb-1 font-black dark:border-stone-400"
                    >
                        {{
                            slip.approved_by?.name ??
                            'Pending Municipal Treasurer decision'
                        }}
                    </p>
                    <p class="text-xs">
                        Municipal Treasurer ·
                        {{ dateTime(slip.approved_by?.approved_at ?? null) }}
                    </p>
                </div>
                <div class="sm:col-start-2">
                    <p>Acknowledged By:</p>
                    <p class="mt-5 border-b border-stone-900 pb-1">
                        Not yet available
                    </p>
                    <p class="text-xs">{{ slip.acknowledgement_note }}</p>
                    <p class="mt-3">Date: —</p>
                </div>
            </footer>
        </div>
    </article>
</template>
