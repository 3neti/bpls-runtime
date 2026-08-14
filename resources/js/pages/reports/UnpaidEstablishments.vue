<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download, Search, X } from '@lucide/vue';
import { ref } from 'vue';
import {
    download,
    index,
} from '@/actions/App/Http/Controllers/Staff/UnpaidEstablishmentReportController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type UnpaidEstablishmentRow = {
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
    total_amount_cents: number;
    paid_amount_cents: number;
    outstanding_amount_cents: number;
    schedule_status: string;
    due_on: string | null;
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
        status: string | null;
    };
    summary: {
        row_count: number;
        business_count: number;
        total_amount_cents: number;
        paid_amount_cents: number;
        outstanding_amount_cents: number;
        partially_paid_count: number;
        date_basis: string;
        scope: string;
        policy_note: string;
    };
    rows: UnpaidEstablishmentRow[];
    types: Option[];
    statuses: Option[];
}>();

const year = ref(String(props.filters.year));
const type = ref(props.filters.type ?? '');
const search = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? '');

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Unpaid Establishments',
        href: index(),
    },
];

function query(): Record<string, string | undefined> {
    return {
        year: year.value || undefined,
        type: type.value || undefined,
        q: search.value || undefined,
        status: status.value || undefined,
    };
}

function applyFilters(): void {
    router.get(index.url({ query: query() }), {}, {
        preserveState: true,
        replace: true,
    });
}

function clearFilters(): void {
    year.value = '';
    type.value = '';
    search.value = '';
    status.value = '';
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
        <Head title="Unpaid Establishments" />

        <main class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        Unpaid Establishments
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Establishment masterlist foundation from pending and
                        partially paid permit schedules.
                    </p>
                </div>
                <Button as-child variant="outline">
                    <a :href="exportUrl()">
                        <Download />
                        Export CSV
                    </a>
                </Button>
            </section>

            <form
                class="flex flex-col gap-3 rounded-lg border border-sidebar-border/70 bg-background p-4 xl:flex-row xl:items-end dark:border-sidebar-border"
                @submit.prevent="applyFilters"
            >
                <div class="grid gap-2 sm:w-36">
                    <label
                        for="unpaid_establishments_year"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Year
                    </label>
                    <Input
                        id="unpaid_establishments_year"
                        v-model="year"
                        name="year"
                        type="number"
                        min="2000"
                        max="2100"
                    />
                </div>
                <div class="grid gap-2 sm:w-48">
                    <label
                        for="unpaid_establishments_type"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Type
                    </label>
                    <select
                        id="unpaid_establishments_type"
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
                <div class="grid gap-2 sm:w-48">
                    <label
                        for="unpaid_establishments_status"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Status
                    </label>
                    <select
                        id="unpaid_establishments_status"
                        v-model="status"
                        name="status"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="">All unpaid</option>
                        <option
                            v-for="option in statuses"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-2 xl:min-w-72">
                    <label
                        for="unpaid_establishments_search"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Search
                    </label>
                    <Input
                        id="unpaid_establishments_search"
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
                aria-label="Unpaid establishment summary"
            >
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Unpaid Schedules
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
                        Outstanding
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ money(summary.outstanding_amount_cents) }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Partially Paid
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ summary.partially_paid_count }}
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
                    <table class="w-full min-w-[980px] table-fixed text-sm">
                        <thead
                            class="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="w-[22%] px-3 py-3 font-medium">
                                    Establishment
                                </th>
                                <th class="w-[14%] px-3 py-3 font-medium">
                                    Owner
                                </th>
                                <th class="w-[16%] px-3 py-3 font-medium">
                                    Application
                                </th>
                                <th class="w-[14%] px-3 py-3 font-medium">
                                    Line of Business
                                </th>
                                <th
                                    class="w-[12%] px-3 py-3 text-right font-medium"
                                >
                                    Outstanding
                                </th>
                                <th class="w-[12%] px-3 py-3 font-medium">
                                    Payment
                                </th>
                                <th class="w-[10%] px-3 py-3 font-medium">
                                    Due
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="rows.length === 0">
                                <td
                                    colspan="7"
                                    class="px-3 py-8 text-center text-muted-foreground"
                                >
                                    No unpaid establishments found for these
                                    filters.
                                </td>
                            </tr>
                            <tr
                                v-for="row in rows"
                                :key="row.payment_schedule_id"
                                class="border-b last:border-0"
                            >
                                <td class="px-3 py-3 align-top">
                                    <div class="break-words font-medium">
                                        {{ row.business_name }}
                                    </div>
                                    <div
                                        class="break-words text-xs text-muted-foreground"
                                    >
                                        {{ row.trade_name ?? '-' }} ·
                                        {{ row.barangay ?? '-' }}
                                    </div>
                                </td>
                                <td class="break-words px-3 py-3 align-top">
                                    {{ row.owner_name }}
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <div class="break-words font-medium">
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
                                <td class="break-words px-3 py-3 align-top">
                                    {{
                                        row.line_of_businesses.length > 0
                                            ? row.line_of_businesses.join(', ')
                                            : '-'
                                    }}
                                </td>
                                <td
                                    class="whitespace-nowrap px-3 py-3 text-right font-medium align-top"
                                >
                                    {{ money(row.outstanding_amount_cents) }}
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <Badge variant="outline">
                                        {{ label(row.schedule_status) }}
                                    </Badge>
                                    <div
                                        v-if="row.paid_amount_cents > 0"
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        Paid {{ money(row.paid_amount_cents) }}
                                    </div>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    {{ row.due_on ?? '-' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
