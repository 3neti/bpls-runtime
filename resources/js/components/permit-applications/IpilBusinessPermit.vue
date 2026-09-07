<script setup lang="ts">
import { QrCode } from '@lucide/vue';
import { computed } from 'vue';
import permitFooterUrl from '../../../pdf/ipil-business-permit/footer.jpg';
import permitHeaderUrl from '../../../pdf/ipil-business-permit/header.jpg';
import permitPatternUrl from '../../../pdf/ipil-business-permit/security-pattern.jpg';

type OfficialReceipt = {
    receipt_group_key: string;
    receipt_number: string;
    series: string | null;
    amount_minor: number;
};

type IssuingAuthority = {
    office: string;
    name: string | null;
    authority_status: string;
    signature_reference?: string | null;
    signature_applied?: boolean;
    production_authority?: boolean;
};

type Permit = {
    state?: string;
    current_stage?: string;
    permit_number: string | null;
    issued_on: string | null;
    valid_until: string | null;
    business_name: string;
    owner_operator: string;
    business_address: string | null;
    lines_of_business: string[];
    conditions: string[];
    issuing_authority: IssuingAuthority;
    official_receipts?: OfficialReceipt[];
    receipt_coverage_confirmed?: boolean;
    semantic_classification?: string;
    production_authority: boolean;
};

type Verification = {
    reference: string;
    view_url: string;
    qr_data_url?: string;
};

const props = withDefaults(
    defineProps<{
        permit: Permit;
        verification: Verification;
        applicationYear: number;
        publicSafe?: boolean;
    }>(),
    { publicSafe: false },
);

const displayYear = computed(() => String(props.applicationYear));
const firstYearPair = computed(() => displayYear.value.slice(0, 2));
const secondYearPair = computed(() => displayYear.value.slice(2, 4));
const linesOfBusiness = computed(() =>
    props.permit.lines_of_business.length > 0
        ? props.permit.lines_of_business.join(' / ').toUpperCase()
        : 'NOT RECORDED',
);
const issuedSentence = computed(() => {
    if (!props.permit.issued_on) {
        return 'Issuance date pending.';
    }

    const date = new Date(`${props.permit.issued_on}T00:00:00`);

    return `Issued this ${ordinal(date.getDate())} day of ${date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })} at Ipil, Zamboanga Sibugay.`;
});
const receiptLine = computed(() =>
    (props.permit.official_receipts ?? [])
        .map((receipt) =>
            [
                receipt.receipt_number,
                receipt.series,
                money(receipt.amount_minor),
            ]
                .filter(Boolean)
                .join(' · '),
        )
        .join(' | '),
);
const stateLabel = computed(() =>
    (props.permit.state ?? props.permit.current_stage ?? 'pending')
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase()),
);
const isSynthetic = computed(
    () =>
        props.permit.semantic_classification === 'synthetic_only' ||
        props.permit.production_authority === false,
);

function date(value: string | null, uppercase = false): string {
    if (!value) {
        return 'PENDING';
    }

    const formatted = new Date(`${value}T00:00:00`).toLocaleDateString(
        'en-US',
        { month: 'long', day: '2-digit', year: 'numeric' },
    );

    return uppercase ? formatted.toUpperCase() : formatted;
}

function ordinal(day: number): string {
    const remainder = day % 100;

    if (remainder >= 11 && remainder <= 13) {
        return `${day}TH`;
    }

    return `${day}${day % 10 === 1 ? 'ST' : day % 10 === 2 ? 'ND' : day % 10 === 3 ? 'RD' : 'TH'}`;
}

function money(amountMinor: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amountMinor / 100);
}
</script>

<template>
    <article
        data-testid="ipil-business-permit"
        :data-public-safe="publicSafe"
        class="[container-type:inline-size] relative isolate mx-auto aspect-[595/842] w-full max-w-[52rem] overflow-hidden bg-white text-black shadow-xl print:max-w-none print:shadow-none"
        :style="{
            backgroundImage: `url(${permitPatternUrl})`,
            backgroundPosition: 'center',
            backgroundSize: 'cover',
        }"
    >
        <img
            :src="permitHeaderUrl"
            alt="Municipality of Ipil and BPLO masthead"
            class="absolute inset-x-0 top-0 z-10 h-[12.9%] w-full object-fill"
        />
        <img
            :src="permitFooterUrl"
            alt="Atong Ipil municipal footer"
            class="absolute inset-x-0 bottom-0 z-10 h-[17.8%] w-full object-fill"
        />

        <div
            aria-hidden="true"
            class="absolute inset-x-0 top-[29%] z-0 text-center leading-[0.82] font-black select-none"
        >
            <span class="block text-[22cqw] text-slate-300/70">{{
                firstYearPair
            }}</span>
            <span class="block text-[22cqw] text-red-200/70">{{
                secondYearPair
            }}</span>
        </div>

        <div
            class="absolute inset-x-[5.8%] top-[13.8%] bottom-[17.2%] z-20 flex flex-col"
        >
            <header class="text-center">
                <h2
                    class="text-[5.1cqw] leading-none font-black tracking-tight text-red-600 [text-shadow:1px_1px_0_#111]"
                >
                    BUSINESS PERMIT
                </h2>
                <p
                    v-if="isSynthetic"
                    class="mt-[1.1cqw] text-[1.25cqw] font-semibold text-red-700"
                >
                    LABORATORY SPECIMEN - NOT FOR OFFICIAL USE
                </p>
            </header>

            <dl class="mt-[1.3cqw] grid grid-cols-3 gap-[1.8cqw] text-center">
                <div
                    v-for="item in [
                        [
                            'BUSINESS PERMIT NO.',
                            permit.permit_number ?? 'NOT YET ISSUED',
                        ],
                        ['DATE ISSUED', date(permit.issued_on, true)],
                        ['VALID UNTIL', date(permit.valid_until)],
                    ]"
                    :key="item[0]"
                >
                    <dt
                        class="bg-red-600 py-[0.55cqw] text-[1.35cqw] font-semibold text-white"
                    >
                        {{ item[0] }}
                    </dt>
                    <dd
                        class="border-b border-red-500 py-[1.1cqw] text-[1.85cqw] font-semibold"
                    >
                        {{ item[1] }}
                    </dd>
                </div>
            </dl>

            <p class="text-center text-[1.8cqw] leading-[3.5cqw]">
                This is to certify that permission is hereby granted to
            </p>

            <dl
                class="mx-auto mt-[3.1cqw] grid w-[86%] gap-[1.1cqw] text-[1.45cqw]"
            >
                <div class="grid grid-cols-[25%_1fr] items-end gap-[1cqw]">
                    <dt class="text-right">Name of Business</dt>
                    <dd
                        class="border-b border-red-500 pb-[0.25cqw] text-[2cqw] font-semibold uppercase"
                    >
                        {{ permit.business_name }}
                    </dd>
                </div>
                <div class="grid grid-cols-[25%_1fr] items-end gap-[1cqw]">
                    <dt class="text-right">Name of Owner/Operator</dt>
                    <dd
                        class="border-b border-red-500 pb-[0.25cqw] text-[1.85cqw] font-semibold uppercase"
                    >
                        {{ permit.owner_operator }}
                    </dd>
                </div>
                <div class="grid grid-cols-[25%_1fr] items-end gap-[1cqw]">
                    <dt class="text-right">Business Address</dt>
                    <dd
                        class="border-b border-red-500 pb-[0.25cqw] text-[1.42cqw] uppercase"
                    >
                        {{ permit.business_address ?? 'NOT RECORDED' }}
                    </dd>
                </div>
                <div class="grid grid-cols-[25%_1fr] items-end gap-[1cqw]">
                    <dt class="text-right">Line of Business</dt>
                    <dd
                        class="border-b border-red-500 pb-[0.25cqw] text-[1.42cqw] uppercase"
                    >
                        {{ linesOfBusiness }}
                    </dd>
                </div>
            </dl>

            <div class="mt-[2.1cqw] text-[1.35cqw] leading-[1.55]">
                <p>
                    To operate and conduct business within the jurisdiction of
                    the Municipality of Ipil, Zamboanga Sibugay, subject to
                    existing laws, ordinances, rules and regulations.
                </p>
                <p class="mt-[1cqw] text-[1.55cqw] font-semibold">CONDITIONS</p>
                <ol
                    class="mt-[0.5cqw] list-decimal space-y-[0.18cqw] pl-[3.2cqw]"
                >
                    <li v-for="condition in permit.conditions" :key="condition">
                        {{ condition }}
                    </li>
                </ol>
                <p class="mt-[1.4cqw] text-center">{{ issuedSentence }}</p>
            </div>

            <div
                class="mt-auto grid grid-cols-[24%_1fr] items-end gap-[3cqw] pb-[1cqw]"
            >
                <div class="text-center">
                    <img
                        v-if="verification.qr_data_url"
                        :src="verification.qr_data_url"
                        alt="QR code for exact permit identity verification"
                        class="mx-auto aspect-square w-[11cqw] bg-white"
                    />
                    <div
                        v-else
                        class="mx-auto grid aspect-square w-[11cqw] place-items-center border bg-white"
                    >
                        <QrCode class="size-1/2" />
                    </div>
                    <p class="mt-[0.4cqw] text-[0.95cqw] font-semibold">
                        SCAN TO VERIFY IDENTITY
                    </p>
                </div>

                <div class="space-y-[1.15cqw] text-[1cqw] leading-[1.35]">
                    <div class="text-center">
                        <p
                            v-if="isSynthetic"
                            class="font-semibold text-red-700"
                        >
                            SYNTHETIC AUTHORIZATION REFERENCE - NO MAYORAL
                            SIGNATURE APPLIED
                        </p>
                        <p class="mt-[0.45cqw] text-[1.75cqw] font-semibold">
                            HON.
                            {{
                                (
                                    permit.issuing_authority.name ??
                                    'UNVERIFIED MUNICIPAL MAYOR'
                                ).toUpperCase()
                            }}
                        </p>
                        <p class="text-[1.25cqw]">Municipal Mayor</p>
                        <p
                            v-if="
                                permit.issuing_authority.signature_reference &&
                                !publicSafe
                            "
                            class="font-mono text-[0.72cqw]"
                        >
                            {{ permit.issuing_authority.signature_reference }}
                        </p>
                    </div>

                    <div class="grid grid-cols-[auto_1fr] gap-[1.2cqw]">
                        <strong>OFFICIAL RECEIPTS:</strong>
                        <span v-if="publicSafe">
                            {{
                                permit.receipt_coverage_confirmed
                                    ? 'Complete coverage confirmed; receipt numbers withheld from this public view.'
                                    : 'Not confirmed.'
                            }}
                        </span>
                        <span v-else-if="receiptLine">{{ receiptLine }}</span>
                        <span v-else>NOT BOUND</span>
                    </div>

                    <p>
                        NOTE: This permit must be displayed in a conspicuous
                        place within the establishment and renewed after every
                        end of the quarter/semester/year. QR verification
                        confirms identity only and does not establish legal
                        effect.
                    </p>
                    <p class="font-semibold">
                        Identity: {{ verification.reference }} | State:
                        {{ stateLabel }}
                    </p>
                </div>
            </div>
        </div>
    </article>
</template>
