<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download, Search, X } from '@lucide/vue';
import { ref } from 'vue';
import {
    download,
    index,
} from '@/actions/App/Http/Controllers/Staff/PaymentSummaryReportController';
import ReportFamilyBanner from '@/components/reports/ReportFamilyBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type PaymentSummaryRow = {
    payment_schedule_id: number;
    schedule_sequence: number;
    schedule_status: string;
    payment_mode: string;
    due_on: string | null;
    application_id: number;
    application_number: string | null;
    application_type: string;
    application_status: string;
    business_name: string;
    trade_name: string | null;
    owner_name: string;
    total_amount_cents: number;
    paid_amount_cents: number;
    outstanding_amount_cents: number;
    collection_amount_cents: number;
    collection_difference_cents: number;
    collection_count: number;
    receipted_amount_cents: number;
    receipted_count: number;
    pending_receipt_amount_cents: number;
    pending_receipt_count: number;
    collection_methods: string[];
    latest_receipt_number: string | null;
};

type Option = { label: string; value: string };

const props = defineProps<{
    filters: {
        year: number;
        type: string | null;
        status: string | null;
        q: string | null;
    };
    summary: {
        row_count: number;
        business_count: number;
        pending_count: number;
        partially_paid_count: number;
        paid_count: number;
        voided_count: number;
        total_amount_cents: number;
        paid_amount_cents: number;
        outstanding_amount_cents: number;
        receipted_amount_cents: number;
        pending_receipt_amount_cents: number;
        date_basis: string;
        grain: string;
        scope: string;
        policy_note: string;
    };
    rows: PaymentSummaryRow[];
    types: Option[];
    statuses: Option[];
}>();

const year = ref(String(props.filters.year));
const type = ref(props.filters.type ?? '');
const status = ref(props.filters.status ?? '');
const search = ref(props.filters.q ?? '');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Payment Summary', href: index() },
];

function query(): Record<string, string | undefined> {
    return {
        year: year.value || undefined,
        type: type.value || undefined,
        status: status.value || undefined,
        q: search.value || undefined,
    };
}

function applyFilters(): void {
    router.get(
        index.url({ query: query() }),
        {},
        {
            preserveState: true,
            replace: true,
        },
    );
}

function clearFilters(): void {
    year.value = '';
    type.value = '';
    status.value = '';
    search.value = '';
    router.get(index.url(), {}, { preserveState: true, replace: true });
}

function money(amountCents: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amountCents / 100);
}

function label(value: string | null): string {
    return value ? value.replaceAll('_', ' ') : '-';
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Payment Summary" />

        <main class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        Payment Summary
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Schedule-level payment, collection, and receipt
                        evidence.
                    </p>
                </div>
                <Button as-child variant="outline">
                    <a :href="download.url({ query: query() })">
                        <Download />
                        Export CSV
                    </a>
                </Button>
            </section>

            <ReportFamilyBanner family="management" availability="working" />

            <form
                class="grid gap-3 rounded-lg border border-sidebar-border/70 bg-background p-4 md:grid-cols-2 xl:grid-cols-[10rem_13rem_13rem_minmax(16rem,1fr)_auto] xl:items-end dark:border-sidebar-border"
                @submit.prevent="applyFilters"
            >
                <div class="grid gap-2">
                    <label
                        for="payment_summary_year"
                        class="text-xs font-medium text-muted-foreground uppercase"
                        >Year</label
                    >
                    <Input
                        id="payment_summary_year"
                        v-model="year"
                        name="year"
                        type="number"
                        min="2000"
                        max="2100"
                    />
                </div>
                <div class="grid gap-2">
                    <label
                        for="payment_summary_type"
                        class="text-xs font-medium text-muted-foreground uppercase"
                        >Type</label
                    >
                    <select
                        id="payment_summary_type"
                        v-model="type"
                        name="type"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="">All types</option>
                        <option
                            v-for="option in types"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <label
                        for="payment_summary_status"
                        class="text-xs font-medium text-muted-foreground uppercase"
                        >Status</label
                    >
                    <select
                        id="payment_summary_status"
                        v-model="status"
                        name="status"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="">All statuses</option>
                        <option
                            v-for="option in statuses"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <label
                        for="payment_summary_search"
                        class="text-xs font-medium text-muted-foreground uppercase"
                        >Search</label
                    >
                    <Input
                        id="payment_summary_search"
                        v-model="search"
                        name="q"
                        type="search"
                        placeholder="Business, owner, registration, application"
                    />
                </div>
                <div class="flex gap-2 md:col-span-2 xl:col-span-1">
                    <Button type="submit"><Search />Apply</Button>
                    <Button
                        type="button"
                        variant="outline"
                        @click="clearFilters"
                        ><X />Clear</Button
                    >
                </div>
            </form>

            <section
                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5"
                aria-label="Payment summary totals"
            >
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Schedules
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ summary.row_count }}
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{ summary.paid_count }} paid ·
                        {{ summary.partially_paid_count }} partial
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Assessed
                    </div>
                    <div class="mt-2 text-xl font-semibold">
                        {{ money(summary.total_amount_cents) }}
                    </div>
                    <div
                        class="mt-1 text-[0.65rem] font-medium text-amber-700 uppercase dark:text-amber-300"
                    >
                        Preview · Sample Data
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Paid
                    </div>
                    <div class="mt-2 text-xl font-semibold">
                        {{ money(summary.paid_amount_cents) }}
                    </div>
                    <div
                        class="mt-1 text-[0.65rem] font-medium text-amber-700 uppercase dark:text-amber-300"
                    >
                        Preview · Sample Data
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Outstanding
                    </div>
                    <div class="mt-2 text-xl font-semibold">
                        {{ money(summary.outstanding_amount_cents) }}
                    </div>
                    <div
                        class="mt-1 text-[0.65rem] font-medium text-amber-700 uppercase dark:text-amber-300"
                    >
                        Preview · Sample Data
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Receipted
                    </div>
                    <div class="mt-2 text-xl font-semibold">
                        {{ money(summary.receipted_amount_cents) }}
                    </div>
                    <div
                        class="mt-1 text-[0.65rem] font-medium text-amber-700 uppercase dark:text-amber-300"
                    >
                        Preview · Sample Data
                    </div>
                </div>
            </section>

            <section
                class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100"
            >
                <p class="font-medium">Report scope</p>
                <p class="mt-1">{{ summary.scope }}</p>
                <p class="mt-1">
                    This report shows recorded schedule, collection, receipt,
                    and balance statuses. It does not recalculate liability or
                    decide delinquency, penalties, or official report approval.
                </p>
                <p class="mt-1 text-xs uppercase">
                    One row per: {{ label(summary.grain) }} · Records dated by:
                    {{ label(summary.date_basis) }}
                </p>
            </section>

            <section
                class="grid gap-3 md:hidden"
                aria-label="Payment summary records"
            >
                <div
                    v-if="rows.length === 0"
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 text-center text-sm text-muted-foreground dark:border-sidebar-border"
                >
                    No matching sample data for these filters.
                </div>
                <article
                    v-for="row in rows"
                    :key="`mobile-${row.payment_schedule_id}`"
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                    data-testid="payment-summary-mobile-row"
                    :data-payment-schedule-id="row.payment_schedule_id"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="text-sm font-semibold break-words">
                                {{ row.business_name }}
                            </h2>
                            <p
                                class="text-xs break-words text-muted-foreground"
                            >
                                {{
                                    row.application_number ??
                                    `Application #${row.application_id}`
                                }}
                            </p>
                        </div>
                        <Badge
                            variant="outline"
                            data-testid="payment-summary-mobile-status"
                            >{{ label(row.schedule_status) }}</Badge
                        >
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-x-3 gap-y-3 text-sm">
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Total
                            </dt>
                            <dd class="mt-1 font-medium">
                                {{ money(row.total_amount_cents) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Paid
                            </dt>
                            <dd
                                class="mt-1 font-medium"
                                data-testid="payment-summary-mobile-paid"
                            >
                                {{ money(row.paid_amount_cents) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Outstanding
                            </dt>
                            <dd class="mt-1 font-medium">
                                {{ money(row.outstanding_amount_cents) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Receipt
                            </dt>
                            <dd class="mt-1 font-medium break-words">
                                {{ row.latest_receipt_number ?? 'Pending' }}
                            </dd>
                        </div>
                    </dl>
                </article>
            </section>

            <section
                class="hidden overflow-hidden rounded-lg border border-sidebar-border/70 bg-background md:block dark:border-sidebar-border"
            >
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1120px] table-fixed text-sm">
                        <thead
                            class="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="w-[22%] px-3 py-3 font-medium">
                                    Business
                                </th>
                                <th class="w-[18%] px-3 py-3 font-medium">
                                    Application
                                </th>
                                <th class="w-[13%] px-3 py-3 font-medium">
                                    Status
                                </th>
                                <th
                                    class="w-[13%] px-3 py-3 text-right font-medium"
                                >
                                    Total
                                </th>
                                <th
                                    class="w-[13%] px-3 py-3 text-right font-medium"
                                >
                                    Paid
                                </th>
                                <th
                                    class="w-[13%] px-3 py-3 text-right font-medium"
                                >
                                    Outstanding
                                </th>
                                <th class="w-[18%] px-3 py-3 font-medium">
                                    Receipt evidence
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border/70">
                            <tr v-if="rows.length === 0">
                                <td
                                    colspan="7"
                                    class="px-3 py-8 text-center text-muted-foreground"
                                >
                                    No matching sample data for these filters.
                                </td>
                            </tr>
                            <tr
                                v-for="row in rows"
                                :key="row.payment_schedule_id"
                                data-testid="payment-summary-row"
                                :data-payment-schedule-id="
                                    row.payment_schedule_id
                                "
                            >
                                <td class="px-3 py-3 align-top">
                                    <div class="font-medium break-words">
                                        {{ row.business_name }}
                                    </div>
                                    <div
                                        class="text-xs break-words text-muted-foreground"
                                    >
                                        {{ row.trade_name ?? '-' }} ·
                                        {{ row.owner_name }}
                                    </div>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <div class="font-medium break-words">
                                        {{
                                            row.application_number ??
                                            `Application #${row.application_id}`
                                        }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ label(row.application_type) }}
                                    </div>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <Badge
                                        variant="outline"
                                        data-testid="payment-summary-status"
                                        >{{ label(row.schedule_status) }}</Badge
                                    >
                                    <div
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{ label(row.payment_mode) }}
                                    </div>
                                </td>
                                <td
                                    class="px-3 py-3 text-right align-top font-medium whitespace-nowrap"
                                >
                                    {{ money(row.total_amount_cents) }}
                                </td>
                                <td
                                    class="px-3 py-3 text-right align-top font-medium whitespace-nowrap"
                                    data-testid="payment-summary-paid"
                                >
                                    {{ money(row.paid_amount_cents) }}
                                </td>
                                <td
                                    class="px-3 py-3 text-right align-top font-medium whitespace-nowrap"
                                >
                                    {{ money(row.outstanding_amount_cents) }}
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <div class="font-medium break-words">
                                        {{
                                            row.latest_receipt_number ??
                                            'Pending receipt'
                                        }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ money(row.receipted_amount_cents) }}
                                        receipted ·
                                        {{ row.receipted_count }} receipt(s)
                                    </div>
                                    <div
                                        v-if="
                                            row.collection_difference_cents !==
                                            0
                                        "
                                        class="mt-1 text-xs text-destructive"
                                    >
                                        Collection difference:
                                        {{
                                            money(
                                                row.collection_difference_cents,
                                            )
                                        }}
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
