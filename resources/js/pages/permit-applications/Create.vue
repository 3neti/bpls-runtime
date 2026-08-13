<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Save } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { index, store } from '@/actions/App/Http/Controllers/Staff/PermitApplicationController';
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

defineProps<{
    currentApplicationYear: number;
    applicationTypes: Option[];
    lineOfBusinesses: LineOfBusiness[];
}>();

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
                    class="grid gap-4 rounded-lg border border-sidebar-border/70 bg-background p-4 md:grid-cols-2 dark:border-sidebar-border"
                >
                    <div class="md:col-span-2">
                        <h2 class="text-sm font-semibold text-foreground">
                            Business
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
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
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
                    <div class="grid gap-2">
                        <Label for="line_of_business_id">
                            Line of business
                        </Label>
                        <select
                            id="line_of_business_id"
                            name="line_of_business_id"
                            required
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option
                                v-for="lineOfBusiness in lineOfBusinesses"
                                :key="lineOfBusiness.id"
                                :value="lineOfBusiness.id"
                            >
                                {{ lineOfBusiness.name }}
                            </option>
                        </select>
                        <InputError :message="errors.line_of_business_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="declared_gross_sales_pesos">
                            Declared gross sales
                        </Label>
                        <Input
                            id="declared_gross_sales_pesos"
                            name="declared_gross_sales_pesos"
                            type="number"
                            min="0"
                            step="0.01"
                            required
                        />
                        <InputError
                            :message="errors.declared_gross_sales_pesos"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="capital_investment_pesos">
                            Capital investment
                        </Label>
                        <Input
                            id="capital_investment_pesos"
                            name="capital_investment_pesos"
                            type="number"
                            min="0"
                            step="0.01"
                            required
                        />
                        <InputError
                            :message="errors.capital_investment_pesos"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="quantity">Quantity</Label>
                        <Input
                            id="quantity"
                            name="quantity"
                            type="number"
                            min="1"
                            :default-value="1"
                            required
                        />
                        <InputError :message="errors.quantity" />
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
