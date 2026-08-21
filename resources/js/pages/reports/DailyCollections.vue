<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download, Search, X } from '@lucide/vue';
import { ref } from 'vue';
import {
    download,
    index,
} from '@/actions/App/Http/Controllers/Staff/DailyCollectionReportController';
import ReportFamilyBanner from '@/components/reports/ReportFamilyBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type DailyCollectionRow = {
    collection_id: number;
    received_at: string;
    receipt_id: number | null;
    receipt_number: string | null;
    receipt_issued_at: string | null;
    numbering_authority: string | null;
    application_id: number;
    application_number: string | null;
    business_name: string;
    trade_name: string | null;
    owner_name: string;
    payer_name: string | null;
    reference_number: string | null;
    method: string;
    channel: string;
    collection_status: string;
    receipt_status: string | null;
    amount_cents: number;
    received_by: string | null;
    issued_by: string | null;
};

const props = defineProps<{
    filters: {
        date_from: string;
        date_to: string;
    };
    summary: {
        row_count: number;
        total_amount_cents: number;
        cash_amount_cents: number;
        manual_receipt_count: number;
        date_basis: string;
        scope: string;
        policy_note: string;
    };
    rows: DailyCollectionRow[];
}>();

const dateFrom = ref(props.filters.date_from);
const dateTo = ref(props.filters.date_to);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Daily Collections',
        href: index(),
    },
];

function applyFilters(): void {
    router.get(
        index.url({
            query: {
                date_from: dateFrom.value || undefined,
                date_to: dateTo.value || undefined,
            },
        }),
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
    router.get(index.url(), {}, { preserveState: true, replace: true });
}

function exportUrl(): string {
    return download.url({
        query: {
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
        },
    });
}

function money(amountCents: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amountCents / 100);
}

function dateTime(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function label(value: string | null): string {
    return value ? value.replaceAll('_', ' ') : '-';
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Daily Collections" />

        <main class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        Daily Collections
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Read-only Treasury collection report for receipted
                        permit collections.
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
                        for="daily_collection_date_from"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        From
                    </label>
                    <Input
                        id="daily_collection_date_from"
                        v-model="dateFrom"
                        name="date_from"
                        type="date"
                    />
                </div>
                <div class="grid gap-2 md:w-56">
                    <label
                        for="daily_collection_date_to"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        To
                    </label>
                    <Input
                        id="daily_collection_date_to"
                        v-model="dateTo"
                        name="date_to"
                        type="date"
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
                aria-label="Daily collection summary"
            >
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Total Collections
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
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
                        Cash Collections
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ money(summary.cash_amount_cents) }}
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
                        Receipts
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ summary.row_count }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Manual ORs
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ summary.manual_receipt_count }}
                    </div>
                </div>
            </section>

            <section
                class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100"
            >
                <p class="font-medium">Report scope</p>
                <p class="mt-1">{{ summary.scope }}</p>
                <p class="mt-1">
                    Official cutoff times, non-permit collections,
                    voids/reversals, and the final abstract format still need
                    municipal confirmation.
                </p>
                <p class="mt-1 text-xs uppercase">
                    Records dated by: {{ label(summary.date_basis) }}
                </p>
            </section>

            <section
                class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background dark:border-sidebar-border"
            >
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1120px] text-sm">
                        <thead
                            class="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3 font-medium">Receipt</th>
                                <th class="px-4 py-3 font-medium">Received</th>
                                <th class="px-4 py-3 font-medium">Payer</th>
                                <th class="px-4 py-3 font-medium">Business</th>
                                <th class="px-4 py-3 font-medium">
                                    Application
                                </th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Amount
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="rows.length === 0">
                                <td
                                    colspan="7"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    No matching sample data for this date range.
                                </td>
                            </tr>
                            <tr
                                v-for="row in rows"
                                :key="row.collection_id"
                                class="border-b last:border-0"
                            >
                                <td class="px-4 py-3 align-top">
                                    <div class="font-medium">
                                        {{ row.receipt_number }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ label(row.numbering_authority) }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    {{ dateTime(row.received_at) }}
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div>{{ row.payer_name ?? '-' }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ row.reference_number ?? '-' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="font-medium">
                                        {{ row.business_name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ row.owner_name }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    {{
                                        row.application_number ??
                                        `Application #${row.application_id}`
                                    }}
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="flex flex-wrap gap-2">
                                        <Badge variant="outline">
                                            {{ label(row.collection_status) }}
                                        </Badge>
                                        <Badge variant="secondary">
                                            {{ label(row.receipt_status) }}
                                        </Badge>
                                        <Badge variant="outline">
                                            {{ label(row.method) }}
                                        </Badge>
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-3 text-right align-top font-medium"
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
