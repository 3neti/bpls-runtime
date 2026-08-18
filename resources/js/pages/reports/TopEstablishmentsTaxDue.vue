<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download, Search, X } from '@lucide/vue';
import { ref } from 'vue';
import {
    download,
    index,
} from '@/actions/App/Http/Controllers/Staff/TopEstablishmentTaxDueReportController';
import ReportFamilyBanner from '@/components/reports/ReportFamilyBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type TopEstablishmentTaxDueRow = {
    payment_schedule_id: number;
    assessment_id: number;
    application_id: number;
    application_number: string | null;
    application_type: string;
    application_status: string;
    application_year: number;
    business_id: number;
    business_name: string;
    trade_name: string | null;
    registration_number: string | null;
    barangay: string | null;
    owner_name: string;
    line_of_businesses: string[];
    tax_line_count: number;
    tax_codes: string[];
    tax_due_cents: number;
    schedule_status: string;
    total_schedule_amount_cents: number;
    paid_amount_cents: number;
    outstanding_amount_cents: number;
};

type Option = {
    label: string;
    value: string;
};

const props = defineProps<{
    filters: {
        year: number;
        type: string | null;
        q: string | null;
        limit: number;
    };
    summary: {
        row_count: number;
        business_count: number;
        tax_due_cents: number;
        largest_tax_due_cents: number;
        date_basis: string;
        scope: string;
        policy_note: string;
    };
    rows: TopEstablishmentTaxDueRow[];
    types: Option[];
}>();

const year = ref(String(props.filters.year));
const type = ref(props.filters.type ?? '');
const search = ref(props.filters.q ?? '');
const limit = ref(String(props.filters.limit));

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Top Tax Due',
        href: index(),
    },
];

function query(): Record<string, string | undefined> {
    return {
        year: year.value || undefined,
        type: type.value || undefined,
        q: search.value || undefined,
        limit: limit.value || undefined,
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
    search.value = '';
    limit.value = '100';
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
        <Head title="Top Tax Due" />

        <main class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        Top Tax Due
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Establishment ranking from persisted permit assessment
                        tax lines.
                    </p>
                </div>
                <Button as-child variant="outline">
                    <a :href="exportUrl()">
                        <Download />
                        Export CSV
                    </a>
                </Button>
            </section>

            <ReportFamilyBanner family="management" availability="working" />

            <form
                class="flex flex-col gap-3 rounded-lg border border-sidebar-border/70 bg-background p-4 xl:flex-row xl:items-end dark:border-sidebar-border"
                @submit.prevent="applyFilters"
            >
                <div class="grid gap-2 sm:w-36">
                    <label
                        for="top_tax_due_year"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Year
                    </label>
                    <Input
                        id="top_tax_due_year"
                        v-model="year"
                        name="year"
                        type="number"
                        min="2000"
                        max="2100"
                    />
                </div>
                <div class="grid gap-2 sm:w-48">
                    <label
                        for="top_tax_due_type"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Type
                    </label>
                    <select
                        id="top_tax_due_type"
                        v-model="type"
                        name="type"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
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
                <div class="grid gap-2 sm:w-32">
                    <label
                        for="top_tax_due_limit"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Limit
                    </label>
                    <Input
                        id="top_tax_due_limit"
                        v-model="limit"
                        name="limit"
                        type="number"
                        min="1"
                        max="100"
                    />
                </div>
                <div class="grid gap-2 xl:min-w-72">
                    <label
                        for="top_tax_due_search"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Search
                    </label>
                    <Input
                        id="top_tax_due_search"
                        v-model="search"
                        name="q"
                        type="search"
                        placeholder="Business, owner, barangay, application"
                    />
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
                class="grid gap-3 md:grid-cols-2 xl:grid-cols-4"
                aria-label="Top tax due summary"
            >
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Ranked Rows
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ summary.row_count }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Businesses
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ summary.business_count }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Tax Due
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ money(summary.tax_due_cents) }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Largest Tax Due
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ money(summary.largest_tax_due_cents) }}
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
                    <table class="w-full min-w-[1040px] table-fixed text-sm">
                        <thead
                            class="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="w-[6%] px-3 py-3 font-medium">
                                    Rank
                                </th>
                                <th class="w-[22%] px-3 py-3 font-medium">
                                    Establishment
                                </th>
                                <th class="w-[13%] px-3 py-3 font-medium">
                                    Owner
                                </th>
                                <th class="w-[16%] px-3 py-3 font-medium">
                                    Application
                                </th>
                                <th class="w-[14%] px-3 py-3 font-medium">
                                    Tax Lines
                                </th>
                                <th
                                    class="w-[12%] px-3 py-3 text-right font-medium"
                                >
                                    Tax Due
                                </th>
                                <th class="w-[10%] px-3 py-3 font-medium">
                                    Payment
                                </th>
                                <th
                                    class="w-[7%] px-3 py-3 text-right font-medium"
                                >
                                    Balance
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="rows.length === 0">
                                <td
                                    colspan="8"
                                    class="px-3 py-8 text-center text-muted-foreground"
                                >
                                    No establishments with assessed tax lines
                                    found for these filters.
                                </td>
                            </tr>
                            <tr
                                v-for="(row, rowIndex) in rows"
                                :key="row.payment_schedule_id"
                                class="border-b last:border-0"
                            >
                                <td class="px-3 py-3 align-top font-medium">
                                    {{ rowIndex + 1 }}
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <div class="font-medium break-words">
                                        {{ row.business_name }}
                                    </div>
                                    <div
                                        class="text-xs break-words text-muted-foreground"
                                    >
                                        {{ row.trade_name ?? '-' }} ·
                                        {{ row.barangay ?? '-' }}
                                    </div>
                                </td>
                                <td class="px-3 py-3 align-top break-words">
                                    {{ row.owner_name }}
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <div class="font-medium break-words">
                                        {{ row.application_number }}
                                    </div>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        <Badge variant="outline">
                                            {{ label(row.application_type) }}
                                        </Badge>
                                        <Badge variant="outline">
                                            {{ label(row.application_status) }}
                                        </Badge>
                                    </div>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <div class="break-words">
                                        {{
                                            row.tax_codes.length > 0
                                                ? row.tax_codes.join(', ')
                                                : '-'
                                        }}
                                    </div>
                                    <div
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{ row.tax_line_count }} tax line<span
                                            v-if="row.tax_line_count !== 1"
                                            >s</span
                                        >
                                    </div>
                                </td>
                                <td
                                    class="px-3 py-3 text-right align-top font-medium whitespace-nowrap"
                                >
                                    {{ money(row.tax_due_cents) }}
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <Badge variant="outline">
                                        {{ label(row.schedule_status) }}
                                    </Badge>
                                </td>
                                <td
                                    class="px-3 py-3 text-right align-top whitespace-nowrap"
                                >
                                    {{ money(row.outstanding_amount_cents) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
