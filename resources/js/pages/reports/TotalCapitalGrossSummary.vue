<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download, Search, X } from '@lucide/vue';
import { ref } from 'vue';
import {
    download,
    index,
} from '@/actions/App/Http/Controllers/Staff/TotalCapitalGrossSummaryReportController';
import ReportFamilyBanner from '@/components/reports/ReportFamilyBanner.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type CapitalGrossRow = {
    application_id: number;
    application_number: string | null;
    application_type: string;
    application_year: number;
    business_id: number;
    owner_name: string;
    business_name: string;
    capital_investment_cents: number;
    gross_sales_cents: number;
    latest_receipt_number: string | null;
    latest_payment_date: string | null;
    payment_amount_cents: number;
    remaining_balance_cents: number;
    payment_status: 'Completed' | 'Partial';
};

const props = defineProps<{
    filters: {
        date_from: string | null;
        date_to: string | null;
    };
    summary: {
        row_count: number;
        business_count: number;
        capital_investment_cents: number;
        gross_sales_cents: number;
        payment_amount_cents: number;
        remaining_balance_cents: number;
        completed_count: number;
        partial_count: number;
        qualification_date_basis: string;
        financial_scope: string;
        grain: string;
        scope: string;
        policy_note: string;
        legacy_note: string;
    };
    rows: CapitalGrossRow[];
}>();

const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Total Capital and Gross Summary', href: index() },
];

function query(): Record<string, string | undefined> {
    return {
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    };
}

function applyFilters(): void {
    router.get(
        index.url({ query: query() }),
        {},
        { preserveState: true, replace: true },
    );
}

function clearFilters(): void {
    dateFrom.value = '';
    dateTo.value = '';
    router.get(index.url(), {}, { preserveState: true, replace: true });
}

function money(amountCents: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amountCents / 100);
}

function label(value: string): string {
    return value.replaceAll('_', ' ');
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Total Capital and Gross Summary" />

        <main class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        Total Capital and Gross Summary
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Declaration and lifetime payment figures for
                        establishments paying in a selected period.
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
                class="grid gap-3 rounded-lg border border-sidebar-border/70 bg-background p-4 sm:grid-cols-2 dark:border-sidebar-border"
                @submit.prevent="applyFilters"
            >
                <div class="grid gap-2">
                    <label
                        for="capital_gross_date_from"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Payment from
                    </label>
                    <Input
                        id="capital_gross_date_from"
                        v-model="dateFrom"
                        name="date_from"
                        type="date"
                    />
                </div>
                <div class="grid gap-2">
                    <label
                        for="capital_gross_date_to"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Payment to
                    </label>
                    <Input
                        id="capital_gross_date_to"
                        v-model="dateTo"
                        name="date_to"
                        type="date"
                    />
                </div>
                <div class="flex gap-2 sm:col-span-2">
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
                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                aria-label="Capital and gross summary totals"
            >
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div
                        class="text-[0.65rem] font-medium text-amber-700 uppercase dark:text-amber-300"
                    >
                        Preview · Sample Data
                    </div>
                    <div class="text-xs text-muted-foreground uppercase">
                        Capital
                    </div>
                    <div
                        class="mt-2 text-xl font-semibold"
                        data-testid="capital-gross-total-capital"
                    >
                        {{ money(summary.capital_investment_cents) }}
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{ summary.row_count }} establishment(s)
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div
                        class="text-[0.65rem] font-medium text-amber-700 uppercase dark:text-amber-300"
                    >
                        Preview · Sample Data
                    </div>
                    <div class="text-xs text-muted-foreground uppercase">
                        Gross sales
                    </div>
                    <div
                        class="mt-2 text-xl font-semibold"
                        data-testid="capital-gross-total-gross"
                    >
                        {{ money(summary.gross_sales_cents) }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div
                        class="text-[0.65rem] font-medium text-amber-700 uppercase dark:text-amber-300"
                    >
                        Preview · Sample Data
                    </div>
                    <div class="text-xs text-muted-foreground uppercase">
                        Lifetime payments
                    </div>
                    <div
                        class="mt-2 text-xl font-semibold"
                        data-testid="capital-gross-total-payment"
                    >
                        {{ money(summary.payment_amount_cents) }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div
                        class="text-[0.65rem] font-medium text-amber-700 uppercase dark:text-amber-300"
                    >
                        Preview · Sample Data
                    </div>
                    <div class="text-xs text-muted-foreground uppercase">
                        Remaining balance
                    </div>
                    <div
                        class="mt-2 text-xl font-semibold"
                        data-testid="capital-gross-total-balance"
                    >
                        {{ money(summary.remaining_balance_cents) }}
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{ summary.completed_count }} completed ·
                        {{ summary.partial_count }} partial
                    </div>
                </div>
            </section>

            <section
                class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100"
            >
                <p class="font-medium">Report scope</p>
                <p class="mt-1">{{ summary.scope }}</p>
                <p class="mt-1">
                    Capital and gross sales come from recorded application
                    declarations. Payments and balances use receipted
                    collections and recorded schedules; this report does not
                    recalculate assessments or decide unresolved adjustments.
                </p>
                <p class="mt-1 text-xs uppercase">
                    Qualification:
                    {{ label(summary.qualification_date_basis) }} · Financial
                    scope: {{ label(summary.financial_scope) }} · One row per:
                    {{ label(summary.grain) }}
                </p>
            </section>

            <section
                class="grid gap-3 lg:hidden"
                aria-label="Capital and gross rows"
            >
                <article
                    v-for="row in rows"
                    :key="`mobile-${row.application_id}`"
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                    data-testid="capital-gross-mobile-row"
                    :data-application-id="row.application_id"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="text-sm font-semibold break-words">
                                {{ row.business_name }}
                            </h2>
                            <p
                                class="text-xs break-words text-muted-foreground"
                            >
                                {{ row.owner_name }}
                            </p>
                        </div>
                        <span
                            class="shrink-0 rounded border px-2 py-1 text-xs font-medium"
                            :class="
                                row.payment_status === 'Completed'
                                    ? 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-200'
                                    : 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-200'
                            "
                        >
                            {{ row.payment_status }}
                        </span>
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-3 text-xs">
                        <div>
                            <dt class="text-muted-foreground">Capital</dt>
                            <dd class="mt-1 font-medium">
                                {{ money(row.capital_investment_cents) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Gross</dt>
                            <dd class="mt-1 font-medium">
                                {{ money(row.gross_sales_cents) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Payment</dt>
                            <dd
                                class="mt-1 font-medium"
                                data-testid="capital-gross-mobile-payment"
                            >
                                {{ money(row.payment_amount_cents) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Balance</dt>
                            <dd class="mt-1 font-medium">
                                {{ money(row.remaining_balance_cents) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Latest OR</dt>
                            <dd class="mt-1 break-words">
                                {{ row.latest_receipt_number ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Payment date</dt>
                            <dd class="mt-1">
                                {{ row.latest_payment_date ?? '—' }}
                            </dd>
                        </div>
                    </dl>
                </article>
            </section>

            <section
                class="hidden overflow-hidden rounded-lg border border-sidebar-border/70 bg-background lg:block dark:border-sidebar-border"
            >
                <table class="w-full table-fixed text-xs">
                    <thead
                        class="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="w-[12%] px-2 py-3 font-medium">Name</th>
                            <th class="w-[14%] px-2 py-3 font-medium">
                                Business name
                            </th>
                            <th
                                class="w-[10%] px-2 py-3 text-right font-medium"
                            >
                                Capital
                            </th>
                            <th
                                class="w-[10%] px-2 py-3 text-right font-medium"
                            >
                                Gross
                            </th>
                            <th class="w-[14%] px-2 py-3 font-medium">
                                OR number
                            </th>
                            <th class="w-[9%] px-2 py-3 font-medium">
                                Payment date
                            </th>
                            <th
                                class="w-[11%] px-2 py-3 text-right font-medium"
                            >
                                Payment amount
                            </th>
                            <th
                                class="w-[11%] px-2 py-3 text-right font-medium"
                            >
                                Remaining balance
                            </th>
                            <th class="w-[9%] px-2 py-3 font-medium">
                                Payment status
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
                        <tr
                            v-for="row in rows"
                            :key="row.application_id"
                            data-testid="capital-gross-row"
                            :data-application-id="row.application_id"
                        >
                            <td class="px-2 py-3 font-medium break-words">
                                {{ row.owner_name }}
                            </td>
                            <td class="px-2 py-3 break-words">
                                {{ row.business_name }}
                            </td>
                            <td
                                class="px-2 py-3 text-right text-[10px] whitespace-nowrap"
                            >
                                {{ money(row.capital_investment_cents) }}
                            </td>
                            <td
                                class="px-2 py-3 text-right text-[10px] whitespace-nowrap"
                            >
                                {{ money(row.gross_sales_cents) }}
                            </td>
                            <td class="px-2 py-3 break-words">
                                {{ row.latest_receipt_number ?? '—' }}
                            </td>
                            <td class="px-2 py-3 break-words">
                                {{ row.latest_payment_date ?? '—' }}
                            </td>
                            <td
                                class="px-2 py-3 text-right text-[10px] font-semibold whitespace-nowrap"
                                data-testid="capital-gross-payment"
                            >
                                {{ money(row.payment_amount_cents) }}
                            </td>
                            <td
                                class="px-2 py-3 text-right text-[10px] whitespace-nowrap"
                            >
                                {{ money(row.remaining_balance_cents) }}
                            </td>
                            <td class="px-2 py-3 break-words">
                                {{ row.payment_status }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t bg-muted/30 font-semibold">
                        <tr>
                            <td class="px-2 py-3">TOTAL</td>
                            <td class="px-2 py-3"></td>
                            <td
                                class="px-2 py-3 text-right text-[10px] whitespace-nowrap"
                            >
                                {{ money(summary.capital_investment_cents) }}
                            </td>
                            <td
                                class="px-2 py-3 text-right text-[10px] whitespace-nowrap"
                            >
                                {{ money(summary.gross_sales_cents) }}
                            </td>
                            <td class="px-2 py-3"></td>
                            <td class="px-2 py-3"></td>
                            <td
                                class="px-2 py-3 text-right text-[10px] whitespace-nowrap"
                            >
                                {{ money(summary.payment_amount_cents) }}
                            </td>
                            <td
                                class="px-2 py-3 text-right text-[10px] whitespace-nowrap"
                            >
                                {{ money(summary.remaining_balance_cents) }}
                            </td>
                            <td class="px-2 py-3"></td>
                        </tr>
                    </tfoot>
                </table>
            </section>

            <p
                v-if="rows.length === 0"
                class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                No matching sample data for the selected period.
            </p>
        </main>
    </AppLayout>
</template>
