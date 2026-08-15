<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Save, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    show as citizenShow,
    index as citizenIndex,
    store as citizenStore,
    update as citizenUpdate,
} from '@/actions/App/Http/Controllers/Citizen/PermitApplicationController';
import {
    index as staffIndex,
    store as staffStore,
} from '@/actions/App/Http/Controllers/Staff/PermitApplicationController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem } from '@/types';

type Option = {
    label: string;
    value: string;
};

type LineOfBusiness = {
    id: number;
    name: string;
    code: string;
};

type BusinessActivityRow = {
    key: number;
    line_of_business_id?: number;
    declared_gross_sales_pesos?: string;
    capital_investment_pesos?: string;
    quantity?: number;
    started_on?: string | null;
};

type PermitApplicationDraft = {
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
    established_on: string | null;
    started_on: string | null;
    registered_on: string | null;
    application_year: number;
    type: string;
    lines: (BusinessActivityRow & { id: number })[];
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
        address: string | null;
    }[];
};

const props = defineProps<{
    intakeAudience: 'staff' | 'citizen';
    currentApplicationYear: number;
    applicationTypes: Option[];
    lineOfBusinesses: LineOfBusiness[];
    applicant?: {
        name: string;
        email: string;
    };
    registry?: Registry;
    draft?: PermitApplicationDraft;
}>();

const isCitizenIntake = computed(() => props.intakeAudience === 'citizen');
const isEditing = computed(() => props.draft !== undefined);
const selectedBusinessId = ref<number | ''>(props.draft?.business_id ?? '');
const usesExistingBusiness = computed(
    () => isCitizenIntake.value && selectedBusinessId.value !== '',
);
const registryBusinessReadOnly = computed(
    () => isEditing.value || usesExistingBusiness.value,
);
const intakeIndex = computed(() =>
    isCitizenIntake.value ? citizenIndex() : staffIndex(),
);
const intakeAction = computed(() => {
    if (isEditing.value && props.draft) {
        return citizenUpdate.form(props.draft.id);
    }

    return isCitizenIntake.value ? citizenStore.form() : staffStore.form();
});
const intakeBack = computed(() =>
    isEditing.value && props.draft
        ? citizenShow(props.draft.id)
        : intakeIndex.value,
);

const businessActivities = ref<BusinessActivityRow[]>(
    props.draft?.lines.map((line) => ({ ...line, key: line.id })) ?? [
        { key: 1 },
    ],
);
let nextBusinessActivityKey =
    Math.max(0, ...businessActivities.value.map((activity) => activity.key)) +
    1;

function addBusinessActivity(): void {
    if (businessActivities.value.length >= 20) {
        return;
    }

    businessActivities.value.push({ key: nextBusinessActivityKey });
    nextBusinessActivityKey += 1;
}

function removeBusinessActivity(key: number): void {
    if (businessActivities.value.length === 1) {
        return;
    }

    businessActivities.value = businessActivities.value.filter(
        (activity) => activity.key !== key,
    );
}

function inputValue(
    value: string | number | null | undefined,
): string | number | undefined {
    return value ?? undefined;
}

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: isCitizenIntake.value
            ? 'My Permit Applications'
            : 'Permit Applications',
        href: intakeIndex.value,
    },
    {
        title: isEditing.value
            ? `Edit Draft #${props.draft?.id}`
            : isCitizenIntake.value
              ? 'New Draft'
              : 'New Application',
        href: '#',
    },
]);

setLayoutProps({ breadcrumbs: breadcrumbs.value });
</script>

<template>
    <div class="contents">
        <Head
            :title="
                isCitizenIntake
                    ? isEditing
                        ? `Edit Permit Application Draft #${draft?.id}`
                        : 'New Permit Application Draft'
                    : 'New Permit Application'
            "
        />

        <main class="flex h-full flex-1 flex-col gap-4 p-4">
            <section class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        {{
                            isCitizenIntake
                                ? isEditing
                                    ? `Edit Permit Application Draft #${draft?.id}`
                                    : 'New Permit Application Draft'
                                : 'New Permit Application'
                        }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{
                            isCitizenIntake
                                ? isEditing
                                    ? 'Update the saved facts in this citizen draft.'
                                    : 'Record your business information for municipal review.'
                                : 'Record a staff-entered business permit application.'
                        }}
                    </p>
                </div>
                <Button as-child variant="outline">
                    <Link :href="intakeBack">
                        <ArrowLeft />
                        Back
                    </Link>
                </Button>
            </section>

            <Form
                v-bind="intakeAction"
                v-slot="{ errors, processing }"
                class="grid gap-4"
            >
                <section
                    v-if="isCitizenIntake"
                    data-testid="citizen-draft-boundary"
                    class="border-l-4 border-amber-500 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:bg-amber-950/30 dark:text-amber-100"
                >
                    Saving {{ isEditing ? 'updates' : 'creates' }} a draft only.
                    It does not submit the application for assessment or assign
                    an official application number.
                </section>
                <InputError :message="errors.draft" />
                <input
                    v-if="draft"
                    type="hidden"
                    name="draft_version"
                    :value="draft.draft_version"
                />
                <fieldset
                    :disabled="isCitizenIntake && registry?.linked"
                    class="grid gap-4 rounded-lg border border-sidebar-border/70 bg-background p-4 md:grid-cols-2 dark:border-sidebar-border"
                >
                    <div class="md:col-span-2">
                        <h2 class="text-sm font-semibold text-foreground">
                            Legal business owner
                        </h2>
                        <p
                            v-if="isCitizenIntake && registry?.linked"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            Linked registry identity. Changes require a separate
                            registry-maintenance action.
                        </p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="owner_name">Owner name</Label>
                        <Input
                            id="owner_name"
                            name="owner_name"
                            :default-value="
                                draft?.owner_name ??
                                registry?.owner?.name ??
                                applicant?.name
                            "
                            required
                        />
                        <InputError :message="errors.owner_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="owner_email">Email</Label>
                        <Input
                            id="owner_email"
                            name="owner_email"
                            type="email"
                            :default-value="
                                isEditing
                                    ? inputValue(draft?.owner_email)
                                    : inputValue(
                                          registry?.owner?.email ??
                                              applicant?.email,
                                      )
                            "
                        />
                        <InputError :message="errors.owner_email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="owner_phone">Phone</Label>
                        <Input
                            id="owner_phone"
                            name="owner_phone"
                            :default-value="
                                inputValue(
                                    draft?.owner_phone ??
                                        registry?.owner?.phone,
                                )
                            "
                        />
                        <InputError :message="errors.owner_phone" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="owner_address">Owner address</Label>
                        <Input
                            id="owner_address"
                            name="owner_address"
                            :default-value="
                                inputValue(
                                    draft?.owner_address ??
                                        registry?.owner?.address,
                                )
                            "
                        />
                        <InputError :message="errors.owner_address" />
                    </div>
                </fieldset>

                <div
                    v-if="isCitizenIntake && registry?.linked && !isEditing"
                    class="grid gap-2 rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <Label for="business_id">Registered business</Label>
                    <select
                        id="business_id"
                        v-model="selectedBusinessId"
                        name="business_id"
                        data-testid="citizen-registry-business-select"
                        class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                    >
                        <option value="">Register a new business</option>
                        <option
                            v-for="business in registry.businesses"
                            :key="business.id"
                            :value="business.id"
                        >
                            {{ business.name }}
                        </option>
                    </select>
                    <InputError :message="errors.business_id" />
                    <p class="text-xs text-muted-foreground">
                        Selecting a registered business reuses its legal
                        registry facts without changing them.
                    </p>
                </div>
                <input
                    v-else-if="isCitizenIntake && draft"
                    type="hidden"
                    name="business_id"
                    :value="draft.business_id"
                />

                <fieldset
                    :disabled="registryBusinessReadOnly"
                    data-testid="permit-establishment-intake"
                    class="grid gap-4 rounded-lg border border-sidebar-border/70 bg-background p-4 md:grid-cols-2 lg:grid-cols-3 dark:border-sidebar-border"
                >
                    <div class="md:col-span-2 lg:col-span-3">
                        <h2 class="text-sm font-semibold text-foreground">
                            Business and establishment
                        </h2>
                        <p
                            v-if="registryBusinessReadOnly"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            Registry facts are read-only in a permit
                            application.
                        </p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="business_name">Business name</Label>
                        <Input
                            id="business_name"
                            name="business_name"
                            :default-value="inputValue(draft?.business_name)"
                            required
                        />
                        <InputError :message="errors.business_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="trade_name">Trade name</Label>
                        <Input
                            id="trade_name"
                            name="trade_name"
                            :default-value="inputValue(draft?.trade_name)"
                        />
                        <InputError :message="errors.trade_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="registration_number">
                            Registration number
                        </Label>
                        <Input
                            id="registration_number"
                            name="registration_number"
                            :default-value="
                                inputValue(draft?.registration_number)
                            "
                        />
                        <InputError :message="errors.registration_number" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="barangay">Barangay</Label>
                        <Input
                            id="barangay"
                            name="barangay"
                            :default-value="inputValue(draft?.barangay)"
                        />
                        <InputError :message="errors.barangay" />
                    </div>
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="business_address">Business address</Label>
                        <Input
                            id="business_address"
                            name="business_address"
                            :default-value="inputValue(draft?.business_address)"
                        />
                        <InputError :message="errors.business_address" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="ownership_type">Ownership type</Label>
                        <select
                            id="ownership_type"
                            name="ownership_type"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background transition-colors placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="" :selected="!draft?.ownership_type">
                                Not recorded
                            </option>
                            <option
                                value="sole-proprietorship"
                                :selected="
                                    draft?.ownership_type ===
                                    'sole-proprietorship'
                                "
                            >
                                Sole proprietorship
                            </option>
                            <option
                                value="partnership"
                                :selected="
                                    draft?.ownership_type === 'partnership'
                                "
                            >
                                Partnership
                            </option>
                            <option
                                value="corporation"
                                :selected="
                                    draft?.ownership_type === 'corporation'
                                "
                            >
                                Corporation
                            </option>
                            <option
                                value="cooperative"
                                :selected="
                                    draft?.ownership_type === 'cooperative'
                                "
                            >
                                Cooperative
                            </option>
                            <option
                                value="religious"
                                :selected="
                                    draft?.ownership_type === 'religious'
                                "
                            >
                                Religious
                            </option>
                            <option
                                value="non-profit"
                                :selected="
                                    draft?.ownership_type === 'non-profit'
                                "
                            >
                                Non-profit
                            </option>
                        </select>
                        <InputError :message="errors.ownership_type" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="organization_name">
                            Organization/company name
                        </Label>
                        <Input
                            id="organization_name"
                            name="organization_name"
                            :default-value="
                                inputValue(draft?.organization_name)
                            "
                        />
                        <InputError :message="errors.organization_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="occupancy">Occupancy</Label>
                        <select
                            id="occupancy"
                            name="occupancy"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background transition-colors placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="" :selected="!draft?.occupancy">
                                Not recorded
                            </option>
                            <option
                                value="owned"
                                :selected="draft?.occupancy === 'owned'"
                            >
                                Owned
                            </option>
                            <option
                                value="rented"
                                :selected="draft?.occupancy === 'rented'"
                            >
                                Rented
                            </option>
                        </select>
                        <InputError :message="errors.occupancy" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="building_name">Building name</Label>
                        <Input
                            id="building_name"
                            name="building_name"
                            :default-value="inputValue(draft?.building_name)"
                        />
                        <InputError :message="errors.building_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="property_index_number">
                            Property index number
                        </Label>
                        <Input
                            id="property_index_number"
                            name="property_index_number"
                            :default-value="
                                inputValue(draft?.property_index_number)
                            "
                        />
                        <InputError :message="errors.property_index_number" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="business_area_square_meters">
                            Business area (m²)
                        </Label>
                        <Input
                            id="business_area_square_meters"
                            name="business_area_square_meters"
                            type="number"
                            min="0.01"
                            step="0.01"
                            :default-value="
                                inputValue(draft?.business_area_square_meters)
                            "
                        />
                        <InputError
                            :message="errors.business_area_square_meters"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="male_employee_count">
                            Male employees
                        </Label>
                        <Input
                            id="male_employee_count"
                            name="male_employee_count"
                            type="number"
                            min="0"
                            :default-value="
                                inputValue(draft?.male_employee_count)
                            "
                        />
                        <InputError :message="errors.male_employee_count" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="female_employee_count">
                            Female employees
                        </Label>
                        <Input
                            id="female_employee_count"
                            name="female_employee_count"
                            type="number"
                            min="0"
                            :default-value="
                                inputValue(draft?.female_employee_count)
                            "
                        />
                        <InputError :message="errors.female_employee_count" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="business_contact_number">
                            Business contact number
                        </Label>
                        <Input
                            id="business_contact_number"
                            name="business_contact_number"
                            :default-value="
                                inputValue(draft?.business_contact_number)
                            "
                        />
                        <InputError :message="errors.business_contact_number" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="business_email">Business email</Label>
                        <Input
                            id="business_email"
                            name="business_email"
                            type="email"
                            :default-value="inputValue(draft?.business_email)"
                        />
                        <InputError :message="errors.business_email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="established_on">Established on</Label>
                        <Input
                            id="established_on"
                            name="established_on"
                            type="date"
                            :default-value="inputValue(draft?.established_on)"
                        />
                        <InputError :message="errors.established_on" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="started_on">Operations started on</Label>
                        <Input
                            id="started_on"
                            name="started_on"
                            type="date"
                            :default-value="inputValue(draft?.started_on)"
                        />
                        <InputError :message="errors.started_on" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="registered_on">Registered on</Label>
                        <Input
                            id="registered_on"
                            name="registered_on"
                            type="date"
                            :default-value="inputValue(draft?.registered_on)"
                        />
                        <InputError :message="errors.registered_on" />
                    </div>
                </fieldset>

                <section
                    class="grid gap-4 rounded-lg border border-sidebar-border/70 bg-background p-4 md:grid-cols-2 dark:border-sidebar-border"
                >
                    <div class="md:col-span-2">
                        <h2 class="text-sm font-semibold text-foreground">
                            Application
                        </h2>
                    </div>
                    <div v-if="!isCitizenIntake" class="grid gap-2">
                        <Label for="application_number">
                            Application number
                        </Label>
                        <Input
                            id="application_number"
                            name="application_number"
                        />
                        <InputError :message="errors.application_number" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="application_year">Application year</Label>
                        <Input
                            id="application_year"
                            name="application_year"
                            type="number"
                            min="2020"
                            :max="currentApplicationYear + 1"
                            :default-value="
                                draft?.application_year ??
                                currentApplicationYear
                            "
                            :readonly="isCitizenIntake"
                            required
                        />
                        <InputError :message="errors.application_year" />
                    </div>
                    <div v-if="!isCitizenIntake" class="grid gap-2">
                        <Label for="type">Type</Label>
                        <select
                            id="type"
                            name="type"
                            required
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background transition-colors placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option
                                v-for="type in applicationTypes"
                                :key="type.value"
                                :value="type.value"
                            >
                                {{ type.label }}
                            </option>
                        </select>
                        <InputError :message="errors.type" />
                    </div>
                    <input v-else type="hidden" name="type" value="new" />
                </section>

                <section
                    data-testid="permit-business-activity-intake"
                    class="grid gap-4 rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <h2 class="text-sm font-semibold text-foreground">
                            Business activities
                        </h2>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            data-testid="permit-add-business-activity"
                            :disabled="businessActivities.length >= 20"
                            @click="addBusinessActivity"
                        >
                            <Plus />
                            Add activity
                        </Button>
                    </div>

                    <InputError :message="errors.lines" />

                    <div
                        v-for="(activity, index) in businessActivities"
                        :key="activity.key"
                        data-testid="permit-business-activity-row"
                        class="grid gap-4 border-t border-sidebar-border/70 pt-4 md:grid-cols-2 lg:grid-cols-5 dark:border-sidebar-border"
                    >
                        <div
                            class="flex min-h-9 items-center justify-between gap-3 md:col-span-2 lg:col-span-5"
                        >
                            <h3 class="text-sm font-medium text-foreground">
                                Activity {{ index + 1 }}
                            </h3>
                            <Button
                                v-if="businessActivities.length > 1"
                                type="button"
                                variant="ghost"
                                size="icon"
                                :aria-label="`Remove activity ${index + 1}`"
                                @click="removeBusinessActivity(activity.key)"
                            >
                                <Trash2 />
                            </Button>
                        </div>
                        <div class="grid gap-2 md:col-span-2">
                            <Label :for="`lines_${index}_line_of_business_id`">
                                Line of business
                            </Label>
                            <select
                                :id="`lines_${index}_line_of_business_id`"
                                :name="`lines[${index}][line_of_business_id]`"
                                required
                                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background transition-colors placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option
                                    value=""
                                    disabled
                                    :selected="!activity.line_of_business_id"
                                >
                                    Select a line of business
                                </option>
                                <option
                                    v-for="lineOfBusiness in lineOfBusinesses"
                                    :key="lineOfBusiness.id"
                                    :value="lineOfBusiness.id"
                                    :selected="
                                        activity.line_of_business_id ===
                                        lineOfBusiness.id
                                    "
                                >
                                    {{ lineOfBusiness.name }}
                                </option>
                            </select>
                            <InputError
                                :message="
                                    errors[`lines.${index}.line_of_business_id`]
                                "
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`lines_${index}_declared_gross_sales`">
                                Declared gross sales
                            </Label>
                            <Input
                                :id="`lines_${index}_declared_gross_sales`"
                                :name="`lines[${index}][declared_gross_sales_pesos]`"
                                type="number"
                                min="0"
                                step="0.01"
                                :default-value="
                                    activity.declared_gross_sales_pesos
                                "
                                required
                            />
                            <InputError
                                :message="
                                    errors[
                                        `lines.${index}.declared_gross_sales_pesos`
                                    ]
                                "
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`lines_${index}_capital_investment`">
                                Capital investment
                            </Label>
                            <Input
                                :id="`lines_${index}_capital_investment`"
                                :name="`lines[${index}][capital_investment_pesos]`"
                                type="number"
                                min="0"
                                step="0.01"
                                :default-value="
                                    activity.capital_investment_pesos
                                "
                                required
                            />
                            <InputError
                                :message="
                                    errors[
                                        `lines.${index}.capital_investment_pesos`
                                    ]
                                "
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`lines_${index}_quantity`">
                                Quantity
                            </Label>
                            <Input
                                :id="`lines_${index}_quantity`"
                                :name="`lines[${index}][quantity]`"
                                type="number"
                                min="1"
                                :default-value="activity.quantity ?? 1"
                                required
                            />
                            <InputError
                                :message="errors[`lines.${index}.quantity`]"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`lines_${index}_started_on`">
                                Activity started on
                            </Label>
                            <Input
                                :id="`lines_${index}_started_on`"
                                :name="`lines[${index}][started_on]`"
                                type="date"
                                :default-value="inputValue(activity.started_on)"
                            />
                            <InputError
                                :message="errors[`lines.${index}.started_on`]"
                            />
                        </div>
                    </div>
                </section>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="processing">
                        <Save />
                        {{
                            isEditing
                                ? 'Save Changes'
                                : isCitizenIntake
                                  ? 'Save Draft'
                                  : 'Save Application'
                        }}
                    </Button>
                </div>
            </Form>
        </main>
    </div>
</template>
