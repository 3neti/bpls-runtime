<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpenText, Search, Settings, TableProperties } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { officeLabel } from '@/lib/evaluationPresentation';
import { index as feeMatrixIndex } from '@/routes/staff/fee-matrix';

type ServiceCategory = {
    key: string;
    label: string;
};

type Fee = {
    id: number;
    code: string;
    name: string;
    family: 'application_wide' | 'line_of_business';
    line_of_business_name: string | null;
    responsible_office: string | null;
    amount_minor: number | null;
    status: string;
    service_category: ServiceCategory;
    management_url: string;
};

type OrdinanceEntry = {
    id: string;
    code: string;
    service_label: string;
    basis_label: string;
    unit_label: string | null;
    source_text: string;
    amount_minor: number | null;
    rate_basis_points: string | null;
    is_ceiling: boolean;
};

type OrdinanceProvision = {
    id: number;
    code: string;
    section_reference: string;
    title: string;
    evidence_summary: string;
    reconciliation_status: string;
    reconciliation_notes: string | null;
    service_category: ServiceCategory;
    governance_url: string;
    linked_fee_rule: {
        id: number;
        code: string;
        execution_status: string | null;
        management_url: string;
    } | null;
    entries: OrdinanceEntry[];
};

type Matrix = {
    context: {
        has_direct_fee_rule: boolean;
    };
    application_wide: Fee[];
    line_of_businesses: { id: number; name: string; fees: Fee[] }[];
    ordinance_register: OrdinanceProvision[];
};

type FeeMatrixContext = {
    evaluationItemId?: number;
    office?: string;
    lineOfBusinessId?: number;
    feeRuleId?: number | null;
    chargeCode?: string | null;
    chargeLabel?: string | null;
    sourceClassification?: string | null;
};

type ScheduleRow = {
    id: string;
    category: ServiceCategory;
    service: string;
    basis: string;
    provisionTitle: string;
    code: string;
    amountMinor: number | null;
    rateBasisPoints: string | null;
    isCeiling: boolean;
    status: 'available' | 'for_confirmation' | 'needs_determination';
    managementUrl: string | null;
    governanceUrl: string | null;
    sourceText: string;
};

const page = usePage();
const open = ref(false);
const loading = ref(false);
const loadFailed = ref(false);
const matrix = ref<Matrix | null>(null);
const query = ref('');
const view = ref<'schedule' | 'source'>('schedule');
const category = ref('all');
const contextOffice = ref<string | null>(null);
const contextEvaluationItemId = ref<number | null>(null);
const contextLineOfBusinessId = ref<number | null>(null);
const contextFeeRuleId = ref<number | null>(null);
const contextChargeCode = ref<string | null>(null);
const contextChargeLabel = ref<string | null>(null);
const contextSourceClassification = ref<string | null>(null);

const canViewFeeRules = computed(() =>
    Boolean(
        (page.props.auth as { can_view_fee_rules?: boolean } | undefined)
            ?.can_view_fee_rules,
    ),
);
const canManageFeeRules = computed(() =>
    Boolean(
        (page.props.auth as { can_manage_fee_rules?: boolean } | undefined)
            ?.can_manage_fee_rules,
    ),
);

const money = (minor: number): string =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(minor / 100);

function rateLabel(rateBasisPoints: string | null): string | null {
    if (rateBasisPoints === null) {
        return null;
    }

    return `${Number(rateBasisPoints) / 100}%`;
}

function rowAmount(row: ScheduleRow): string {
    const value =
        row.amountMinor === null
            ? rateLabel(row.rateBasisPoints)
            : money(row.amountMinor);

    return row.isCeiling && value ? `Up to ${value}` : (value ?? 'Case-based');
}

function searchTokens(value: string): string[] {
    return value
        .toLowerCase()
        .replaceAll('&', ' and ')
        .replace(/[^a-z0-9]+/g, ' ')
        .split(' ')
        .filter((token) => token.length > 2 && token !== 'and');
}

function matches(values: Array<string | null | undefined>): boolean {
    const tokens = searchTokens(query.value);
    const haystack = values.filter(Boolean).join(' ').toLowerCase();

    return (
        tokens.length === 0 || tokens.every((token) => haystack.includes(token))
    );
}

const currentFees = computed(() => [
    ...(matrix.value?.application_wide ?? []),
    ...(matrix.value?.line_of_businesses ?? []).flatMap((group) => group.fees),
]);

const scheduleRows = computed<ScheduleRow[]>(() => {
    const rules = currentFees.value
        .filter((fee) => fee.amount_minor !== null)
        .map((fee): ScheduleRow => ({
            id: `rule-${fee.id}`,
            category: fee.service_category,
            service: fee.name,
            basis:
                fee.family === 'application_wide'
                    ? 'Whole application'
                    : (fee.line_of_business_name ?? 'Line of Business'),
            provisionTitle: fee.name,
            code: fee.code,
            amountMinor: fee.amount_minor,
            rateBasisPoints: null,
            isCeiling: false,
            status:
                fee.status === 'in_force' ? 'available' : 'for_confirmation',
            managementUrl: fee.management_url,
            governanceUrl: null,
            sourceText: '',
        }));

    const ordinance = (matrix.value?.ordinance_register ?? []).flatMap(
        (provision) =>
            provision.entries
                .filter(
                    () =>
                        contextFeeRuleId.value === null ||
                        provision.linked_fee_rule?.id ===
                            contextFeeRuleId.value,
                )
                .filter(
                    (entry) =>
                        entry.amount_minor !== null ||
                        entry.rate_basis_points !== null,
                )
                .filter(
                    (entry) =>
                        !rules.some(
                            (rule) =>
                                rule.managementUrl ===
                                    provision.linked_fee_rule?.management_url &&
                                rule.amountMinor === entry.amount_minor,
                        ),
                )
                .map((entry): ScheduleRow => ({
                    id: `${provision.code}-${entry.id}`,
                    category: provision.service_category,
                    service: entry.service_label,
                    basis: [entry.basis_label, entry.unit_label]
                        .filter(Boolean)
                        .join(' · '),
                    provisionTitle: provision.title,
                    code: entry.code,
                    amountMinor: entry.amount_minor,
                    rateBasisPoints: entry.rate_basis_points,
                    isCeiling: entry.is_ceiling,
                    status: entry.is_ceiling
                        ? 'needs_determination'
                        : 'for_confirmation',
                    managementUrl:
                        provision.linked_fee_rule?.management_url ?? null,
                    governanceUrl: provision.governance_url,
                    sourceText: entry.source_text,
                })),
    );

    return [...rules, ...ordinance].filter(
        (row) =>
            (category.value === 'all' || row.category.key === category.value) &&
            matches([row.service, row.basis, row.provisionTitle, row.code]),
    );
});

const availableCategories = computed(() => {
    const values = new Map<string, ServiceCategory>();

    for (const fee of currentFees.value) {
        values.set(fee.service_category.key, fee.service_category);
    }

    for (const provision of matrix.value?.ordinance_register ?? []) {
        values.set(provision.service_category.key, provision.service_category);
    }

    return [...values.values()];
});

const scheduleGroups = computed(() => {
    const values = new Map<string, ServiceCategory>();

    for (const row of scheduleRows.value) {
        values.set(row.category.key, row.category);
    }

    return [...values.values()].map((group) => ({
        ...group,
        rows: scheduleRows.value.filter(
            (row) => row.category.key === group.key,
        ),
    }));
});

const sourceProvisions = computed(() =>
    (matrix.value?.ordinance_register ?? []).filter((provision) =>
        matches([
            provision.code,
            provision.section_reference,
            provision.title,
            provision.evidence_summary,
            ...provision.entries.map((entry) => entry.source_text),
        ]),
    ),
);

const statusLabel = (status: ScheduleRow['status']): string =>
    ({
        available: 'Available',
        for_confirmation: 'For confirmation',
        needs_determination: 'Needs determination',
    })[status];

function contextParams(): URLSearchParams {
    const params = new URLSearchParams();
    const values: Array<[string, string | number | null]> = [
        ['office', contextOffice.value],
        ['line_of_business_id', contextLineOfBusinessId.value],
        ['fee_rule_id', contextFeeRuleId.value],
        ['charge_code', contextChargeCode.value],
        ['charge_label', contextChargeLabel.value],
        ['source_classification', contextSourceClassification.value],
    ];

    values.forEach(([key, value]) => {
        if (value !== null && value !== '') {
            params.set(key, String(value));
        }
    });

    return params;
}

async function load(): Promise<void> {
    loading.value = true;
    loadFailed.value = false;

    try {
        const response = await fetch(
            feeMatrixIndex().url + '?' + contextParams().toString(),
            { headers: { Accept: 'application/json' } },
        );
        matrix.value = response.ok ? ((await response.json()) as Matrix) : null;
        loadFailed.value = !response.ok;
    } catch {
        matrix.value = null;
        loadFailed.value = true;
    } finally {
        loading.value = false;
    }
}

function clearContext(): void {
    contextEvaluationItemId.value = null;
    contextOffice.value = null;
    contextLineOfBusinessId.value = null;
    contextFeeRuleId.value = null;
    contextChargeCode.value = null;
    contextChargeLabel.value = null;
    contextSourceClassification.value = null;
    query.value = '';
    category.value = 'all';
    view.value = 'schedule';
}

function openFromContext(event: Event): void {
    const detail = (event as CustomEvent).detail as
        FeeMatrixContext | undefined;
    contextEvaluationItemId.value = detail?.evaluationItemId ?? null;
    contextOffice.value = detail?.office ?? null;
    contextLineOfBusinessId.value = detail?.lineOfBusinessId ?? null;
    contextFeeRuleId.value = detail?.feeRuleId ?? null;
    contextChargeCode.value = detail?.chargeCode ?? null;
    contextChargeLabel.value = detail?.chargeLabel ?? null;
    contextSourceClassification.value = detail?.sourceClassification ?? null;
    query.value = detail?.feeRuleId ? '' : (detail?.chargeLabel ?? '');
    category.value = 'all';
    view.value = 'schedule';
    open.value = true;
}

function selectScheduleRow(row: ScheduleRow): void {
    if (contextEvaluationItemId.value === null || row.amountMinor === null) {
        return;
    }

    window.dispatchEvent(
        new CustomEvent('fee-matrix-row-selected', {
            detail: {
                evaluationItemId: contextEvaluationItemId.value,
                serviceLabel: row.service,
                basis: row.basis,
                scheduleReference: row.id,
                unitAmountMinor: row.amountMinor,
            },
        }),
    );
    open.value = false;
}

watch(open, (isOpen) => {
    if (isOpen) {
        void load();
    }
});

onMounted(() => window.addEventListener('open-fee-matrix', openFromContext));
onBeforeUnmount(() =>
    window.removeEventListener('open-fee-matrix', openFromContext),
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button
                class="fixed right-4 bottom-4 z-40 rounded-full shadow-lg sm:right-6 sm:bottom-6"
                data-testid="fee-matrix-quick-look"
                @click="clearContext"
            >
                <TableProperties aria-hidden="true" />
                Fees
            </Button>
        </DialogTrigger>
        <DialogContent
            class="h-[100dvh] w-screen max-w-none overflow-y-auto rounded-none p-0 sm:h-auto sm:max-h-[88vh] sm:w-[min(1040px,calc(100vw-3rem))] sm:max-w-[1040px] sm:rounded-xl"
            data-testid="fee-matrix-dialog"
        >
            <DialogHeader class="sticky top-0 z-10 border-b bg-background p-5">
                <DialogTitle>Municipal Schedule of Fees</DialogTitle>
                <DialogDescription>
                    Services, applicable conditions, and ordinance rates.
                </DialogDescription>
                <p
                    v-if="contextChargeLabel"
                    class="rounded-lg bg-muted/60 px-3 py-2 text-sm"
                >
                    <strong>{{ contextChargeLabel }}</strong>
                    <template v-if="contextOffice">
                        · {{ officeLabel(contextOffice) }}</template
                    >
                    <span class="text-muted-foreground">
                        — select the applicable row before recording an amount.
                    </span>
                </p>
                <div class="grid grid-cols-2 gap-2" aria-label="Fee view">
                    <Button
                        :variant="view === 'schedule' ? 'default' : 'outline'"
                        @click="view = 'schedule'"
                    >
                        <TableProperties aria-hidden="true" />
                        Schedule of Fees
                    </Button>
                    <Button
                        :variant="view === 'source' ? 'default' : 'outline'"
                        @click="view = 'source'"
                    >
                        <BookOpenText aria-hidden="true" />
                        Ordinance Source
                    </Button>
                </div>
                <div class="grid gap-2 sm:grid-cols-[1fr_220px]">
                    <div class="relative">
                        <Search
                            class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Input
                            v-model="query"
                            class="pl-9"
                            placeholder="Search service, instrument, or activity"
                        />
                    </div>
                    <select
                        v-if="view === 'schedule' && !contextChargeLabel"
                        v-model="category"
                        aria-label="Service category"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="all">All service categories</option>
                        <option
                            v-for="option in availableCategories"
                            :key="option.key"
                            :value="option.key"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
            </DialogHeader>

            <div class="grid gap-5 p-5">
                <p v-if="loading" class="text-sm text-muted-foreground">
                    Loading schedule…
                </p>
                <p
                    v-else-if="loadFailed"
                    class="rounded-lg border border-destructive/30 p-4 text-sm"
                >
                    The schedule could not be loaded. No case value was changed.
                </p>

                <template v-else-if="matrix && view === 'schedule'">
                    <section
                        v-for="group in scheduleGroups"
                        :key="group.key"
                        class="grid gap-2"
                    >
                        <h3 class="text-sm font-semibold">{{ group.label }}</h3>

                        <div class="grid gap-2 sm:hidden">
                            <article
                                v-for="row in group.rows"
                                :key="row.id"
                                class="grid gap-2 rounded-lg border p-3"
                            >
                                <div>
                                    <p class="font-medium">{{ row.service }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ row.basis }}
                                    </p>
                                </div>
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <div>
                                        <p class="font-semibold tabular-nums">
                                            {{ rowAmount(row) }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ statusLabel(row.status) }}
                                        </p>
                                    </div>
                                    <Button
                                        v-if="
                                            contextEvaluationItemId !== null &&
                                            row.amountMinor !== null
                                        "
                                        size="sm"
                                        @click="selectScheduleRow(row)"
                                    >
                                        Select
                                    </Button>
                                    <Button
                                        v-if="canViewFeeRules"
                                        variant="outline"
                                        size="sm"
                                        as-child
                                    >
                                        <Link
                                            :href="
                                                row.managementUrl ??
                                                row.governanceUrl ??
                                                '#'
                                            "
                                        >
                                            <Settings aria-hidden="true" />
                                            {{
                                                row.managementUrl
                                                    ? canManageFeeRules
                                                        ? 'Manage'
                                                        : 'View'
                                                    : 'Review'
                                            }}
                                        </Link>
                                    </Button>
                                </div>
                            </article>
                        </div>

                        <div
                            class="hidden overflow-x-auto rounded-lg border sm:block"
                        >
                            <table class="w-full min-w-[760px] text-sm">
                                <thead class="border-b bg-muted/50 text-left">
                                    <tr>
                                        <th class="px-3 py-2 font-medium">
                                            Service
                                        </th>
                                        <th class="px-3 py-2 font-medium">
                                            Basis / condition
                                        </th>
                                        <th
                                            class="px-3 py-2 text-right font-medium"
                                        >
                                            Fee / rate
                                        </th>
                                        <th class="px-3 py-2 font-medium">
                                            Status
                                        </th>
                                        <th
                                            v-if="
                                                canViewFeeRules ||
                                                contextEvaluationItemId !== null
                                            "
                                            class="px-3 py-2 text-right font-medium"
                                        >
                                            Fee record
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in group.rows"
                                        :key="row.id"
                                        class="border-b last:border-0"
                                    >
                                        <td
                                            class="px-3 py-3 align-top font-medium"
                                        >
                                            {{ row.service }}
                                        </td>
                                        <td
                                            class="px-3 py-3 align-top text-muted-foreground"
                                        >
                                            {{ row.basis }}
                                        </td>
                                        <td
                                            class="px-3 py-3 text-right align-top font-semibold tabular-nums"
                                        >
                                            {{ rowAmount(row) }}
                                        </td>
                                        <td class="px-3 py-3 align-top">
                                            {{ statusLabel(row.status) }}
                                        </td>
                                        <td
                                            v-if="
                                                canViewFeeRules ||
                                                contextEvaluationItemId !== null
                                            "
                                            class="px-3 py-2 text-right align-top"
                                        >
                                            <Button
                                                v-if="
                                                    contextEvaluationItemId !==
                                                        null &&
                                                    row.amountMinor !== null
                                                "
                                                size="sm"
                                                @click="selectScheduleRow(row)"
                                            >
                                                Select
                                            </Button>
                                            <Button
                                                v-if="canViewFeeRules"
                                                variant="ghost"
                                                size="sm"
                                                as-child
                                            >
                                                <Link
                                                    :href="
                                                        row.managementUrl ??
                                                        row.governanceUrl ??
                                                        '#'
                                                    "
                                                >
                                                    {{
                                                        row.managementUrl
                                                            ? canManageFeeRules
                                                                ? 'Manage fee'
                                                                : 'View fee'
                                                            : 'Review source'
                                                    }}
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <p
                        v-if="scheduleGroups.length === 0"
                        class="rounded-lg border border-dashed p-5 text-sm text-muted-foreground"
                    >
                        No fee or rate matches this search.
                    </p>
                    <p class="text-xs text-muted-foreground">
                        “For confirmation” rows reproduce ordinance values but
                        do not calculate an Assessment until commissioned.
                    </p>
                </template>

                <template v-else-if="matrix">
                    <article
                        v-for="provision in sourceProvisions"
                        :key="provision.id"
                        class="rounded-lg border p-4"
                    >
                        <p class="text-xs text-muted-foreground">
                            {{ provision.section_reference }} ·
                            {{ provision.code }}
                        </p>
                        <h3 class="mt-1 font-semibold">
                            {{ provision.title }}
                        </h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ provision.evidence_summary }}
                        </p>
                        <details class="mt-3 border-t pt-3">
                            <summary class="cursor-pointer text-sm font-medium">
                                View ordinance text and policy notes
                            </summary>
                            <div class="mt-3 grid gap-2 text-xs">
                                <p
                                    v-for="entry in provision.entries"
                                    :key="entry.id"
                                    class="rounded-md bg-muted/50 p-3 leading-5"
                                >
                                    {{ entry.source_text }}
                                </p>
                                <p
                                    v-if="provision.reconciliation_notes"
                                    class="text-muted-foreground"
                                >
                                    {{ provision.reconciliation_notes }}
                                </p>
                            </div>
                        </details>
                    </article>
                </template>
            </div>
        </DialogContent>
    </Dialog>
</template>
contextEvaluationItemId.value = detail?.evaluationItemId ?? null;
