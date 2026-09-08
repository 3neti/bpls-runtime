<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Search, X } from '@lucide/vue';
import { computed, ref } from 'vue';
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
    revenue_code: string | null;
    source_name: string;
    business_division: {
        code: string;
        name: string;
        source_name: string;
    } | null;
    lines_of_business: {
        id: number;
        code: string | null;
        name: string;
        source_name: string;
    }[];
    applies_to: string[];
    owner: string;
    determination_channel: string;
    amount_display: string;
    amount_basis: string;
    raw_formula: string | null;
    display_status: string;
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

type RevenueCodeProvision = {
    id: number;
    code: string;
    section_reference: string;
    title: string;
    provision_type: string;
    evidence_summary: string;
    reconciliation_status: string;
    reconciliation_notes: string | null;
    known_ambiguities: string[];
    fee_rule: {
        id: number;
        code: string;
        name: string;
        execution_status: string | null;
    } | null;
};

type RevenueCodeScheduleIssue = {
    type: string;
    related_row_code?: string;
};

type RevenueCodeScheduleRow = {
    id: number;
    sequence: number;
    code: string;
    source_basis_text: string;
    source_value_text: string;
    basis_from_cents: number | null;
    basis_below_cents: number | null;
    amount_cents: number | null;
    rate_basis_points: string | null;
    is_ceiling: boolean;
    normalization_status: string;
    normalization_notes: string | null;
    issues: RevenueCodeScheduleIssue[];
};

type RevenueCodeScheduleMatrix = {
    provision: {
        id: number;
        code: string;
        section_reference: string;
        title: string;
        reconciliation_status: string;
        linked_fee_rule_code: string | null;
        linked_fee_rule_execution_status: string | null;
    };
    summary: {
        row_count: number;
        exact_row_count: number;
        reconciliation_required_count: number;
        overlap_count: number;
        gap_count: number;
        ceiling_count: number;
        execution_ready: boolean;
    };
    rows: RevenueCodeScheduleRow[];
};

type RevenueCodePolicyBoundary = {
    provision: {
        code: string;
        section_reference: string;
        title: string;
        reconciliation_status: string;
    };
    clauses: {
        id: number;
        sequence: number;
        code: string;
        clause_type: string;
        source_text: string;
        candidate_interpretation: string;
        amount_cents: number | null;
        rate_basis_points: string | null;
        is_ceiling: boolean;
        reconciliation_status: string;
        execution_blocker: string;
        candidate_values_are_non_executable: boolean;
    }[];
};

const props = defineProps<{
    filters: {
        q: string;
        category: string;
        scope: string;
        calculation_type: string;
        business_division: string;
        office: string;
        determination_channel: string;
        application_type: string;
        year: string | number;
        status: string;
        revenue_code: string;
    };
    feeRules: {
        data: FeeRule[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    revenueCodeProvisions: RevenueCodeProvision[];
    revenueCodeScheduleMatrices: RevenueCodeScheduleMatrix[];
    revenueCodePolicyBoundaries: RevenueCodePolicyBoundary[];
    summary: {
        total_rules: number;
        active_rules: number;
        mrc_rules: number;
        blocked_policy_count: number;
        executable_rule_count: number;
        provisions_recorded: number;
        provisions_requiring_reconciliation: number;
        provisions_linked_to_rules: number;
        policy_boundary_clauses: number;
        policy_boundary_clauses_requiring_reconciliation: number;
    };
    categories: Option[];
    scopes: Option[];
    calculationTypes: Option[];
    businessDivisions: Option[];
    offices: Option[];
    determinationChannels: Option[];
}>();

const search = ref(props.filters.q);
const category = ref(props.filters.category);
const scope = ref(props.filters.scope);
const calculationType = ref(props.filters.calculation_type);
const businessDivision = ref(props.filters.business_division);
const office = ref(props.filters.office);
const determinationChannel = ref(props.filters.determination_channel);
const applicationType = ref(props.filters.application_type);
const year = ref(props.filters.year ? String(props.filters.year) : '');
const status = ref(props.filters.status);
const revenueCode = ref(props.filters.revenue_code);
const activeScheduleCode = ref('MRC-2A-02-B-WHOLESALERS');
const activeScheduleMatrix = computed(
    () =>
        props.revenueCodeScheduleMatrices.find(
            (matrix) => matrix.provision.code === activeScheduleCode.value,
        ) ?? props.revenueCodeScheduleMatrices[0],
);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Fee and Rule Catalog',
        href: index(),
    },
];

function query(): Record<string, string | undefined> {
    return {
        q: search.value || undefined,
        category: category.value || undefined,
        scope: scope.value || undefined,
        calculation_type: calculationType.value || undefined,
        business_division: businessDivision.value || undefined,
        office: office.value || undefined,
        determination_channel: determinationChannel.value || undefined,
        application_type: applicationType.value || undefined,
        year: year.value || undefined,
        status: status.value || undefined,
        revenue_code: revenueCode.value || undefined,
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
    businessDivision.value = '';
    office.value = '';
    determinationChannel.value = '';
    applicationType.value = '';
    year.value = '';
    status.value = 'active';
    revenueCode.value = '';
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

function candidateBasis(row: RevenueCodeScheduleRow): string {
    const from = row.basis_from_cents;
    const below = row.basis_below_cents;

    if (from === null && below === null) {
        return '-';
    }

    if (from === 0 && below !== null) {
        return `Below ${money(below)}`;
    }

    if (from !== null && below === null) {
        return `${money(from)} and above`;
    }

    return `${money(from ?? 0)} to below ${money(below ?? 0)}`;
}

function candidateValue(row: RevenueCodeScheduleRow): string {
    if (row.amount_cents !== null) {
        return money(row.amount_cents);
    }

    if (row.rate_basis_points !== null) {
        return `${row.rate_basis_points} basis points ceiling`;
    }

    return '-';
}

function clauseCandidateValue(
    clause: RevenueCodePolicyBoundary['clauses'][number],
): string | null {
    if (clause.amount_cents !== null) {
        return `${money(clause.amount_cents)}${clause.is_ceiling ? ' ceiling' : ''}`;
    }

    if (clause.rate_basis_points !== null) {
        return `${clause.rate_basis_points} basis points${clause.is_ceiling ? ' ceiling' : ''}`;
    }

    return null;
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
        <Head title="Municipal Fees" />

        <main class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        Municipal Fees
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Search the active municipal catalogue by division,
                        owner, and applicability.
                    </p>
                </div>
                <Button as-child variant="outline">
                    <Link :href="index.url() + '/legacy-candidates'">
                        Legacy candidates
                    </Link>
                </Button>
            </section>

            <form
                class="grid gap-3 rounded-lg border border-sidebar-border/70 bg-background p-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 dark:border-sidebar-border"
                @submit.prevent="applyFilters"
            >
                <div class="grid gap-2 sm:col-span-2">
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
                        for="fee_rule_division"
                        class="text-xs font-medium text-muted-foreground uppercase"
                        >Business Division</label
                    >
                    <select
                        id="fee_rule_division"
                        v-model="businessDivision"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                    >
                        <option value="">All divisions</option>
                        <option
                            v-for="option in businessDivisions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <label
                        for="fee_rule_owner"
                        class="text-xs font-medium text-muted-foreground uppercase"
                        >Owner</label
                    >
                    <select
                        id="fee_rule_owner"
                        v-model="office"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                    >
                        <option value="">All offices</option>
                        <option
                            v-for="option in offices"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <label
                        for="fee_rule_channel"
                        class="text-xs font-medium text-muted-foreground uppercase"
                        >Workflow</label
                    >
                    <select
                        id="fee_rule_channel"
                        v-model="determinationChannel"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                    >
                        <option value="">All workflows</option>
                        <option
                            v-for="option in determinationChannels"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <label
                        for="fee_rule_application_type"
                        class="text-xs font-medium text-muted-foreground uppercase"
                        >Application</label
                    >
                    <select
                        id="fee_rule_application_type"
                        v-model="applicationType"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                    >
                        <option value="">New and renewal</option>
                        <option value="new">New</option>
                        <option value="renewal">Renewal</option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <label
                        for="fee_rule_year"
                        class="text-xs font-medium text-muted-foreground uppercase"
                        >Effective year</label
                    >
                    <Input
                        id="fee_rule_year"
                        v-model="year"
                        inputmode="numeric"
                        placeholder="2025"
                    />
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
                        for="fee_rule_revenue_code"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Revenue code
                    </label>
                    <select
                        id="fee_rule_revenue_code"
                        v-model="revenueCode"
                        name="revenue_code"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="">Recorded and missing</option>
                        <option value="recorded">Recorded</option>
                        <option value="missing">Missing</option>
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
                        <option value="incomplete">Incomplete</option>
                        <option value="inactive">Inactive</option>
                        <option value="superseded">Superseded</option>
                        <option value="">All statuses</option>
                    </select>
                </div>
                <div
                    class="flex gap-2 sm:col-span-2 lg:col-span-4 xl:col-span-5"
                >
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
                        Awaiting confirmation
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ summary.blocked_policy_count }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                >
                    <div class="text-xs text-muted-foreground uppercase">
                        Available for assessment
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ summary.executable_rule_count }}
                    </div>
                </div>
            </section>

            <details
                class="rounded-lg border border-sidebar-border/70 bg-background dark:border-sidebar-border"
            >
                <summary
                    class="cursor-pointer px-4 py-3 font-semibold text-foreground"
                >
                    Technical Revenue Code review details
                </summary>
                <div class="grid gap-4 border-t p-4">
                    <section
                        class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background dark:border-sidebar-border"
                        aria-labelledby="revenue-code-coverage-heading"
                        data-testid="revenue-code-provision-register"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3 border-b px-4 py-3"
                        >
                            <div>
                                <h2
                                    id="revenue-code-coverage-heading"
                                    class="font-semibold"
                                >
                                    Revenue Code provision coverage
                                </h2>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Legal provisions are recorded independently
                                    from executable fee rules. Coverage does not
                                    authorize a calculation.
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2 text-xs">
                                <Badge variant="outline">
                                    {{ summary.provisions_recorded }} recorded
                                </Badge>
                                <Badge variant="destructive">
                                    {{
                                        summary.provisions_requiring_reconciliation
                                    }}
                                    require reconciliation
                                </Badge>
                                <Badge variant="outline">
                                    {{ summary.provisions_linked_to_rules }}
                                    linked to rules
                                </Badge>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table
                                class="w-full min-w-[960px] table-fixed text-sm"
                            >
                                <thead
                                    class="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase"
                                >
                                    <tr>
                                        <th
                                            class="w-[16%] px-3 py-3 font-medium"
                                        >
                                            Provision
                                        </th>
                                        <th
                                            class="w-[20%] px-3 py-3 font-medium"
                                        >
                                            Subject
                                        </th>
                                        <th
                                            class="w-[29%] px-3 py-3 font-medium"
                                        >
                                            Evidence coverage
                                        </th>
                                        <th
                                            class="w-[22%] px-3 py-3 font-medium"
                                        >
                                            Reconciliation
                                        </th>
                                        <th
                                            class="w-[13%] px-3 py-3 font-medium"
                                        >
                                            Executable rule
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="provision in revenueCodeProvisions"
                                        :key="provision.id"
                                        :id="
                                            'revenue-code-provision-' +
                                            provision.code
                                        "
                                        class="border-b last:border-0"
                                        :data-provision-code="provision.code"
                                    >
                                        <td class="px-3 py-3 align-top">
                                            <div class="font-medium">
                                                {{
                                                    provision.section_reference
                                                }}
                                            </div>
                                            <div
                                                class="mt-1 text-xs break-words text-muted-foreground"
                                            >
                                                {{ provision.code }}
                                            </div>
                                            <Badge
                                                class="mt-2"
                                                variant="outline"
                                            >
                                                {{
                                                    label(
                                                        provision.provision_type,
                                                    )
                                                }}
                                            </Badge>
                                        </td>
                                        <td class="px-3 py-3 align-top">
                                            {{ provision.title }}
                                            <div
                                                v-if="
                                                    provision.known_ambiguities
                                                        .length > 0
                                                "
                                                class="mt-2 flex flex-wrap gap-1"
                                            >
                                                <span
                                                    v-for="ambiguity in provision.known_ambiguities"
                                                    :key="ambiguity"
                                                    class="rounded-md border border-sidebar-border/70 px-2 py-0.5 text-xs leading-snug break-words text-muted-foreground dark:border-sidebar-border"
                                                >
                                                    {{ label(ambiguity) }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 align-top text-xs">
                                            {{ provision.evidence_summary }}
                                        </td>
                                        <td class="px-3 py-3 align-top">
                                            <Badge
                                                :variant="
                                                    provision.reconciliation_status ===
                                                    'reconciled'
                                                        ? 'default'
                                                        : 'destructive'
                                                "
                                            >
                                                {{
                                                    label(
                                                        provision.reconciliation_status,
                                                    )
                                                }}
                                            </Badge>
                                            <p
                                                v-if="
                                                    provision.reconciliation_notes
                                                "
                                                class="mt-2 text-xs text-muted-foreground"
                                            >
                                                {{
                                                    provision.reconciliation_notes
                                                }}
                                            </p>
                                        </td>
                                        <td class="px-3 py-3 align-top">
                                            <template v-if="provision.fee_rule">
                                                <Link
                                                    :href="
                                                        show(
                                                            provision.fee_rule
                                                                .id,
                                                        ).url
                                                    "
                                                    class="font-medium text-primary underline-offset-4 hover:underline"
                                                >
                                                    {{
                                                        provision.fee_rule.code
                                                    }}
                                                </Link>
                                                <Badge
                                                    v-if="
                                                        provision.fee_rule
                                                            .execution_status
                                                    "
                                                    class="mt-2"
                                                    :variant="
                                                        provision.fee_rule
                                                            .execution_status ===
                                                        'executable'
                                                            ? 'default'
                                                            : 'destructive'
                                                    "
                                                >
                                                    {{
                                                        label(
                                                            provision.fee_rule
                                                                .execution_status,
                                                        )
                                                    }}
                                                </Badge>
                                            </template>
                                            <span
                                                v-else
                                                class="text-xs text-muted-foreground"
                                            >
                                                No executable rule
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section
                        class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background dark:border-sidebar-border"
                        aria-labelledby="revenue-code-policy-boundaries-heading"
                        data-testid="revenue-code-policy-boundary-register"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3 border-b px-4 py-3"
                        >
                            <div>
                                <h2
                                    id="revenue-code-policy-boundaries-heading"
                                    class="font-semibold"
                                >
                                    Non-schedule policy boundaries
                                </h2>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Exact clauses and candidate facts are
                                    preserved for municipal reconciliation. They
                                    cannot execute an assessment.
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2 text-xs">
                                <Badge variant="outline">
                                    {{ summary.policy_boundary_clauses }}
                                    clauses
                                </Badge>
                                <Badge variant="destructive">
                                    {{
                                        summary.policy_boundary_clauses_requiring_reconciliation
                                    }}
                                    non-executable
                                </Badge>
                            </div>
                        </div>

                        <div class="divide-y">
                            <div
                                v-for="boundary in revenueCodePolicyBoundaries"
                                :key="boundary.provision.code"
                                class="px-4 py-4"
                                :data-policy-provision-code="
                                    boundary.provision.code
                                "
                            >
                                <div
                                    class="mb-3 flex flex-wrap items-center justify-between gap-2"
                                >
                                    <div>
                                        <h3 class="text-sm font-semibold">
                                            {{
                                                boundary.provision
                                                    .section_reference
                                            }}
                                            · {{ boundary.provision.title }}
                                        </h3>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{ boundary.provision.code }}
                                        </p>
                                    </div>
                                    <Badge variant="destructive">
                                        {{
                                            label(
                                                boundary.provision
                                                    .reconciliation_status,
                                            )
                                        }}
                                    </Badge>
                                </div>

                                <div class="overflow-x-auto">
                                    <table
                                        class="w-full min-w-[980px] table-fixed text-sm"
                                    >
                                        <thead
                                            class="border-y bg-muted/30 text-left text-xs text-muted-foreground uppercase"
                                        >
                                            <tr>
                                                <th
                                                    class="w-[16%] px-3 py-2 font-medium"
                                                >
                                                    Boundary
                                                </th>
                                                <th
                                                    class="w-[29%] px-3 py-2 font-medium"
                                                >
                                                    Ordinance evidence
                                                </th>
                                                <th
                                                    class="w-[27%] px-3 py-2 font-medium"
                                                >
                                                    Candidate fact
                                                </th>
                                                <th
                                                    class="w-[28%] px-3 py-2 font-medium"
                                                >
                                                    Execution refusal
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="clause in boundary.clauses"
                                                :key="clause.id"
                                                class="border-b last:border-0"
                                                :data-policy-clause-code="
                                                    clause.code
                                                "
                                            >
                                                <td class="px-3 py-3 align-top">
                                                    <Badge variant="outline">
                                                        {{
                                                            label(
                                                                clause.clause_type,
                                                            )
                                                        }}
                                                    </Badge>
                                                    <div
                                                        class="mt-2 text-xs break-words text-muted-foreground"
                                                    >
                                                        {{ clause.code }}
                                                    </div>
                                                </td>
                                                <td
                                                    class="px-3 py-3 align-top text-xs leading-relaxed"
                                                >
                                                    {{ clause.source_text }}
                                                </td>
                                                <td class="px-3 py-3 align-top">
                                                    <Badge
                                                        v-if="
                                                            clauseCandidateValue(
                                                                clause,
                                                            )
                                                        "
                                                        class="mb-2"
                                                        variant="outline"
                                                    >
                                                        {{
                                                            clauseCandidateValue(
                                                                clause,
                                                            )
                                                        }}
                                                    </Badge>
                                                    <p
                                                        class="text-xs leading-relaxed"
                                                    >
                                                        {{
                                                            clause.candidate_interpretation
                                                        }}
                                                    </p>
                                                    <p
                                                        v-if="
                                                            clause.candidate_values_are_non_executable
                                                        "
                                                        class="mt-2 text-xs font-medium text-destructive"
                                                    >
                                                        Candidate values are
                                                        non-executable.
                                                    </p>
                                                </td>
                                                <td
                                                    class="px-3 py-3 align-top text-xs leading-relaxed text-muted-foreground"
                                                >
                                                    {{
                                                        clause.execution_blocker
                                                    }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        v-if="activeScheduleMatrix"
                        class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background dark:border-sidebar-border"
                        aria-labelledby="revenue-code-matrix-heading"
                        data-testid="revenue-code-schedule-matrix"
                    >
                        <div
                            class="flex gap-1 overflow-x-auto border-b bg-muted/20 p-2"
                            aria-label="Revenue Code schedule"
                        >
                            <Button
                                v-for="matrix in revenueCodeScheduleMatrices"
                                :key="matrix.provision.code"
                                type="button"
                                size="sm"
                                :variant="
                                    matrix.provision.code === activeScheduleCode
                                        ? 'default'
                                        : 'ghost'
                                "
                                class="shrink-0"
                                :aria-pressed="
                                    matrix.provision.code === activeScheduleCode
                                "
                                :data-schedule-provision-code="
                                    matrix.provision.code
                                "
                                @click="
                                    activeScheduleCode = matrix.provision.code
                                "
                            >
                                {{ matrix.provision.section_reference }}
                            </Button>
                        </div>

                        <div
                            class="flex flex-wrap items-start justify-between gap-3 border-b px-4 py-3"
                        >
                            <div>
                                <h2
                                    id="revenue-code-matrix-heading"
                                    class="font-semibold"
                                >
                                    {{
                                        activeScheduleMatrix.provision
                                            .section_reference
                                    }}
                                    reconciliation matrix
                                </h2>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Source rows and non-executable candidate
                                    values for
                                    {{ activeScheduleMatrix.provision.title }}.
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2 text-xs">
                                <Badge variant="outline">
                                    {{ activeScheduleMatrix.summary.row_count }}
                                    source rows
                                </Badge>
                                <Badge variant="destructive">
                                    {{
                                        activeScheduleMatrix.summary
                                            .overlap_count
                                    }}
                                    overlap
                                </Badge>
                                <Badge variant="destructive">
                                    {{
                                        activeScheduleMatrix.summary
                                            .reconciliation_required_count
                                    }}
                                    require normalization
                                </Badge>
                                <Badge variant="outline">
                                    {{ activeScheduleMatrix.summary.gap_count }}
                                    gaps
                                </Badge>
                            </div>
                        </div>

                        <div
                            class="border-b border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100"
                        >
                            <span class="font-medium">Execution refused.</span>
                            Candidate bounds and values support reconciliation
                            review; they are not accepted municipal policy and
                            are not used by assessment calculation.
                        </div>

                        <div class="overflow-x-auto">
                            <table
                                class="w-full min-w-[1080px] table-fixed text-sm"
                            >
                                <thead
                                    class="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase"
                                >
                                    <tr>
                                        <th
                                            class="w-[7%] px-3 py-3 font-medium"
                                        >
                                            Row
                                        </th>
                                        <th
                                            class="w-[25%] px-3 py-3 font-medium"
                                        >
                                            Ordinance basis text
                                        </th>
                                        <th
                                            class="w-[18%] px-3 py-3 font-medium"
                                        >
                                            Ordinance value text
                                        </th>
                                        <th
                                            class="w-[18%] px-3 py-3 font-medium"
                                        >
                                            Candidate basis
                                        </th>
                                        <th
                                            class="w-[12%] px-3 py-3 font-medium"
                                        >
                                            Candidate value
                                        </th>
                                        <th
                                            class="w-[20%] px-3 py-3 font-medium"
                                        >
                                            Reconciliation finding
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in activeScheduleMatrix.rows"
                                        :key="row.id"
                                        class="border-b last:border-0"
                                        :class="
                                            row.issues.length > 0
                                                ? 'bg-red-50/60 dark:bg-red-950/10'
                                                : ''
                                        "
                                        :data-schedule-row-code="row.code"
                                    >
                                        <td class="px-3 py-3 align-top">
                                            <div class="font-medium">
                                                {{ row.sequence }}
                                            </div>
                                            <div
                                                class="mt-1 text-xs text-muted-foreground"
                                            >
                                                {{ row.code }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 align-top">
                                            {{ row.source_basis_text }}
                                        </td>
                                        <td class="px-3 py-3 align-top">
                                            {{ row.source_value_text }}
                                        </td>
                                        <td class="px-3 py-3 align-top">
                                            {{ candidateBasis(row) }}
                                        </td>
                                        <td
                                            class="px-3 py-3 align-top font-medium"
                                        >
                                            {{ candidateValue(row) }}
                                        </td>
                                        <td class="px-3 py-3 align-top">
                                            <Badge
                                                :variant="
                                                    row.issues.length === 0
                                                        ? 'outline'
                                                        : 'destructive'
                                                "
                                            >
                                                {{
                                                    row.issues.length === 0
                                                        ? 'No mechanical issue'
                                                        : label(
                                                              row.normalization_status,
                                                          )
                                                }}
                                            </Badge>
                                            <div
                                                v-if="row.issues.length > 0"
                                                class="mt-2 flex flex-wrap gap-1"
                                            >
                                                <span
                                                    v-for="issue in row.issues"
                                                    :key="`${row.code}-${issue.type}`"
                                                    class="rounded-md border border-red-300 px-2 py-0.5 text-xs text-red-800 dark:border-red-700 dark:text-red-200"
                                                >
                                                    {{ label(issue.type) }}
                                                    <template
                                                        v-if="
                                                            issue.related_row_code
                                                        "
                                                    >
                                                        with
                                                        {{
                                                            issue.related_row_code
                                                        }}
                                                    </template>
                                                </span>
                                            </div>
                                            <p
                                                v-if="row.normalization_notes"
                                                class="mt-2 text-xs text-muted-foreground"
                                            >
                                                {{ row.normalization_notes }}
                                            </p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </details>

            <section
                class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background dark:border-sidebar-border"
            >
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1100px] table-fixed text-sm">
                        <thead
                            class="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="w-[19%] px-3 py-3 font-medium">
                                    Fee
                                </th>
                                <th class="w-[13%] px-3 py-3 font-medium">
                                    Revenue code
                                </th>
                                <th class="w-[13%] px-3 py-3 font-medium">
                                    Business Division
                                </th>
                                <th class="w-[18%] px-3 py-3 font-medium">
                                    Applies to
                                </th>
                                <th class="w-[15%] px-3 py-3 font-medium">
                                    Amount / basis
                                </th>
                                <th class="w-[12%] px-3 py-3 font-medium">
                                    Owner
                                </th>
                                <th class="w-[6%] px-3 py-3 font-medium">
                                    Status
                                </th>
                                <th
                                    class="w-[4%] px-3 py-3 text-right font-medium"
                                >
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="feeRules.data.length === 0">
                                <td
                                    colspan="8"
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
                                        {{ rule.name }}
                                    </div>
                                    <div
                                        class="mt-1 text-xs break-words text-muted-foreground"
                                    >
                                        {{ label(rule.category) }} ·
                                        {{
                                            applicability(
                                                rule.application_types,
                                            )
                                        }}
                                    </div>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    {{ rule.revenue_code ?? '—' }}
                                </td>
                                <td class="px-3 py-3 align-top">
                                    {{ rule.business_division?.name ?? '—' }}
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <template v-if="rule.applies_to.length">
                                        <span
                                            v-for="(
                                                appliesTo, index
                                            ) in rule.applies_to.slice(0, 2)"
                                            :key="appliesTo"
                                        >
                                            {{ index ? ' · ' : ''
                                            }}{{ appliesTo }}
                                        </span>
                                        <span
                                            v-if="rule.applies_to.length > 2"
                                            class="text-xs text-muted-foreground"
                                        >
                                            +{{ rule.applies_to.length - 2 }}
                                            more
                                        </span>
                                    </template>
                                    <span v-else>Application-wide</span>
                                </td>
                                <td class="px-3 py-3 align-top font-medium">
                                    {{ rule.amount_display }}
                                    <div
                                        v-if="rule.amount_basis"
                                        class="mt-1 text-xs font-normal text-muted-foreground"
                                    >
                                        {{ rule.amount_basis }}
                                    </div>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    {{ rule.owner }}
                                    <div
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{ label(rule.determination_channel) }}
                                    </div>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <Badge
                                        :variant="
                                            rule.display_status === 'Active'
                                                ? 'default'
                                                : 'outline'
                                        "
                                    >
                                        {{ rule.display_status }}
                                    </Badge>
                                </td>
                                <td class="px-3 py-3 text-right align-top">
                                    <Button
                                        as-child
                                        size="sm"
                                        variant="outline"
                                    >
                                        <Link
                                            :href="show(rule.id)"
                                            :aria-label="`View ${rule.name}`"
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
