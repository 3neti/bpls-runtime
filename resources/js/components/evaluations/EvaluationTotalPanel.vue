<script setup lang="ts">
import { Clock3, LockKeyhole } from '@lucide/vue';
import { money } from '@/lib/evaluationPresentation';
import type { FinancialWorkingPaperPresentation } from '@/lib/evaluationPresentation';

defineProps<{
    workingPaper: FinancialWorkingPaperPresentation;
    statusLabel: string;
    financialLock: boolean;
}>();
</script>

<template>
    <section
        class="overflow-hidden rounded-2xl border bg-card shadow-xs"
        aria-labelledby="evaluated-total-heading"
    >
        <div class="grid gap-5 p-5 sm:p-6 lg:grid-cols-[auto_minmax(0,1fr)]">
            <div
                class="rounded-xl bg-primary px-5 py-4 text-primary-foreground lg:min-w-72"
            >
                <h2
                    id="evaluated-total-heading"
                    class="text-xs font-medium opacity-80"
                >
                    Grand Total
                </h2>
                <p
                    class="mt-1 text-3xl font-semibold tabular-nums"
                    data-testid="evaluated-total"
                >
                    {{
                        workingPaper.grandTotalAvailable &&
                        workingPaper.grandTotalCents !== null
                            ? money(workingPaper.grandTotalCents)
                            : 'Pending resolution'
                    }}
                </p>
                <p class="mt-2 text-xs leading-5 opacity-80">
                    {{ statusLabel }}
                </p>
            </div>

            <div class="min-w-0 space-y-3">
                <div>
                    <p class="font-medium">Canonical financial roll-up</p>
                    <p class="mt-1 text-sm leading-6 text-muted-foreground">
                        These subtotals and the Grand Total come from the
                        Evaluation. This page does not calculate or edit them.
                    </p>
                </div>

                <dl class="grid gap-1 text-sm">
                    <div
                        v-for="section in workingPaper.lineSections"
                        :key="section.key"
                        class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b border-dashed pb-1"
                    >
                        <dt class="min-w-0 break-words">
                            {{ section.label }} subtotal
                        </dt>
                        <dd class="font-medium tabular-nums">
                            {{ money(section.subtotalCents) }}
                        </dd>
                    </div>
                    <div
                        v-if="workingPaper.applicationSection"
                        class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b border-dashed pb-1"
                    >
                        <dt>Application-wide subtotal</dt>
                        <dd class="font-medium tabular-nums">
                            {{
                                money(
                                    workingPaper.applicationSection
                                        .subtotalCents,
                                )
                            }}
                        </dd>
                    </div>
                </dl>

                <div
                    v-if="workingPaper.requiredUnresolvedChargeCount > 0"
                    class="flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
                    role="status"
                >
                    <Clock3 class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <span>
                        {{ workingPaper.requiredUnresolvedChargeCount }}
                        required
                        {{
                            workingPaper.requiredUnresolvedChargeCount === 1
                                ? 'charge remains'
                                : 'charges remain'
                        }}
                        unresolved. A speculative Grand Total is withheld.
                    </span>
                </div>

                <p
                    v-if="financialLock"
                    class="flex items-start gap-2 rounded-lg bg-muted/60 p-3 text-sm"
                >
                    <LockKeyhole
                        class="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span>
                        A Payment Schedule exists, so this Evaluation is
                        read-only and cannot rewrite what is payable.
                    </span>
                </p>
            </div>
        </div>
    </section>
</template>
