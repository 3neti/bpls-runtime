<script setup lang="ts">
import {
    AlertTriangle,
    BookOpenText,
    Search,
    TableProperties,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
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
import { officeLabel, sourceLabel } from '@/lib/evaluationPresentation';
import { index as feeMatrixIndex } from '@/routes/staff/fee-matrix';

type Fee = {
    id: number;
    code: string;
    name: string;
    family: 'application_wide' | 'line_of_business';
    line_of_business_id: number | null;
    line_of_business_name: string | null;
    responsible_office: string | null;
    amount_minor: number | null;
    status: string;
    legal_basis: string | null;
};

type OrdinanceEntry = {
    id: string;
    label: string;
    source_text: string;
    is_ceiling: boolean;
};

type OrdinanceProvision = {
    id: number;
    code: string;
    section_reference: string;
    title: string;
    provision_type: string;
    evidence_summary: string;
    reconciliation_status: string;
    reconciliation_notes: string | null;
    known_ambiguities: string[];
    linked_fee_rule: {
        code: string;
        execution_status: string | null;
    } | null;
    entries: OrdinanceEntry[];
};

type Matrix = {
    context: {
        has_direct_fee_rule: boolean;
    };
    summary: {
        fee_rules: number;
        executable_fee_rules: number;
        ordinance_fee_provisions: number;
        ordinance_provisions: number;
        ordinance_schedule_rows: number;
        ordinance_policy_clauses: number;
    };
    application_wide: Fee[];
    line_of_businesses: { id: number; name: string; fees: Fee[] }[];
    ordinance_register: OrdinanceProvision[];
};

type FeeMatrixContext = {
    office?: string;
    lineOfBusinessId?: number;
    feeRuleId?: number | null;
    chargeCode?: string | null;
    chargeLabel?: string | null;
    sourceClassification?: string | null;
};

const open = ref(false);
const loading = ref(false);
const matrix = ref<Matrix | null>(null);
const loadFailed = ref(false);
const query = ref('');
const view = ref<'current' | 'ordinance'>('current');
const lens = ref<'all' | 'application_wide' | 'line_of_business'>('all');
const contextOffice = ref<string | null>(null);
const contextLineOfBusinessId = ref<number | null>(null);
const contextFeeRuleId = ref<number | null>(null);
const contextChargeCode = ref<string | null>(null);
const contextChargeLabel = ref<string | null>(null);
const contextSourceClassification = ref<string | null>(null);

const statusLabels: Record<string, string> = {
    in_force: 'In Force',
    municipal_confirmation_required:
        'Recorded — Municipal Confirmation Required',
    concerned_office_determined: 'Concerned-office Determined',
    not_commissioned: 'Not Commissioned',
};
const ordinanceStatusLabels: Record<string, string> = {
    recorded: 'Recorded evidence',
    reconciliation_required: 'Municipal reconciliation required',
    reconciled: 'Reconciled',
};
const provisionTypeLabels: Record<string, string> = {
    fixed_fee: 'Fixed fee provision',
    tax_schedule: 'Tax schedule',
    percentage_rate: 'Percentage rate',
    presumptive_income_schedule: 'Presumptive income schedule',
};

const money = (minor: number | null): string =>
    minor === null
        ? 'Exact amount not commissioned'
        : new Intl.NumberFormat('en-PH', {
              style: 'currency',
              currency: 'PHP',
          }).format(minor / 100);

function searchTokens(value: string): string[] {
    return value
        .toLowerCase()
        .replaceAll('&', ' and ')
        .replace(/[^a-z0-9]+/g, ' ')
        .split(' ')
        .filter((token) => token.length > 2 && token !== 'and');
}

function matchesQuery(values: Array<string | null | undefined>): boolean {
    const tokens = searchTokens(query.value);
    const haystack = values.filter(Boolean).join(' ').toLowerCase();

    return (
        tokens.length === 0 || tokens.every((token) => haystack.includes(token))
    );
}

const includesFeeQuery = (fee: Fee): boolean =>
    matchesQuery([
        fee.name,
        fee.code,
        fee.line_of_business_name,
        fee.responsible_office,
        fee.legal_basis,
    ]);
const applicationFees = computed(() =>
    lens.value === 'line_of_business'
        ? []
        : (matrix.value?.application_wide ?? []).filter(includesFeeQuery),
);
const lineGroups = computed(() =>
    lens.value === 'application_wide'
        ? []
        : (matrix.value?.line_of_businesses ?? [])
              .map((group) => ({
                  ...group,
                  fees: group.fees.filter(includesFeeQuery),
              }))
              .filter((group) => group.fees.length > 0),
);
const ordinanceProvisions = computed(() =>
    (matrix.value?.ordinance_register ?? []).filter((provision) =>
        matchesQuery([
            provision.code,
            provision.section_reference,
            provision.title,
            provision.evidence_summary,
            provision.reconciliation_notes,
            ...provision.entries.flatMap((entry) => [
                entry.label,
                entry.source_text,
            ]),
        ]),
    ),
);

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
    contextOffice.value = null;
    contextLineOfBusinessId.value = null;
    contextFeeRuleId.value = null;
    contextChargeCode.value = null;
    contextChargeLabel.value = null;
    contextSourceClassification.value = null;
    query.value = '';
    view.value = 'current';
    lens.value = 'all';
}

function openFromContext(event: Event): void {
    const detail = (event as CustomEvent).detail as
        FeeMatrixContext | undefined;
    contextOffice.value = detail?.office ?? null;
    contextLineOfBusinessId.value = detail?.lineOfBusinessId ?? null;
    contextFeeRuleId.value = detail?.feeRuleId ?? null;
    contextChargeCode.value = detail?.chargeCode ?? null;
    contextChargeLabel.value = detail?.chargeLabel ?? null;
    contextSourceClassification.value = detail?.sourceClassification ?? null;
    query.value = detail?.feeRuleId ? '' : (detail?.chargeLabel ?? '');
    view.value = detail?.feeRuleId ? 'current' : 'ordinance';
    lens.value = 'all';
    open.value = true;
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
            class="h-[100dvh] w-screen max-w-none overflow-y-auto rounded-none p-0 sm:h-auto sm:max-h-[88vh] sm:w-[min(960px,calc(100vw-3rem))] sm:rounded-xl"
            data-testid="fee-matrix-dialog"
        >
            <DialogHeader class="sticky top-0 z-10 border-b bg-background p-5">
                <DialogTitle>Municipal Fees and Ordinance Register</DialogTitle>
                <DialogDescription>
                    Current calculation rules and the broader ordinance record
                    are shown separately. Recorded evidence does not become an
                    executable charge merely because it is visible here.
                </DialogDescription>

                <div
                    v-if="contextChargeLabel"
                    class="mt-2 grid gap-1 rounded-lg border bg-muted/40 p-3 text-sm leading-5"
                >
                    <p>
                        Reviewing evidence for
                        <strong>{{ contextChargeLabel }}</strong>
                        <template v-if="contextOffice">
                            · {{ officeLabel(contextOffice) }}</template
                        >
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Case source:
                        {{ sourceLabel(contextSourceClassification) }}.
                        <template v-if="matrix?.context.has_direct_fee_rule">
                            This charge has a direct governed FeeRule link.
                        </template>
                        <template v-else>
                            No direct governed FeeRule backs this case proposal.
                            Matching ordinance results below are evidence for
                            review, not an automatic price.
                        </template>
                    </p>
                </div>

                <div
                    class="mt-3 grid grid-cols-2 gap-2"
                    aria-label="Fee evidence view"
                >
                    <Button
                        :variant="view === 'current' ? 'default' : 'outline'"
                        @click="view = 'current'"
                    >
                        <TableProperties aria-hidden="true" />
                        Current Fee Rules
                    </Button>
                    <Button
                        :variant="view === 'ordinance' ? 'default' : 'outline'"
                        @click="view = 'ordinance'"
                    >
                        <BookOpenText aria-hidden="true" />
                        Ordinance Register
                    </Button>
                </div>

                <div class="relative mt-2">
                    <Search
                        class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        v-model="query"
                        class="pl-9"
                        :placeholder="
                            view === 'current'
                                ? 'Search current fee, office, or Line of Business'
                                : 'Search ordinance section, fee, tax, or activity'
                        "
                    />
                </div>
                <div
                    v-if="view === 'current'"
                    class="flex flex-wrap gap-2 pt-2"
                    aria-label="Fee family"
                >
                    <Button
                        v-for="option in [
                            ['all', 'All'],
                            ['application_wide', 'Application-wide'],
                            ['line_of_business', 'By LOB'],
                        ] as const"
                        :key="option[0]"
                        size="sm"
                        :variant="lens === option[0] ? 'default' : 'outline'"
                        @click="lens = option[0]"
                    >
                        {{ option[1] }}
                    </Button>
                </div>
            </DialogHeader>

            <div class="grid gap-6 p-5">
                <p v-if="loading" class="text-sm text-muted-foreground">
                    Loading municipal fee evidence…
                </p>
                <p
                    v-else-if="loadFailed"
                    class="rounded-lg border border-destructive/30 bg-destructive/5 p-5 text-sm"
                >
                    Municipal fee evidence could not be loaded. Your case
                    determination has not been changed. Close this sheet and try
                    again.
                </p>
                <template v-else-if="matrix">
                    <dl class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                        <div class="rounded-lg bg-muted/50 p-3">
                            <dt class="text-xs text-muted-foreground">
                                Current Fee Rules
                            </dt>
                            <dd class="mt-1 font-semibold">
                                {{ matrix.summary.fee_rules }}
                            </dd>
                        </div>
                        <div class="rounded-lg bg-muted/50 p-3">
                            <dt class="text-xs text-muted-foreground">
                                Executable now
                            </dt>
                            <dd class="mt-1 font-semibold">
                                {{ matrix.summary.executable_fee_rules }}
                            </dd>
                        </div>
                        <div class="rounded-lg bg-muted/50 p-3">
                            <dt class="text-xs text-muted-foreground">
                                Fee/rate provisions
                            </dt>
                            <dd class="mt-1 font-semibold">
                                {{ matrix.summary.ordinance_fee_provisions }}
                            </dd>
                        </div>
                        <div class="rounded-lg bg-muted/50 p-3">
                            <dt class="text-xs text-muted-foreground">
                                All provisions recorded
                            </dt>
                            <dd class="mt-1 font-semibold">
                                {{ matrix.summary.ordinance_provisions }}
                            </dd>
                        </div>
                    </dl>

                    <template v-if="view === 'current'">
                        <section
                            class="rounded-lg border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100"
                        >
                            <h3 class="font-semibold">
                                Rules eligible for the pricing boundary
                            </h3>
                            <p class="mt-1 leading-5">
                                Status and exact-amount availability determine
                                whether a rule may participate in computation.
                                Ordinance evidence is never promoted by itself.
                            </p>
                        </section>
                        <section
                            v-if="applicationFees.length"
                            class="grid gap-2"
                        >
                            <h3
                                class="text-sm font-bold tracking-wide uppercase"
                            >
                                Application-wide Fees
                            </h3>
                            <article
                                v-for="fee in applicationFees"
                                :key="fee.id"
                                class="grid gap-1 rounded-lg border p-3 sm:grid-cols-[1fr_auto]"
                            >
                                <div>
                                    <p class="font-semibold">
                                        {{ fee.name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ fee.code }} ·
                                        {{ statusLabels[fee.status] }}
                                    </p>
                                </div>
                                <p class="font-semibold tabular-nums">
                                    {{ money(fee.amount_minor) }}
                                </p>
                            </article>
                        </section>
                        <section
                            v-for="group in lineGroups"
                            :key="group.id"
                            class="grid gap-2"
                        >
                            <h3
                                class="text-sm font-bold tracking-wide uppercase"
                            >
                                {{ group.name }}
                            </h3>
                            <article
                                v-for="fee in group.fees"
                                :key="fee.id"
                                class="grid gap-1 rounded-lg border p-3 sm:grid-cols-[1fr_auto]"
                            >
                                <div>
                                    <p class="font-semibold">
                                        {{ fee.name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{
                                            fee.responsible_office ??
                                            'Municipality'
                                        }}
                                        · {{ statusLabels[fee.status] }}
                                    </p>
                                </div>
                                <p class="font-semibold tabular-nums">
                                    {{ money(fee.amount_minor) }}
                                </p>
                            </article>
                        </section>
                        <p
                            v-if="!applicationFees.length && !lineGroups.length"
                            class="rounded-lg border border-dashed p-5 text-sm text-muted-foreground"
                        >
                            No current FeeRule matches this context and search.
                            This empty result does not set the case amount to
                            zero.
                        </p>
                    </template>

                    <template v-else>
                        <section
                            class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100"
                        >
                            <div class="flex items-start gap-3">
                                <AlertTriangle
                                    class="mt-0.5 size-5 shrink-0"
                                    aria-hidden="true"
                                />
                                <div>
                                    <h3 class="font-semibold">
                                        Ordinance evidence—not a price list
                                    </h3>
                                    <p class="mt-1 leading-5">
                                        Every extracted fee, tax, rate, and
                                        presumptive-income provision is visible
                                        here. Most require municipal
                                        reconciliation and cannot calculate an
                                        Assessment.
                                    </p>
                                    <p class="mt-1 text-xs">
                                        {{
                                            matrix.summary
                                                .ordinance_schedule_rows
                                        }}
                                        schedule rows and
                                        {{
                                            matrix.summary
                                                .ordinance_policy_clauses
                                        }}
                                        policy clauses are retained behind these
                                        provisions.
                                    </p>
                                </div>
                            </div>
                        </section>
                        <div class="grid gap-3">
                            <article
                                v-for="provision in ordinanceProvisions"
                                :key="provision.id"
                                class="rounded-xl border bg-card p-4"
                                :data-testid="
                                    'ordinance-provision-' + provision.code
                                "
                            >
                                <div
                                    class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                                >
                                    <div class="min-w-0">
                                        <p
                                            class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                        >
                                            {{ provision.section_reference }} ·
                                            {{
                                                provisionTypeLabels[
                                                    provision.provision_type
                                                ] ?? provision.provision_type
                                            }}
                                        </p>
                                        <h3
                                            class="mt-1 font-semibold break-words"
                                        >
                                            {{ provision.title }}
                                        </h3>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{ provision.code }}
                                        </p>
                                    </div>
                                    <Badge
                                        :variant="
                                            provision.reconciliation_status ===
                                            'reconciled'
                                                ? 'secondary'
                                                : 'outline'
                                        "
                                        class="w-fit shrink-0"
                                    >
                                        {{
                                            ordinanceStatusLabels[
                                                provision.reconciliation_status
                                            ]
                                        }}
                                    </Badge>
                                </div>
                                <p class="mt-3 text-sm leading-6">
                                    {{ provision.evidence_summary }}
                                </p>
                                <p
                                    v-if="provision.linked_fee_rule"
                                    class="mt-3 rounded-lg bg-muted/50 p-3 text-xs"
                                >
                                    Linked FeeRule:
                                    <strong>{{
                                        provision.linked_fee_rule.code
                                    }}</strong>
                                    ·
                                    {{
                                        provision.linked_fee_rule
                                            .execution_status ??
                                        'No execution decision'
                                    }}
                                </p>
                                <details
                                    v-if="provision.entries.length"
                                    class="mt-3 border-t pt-3"
                                >
                                    <summary
                                        class="cursor-pointer text-sm font-medium"
                                    >
                                        Source clauses, amounts, and rates ({{
                                            provision.entries.length
                                        }})
                                    </summary>
                                    <div class="mt-3 grid gap-2">
                                        <div
                                            v-for="entry in provision.entries"
                                            :key="entry.id"
                                            class="rounded-lg bg-muted/40 p-3 text-xs"
                                        >
                                            <p class="font-medium">
                                                {{ entry.label }}
                                            </p>
                                            <p
                                                class="mt-1 leading-5 text-muted-foreground"
                                            >
                                                {{ entry.source_text }}
                                            </p>
                                            <p
                                                v-if="entry.is_ceiling"
                                                class="mt-1 font-medium text-amber-700 dark:text-amber-300"
                                            >
                                                Ceiling—not an exact price
                                            </p>
                                        </div>
                                    </div>
                                </details>
                                <details
                                    v-if="
                                        provision.reconciliation_notes ||
                                        provision.known_ambiguities.length
                                    "
                                    class="mt-3 border-t pt-3"
                                >
                                    <summary
                                        class="cursor-pointer text-sm font-medium"
                                    >
                                        Why execution is restricted
                                    </summary>
                                    <p
                                        v-if="provision.reconciliation_notes"
                                        class="mt-2 text-xs leading-5 text-muted-foreground"
                                    >
                                        {{ provision.reconciliation_notes }}
                                    </p>
                                    <p
                                        v-if="
                                            provision.known_ambiguities.length
                                        "
                                        class="mt-2 text-xs text-muted-foreground"
                                    >
                                        {{ provision.known_ambiguities.length }}
                                        recorded policy
                                        {{
                                            provision.known_ambiguities
                                                .length === 1
                                                ? 'question'
                                                : 'questions'
                                        }}.
                                    </p>
                                </details>
                            </article>
                        </div>
                        <p
                            v-if="!ordinanceProvisions.length"
                            class="rounded-lg border border-dashed p-5 text-sm text-muted-foreground"
                        >
                            No ordinance fee or rate provision matches this
                            search. Clear the search to browse the full fee
                            register.
                        </p>
                    </template>
                </template>
            </div>
        </DialogContent>
    </Dialog>
</template>
