<script setup lang="ts">
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Option = {
    id: number;
    code: string;
    name: string;
    default_amount_cents: number;
    account_code?: string | null;
};
type Item = {
    fee_rule_id: number;
    code: string;
    name: string;
    amount_cents: number;
};

const props = defineProps<{ options: Option[]; modelValue: Item[] }>();
const emit = defineEmits<{ 'update:modelValue': [items: Item[]] }>();
const selectedId = ref<number | null>(null);
const amount = ref<number | undefined>();

const subtotal = computed(() =>
    props.modelValue.reduce((sum, item) => sum + item.amount_cents, 0),
);

function choose(): void {
    const option = props.options.find(
        (candidate) => candidate.id === selectedId.value,
    );
    amount.value = option?.default_amount_cents;
}

function add(): void {
    const option = props.options.find(
        (candidate) => candidate.id === selectedId.value,
    );

    if (!option || amount.value === undefined || amount.value < 0) {
        return;
    }

    emit('update:modelValue', [
        ...props.modelValue.filter((item) => item.fee_rule_id !== option.id),
        {
            fee_rule_id: option.id,
            code: option.code,
            name: option.name,
            amount_cents: amount.value,
        },
    ]);
    selectedId.value = null;
    amount.value = undefined;
}

function remove(id: number): void {
    emit(
        'update:modelValue',
        props.modelValue.filter((item) => item.fee_rule_id !== id),
    );
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
        <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_9rem_auto]">
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
                    <template v-if="option.account_code">
                        · {{ option.account_code }}</template
                    >
                    · {{ money(option.default_amount_cents) }}
                </option>
            </select>
            <Input
                v-model.number="amount"
                type="number"
                min="0"
                step="1"
                placeholder="Amount (centavos)"
            />
            <Button type="button" variant="outline" @click="add"
                >Add Item</Button
            >
        </div>
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
