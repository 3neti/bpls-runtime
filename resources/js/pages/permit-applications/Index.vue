<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Calculator, Eye, Plus } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { create, index, show } from '@/actions/App/Http/Controllers/Staff/PermitApplicationController';
import { store as assess } from '@/actions/App/Http/Controllers/Staff/PermitApplicationAssessmentController';
import type { BreadcrumbItem } from '@/types';

type PermitApplicationRow = {
    id: number;
    application_number: string | null;
    type: string;
    status: string;
    application_year: number;
    business: {
        name: string;
        owner: {
            name: string;
        };
    };
    lines: {
        id: number;
        line_of_business: {
            name: string | null;
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
    can_continue: boolean;
};

defineProps<{
    permitApplications: {
        data: PermitApplicationRow[];
    };
    can: {
        create_permit_applications: boolean;
        assess_permit_applications: boolean;
        update_permit_application_status: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Permit Applications',
        href: index(),
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
        <Head title="Permit Applications" />

        <main class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <section class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        Permit Applications
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Staff intake and review surface for business permit
                        applications.
                    </p>
                </div>
                <Button v-if="can.create_permit_applications" as-child>
                    <Link :href="create()">
                        <Plus />
                        New Application
                    </Link>
                </Button>
            </section>

            <section
                class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background dark:border-sidebar-border"
            >
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[920px] text-sm">
                        <thead
                            class="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3 font-medium">
                                    Application
                                </th>
                                <th class="px-4 py-3 font-medium">Business</th>
                                <th class="px-4 py-3 font-medium">Type</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Activity
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Assessment
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="permitApplication in permitApplications.data"
                                :key="permitApplication.id"
                                class="border-b last:border-b-0"
                            >
                                <td class="px-4 py-3 align-top">
                                    <div class="font-medium">
                                        {{
                                            permitApplication.application_number ??
                                            `Application #${permitApplication.id}`
                                        }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ permitApplication.application_year }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="font-medium">
                                        {{ permitApplication.business.name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{
                                            permitApplication.business.owner
                                                .name
                                        }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top capitalize">
                                    {{ permitApplication.type }}
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <Badge
                                        variant="secondary"
                                        class="capitalize"
                                    >
                                        {{
                                            permitApplication.status.replace(
                                                '_',
                                                ' ',
                                            )
                                        }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-right align-top">
                                    <div>
                                        {{ permitApplication.lines.length }}
                                        line
                                    </div>
                                    <div
                                        v-if="permitApplication.lines[0]"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{
                                            money(
                                                permitApplication.lines[0]
                                                    .declared_gross_sales_cents,
                                            )
                                        }}
                                        gross
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right align-top">
                                    <div
                                        v-if="
                                            permitApplication.latest_assessment
                                        "
                                    >
                                        <div class="font-medium">
                                            {{
                                                money(
                                                    permitApplication
                                                        .latest_assessment
                                                        .total_amount_cents,
                                                )
                                            }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            Sequence
                                            {{
                                                permitApplication
                                                    .latest_assessment.sequence
                                            }}
                                        </div>
                                    </div>
                                    <span v-else class="text-muted-foreground"
                                        >Not assessed</span
                                    >
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            as-child
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link
                                                :href="
                                                    show(permitApplication.id)
                                                "
                                            >
                                                <Eye />
                                                View
                                            </Link>
                                        </Button>
                                        <Link
                                            v-if="
                                                can.assess_permit_applications &&
                                                permitApplication.can_continue
                                            "
                                            :href="assess(permitApplication.id)"
                                            method="post"
                                            as="button"
                                            :class="
                                                buttonVariants({ size: 'sm' })
                                            "
                                        >
                                            <Calculator />
                                            Assess
                                        </Link>
                                        <span
                                            v-if="
                                                !permitApplication.can_continue
                                            "
                                            class="text-xs text-muted-foreground"
                                        >
                                            Terminal
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="permitApplications.data.length === 0">
                                <td
                                    colspan="7"
                                    class="px-4 py-10 text-center text-muted-foreground"
                                >
                                    No permit applications have been recorded.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
