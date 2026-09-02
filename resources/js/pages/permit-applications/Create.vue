<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { ArrowLeft, FileCheck2, Plus, Save, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    index as citizenIndex,
    show as citizenShow,
    store as citizenStore,
    update as citizenUpdate,
} from '@/actions/App/Http/Controllers/Citizen/PermitApplicationController';
import {
    index as staffIndex,
    store as staffStore,
} from '@/actions/App/Http/Controllers/Staff/PermitApplicationController';
import InputError from '@/components/InputError.vue';
import IpilField from '@/components/permit-applications/IpilField.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';

type Option = { label: string; value: string };
type LineOfBusiness = { id: number; name: string; code: string };
type Activity = {
    key: number;
    id?: number;
    line_of_business_id?: number;
    declared_gross_sales_pesos?: string | null;
    essential_gross_sales_pesos?: string | null;
    non_essential_gross_sales_pesos?: string | null;
    capital_investment_pesos?: string;
    quantity?: number;
    started_on?: string | null;
};
type Registry = {
    linked: boolean;
    owner: {
        id: number;
        name: string;
        email: string | null;
        phone: string | null;
        address: string | null;
    } | null;
    businesses: {
        id: number;
        name: string;
        trade_name: string | null;
        registration_number: string | null;
        address: string | null;
        barangay: string | null;
    }[];
};
type Draft = {
    id: number;
    business_id: number;
    draft_version: string;
    owner_name: string;
    owner_email: string | null;
    owner_phone: string | null;
    owner_address: string | null;
    business_name: string;
    trade_name: string | null;
    registration_number: string | null;
    business_address: string | null;
    barangay: string | null;
    ownership_type: string | null;
    organization_name: string | null;
    occupancy: string | null;
    building_name: string | null;
    property_index_number: string | null;
    business_area_square_meters: string | null;
    male_employee_count: number | null;
    female_employee_count: number | null;
    business_contact_number: string | null;
    business_email: string | null;
    registered_on: string | null;
    application_year: number;
    type: string;
    declaration?: Record<string, unknown> | null;
    lines: Activity[];
};
type CleanroomIntake = Record<string, unknown> & {
    run_id: string;
    application_year: number;
    lines: Activity[];
};

const props = defineProps<{
    intakeAudience: 'staff' | 'citizen';
    currentApplicationYear: number;
    applicationTypes: Option[];
    lineOfBusinesses: LineOfBusiness[];
    applicant?: { name: string; email: string };
    registry?: Registry;
    draft?: Draft;
    cleanroomIntake?: CleanroomIntake | null;
}>();

const isCitizen = computed(() => props.intakeAudience === 'citizen');
const isEditing = computed(() => props.draft !== undefined);
const selectedBusinessId = ref<number | ''>(props.draft?.business_id ?? '');
const selectedRegistryBusiness = computed(
    () =>
        props.registry?.businesses.find(
            (business) => business.id === selectedBusinessId.value,
        ) ?? null,
);
const action = computed(() => {
    if (props.draft) {
        return citizenUpdate.form(props.draft.id);
    }

    return isCitizen.value ? citizenStore.form() : staffStore.form();
});
const back = computed(() => {
    if (props.draft) {
        return citizenShow(props.draft.id);
    }

    return isCitizen.value ? citizenIndex() : staffIndex();
});

const activities = ref<Activity[]>(
    props.draft?.lines.map((line) => ({ ...line, key: line.id ?? line.key })) ??
        props.cleanroomIntake?.lines.map((line, index) => ({
            ...line,
            key: index + 1,
        })) ?? [{ key: 1, quantity: 1 }],
);
let nextKey = Math.max(0, ...activities.value.map((line) => line.key)) + 1;

function addActivity(): void {
    if (activities.value.length < 20) {
        activities.value.push({ key: nextKey++, quantity: 1 });
    }
}
function removeActivity(key: number): void {
    if (activities.value.length > 1) {
        activities.value = activities.value.filter((line) => line.key !== key);
    }
}
function nested(path: string): unknown {
    return path.split('.').reduce<unknown>((value, key) => {
        if (typeof value !== 'object' || value === null) {
            return undefined;
        }

        return (value as Record<string, unknown>)[key];
    }, props.draft?.declaration);
}
function cleanroom(key: string): unknown {
    return props.cleanroomIntake?.[key];
}
function text(value: unknown): string | number | null {
    return typeof value === 'string' || typeof value === 'number'
        ? value
        : null;
}
function initial(path: string, fallback?: unknown): string | number | null {
    return text(nested(path) ?? fallback);
}
function splitOwnerName(): { first: string; middle: string; last: string } {
    const value =
        props.draft?.owner_name ??
        props.registry?.owner?.name ??
        props.applicant?.name ??
        '';
    const parts = value.trim().split(/\s+/).filter(Boolean);

    return {
        first: parts.shift() ?? '',
        last: parts.pop() ?? '',
        middle: parts.join(' '),
    };
}
const ownerName = splitOwnerName();
const selectedType = computed(
    () => props.draft?.type ?? props.applicationTypes[0]?.value ?? 'new',
);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: isCitizen.value
            ? 'My Permit Applications'
            : 'Permit Applications',
        href: isCitizen.value ? citizenIndex() : staffIndex(),
    },
    { title: 'Application Form for Business Permit', href: '#' },
]);
setLayoutProps({ breadcrumbs: breadcrumbs.value });
</script>

<template>
    <div class="contents">
        <Head title="Application Form for Business Permit" />
        <main
            class="flex h-full flex-1 flex-col gap-4 bg-stone-100 p-3 text-stone-950 sm:p-5 dark:bg-stone-950 dark:text-stone-100"
        >
            <div
                class="mx-auto flex w-full max-w-6xl items-center justify-between gap-3"
            >
                <div>
                    <p
                        class="text-xs font-black tracking-[0.18em] text-blue-800 uppercase dark:text-blue-300"
                    >
                        Municipality of Ipil
                    </p>
                    <h1 class="text-lg font-bold">
                        Executable municipal document
                    </h1>
                </div>
                <Button as-child variant="outline"
                    ><Link :href="back"><ArrowLeft />Back</Link></Button
                >
            </div>

            <Form
                v-bind="action"
                v-slot="{ errors, processing }"
                class="mx-auto grid w-full max-w-6xl gap-4"
            >
                <input
                    v-if="draft"
                    type="hidden"
                    name="draft_version"
                    :value="draft.draft_version"
                />
                <input
                    v-if="isCitizen"
                    type="hidden"
                    name="type"
                    :value="selectedType"
                />
                <input
                    type="hidden"
                    name="application_year"
                    :value="
                        draft?.application_year ??
                        cleanroomIntake?.application_year ??
                        currentApplicationYear
                    "
                />
                <input
                    v-if="isCitizen && draft"
                    type="hidden"
                    name="business_id"
                    :value="draft.business_id"
                />

                <section
                    v-if="cleanroomIntake"
                    data-testid="lifecycle-cleanroom-intake"
                    class="border-l-4 border-emerald-600 bg-emerald-50 p-3 text-sm text-emerald-950 dark:bg-emerald-950/40 dark:text-emerald-100"
                >
                    <strong
                        >Lifecycle Cleanroom
                        {{ cleanroomIntake.run_id }}</strong
                    >
                    - complete the real Ipil application form. Saving creates a
                    draft; lodging freezes this declaration.
                </section>
                <section
                    data-testid="citizen-draft-boundary"
                    class="flex items-start gap-3 border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100"
                >
                    <FileCheck2 class="mt-0.5 size-5 shrink-0" />
                    <span
                        ><strong>Applicant Declaration.</strong> This document
                        remains editable until formally submitted. Assessment,
                        payment, and permit authority are separate municipal
                        facts.</span
                    >
                </section>
                <InputError :message="errors.draft" />

                <article
                    data-testid="ipil-executable-application-page-1"
                    class="overflow-hidden border-2 border-stone-900 bg-white shadow-sm dark:border-stone-400 dark:bg-stone-900"
                >
                    <header
                        class="grid gap-3 border-b-2 border-stone-900 p-4 sm:grid-cols-[1fr_auto] dark:border-stone-400"
                    >
                        <div class="text-center sm:text-left">
                            <h2
                                class="text-xl font-black tracking-wide uppercase sm:text-2xl"
                            >
                                Application Form for Business Permit
                            </h2>
                            <p class="mt-1 text-base font-bold">
                                TAX YEAR:
                                {{
                                    draft?.application_year ??
                                    cleanroomIntake?.application_year ??
                                    currentApplicationYear
                                }}
                            </p>
                        </div>
                        <div
                            class="border-2 border-stone-900 px-4 py-2 text-xs dark:border-stone-400"
                        >
                            <span class="block font-semibold"
                                >Application No.</span
                            >
                            <strong>{{
                                isCitizen ? 'Not yet assigned' : 'Staff intake'
                            }}</strong>
                        </div>
                    </header>

                    <div class="grid gap-5 p-4 sm:p-5">
                        <p class="text-sm leading-6">
                            The Honorable Mayor:<br />Pursuant to the applicable
                            Revenue Code, I apply for a BUSINESS/MAYOR'S PERMIT
                            subject to existing laws and ordinances.
                        </p>

                        <section
                            class="grid gap-4 border-y border-stone-400 py-4 md:grid-cols-3"
                        >
                            <fieldset class="grid gap-2">
                                <legend class="text-xs font-black uppercase">
                                    Application
                                </legend>
                                <label
                                    v-for="option in [
                                        { v: 'new', l: 'New' },
                                        { v: 'renewal', l: 'Renew' },
                                        { v: 'additional', l: 'Additional' },
                                    ]"
                                    :key="option.v"
                                    class="flex items-center gap-2 text-sm"
                                >
                                    <input
                                        type="radio"
                                        :name="isCitizen ? undefined : 'type'"
                                        :value="option.v"
                                        :checked="selectedType === option.v"
                                        :disabled="
                                            isCitizen ||
                                            option.v === 'additional'
                                        "
                                    />
                                    {{ option.l }}
                                </label>
                            </fieldset>
                            <div class="grid grid-cols-2 gap-4">
                                <fieldset class="grid content-start gap-2">
                                    <legend
                                        class="text-xs font-black uppercase"
                                    >
                                        Transfer
                                    </legend>
                                    <label
                                        class="flex items-center gap-2 text-sm opacity-60"
                                        ><input type="checkbox" disabled />
                                        Ownership</label
                                    >
                                    <label
                                        class="flex items-center gap-2 text-sm opacity-60"
                                        ><input type="checkbox" disabled />
                                        Location</label
                                    >
                                    <span class="text-[10px] text-amber-700"
                                        >Municipal semantics unresolved</span
                                    >
                                </fieldset>
                                <fieldset class="grid content-start gap-2">
                                    <legend
                                        class="text-xs font-black uppercase"
                                    >
                                        Amendment
                                    </legend>
                                    <label
                                        v-for="label in [
                                            'Single to Partnership',
                                            'Single to Corporation',
                                            'Partnership to Single',
                                            'Partnership to Corporation',
                                            'Corporation to Single',
                                            'Corporation to Partnership',
                                        ]"
                                        :key="label"
                                        class="flex items-start gap-2 text-xs opacity-60"
                                        ><input type="checkbox" disabled />{{
                                            label
                                        }}</label
                                    >
                                </fieldset>
                            </div>
                            <fieldset class="grid content-start gap-2">
                                <legend class="text-xs font-black uppercase">
                                    Mode of Payment
                                </legend>
                                <label
                                    v-for="option in [
                                        { v: 'annually', l: 'Annually' },
                                        {
                                            v: 'semi_annually',
                                            l: 'Semi-Annually',
                                        },
                                        { v: 'quarterly', l: 'Quarterly' },
                                    ]"
                                    :key="option.v"
                                    class="flex items-center gap-2 text-sm"
                                >
                                    <input
                                        type="radio"
                                        name="mode_of_payment"
                                        :value="option.v"
                                        :checked="
                                            initial(
                                                'application.mode_of_payment',
                                                cleanroom('mode_of_payment') ??
                                                    'annually',
                                            ) === option.v
                                        "
                                        required
                                    />{{ option.l }}
                                </label>
                                <span class="text-[10px] text-stone-500"
                                    >Declaration only; no installment formula is
                                    created here.</span
                                >
                                <InputError :message="errors.mode_of_payment" />
                            </fieldset>
                        </section>

                        <section class="grid gap-3 md:grid-cols-2">
                            <IpilField
                                name="date_of_application"
                                label="Date of Application"
                                type="date"
                                :value="
                                    initial(
                                        'application.date_of_application',
                                        cleanroom('date_of_application') ??
                                            new Date()
                                                .toISOString()
                                                .slice(0, 10),
                                    )
                                "
                                :error="errors.date_of_application"
                                required
                            />
                            <IpilField
                                name="registration_number"
                                label="DTI/SEC/CDA Registration No."
                                :value="
                                    initial(
                                        'registration.number',
                                        draft?.registration_number ??
                                            cleanroom('registration_number'),
                                    )
                                "
                                :error="errors.registration_number"
                            />
                            <IpilField
                                name="reference_number"
                                label="Reference No."
                                :value="
                                    initial('registration.reference_number')
                                "
                                :error="errors.reference_number"
                            />
                            <IpilField
                                name="registered_on"
                                label="DTI/SEC/CDA Date of Registration"
                                type="date"
                                :value="
                                    initial(
                                        'registration.registered_on',
                                        draft?.registered_on,
                                    )
                                "
                                :error="errors.registered_on"
                            />
                        </section>

                        <section
                            class="grid gap-3 border-t border-stone-300 pt-4"
                        >
                            <fieldset class="flex flex-wrap gap-x-5 gap-y-2">
                                <legend
                                    class="mb-2 text-xs font-black uppercase"
                                >
                                    Type of Organization
                                </legend>
                                <label
                                    v-for="option in [
                                        {
                                            v: 'sole-proprietorship',
                                            l: 'Single',
                                        },
                                        { v: 'partnership', l: 'Partnership' },
                                        { v: 'corporation', l: 'Corporation' },
                                        { v: 'cooperative', l: 'Cooperative' },
                                        {
                                            v: 'non-profit',
                                            l: 'Non-profit Organization',
                                        },
                                    ]"
                                    :key="option.v"
                                    class="flex items-center gap-2 text-sm"
                                >
                                    <input
                                        type="radio"
                                        name="ownership_type"
                                        :value="option.v"
                                        :checked="
                                            initial(
                                                'organization.type',
                                                draft?.ownership_type ??
                                                    cleanroom('ownership_type'),
                                            ) === option.v
                                        "
                                    />{{ option.l }}
                                </label>
                            </fieldset>
                            <div class="grid gap-3 md:grid-cols-3">
                                <IpilField
                                    name="organization_name"
                                    label="Organization Name (if applicable)"
                                    :value="
                                        initial(
                                            'organization.organization_name',
                                            draft?.organization_name,
                                        )
                                    "
                                    :error="errors.organization_name"
                                />
                                <IpilField
                                    name="ctc_number"
                                    label="CTC No."
                                    :value="initial('organization.ctc_number')"
                                    :error="errors.ctc_number"
                                />
                                <IpilField
                                    name="tin"
                                    label="TIN"
                                    :value="initial('organization.tin')"
                                    :error="errors.tin"
                                />
                            </div>
                            <div
                                class="grid gap-3 md:grid-cols-[auto_1fr] md:items-end"
                            >
                                <fieldset class="flex flex-wrap gap-4">
                                    <legend class="text-xs font-semibold">
                                        Are you enjoying tax incentive from any
                                        Government Entity?
                                    </legend>
                                    <label
                                        class="flex items-center gap-2 text-sm"
                                        ><input
                                            type="radio"
                                            name="tax_incentive_enjoyed"
                                            value="1"
                                            :checked="
                                                nested(
                                                    'organization.tax_incentive_enjoyed',
                                                ) === true
                                            "
                                        />Yes</label
                                    >
                                    <label
                                        class="flex items-center gap-2 text-sm"
                                        ><input
                                            type="radio"
                                            name="tax_incentive_enjoyed"
                                            value="0"
                                            :checked="
                                                nested(
                                                    'organization.tax_incentive_enjoyed',
                                                ) !== true
                                            "
                                        />No</label
                                    >
                                </fieldset>
                                <IpilField
                                    name="tax_incentive_entity"
                                    label="Please specify the entity"
                                    :value="
                                        initial(
                                            'organization.tax_incentive_entity',
                                        )
                                    "
                                    :error="errors.tax_incentive_entity"
                                />
                            </div>
                        </section>

                        <section
                            class="grid gap-3 border-t border-stone-300 pt-4"
                        >
                            <h3
                                class="text-xs font-black tracking-wide uppercase"
                            >
                                Name of Tax Payer
                            </h3>
                            <div class="grid gap-3 sm:grid-cols-3">
                                <IpilField
                                    name="owner_last_name"
                                    label="Last Name"
                                    :value="
                                        initial(
                                            'taxpayer.last_name',
                                            cleanroom('owner_last_name') ??
                                                ownerName.last,
                                        )
                                    "
                                    :error="errors.owner_last_name"
                                    required
                                />
                                <IpilField
                                    name="owner_first_name"
                                    label="First Name"
                                    :value="
                                        initial(
                                            'taxpayer.first_name',
                                            cleanroom('owner_first_name') ??
                                                ownerName.first,
                                        )
                                    "
                                    :error="errors.owner_first_name"
                                    required
                                />
                                <IpilField
                                    name="owner_middle_name"
                                    label="Middle Name"
                                    :value="
                                        initial(
                                            'taxpayer.middle_name',
                                            cleanroom('owner_middle_name') ??
                                                ownerName.middle,
                                        )
                                    "
                                    :error="errors.owner_middle_name"
                                />
                            </div>
                            <input
                                type="hidden"
                                name="owner_email"
                                :value="
                                    draft?.owner_email ??
                                    registry?.owner?.email ??
                                    applicant?.email
                                "
                            />
                            <input
                                type="hidden"
                                name="owner_phone"
                                :value="
                                    draft?.owner_phone ??
                                    registry?.owner?.phone ??
                                    ''
                                "
                            />
                            <input
                                type="hidden"
                                name="owner_address"
                                :value="
                                    draft?.owner_address ??
                                    registry?.owner?.address ??
                                    String(cleanroom('owner_address') ?? '')
                                "
                            />
                        </section>

                        <section class="grid gap-3">
                            <div
                                v-if="
                                    isCitizen && registry?.linked && !isEditing
                                "
                                class="grid gap-1"
                            >
                                <label
                                    for="business_id"
                                    class="text-xs font-black uppercase"
                                    >Registered Business</label
                                >
                                <select
                                    id="business_id"
                                    v-model="selectedBusinessId"
                                    name="business_id"
                                    class="h-10 border border-stone-400 bg-white px-2 dark:bg-stone-900"
                                >
                                    <option value="">
                                        Register a new business
                                    </option>
                                    <option
                                        v-for="business in registry.businesses"
                                        :key="business.id"
                                        :value="business.id"
                                    >
                                        {{ business.name }}
                                    </option>
                                </select>
                            </div>
                            <IpilField
                                name="business_name"
                                label="Business Name"
                                :value="
                                    initial(
                                        'business.name',
                                        selectedRegistryBusiness?.name ??
                                            draft?.business_name ??
                                            cleanroom('business_name'),
                                    )
                                "
                                :error="errors.business_name"
                                required
                            />
                            <div class="grid gap-3 sm:grid-cols-2">
                                <IpilField
                                    name="business_plate_number"
                                    label="Business Plate No."
                                    :value="initial('business.plate_number')"
                                    :error="errors.business_plate_number"
                                />
                                <IpilField
                                    name="trade_name"
                                    label="Trade Name/Franchise"
                                    :value="
                                        initial(
                                            'business.trade_name',
                                            selectedRegistryBusiness?.trade_name ??
                                                draft?.trade_name ??
                                                cleanroom('trade_name'),
                                        )
                                    "
                                    :error="errors.trade_name"
                                />
                            </div>
                            <h3 class="pt-2 text-xs font-black uppercase">
                                Name of President/Treasurer of Corporation
                            </h3>
                            <div class="grid gap-3 sm:grid-cols-3">
                                <IpilField
                                    name="corporate_officer_last_name"
                                    label="Last Name"
                                    :value="
                                        initial('corporate_officer.last_name')
                                    "
                                    :error="errors.corporate_officer_last_name"
                                />
                                <IpilField
                                    name="corporate_officer_first_name"
                                    label="First Name"
                                    :value="
                                        initial('corporate_officer.first_name')
                                    "
                                    :error="errors.corporate_officer_first_name"
                                />
                                <IpilField
                                    name="corporate_officer_middle_name"
                                    label="Middle Name"
                                    :value="
                                        initial('corporate_officer.middle_name')
                                    "
                                    :error="
                                        errors.corporate_officer_middle_name
                                    "
                                />
                            </div>
                        </section>

                        <section
                            class="grid gap-4 border-t border-stone-300 pt-4 lg:grid-cols-2"
                        >
                            <div
                                v-for="address in [
                                    {
                                        key: 'business',
                                        title: 'Business Address',
                                    },
                                    { key: 'owner', title: `Owner's Address` },
                                ]"
                                :key="address.key"
                                class="grid gap-2 border border-stone-300 p-3"
                            >
                                <h3
                                    class="bg-stone-100 py-2 text-center text-sm font-black uppercase dark:bg-stone-800"
                                >
                                    {{ address.title }}
                                </h3>
                                <IpilField
                                    v-for="field in [
                                        {
                                            k: 'house_building_number',
                                            l: 'House No./Bldg. No.',
                                        },
                                        {
                                            k: 'building_name',
                                            l: 'Building Name',
                                        },
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
                                    :name="`${address.key}_${field.k}`"
                                    :label="field.l"
                                    :type="
                                        field.k === 'email' ? 'email' : 'text'
                                    "
                                    :value="
                                        initial(
                                            `${address.key}_address.${field.k}`,
                                            address.key === 'business'
                                                ? field.k === 'street'
                                                    ? (selectedRegistryBusiness?.address ??
                                                      draft?.business_address ??
                                                      cleanroom(
                                                          'business_address',
                                                      ))
                                                    : field.k === 'barangay'
                                                      ? (selectedRegistryBusiness?.barangay ??
                                                        draft?.barangay ??
                                                        cleanroom('barangay'))
                                                      : cleanroom(
                                                            `business_${field.k}`,
                                                        )
                                                : cleanroom(`owner_${field.k}`),
                                        )
                                    "
                                    :error="errors[`${address.key}_${field.k}`]"
                                />
                            </div>
                        </section>

                        <section
                            class="grid gap-3 border-t border-stone-300 pt-4 sm:grid-cols-3"
                        >
                            <IpilField
                                name="property_index_number"
                                label="Property Index Number (PIN)"
                                :value="
                                    initial(
                                        'establishment.property_index_number',
                                        draft?.property_index_number,
                                    )
                                "
                                :error="errors.property_index_number"
                            />
                            <IpilField
                                name="business_area_square_meters"
                                label="Business Area (in sq m)"
                                type="number"
                                min="0.01"
                                step="0.01"
                                :value="
                                    initial(
                                        'establishment.business_area_square_meters',
                                        draft?.business_area_square_meters ??
                                            cleanroom(
                                                'business_area_square_meters',
                                            ),
                                    )
                                "
                                :error="errors.business_area_square_meters"
                            />
                            <IpilField
                                name="total_employee_count"
                                label="Total No. of Employees"
                                type="number"
                                min="0"
                                :value="
                                    initial(
                                        'establishment.total_employees',
                                        cleanroom('total_employee_count'),
                                    )
                                "
                                :error="errors.total_employee_count"
                            />
                            <IpilField
                                name="employees_residing_in_lgu"
                                label="Employees Residing in LGU"
                                type="number"
                                min="0"
                                :value="
                                    initial(
                                        'establishment.employees_residing_in_lgu',
                                        cleanroom('employees_residing_in_lgu'),
                                    )
                                "
                                :error="errors.employees_residing_in_lgu"
                            />
                            <IpilField
                                name="male_employee_count"
                                label="Male Employees (supplementary canonical fact)"
                                type="number"
                                min="0"
                                :value="
                                    initial(
                                        'establishment.male_employees',
                                        draft?.male_employee_count ??
                                            cleanroom('male_employee_count'),
                                    )
                                "
                                :error="errors.male_employee_count"
                            />
                            <IpilField
                                name="female_employee_count"
                                label="Female Employees (supplementary canonical fact)"
                                type="number"
                                min="0"
                                :value="
                                    initial(
                                        'establishment.female_employees',
                                        draft?.female_employee_count ??
                                            cleanroom('female_employee_count'),
                                    )
                                "
                                :error="errors.female_employee_count"
                            />
                            <input
                                type="hidden"
                                name="occupancy"
                                :value="
                                    nested('rental.place_is_rented') === true ||
                                    cleanroom('occupancy') === 'rented'
                                        ? 'rented'
                                        : 'owned'
                                "
                            />
                        </section>

                        <section
                            class="grid gap-3 border-t border-stone-300 pt-4"
                        >
                            <h3 class="text-xs font-black uppercase">
                                If Place of Business is Rented, please identify
                                the following
                            </h3>
                            <IpilField
                                name="monthly_rental_pesos"
                                label="Monthly Rental"
                                type="number"
                                min="0"
                                step="0.01"
                                :value="
                                    initial(
                                        'rental.monthly_rental_pesos',
                                        cleanroom('monthly_rental_pesos'),
                                    )
                                "
                                :error="errors.monthly_rental_pesos"
                            />
                            <div class="grid gap-3 sm:grid-cols-3">
                                <IpilField
                                    name="lessor_last_name"
                                    label="Lessor Last Name"
                                    :value="initial('rental.lessor.last_name')"
                                    :error="errors.lessor_last_name"
                                />
                                <IpilField
                                    name="lessor_first_name"
                                    label="Lessor First Name"
                                    :value="initial('rental.lessor.first_name')"
                                    :error="errors.lessor_first_name"
                                />
                                <IpilField
                                    name="lessor_middle_name"
                                    label="Lessor Middle Name"
                                    :value="
                                        initial('rental.lessor.middle_name')
                                    "
                                    :error="errors.lessor_middle_name"
                                />
                            </div>
                            <div class="grid gap-3 md:grid-cols-2">
                                <IpilField
                                    v-for="field in [
                                        {
                                            k: 'house_building_number',
                                            l: 'House No./Bldg. No.',
                                        },
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
                                    :name="`lessor_${field.k}`"
                                    :label="field.l"
                                    :value="
                                        initial(
                                            `rental.lessor.address.${field.k}`,
                                        )
                                    "
                                    :error="errors[`lessor_${field.k}`]"
                                />
                            </div>
                        </section>

                        <section
                            class="grid gap-3 border-t border-stone-300 pt-4 sm:grid-cols-2 lg:grid-cols-4"
                        >
                            <h3
                                class="text-xs font-black uppercase sm:col-span-2 lg:col-span-4"
                            >
                                In case of Emergency - Contact Person /
                                Telephone / Mobile Phone / Email Address
                            </h3>
                            <IpilField
                                name="emergency_contact_name"
                                label="Contact Person"
                                :value="
                                    initial(
                                        'emergency_contact.name',
                                        cleanroom('emergency_contact_name'),
                                    )
                                "
                                :error="errors.emergency_contact_name"
                            />
                            <IpilField
                                name="emergency_contact_telephone"
                                label="Telephone"
                                :value="initial('emergency_contact.telephone')"
                                :error="errors.emergency_contact_telephone"
                            />
                            <IpilField
                                name="emergency_contact_mobile"
                                label="Mobile Phone"
                                :value="
                                    initial(
                                        'emergency_contact.mobile',
                                        cleanroom('emergency_contact_mobile'),
                                    )
                                "
                                :error="errors.emergency_contact_mobile"
                            />
                            <IpilField
                                name="emergency_contact_email"
                                label="Email Address"
                                type="email"
                                :value="initial('emergency_contact.email')"
                                :error="errors.emergency_contact_email"
                            />
                        </section>

                        <section
                            data-testid="permit-business-activity-intake"
                            class="grid gap-3 border-t border-stone-300 pt-4"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <h3 class="text-sm font-black uppercase">
                                    Lines of Business
                                </h3>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="activities.length >= 20"
                                    @click="addActivity"
                                    ><Plus />Add row</Button
                                >
                            </div>
                            <InputError :message="errors.lines" />
                            <div
                                class="hidden grid-cols-[100px_minmax(180px,1fr)_100px_140px_140px_140px_50px] border border-stone-900 bg-slate-200 text-[11px] font-black uppercase lg:grid dark:bg-slate-800"
                            >
                                <span class="p-2">Code</span
                                ><span class="p-2">Line of Business</span
                                ><span class="p-2">No. of Units</span
                                ><span class="p-2">Capitalization</span
                                ><span class="p-2">Gross Sales Essential</span
                                ><span class="p-2"
                                    >Gross Sales Non-Essential</span
                                ><span />
                            </div>
                            <div
                                v-for="(activity, index) in activities"
                                :key="activity.key"
                                data-testid="permit-business-activity-row"
                                class="grid gap-3 border border-stone-400 p-3 lg:grid-cols-[100px_minmax(180px,1fr)_100px_140px_140px_140px_50px] lg:items-start lg:border-t-0 lg:p-0"
                            >
                                <div class="grid gap-1 lg:p-2">
                                    <span
                                        class="text-[10px] font-black uppercase lg:hidden"
                                        >Code</span
                                    ><span class="font-mono text-xs">{{
                                        lineOfBusinesses.find(
                                            (line) =>
                                                line.id ===
                                                activity.line_of_business_id,
                                        )?.code ?? 'Select'
                                    }}</span>
                                </div>
                                <div class="grid gap-1 lg:p-2">
                                    <label
                                        class="text-[10px] font-black uppercase lg:hidden"
                                        >Line of Business</label
                                    ><select
                                        :name="`lines[${index}][line_of_business_id]`"
                                        required
                                        class="h-9 min-w-0 border border-stone-400 bg-white px-2 text-sm dark:bg-stone-900"
                                    >
                                        <option
                                            value=""
                                            disabled
                                            :selected="
                                                !activity.line_of_business_id
                                            "
                                        >
                                            Select activity
                                        </option>
                                        <option
                                            v-for="line in lineOfBusinesses"
                                            :key="line.id"
                                            :value="line.id"
                                            :selected="
                                                activity.line_of_business_id ===
                                                line.id
                                            "
                                        >
                                            {{ line.name }}
                                        </option></select
                                    ><InputError
                                        :message="
                                            errors[
                                                `lines.${index}.line_of_business_id`
                                            ]
                                        "
                                    />
                                </div>
                                <IpilField
                                    :name="`lines[${index}][quantity]`"
                                    label="No. of Units"
                                    type="number"
                                    min="1"
                                    :value="activity.quantity ?? 1"
                                    :error="errors[`lines.${index}.quantity`]"
                                    required
                                    class="lg:p-2"
                                />
                                <IpilField
                                    :name="`lines[${index}][capital_investment_pesos]`"
                                    label="Capitalization"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    :value="activity.capital_investment_pesos"
                                    :error="
                                        errors[
                                            `lines.${index}.capital_investment_pesos`
                                        ]
                                    "
                                    required
                                    class="lg:p-2"
                                />
                                <IpilField
                                    :name="`lines[${index}][essential_gross_sales_pesos]`"
                                    label="Gross Sales Essential"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    :value="
                                        activity.essential_gross_sales_pesos ??
                                        '0.00'
                                    "
                                    :error="
                                        errors[
                                            `lines.${index}.essential_gross_sales_pesos`
                                        ]
                                    "
                                    required
                                    class="lg:p-2"
                                />
                                <IpilField
                                    :name="`lines[${index}][non_essential_gross_sales_pesos]`"
                                    label="Gross Sales Non-Essential"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    :value="
                                        activity.non_essential_gross_sales_pesos ??
                                        activity.declared_gross_sales_pesos
                                    "
                                    :error="
                                        errors[
                                            `lines.${index}.non_essential_gross_sales_pesos`
                                        ]
                                    "
                                    required
                                    class="lg:p-2"
                                />
                                <Button
                                    v-if="activities.length > 1"
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="`Remove line ${index + 1}`"
                                    @click="removeActivity(activity.key)"
                                    ><Trash2
                                /></Button>
                            </div>
                        </section>

                        <section
                            class="grid gap-3 border-t-2 border-stone-900 pt-4 dark:border-stone-400"
                        >
                            <label class="flex items-start gap-3 text-sm"
                                ><input
                                    name="undertaking_accepted"
                                    type="checkbox"
                                    value="1"
                                    :checked="
                                        nested('undertaking.accepted') !== false
                                    "
                                    required
                                    class="mt-1"
                                /><span
                                    ><strong>Oath of Undertaking:</strong> I
                                    undertake to comply with the regulatory
                                    requirement and other deficiencies within 30
                                    days from release of the business
                                    permit.</span
                                ></label
                            >
                            <InputError
                                :message="errors.undertaking_accepted"
                            />
                            <div class="grid gap-4 sm:grid-cols-2">
                                <IpilField
                                    name="applicant_printed_name"
                                    label="SIGNATURE OF APPLICANT OVER PRINTED NAME"
                                    :value="
                                        initial(
                                            'undertaking.applicant_printed_name',
                                            cleanroom(
                                                'applicant_printed_name',
                                            ) ??
                                                applicant?.name ??
                                                draft?.owner_name,
                                        )
                                    "
                                    :error="errors.applicant_printed_name"
                                    required
                                />
                                <IpilField
                                    name="position_title"
                                    label="POSITION/TITLE"
                                    :value="
                                        initial(
                                            'undertaking.position_title',
                                            cleanroom('position_title') ??
                                                'Owner',
                                        )
                                    "
                                    :error="errors.position_title"
                                    required
                                />
                            </div>
                            <p class="text-[10px] text-amber-700">
                                Digital/signature authority semantics remain
                                unresolved; this field preserves the applicant's
                                printed-name declaration.
                            </p>
                        </section>
                    </div>
                </article>

                <div
                    class="sticky bottom-0 z-10 flex flex-col gap-2 border border-stone-300 bg-white/95 p-3 shadow-lg backdrop-blur sm:flex-row sm:items-center sm:justify-between dark:bg-stone-900/95"
                >
                    <p class="text-xs text-stone-600 dark:text-stone-300">
                        Same municipal nouns, responsive layout. Submission
                        remains a separate lodging action.
                    </p>
                    <Button
                        type="submit"
                        :disabled="processing || lineOfBusinesses.length === 0"
                        ><Save />{{
                            processing
                                ? 'Saving document...'
                                : isEditing
                                  ? 'Save document changes'
                                  : isCitizen
                                    ? 'Save application draft'
                                    : 'Save application'
                        }}</Button
                    >
                </div>
            </Form>
        </main>
    </div>
</template>
