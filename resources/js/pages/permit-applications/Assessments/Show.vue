<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CreditCard, FileText, ReceiptText } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    index as assessmentIndex,
    pdf as assessmentPdf,
} from '@/actions/App/Http/Controllers/Staff/PermitApplicationAssessmentController';
import {
    show as paymentScheduleShow,
    store as paymentScheduleStore,
} from '@/actions/App/Http/Controllers/Staff/AssessmentPaymentScheduleController';
import type { BreadcrumbItem } from '@/types';

type AssessmentLine = {
    id: number;
    code: string;
    name: string;
    category: string;
    calculation_type: string;
    basis: string;
    basis_amount_cents: number;
    amount_cents: number;
    line_of_business: string | null;
    legal_basis: string | null;
};

type Assessment = {
    id: number;
    sequence: number;
    status: string;
    assessed_at: string | null;
    assessed_by: string | null;
    total_amount_cents: number;
    permit_application: {
        id: number;
        application_number: string | null;
        type: string;
        status: string;
        application_year: number;
        business_name: string;
        owner_name: string;
    };
    lines: AssessmentLine[];
    latest_payment_schedule: {
        id: number;
        sequence: number;
        status: string;
        payment_mode: string;
        total_amount_cents: number;
        paid_amount_cents: number;
        created_at: string | null;
    } | null;
};

const props = defineProps<{
    assessment: Assessment;
    can: {
        prepare_payment_schedule: boolean;
        view_payment_schedules: boolean;
        view_assessment_documents: boolean;
    };
    assessmentDocumentGaps: string[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Permit Assessments',
        href: assessmentIndex(),
    },
    {
        title: `Assessment #${props.assessment.sequence}`,
        href: '#',
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
        <Head :title="`Assessment #${assessment.sequence}`" />

        <main class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex flex-col gap-1">
                    <Button
                        as-child
                        variant="ghost"
                        size="sm"
                        class="w-fit px-0"
                    >
                        <Link :href="assessmentIndex()">
                            <ArrowLeft />
                            Back
                        </Link>
                    </Button>
                    <h1 class="text-xl font-semibold text-foreground">
                        Assessment #{{ assessment.sequence }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ assessment.permit_application.business_name }} ·
                        {{ assessment.permit_application.owner_name }}
                    </p>
                </div>
                <div class="text-right">
                    <div class="text-xs text-muted-foreground uppercase">
                        Total assessed
                    </div>
                    <div class="text-2xl font-semibold">
                        {{ money(assessment.total_amount_cents) }}
                    </div>
                    <div class="mt-3 flex justify-end gap-2">
                        <Button
                            v-if="can.view_assessment_documents"
                            as-child
                            variant="outline"
                            size="sm"
                        >
                            <a :href="assessmentPdf.url(assessment.id)" target="_blank">
                                <FileText />
                                PDF
                            </a>
                        </Button>
                        <Button
                            v-if="
                                assessment.latest_payment_schedule &&
                                can.view_payment_schedules
                            "
                            as-child
                            variant="outline"
                            size="sm"
                        >
                            <Link
                                :href="
                                    paymentScheduleShow(
                                        assessment.latest_payment_schedule.id,
                                    )
                                "
                            >
                                <ReceiptText />
                                Payment Schedule
                            </Link>
                        </Button>
                        <Link
                            v-else-if="can.prepare_payment_schedule"
                            :href="paymentScheduleStore(assessment.id)"
                            method="post"
                            as="button"
                            :class="buttonVariants({ size: 'sm' })"
                        >
                            <CreditCard />
                            Prepare Payment
                        </Link>
                    </div>
                </div>
            </div>

            <section class="grid gap-3 md:grid-cols-3">
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Application
                    </div>
                    <div class="mt-1 font-medium">
                        {{
                            assessment.permit_application.application_number ??
                            `Application #${assessment.permit_application.id}`
                        }}
                    </div>
                    <div class="text-sm text-muted-foreground capitalize">
                        {{ assessment.permit_application.type }} ·
                        {{ assessment.permit_application.application_year }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Status
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <Badge variant="secondary" class="capitalize">{{
                            assessment.status
                        }}</Badge>
                        <Badge variant="outline" class="capitalize">
                            {{
                                assessment.permit_application.status.replace(
                                    '_',
                                    ' ',
                                )
                            }}
                        </Badge>
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Assessed by
                    </div>
                    <div class="mt-1 font-medium">
                        {{ assessment.assessed_by ?? 'System' }}
                    </div>
                    <div class="text-sm text-muted-foreground">
                        {{ assessment.assessed_at ?? 'No timestamp' }}
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
                                <th class="px-4 py-3 font-medium">Fee / Tax</th>
                                <th class="px-4 py-3 font-medium">Category</th>
                                <th class="px-4 py-3 font-medium">Basis</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Basis amount
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Amount
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="line in assessment.lines"
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
                                    <div class="capitalize">
                                        {{ line.calculation_type }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ line.basis.replaceAll('_', ' ') }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right align-top">
                                    {{ money(line.basis_amount_cents) }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right align-top font-medium"
                                >
                                    {{ money(line.amount_cents) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
            >
                <h2 class="mb-2 text-sm font-semibold text-foreground">
                    Assessment document gaps
                </h2>
                <ul class="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                    <li v-for="gap in assessmentDocumentGaps" :key="gap">
                        {{ gap }}
                    </li>
                </ul>
            </section>
        </main>
    </AppLayout>
</template>
