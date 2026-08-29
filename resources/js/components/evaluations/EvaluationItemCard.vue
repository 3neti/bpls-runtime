<script setup lang="ts">
import { ChevronRight, History } from '@lucide/vue';
import { computed } from 'vue';
import EvaluationResponsibilityForm from '@/components/evaluations/EvaluationResponsibilityForm.vue';
import { Badge } from '@/components/ui/badge';
import {
    applicabilityLabel,
    dateTime,
    itemTypeLabel,
    officeLabel,
    revisionActionLabel,
    sourceLabel,
} from '@/lib/evaluationPresentation';
import type { ResponsibilityDraft } from '@/lib/evaluationPresentation';
import type { EvaluationItem, EvaluationValue } from '@/types';

const props = defineProps<{
    item: EvaluationItem;
    /** Backend-supplied: may this viewer record work on this item? */
    editable: boolean;
    submitting: boolean;
}>();
const emit = defineEmits<{
    submit: [item: EvaluationItem, draft: ResponsibilityDraft];
}>();

/**
 * A non-monetary responsibility is municipal work, not an amount: what the
 * office determined, and whether the review behind it is complete.
 */
const determination = computed<string>(() => {
    if (props.item.applicability === 'not_applicable') {
        return 'Not applicable to this application';
    }

    const value = props.item.resolved_value;

    if (!value || typeof value !== 'object' || Array.isArray(value)) {
        return props.item.resolution === 'resolved'
            ? 'Recorded'
            : `Awaiting ${officeLabel(props.item.responsible_party)}`;
    }

    const inspection = (value as Record<string, EvaluationValue>).inspection;

    if (
        inspection &&
        typeof inspection === 'object' &&
        !Array.isArray(inspection)
    ) {
        return (inspection as Record<string, unknown>).completed === true
            ? 'Review complete'
            : 'Review not yet complete';
    }

    return props.item.resolution === 'resolved' ? 'Recorded' : 'In progress';
});

const stateLabel = computed<string>(() => {
    if (props.item.resolution === 'resolved') {
        return props.item.applicability === 'not_applicable'
            ? 'Not applicable'
            : 'Complete';
    }

    if (props.item.resolution === 'superseded') {
        return 'Superseded';
    }

    return `Awaiting ${officeLabel(props.item.responsible_party)}`;
});
</script>

<template>
    <article
        class="rounded-xl border bg-card p-4 shadow-xs sm:p-5"
        :class="item.is_mine ? 'border-primary/50 bg-primary/5' : ''"
        :data-testid="`evaluation-item-${item.id}`"
    >
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="min-w-0 space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="font-semibold break-words">{{ item.label }}</h3>
                    <Badge variant="outline">
                        {{ itemTypeLabel(item.item_type) }}
                    </Badge>
                    <Badge
                        v-if="item.is_mine"
                        class="bg-primary text-primary-foreground"
                    >
                        Your responsibility
                    </Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ officeLabel(item.responsible_party) }} ·
                    {{ item.is_required ? 'Required' : 'Supporting' }}
                </p>
            </div>
            <Badge
                :variant="
                    item.resolution === 'resolved' ? 'secondary' : 'outline'
                "
            >
                {{ stateLabel }}
            </Badge>
        </div>

        <dl class="mt-4 grid gap-3 sm:grid-cols-2">
            <div class="rounded-lg bg-muted/40 p-3">
                <dt class="text-xs text-muted-foreground">
                    Municipal determination
                </dt>
                <dd class="mt-1 font-medium">{{ determination }}</dd>
            </div>
            <div class="rounded-lg bg-primary/5 p-3">
                <dt class="text-xs text-muted-foreground">Applicability</dt>
                <dd class="mt-1 font-medium">
                    {{ applicabilityLabel(item.applicability) }}
                </dd>
                <dd class="mt-1 text-xs text-muted-foreground">
                    {{ sourceLabel(item.source_classification) }}
                </dd>
            </div>
        </dl>

        <p
            v-if="item.reason"
            class="mt-3 rounded-lg border-l-2 border-primary bg-muted/30 p-3 text-sm"
        >
            {{ item.reason }}
        </p>

        <div
            v-if="editable"
            class="mt-4 rounded-lg border-2 border-dashed border-primary/40 p-3 sm:p-4"
        >
            <EvaluationResponsibilityForm
                :item="item"
                :submitting="submitting"
                @submit="(payload, draft) => emit('submit', payload, draft)"
            />
        </div>

        <details v-if="item.history.length" class="group mt-4 border-t pt-3">
            <summary
                class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-medium outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
            >
                <span class="flex items-center gap-2">
                    <History class="size-4" aria-hidden="true" />
                    Provenance ({{ item.history.length }})
                </span>
                <ChevronRight
                    class="size-4 transition-transform group-open:rotate-90"
                    aria-hidden="true"
                />
            </summary>
            <ol class="mt-3 grid gap-3 border-l pl-4 text-sm">
                <li
                    v-for="revision in item.history"
                    :key="`${revision.version_sequence}-${revision.occurred_at}`"
                >
                    <p class="font-medium">
                        {{ revisionActionLabel(revision.action) }} · Evaluation
                        v{{ revision.version_sequence }}
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        {{ revision.actor_name ?? 'Recorded actor' }} ·
                        {{ dateTime(revision.occurred_at) }}
                    </p>
                    <p v-if="revision.reason" class="mt-1">
                        {{ revision.reason }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ applicabilityLabel(revision.applicability) }} ·
                        {{ sourceLabel(revision.source_classification) }}
                    </p>
                </li>
            </ol>
        </details>
    </article>
</template>
