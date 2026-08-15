<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Search, X } from '@lucide/vue';
import { ref } from 'vue';
import {
    index,
    show,
} from '@/actions/App/Http/Controllers/Staff/FeeRuleController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Option = {
    label: string;
    value: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type FeeRule = {
    id: number;
    code: string;
    name: string;
    category: string;
    scope: string;
    calculation_type: string;
    basis: string;
    amount_cents: number;
    rate_basis_points: number | null;
    effective_from: string | null;
    effective_until: string | null;
    is_active: boolean;
    legal_basis: string | null;
    legacy_source_id: string | null;
    line_of_business: {
        id: number;
        code: string | null;
        name: string;
    } | null;
    range_count: number;
    catalog_status: string | null;
    application_types: string[] | null;
    policy_boundaries: string[];
    policy_note: string | null;
    reconciliation_required: boolean;
    current_reconciliation: {
        execution_status: string;
        execution_reason: string;
    } | null;
};

const props = defineProps<{
    filters: {
        q: string;
        category: string;
        scope: string;
        calculation_type: string;
        status: string;
    };
    feeRules: {
        data: FeeRule[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    summary: {
        total_rules: number;
        active_rules: number;
        mrc_rules: number;
        blocked_policy_count: number;
        executable_rule_count: number;
    };
    categories: Option[];
    scopes: Option[];
    calculationTypes: Option[];
}>();

const search = ref(props.filters.q);
const category = ref(props.filters.category);
const scope = ref(props.filters.scope);
const calculationType = ref(props.filters.calculation_type);
const status = ref(props.filters.status);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Taxes and Fees',
        href: index(),
    },
];

function query(): Record<string, string | undefined> {
    return {
        q: search.value || undefined,
        category: category.value || undefined,
        scope: scope.value || undefined,
        calculation_type: calculationType.value || undefined,
        status: status.value || undefined,
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
    search.value = '';
    category.value = '';
    scope.value = '';
    calculationType.value = '';
    status.value = 'active';
    router.get(
        index.url({ query: query() }),
        {},
        {
            preserveState: true,
            replace: true,
        },
    );
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

function applicability(applicationTypes: string[] | null): string {
    if (!applicationTypes || applicationTypes.length === 0) {
        return 'All application types';
    }

    return applicationTypes.map(label).join(', ');
}

function decodePaginationLabel(value: string): string {
    return value
        .replace('&laquo; Previous', 'Previous')
        .replace('Next &raquo;', 'Next')
        .replace('&laquo;', 'Previous')
        .replace('&raquo;', 'Next');
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Taxes and Fees" />

        <main class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        Taxes and Fees
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Read-only Revenue Code fee-rule catalog with legal
                        provenance and unresolved policy boundaries.
                    </p>
                </div>
            </section>

            <form
                class="grid gap-3 rounded-lg border border-sidebar-border/70 bg-background p-4 md:grid-cols-2 lg:grid-cols-6 dark:border-sidebar-border"
                @submit.prevent="applyFilters"
            >
                <div class="grid gap-2 lg:col-span-2">
                    <label
                        for="fee_rule_search"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Search
                    </label>
                    <Input
                        id="fee_rule_search"
                        v-model="search"
                        name="q"
                        type="search"
                        placeholder="Code, name, source, or business line"
                    />
                </div>
                <div class="grid gap-2">
                    <label
                        for="fee_rule_category"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Category
                    </label>
                    <select
                        id="fee_rule_category"
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
                <div class="grid gap-2">
                    <label
                        for="fee_rule_scope"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Scope
                    </label>
                    <select
                        id="fee_rule_scope"
                        v-model="scope"
                        name="scope"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="">All scopes</option>
                        <option
                            v-for="option in scopes"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <label
                        for="fee_rule_calculation_type"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Calculation
                    </label>
                    <select
                        id="fee_rule_calculation_type"
                        v-model="calculationType"
                        name="calculation_type"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="">All types</option>
                        <option
                            v-for="option in calculationTypes"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <label
                        for="fee_rule_status"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Status
                    </label>
                    <select
                        id="fee_rule_status"
                        v-model="status"
                        name="status"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="">All statuses</option>
                    </select>
                </div>
                <div class="flex gap-2 lg:col-span-6">
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
                class="grid gap-3 md:grid-cols-3 xl:grid-cols-5"
                aria-label="Fee catalog summary"
            >
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Total Rules
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ summary.total_rules }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Active
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ summary.active_rules }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Revenue Code
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ summary.mrc_rules }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Blocked Rules
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ summary.blocked_policy_count }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Executable Rules
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ summary.executable_rule_count }}
                    </div>
                </div>
            </section>

            <section
                class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100"
            >
                <p class="font-medium">Catalog scope</p>
                <p class="mt-1">
                    This page exposes persisted fee rules for review. It does
                    not grant authority to edit rates, invent formula behavior,
                    resolve PIL, or declare the Revenue Code catalog complete.
                </p>
            </section>

            <section
                class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background dark:border-sidebar-border"
            >
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[920px] table-fixed text-sm">
                        <thead
                            class="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="w-[22%] px-3 py-3 font-medium">
                                    Rule
                                </th>
                                <th class="w-[12%] px-3 py-3 font-medium">
                                    Category
                                </th>
                                <th class="w-[16%] px-3 py-3 font-medium">
                                    Applicability
                                </th>
                                <th class="w-[14%] px-3 py-3 font-medium">
                                    Calculation
                                </th>
                                <th
                                    class="w-[11%] px-3 py-3 text-right font-medium"
                                >
                                    Amount
                                </th>
                                <th class="w-[20%] px-3 py-3 font-medium">
                                    Evidence
                                </th>
                                <th
                                    class="w-[5%] px-3 py-3 text-right font-medium"
                                >
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="feeRules.data.length === 0">
                                <td
                                    colspan="7"
                                    class="px-3 py-8 text-center text-muted-foreground"
                                >
                                    No fee rules match the current filters.
                                </td>
                            </tr>
                            <tr
                                v-for="rule in feeRules.data"
                                :key="rule.id"
                                class="border-b last:border-0"
                            >
                                <td class="px-3 py-3 align-top">
                                    <div class="font-medium break-words">
                                        {{ rule.code }}
                                    </div>
                                    <div
                                        class="mt-1 text-xs break-words text-muted-foreground"
                                    >
                                        {{ rule.name }}
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        <Badge
                                            :variant="
                                                rule.is_active
                                                    ? 'default'
                                                    : 'outline'
                                            "
                                        >
                                            {{
                                                rule.is_active
                                                    ? 'Active'
                                                    : 'Inactive'
                                            }}
                                        </Badge>
                                        <Badge
                                            v-if="rule.catalog_status"
                                            variant="outline"
                                        >
                                            {{ label(rule.catalog_status) }}
                                        </Badge>
                                        <Badge
                                            v-if="rule.current_reconciliation"
                                            :variant="
                                                rule.current_reconciliation
                                                    .execution_status ===
                                                'executable'
                                                    ? 'default'
                                                    : 'destructive'
                                            "
                                        >
                                            {{
                                                label(
                                                    rule.current_reconciliation
                                                        .execution_status,
                                                )
                                            }}
                                        </Badge>
                                    </div>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <Badge variant="outline">
                                        {{ label(rule.category) }}
                                    </Badge>
                                    <div
                                        class="mt-2 text-xs text-muted-foreground"
                                    >
                                        {{ label(rule.scope) }}
                                    </div>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <div>
                                        {{
                                            rule.line_of_business?.name ??
                                            'Application-wide'
                                        }}
                                    </div>
                                    <div
                                        v-if="rule.line_of_business?.code"
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{ rule.line_of_business.code }}
                                    </div>
                                    <div
                                        class="mt-2 text-xs text-muted-foreground"
                                    >
                                        {{
                                            applicability(
                                                rule.application_types,
                                            )
                                        }}
                                    </div>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <div>
                                        {{ label(rule.calculation_type) }}
                                    </div>
                                    <div
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        Basis: {{ label(rule.basis) }}
                                    </div>
                                    <div
                                        v-if="rule.range_count > 0"
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        Ranges: {{ rule.range_count }}
                                    </div>
                                    <div
                                        v-if="rule.rate_basis_points !== null"
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        Rate bps:
                                        {{ rule.rate_basis_points }}
                                    </div>
                                </td>
                                <td
                                    class="px-3 py-3 text-right align-top font-medium"
                                >
                                    {{ money(rule.amount_cents) }}
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <div class="break-words">
                                        {{ rule.legal_basis ?? '-' }}
                                    </div>
                                    <div
                                        v-if="rule.legacy_source_id"
                                        class="mt-1 text-xs break-words text-muted-foreground"
                                    >
                                        {{ rule.legacy_source_id }}
                                    </div>
                                    <div
                                        v-if="rule.policy_note"
                                        class="mt-2 text-xs text-amber-700 dark:text-amber-200"
                                    >
                                        {{ rule.policy_note }}
                                    </div>
                                    <div
                                        v-if="rule.policy_boundaries.length > 0"
                                        class="mt-2 flex flex-wrap gap-1"
                                    >
                                        <span
                                            v-for="boundary in rule.policy_boundaries"
                                            :key="boundary"
                                            class="rounded-md border border-sidebar-border/70 px-2 py-0.5 text-xs leading-snug break-words whitespace-normal text-muted-foreground dark:border-sidebar-border"
                                        >
                                            {{ label(boundary) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-right align-top">
                                    <Button
                                        as-child
                                        size="sm"
                                        variant="outline"
                                    >
                                        <Link
                                            :href="show(rule.id)"
                                            :aria-label="`View ${rule.code}`"
                                        >
                                            View
                                        </Link>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                class="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground"
            >
                <div>
                    Showing {{ feeRules.from ?? 0 }} to
                    {{ feeRules.to ?? 0 }} of {{ feeRules.total }} rules
                </div>
                <div class="flex flex-wrap gap-1">
                    <Button
                        v-for="link in feeRules.links"
                        :key="`${link.label}:${link.url}`"
                        as-child
                        size="sm"
                        :variant="link.active ? 'default' : 'outline'"
                        :disabled="!link.url"
                    >
                        <Link v-if="link.url" :href="link.url" preserve-state>
                            {{ decodePaginationLabel(link.label) }}
                        </Link>
                        <span v-else>
                            {{ decodePaginationLabel(link.label) }}
                        </span>
                    </Button>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
