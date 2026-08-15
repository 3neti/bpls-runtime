<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download, Search, X } from '@lucide/vue';
import { ref } from 'vue';
import {
    download,
    index,
} from '@/actions/App/Http/Controllers/Staff/AssessmentSummaryReportController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type AssessmentSummaryRow = {
    assessment_id: number;
    assessment_sequence: number;
    assessment_status: string;
    assessed_at: string | null;
    assessed_by: string | null;
    application_id: number;
    application_number: string | null;
    application_type: string;
    application_status: string;
    application_year: number;
    business_id: number;
    business_name: string;
    trade_name: string | null;
    registration_number: string | null;
    owner_name: string;
    line_count: number;
    line_of_businesses: string[];
    tax_amount_cents: number;
    fee_amount_cents: number;
    clearance_amount_cents: number;
    other_amount_cents: number;
    total_amount_cents: number;
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
        total_amount_cents: number;
        tax_amount_cents: number;
        fee_amount_cents: number;
        clearance_amount_cents: number;
        other_amount_cents: number;
        date_basis: string;
        scope: string;
        policy_note: string;
    };
    rows: AssessmentSummaryRow[];
    types: Array<{ label: string; value: string }>;
}>();

const year = ref(String(props.filters.year));
const type = ref(props.filters.type ?? '');
const search = ref(props.filters.q ?? '');

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Assessment Summary',
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
        { preserveState: true, replace: true },
    );
}

function clearFilters(): void {
    year.value = String(new Date().getFullYear());
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

function dateTime(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function label(value: string): string {
    return value.replaceAll('_', ' ');
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Assessment Summary" />

        <main class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        Assessment Summary
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Current computed assessment snapshots grouped by
                        application.
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
                class="grid gap-3 border border-sidebar-border/70 bg-background p-4 md:grid-cols-[10rem_14rem_minmax(14rem,1fr)_auto] md:items-end dark:border-sidebar-border"
                @submit.prevent="applyFilters"
            >
                <div class="grid gap-2">
                    <label
                        for="assessment_summary_year"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Year
                    </label>
                    <Input
                        id="assessment_summary_year"
                        v-model="year"
                        name="year"
                        type="number"
                        min="2000"
                        max="2100"
                    />
                </div>
                <div class="grid gap-2">
                    <label
                        for="assessment_summary_type"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Application type
                    </label>
                    <select
                        id="assessment_summary_type"
                        v-model="type"
                        name="type"
                        class="h-9 w-full border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
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
                        for="assessment_summary_search"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Search
                    </label>
                    <Input
                        id="assessment_summary_search"
                        v-model="search"
                        name="q"
                        placeholder="Application, business, owner, registration"
                    />
                </div>
                <div class="flex gap-2">
                    <Button type="submit"><Search />Apply</Button>
                    <Button
                        type="button"
                        variant="outline"
                        aria-label="Clear filters"
                        @click="clearFilters"
                        ><X
                    /></Button>
                </div>
            </form>

            <section
                class="grid gap-3 md:grid-cols-2 xl:grid-cols-4"
                aria-label="Assessment summary totals"
            >
                <div
                    class="border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Total assessed
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ money(summary.total_amount_cents) }}
                    </div>
                </div>
                <div
                    class="border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Business tax
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ money(summary.tax_amount_cents) }}
                    </div>
                </div>
                <div
                    class="border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Fees and clearances
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{
                            money(
                                summary.fee_amount_cents +
                                    summary.clearance_amount_cents +
                                    summary.other_amount_cents,
                            )
                        }}
                    </div>
                </div>
                <div
                    class="border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Current assessments
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ summary.row_count }}
                    </div>
                    <div class="text-xs text-muted-foreground">
                        {{ summary.business_count }} businesses
                    </div>
                </div>
            </section>

            <section
                class="grid gap-2 border border-sidebar-border/70 bg-muted/20 p-4 text-sm dark:border-sidebar-border"
            >
                <p>{{ summary.scope }}</p>
                <p class="text-muted-foreground">{{ summary.policy_note }}</p>
            </section>

            <section
                class="overflow-hidden border border-sidebar-border/70 bg-background dark:border-sidebar-border"
            >
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1080px] text-left text-sm">
                        <thead
                            class="border-b bg-muted/40 text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3">Assessment</th>
                                <th class="px-4 py-3">Application</th>
                                <th class="px-4 py-3">Business</th>
                                <th class="px-4 py-3">Lines</th>
                                <th class="px-4 py-3 text-right">Tax</th>
                                <th class="px-4 py-3 text-right">Fees</th>
                                <th class="px-4 py-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border/70">
                            <tr
                                v-for="row in rows"
                                :key="row.assessment_id"
                                data-testid="assessment-summary-row"
                            >
                                <td class="px-4 py-3 align-top">
                                    <div class="font-medium">
                                        Assessment {{ row.assessment_id }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ dateTime(row.assessed_at) }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ row.assessed_by ?? 'Unassigned' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="font-medium">
                                        {{
                                            row.application_number ??
                                            'Unnumbered'
                                        }}
                                    </div>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        <Badge variant="outline">{{
                                            label(row.application_type)
                                        }}</Badge>
                                        <Badge variant="secondary">{{
                                            label(row.application_status)
                                        }}</Badge>
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
                                    <div>
                                        {{ row.line_count }} assessment lines
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{
                                            row.line_of_businesses.join(', ') ||
                                            '-'
                                        }}
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-3 text-right align-top font-medium"
                                >
                                    {{ money(row.tax_amount_cents) }}
                                </td>
                                <td class="px-4 py-3 text-right align-top">
                                    {{
                                        money(
                                            row.fee_amount_cents +
                                                row.clearance_amount_cents +
                                                row.other_amount_cents,
                                        )
                                    }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right align-top font-semibold"
                                >
                                    {{ money(row.total_amount_cents) }}
                                </td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td
                                    colspan="7"
                                    class="px-4 py-10 text-center text-muted-foreground"
                                >
                                    No current computed assessments match these
                                    filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
