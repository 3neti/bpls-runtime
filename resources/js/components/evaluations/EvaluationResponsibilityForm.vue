<script setup lang="ts">
import { ClipboardCheck, TableProperties } from '@lucide/vue';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
import EvaluationPriceCalculator from '@/components/evaluations/EvaluationPriceCalculator.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    amountFromValue,
    inspectionModeLabels,
    officeLabel,
    sourceLabel,
} from '@/lib/evaluationPresentation';
import type { ResponsibilityDraft } from '@/lib/evaluationPresentation';
import type { EvaluationItem } from '@/types';

const props = defineProps<{
    applicationId?: number;
    applicationType?: string;
    applicationYear?: number;
    item: EvaluationItem;
    submitting: boolean;
    canViewFeeMatrix?: boolean;
    feeRuleId?: number | null;
    chargeCode?: string | null;
    chargeLabel?: string | null;
    sourceClassification?: string | null;
}>();
const emit = defineEmits<{
    submit: [item: EvaluationItem, draft: ResponsibilityDraft];
}>();

type ScheduleSelection = {
    serviceLabel: string;
    basis: string | null;
    scheduleReference: string | null;
    unitAmountMinor: number;
};

const selectedSchedule = ref<ScheduleSelection | null>(null);

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
    determinationType:
        props.item.default_value == null ? 'office_determination' : 'confirm',
    amount:
        pesosFromValue(props.item.resolved_value) ??
        pesosFromValue(props.item.default_value) ??
        '',
    reason: '',
    authority: '',
    inspectionMode: initialInspectionMode(),
    inspectionCompleted: Boolean(inspectionValue('completed')),
    findings: String(inspectionValue('findings') ?? ''),
    proForma: null,
});

const proposalPesos = computed(() => pesosFromValue(props.item.default_value));
const isLaboratoryProposal = computed(
    () =>
        (props.sourceClassification ??
            props.item.default_source_classification) === 'provisional_uat',
);
const defaultSourceDescription = computed(() =>
    isLaboratoryProposal.value
        ? 'Historical reference — office confirmation required'
        : sourceLabel(
              props.sourceClassification ??
                  props.item.default_source_classification,
          ),
);

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

watch(
    () => draft.determinationType,
    (type) => {
        draft.applicability =
            type === 'not_applicable' ? 'not_applicable' : 'applicable';

        if (
            type === 'confirm' &&
            proposalPesos.value !== null &&
            proposalPesos.value !== null
        ) {
            draft.amount = proposalPesos.value;
        }

        if (type === 'not_applicable') {
            draft.proForma = null;
        }
    },
);

watch(
    () => draft.amount,
    () => {
        if (
            draft.proForma &&
            determinedMinor.value !==
                draft.proForma.unitAmountMinor * draft.proForma.quantity
        ) {
            draft.proForma = null;
        }
    },
);

const scheduledMinor = computed(() =>
    amountFromValue(props.item.default_value),
);
const determinedMinor = computed(() => {
    const parts = String(draft.amount)
        .trim()
        .match(/^(\d+)(?:\.(\d{1,2}))?$/);

    return parts
        ? Number(parts[1]) * 100 + Number((parts[2] ?? '').padEnd(2, '0'))
        : null;
});
const varianceMinor = computed(() =>
    scheduledMinor.value !== null && determinedMinor.value !== null
        ? determinedMinor.value - scheduledMinor.value
        : null,
);

function openFeeMatrix(): void {
    window.dispatchEvent(
        new CustomEvent('open-fee-matrix', {
            detail: {
                evaluationItemId: props.item.id,
                office: props.item.responsible_party,
                lineOfBusinessId: props.item.line_of_business_id,
                feeRuleId: props.feeRuleId,
                chargeCode: props.chargeCode,
                chargeLabel: props.chargeLabel ?? props.item.label,
                sourceClassification:
                    props.sourceClassification ??
                    props.item.default_source_classification,
            },
        }),
    );
}

function selectSchedule(event: Event): void {
    const detail = (event as CustomEvent).detail as
        (ScheduleSelection & { evaluationItemId: number }) | undefined;

    if (!detail || detail.evaluationItemId !== props.item.id) {
        return;
    }

    selectedSchedule.value = {
        serviceLabel: detail.serviceLabel,
        basis: detail.basis,
        scheduleReference: detail.scheduleReference,
        unitAmountMinor: detail.unitAmountMinor,
    };
    draft.amount = (detail.unitAmountMinor / 100).toFixed(2);
    draft.proForma = null;
    draft.applicability = 'applicable';
    draft.determinationType =
        proposalPesos.value !== null &&
        Number(draft.amount) === Number(proposalPesos.value)
            ? 'confirm'
            : 'override';
}

function applyProForma(
    amount: string,
    proForma: NonNullable<ResponsibilityDraft['proForma']>,
): void {
    draft.amount = amount;
    draft.proForma = proForma;
    draft.applicability = 'applicable';
    draft.determinationType =
        proposalPesos.value !== null &&
        Number(amount) === Number(proposalPesos.value)
            ? 'confirm'
            : 'override';
}

onMounted(() =>
    window.addEventListener('fee-matrix-row-selected', selectSchedule),
);
onBeforeUnmount(() =>
    window.removeEventListener('fee-matrix-row-selected', selectSchedule),
);
</script>

<template>
    <form class="grid gap-4" @submit.prevent="emit('submit', item, draft)">
        <fieldset class="grid gap-4">
            <legend class="text-sm font-semibold">
                Record the {{ officeLabel(item.responsible_party) }}
                determination
            </legend>

            <div
                v-if="
                    isCharge &&
                    canViewFeeMatrix &&
                    applicationId &&
                    applicationType &&
                    applicationYear
                "
                class="grid gap-1.5"
            >
                <div class="flex flex-col gap-2 sm:flex-row">
                    <Button
                        type="button"
                        variant="outline"
                        class="w-full sm:w-fit"
                        @click="openFeeMatrix"
                    >
                        <TableProperties aria-hidden="true" />
                        Choose from Schedule of Fees
                    </Button>
                    <EvaluationPriceCalculator
                        :application-id="applicationId"
                        :application-type="applicationType"
                        :application-year="applicationYear"
                        :item="item"
                        :amount="draft.amount"
                        :selection="selectedSchedule"
                        :default-source="defaultSourceDescription"
                        :default-reference="chargeCode"
                        @apply="applyProForma"
                    />
                </div>
                <p class="text-xs leading-5 text-muted-foreground">
                    Select a service, preview the Price report, then apply it to
                    this determination.
                </p>
                <p
                    v-if="selectedSchedule"
                    class="rounded-lg bg-muted/50 px-3 py-2 text-sm"
                >
                    Selected:
                    <strong>{{ selectedSchedule.serviceLabel }}</strong> · ₱{{
                        (selectedSchedule.unitAmountMinor / 100).toFixed(2)
                    }}
                </p>
            </div>

            <div v-if="isCharge" class="grid gap-2">
                <Label>Determination</Label>
                <label
                    v-if="proposalPesos !== null"
                    class="flex items-center gap-2 rounded-lg border p-3 text-sm"
                >
                    <input
                        v-model="draft.determinationType"
                        type="radio"
                        value="confirm"
                    />
                    Confirm default — ₱{{ proposalPesos }}
                </label>
                <label
                    v-if="proposalPesos !== null"
                    class="flex items-center gap-2 rounded-lg border p-3 text-sm"
                >
                    <input
                        v-model="draft.determinationType"
                        type="radio"
                        value="override"
                    />
                    Change the default amount
                </label>
                <label
                    v-if="proposalPesos === null"
                    class="flex items-center gap-2 rounded-lg border p-3 text-sm"
                >
                    <input
                        v-model="draft.determinationType"
                        type="radio"
                        value="office_determination"
                    />
                    Select the applicable service and enter the amount
                </label>
                <label
                    class="flex items-center gap-2 rounded-lg border p-3 text-sm"
                >
                    <input
                        v-model="draft.determinationType"
                        type="radio"
                        value="not_applicable"
                    />
                    Not Applicable
                </label>
            </div>

            <div v-else class="grid gap-2">
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
                        Defaulted from {{ defaultSourceDescription }}. Confirm
                        it as-is, or change it and record why.
                    </template>
                    <template v-else>
                        No amount was proposed. Record the amount your office
                        determines.
                    </template>
                </p>
                <dl
                    v-if="draft.determinationType === 'override'"
                    class="grid grid-cols-3 gap-2 rounded-lg bg-muted/40 p-3 text-xs"
                >
                    <div>
                        <dt>
                            {{
                                isLaboratoryProposal
                                    ? 'Laboratory proposal'
                                    : 'Scheduled'
                            }}
                        </dt>
                        <dd class="font-semibold">₱{{ proposalPesos }}</dd>
                    </div>
                    <div>
                        <dt>Determined</dt>
                        <dd class="font-semibold">
                            ₱{{ draft.amount || '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt>Variance</dt>
                        <dd class="font-semibold">
                            {{
                                varianceMinor === null
                                    ? '—'
                                    : `${varianceMinor < 0 ? '−' : '+'}₱${Math.abs(varianceMinor / 100).toFixed(2)}`
                            }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div
                v-if="draft.determinationType === 'override'"
                class="grid gap-2"
            >
                <Label :for="`authority-${item.id}`"
                    >Authority / policy basis *</Label
                >
                <textarea
                    :id="`authority-${item.id}`"
                    v-model="draft.authority"
                    required
                    rows="2"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                />
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
