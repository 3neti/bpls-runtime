<script setup lang="ts">
import { AlertTriangle, Clock3, Plus } from '@lucide/vue';
import { money } from '@/lib/evaluationPresentation';
import type { ComponentReconciliation } from '@/lib/evaluationPresentation';

defineProps<{
    reconciliation: ComponentReconciliation;
    /** Plain-language state of the Evaluation as a whole. */
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
                    Current evaluated amount
                </h2>
                <p
                    class="mt-1 text-3xl font-semibold tabular-nums"
                    data-testid="evaluated-total"
                >
                    {{ money(reconciliation.canonicalTotalCents) }}
                </p>
                <p class="mt-2 text-xs leading-5 opacity-80">
                    {{ statusLabel }}
                </p>
            </div>

            <div class="min-w-0 space-y-3">
                <p class="text-sm leading-6 text-muted-foreground">
                    This amount is the sum of the municipal charges the
                    Municipality has resolved so far. It is never typed in
                    directly.
                </p>

                <ul
                    v-if="reconciliation.included.length"
                    class="grid gap-1 text-sm"
                >
                    <li
                        v-for="component in reconciliation.included"
                        :key="component.key"
                        class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b border-dashed pb-1 last:border-b-0"
                    >
                        <span class="min-w-0">
                            {{ component.label }}
                            <span class="text-muted-foreground">
                                · {{ component.owner }}
                            </span>
                        </span>
                        <span class="font-medium tabular-nums">
                            {{ money(component.resolvedCents ?? 0) }}
                        </span>
                    </li>
                    <li
                        class="mt-1 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 font-semibold"
                    >
                        <span>Counted in the evaluated total</span>
                        <span class="tabular-nums" data-testid="included-total">
                            {{ money(reconciliation.includedTotalCents) }}
                        </span>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">
                    No municipal charge has been resolved for this application
                    yet.
                </p>

                <div
                    v-if="!reconciliation.reconciled"
                    role="alert"
                    class="flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
                >
                    <AlertTriangle
                        class="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span>
                        The components listed here do not add up to the recorded
                        evaluated amount. The recorded amount above remains
                        authoritative; ask the Assessment Officer to refresh the
                        Evaluation.
                    </span>
                </div>

                <div
                    v-if="reconciliation.pending.length"
                    class="rounded-lg bg-muted/40 p-3 text-sm"
                >
                    <p class="flex items-center gap-2 font-medium">
                        <Clock3 class="size-4 shrink-0" aria-hidden="true" />
                        Not counted yet — municipal evaluation is still open
                    </p>
                    <ul class="mt-2 grid gap-1">
                        <li
                            v-for="component in reconciliation.pending"
                            :key="component.key"
                            class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1"
                        >
                            <span class="flex min-w-0 items-baseline gap-1">
                                <Plus
                                    class="size-3 shrink-0 self-center"
                                    aria-hidden="true"
                                />
                                {{ component.label }}
                                <span class="text-muted-foreground">
                                    · {{ component.status.label }}
                                </span>
                            </span>
                            <span class="text-muted-foreground tabular-nums">
                                {{
                                    component.proposalCents === null
                                        ? 'Amount not yet proposed'
                                        : `${money(component.proposalCents)} proposed`
                                }}
                            </span>
                        </li>
                    </ul>
                    <p class="mt-2 text-xs text-muted-foreground">
                        These amounts are proposals only. The evaluated amount
                        changes when the responsible office records its
                        determination.
                    </p>
                </div>

                <p
                    v-if="financialLock"
                    class="rounded-lg bg-muted/60 p-3 text-sm"
                >
                    A Payment Schedule exists for this application, so the
                    Evaluation is read-only and cannot rewrite what is payable.
                </p>
            </div>
        </div>
    </section>
</template>
