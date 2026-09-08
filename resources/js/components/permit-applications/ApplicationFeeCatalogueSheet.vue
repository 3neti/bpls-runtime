<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ExternalLink } from '@lucide/vue';
import { computed } from 'vue';
import type {
    MunicipalFeeScheduleRow,
    MunicipalScheduleOfFees,
} from '@/types/municipal-schedule-of-fees';

const props = defineProps<{ schedule: MunicipalScheduleOfFees }>();
const page = usePage();
const canViewFeeRules = computed(() =>
    Boolean(
        (page.props.auth as { can_view_fee_rules?: boolean } | undefined)
            ?.can_view_fee_rules,
    ),
);
const state = computed(
    () => props.schedule.context?.state ?? 'awaiting_context',
);
const total = computed(() => props.schedule.context?.total_amount_minor ?? 0);

const heading = computed(() => {
    return (
        {
            assessed_snapshot: 'Assessment fees',
            selected_charges: 'Current application fees',
            application_options: 'Available fee options',
            awaiting_context: 'Fees for this Application',
        }[state.value] ?? 'Fees for this Application'
    );
});

const supportingText = computed(() => {
    return {
        assessed_snapshot: 'Frozen with the Assessment of record.',
        selected_charges: 'Confirmed Payment Order and Treasury items.',
        application_options:
            'Limited to the municipal work assigned to this Application.',
        awaiting_context:
            'Fee options appear after BPLO routing and Treasury classification.',
    }[state.value];
});

function money(amountMinor: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amountMinor / 100);
}

function amount(row: MunicipalFeeScheduleRow): string {
    if (row.amount_minor !== null) {
        return money(row.amount_minor);
    }

    if (row.rate_basis_points !== null) {
        return `${Number(row.rate_basis_points) / 100}%`;
    }

    return 'Set during review';
}

function stateLabel(row: MunicipalFeeScheduleRow): string {
    return {
        assessed: 'Assessed',
        selected: 'Selected',
        eligible: 'Available',
    }[row.application_state ?? 'eligible'];
}
</script>

<template>
    <article
        data-testid="application-fee-catalogue-sheet"
        class="mx-auto w-full max-w-[64rem] overflow-hidden rounded-xl border bg-background shadow-sm"
    >
        <header
            class="flex flex-col gap-3 border-b px-4 py-5 sm:flex-row sm:items-end sm:justify-between sm:px-6"
        >
            <div>
                <p class="text-xs font-semibold text-muted-foreground">
                    Municipal Schedule of Fees · {{ schedule.application_year }}
                </p>
                <h3 class="mt-1 text-xl font-semibold tracking-tight">
                    {{ heading }}
                </h3>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ supportingText }}
                </p>
            </div>
            <div
                v-if="total > 0"
                class="shrink-0 rounded-lg bg-muted px-4 py-2 sm:text-right"
            >
                <p
                    class="text-[10px] font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    {{
                        state === 'assessed_snapshot'
                            ? 'Assessed total'
                            : 'Current total'
                    }}
                </p>
                <p class="text-lg font-bold tabular-nums">{{ money(total) }}</p>
            </div>
        </header>

        <div v-if="schedule.categories.length" class="divide-y">
            <section
                v-for="category in schedule.categories"
                :key="category.key"
                class="px-4 py-4 sm:px-6"
            >
                <h4 class="mb-3 text-sm font-semibold">{{ category.label }}</h4>
                <div class="grid gap-2">
                    <article
                        v-for="row in category.rows"
                        :key="row.id"
                        class="grid min-w-0 gap-3 rounded-lg border p-3 sm:grid-cols-[minmax(0,1.5fr)_minmax(9rem,0.8fr)_minmax(7rem,0.65fr)_auto] sm:items-center"
                    >
                        <div class="min-w-0">
                            <p class="font-medium break-words">
                                {{ row.service }}
                            </p>
                            <p
                                class="mt-0.5 text-xs break-words text-muted-foreground"
                            >
                                {{
                                    row.source_label ||
                                    row.basis ||
                                    'Application-wide'
                                }}
                            </p>
                        </div>
                        <div class="min-w-0 text-xs">
                            <p class="font-medium text-muted-foreground">
                                Revenue Code
                            </p>
                            <p class="mt-0.5 font-mono break-all">
                                {{ row.revenue_code || '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="font-semibold tabular-nums">
                                {{ amount(row) }}
                            </p>
                            <p
                                v-if="row.catalogue_version"
                                class="mt-0.5 text-[10px] break-all text-muted-foreground"
                            >
                                Catalogue {{ row.catalogue_version }}
                            </p>
                        </div>
                        <div
                            class="flex items-center justify-between gap-3 sm:flex-col sm:items-end"
                        >
                            <span
                                class="text-[10px] font-bold tracking-wide uppercase"
                            >
                                {{ stateLabel(row) }}
                            </span>
                            <Link
                                v-if="canViewFeeRules && row.management_url"
                                :href="row.management_url"
                                class="inline-flex items-center gap-1 text-xs font-semibold text-primary underline underline-offset-4"
                            >
                                View fee details
                                <ExternalLink
                                    class="size-3"
                                    aria-hidden="true"
                                />
                            </Link>
                        </div>
                    </article>
                </div>
            </section>
        </div>

        <div v-else class="px-4 py-12 text-center sm:px-6">
            <p class="font-medium">No application fees yet</p>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ supportingText }}
            </p>
        </div>
    </article>
</template>
