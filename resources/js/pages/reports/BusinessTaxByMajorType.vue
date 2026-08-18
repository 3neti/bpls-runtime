<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download, Search, X } from '@lucide/vue';
import { ref } from 'vue';
import {
    download,
    index,
} from '@/actions/App/Http/Controllers/Staff/BusinessTaxByMajorTypeReportController';
import ReportFamilyBanner from '@/components/reports/ReportFamilyBanner.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type TaxRow = {
    major_type: string;
    allocation_count: number;
    receipt_count: number;
    amount_cents: number;
};

const props = defineProps<{
    filters: {
        date_from: string | null;
        date_to: string | null;
        receipt_from: string | null;
        receipt_to: string | null;
    };
    summary: {
        major_type_count: number;
        collected_major_type_count: number;
        allocation_count: number;
        receipt_count: number;
        total_amount_cents: number;
        date_basis: string;
        classification_basis: string;
        scope: string;
        policy_note: string;
        classification_note: string;
    };
    rows: TaxRow[];
}>();

const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
const receiptFrom = ref(props.filters.receipt_from ?? '');
const receiptTo = ref(props.filters.receipt_to ?? '');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Business Tax by Major Type', href: index() },
];

function query(): Record<string, string | undefined> {
    return {
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        receipt_from: receiptFrom.value || undefined,
        receipt_to: receiptTo.value || undefined,
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
    dateFrom.value = '';
    dateTo.value = '';
    receiptFrom.value = '';
    receiptTo.value = '';
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
        <Head title="Business Tax by Major Type" />

        <main class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        Business Tax on Major Type
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Collected business tax grouped by primary business
                        classification.
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
                class="grid gap-3 rounded-lg border border-sidebar-border/70 bg-background p-4 md:grid-cols-2 xl:grid-cols-4 dark:border-sidebar-border"
                @submit.prevent="applyFilters"
            >
                <div class="grid gap-2">
                    <label
                        for="tax_major_date_from"
                        class="text-xs font-medium text-muted-foreground uppercase"
                        >Collected from</label
                    >
                    <Input
                        id="tax_major_date_from"
                        v-model="dateFrom"
                        name="date_from"
                        type="date"
                    />
                </div>
                <div class="grid gap-2">
                    <label
                        for="tax_major_date_to"
                        class="text-xs font-medium text-muted-foreground uppercase"
                        >Collected to</label
                    >
                    <Input
                        id="tax_major_date_to"
                        v-model="dateTo"
                        name="date_to"
                        type="date"
                    />
                </div>
                <div class="grid gap-2">
                    <label
                        for="tax_major_receipt_from"
                        class="text-xs font-medium text-muted-foreground uppercase"
                        >OR number from</label
                    >
                    <Input
                        id="tax_major_receipt_from"
                        v-model="receiptFrom"
                        name="receipt_from"
                        placeholder="Optional lower bound"
                    />
                </div>
                <div class="grid gap-2">
                    <label
                        for="tax_major_receipt_to"
                        class="text-xs font-medium text-muted-foreground uppercase"
                        >OR number to</label
                    >
                    <Input
                        id="tax_major_receipt_to"
                        v-model="receiptTo"
                        name="receipt_to"
                        placeholder="Optional upper bound"
                    />
                </div>
                <div class="flex gap-2 md:col-span-2 xl:col-span-4">
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
                aria-label="Business tax summary"
            >
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Major types collected
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ summary.collected_major_type_count }}
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        of {{ summary.major_type_count }} classified rows
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Tax allocations
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ summary.allocation_count }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Issued receipts
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ summary.receipt_count }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Collected business tax
                    </div>
                    <div
                        class="mt-2 text-xl font-semibold"
                        data-testid="business-tax-major-total"
                    >
                        {{ money(summary.total_amount_cents) }}
                    </div>
                </div>
            </section>

            <section
                class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100"
            >
                <p class="font-medium">Report scope</p>
                <p class="mt-1">{{ summary.scope }}</p>
                <p class="mt-1">{{ summary.policy_note }}</p>
                <p class="mt-1">{{ summary.classification_note }}</p>
                <p class="mt-1 text-xs uppercase">
                    Date basis: {{ label(summary.date_basis) }} ·
                    Classification: {{ label(summary.classification_basis) }}
                </p>
            </section>

            <section
                class="grid gap-3 md:hidden"
                aria-label="Business tax by major type rows"
            >
                <article
                    v-for="row in rows"
                    :key="`mobile-${row.major_type}`"
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                    data-testid="business-tax-major-mobile-row"
                    :data-major-type="row.major_type"
                >
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="min-w-0 text-sm font-semibold break-words">
                            {{ row.major_type }}
                        </h2>
                        <div
                            class="text-sm font-semibold whitespace-nowrap"
                            data-testid="business-tax-major-mobile-amount"
                        >
                            {{ money(row.amount_cents) }}
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        {{ row.allocation_count }} allocation(s) ·
                        {{ row.receipt_count }} receipt(s)
                    </p>
                </article>
            </section>

            <section
                class="hidden overflow-hidden rounded-lg border border-sidebar-border/70 bg-background md:block dark:border-sidebar-border"
            >
                <table class="w-full table-fixed text-sm">
                    <thead
                        class="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="w-[55%] px-4 py-3 font-medium">
                                Major Type
                            </th>
                            <th
                                class="w-[15%] px-4 py-3 text-right font-medium"
                            >
                                Allocations
                            </th>
                            <th
                                class="w-[15%] px-4 py-3 text-right font-medium"
                            >
                                Receipts
                            </th>
                            <th
                                class="w-[15%] px-4 py-3 text-right font-medium"
                            >
                                Amount
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
                        <tr
                            v-for="row in rows"
                            :key="row.major_type"
                            data-testid="business-tax-major-row"
                            :data-major-type="row.major_type"
                        >
                            <td class="px-4 py-3 font-medium break-words">
                                {{ row.major_type }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                {{ row.allocation_count }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                {{ row.receipt_count }}
                            </td>
                            <td
                                class="px-4 py-3 text-right font-semibold whitespace-nowrap"
                                data-testid="business-tax-major-amount"
                            >
                                {{ money(row.amount_cents) }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t bg-muted/30 font-semibold">
                        <tr>
                            <td class="px-4 py-3">Total Amount</td>
                            <td class="px-4 py-3 text-right">
                                {{ summary.allocation_count }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                {{ summary.receipt_count }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                {{ money(summary.total_amount_cents) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </section>
        </main>
    </AppLayout>
</template>
