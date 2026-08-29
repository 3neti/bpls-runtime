<script setup lang="ts">
import { ClipboardCheck } from '@lucide/vue';
import { computed, reactive } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    amountFromValue,
    inspectionModeLabels,
    officeLabel,
} from '@/lib/evaluationPresentation';
import type { ResponsibilityDraft } from '@/lib/evaluationPresentation';
import type { EvaluationItem } from '@/types';

const props = defineProps<{
    item: EvaluationItem;
    submitting: boolean;
}>();
const emit = defineEmits<{
    submit: [item: EvaluationItem, draft: ResponsibilityDraft];
}>();

function pesosFromValue(value: unknown): string | null {
    const amountCents = amountFromValue(value);

    return amountCents === null ? null : (amountCents / 100).toFixed(2);
}

function inspectionValue(key: string): unknown {
    const resolved = props.item.resolved_value;

    if (!resolved || typeof resolved !== 'object' || Array.isArray(resolved)) {
        return null;
    }

    const inspection = resolved.inspection;

    if (
        !inspection ||
        typeof inspection !== 'object' ||
        Array.isArray(inspection)
    ) {
        return null;
    }

    return (inspection as Record<string, unknown>)[key];
}

function initialInspectionMode(): ResponsibilityDraft['inspectionMode'] {
    const mode = inspectionValue('mode');

    return mode === 'physical' ||
        mode === 'virtual' ||
        mode === 'document_review'
        ? mode
        : '';
}

const draft = reactive<ResponsibilityDraft>({
    applicability: props.item.applicability,
    amount:
        pesosFromValue(props.item.resolved_value) ??
        pesosFromValue(props.item.default_value) ??
        '',
    reason: '',
    inspectionMode: initialInspectionMode(),
    inspectionCompleted: Boolean(inspectionValue('completed')),
    findings: String(inspectionValue('findings') ?? ''),
});

const proposalPesos = computed(() => pesosFromValue(props.item.default_value));

/**
 * The Municipality requires a reason whenever the office departs from the
 * proposal, and whenever it declares the responsibility not applicable. The
 * backend enforces this; the form states it up front.
 *
 * Amounts are compared numerically because Vue casts `v-model` on a number
 * input to a number, so `150` and `'150.00'` are the same municipal amount.
 */
const reasonRequired = computed(() => {
    if (draft.applicability === 'not_applicable') {
        return true;
    }

    if (props.item.item_type !== 'charge' || proposalPesos.value === null) {
        return false;
    }

    return Number(draft.amount) !== Number(proposalPesos.value);
});

const isCharge = computed(() => props.item.item_type === 'charge');
const amountRequired = computed(
    () => isCharge.value && draft.applicability === 'applicable',
);
</script>

<template>
    <form class="grid gap-4" @submit.prevent="emit('submit', item, draft)">
        <fieldset class="grid gap-4">
            <legend class="text-sm font-semibold">
                Record the {{ officeLabel(item.responsible_party) }}
                determination
            </legend>

            <div class="grid gap-2">
                <Label :for="`applicability-${item.id}`"
                    >Does this apply?</Label
                >
                <select
                    :id="`applicability-${item.id}`"
                    v-model="draft.applicability"
                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                >
                    <option value="applicable">
                        Applies to this application
                    </option>
                    <option value="not_applicable">
                        Not applicable — no charge
                    </option>
                    <option value="undetermined">Not yet determined</option>
                </select>
            </div>

            <div v-if="amountRequired" class="grid gap-2">
                <Label :for="`amount-${item.id}`">
                    Amount you resolve (PHP)
                    <span aria-hidden="true">*</span>
                </Label>
                <Input
                    :id="`amount-${item.id}`"
                    v-model="draft.amount"
                    type="number"
                    min="0"
                    step="0.01"
                    inputmode="decimal"
                    required
                    :aria-describedby="`amount-help-${item.id}`"
                />
                <p
                    :id="`amount-help-${item.id}`"
                    class="text-xs text-muted-foreground"
                >
                    <template v-if="proposalPesos !== null">
                        Proposed for you: ₱{{ proposalPesos }}. Confirm it
                        as-is, or change it and record why.
                    </template>
                    <template v-else>
                        No amount was proposed. Record the amount your office
                        determines.
                    </template>
                </p>
            </div>

            <div
                v-if="item.inspection_required"
                class="grid gap-3 rounded-lg bg-muted/40 p-3"
            >
                <p class="text-sm font-medium">Inspection or review</p>
                <div class="grid gap-2">
                    <Label :for="`inspection-mode-${item.id}`">How</Label>
                    <select
                        :id="`inspection-mode-${item.id}`"
                        v-model="draft.inspectionMode"
                        required
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                    >
                        <option value="" disabled>
                            Select how it was done
                        </option>
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
                    Inspection or review is complete
                </label>
                <div class="grid gap-2">
                    <Label :for="`findings-${item.id}`">
                        Findings or remarks
                    </Label>
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
                    :aria-describedby="`reason-help-${item.id}`"
                />
                <p
                    :id="`reason-help-${item.id}`"
                    class="text-xs text-muted-foreground"
                >
                    <template v-if="reasonRequired">
                        A reason is required because you are changing the
                        proposed municipal position.
                    </template>
                    <template v-else>
                        Optional when you confirm the proposal unchanged.
                    </template>
                </p>
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
</template>
