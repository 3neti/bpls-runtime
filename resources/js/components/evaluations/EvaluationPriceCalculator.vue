<script setup lang="ts">
import { Calculator, Check, RefreshCw } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { pricePreview } from '@/actions/App/Http/Controllers/Staff/BusinessPermitEvaluationController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ProFormaDraft } from '@/lib/evaluationPresentation';
import type { EvaluationItem } from '@/types';

type ScheduleSelection = {
    serviceLabel: string;
    basis: string | null;
    scheduleReference: string | null;
    unitAmountMinor: number;
};

type PriceReport = {
    currency: string;
    groups: { key: string; label: string; currency: string; minor: number }[];
    components: {
        exact_once_key: string;
        label: string;
        responsible_office: string | null;
        resolved_minor: number;
    }[];
    total: { currency: string; minor: number };
};

type Preview = {
    official: false;
    currency: string;
    selection: {
        service_label: string;
        basis: string | null;
        schedule_reference: string | null;
        unit_amount_minor: number;
        quantity: number;
        line_total_minor: number;
    };
    pending_charges: { id: number; label: string; office: string }[];
    input_fingerprint: string;
    report_fingerprint: string;
    price_report: PriceReport;
};

const props = defineProps<{
    applicationId: number;
    applicationType: string;
    applicationYear: number;
    item: EvaluationItem;
    amount: string;
    selection: ScheduleSelection | null;
    defaultSource: string;
    defaultReference?: string | null;
}>();
const emit = defineEmits<{
    apply: [amount: string, proForma: ProFormaDraft];
}>();

const open = ref(false);
const loading = ref(false);
const error = ref<string | null>(null);
const preview = ref<Preview | null>(null);
const unitAmount = ref(props.amount);
const quantity = ref(1);
const serviceLabel = ref(props.selection?.serviceLabel ?? props.item.label);
const basis = ref(props.selection?.basis ?? '');
const scheduleReference = ref(props.selection?.scheduleReference ?? '');

watch(open, async (isOpen) => {
    if (!isOpen) {
        return;
    }

    unitAmount.value = props.selection
        ? (props.selection.unitAmountMinor / 100).toFixed(2)
        : props.amount;
    serviceLabel.value = props.selection?.serviceLabel ?? props.item.label;
    basis.value = props.selection?.basis ?? '';
    scheduleReference.value =
        props.selection?.scheduleReference ?? props.defaultReference ?? '';
    quantity.value = 1;
    preview.value = null;
    error.value = null;

    if (unitAmount.value !== '') {
        await calculate();
    }
});

const unitAmountMinor = computed(() => {
    const match = String(unitAmount.value)
        .trim()
        .match(/^(\d+)(?:\.(\d{1,2}))?$/);

    return match
        ? Number(match[1]) * 100 + Number((match[2] ?? '').padEnd(2, '0'))
        : null;
});

const money = (minor: number): string =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(minor / 100);

async function calculate(): Promise<void> {
    if (unitAmountMinor.value === null || quantity.value < 1) {
        error.value = 'Enter a valid amount and quantity.';

        return;
    }

    loading.value = true;
    error.value = null;
    preview.value = null;
    const params = new URLSearchParams({
        unit_amount_minor: String(unitAmountMinor.value),
        quantity: String(quantity.value),
        service_label: serviceLabel.value,
    });

    if (basis.value) {
        params.set('basis', basis.value);
    }

    if (scheduleReference.value) {
        params.set('schedule_reference', scheduleReference.value);
    }

    try {
        const response = await fetch(
            pricePreview([props.applicationId, props.item.id]).url +
                '?' +
                params.toString(),
            { headers: { Accept: 'application/json' } },
        );

        if (!response.ok) {
            throw new Error('The computation could not be prepared.');
        }

        preview.value = (await response.json()) as Preview;
    } catch (exception) {
        error.value =
            exception instanceof Error
                ? exception.message
                : 'The computation could not be prepared.';
    } finally {
        loading.value = false;
    }
}

function apply(): void {
    if (!preview.value) {
        return;
    }

    emit('apply', (preview.value.selection.line_total_minor / 100).toFixed(2), {
        unitAmountMinor: preview.value.selection.unit_amount_minor,
        quantity: preview.value.selection.quantity,
        serviceLabel: preview.value.selection.service_label,
        basis: preview.value.selection.basis,
        scheduleReference: preview.value.selection.schedule_reference,
        inputFingerprint: preview.value.input_fingerprint,
        reportFingerprint: preview.value.report_fingerprint,
    });
    open.value = false;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button type="button" variant="outline">
                <Calculator aria-hidden="true" />
                Pro Forma Calculator
            </Button>
        </DialogTrigger>
        <DialogContent
            class="h-[100dvh] w-screen max-w-none overflow-y-auto rounded-none sm:h-auto sm:max-h-[88vh] sm:w-[min(820px,calc(100vw-3rem))] sm:max-w-[820px] sm:rounded-xl"
            data-testid="evaluation-price-calculator"
        >
            <DialogHeader>
                <DialogTitle>Pro Forma Calculator</DialogTitle>
                <DialogDescription>
                    Preview only. Nothing is recorded or made payable here.
                </DialogDescription>
            </DialogHeader>

            <dl
                class="grid gap-2 rounded-xl bg-muted/50 p-3 text-sm sm:grid-cols-3"
            >
                <div>
                    <dt class="text-xs text-muted-foreground">Application</dt>
                    <dd class="font-medium capitalize">
                        {{ applicationType }} · {{ applicationYear }}
                    </dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs text-muted-foreground">
                        Line of Business
                    </dt>
                    <dd class="font-medium">
                        {{ item.line_of_business_name ?? 'Whole application' }}
                    </dd>
                </div>
                <div class="sm:col-span-3">
                    <dt class="text-xs text-muted-foreground">
                        Default source
                    </dt>
                    <dd class="font-medium">{{ defaultSource }}</dd>
                </div>
            </dl>

            <div class="grid gap-5">
                <div class="grid gap-3 rounded-xl border p-4 sm:grid-cols-2">
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="calculator-service">Service</Label>
                        <Input id="calculator-service" v-model="serviceLabel" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="calculator-amount">Unit amount (PHP)</Label>
                        <Input
                            id="calculator-amount"
                            v-model="unitAmount"
                            type="number"
                            min="0"
                            step="0.01"
                            inputmode="decimal"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="calculator-quantity">Quantity</Label>
                        <Input
                            id="calculator-quantity"
                            v-model.number="quantity"
                            type="number"
                            min="1"
                            max="100"
                            step="1"
                        />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="calculator-basis">Basis or condition</Label>
                        <Input id="calculator-basis" v-model="basis" />
                    </div>
                    <Button
                        type="button"
                        class="sm:col-span-2"
                        :disabled="loading"
                        @click="calculate"
                    >
                        <RefreshCw
                            :class="['size-4', loading ? 'animate-spin' : '']"
                            aria-hidden="true"
                        />
                        {{ loading ? 'Calculating…' : 'Recalculate pro forma' }}
                    </Button>
                </div>

                <p
                    v-if="error"
                    role="alert"
                    class="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive"
                >
                    {{ error }}
                </p>

                <section v-if="preview" class="grid gap-4" aria-live="polite">
                    <div
                        class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
                    >
                        <p
                            class="text-xs font-semibold tracking-wide uppercase"
                        >
                            Pro forma · not official
                        </p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums">
                            {{ money(preview.price_report.total.minor) }}
                        </p>
                        <p class="mt-1 text-sm">
                            This is the emerging application total, including
                            completed determinations and this proposed line.
                        </p>
                    </div>

                    <div class="overflow-hidden rounded-xl border">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/50 text-left">
                                <tr>
                                    <th class="px-3 py-2 font-medium">
                                        Component
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Amount
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="component in preview.price_report
                                        .components"
                                    :key="component.exact_once_key"
                                    class="border-b last:border-0"
                                >
                                    <td class="px-3 py-2.5">
                                        {{ component.label }}
                                    </td>
                                    <td
                                        class="px-3 py-2.5 text-right font-medium tabular-nums"
                                    >
                                        {{ money(component.resolved_minor) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p
                        v-if="preview.pending_charges.length"
                        class="text-sm text-muted-foreground"
                    >
                        {{ preview.pending_charges.length }} other
                        {{
                            preview.pending_charges.length === 1
                                ? 'charge remains'
                                : 'charges remain'
                        }}
                        unresolved and excluded from this total.
                    </p>
                </section>
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    :disabled="preview === null"
                    @click="apply"
                >
                    <Check aria-hidden="true" />
                    Apply to determination
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
