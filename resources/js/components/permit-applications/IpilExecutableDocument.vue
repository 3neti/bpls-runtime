<script setup lang="ts">
import { computed } from 'vue';
import IpilMunicipalProcessingSheet from '@/components/permit-applications/IpilMunicipalProcessingSheet.vue';

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
    commissioned_path: boolean;
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
    signature_evidence: {
        id: number;
        signer_id: number;
        purpose: string;
        signable_type: string;
        signable_id: number;
        captured_at: string;
        method: string;
        evidence_digest: string;
        media_id: number;
        facsimile_data_url: string | null;
        legal_semantics: 'visual_facsimile_evidence_only';
    }[];
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
        processing_summary: string | null;
        total_label: string;
        total_source: string;
        emerging_total_amount_cents: number | null;
        required_unresolved_charge_count: number;
        offices: OfficeFeeDetermination[];
        concerned_office_payment_orders: {
            all_finalized: boolean;
            finalized_subtotal_amount_cents: number | null;
            offices: {
                routing_work_id: number;
                office_code: string;
                status: 'finalized' | 'awaiting_payment_order' | 'conflict';
                total_amount_cents: number | null;
            }[];
        } | null;
        treasury_lines_of_business: {
            assignment_id: number;
            name: string;
            assigned_by: string;
            assigned_at: string;
            payment_items: {
                id: number;
                name: string;
                determined_amount_cents: number;
            }[];
        }[];
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
    payment_reference: {
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
    official_receipt_reference: {
        receipt_number: string;
        series: string | null;
        issued_on: string;
    } | null;
    permit_reference: {
        state: string | null;
        permit_number: string | null;
        issued_on: string | null;
        valid_until: string | null;
        official_receipt_number: string | null;
        verification_reference: string | null;
    };
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
        recentCertificationOffice?: string | null;
    }>(),
    { page: 'all', recentCertificationOffice: null },
);
const snapshot = computed(() => props.document.declaration.snapshot ?? {});
const applicantLodgingSignature = computed(
    () =>
        (props.document.signature_evidence ?? []).find(
            (evidence) =>
                evidence.purpose === 'applicant_lodging' &&
                evidence.method === 'captured_facsimile' &&
                evidence.facsimile_data_url,
        ) ?? null,
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
</script>

<template>
    <article
        data-testid="ipil-executable-document"
        class="grid gap-5 bg-stone-100 p-2 text-stone-950 sm:p-4 dark:bg-stone-950 dark:text-stone-100"
    >
        <section
            v-if="page === 'all' || page === 'page_1'"
            data-testid="ipil-executable-document-page-1"
            class="min-w-0 border-2 border-stone-900 bg-white wrap-anywhere shadow-sm dark:border-stone-400 dark:bg-stone-900"
        >
            <header
                class="grid gap-3 border-b-2 border-stone-900 p-4 sm:grid-cols-[1fr_auto] dark:border-stone-400"
            >
                <div>
                    <h2 class="text-xl font-black uppercase sm:text-2xl">
                        Application Form for Business Permit
                    </h2>
                    <p class="font-bold">
                        TAX YEAR:
                        {{
                            value('application.tax_year') ??
                            document.identity.tax_year
                        }}
                    </p>
                </div>
                <div
                    class="grid min-w-0 gap-2 border-2 border-stone-900 p-2 text-xs dark:border-stone-400"
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
            <div class="grid min-w-0 gap-4 p-4 text-sm sm:p-5">
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
                        class="w-full min-w-0 font-mono text-[10px] break-all text-stone-500"
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
                        <strong>Organization Name:</strong>
                        {{ shown(value('organization.organization_name')) }}
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
                    <p data-testid="frozen-business-description">
                        <strong>Nature / Description of Business:</strong>
                        {{
                            value('business.activity_description') ??
                            value('applicant_business_activity_description') ??
                            '—'
                        }}
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
                            {
                                key: 'rental.lessor.address',
                                title: `Declared Lessor's Address`,
                            },
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
                                    {
                                        k: 'barangay_psgc_code',
                                        l: 'Barangay PSGC',
                                    },
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
                                class="grid min-w-0 grid-cols-[minmax(0,1fr)_minmax(0,1fr)] gap-2 p-2"
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
                        <strong>Male Employees:</strong>
                        {{ shown(value('establishment.male_employees')) }}
                    </p>
                    <p>
                        <strong>Female Employees:</strong>
                        {{ shown(value('establishment.female_employees')) }}
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
                        <strong>Place of Business Rented:</strong>
                        {{ shown(value('rental.place_is_rented')) }}
                    </p>
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
                <section
                    v-if="
                        !document.commissioned_path &&
                        value('lines_of_business')?.length
                    "
                    class="grid gap-2"
                >
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
                <details
                    v-if="value('applicant_documents_manifest')"
                    class="min-w-0 border-t border-stone-300 pt-3"
                >
                    <summary class="cursor-pointer font-bold">
                        Documents lodged with this declaration
                    </summary>
                    <ul class="mt-2 grid gap-2">
                        <li
                            v-for="item in value(
                                'applicant_documents_manifest.documents',
                            ) ?? []"
                            :key="item.document_id"
                            class="min-w-0"
                        >
                            <p>
                                <span v-if="item.label"
                                    >{{ item.label }} ·
                                </span>
                                {{ shown(item.document_type) }} ·
                                {{ item.original_name }}
                            </p>
                            <p class="text-xs">
                                Document {{ item.document_id }} · Version
                                {{ item.version }}
                            </p>
                            <p class="font-mono text-[10px] break-all">
                                SHA-256 {{ item.checksum_sha256 }}
                            </p>
                        </li>
                    </ul>
                    <p
                        v-if="
                            !value('applicant_documents_manifest.documents')
                                ?.length
                        "
                        class="mt-2"
                    >
                        No documents lodged.
                    </p>
                    <p class="mt-2 font-mono text-[10px] break-all">
                        Manifest SHA-256
                        {{ value('applicant_documents_manifest.digest') }}
                    </p>
                </details>
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
                        <div class="flex min-h-24 flex-col justify-end">
                            <img
                                v-if="
                                    applicantLodgingSignature?.facsimile_data_url
                                "
                                data-testid="applicant-lodging-signature"
                                :src="
                                    applicantLodgingSignature.facsimile_data_url
                                "
                                alt="Applicant signature facsimile"
                                class="mx-auto mb-1 h-20 max-w-full object-contain"
                            />
                            <p class="border-t border-stone-900 pt-1">
                                <strong>{{
                                    shown(
                                        value(
                                            'undertaking.applicant_printed_name',
                                        ),
                                    )
                                }}</strong
                                ><span class="block text-[10px] uppercase"
                                    >Signature of Applicant over Printed
                                    Name</span
                                >
                            </p>
                        </div>
                        <p class="mt-auto border-t border-stone-900 pt-1">
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

        <IpilMunicipalProcessingSheet
            v-if="page === 'all' || page === 'page_2'"
            :document="document"
            :recent-certification-office="recentCertificationOffice"
        />
    </article>
</template>
