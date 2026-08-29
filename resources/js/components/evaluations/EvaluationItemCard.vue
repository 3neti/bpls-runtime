<script setup lang="ts">
import { ChevronRight, ClipboardCheck, History } from '@lucide/vue';
import { computed, reactive } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    applicabilityLabel,
    inspectionModeLabels,
    itemTypeLabel,
    officeLabel,
    revisionActionLabel,
    sourceLabel,
} from '@/lib/evaluationPresentation';
import type {
    EvaluationApplicability as Applicability,
    EvaluationItem,
    EvaluationValue,
} from '@/types';

type Draft = {
    applicability: Applicability;
    amount: string;
    reason: string;
    inspectionMode: '' | 'physical' | 'virtual' | 'document_review';
    inspectionCompleted: boolean;
    findings: string;
};

const props = defineProps<{
    item: EvaluationItem;
    editable: boolean;
    submitting: boolean;
}>();
const emit = defineEmits<{ submit: [item: EvaluationItem, draft: Draft] }>();

const draft = reactive<Draft>({
    applicability: props.item.applicability,
    amount:
        amountFromValue(props.item.resolved_value) ??
        amountFromValue(props.item.default_value) ??
        '',
    reason: '',
    inspectionMode: inspectionMode(),
    inspectionCompleted: Boolean(inspectionValue('completed')),
    findings: String(inspectionValue('findings') ?? ''),
});

const reasonRequired = computed(() => {
    const defaultAmount = amountFromValue(props.item.default_value);

    return (
        draft.applicability === 'not_applicable' ||
        (props.item.item_type === 'charge' &&
            defaultAmount !== null &&
            draft.amount !== defaultAmount)
    );
});

function money(amountCents: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amountCents / 100);
}

function dateTime(value: string | null): string {
    if (!value) {
        return 'Not recorded';
    }

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function amountFromValue(value: EvaluationValue): string | null {
    if (!value || typeof value !== 'object' || Array.isArray(value)) {
        return null;
    }

    return typeof value.amount_cents === 'number'
        ? (value.amount_cents / 100).toFixed(2)
        : null;
}

function inspectionValue(key: string): unknown {
    if (
        !props.item.resolved_value ||
        typeof props.item.resolved_value !== 'object' ||
        Array.isArray(props.item.resolved_value)
    ) {
        return null;
    }

    const inspection = props.item.resolved_value.inspection;

    if (
        !inspection ||
        typeof inspection !== 'object' ||
        Array.isArray(inspection)
    ) {
        return null;
    }

    return (inspection as Record<string, unknown>)[key];
}

function inspectionMode(): Draft['inspectionMode'] {
    const value = inspectionValue('mode');

    return value === 'physical' ||
        value === 'virtual' ||
        value === 'document_review'
        ? value
        : '';
}

function displayValue(value: EvaluationValue): string {
    if (value === null || value === undefined) {
        return 'Not recorded';
    }

    if (typeof value === 'string' || typeof value === 'number') {
        return String(value);
    }

    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    if (Array.isArray(value)) {
        return value.map(String).join(', ');
    }

    if (typeof value === 'object') {
        if (typeof value.amount_cents === 'number') {
            return money(value.amount_cents);
        }

        if (typeof value.label === 'string') {
            return value.label;
        }

        if (
            typeof value.value === 'string' ||
            typeof value.value === 'number'
        ) {
            return String(value.value);
        }

        const inspection = value.inspection;

        if (
            inspection &&
            typeof inspection === 'object' &&
            !Array.isArray(inspection)
        ) {
            return `Inspection ${(inspection as Record<string, unknown>).completed ? 'completed' : 'not completed'}`;
        }
    }

    return props.item.item_type === 'charge'
        ? 'Amount not recorded'
        : 'Recorded determination';
}

function itemStatus(): string {
    if (props.item.resolution === 'resolved') {
        return props.item.applicability === 'not_applicable'
            ? 'Not applicable'
            : 'Complete';
    }

    if (props.item.resolution === 'superseded') {
        return 'Superseded';
    }

    return `Awaiting ${officeLabel(props.item.responsible_party)}`;
}
</script>

<template>
    <article
        class="rounded-xl border bg-card p-4 shadow-xs sm:p-5"
        :data-testid="`evaluation-item-${item.id}`"
    >
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="font-semibold break-words">{{ item.label }}</h3>
                    <Badge variant="outline">{{
                        itemTypeLabel(item.item_type)
                    }}</Badge>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ officeLabel(item.responsible_party) }} ·
                    {{ item.is_required ? 'Required' : 'Supporting' }}
                </p>
            </div>
            <Badge
                :variant="
                    item.resolution === 'resolved' ? 'secondary' : 'outline'
                "
            >
                {{ itemStatus() }}
            </Badge>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <div class="rounded-lg bg-muted/40 p-3">
                <p class="text-xs text-muted-foreground">
                    System / default proposal
                </p>
                <p class="mt-1 font-medium">
                    {{ displayValue(item.default_value) }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ sourceLabel(item.default_source_classification) }}
                </p>
            </div>
            <div class="rounded-lg bg-primary/5 p-3">
                <p class="text-xs text-muted-foreground">
                    Resolved municipal value
                </p>
                <p class="mt-1 font-medium">
                    {{
                        item.applicability === 'not_applicable'
                            ? 'Not applicable'
                            : displayValue(item.resolved_value)
                    }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ sourceLabel(item.source_classification) }}
                </p>
            </div>
        </div>

        <p
            v-if="item.reason"
            class="mt-3 rounded-lg border-l-2 border-primary bg-muted/30 p-3 text-sm"
        >
            {{ item.reason }}
        </p>

        <form
            v-if="editable"
            class="mt-4 border-t pt-4"
            @submit.prevent="emit('submit', item, draft)"
        >
            <fieldset class="grid gap-4">
                <legend class="font-semibold">
                    Record
                    {{ officeLabel(item.responsible_party) }} determination
                </legend>

                <div class="grid gap-2">
                    <Label :for="`applicability-${item.id}`"
                        >Applicability</Label
                    >
                    <select
                        :id="`applicability-${item.id}`"
                        v-model="draft.applicability"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                    >
                        <option value="applicable">Applicable</option>
                        <option value="not_applicable">Not applicable</option>
                        <option value="undetermined">Not yet determined</option>
                    </select>
                </div>

                <div
                    v-if="
                        item.item_type === 'charge' &&
                        draft.applicability === 'applicable'
                    "
                    class="grid gap-2"
                >
                    <Label :for="`amount-${item.id}`"
                        >Resolved amount (PHP)
                        <span aria-hidden="true">*</span></Label
                    >
                    <Input
                        :id="`amount-${item.id}`"
                        v-model="draft.amount"
                        type="number"
                        min="0"
                        step="0.01"
                        inputmode="decimal"
                        required
                    />
                </div>

                <div
                    v-if="item.inspection_required"
                    class="grid gap-3 rounded-lg bg-muted/30 p-3"
                >
                    <p class="font-medium">Inspection / review</p>
                    <div class="grid gap-2">
                        <Label :for="`inspection-mode-${item.id}`">Mode</Label>
                        <select
                            :id="`inspection-mode-${item.id}`"
                            v-model="draft.inspectionMode"
                            required
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                        >
                            <option value="" disabled>Select mode</option>
                            <option
                                v-for="(label, mode) in inspectionModeLabels"
                                :key="mode"
                                :value="mode"
                            >
                                {{ label }}
                            </option>
                        </select>
                    </div>
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input
                            v-model="draft.inspectionCompleted"
                            type="checkbox"
                            class="size-4"
                        />
                        Inspection / review completed
                    </label>
                    <div class="grid gap-2">
                        <Label :for="`findings-${item.id}`"
                            >Findings / remarks</Label
                        >
                        <textarea
                            :id="`findings-${item.id}`"
                            v-model="draft.findings"
                            rows="3"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                        />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label :for="`reason-${item.id}`">
                        Reason
                        <span v-if="reasonRequired" aria-hidden="true">*</span>
                    </Label>
                    <textarea
                        :id="`reason-${item.id}`"
                        v-model="draft.reason"
                        :required="reasonRequired"
                        rows="3"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                    />
                </div>

                <Button
                    type="submit"
                    class="w-full sm:w-auto"
                    :disabled="submitting"
                >
                    <ClipboardCheck aria-hidden="true" />
                    {{ submitting ? 'Recording…' : 'Record determination' }}
                </Button>
            </fieldset>
        </form>

        <details v-if="item.history.length" class="group mt-4 border-t pt-4">
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
            <ol class="mt-3 grid gap-3 border-l pl-4">
                <li
                    v-for="revision in item.history"
                    :key="`${revision.version_sequence}-${revision.occurred_at}`"
                    class="text-sm"
                >
                    <p class="font-medium">
                        {{ revisionActionLabel(revision.action) }} · Evaluation
                        v{{ revision.version_sequence }}
                    </p>
                    <p class="mt-0.5 text-muted-foreground">
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
