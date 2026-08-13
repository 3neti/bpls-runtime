<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Banknote } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { show as assessmentShow } from '@/actions/App/Http/Controllers/Staff/PermitApplicationAssessmentController';
import { show as paymentScheduleShow } from '@/actions/App/Http/Controllers/Staff/AssessmentPaymentScheduleController';
import { store as collectionStore } from '@/actions/App/Http/Controllers/Staff/PaymentScheduleCollectionController';
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
    collections: TreasuryCollection[];
};

type TreasuryCollection = {
    id: number;
    status: string;
    channel: string;
    method: string;
    amount_cents: number;
    payer_name: string | null;
    reference_number: string | null;
    received_at: string;
    received_by: string | null;
    allocations: CollectionAllocation[];
};

type CollectionAllocation = {
    id: number;
    payment_schedule_line_id: number;
    code: string;
    name: string;
    amount_cents: number;
};

type Option = {
    label: string;
    value: string;
};

const props = defineProps<{
    paymentSchedule: PaymentSchedule;
    collectionMethods: Option[];
    can: {
        record_collections: boolean;
        view_collections: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Payment Schedule',
        href: paymentScheduleShow(props.paymentSchedule.id),
    },
];

const balanceDueCents = computed(
    () =>
        props.paymentSchedule.total_amount_cents -
        props.paymentSchedule.paid_amount_cents,
);

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
                        {{ money(balanceDueCents) }}
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
                v-if="can.record_collections && balanceDueCents > 0"
                class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
            >
                <div class="mb-4 flex items-center gap-2">
                    <Banknote class="size-4 text-muted-foreground" />
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">
                            Record Collection
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Over-the-counter collection only. Receipt issuance is
                            not part of this action.
                        </p>
                    </div>
                </div>

                <Form
                    v-bind="collectionStore.form(paymentSchedule.id)"
                    v-slot="{ errors, processing }"
                    class="grid gap-4 md:grid-cols-5"
                >
                    <div class="grid gap-2">
                        <Label for="amount_pesos">Amount</Label>
                        <Input
                            id="amount_pesos"
                            name="amount_pesos"
                            type="number"
                            min="0.01"
                            step="0.01"
                            :max="(balanceDueCents / 100).toFixed(2)"
                            required
                        />
                        <InputError :message="errors.amount_pesos" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="method">Method</Label>
                        <select
                            id="method"
                            name="method"
                            required
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option
                                v-for="method in collectionMethods"
                                :key="method.value"
                                :value="method.value"
                            >
                                {{ method.label }}
                            </option>
                        </select>
                        <InputError :message="errors.method" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="payer_name">Payer</Label>
                        <Input id="payer_name" name="payer_name" />
                        <InputError :message="errors.payer_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="reference_number">Reference</Label>
                        <Input id="reference_number" name="reference_number" />
                        <InputError :message="errors.reference_number" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="remarks">Remarks</Label>
                        <Input id="remarks" name="remarks" />
                        <InputError :message="errors.remarks" />
                    </div>
                    <div class="md:col-span-5">
                        <Button type="submit" :disabled="processing">
                            <Banknote />
                            {{ processing ? 'Recording...' : 'Record Collection' }}
                        </Button>
                    </div>
                </Form>
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

            <section
                v-if="can.view_collections || can.record_collections"
                class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background dark:border-sidebar-border"
            >
                <div class="border-b px-4 py-3">
                    <h2 class="text-sm font-semibold text-foreground">
                        Collection History
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px] text-sm">
                        <thead
                            class="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3 font-medium">Date</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium">Method</th>
                                <th class="px-4 py-3 font-medium">Payer</th>
                                <th class="px-4 py-3 font-medium">Reference</th>
                                <th class="px-4 py-3 font-medium">Received by</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Amount
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="collection in paymentSchedule.collections"
                                :key="collection.id"
                                class="border-b last:border-b-0"
                            >
                                <td class="px-4 py-3 align-top">
                                    {{ collection.received_at }}
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <Badge variant="outline" class="capitalize">
                                        {{ collection.status.replace('_', ' ') }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 align-top capitalize">
                                    {{ collection.method.replace('_', ' ') }}
                                </td>
                                <td class="px-4 py-3 align-top">
                                    {{ collection.payer_name ?? 'Not recorded' }}
                                </td>
                                <td class="px-4 py-3 align-top">
                                    {{
                                        collection.reference_number ??
                                        'Not recorded'
                                    }}
                                </td>
                                <td class="px-4 py-3 align-top">
                                    {{ collection.received_by ?? 'System' }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right align-top font-medium"
                                >
                                    {{ money(collection.amount_cents) }}
                                </td>
                            </tr>
                            <tr v-if="paymentSchedule.collections.length === 0">
                                <td
                                    colspan="7"
                                    class="px-4 py-10 text-center text-muted-foreground"
                                >
                                    No collections have been recorded.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
