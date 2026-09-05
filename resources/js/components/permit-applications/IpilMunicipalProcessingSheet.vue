<script setup lang="ts">
import { computed } from 'vue';

type OfficeLine = {
    evaluation_item_id: number;
    name: string;
    status: string;
    display_amount_cents: number | null;
    paperless_payment_order: { id: number } | null;
};

type Office = {
    code: string;
    label: string;
    status: string;
    required_determination_count: number;
    resolved_determination_count: number;
    payment_order_count: number;
    total_amount_cents: number;
    certification: {
        officer_name: string | null;
        certified_at: string | null;
    } | null;
    lines: OfficeLine[];
};

type DocumentProjection = {
    identity: {
        application_id: number;
        application_number: string | null;
        tracking_reference: string | null;
        tax_year: number;
        type: string;
    };
    declaration: {
        snapshot_hash: string | null;
    };
    routing: {
        status: string;
        determined_at: string | null;
        determined_by: string | null;
        works: {
            id: number;
            office_label: string;
            line_of_business_name: string | null;
            required_work: string;
        }[];
    };
    page_2_assessment: {
        emerging_total_amount_cents: number | null;
        required_unresolved_charge_count: number;
        offices: Office[];
    };
    computation_assessment_slip: {
        assessment_id: number;
        sequence: number;
        status: string;
        total_amount_cents: number;
    } | null;
    treasury_counter_check: {
        result: string | null;
        checked_at: string | null;
    } | null;
    municipal_treasurer: {
        action: string;
        decided_at: string | null;
    } | null;
    payment_reference?: {
        state: string | null;
        payable: {
            status: string;
            total_amount_cents: number;
            paid_amount_cents: number;
            balance_amount_cents: number;
            due_on: string | null;
        } | null;
        collection_count: number;
        latest_collection: {
            amount_cents: number;
            received_at: string;
        } | null;
    };
    official_receipt_reference?: {
        receipt_number: string;
        series: string | null;
        issued_on: string;
    } | null;
    verification: {
        description: string;
        issuing_office: string;
        status: string;
        date_issued: string | null;
    }[];
    permit_reference?: {
        state: string | null;
        permit_number: string | null;
        issued_on: string | null;
        valid_until: string | null;
        official_receipt_number: string | null;
        verification_reference: string | null;
    };
};

type DetailRow = {
    key: string;
    office: string;
    responsibility: string;
    determination: string;
    paymentOrder: string;
    amountCents: number | null;
    certification: string;
};

const props = defineProps<{ document: DocumentProjection }>();

const detailRows = computed<DetailRow[]>(() =>
    props.document.page_2_assessment.offices.flatMap((office) =>
        office.lines.map((line, index) => ({
            key: `${office.code}-${line.evaluation_item_id}`,
            office: office.label,
            responsibility: line.name,
            determination: label(line.status),
            paymentOrder: line.paperless_payment_order
                ? `#${line.paperless_payment_order.id}`
                : index === 0 && office.payment_order_count > 0
                  ? `${office.payment_order_count} issued`
                  : '',
            amountCents: line.display_amount_cents,
            certification: office.certification
                ? `${office.certification.officer_name ?? ''}${office.certification.certified_at ? ` / ${date(office.certification.certified_at)}` : ''}`
                : '',
        })),
    ),
);

const continuationSheets = computed(() => {
    const rows = detailRows.value;
    const sheets: DetailRow[][] = [];

    for (let index = 0; index < rows.length; index += 8) {
        sheets.push(rows.slice(index, index + 8));
    }

    return sheets;
});

function label(value: string | null | undefined): string {
    return value ? value.replaceAll('_', ' ') : '';
}

function money(value: number | null | undefined): string {
    if (value === null || value === undefined) {
        return '';
    }

    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(value / 100);
}

function date(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    return new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium' }).format(
        new Date(value),
    );
}

function continuationLabel(index: number): string {
    return `Page 2-${String.fromCharCode(65 + index)}`;
}
</script>

<template>
    <div
        data-testid="municipal-processing-continuation-set"
        class="grid gap-5 bg-stone-100 p-2 text-stone-950 sm:p-4 dark:bg-stone-950 dark:text-stone-100 print:block print:bg-white print:p-0 print:text-black"
    >
        <article
            data-testid="ipil-executable-document-page-2"
            class="processing-paper overflow-hidden border-2 border-stone-900 bg-white shadow-sm dark:border-stone-400 dark:bg-stone-900 print:break-after-page print:shadow-none"
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
                        >Municipal Processing Continuation Sheet</strong
                    >
                    <strong class="mt-1 text-xs uppercase sm:mt-0 sm:text-right"
                        >Page 2</strong
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
                        {{ document.identity.application_number ?? '' }}
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
                        {{ document.identity.tracking_reference ?? '' }}
                    </dd>
                </div>
                <div class="border-r border-stone-400 p-2">
                    <dt class="text-[9px] font-black uppercase">Tax year</dt>
                    <dd class="min-h-5 font-bold">
                        {{ document.identity.tax_year }}
                    </dd>
                </div>
                <div class="p-2">
                    <dt class="text-[9px] font-black uppercase">Transaction</dt>
                    <dd class="min-h-5 font-bold uppercase">
                        {{ label(document.identity.type) }}
                    </dd>
                </div>
            </dl>

            <div class="grid gap-4 p-3 sm:p-5">
                <section data-testid="page-2-bplo-routing-recorded">
                    <h3 class="paper-section-title">A. BPLO Routing</h3>
                    <div class="paper-table">
                        <div
                            class="paper-row paper-table-head grid-cols-[1fr_1.1fr_1.8fr]"
                        >
                            <span>Concerned office</span
                            ><span>Business context</span
                            ><span>Required review</span>
                        </div>
                        <div
                            v-for="work in document.routing.works"
                            :key="work.id"
                            class="paper-row grid-cols-1 sm:grid-cols-[1fr_1.1fr_1.8fr]"
                        >
                            <strong>{{ work.office_label }}</strong>
                            <span>{{ work.line_of_business_name ?? '' }}</span>
                            <span>{{ work.required_work }}</span>
                        </div>
                        <div
                            v-for="blankRow in Math.max(
                                0,
                                4 - document.routing.works.length,
                            )"
                            :key="`routing-blank-${blankRow}`"
                            class="paper-row h-9 grid-cols-[1fr_1.1fr_1.8fr]"
                            aria-label="Blank routing row"
                        >
                            <span></span><span></span><span></span>
                        </div>
                    </div>
                    <dl
                        class="mt-1 grid grid-cols-2 border border-stone-400 text-[10px] sm:grid-cols-4"
                    >
                        <div class="border-r border-stone-400 p-1.5">
                            <dt class="font-black uppercase">Status</dt>
                            <dd class="min-h-4 uppercase">
                                {{ label(document.routing.status) }}
                            </dd>
                        </div>
                        <div class="border-r border-stone-400 p-1.5">
                            <dt class="font-black uppercase">Recorded by</dt>
                            <dd class="min-h-4">
                                {{ document.routing.determined_by ?? '' }}
                            </dd>
                        </div>
                        <div class="border-r border-stone-400 p-1.5">
                            <dt class="font-black uppercase">Date</dt>
                            <dd class="min-h-4">
                                {{ date(document.routing.determined_at) }}
                            </dd>
                        </div>
                        <div class="p-1.5">
                            <dt class="font-black uppercase">
                                Page 1 declaration
                            </dt>
                            <dd class="min-h-4">
                                {{
                                    document.declaration.snapshot_hash
                                        ? 'Frozen'
                                        : ''
                                }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section>
                    <h3 class="paper-section-title">
                        B. Office Determinations and Payment Orders
                    </h3>
                    <div class="paper-table">
                        <div
                            class="paper-row paper-table-head grid-cols-[1.2fr_0.7fr_0.55fr_0.9fr]"
                        >
                            <span>Office</span><span>Determinations</span
                            ><span>PPOs</span><span>Working subtotal</span>
                        </div>
                        <div
                            v-for="office in document.page_2_assessment.offices"
                            :key="office.code"
                            class="paper-row grid-cols-[1.2fr_0.7fr_0.55fr_0.9fr]"
                        >
                            <span
                                ><strong>{{ office.label }}</strong
                                ><small class="block uppercase">{{
                                    label(office.status)
                                }}</small></span
                            >
                            <span
                                >{{ office.resolved_determination_count }}/{{
                                    office.required_determination_count
                                }}</span
                            >
                            <span>{{ office.payment_order_count }}</span>
                            <strong class="text-right tabular-nums">{{
                                money(office.total_amount_cents)
                            }}</strong>
                        </div>
                        <div
                            v-for="blankRow in Math.max(
                                0,
                                4 - document.page_2_assessment.offices.length,
                            )"
                            :key="`office-blank-${blankRow}`"
                            class="paper-row h-9 grid-cols-[1.2fr_0.7fr_0.55fr_0.9fr]"
                            aria-label="Blank office-determination row"
                        >
                            <span></span><span></span><span></span><span></span>
                        </div>
                        <div
                            class="grid grid-cols-[1fr_auto] border-t-2 border-stone-900 p-2 text-xs"
                        >
                            <span class="font-black uppercase"
                                >Processing working total</span
                            >
                            <strong class="tabular-nums">{{
                                money(
                                    document.page_2_assessment
                                        .emerging_total_amount_cents,
                                )
                            }}</strong>
                        </div>
                    </div>
                </section>

                <section data-testid="page-2-assessment-reference">
                    <h3 class="paper-section-title">C. Assessment Reference</h3>
                    <dl class="paper-field-grid sm:grid-cols-4">
                        <div>
                            <dt>Assessment no.</dt>
                            <dd>
                                {{
                                    document.computation_assessment_slip
                                        ?.sequence ?? ''
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt>Status</dt>
                            <dd>
                                {{
                                    label(
                                        document.computation_assessment_slip
                                            ?.status,
                                    )
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt>Assessed amount</dt>
                            <dd>
                                {{
                                    money(
                                        document.computation_assessment_slip
                                            ?.total_amount_cents,
                                    )
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt>Unresolved charges</dt>
                            <dd>
                                {{
                                    document.page_2_assessment
                                        .required_unresolved_charge_count || ''
                                }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section>
                    <h3 class="paper-section-title">
                        D. Treasury Verification
                    </h3>
                    <dl class="paper-field-grid sm:grid-cols-4">
                        <div>
                            <dt>Counter-check</dt>
                            <dd>
                                {{
                                    label(
                                        document.treasury_counter_check?.result,
                                    )
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt>Date checked</dt>
                            <dd>
                                {{
                                    date(
                                        document.treasury_counter_check
                                            ?.checked_at,
                                    )
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt>Treasurer action</dt>
                            <dd>
                                {{
                                    label(document.municipal_treasurer?.action)
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt>Date acted</dt>
                            <dd>
                                {{
                                    date(
                                        document.municipal_treasurer
                                            ?.decided_at,
                                    )
                                }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section>
                    <h3 class="paper-section-title">
                        E. Payment and Official Receipt Reference
                    </h3>
                    <dl class="paper-field-grid sm:grid-cols-4">
                        <div>
                            <dt>Payable status</dt>
                            <dd>
                                {{
                                    label(
                                        document.payment_reference?.payable
                                            ?.status,
                                    )
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt>Balance</dt>
                            <dd>
                                {{
                                    money(
                                        document.payment_reference?.payable
                                            ?.balance_amount_cents,
                                    )
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt>Collections</dt>
                            <dd>
                                {{
                                    document.payment_reference
                                        ?.collection_count || ''
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt>Latest collection</dt>
                            <dd>
                                {{
                                    money(
                                        document.payment_reference
                                            ?.latest_collection?.amount_cents,
                                    )
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt>Official Receipt no.</dt>
                            <dd>
                                {{
                                    document.official_receipt_reference
                                        ?.receipt_number ?? ''
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt>Series</dt>
                            <dd>
                                {{
                                    document.official_receipt_reference
                                        ?.series ?? ''
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt>Date issued</dt>
                            <dd>
                                {{
                                    date(
                                        document.official_receipt_reference
                                            ?.issued_on,
                                    )
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt>Due date</dt>
                            <dd>
                                {{
                                    date(
                                        document.payment_reference?.payable
                                            ?.due_on,
                                    )
                                }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section>
                    <h3 class="paper-section-title">
                        F. Post-payment Certifications
                    </h3>
                    <div class="paper-table">
                        <div
                            class="paper-row paper-table-head grid-cols-[1.5fr_1fr_0.7fr_0.8fr]"
                        >
                            <span>Certification</span><span>Office</span
                            ><span>Status</span><span>Date</span>
                        </div>
                        <div
                            v-for="item in document.verification"
                            :key="item.description"
                            class="paper-row grid-cols-[1.5fr_1fr_0.7fr_0.8fr]"
                        >
                            <span>{{ item.description }}</span
                            ><span>{{ item.issuing_office }}</span
                            ><span class="uppercase">{{
                                label(item.status)
                            }}</span
                            ><span>{{ date(item.date_issued) }}</span>
                        </div>
                        <div
                            v-if="document.verification.length === 0"
                            class="paper-row h-9 grid-cols-[1.5fr_1fr_0.7fr_0.8fr]"
                            aria-label="Blank certification row"
                        >
                            <span></span><span></span><span></span><span></span>
                        </div>
                    </div>
                </section>

                <section>
                    <h3 class="paper-section-title">
                        G. Permit Processing Reference
                    </h3>
                    <dl class="paper-field-grid sm:grid-cols-3">
                        <div>
                            <dt>Permit no.</dt>
                            <dd>
                                {{
                                    document.permit_reference?.permit_number ??
                                    ''
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt>Date issued</dt>
                            <dd>
                                {{ date(document.permit_reference?.issued_on) }}
                            </dd>
                        </div>
                        <div>
                            <dt>Valid until</dt>
                            <dd>
                                {{
                                    date(document.permit_reference?.valid_until)
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt>Official Receipt no.</dt>
                            <dd>
                                {{
                                    document.permit_reference
                                        ?.official_receipt_number ?? ''
                                }}
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt>Public verification reference</dt>
                            <dd class="break-all">
                                {{
                                    document.permit_reference
                                        ?.verification_reference ?? ''
                                }}
                            </dd>
                        </div>
                    </dl>
                </section>
            </div>

            <footer
                class="border-t border-stone-900 px-4 py-2 text-[9px] font-bold tracking-wide uppercase"
            >
                BPLS system-generated municipal processing record
            </footer>
        </article>

        <article
            v-for="(rows, sheetIndex) in continuationSheets"
            :key="sheetIndex"
            :data-testid="`municipal-processing-${continuationLabel(sheetIndex).toLowerCase().replace(' ', '-')}`"
            class="processing-paper overflow-hidden border-2 border-stone-900 bg-white shadow-sm dark:border-stone-400 dark:bg-stone-900 print:break-after-page print:shadow-none"
        >
            <header
                class="border-b-2 border-stone-900 p-4 sm:p-5 dark:border-stone-400"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-bold uppercase">
                            Municipality of Ipil
                        </p>
                        <h2 class="font-black uppercase">
                            Office Determination and PPO Register
                        </h2>
                    </div>
                    <strong class="text-xs uppercase">{{
                        continuationLabel(sheetIndex)
                    }}</strong>
                </div>
                <p class="mt-2 font-mono text-[10px] break-all">
                    {{ document.identity.tracking_reference ?? '' }}
                </p>
            </header>
            <div class="p-3 sm:p-5">
                <div class="paper-table">
                    <div
                        class="paper-row paper-table-head grid-cols-[0.9fr_1.8fr_0.8fr_0.65fr_0.8fr]"
                    >
                        <span>Office</span><span>Responsibility</span
                        ><span>Determination</span><span>PPO</span
                        ><span>Amount</span>
                    </div>
                    <div
                        v-for="row in rows"
                        :key="row.key"
                        class="paper-row min-h-14 grid-cols-[0.9fr_1.8fr_0.8fr_0.65fr_0.8fr]"
                    >
                        <span
                            ><strong>{{ row.office }}</strong
                            ><small
                                v-if="row.certification"
                                class="mt-1 block text-[9px]"
                                >{{ row.certification }}</small
                            ></span
                        >
                        <span>{{ row.responsibility }}</span>
                        <span class="uppercase">{{ row.determination }}</span>
                        <span>{{ row.paymentOrder }}</span>
                        <strong class="text-right tabular-nums">{{
                            money(row.amountCents)
                        }}</strong>
                    </div>
                    <div
                        v-for="blankRow in Math.max(0, 8 - rows.length)"
                        :key="`detail-blank-${blankRow}`"
                        class="paper-row h-14 grid-cols-[0.9fr_1.8fr_0.8fr_0.65fr_0.8fr]"
                        aria-label="Blank determination row"
                    >
                        <span></span><span></span><span></span><span></span
                        ><span></span>
                    </div>
                </div>
            </div>
            <footer
                class="mt-auto border-t border-stone-900 px-4 py-2 text-[9px] font-bold uppercase"
            >
                Continuation of Page 2 · Application record
                {{ document.identity.application_id }}
            </footer>
        </article>
    </div>
</template>

<style scoped>
.paper-section-title {
    border-bottom: 2px solid currentColor;
    padding-bottom: 0.25rem;
    font-size: 0.75rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.paper-table {
    margin-top: 0.375rem;
    overflow: hidden;
    border: 1px solid currentColor;
}

.paper-row {
    display: grid;
    border-bottom: 1px solid rgb(168 162 158);
    font-size: 0.6875rem;
}

.paper-row:last-child {
    border-bottom: 0;
}

.paper-row > * {
    min-width: 0;
    padding: 0.375rem;
    overflow-wrap: anywhere;
    border-right: 1px solid rgb(168 162 158);
}

.paper-row > *:last-child {
    border-right: 0;
}

.paper-table-head {
    background: rgb(231 229 228);
    font-size: 0.5625rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.paper-field-grid {
    display: grid;
    margin-top: 0.375rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    border-top: 1px solid currentColor;
    border-left: 1px solid currentColor;
}

.paper-field-grid > div {
    min-width: 0;
    min-height: 2.75rem;
    padding: 0.375rem;
    border-right: 1px solid currentColor;
    border-bottom: 1px solid currentColor;
}

.paper-field-grid dt {
    font-size: 0.5625rem;
    font-weight: 900;
    text-transform: uppercase;
}

.paper-field-grid dd {
    min-height: 1rem;
    margin-top: 0.125rem;
    font-size: 0.6875rem;
    font-weight: 700;
    overflow-wrap: anywhere;
}

@media (min-width: 640px) {
    .paper-field-grid.sm\:grid-cols-3 {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .paper-field-grid.sm\:grid-cols-4 {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

@media print {
    .processing-paper {
        display: flex;
        width: 210mm;
        min-height: 297mm;
        flex-direction: column;
        border-color: #000;
        color: #000;
        background: #fff;
        box-shadow: none;
    }
}
</style>
