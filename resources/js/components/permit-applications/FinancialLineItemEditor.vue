<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type {
    FinancialLineItem as Item,
    FinancialLineItemOption as Option,
} from '@/lib/financialLineItems';
import {
    financialLineItemSubtotal,
    formatMinorAsPesoInput,
    parsePesoAmount,
    removeFinancialLineItem,
    upsertFinancialLineItem,
} from '@/lib/financialLineItems';

const props = defineProps<{ options: Option[]; modelValue: Item[] }>();
const emit = defineEmits<{ 'update:modelValue': [items: Item[]] }>();
const selectedId = ref<number | null>(null);
const amount = ref('');
const amountError = ref<string | null>(null);

const subtotal = computed(() => financialLineItemSubtotal(props.modelValue));
const selectedOption = computed(() =>
    props.options.find((candidate) => candidate.id === selectedId.value),
);

function choose(): void {
    const option = props.options.find(
        (candidate) => candidate.id === selectedId.value,
    );
    amount.value = option
        ? formatMinorAsPesoInput(option.default_amount_cents)
        : '';
    amountError.value = null;
}

function validateAmount(): void {
    const parsed = parsePesoAmount(amount.value);
    amountError.value = amount.value === '' || parsed.ok ? null : parsed.error;
}

watch(amount, validateAmount);

function add(): void {
    const option = props.options.find(
        (candidate) => candidate.id === selectedId.value,
    );

    if (!option) {
        return;
    }

    const parsed = parsePesoAmount(amount.value);

    if (!parsed.ok) {
        amountError.value = parsed.error;

        return;
    }

    emit(
        'update:modelValue',
        upsertFinancialLineItem(props.modelValue, option, parsed.amountCents),
    );
    selectedId.value = null;
    amount.value = '';
    amountError.value = null;
}

function remove(id: number): void {
    emit('update:modelValue', removeFinancialLineItem(props.modelValue, id));
}

function money(cents: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(cents / 100);
}
</script>

<template>
    <div class="grid gap-3">
        <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_10rem_auto]">
            <select
                v-model="selectedId"
                class="h-9 min-w-0 rounded-md border bg-background px-3 text-sm"
                @change="choose"
            >
                <option :value="null">Select fee</option>
                <option
                    v-for="option in options"
                    :key="option.id"
                    :value="option.id"
                >
                    {{ option.name }}
                </option>
            </select>
            <div class="relative min-w-0">
                <span
                    aria-hidden="true"
                    class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm font-semibold text-muted-foreground"
                    >₱</span
                >
                <Input
                    v-model="amount"
                    aria-label="Amount in pesos"
                    class="pl-7 tabular-nums"
                    inputmode="decimal"
                    type="text"
                    placeholder="0.00"
                />
            </div>
            <Button
                type="button"
                variant="outline"
                :disabled="selectedOption === undefined || amount === ''"
                @click="add"
                >Add Item</Button
            >
        </div>
        <p v-if="amountError" role="alert" class="text-xs text-destructive">
            {{ amountError }}
        </p>
        <p
            v-if="selectedOption?.calculation?.explanation"
            class="text-xs font-medium text-muted-foreground"
        >
            {{ selectedOption.calculation.explanation }}
        </p>
        <div v-if="modelValue.length" class="grid gap-1 text-sm">
            <div
                v-for="item in modelValue"
                :key="item.fee_rule_id"
                class="flex items-center justify-between gap-3 border-b py-2"
            >
                <span>{{ item.name }}</span>
                <span class="flex items-center gap-3"
                    ><strong>{{ money(item.amount_cents) }}</strong
                    ><button
                        type="button"
                        class="text-xs text-destructive"
                        @click="remove(item.fee_rule_id)"
                    >
                        Remove
                    </button></span
                >
            </div>
            <p class="text-right font-black">Subtotal {{ money(subtotal) }}</p>
        </div>
        <p
            v-else-if="options.length === 0"
            class="text-xs text-muted-foreground"
        >
            No preview fee menu is configured for this office. Awaiting the
            Nelson schedule.
        </p>
    </div>
</template>
