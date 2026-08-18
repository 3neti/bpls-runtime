<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Ban, FileText, Printer } from '@lucide/vue';
import { show as paymentScheduleShow } from '@/actions/App/Http/Controllers/Staff/AssessmentPaymentScheduleController';
import {
    pdf as receiptPdf,
    show as receiptShow,
    voidReceipt as receiptVoidReceipt,
} from '@/actions/App/Http/Controllers/Staff/ReceiptController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AuthorityBoundaryPanel from '@/components/workflow/AuthorityBoundaryPanel.vue';
import WorkflowStageSummary from '@/components/workflow/WorkflowStageSummary.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type ReceiptAllocation = {
    id: number;
    code: string;
    name: string;
    category: string;
    line_of_business: string | null;
    amount_cents: number;
};

type Receipt = {
    id: number;
    status: string;
    numbering_authority: string;
    receipt_number: string;
    amount_cents: number;
    issued_at: string;
    issued_by: string | null;
    remarks: string | null;
    source_snapshot: Record<string, unknown>;
    void_boundary: {
        reference: string;
        status: string;
        can_void: boolean;
        receipt_status: string;
        collection_status: string;
        policy_note: string;
    };
    collection: {
        id: number;
        status: string;
        channel: string;
        method: string;
        amount_cents: number;
        payer_name: string | null;
        reference_number: string | null;
        received_at: string;
        received_by: string | null;
    };
    payment_schedule: {
        id: number;
        sequence: number;
        status: string;
        payment_mode: string;
    };
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
    };
    business: {
        id: number;
        name: string;
        trade_name: string | null;
        registration_number: string | null;
        address: string | null;
        barangay: string | null;
        owner: {
            id: number;
            name: string;
            email: string | null;
            phone: string | null;
            address: string | null;
        };
    };
    allocations: ReceiptAllocation[];
};

const props = defineProps<{
    receipt: Receipt;
    policyGaps: string[];
    can: {
        void_receipts: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Receipt',
        href: receiptShow(props.receipt.id),
    },
];

function money(amountCents: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amountCents / 100);
}

function printReceipt(): void {
    window.print();
}

function label(value: string): string {
    return value.replaceAll('_', ' ');
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Receipt ${receipt.receipt_number}`" />

        <main
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4 print:block print:overflow-visible print:bg-white print:p-0 print:text-black"
        >
            <div
                class="flex flex-wrap items-start justify-between gap-3 print:hidden"
            >
                <div class="flex flex-col gap-1">
                    <Button
                        as-child
                        variant="ghost"
                        size="sm"
                        class="w-fit px-0"
                    >
                        <Link
                            :href="
                                paymentScheduleShow(receipt.payment_schedule.id)
                            "
                        >
                            <ArrowLeft />
                            Back
                        </Link>
                    </Button>
                    <h1 class="text-xl font-semibold text-foreground">
                        Receipt {{ receipt.receipt_number }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ receipt.business.name }} ·
                        {{ receipt.business.owner.name }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Form
                        v-if="can.void_receipts"
                        v-bind="receiptVoidReceipt.form(receipt.id)"
                        #default="{ errors }"
                    >
                        <Button
                            type="submit"
                            variant="outline"
                            disabled
                            title="Receipt voiding policy is unresolved"
                        >
                            <Ban />
                            Void unavailable
                        </Button>
                        <p
                            v-if="errors.receipt_policy"
                            class="mt-1 text-xs text-destructive"
                        >
                            {{ errors.receipt_policy }}
                        </p>
                    </Form>
                    <Button as-child variant="outline">
                        <a :href="receiptPdf.url(receipt.id)" target="_blank">
                            <FileText />
                            PDF
                        </a>
                    </Button>
                    <Button type="button" @click="printReceipt">
                        <Printer />
                        Print
                    </Button>
                </div>
            </div>

            <WorkflowStageSummary
                class="print:hidden"
                eyebrow="Current receipt evidence"
                :title="money(receipt.amount_cents)"
                description="This receipt records a collection separately from its source payment schedule and application."
                :items="[
                    {
                        label: 'Receipt status',
                        value: label(receipt.status),
                    },
                    {
                        label: 'Receipt number',
                        value: receipt.receipt_number,
                        detail: `${label(receipt.numbering_authority)} numbering`,
                    },
                    {
                        label: 'Collection status',
                        value: label(receipt.collection.status),
                        detail: label(receipt.collection.channel),
                    },
                    {
                        label: 'Current task',
                        value: 'Review, print, or open PDF',
                        detail: 'Void and reversal remain unavailable.',
                    },
                ]"
            />

            <section
                class="rounded-lg border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border print:rounded-none print:border-black print:bg-white print:p-0"
            >
                <div
                    class="flex flex-wrap items-start justify-between gap-4 border-b pb-5 print:border-black"
                >
                    <div>
                        <div
                            class="text-xs font-medium text-muted-foreground uppercase print:text-black"
                        >
                            Municipality of Ipil
                        </div>
                        <h2
                            class="mt-1 text-2xl font-semibold text-foreground print:text-black"
                        >
                            Receipt {{ receipt.receipt_number }}
                        </h2>
                        <p
                            class="text-sm text-muted-foreground print:text-black"
                        >
                            Business Permit and Licensing System
                        </p>
                    </div>
                    <div class="text-left sm:text-right">
                        <Badge
                            variant="secondary"
                            class="border-black capitalize print:border"
                        >
                            {{ receipt.status.replace('_', ' ') }}
                        </Badge>
                        <div
                            class="mt-2 text-2xl font-semibold print:text-black"
                        >
                            {{ money(receipt.amount_cents) }}
                        </div>
                        <div
                            class="text-xs text-muted-foreground print:text-black"
                        >
                            {{ receipt.numbering_authority }} numbering
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 py-6 md:grid-cols-3 print:grid-cols-3">
                    <div class="space-y-1">
                        <div
                            class="text-xs font-medium text-muted-foreground uppercase print:text-black"
                        >
                            Issued
                        </div>
                        <div class="text-sm font-medium">
                            {{ receipt.issued_at }}
                        </div>
                        <div
                            class="text-sm text-muted-foreground print:text-black"
                        >
                            By {{ receipt.issued_by ?? 'System' }}
                        </div>
                    </div>
                    <div class="space-y-1">
                        <div
                            class="text-xs font-medium text-muted-foreground uppercase print:text-black"
                        >
                            Collected
                        </div>
                        <div class="text-sm font-medium">
                            {{ receipt.collection.received_at }}
                        </div>
                        <div
                            class="text-sm text-muted-foreground print:text-black"
                        >
                            {{ receipt.collection.method.replace('_', ' ') }}
                            · {{ receipt.collection.received_by ?? 'System' }}
                        </div>
                    </div>
                    <div class="space-y-1">
                        <div
                            class="text-xs font-medium text-muted-foreground uppercase print:text-black"
                        >
                            Application
                        </div>
                        <div class="text-sm font-medium">
                            {{
                                receipt.permit_application.application_number ??
                                `Application #${receipt.permit_application.id}`
                            }}
                        </div>
                        <div
                            class="text-sm text-muted-foreground print:text-black"
                        >
                            {{ receipt.permit_application.type }} ·
                            {{ receipt.permit_application.application_year }}
                        </div>
                    </div>
                </div>

                <div
                    class="grid gap-6 border-t py-6 md:grid-cols-2 print:grid-cols-2 print:border-black"
                >
                    <div class="space-y-1">
                        <div
                            class="text-xs font-medium text-muted-foreground uppercase print:text-black"
                        >
                            Business
                        </div>
                        <div class="text-sm font-medium">
                            {{ receipt.business.name }}
                        </div>
                        <div
                            class="text-sm text-muted-foreground print:text-black"
                        >
                            {{ receipt.business.trade_name ?? 'No trade name' }}
                        </div>
                        <div
                            class="text-sm text-muted-foreground print:text-black"
                        >
                            {{
                                receipt.business.registration_number ??
                                'No registration number'
                            }}
                        </div>
                        <div
                            class="text-sm text-muted-foreground print:text-black"
                        >
                            {{ receipt.business.address ?? 'No address' }}
                            <span v-if="receipt.business.barangay">
                                · {{ receipt.business.barangay }}
                            </span>
                        </div>
                    </div>
                    <div class="space-y-1">
                        <div
                            class="text-xs font-medium text-muted-foreground uppercase print:text-black"
                        >
                            Payer / Owner
                        </div>
                        <div class="text-sm font-medium">
                            {{
                                receipt.collection.payer_name ??
                                receipt.business.owner.name
                            }}
                        </div>
                        <div
                            class="text-sm text-muted-foreground print:text-black"
                        >
                            Owner: {{ receipt.business.owner.name }}
                        </div>
                        <div
                            class="text-sm text-muted-foreground print:text-black"
                        >
                            {{ receipt.business.owner.email ?? 'No email' }}
                            <span v-if="receipt.business.owner.phone">
                                · {{ receipt.business.owner.phone }}
                            </span>
                        </div>
                        <div
                            class="text-sm text-muted-foreground print:text-black"
                        >
                            Reference:
                            {{
                                receipt.collection.reference_number ??
                                'Not recorded'
                            }}
                        </div>
                    </div>
                </div>

                <div
                    class="overflow-x-auto border-t pt-6 print:overflow-visible print:border-black"
                >
                    <table class="w-full min-w-[680px] text-sm print:min-w-0">
                        <thead
                            class="border-b text-left text-xs text-muted-foreground uppercase print:border-black print:text-black"
                        >
                            <tr>
                                <th class="py-3 pr-4 font-medium">Code</th>
                                <th class="px-4 py-3 font-medium">Item</th>
                                <th class="px-4 py-3 font-medium">Category</th>
                                <th class="px-4 py-3 font-medium">
                                    Business line
                                </th>
                                <th class="py-3 pl-4 text-right font-medium">
                                    Amount
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="allocation in receipt.allocations"
                                :key="allocation.id"
                                class="border-b last:border-b-0 print:border-black"
                            >
                                <td
                                    class="py-3 pr-4 align-top font-mono text-xs"
                                >
                                    {{ allocation.code }}
                                </td>
                                <td class="px-4 py-3 align-top">
                                    {{ allocation.name }}
                                </td>
                                <td class="px-4 py-3 align-top capitalize">
                                    {{ allocation.category }}
                                </td>
                                <td class="px-4 py-3 align-top">
                                    {{
                                        allocation.line_of_business ??
                                        'Application-wide'
                                    }}
                                </td>
                                <td
                                    class="py-3 pl-4 text-right align-top font-medium"
                                >
                                    {{ money(allocation.amount_cents) }}
                                </td>
                            </tr>
                            <tr v-if="receipt.allocations.length === 0">
                                <td
                                    colspan="5"
                                    class="py-10 text-center text-muted-foreground print:text-black"
                                >
                                    No receipt allocations were recorded.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t print:border-black">
                                <td
                                    colspan="4"
                                    class="py-3 pr-4 text-right font-medium"
                                >
                                    Total
                                </td>
                                <td class="py-3 pl-4 text-right font-semibold">
                                    {{ money(receipt.amount_cents) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div
                    v-if="receipt.remarks"
                    class="mt-6 border-t pt-4 print:border-black"
                >
                    <div
                        class="text-xs font-medium text-muted-foreground uppercase print:text-black"
                    >
                        Remarks
                    </div>
                    <p class="mt-1 text-sm">{{ receipt.remarks }}</p>
                </div>

                <AuthorityBoundaryPanel
                    class="mt-6 print:hidden"
                    title="Void and reversal remain unavailable"
                    :status="receipt.void_boundary.status"
                    :statement="receipt.void_boundary.policy_note"
                    :facts="[
                        {
                            label: 'Boundary reference',
                            value: receipt.void_boundary.reference,
                        },
                        {
                            label: 'Can void',
                            value: receipt.void_boundary.can_void
                                ? 'Yes'
                                : 'No',
                        },
                        {
                            label: 'Receipt remains',
                            value: label(receipt.void_boundary.receipt_status),
                        },
                        {
                            label: 'Collection remains',
                            value: label(
                                receipt.void_boundary.collection_status,
                            ),
                        },
                    ]"
                />

                <div class="mt-6 border-t pt-4 print:hidden">
                    <div
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Policy gaps
                    </div>
                    <ul
                        class="mt-2 list-disc space-y-1 pl-5 text-sm text-muted-foreground"
                    >
                        <li v-for="gap in policyGaps" :key="gap">
                            {{ gap }}
                        </li>
                    </ul>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
