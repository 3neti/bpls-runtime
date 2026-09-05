<script setup lang="ts">
import { computed, ref } from 'vue';

type OfficeFeeDeterminationLine = {
    evaluation_item_id: number;
    name: string;
    status:
        | 'awaiting_determination'
        | 'not_applicable'
        | 'confirmed'
        | 'changed'
        | 'determined';
    proposal_amount_cents: number | null;
    determined_amount_cents: number | null;
    display_amount_cents: number | null;
    source_classification: string | null;
    paperless_payment_order: {
        id: number;
        sequence: number;
        issued_at: string;
    } | null;
};

type OfficeFeeDetermination = {
    code: string;
    label: string;
    status: 'certified' | 'in_progress' | 'awaiting_determination';
    required_determination_count: number;
    resolved_determination_count: number;
    payment_order_count: number;
    total_amount_cents: number;
    certification: {
        officer_name: string | null;
        certified_at: string;
        statement: string;
    } | null;
    lines: OfficeFeeDeterminationLine[];
};

type DocumentProjection = {
    identity: {
        application_id: number;
        application_number: string | null;
        tracking_reference: string | null;
        tax_year: number;
        type: string;
        status: string;
    };
    declaration: {
        state: 'draft' | 'frozen';
        declared_at: string | null;
        snapshot_hash: string | null;
        snapshot: Record<string, any> | null;
    };
    verification: {
        description: string;
        issuing_office: string;
        status: string;
        date_issued: string | null;
        verified_by: number | null;
        recommending_approval: string | null;
    }[];
    routing: {
        status: 'pending' | 'determined';
        determination_id: number | null;
        determined_at: string | null;
        determined_by: string | null;
        reason: string | null;
        works: {
            id: number;
            office_code: string;
            office_label: string;
            line_of_business_name: string | null;
            situational_reason: string;
            required_work: string;
        }[];
    };
    page_2_assessment: {
        status: string;
        statement: string;
        populated_from_canonical_assessment: boolean;
        emerging_total_amount_cents: number | null;
        required_unresolved_charge_count: number;
        offices: OfficeFeeDetermination[];
    };
    computation_assessment_slip: {
        assessment_id: number;
        sequence: number;
        status: string;
        total_amount_cents: number;
        statement: string;
    } | null;
    treasury_counter_check: {
        result: string | null;
        checked_at: string;
        statement: string;
    } | null;
    municipal_treasurer: {
        action: string;
        decided_at: string;
        exact_approval: boolean;
        assessment_snapshot_hash: string;
    } | null;
    permit: {
        status: string;
        statement: string;
        mayor_signature_authority: string;
    };
};

const props = withDefaults(
    defineProps<{
        document: DocumentProjection;
        page?: 'all' | 'page_1' | 'page_2';
    }>(),
    { page: 'all' },
);
const snapshot = computed(() => props.document.declaration.snapshot ?? {});
const selectedOfficeCode = ref<string | null>(null);
const selectedOffice = computed(
    () =>
        props.document.page_2_assessment.offices.find(
            (office) => office.code === selectedOfficeCode.value,
        ) ??
        props.document.page_2_assessment.offices[0] ??
        null,
);

function value(path: string): any {
    return path
        .split('.')
        .reduce<any>((current, key) => current?.[key], snapshot.value);
}
function shown(item: unknown): string {
    if (item === true) {
        return 'Yes';
    }

    if (item === false) {
        return 'No';
    }

    return item === null || item === undefined || item === ''
        ? '—'
        : String(item).replaceAll('_', ' ');
}
function money(cents: number | null | undefined): string {
    if (cents === null || cents === undefined) {
        return '—';
    }

    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(cents / 100);
}
function date(value: string | null): string {
    return value
        ? new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium' }).format(
              new Date(value),
          )
        : '—';
}
function dateTime(value: string | null): string {
    return value
        ? new Intl.DateTimeFormat('en-PH', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : '—';
}
function determinationStatus(value: OfficeFeeDeterminationLine['status']) {
    return {
        awaiting_determination: 'Awaiting determination',
        not_applicable: 'Not Applicable',
        confirmed: 'Confirmed',
        changed: 'Changed',
        determined: 'Determined',
    }[value];
}
function officeStatus(value: OfficeFeeDetermination['status']) {
    return {
        certified: 'Certified',
        in_progress: 'In progress',
        awaiting_determination: 'Not started',
    }[value];
}
</script>

<template>
    <article
        data-testid="ipil-executable-document"
        class="grid gap-5 bg-stone-100 p-2 text-stone-950 sm:p-4 dark:bg-stone-950 dark:text-stone-100"
    >
        <section
            v-if="page === 'all' || page === 'page_1'"
            data-testid="ipil-executable-document-page-1"
            class="overflow-hidden border-2 border-stone-900 bg-white shadow-sm dark:border-stone-400 dark:bg-stone-900"
        >
            <header
                class="grid gap-3 border-b-2 border-stone-900 p-4 sm:grid-cols-[1fr_auto] dark:border-stone-400"
            >
                <div>
                    <h2 class="text-xl font-black uppercase sm:text-2xl">
                        Application Form for Business Permit
                    </h2>
                    <p class="font-bold">
                        TAX YEAR: {{ document.identity.tax_year }}
                    </p>
                </div>
                <div
                    class="grid min-w-56 gap-2 border-2 border-stone-900 p-2 text-xs dark:border-stone-400"
                >
                    <div>
                        <span class="block">Official application number</span>
                        <strong>{{
                            document.identity.application_number ??
                            'Pending municipal assignment'
                        }}</strong>
                    </div>
                    <div
                        v-if="document.identity.tracking_reference"
                        class="border-t border-stone-300 pt-2 dark:border-stone-600"
                    >
                        <span class="block">Submission tracking reference</span>
                        <strong class="block font-mono break-all">{{
                            document.identity.tracking_reference
                        }}</strong>
                    </div>
                </div>
            </header>
            <div class="grid gap-4 p-4 text-sm sm:p-5">
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-300 pb-3"
                >
                    <span class="font-black uppercase"
                        >Applicant Declaration</span
                    >
                    <span
                        :class="
                            document.declaration.state === 'frozen'
                                ? 'bg-emerald-100 text-emerald-900'
                                : 'bg-amber-100 text-amber-900'
                        "
                        class="px-3 py-1 text-xs font-black uppercase"
                        >{{ document.declaration.state }}</span
                    >
                    <span
                        v-if="document.declaration.snapshot_hash"
                        class="font-mono text-[10px] text-stone-500"
                        >SHA-256 {{ document.declaration.snapshot_hash }}</span
                    >
                </div>
                <div class="grid gap-4 md:grid-cols-3">
                    <dl class="grid gap-2">
                        <dt class="text-xs font-black uppercase">
                            Application
                        </dt>
                        <dd>☒ {{ shown(value('application.type')) }}</dd>
                        <dd>
                            Mode of Payment:
                            {{ shown(value('application.mode_of_payment')) }}
                        </dd>
                        <dd>
                            Date:
                            {{
                                shown(value('application.date_of_application'))
                            }}
                        </dd>
                    </dl>
                    <dl class="grid gap-2">
                        <dt class="text-xs font-black uppercase">Transfer</dt>
                        <dd class="text-stone-500">☐ Ownership</dd>
                        <dd class="text-stone-500">☐ Location</dd>
                        <dd class="text-[10px] text-amber-700">
                            Not yet available — municipal procedure requires
                            confirmation
                        </dd>
                    </dl>
                    <dl class="grid gap-2">
                        <dt class="text-xs font-black uppercase">Amendment</dt>
                        <dd class="text-stone-500">No executable selection</dd>
                        <dd class="text-[10px] text-amber-700">
                            Not yet available — municipal procedure requires
                            confirmation
                        </dd>
                    </dl>
                </div>
                <div
                    class="grid gap-3 border-y border-stone-300 py-3 md:grid-cols-2"
                >
                    <p>
                        <strong>Date of Application:</strong>
                        {{ shown(value('application.date_of_application')) }}
                    </p>
                    <p>
                        <strong>DTI/SEC/CDA Registration No.:</strong>
                        {{ shown(value('registration.number')) }}
                    </p>
                    <p>
                        <strong>Reference No.:</strong>
                        {{ shown(value('registration.reference_number')) }}
                    </p>
                    <p>
                        <strong>DTI/SEC/CDA Date of Registration:</strong>
                        {{ shown(value('registration.registered_on')) }}
                    </p>
                    <p class="md:col-span-2">
                        <strong>Type of Organization:</strong>
                        {{ shown(value('organization.type')) }} ·
                        <strong>CTC No.:</strong>
                        {{ shown(value('organization.ctc_number')) }} ·
                        <strong>TIN:</strong>
                        {{ shown(value('organization.tin')) }}
                    </p>
                    <p class="md:col-span-2">
                        <strong>Tax incentive from Government Entity:</strong>
                        {{ shown(value('organization.tax_incentive_enjoyed')) }}
                        ·
                        {{ shown(value('organization.tax_incentive_entity')) }}
                    </p>
                </div>
                <section>
                    <h3 class="mb-2 text-xs font-black uppercase">
                        Name of Tax Payer
                    </h3>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <p>
                            <span class="block text-[10px] uppercase"
                                >Last Name</span
                            ><strong>{{
                                shown(value('taxpayer.last_name'))
                            }}</strong>
                        </p>
                        <p>
                            <span class="block text-[10px] uppercase"
                                >First Name</span
                            ><strong>{{
                                shown(value('taxpayer.first_name'))
                            }}</strong>
                        </p>
                        <p>
                            <span class="block text-[10px] uppercase"
                                >Middle Name</span
                            ><strong>{{
                                shown(value('taxpayer.middle_name'))
                            }}</strong>
                        </p>
                    </div>
                </section>
                <section class="grid gap-2">
                    <p>
                        <strong>Business Name:</strong>
                        {{ shown(value('business.name')) }}
                    </p>
                    <p>
                        <strong>Business Plate No.:</strong>
                        {{ shown(value('business.plate_number')) }}
                    </p>
                    <p>
                        <strong>Trade Name/Franchise:</strong>
                        {{ shown(value('business.trade_name')) }}
                    </p>
                    <h3 class="pt-2 text-xs font-black uppercase">
                        Name of President/Treasurer of Corporation
                    </h3>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <p>
                            Last Name:
                            {{ shown(value('corporate_officer.last_name')) }}
                        </p>
                        <p>
                            First Name:
                            {{ shown(value('corporate_officer.first_name')) }}
                        </p>
                        <p>
                            Middle Name:
                            {{ shown(value('corporate_officer.middle_name')) }}
                        </p>
                    </div>
                </section>
                <div class="grid gap-4 lg:grid-cols-2">
                    <section
                        v-for="address in [
                            {
                                key: 'business_address',
                                title: 'Business Address',
                            },
                            { key: 'owner_address', title: `Owner's Address` },
                        ]"
                        :key="address.key"
                        class="border border-stone-300"
                    >
                        <h3
                            class="bg-stone-100 p-2 text-center font-black uppercase dark:bg-stone-800"
                        >
                            {{ address.title }}
                        </h3>
                        <dl class="divide-y divide-stone-200">
                            <div
                                v-for="field in [
                                    {
                                        k: 'house_or_building_number',
                                        l: 'House No./Bldg. No.',
                                    },
                                    { k: 'building_name', l: 'Building Name' },
                                    { k: 'unit_number', l: 'Unit No.' },
                                    { k: 'street', l: 'Street' },
                                    { k: 'barangay', l: 'Barangay' },
                                    { k: 'subdivision', l: 'Subdivision' },
                                    {
                                        k: 'city_municipality',
                                        l: 'City/Municipality',
                                    },
                                    { k: 'province', l: 'Province' },
                                    { k: 'telephone', l: 'Tel. No.' },
                                    { k: 'email', l: 'Email Address' },
                                ]"
                                :key="field.k"
                                class="grid grid-cols-[145px_1fr] gap-2 p-2"
                            >
                                <dt class="text-xs text-stone-500">
                                    {{ field.l }}
                                </dt>
                                <dd>
                                    {{
                                        shown(
                                            value(`${address.key}.${field.k}`),
                                        )
                                    }}
                                </dd>
                            </div>
                        </dl>
                    </section>
                </div>
                <div
                    class="grid gap-3 border-y border-stone-300 py-3 sm:grid-cols-3"
                >
                    <p>
                        <strong>Property Index Number (PIN):</strong>
                        {{
                            shown(value('establishment.property_index_number'))
                        }}
                    </p>
                    <p>
                        <strong>Business Area (in sq m):</strong>
                        {{
                            shown(
                                value(
                                    'establishment.business_area_square_meters',
                                ),
                            )
                        }}
                    </p>
                    <p>
                        <strong>Total No. of Employees:</strong>
                        {{ shown(value('establishment.total_employees')) }}
                    </p>
                    <p>
                        <strong>Employees Residing in LGU:</strong>
                        {{
                            shown(
                                value(
                                    'establishment.employees_residing_in_lgu',
                                ),
                            )
                        }}
                    </p>
                </div>
                <section class="grid gap-2">
                    <h3 class="text-xs font-black uppercase">
                        If Place of Business is Rented
                    </h3>
                    <p>
                        <strong>Monthly Rental:</strong>
                        {{ shown(value('rental.monthly_rental_pesos')) }}
                    </p>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <p>
                            <strong>Last Name:</strong>
                            {{ shown(value('rental.lessor.last_name')) }}
                        </p>
                        <p>
                            <strong>First Name:</strong>
                            {{ shown(value('rental.lessor.first_name')) }}
                        </p>
                        <p>
                            <strong>Middle Name:</strong>
                            {{ shown(value('rental.lessor.middle_name')) }}
                        </p>
                    </div>
                </section>
                <p>
                    <strong
                        >In case of Emergency - Contact Person/Tel No./Mobile
                        Phone No./Email Address:</strong
                    >
                    {{ shown(value('emergency_contact.name')) }} ·
                    {{ shown(value('emergency_contact.telephone')) }} ·
                    {{ shown(value('emergency_contact.mobile')) }} ·
                    {{ shown(value('emergency_contact.email')) }}
                </p>
                <section class="grid gap-2">
                    <h3 class="text-xs font-black uppercase">
                        Lines of Business
                    </h3>
                    <div
                        class="hidden grid-cols-[100px_1fr_100px_140px_140px_140px] border border-stone-900 bg-slate-200 text-[10px] font-black uppercase lg:grid dark:bg-slate-800"
                    >
                        <span class="p-2">Code</span
                        ><span class="p-2">Line of Business</span
                        ><span class="p-2">No. of Units</span
                        ><span class="p-2">Capitalization</span
                        ><span class="p-2">Gross Sales Essential</span
                        ><span class="p-2">Gross Sales Non-Essential</span>
                    </div>
                    <div
                        v-for="line in value('lines_of_business') ?? []"
                        :key="`${line.code}-${line.name}`"
                        class="grid gap-2 border border-stone-400 p-3 lg:grid-cols-[100px_1fr_100px_140px_140px_140px] lg:border-t-0 lg:p-0"
                    >
                        <p class="lg:p-2">
                            <span
                                class="block text-[10px] font-black uppercase lg:hidden"
                                >Code</span
                            >{{ shown(line.code) }}
                        </p>
                        <p class="lg:p-2">
                            <span
                                class="block text-[10px] font-black uppercase lg:hidden"
                                >Line of Business</span
                            >{{ shown(line.name) }}
                        </p>
                        <p class="lg:p-2">
                            <span
                                class="block text-[10px] font-black uppercase lg:hidden"
                                >No. of Units</span
                            >{{ shown(line.number_of_units) }}
                        </p>
                        <p class="lg:p-2">
                            <span
                                class="block text-[10px] font-black uppercase lg:hidden"
                                >Capitalization</span
                            >{{ money(line.capitalization_cents) }}
                        </p>
                        <p class="lg:p-2">
                            <span
                                class="block text-[10px] font-black uppercase lg:hidden"
                                >Gross Sales Essential</span
                            >{{ money(line.essential_gross_sales_cents) }}
                        </p>
                        <p class="lg:p-2">
                            <span
                                class="block text-[10px] font-black uppercase lg:hidden"
                                >Gross Sales Non-Essential</span
                            >{{ money(line.non_essential_gross_sales_cents) }}
                        </p>
                    </div>
                </section>
                <section
                    class="border-t-2 border-stone-900 pt-3 dark:border-stone-400"
                >
                    <p>
                        <strong>Oath of Undertaking:</strong>
                        {{
                            value('undertaking.accepted')
                                ? 'Accepted'
                                : 'Not yet accepted'
                        }}
                    </p>
                    <div class="mt-4 grid gap-4 text-center sm:grid-cols-2">
                        <p class="border-t border-stone-900 pt-1">
                            <strong>{{
                                shown(
                                    value('undertaking.applicant_printed_name'),
                                )
                            }}</strong
                            ><span class="block text-[10px] uppercase"
                                >Signature of Applicant over Printed Name</span
                            >
                        </p>
                        <p class="border-t border-stone-900 pt-1">
                            <strong>{{
                                shown(value('undertaking.position_title'))
                            }}</strong
                            ><span class="block text-[10px] uppercase"
                                >Position/Title</span
                            >
                        </p>
                    </div>
                </section>
            </div>
        </section>

        <section
            v-if="page === 'all' || page === 'page_2'"
            data-testid="ipil-executable-document-page-2"
            class="overflow-hidden border-2 border-stone-900 bg-white shadow-sm dark:border-stone-400 dark:bg-stone-900"
        >
            <h2
                class="bg-[#1f416b] px-4 py-3 text-lg font-black text-white uppercase"
            >
                Municipal Processing
            </h2>
            <div class="grid gap-4 p-4 sm:p-5">
                <section
                    v-if="document.routing.status === 'determined'"
                    class="grid gap-3 border-2 border-[#1f416b] bg-blue-50 p-3 dark:bg-blue-950/30"
                    data-testid="page-2-bplo-routing-recorded"
                >
                    <div
                        class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div>
                            <p
                                class="font-black tracking-[0.12em] text-[#1f416b] uppercase dark:text-blue-200"
                            >
                                BPLO routing recorded
                            </p>
                            <p class="mt-1 text-sm">
                                {{ document.routing.works.length }}
                                concerned-office work item(s) written to this
                                living Page 2 projection.
                            </p>
                        </div>
                        <span class="text-xs font-bold uppercase"
                            >Canonical record</span
                        >
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <article
                            v-for="work in document.routing.works"
                            :key="work.id"
                            class="border-l-4 border-[#1f416b] bg-white p-3 text-sm dark:bg-stone-900"
                        >
                            <p class="font-black">{{ work.office_label }}</p>
                            <p
                                class="text-xs text-stone-600 dark:text-stone-300"
                            >
                                {{
                                    work.line_of_business_name ??
                                    'Application-wide context'
                                }}
                            </p>
                            <p class="mt-2">{{ work.required_work }}</p>
                        </article>
                    </div>
                    <p class="text-xs text-stone-600 dark:text-stone-300">
                        {{ document.routing.determined_by }} ·
                        {{ shown(document.routing.determined_at) }} · Page 1
                        unchanged
                    </p>
                </section>

                <div
                    class="flex flex-col gap-2 border-b-2 border-stone-900 pb-3 sm:flex-row sm:items-end sm:justify-between dark:border-stone-400"
                >
                    <div>
                        <h3 class="font-black uppercase">
                            Office Fee Determinations
                        </h3>
                        <p class="mt-1 text-sm">
                            {{ document.page_2_assessment.statement }}
                        </p>
                    </div>
                    <div class="sm:text-right">
                        <p class="text-[10px] font-black uppercase">
                            Emerging total
                        </p>
                        <p class="text-xl font-black tabular-nums">
                            {{
                                money(
                                    document.page_2_assessment
                                        .emerging_total_amount_cents,
                                )
                            }}
                        </p>
                    </div>
                </div>

                <p
                    v-if="
                        document.page_2_assessment.offices.length === 0 &&
                        document.routing.status === 'pending'
                    "
                    class="border-2 border-dashed border-stone-400 bg-stone-50 p-4 text-sm dark:bg-stone-800"
                >
                    Awaiting the mandatory BPLO routing determination.
                </p>
                <p
                    v-else-if="document.page_2_assessment.offices.length === 0"
                    class="border-2 border-dashed border-stone-400 bg-stone-50 p-4 text-sm dark:bg-stone-800"
                >
                    BPLO routing is recorded. Office responsibility records will
                    appear when Evaluation work is created.
                </p>

                <div
                    v-else
                    class="grid gap-4 lg:grid-cols-[minmax(190px,0.72fr)_minmax(0,1.6fr)]"
                >
                    <div class="grid content-start gap-2">
                        <p class="text-xs font-black uppercase">
                            Concerned Offices
                        </p>
                        <button
                            v-for="office in document.page_2_assessment.offices"
                            :key="office.code"
                            type="button"
                            :aria-pressed="selectedOffice?.code === office.code"
                            class="grid gap-1 border p-3 text-left outline-none hover:bg-stone-50 focus-visible:ring-2 focus-visible:ring-[#1f416b] aria-pressed:border-2 aria-pressed:border-[#1f416b] aria-pressed:bg-blue-50 dark:hover:bg-stone-800 dark:aria-pressed:bg-blue-950/30"
                            @click="selectedOfficeCode = office.code"
                        >
                            <span
                                class="flex items-start justify-between gap-3 text-sm font-black"
                            >
                                <span>{{ office.label }}</span>
                                <span
                                    :class="
                                        office.status === 'certified'
                                            ? 'text-emerald-700 dark:text-emerald-300'
                                            : 'text-stone-500'
                                    "
                                    class="text-xs"
                                >
                                    {{ officeStatus(office.status) }}
                                </span>
                            </span>
                            <span
                                class="text-xs text-stone-600 dark:text-stone-400"
                            >
                                {{ money(office.total_amount_cents) }} ·
                                {{ office.resolved_determination_count }} of
                                {{ office.required_determination_count }}
                                determined
                            </span>
                        </button>
                    </div>

                    <section
                        v-if="selectedOffice"
                        class="min-w-0 border-2 border-stone-900 dark:border-stone-400"
                        data-testid="page-2-paperless-payment-orders"
                    >
                        <header
                            class="flex flex-col gap-2 border-b border-stone-300 bg-stone-50 p-3 sm:flex-row sm:items-start sm:justify-between dark:bg-stone-800"
                        >
                            <div>
                                <p class="text-xs font-black uppercase">
                                    {{ selectedOffice.label }}
                                </p>
                                <h3 class="font-black">
                                    Paperless Payment Orders
                                </h3>
                                <p class="text-xs text-stone-500">
                                    {{ selectedOffice.payment_order_count }}
                                    currently issued
                                </p>
                            </div>
                            <strong
                                class="text-lg font-black tabular-nums sm:text-right"
                            >
                                {{ money(selectedOffice.total_amount_cents) }}
                            </strong>
                        </header>

                        <div
                            v-for="line in selectedOffice.lines"
                            :key="line.evaluation_item_id"
                            class="grid gap-1 border-b border-stone-300 p-3 text-sm sm:grid-cols-[minmax(0,1fr)_150px_100px] sm:items-start"
                        >
                            <div>
                                <strong>{{ line.name }}</strong>
                                <p
                                    v-if="line.paperless_payment_order"
                                    class="text-[10px] text-stone-500"
                                >
                                    Paperless Payment Order #{{
                                        line.paperless_payment_order.id
                                    }}
                                </p>
                                <p
                                    v-else-if="
                                        line.display_amount_cents !== null
                                    "
                                    class="text-[10px] text-stone-500"
                                >
                                    Proposed amount
                                </p>
                            </div>
                            <span class="font-semibold">
                                {{ determinationStatus(line.status) }}
                            </span>
                            <strong class="tabular-nums sm:text-right">
                                {{ money(line.display_amount_cents) }}
                            </strong>
                        </div>

                        <div
                            v-if="selectedOffice.lines.length === 0"
                            class="p-4 text-sm text-stone-500"
                        >
                            No amount-bearing determination has been created for
                            this office.
                        </div>

                        <div
                            class="m-3 border-l-4 p-3 text-sm"
                            :class="
                                selectedOffice.certification
                                    ? 'border-emerald-600 bg-emerald-50 dark:bg-emerald-950/30'
                                    : 'border-stone-400 bg-stone-50 dark:bg-stone-800'
                            "
                        >
                            <template v-if="selectedOffice.certification">
                                <strong>Electronically certified</strong>
                                <p>
                                    {{
                                        selectedOffice.certification
                                            .officer_name ?? 'Municipal officer'
                                    }}
                                    ·
                                    {{
                                        dateTime(
                                            selectedOffice.certification
                                                .certified_at,
                                        )
                                    }}
                                </p>
                            </template>
                            <template v-else>
                                <strong>Office certification pending</strong>
                                <p>
                                    Complete all required determinations for
                                    this office.
                                </p>
                            </template>
                        </div>
                    </section>
                </div>

                <div
                    class="flex flex-col gap-2 border-2 border-dashed border-stone-400 bg-stone-50 p-3 text-sm sm:flex-row sm:items-center sm:justify-between dark:bg-stone-800"
                >
                    <div>
                        <strong>Consolidated Assessment</strong>
                        <p class="text-xs text-stone-600 dark:text-stone-400">
                            <template
                                v-if="
                                    document.page_2_assessment
                                        .required_unresolved_charge_count > 0
                                "
                            >
                                Waiting for
                                {{
                                    document.page_2_assessment
                                        .required_unresolved_charge_count
                                }}
                                required determination(s).
                            </template>
                            <template
                                v-else-if="document.computation_assessment_slip"
                            >
                                {{
                                    document.computation_assessment_slip
                                        .statement
                                }}
                            </template>
                            <template v-else>
                                Ready for Assessment preparation.
                            </template>
                        </p>
                    </div>
                    <strong class="text-lg tabular-nums">
                        {{
                            money(
                                document.computation_assessment_slip
                                    ?.total_amount_cents ??
                                    document.page_2_assessment
                                        .emerging_total_amount_cents,
                            )
                        }}
                    </strong>
                </div>
            </div>

            <h2
                class="bg-[#1f416b] px-4 py-3 text-lg font-black text-white uppercase"
            >
                Verification of Documents
            </h2>
            <div class="grid gap-2 p-4">
                <div
                    v-for="item in document.verification"
                    :key="item.description"
                    class="grid gap-2 border border-stone-300 p-3 sm:grid-cols-[1fr_150px_130px_130px]"
                >
                    <p class="font-semibold">{{ item.description }}</p>
                    <p>{{ item.issuing_office }}</p>
                    <p class="capitalize">{{ shown(item.status) }}</p>
                    <p>{{ item.date_issued ?? 'Not available' }}</p>
                </div>
            </div>

            <div
                class="grid gap-3 border-t border-stone-300 p-4 md:grid-cols-3"
            >
                <div class="border p-3">
                    <p class="text-xs font-black uppercase">
                        Treasury Counter-check
                    </p>
                    <p class="mt-1 font-semibold">
                        {{
                            document.treasury_counter_check?.statement ??
                            'Pending'
                        }}
                    </p>
                    <p class="text-xs text-stone-500">
                        {{
                            date(
                                document.treasury_counter_check?.checked_at ??
                                    null,
                            )
                        }}
                    </p>
                </div>
                <div class="border p-3">
                    <p class="text-xs font-black uppercase">
                        Municipal Treasurer
                    </p>
                    <p class="mt-1 font-semibold">
                        {{
                            document.municipal_treasurer?.exact_approval
                                ? 'Exact Assessment approved'
                                : document.municipal_treasurer
                                  ? shown(document.municipal_treasurer.action)
                                  : 'Pending'
                        }}
                    </p>
                    <p class="text-xs text-stone-500">
                        {{
                            date(
                                document.municipal_treasurer?.decided_at ??
                                    null,
                            )
                        }}
                    </p>
                </div>
                <div
                    class="border border-amber-400 bg-amber-50 p-3 text-amber-950 dark:bg-amber-950/30 dark:text-amber-100"
                >
                    <p class="text-xs font-black uppercase">Permit</p>
                    <p class="mt-1 font-black">
                        {{
                            document.permit.statement || 'Permit not yet issued'
                        }}
                    </p>
                    <p class="text-xs">
                        Mayor-signature and issuance authority remain
                        unresolved.
                    </p>
                </div>
            </div>
        </section>
    </article>
</template>
