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
    financialLineItemsResolved,
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
const resolved = computed(() => financialLineItemsResolved(props.modelValue));
const selectedOption = computed(() =>
    props.options.find((candidate) => candidate.id === selectedId.value),
);

function choose(): void {
    const option = props.options.find(
        (candidate) => candidate.id === selectedId.value,
    );
    amount.value =
        option && option.resolution_status !== 'unresolved'
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

    if (!option || option.resolution_status === 'unresolved') {
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
    <div class="grid w-full min-w-0 grid-cols-1 gap-3">
        <div
            class="grid min-w-0 grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_10rem_auto]"
        >
            <select
                v-model="selectedId"
                class="h-9 w-full max-w-full min-w-0 rounded-md border bg-background px-3 text-sm"
                @change="choose"
            >
                <option :value="null">Select fee</option>
                <option
                    v-for="option in options"
                    :key="option.id"
                    :value="option.id"
                >
                    {{ option.name
                    }}{{
                        option.resolution_status === 'unresolved'
                            ? ' — ' + option.resolution_message
                            : ''
                    }}
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
                    :disabled="
                        selectedOption?.resolution_status === 'unresolved'
                    "
                    aria-label="Amount in pesos"
                    class="w-full min-w-0 pl-7 tabular-nums"
                    inputmode="decimal"
                    type="text"
                    :placeholder="
                        selectedOption?.resolution_status === 'unresolved'
                            ? 'TBD'
                            : '0.00'
                    "
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
        <div
            v-if="modelValue.length"
            class="grid min-w-0 grid-cols-1 gap-1 text-sm"
        >
            <div
                v-for="item in modelValue"
                :key="item.fee_rule_id"
                class="flex min-w-0 flex-wrap items-center justify-between gap-x-3 gap-y-1 border-b py-2"
            >
                <span class="min-w-0 flex-1 basis-40 break-words"
                    >{{ item.name
                    }}<span v-if="item.resolution_status === 'unresolved'">
                        — {{ item.resolution_message }}</span
                    ></span
                >
                <span class="flex min-w-0 flex-wrap items-center gap-3"
                    ><strong>{{
                        item.resolution_status === 'unresolved'
                            ? 'TBD'
                            : money(item.amount_cents)
                    }}</strong
                    ><button
                        v-if="
                            item.resolution_status !== 'unresolved' &&
                            !item.amount_locked
                        "
                        type="button"
                        class="text-xs text-destructive"
                        @click="remove(item.fee_rule_id)"
                    >
                        Remove
                    </button></span
                >
            </div>
            <p class="min-w-0 text-right font-black break-words">
                Subtotal {{ resolved ? money(subtotal) : 'TBD — incomplete' }}
            </p>
            <p v-if="!resolved" class="min-w-0 text-right text-xs break-words">
                Known items (partial): {{ money(subtotal) }}
            </p>
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
