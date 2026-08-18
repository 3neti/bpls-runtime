<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download, Search, X } from '@lucide/vue';
import { ref } from 'vue';
import {
    download,
    index,
} from '@/actions/App/Http/Controllers/Staff/RevenueSourceReportController';
import ReportFamilyBanner from '@/components/reports/ReportFamilyBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type RevenueSourceRow = {
    category: string;
    code: string;
    name: string;
    line_of_business_id: number | null;
    line_of_business: string | null;
    allocation_count: number;
    receipt_count: number;
    amount_cents: number;
};

type Option = {
    label: string;
    value: string;
};

const props = defineProps<{
    filters: {
        date_from: string;
        date_to: string;
        category: string | null;
    };
    summary: {
        source_count: number;
        allocation_count: number;
        total_amount_cents: number;
        date_basis: string;
        scope: string;
        policy_note: string;
    };
    rows: RevenueSourceRow[];
    categories: Option[];
}>();

const dateFrom = ref(props.filters.date_from);
const dateTo = ref(props.filters.date_to);
const category = ref(props.filters.category ?? '');

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Revenue Sources',
        href: index(),
    },
];

function query(): Record<string, string | undefined> {
    return {
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        category: category.value || undefined,
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
    category.value = '';
    router.get(index.url(), {}, { preserveState: true, replace: true });
}

function exportUrl(): string {
    return download.url({ query: query() });
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
        <Head title="Revenue Sources" />

        <main class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        Revenue Sources
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Collection allocation report grouped by fee, tax, and
                        charge source.
                    </p>
                </div>
                <Button as-child variant="outline">
                    <a :href="exportUrl()">
                        <Download />
                        Export CSV
                    </a>
                </Button>
            </section>

            <ReportFamilyBanner family="operational" availability="working" />

            <form
                class="flex flex-col gap-3 rounded-lg border border-sidebar-border/70 bg-background p-4 md:flex-row md:items-end dark:border-sidebar-border"
                @submit.prevent="applyFilters"
            >
                <div class="grid gap-2 md:w-56">
                    <label
                        for="revenue_source_date_from"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        From
                    </label>
                    <Input
                        id="revenue_source_date_from"
                        v-model="dateFrom"
                        name="date_from"
                        type="date"
                    />
                </div>
                <div class="grid gap-2 md:w-56">
                    <label
                        for="revenue_source_date_to"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        To
                    </label>
                    <Input
                        id="revenue_source_date_to"
                        v-model="dateTo"
                        name="date_to"
                        type="date"
                    />
                </div>
                <div class="grid gap-2 md:w-56">
                    <label
                        for="revenue_source_category"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Category
                    </label>
                    <select
                        id="revenue_source_category"
                        v-model="category"
                        name="category"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="">All categories</option>
                        <option
                            v-for="option in categories"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <Button type="submit">
                        <Search />
                        Apply
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        @click="clearFilters"
                    >
                        <X />
                        Clear
                    </Button>
                </div>
            </form>

            <section
                class="grid gap-3 md:grid-cols-3"
                aria-label="Revenue source summary"
            >
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Total Allocated
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ money(summary.total_amount_cents) }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Sources
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ summary.source_count }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Allocations
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ summary.allocation_count }}
                    </div>
                </div>
            </section>

            <section
                class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100"
            >
                <p class="font-medium">Report scope</p>
                <p class="mt-1">{{ summary.scope }}</p>
                <p class="mt-1">{{ summary.policy_note }}</p>
                <p class="mt-1 text-xs uppercase">
                    Date basis: {{ label(summary.date_basis) }}
                </p>
            </section>

            <section
                class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background dark:border-sidebar-border"
            >
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] table-fixed text-sm">
                        <thead
                            class="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="w-[28%] px-3 py-3 font-medium">
                                    Source
                                </th>
                                <th class="w-[12%] px-3 py-3 font-medium">
                                    Category
                                </th>
                                <th class="w-[20%] px-3 py-3 font-medium">
                                    Line of Business
                                </th>
                                <th
                                    class="w-[13%] px-3 py-3 text-right font-medium"
                                >
                                    Allocations
                                </th>
                                <th
                                    class="w-[12%] px-3 py-3 text-right font-medium"
                                >
                                    Receipts
                                </th>
                                <th
                                    class="w-[15%] px-3 py-3 text-right font-medium"
                                >
                                    Amount
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="rows.length === 0">
                                <td
                                    colspan="6"
                                    class="px-3 py-8 text-center text-muted-foreground"
                                >
                                    No revenue source allocations found for this
                                    date range.
                                </td>
                            </tr>
                            <tr
                                v-for="row in rows"
                                :key="`${row.category}:${row.code}:${row.line_of_business_id ?? 'none'}`"
                                class="border-b last:border-0"
                            >
                                <td class="px-3 py-3 align-top">
                                    <div class="font-medium break-words">
                                        {{ row.code }}
                                    </div>
                                    <div
                                        class="text-xs break-words text-muted-foreground"
                                    >
                                        {{ row.name }}
                                    </div>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <Badge variant="outline">
                                        {{ label(row.category) }}
                                    </Badge>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    {{ row.line_of_business ?? '-' }}
                                </td>
                                <td class="px-3 py-3 text-right align-top">
                                    {{ row.allocation_count }}
                                </td>
                                <td class="px-3 py-3 text-right align-top">
                                    {{ row.receipt_count }}
                                </td>
                                <td
                                    class="px-3 py-3 text-right align-top font-medium whitespace-nowrap"
                                >
                                    {{ money(row.amount_cents) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
