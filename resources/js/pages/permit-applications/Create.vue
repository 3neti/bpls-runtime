<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Save, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import {
    index,
    store,
} from '@/actions/App/Http/Controllers/Staff/PermitApplicationController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
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
};

defineProps<{
    currentApplicationYear: number;
    applicationTypes: Option[];
    lineOfBusinesses: LineOfBusiness[];
}>();

const businessActivities = ref<BusinessActivityRow[]>([{ key: 1 }]);
let nextBusinessActivityKey = 2;

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

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Permit Applications',
        href: index(),
    },
    {
        title: 'New Application',
        href: '#',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="New Permit Application" />

        <main class="flex h-full flex-1 flex-col gap-4 p-4">
            <section class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        New Permit Application
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Record a staff-entered business permit application.
                    </p>
                </div>
                <Button as-child variant="outline">
                    <Link :href="index()">
                        <ArrowLeft />
                        Back
                    </Link>
                </Button>
            </section>

            <Form
                v-bind="store.form()"
                v-slot="{ errors, processing }"
                class="grid gap-4"
            >
                <section
                    class="grid gap-4 rounded-lg border border-sidebar-border/70 bg-background p-4 md:grid-cols-2 dark:border-sidebar-border"
                >
                    <div class="md:col-span-2">
                        <h2 class="text-sm font-semibold text-foreground">
                            Owner
                        </h2>
                    </div>
                    <div class="grid gap-2">
                        <Label for="owner_name">Owner name</Label>
                        <Input id="owner_name" name="owner_name" required />
                        <InputError :message="errors.owner_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="owner_email">Email</Label>
                        <Input
                            id="owner_email"
                            name="owner_email"
                            type="email"
                        />
                        <InputError :message="errors.owner_email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="owner_phone">Phone</Label>
                        <Input id="owner_phone" name="owner_phone" />
                        <InputError :message="errors.owner_phone" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="owner_address">Owner address</Label>
                        <Input id="owner_address" name="owner_address" />
                        <InputError :message="errors.owner_address" />
                    </div>
                </section>

                <section
                    data-testid="permit-establishment-intake"
                    class="grid gap-4 rounded-lg border border-sidebar-border/70 bg-background p-4 md:grid-cols-2 lg:grid-cols-3 dark:border-sidebar-border"
                >
                    <div class="md:col-span-2 lg:col-span-3">
                        <h2 class="text-sm font-semibold text-foreground">
                            Business and establishment
                        </h2>
                    </div>
                    <div class="grid gap-2">
                        <Label for="business_name">Business name</Label>
                        <Input
                            id="business_name"
                            name="business_name"
                            required
                        />
                        <InputError :message="errors.business_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="trade_name">Trade name</Label>
                        <Input id="trade_name" name="trade_name" />
                        <InputError :message="errors.trade_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="registration_number">
                            Registration number
                        </Label>
                        <Input
                            id="registration_number"
                            name="registration_number"
                        />
                        <InputError :message="errors.registration_number" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="barangay">Barangay</Label>
                        <Input id="barangay" name="barangay" />
                        <InputError :message="errors.barangay" />
                    </div>
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="business_address">Business address</Label>
                        <Input id="business_address" name="business_address" />
                        <InputError :message="errors.business_address" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="ownership_type">Ownership type</Label>
                        <select
                            id="ownership_type"
                            name="ownership_type"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background transition-colors placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="">Not recorded</option>
                            <option value="sole-proprietorship">
                                Sole proprietorship
                            </option>
                            <option value="partnership">Partnership</option>
                            <option value="corporation">Corporation</option>
                            <option value="cooperative">Cooperative</option>
                            <option value="religious">Religious</option>
                            <option value="non-profit">Non-profit</option>
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
                            <option value="">Not recorded</option>
                            <option value="owned">Owned</option>
                            <option value="rented">Rented</option>
                        </select>
                        <InputError :message="errors.occupancy" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="building_name">Building name</Label>
                        <Input id="building_name" name="building_name" />
                        <InputError :message="errors.building_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="property_index_number">
                            Property index number
                        </Label>
                        <Input
                            id="property_index_number"
                            name="property_index_number"
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
                        />
                        <InputError :message="errors.business_contact_number" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="business_email">Business email</Label>
                        <Input
                            id="business_email"
                            name="business_email"
                            type="email"
                        />
                        <InputError :message="errors.business_email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="established_on">Established on</Label>
                        <Input
                            id="established_on"
                            name="established_on"
                            type="date"
                        />
                        <InputError :message="errors.established_on" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="started_on">Operations started on</Label>
                        <Input id="started_on" name="started_on" type="date" />
                        <InputError :message="errors.started_on" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="registered_on">Registered on</Label>
                        <Input
                            id="registered_on"
                            name="registered_on"
                            type="date"
                        />
                        <InputError :message="errors.registered_on" />
                    </div>
                </section>

                <section
                    class="grid gap-4 rounded-lg border border-sidebar-border/70 bg-background p-4 md:grid-cols-2 dark:border-sidebar-border"
                >
                    <div class="md:col-span-2">
                        <h2 class="text-sm font-semibold text-foreground">
                            Application
                        </h2>
                    </div>
                    <div class="grid gap-2">
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
                            :default-value="currentApplicationYear"
                            required
                        />
                        <InputError :message="errors.application_year" />
                    </div>
                    <div class="grid gap-2">
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
                                <option value="" disabled selected>
                                    Select a line of business
                                </option>
                                <option
                                    v-for="lineOfBusiness in lineOfBusinesses"
                                    :key="lineOfBusiness.id"
                                    :value="lineOfBusiness.id"
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
                                :default-value="1"
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
                        Save Application
                    </Button>
                </div>
            </Form>
        </main>
    </AppLayout>
</template>
