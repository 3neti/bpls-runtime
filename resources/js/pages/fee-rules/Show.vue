<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import {
    index,
    show,
} from '@/actions/App/Http/Controllers/Staff/FeeRuleController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type FeeRuleRange = {
    id: number;
    min_basis_cents: number;
    max_basis_cents: number | null;
    amount_cents: number;
    rate_basis_points: number | null;
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
    ranges: FeeRuleRange[];
};

const props = defineProps<{
    feeRule: FeeRule;
    scopeNote: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Taxes and Fees',
        href: index(),
    },
    {
        title: props.feeRule.code,
        href: show(props.feeRule.id),
    },
];

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

function basisRange(range: FeeRuleRange): string {
    if (range.max_basis_cents === null) {
        return `${money(range.min_basis_cents)} and above`;
    }

    return `${money(range.min_basis_cents)} to ${money(range.max_basis_cents)}`;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="feeRule.code" />

        <main class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1
                            class="break-words text-xl font-semibold text-foreground"
                        >
                            {{ feeRule.code }}
                        </h1>
                        <Badge
                            :variant="feeRule.is_active ? 'default' : 'outline'"
                        >
                            {{ feeRule.is_active ? 'Active' : 'Inactive' }}
                        </Badge>
                        <Badge v-if="feeRule.catalog_status" variant="outline">
                            {{ label(feeRule.catalog_status) }}
                        </Badge>
                    </div>
                    <p
                        class="mt-1 max-w-4xl break-words text-sm text-muted-foreground"
                    >
                        {{ feeRule.name }}
                    </p>
                </div>

                <Button as-child variant="outline">
                    <Link :href="index()">
                        <ArrowLeft />
                        Back to catalog
                    </Link>
                </Button>
            </section>

            <section
                class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100"
            >
                <p class="font-medium">Read-only policy boundary</p>
                <p class="mt-1">
                    {{ scopeNote }}
                </p>
            </section>

            <section class="grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <h2 class="text-sm font-semibold text-foreground">
                        Rule Summary
                    </h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Category
                            </dt>
                            <dd class="mt-1">
                                {{ label(feeRule.category) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Scope
                            </dt>
                            <dd class="mt-1">
                                {{ label(feeRule.scope) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Calculation
                            </dt>
                            <dd class="mt-1">
                                {{ label(feeRule.calculation_type) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Basis
                            </dt>
                            <dd class="mt-1">
                                {{ label(feeRule.basis) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Amount
                            </dt>
                            <dd class="mt-1 font-medium">
                                {{ money(feeRule.amount_cents) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Rate Basis Points
                            </dt>
                            <dd class="mt-1">
                                {{ feeRule.rate_basis_points ?? '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Effective From
                            </dt>
                            <dd class="mt-1">
                                {{ feeRule.effective_from ?? '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Effective Until
                            </dt>
                            <dd class="mt-1">
                                {{ feeRule.effective_until ?? 'No end date' }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <h2 class="text-sm font-semibold text-foreground">
                        Applicability and Evidence
                    </h2>
                    <dl class="mt-4 grid gap-4">
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Line of Business
                            </dt>
                            <dd class="mt-1 break-words">
                                {{
                                    feeRule.line_of_business?.name ??
                                    'Application-wide'
                                }}
                            </dd>
                            <dd
                                v-if="feeRule.line_of_business?.code"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{ feeRule.line_of_business.code }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Application Types
                            </dt>
                            <dd class="mt-1">
                                {{ applicability(feeRule.application_types) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Legal Basis
                            </dt>
                            <dd class="mt-1 break-words">
                                {{ feeRule.legal_basis ?? '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground uppercase">
                                Legacy Source
                            </dt>
                            <dd class="mt-1 break-words">
                                {{ feeRule.legacy_source_id ?? '-' }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section
                v-if="feeRule.policy_note || feeRule.policy_boundaries.length > 0"
                class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
            >
                <h2 class="text-sm font-semibold text-foreground">
                    Policy Boundaries
                </h2>
                <p
                    v-if="feeRule.policy_note"
                    class="mt-3 text-sm text-muted-foreground"
                >
                    {{ feeRule.policy_note }}
                </p>
                <div
                    v-if="feeRule.policy_boundaries.length > 0"
                    class="mt-3 flex flex-wrap gap-2"
                >
                    <Badge
                        v-for="boundary in feeRule.policy_boundaries"
                        :key="boundary"
                        variant="outline"
                    >
                        {{ label(boundary) }}
                    </Badge>
                </div>
            </section>

            <section
                class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background dark:border-sidebar-border"
            >
                <div class="border-b p-4">
                    <h2 class="text-sm font-semibold text-foreground">
                        Ranges
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Persisted range brackets for this rule. These brackets
                        are evidence for assessment behavior; unresolved formula
                        semantics remain explicit policy boundaries.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[680px] table-fixed text-sm">
                        <thead
                            class="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="w-[45%] px-3 py-3 font-medium">
                                    Basis Range
                                </th>
                                <th
                                    class="w-[25%] px-3 py-3 text-right font-medium"
                                >
                                    Amount
                                </th>
                                <th
                                    class="w-[30%] px-3 py-3 text-right font-medium"
                                >
                                    Rate Basis Points
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="feeRule.ranges.length === 0">
                                <td
                                    colspan="3"
                                    class="px-3 py-8 text-center text-muted-foreground"
                                >
                                    This rule has no persisted range brackets.
                                </td>
                            </tr>
                            <tr
                                v-for="range in feeRule.ranges"
                                :key="range.id"
                                class="border-b last:border-0"
                            >
                                <td class="px-3 py-3">
                                    {{ basisRange(range) }}
                                </td>
                                <td class="px-3 py-3 text-right font-medium">
                                    {{ money(range.amount_cents) }}
                                </td>
                                <td class="px-3 py-3 text-right">
                                    {{ range.rate_basis_points ?? '-' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
