<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Calculator, ListChecks } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { index, show } from '@/actions/App/Http/Controllers/Staff/PermitApplicationController';
import { show as showAssessment, store as assess } from '@/actions/App/Http/Controllers/Staff/PermitApplicationAssessmentController';
import type { BreadcrumbItem } from '@/types';

type PermitApplication = {
    id: number;
    application_number: string | null;
    type: string;
    status: string;
    application_year: number;
    submitted_at: string | null;
    business: {
        name: string;
        trade_name: string | null;
        registration_number: string | null;
        address: string | null;
        barangay: string | null;
        owner: {
            name: string;
            email: string | null;
            phone: string | null;
            address: string | null;
        };
    };
    lines: {
        id: number;
        line_of_business: {
            name: string | null;
            code: string | null;
        };
        declared_gross_sales_cents: number;
        capital_investment_cents: number;
        quantity: number;
    }[];
    latest_assessment: {
        id: number;
        sequence: number;
        status: string;
        total_amount_cents: number;
        assessed_at: string | null;
    } | null;
};

const props = defineProps<{
    permitApplication: PermitApplication;
    can: {
        assess_permit_applications: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Permit Applications',
        href: index(),
    },
    {
        title:
            props.permitApplication.application_number ??
            `Application #${props.permitApplication.id}`,
        href: show(props.permitApplication.id),
    },
];

function money(amountCents: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amountCents / 100);
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Permit Application" />

        <main class="flex h-full flex-1 flex-col gap-4 p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        {{
                            permitApplication.application_number ??
                            `Application #${permitApplication.id}`
                        }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ permitApplication.business.name }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button
                        v-if="permitApplication.latest_assessment"
                        as-child
                        variant="outline"
                    >
                        <Link
                            :href="
                                showAssessment(
                                    permitApplication.latest_assessment.id,
                                )
                            "
                        >
                            <ListChecks />
                            Assessment
                        </Link>
                    </Button>
                    <Link
                        v-if="can.assess_permit_applications"
                        :href="assess(permitApplication.id)"
                        method="post"
                        as="button"
                        :class="buttonVariants()"
                    >
                        <Calculator />
                        Assess
                    </Link>
                </div>
            </section>

            <section
                class="grid gap-4 rounded-lg border border-sidebar-border/70 bg-background p-4 md:grid-cols-3 dark:border-sidebar-border"
            >
                <div>
                    <div class="text-xs text-muted-foreground">Type</div>
                    <div class="capitalize">{{ permitApplication.type }}</div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Status</div>
                    <Badge variant="secondary" class="capitalize">
                        {{ permitApplication.status.replace('_', ' ') }}
                    </Badge>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Year</div>
                    <div>{{ permitApplication.application_year }}</div>
                </div>
            </section>

            <section
                class="grid gap-4 rounded-lg border border-sidebar-border/70 bg-background p-4 md:grid-cols-2 dark:border-sidebar-border"
            >
                <div>
                    <h2 class="mb-3 text-sm font-semibold text-foreground">
                        Business
                    </h2>
                    <dl class="grid gap-2 text-sm">
                        <div>
                            <dt class="text-xs text-muted-foreground">Name</dt>
                            <dd>{{ permitApplication.business.name }}</dd>
                        </div>
                        <div v-if="permitApplication.business.trade_name">
                            <dt class="text-xs text-muted-foreground">
                                Trade name
                            </dt>
                            <dd>{{ permitApplication.business.trade_name }}</dd>
                        </div>
                        <div
                            v-if="
                                permitApplication.business.registration_number
                            "
                        >
                            <dt class="text-xs text-muted-foreground">
                                Registration
                            </dt>
                            <dd>
                                {{
                                    permitApplication.business
                                        .registration_number
                                }}
                            </dd>
                        </div>
                        <div v-if="permitApplication.business.barangay">
                            <dt class="text-xs text-muted-foreground">
                                Barangay
                            </dt>
                            <dd>{{ permitApplication.business.barangay }}</dd>
                        </div>
                        <div v-if="permitApplication.business.address">
                            <dt class="text-xs text-muted-foreground">
                                Address
                            </dt>
                            <dd>{{ permitApplication.business.address }}</dd>
                        </div>
                    </dl>
                </div>
                <div>
                    <h2 class="mb-3 text-sm font-semibold text-foreground">
                        Owner
                    </h2>
                    <dl class="grid gap-2 text-sm">
                        <div>
                            <dt class="text-xs text-muted-foreground">Name</dt>
                            <dd>{{ permitApplication.business.owner.name }}</dd>
                        </div>
                        <div v-if="permitApplication.business.owner.email">
                            <dt class="text-xs text-muted-foreground">Email</dt>
                            <dd>
                                {{ permitApplication.business.owner.email }}
                            </dd>
                        </div>
                        <div v-if="permitApplication.business.owner.phone">
                            <dt class="text-xs text-muted-foreground">Phone</dt>
                            <dd>
                                {{ permitApplication.business.owner.phone }}
                            </dd>
                        </div>
                        <div v-if="permitApplication.business.owner.address">
                            <dt class="text-xs text-muted-foreground">
                                Address
                            </dt>
                            <dd>
                                {{ permitApplication.business.owner.address }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section
                class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background dark:border-sidebar-border"
            >
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead
                            class="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3 font-medium">
                                    Line of business
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Gross sales
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Capital
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Quantity
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="line in permitApplication.lines"
                                :key="line.id"
                                class="border-b last:border-b-0"
                            >
                                <td class="px-4 py-3">
                                    <div>
                                        {{
                                            line.line_of_business.name ??
                                            'Unclassified'
                                        }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ line.line_of_business.code }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{
                                        money(
                                            line.declared_gross_sales_cents,
                                        )
                                    }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{ money(line.capital_investment_cents) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{ line.quantity }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
