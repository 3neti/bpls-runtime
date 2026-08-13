<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { show as assessmentShow } from '@/actions/App/Http/Controllers/Staff/PermitApplicationAssessmentController';
import { show } from '@/actions/App/Http/Controllers/Staff/AssessmentPaymentScheduleController';
import type { BreadcrumbItem } from '@/types';

type PaymentScheduleLine = {
    id: number;
    assessment_line_id: number | null;
    code: string;
    name: string;
    category: string;
    due_on: string | null;
    status: string;
    amount_cents: number;
    paid_amount_cents: number;
    line_of_business: string | null;
};

type PaymentSchedule = {
    id: number;
    sequence: number;
    status: string;
    payment_mode: string;
    due_on: string | null;
    total_amount_cents: number;
    paid_amount_cents: number;
    prepared_by: string | null;
    created_at: string | null;
    assessment: {
        id: number;
        sequence: number;
        status: string;
    };
    permit_application: {
        id: number;
        application_number: string | null;
        type: string;
        status: string;
        application_year: number;
        business_name: string;
        owner_name: string;
    };
    lines: PaymentScheduleLine[];
};

const props = defineProps<{
    paymentSchedule: PaymentSchedule;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Payment Schedule',
        href: show(props.paymentSchedule.id),
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
        <Head :title="`Payment Schedule #${paymentSchedule.sequence}`" />

        <main class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex flex-col gap-1">
                    <Button
                        as-child
                        variant="ghost"
                        size="sm"
                        class="w-fit px-0"
                    >
                        <Link :href="assessmentShow(paymentSchedule.assessment.id)">
                            <ArrowLeft />
                            Back
                        </Link>
                    </Button>
                    <h1 class="text-xl font-semibold text-foreground">
                        Payment Schedule #{{ paymentSchedule.sequence }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ paymentSchedule.permit_application.business_name }}
                        · {{ paymentSchedule.permit_application.owner_name }}
                    </p>
                </div>
                <div class="text-right">
                    <div class="text-xs text-muted-foreground uppercase">
                        Balance due
                    </div>
                    <div class="text-2xl font-semibold">
                        {{
                            money(
                                paymentSchedule.total_amount_cents -
                                    paymentSchedule.paid_amount_cents,
                            )
                        }}
                    </div>
                </div>
            </div>

            <section class="grid gap-3 md:grid-cols-4">
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Application
                    </div>
                    <div class="mt-1 font-medium">
                        {{
                            paymentSchedule.permit_application
                                .application_number ??
                            `Application #${paymentSchedule.permit_application.id}`
                        }}
                    </div>
                    <div class="text-sm text-muted-foreground capitalize">
                        {{ paymentSchedule.permit_application.type }} ·
                        {{ paymentSchedule.permit_application.application_year }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Schedule status
                    </div>
                    <div class="mt-2">
                        <Badge variant="secondary" class="capitalize">
                            {{ paymentSchedule.status.replace('_', ' ') }}
                        </Badge>
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Payment mode
                    </div>
                    <div class="mt-1 font-medium capitalize">
                        {{ paymentSchedule.payment_mode }}
                    </div>
                    <div class="text-sm text-muted-foreground">
                        {{ paymentSchedule.due_on ?? 'No due date set' }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Prepared by
                    </div>
                    <div class="mt-1 font-medium">
                        {{ paymentSchedule.prepared_by ?? 'System' }}
                    </div>
                    <div class="text-sm text-muted-foreground">
                        {{ paymentSchedule.created_at ?? 'No timestamp' }}
                    </div>
                </div>
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
                                <th class="px-4 py-3 font-medium">Code</th>
                                <th class="px-4 py-3 font-medium">Item</th>
                                <th class="px-4 py-3 font-medium">Category</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Paid
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Amount
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="line in paymentSchedule.lines"
                                :key="line.id"
                                class="border-b last:border-b-0"
                            >
                                <td
                                    class="px-4 py-3 align-top font-mono text-xs"
                                >
                                    {{ line.code }}
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="font-medium">
                                        {{ line.name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{
                                            line.line_of_business ??
                                            'Application-wide'
                                        }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top capitalize">
                                    {{ line.category }}
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <Badge variant="outline" class="capitalize">
                                        {{ line.status.replace('_', ' ') }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-right align-top">
                                    {{ money(line.paid_amount_cents) }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right align-top font-medium"
                                >
                                    {{ money(line.amount_cents) }}
                                </td>
                            </tr>
                            <tr v-if="paymentSchedule.lines.length === 0">
                                <td
                                    colspan="6"
                                    class="px-4 py-10 text-center text-muted-foreground"
                                >
                                    No payment schedule lines have been
                                    prepared.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
