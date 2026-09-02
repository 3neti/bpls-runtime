<script setup lang="ts">
import { computed } from 'vue';

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
    page_2_assessment: {
        status: string;
        statement: string;
        populated_from_canonical_assessment: boolean;
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
    post_payment_office_signatures: {
        status: string;
        statement: string;
    };
};

const props = defineProps<{ document: DocumentProjection }>();
const snapshot = computed(() => props.document.declaration.snapshot ?? {});

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
</script>

<template>
    <article
        data-testid="ipil-executable-document"
        class="grid gap-5 bg-stone-100 p-2 text-stone-950 sm:p-4 dark:bg-stone-950 dark:text-stone-100"
    >
        <section
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
                    class="border-2 border-stone-900 p-2 text-xs dark:border-stone-400"
                >
                    <span class="block">Application No.</span
                    ><strong>{{
                        document.identity.application_number ??
                        'Not yet assigned'
                    }}</strong
                    ><span
                        v-if="document.identity.tracking_reference"
                        class="mt-1 block font-mono"
                        >{{ document.identity.tracking_reference }}</span
                    >
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
                            Municipal semantics unresolved
                        </dd>
                    </dl>
                    <dl class="grid gap-2">
                        <dt class="text-xs font-black uppercase">Amendment</dt>
                        <dd class="text-stone-500">No executable selection</dd>
                        <dd class="text-[10px] text-amber-700">
                            Municipal semantics unresolved
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
            data-testid="ipil-executable-document-page-2"
            class="overflow-hidden border-2 border-stone-900 bg-white shadow-sm dark:border-stone-400 dark:bg-stone-900"
        >
            <h2
                class="bg-[#1f416b] px-4 py-3 text-lg font-black text-white uppercase"
            >
                Assessments
            </h2>
            <div class="grid gap-3 p-4">
                <p
                    class="border-2 border-dashed border-stone-400 bg-stone-50 p-4 text-sm dark:bg-stone-800"
                >
                    <strong>Not used by Ipil.</strong>
                    {{ document.page_2_assessment.statement }} It is
                    deliberately not populated from the canonical Assessment.
                </p>
                <p
                    v-if="document.computation_assessment_slip"
                    class="text-sm font-semibold"
                >
                    {{ document.computation_assessment_slip.statement }} ·
                    {{
                        money(
                            document.computation_assessment_slip
                                .total_amount_cents,
                        )
                    }}
                </p>
                <p v-else class="text-sm text-stone-600">
                    The separate Computation/Assessment Slip is not yet
                    available.
                </p>
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
                <p class="text-[10px] text-stone-500">
                    {{ document.post_payment_office_signatures.statement }}
                </p>
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
