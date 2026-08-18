<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download, Search, X } from '@lucide/vue';
import { ref } from 'vue';
import {
    download,
    index,
} from '@/actions/App/Http/Controllers/Staff/PaidEstablishmentReportController';
import ReportFamilyBanner from '@/components/reports/ReportFamilyBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type PaidEstablishmentRow = {
    payment_schedule_id: number;
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
    paid_amount_cents: number;
    schedule_status: string;
    receipt_number: string | null;
    receipt_status: string | null;
    receipt_issued_at: string | null;
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
    };
    summary: {
        row_count: number;
        business_count: number;
        paid_amount_cents: number;
        receipted_count: number;
        date_basis: string;
        scope: string;
        policy_note: string;
    };
    rows: PaidEstablishmentRow[];
    types: Option[];
}>();

const year = ref(String(props.filters.year));
const type = ref(props.filters.type ?? '');
const search = ref(props.filters.q ?? '');

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Paid Establishments',
        href: index(),
    },
];

function query(): Record<string, string | undefined> {
    return {
        year: year.value || undefined,
        type: type.value || undefined,
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
    search.value = '';
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
        <Head title="Paid Establishments" />

        <main class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        Paid Establishments
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Establishment masterlist foundation from paid permit
                        schedules.
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
                class="flex flex-col gap-3 rounded-lg border border-sidebar-border/70 bg-background p-4 xl:flex-row xl:items-end dark:border-sidebar-border"
                @submit.prevent="applyFilters"
            >
                <div class="grid gap-2 sm:w-40">
                    <label
                        for="paid_establishments_year"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Year
                    </label>
                    <Input
                        id="paid_establishments_year"
                        v-model="year"
                        name="year"
                        type="number"
                        min="2000"
                        max="2100"
                    />
                </div>
                <div class="grid gap-2 sm:w-56">
                    <label
                        for="paid_establishments_type"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Type
                    </label>
                    <select
                        id="paid_establishments_type"
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
                <div class="grid gap-2 xl:min-w-80">
                    <label
                        for="paid_establishments_search"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Search
                    </label>
                    <Input
                        id="paid_establishments_search"
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
                aria-label="Paid establishment summary"
            >
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Paid Schedules
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
                        Paid Amount
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ money(summary.paid_amount_cents) }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Receipted
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ summary.receipted_count }}
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
                    <table class="w-full min-w-[880px] table-fixed text-sm">
                        <thead
                            class="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="w-[23%] px-3 py-3 font-medium">
                                    Establishment
                                </th>
                                <th class="w-[15%] px-3 py-3 font-medium">
                                    Owner
                                </th>
                                <th class="w-[17%] px-3 py-3 font-medium">
                                    Application
                                </th>
                                <th class="w-[15%] px-3 py-3 font-medium">
                                    Line of Business
                                </th>
                                <th
                                    class="w-[12%] px-3 py-3 text-right font-medium"
                                >
                                    Paid
                                </th>
                                <th class="w-[18%] px-3 py-3 font-medium">
                                    Receipt
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="rows.length === 0">
                                <td
                                    colspan="6"
                                    class="px-3 py-8 text-center text-muted-foreground"
                                >
                                    No paid establishments found for these
                                    filters.
                                </td>
                            </tr>
                            <tr
                                v-for="row in rows"
                                :key="row.payment_schedule_id"
                                class="border-b last:border-0"
                            >
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
                                        {{
                                            row.application_number ??
                                            `Application #${row.application_id}`
                                        }}
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
                                <td class="px-3 py-3 align-top break-words">
                                    {{
                                        row.line_of_businesses.length > 0
                                            ? row.line_of_businesses.join(', ')
                                            : '-'
                                    }}
                                </td>
                                <td
                                    class="px-3 py-3 text-right align-top font-medium whitespace-nowrap"
                                >
                                    {{ money(row.paid_amount_cents) }}
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <div class="font-medium break-words">
                                        {{ row.receipt_number ?? '-' }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ label(row.receipt_status) }}
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
