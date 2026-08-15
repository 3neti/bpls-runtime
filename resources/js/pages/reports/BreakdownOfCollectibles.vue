<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download, Search, X } from '@lucide/vue';
import { ref } from 'vue';
import {
    download,
    index,
} from '@/actions/App/Http/Controllers/Staff/CollectiblesReportController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type CollectibleRow = {
    application_id: number;
    application_number: string | null;
    application_type: string;
    application_status: string;
    application_year: number;
    application_date: string | null;
    business_id: number;
    business_name: string;
    trade_name: string | null;
    business_address: string | null;
    barangay: string | null;
    owner_name: string;
    capital_investment_cents: number;
    gross_sales_cents: number;
    payment_modes: string[];
    schedule_count: number;
    q1_amount_cents: number;
    q2_amount_cents: number;
    q3_amount_cents: number;
    q4_amount_cents: number;
    unscheduled_amount_cents: number;
    total_amount_cents: number;
};

type Option = { label: string; value: string };

const props = defineProps<{
    filters: { year: number; type: string | null; q: string | null };
    summary: {
        row_count: number;
        business_count: number;
        schedule_count: number;
        q1_amount_cents: number;
        q2_amount_cents: number;
        q3_amount_cents: number;
        q4_amount_cents: number;
        unscheduled_amount_cents: number;
        total_amount_cents: number;
        date_basis: string;
        grain: string;
        scope: string;
        policy_note: string;
        legacy_discrepancy: string;
    };
    rows: CollectibleRow[];
    types: Option[];
}>();

const year = ref(String(props.filters.year));
const type = ref(props.filters.type ?? '');
const search = ref(props.filters.q ?? '');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Breakdown of Collectibles', href: index() },
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
        <Head title="Breakdown of Collectibles" />

        <main class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        Breakdown of Collectibles
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Outstanding business-permit balances by due-date
                        quarter.
                    </p>
                </div>
                <Button as-child variant="outline">
                    <a :href="download.url({ query: query() })">
                        <Download />
                        Export CSV
                    </a>
                </Button>
            </section>

            <form
                class="grid gap-3 rounded-lg border border-sidebar-border/70 bg-background p-4 md:grid-cols-2 xl:grid-cols-[10rem_14rem_minmax(18rem,1fr)_auto] xl:items-end dark:border-sidebar-border"
                @submit.prevent="applyFilters"
            >
                <div class="grid gap-2">
                    <label
                        for="collectibles_year"
                        class="text-xs font-medium text-muted-foreground uppercase"
                        >Year</label
                    >
                    <Input
                        id="collectibles_year"
                        v-model="year"
                        name="year"
                        type="number"
                        min="2000"
                        max="2100"
                    />
                </div>
                <div class="grid gap-2">
                    <label
                        for="collectibles_type"
                        class="text-xs font-medium text-muted-foreground uppercase"
                        >Application type</label
                    >
                    <select
                        id="collectibles_type"
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
                        for="collectibles_search"
                        class="text-xs font-medium text-muted-foreground uppercase"
                        >Search</label
                    >
                    <Input
                        id="collectibles_search"
                        v-model="search"
                        name="q"
                        type="search"
                        placeholder="Business, owner, address, registration, application"
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
                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                aria-label="Collectibles summary"
            >
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Establishments
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ summary.row_count }}
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{ summary.schedule_count }} outstanding schedule(s)
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Quarterly assigned
                    </div>
                    <div class="mt-2 text-xl font-semibold">
                        {{
                            money(
                                summary.q1_amount_cents +
                                    summary.q2_amount_cents +
                                    summary.q3_amount_cents +
                                    summary.q4_amount_cents,
                            )
                        }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950/30"
                >
                    <div
                        class="text-xs text-amber-800 uppercase dark:text-amber-200"
                    >
                        Unscheduled
                    </div>
                    <div
                        class="mt-2 text-xl font-semibold"
                        data-testid="collectibles-unscheduled-total"
                    >
                        {{ money(summary.unscheduled_amount_cents) }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Total collectible
                    </div>
                    <div
                        class="mt-2 text-xl font-semibold"
                        data-testid="collectibles-total"
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
                <p class="mt-1">{{ summary.legacy_discrepancy }}</p>
                <p class="mt-1 text-xs uppercase">
                    Grain: {{ label(summary.grain) }} · Date basis:
                    {{ label(summary.date_basis) }}
                </p>
            </section>

            <section
                class="grid gap-3 md:hidden"
                aria-label="Collectible records"
            >
                <div
                    v-if="rows.length === 0"
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 text-center text-sm text-muted-foreground dark:border-sidebar-border"
                >
                    No outstanding collectibles found for these filters.
                </div>
                <article
                    v-for="row in rows"
                    :key="`mobile-${row.application_id}`"
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                    data-testid="collectibles-mobile-row"
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
                                {{ row.owner_name }} ·
                                {{
                                    row.application_number ??
                                    `Application #${row.application_id}`
                                }}
                            </p>
                        </div>
                        <Badge variant="outline">{{
                            label(row.application_type)
                        }}</Badge>
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-x-3 gap-y-3 text-sm">
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Q1
                            </dt>
                            <dd class="mt-1 font-medium">
                                {{ money(row.q1_amount_cents) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Q2
                            </dt>
                            <dd class="mt-1 font-medium">
                                {{ money(row.q2_amount_cents) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Q3
                            </dt>
                            <dd class="mt-1 font-medium">
                                {{ money(row.q3_amount_cents) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Q4
                            </dt>
                            <dd class="mt-1 font-medium">
                                {{ money(row.q4_amount_cents) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Unscheduled
                            </dt>
                            <dd
                                class="mt-1 font-medium"
                                data-testid="collectibles-mobile-unscheduled"
                            >
                                {{ money(row.unscheduled_amount_cents) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Total
                            </dt>
                            <dd
                                class="mt-1 font-semibold"
                                data-testid="collectibles-mobile-total"
                            >
                                {{ money(row.total_amount_cents) }}
                            </dd>
                        </div>
                    </dl>
                </article>
            </section>

            <section
                class="hidden overflow-hidden rounded-lg border border-sidebar-border/70 bg-background md:block dark:border-sidebar-border"
            >
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1760px] table-fixed text-sm">
                        <thead
                            class="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="w-[12%] px-3 py-3 font-medium">
                                    Owner / Applicant
                                </th>
                                <th class="w-[12%] px-3 py-3 font-medium">
                                    Business
                                </th>
                                <th class="w-[15%] px-3 py-3 font-medium">
                                    Address
                                </th>
                                <th class="w-[11%] px-3 py-3 font-medium">
                                    Application
                                </th>
                                <th
                                    class="w-[9%] px-3 py-3 text-right font-medium"
                                >
                                    Capital
                                </th>
                                <th
                                    class="w-[9%] px-3 py-3 text-right font-medium"
                                >
                                    Gross sales
                                </th>
                                <th
                                    class="w-[6%] px-3 py-3 text-right font-medium"
                                >
                                    Q1
                                </th>
                                <th
                                    class="w-[6%] px-3 py-3 text-right font-medium"
                                >
                                    Q2
                                </th>
                                <th
                                    class="w-[6%] px-3 py-3 text-right font-medium"
                                >
                                    Q3
                                </th>
                                <th
                                    class="w-[6%] px-3 py-3 text-right font-medium"
                                >
                                    Q4
                                </th>
                                <th
                                    class="w-[8%] px-3 py-3 text-right font-medium"
                                >
                                    Unscheduled
                                </th>
                                <th
                                    class="w-[8%] px-3 py-3 text-right font-medium"
                                >
                                    Total
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border/70">
                            <tr v-if="rows.length === 0">
                                <td
                                    colspan="12"
                                    class="px-3 py-8 text-center text-muted-foreground"
                                >
                                    No outstanding collectibles found for these
                                    filters.
                                </td>
                            </tr>
                            <tr
                                v-for="row in rows"
                                :key="row.application_id"
                                data-testid="collectibles-row"
                                :data-application-id="row.application_id"
                            >
                                <td class="px-3 py-3 align-top break-words">
                                    {{ row.owner_name }}
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <div class="font-medium break-words">
                                        {{ row.business_name }}
                                    </div>
                                    <div
                                        class="text-xs break-words text-muted-foreground"
                                    >
                                        {{ row.trade_name ?? '-' }}
                                    </div>
                                </td>
                                <td class="px-3 py-3 align-top break-words">
                                    {{ row.business_address ?? '-'
                                    }}<span v-if="row.barangay"
                                        >, {{ row.barangay }}</span
                                    >
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <div class="font-medium break-words">
                                        {{
                                            row.application_number ??
                                            `Application #${row.application_id}`
                                        }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ label(row.application_type) }} ·
                                        {{ row.application_date ?? '-' }} ·
                                        {{
                                            row.payment_modes
                                                .map(label)
                                                .join(', ')
                                        }}
                                    </div>
                                </td>
                                <td
                                    class="px-3 py-3 text-right align-top whitespace-nowrap"
                                >
                                    {{ money(row.capital_investment_cents) }}
                                </td>
                                <td
                                    class="px-3 py-3 text-right align-top whitespace-nowrap"
                                >
                                    {{ money(row.gross_sales_cents) }}
                                </td>
                                <td
                                    class="px-3 py-3 text-right align-top whitespace-nowrap"
                                >
                                    {{ money(row.q1_amount_cents) }}
                                </td>
                                <td
                                    class="px-3 py-3 text-right align-top whitespace-nowrap"
                                >
                                    {{ money(row.q2_amount_cents) }}
                                </td>
                                <td
                                    class="px-3 py-3 text-right align-top whitespace-nowrap"
                                >
                                    {{ money(row.q3_amount_cents) }}
                                </td>
                                <td
                                    class="px-3 py-3 text-right align-top whitespace-nowrap"
                                >
                                    {{ money(row.q4_amount_cents) }}
                                </td>
                                <td
                                    class="px-3 py-3 text-right align-top font-medium whitespace-nowrap"
                                    data-testid="collectibles-unscheduled"
                                >
                                    {{ money(row.unscheduled_amount_cents) }}
                                </td>
                                <td
                                    class="px-3 py-3 text-right align-top font-semibold whitespace-nowrap"
                                    data-testid="collectibles-row-total"
                                >
                                    {{ money(row.total_amount_cents) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
