<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, ChevronRight, History, PencilLine } from '@lucide/vue';
import { show as showFeeRule } from '@/actions/App/Http/Controllers/Staff/FeeRuleController';
import EvaluationResponsibilityForm from '@/components/evaluations/EvaluationResponsibilityForm.vue';
import EvaluationStatusChip from '@/components/evaluations/EvaluationStatusChip.vue';
import { Badge } from '@/components/ui/badge';
import {
    applicabilityLabel,
    dateTime,
    money,
    revisionActionLabel,
    sourceLabel,
} from '@/lib/evaluationPresentation';
import type {
    FinancialComponent,
    ResponsibilityDraft,
} from '@/lib/evaluationPresentation';
import type { EvaluationItem } from '@/types';

defineProps<{
    component: FinancialComponent;
    /** The owning item, when this component is an office responsibility. */
    item: EvaluationItem | null;
    /** Backend-supplied: may this viewer record work on this component? */
    editable: boolean;
    submitting: boolean;
    canViewFeeRules: boolean;
}>();
const emit = defineEmits<{
    submit: [item: EvaluationItem, draft: ResponsibilityDraft];
}>();
</script>

<template>
    <article
        class="rounded-xl border bg-card p-4 shadow-xs sm:p-5"
        :class="component.isMine ? 'border-primary/50 bg-primary/5' : ''"
        :data-testid="`evaluation-component-${component.key}`"
    >
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="min-w-0 space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="font-semibold break-words">
                        {{ component.label }}
                    </h3>
                    <Badge v-if="component.reference" variant="outline">
                        {{ component.reference }}
                    </Badge>
                    <Badge
                        v-if="component.isMine"
                        class="bg-primary text-primary-foreground"
                    >
                        Your responsibility
                    </Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ component.owner }} · {{ component.whyItApplies }}
                </p>
            </div>
            <EvaluationStatusChip :status="component.status" />
        </div>

        <dl class="mt-4 grid gap-3 sm:grid-cols-2">
            <div class="rounded-lg bg-muted/40 p-3">
                <dt class="text-xs text-muted-foreground">
                    {{
                        component.origin === 'governed'
                            ? 'Municipal pricing'
                            : 'Proposed to the office'
                    }}
                </dt>
                <dd class="mt-1 font-medium tabular-nums">
                    {{
                        component.proposalCents === null
                            ? 'No amount proposed'
                            : money(component.proposalCents)
                    }}
                </dd>
            </div>
            <div class="rounded-lg bg-primary/5 p-3">
                <dt class="text-xs text-muted-foreground">
                    Resolved by the Municipality
                </dt>
                <dd class="mt-1 font-medium tabular-nums">
                    {{
                        component.resolvedCents === null
                            ? component.status.label
                            : money(component.resolvedCents)
                    }}
                </dd>
                <dd class="mt-1 text-xs text-muted-foreground">
                    {{
                        component.includedInTotal
                            ? 'Counted in the evaluated total'
                            : 'Not counted in the evaluated total yet'
                    }}
                </dd>
            </div>
        </dl>

        <div
            v-if="component.change"
            class="mt-3 rounded-lg border-l-2 border-primary bg-muted/30 p-3 text-sm"
        >
            <p class="flex flex-wrap items-center gap-2 font-medium">
                <PencilLine class="size-4 shrink-0" aria-hidden="true" />
                Changed from
                <span class="tabular-nums">
                    {{
                        component.change.fromCents === null
                            ? 'no amount'
                            : money(component.change.fromCents)
                    }}
                </span>
                to
                <span class="tabular-nums">
                    {{
                        component.change.toCents === null
                            ? 'no amount'
                            : money(component.change.toCents)
                    }}
                </span>
            </p>
            <p v-if="component.change.reason" class="mt-1">
                {{ component.change.reason }}
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                {{ component.change.actorName ?? 'Recorded actor' }} ·
                {{ dateTime(component.change.occurredAt) }}
            </p>
        </div>

        <div
            v-if="editable && item"
            class="mt-4 rounded-lg border-2 border-dashed border-primary/40 p-3 sm:p-4"
        >
            <EvaluationResponsibilityForm
                :item="item"
                :submitting="submitting"
                @submit="(payload, draft) => emit('submit', payload, draft)"
            />
        </div>

        <details class="group mt-4 border-t pt-3">
            <summary
                class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-medium outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
            >
                <span class="flex items-center gap-2">
                    <History class="size-4" aria-hidden="true" />
                    How this amount is explained
                </span>
                <ChevronRight
                    class="size-4 transition-transform group-open:rotate-90"
                    aria-hidden="true"
                />
            </summary>
            <div class="mt-3 grid gap-3 text-sm">
                <dl class="grid gap-2">
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Recorded source
                        </dt>
                        <dd class="mt-0.5">{{ component.sourceLabel }}</dd>
                    </div>
                    <div v-if="component.legalBasis">
                        <dt class="text-xs text-muted-foreground">
                            Legal basis
                        </dt>
                        <dd class="mt-0.5">{{ component.legalBasis }}</dd>
                    </div>
                    <div v-if="component.inspectionRequired">
                        <dt class="text-xs text-muted-foreground">
                            Inspection or review
                        </dt>
                        <dd class="mt-0.5">
                            Required before this responsibility is complete
                        </dd>
                    </div>
                </dl>

                <Link
                    v-if="canViewFeeRules && component.feeRuleId !== null"
                    :href="showFeeRule(component.feeRuleId)"
                    class="inline-flex w-fit items-center gap-1 text-xs font-medium text-primary underline-offset-4 hover:underline focus-visible:ring-3 focus-visible:ring-ring/50"
                >
                    Open the municipal pricing rule
                    <ArrowRight class="size-3" aria-hidden="true" />
                </Link>

                <div v-if="component.history.length" class="grid gap-2">
                    <p class="text-xs font-semibold tracking-wide uppercase">
                        Provenance ({{ component.history.length }})
                    </p>
                    <ol class="grid gap-3 border-l pl-4">
                        <li
                            v-for="revision in component.history"
                            :key="`${revision.version_sequence}-${revision.occurred_at}`"
                        >
                            <p class="font-medium">
                                {{ revisionActionLabel(revision.action) }} ·
                                Evaluation v{{ revision.version_sequence }}
                            </p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{ revision.actor_name ?? 'Recorded actor' }} ·
                                {{ dateTime(revision.occurred_at) }}
                            </p>
                            <p v-if="revision.reason" class="mt-1">
                                {{ revision.reason }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ applicabilityLabel(revision.applicability) }}
                                ·
                                {{
                                    sourceLabel(revision.source_classification)
                                }}
                            </p>
                        </li>
                    </ol>
                </div>
            </div>
        </details>
    </article>
</template>
