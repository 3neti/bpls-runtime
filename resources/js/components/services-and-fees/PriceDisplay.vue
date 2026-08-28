<script setup lang="ts">
import { CircleAlert } from '@lucide/vue';
import type { MunicipalServiceOffering } from '@/types';

defineProps<{
    pricing: MunicipalServiceOffering['pricing'];
}>();

function money(amountCents: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
    }).format(amountCents / 100);
}
</script>

<template>
    <div class="space-y-4">
        <div v-if="pricing.confirmed_charges.length > 0" class="space-y-2">
            <p class="text-xs font-semibold tracking-wide uppercase">
                Currently confirmed charge
            </p>
            <div
                v-for="charge in pricing.confirmed_charges"
                :key="charge.traceability.fee_rule_id"
                class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 rounded-lg border bg-background p-3"
            >
                <span class="text-sm font-medium">{{ charge.label }}</span>
                <span class="text-lg font-semibold tabular-nums">
                    {{ money(charge.amount_cents) }} / {{ charge.cadence }}
                </span>
            </div>
        </div>

        <div class="flex items-start gap-2.5 text-sm leading-6">
            <CircleAlert
                class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
            <div>
                <p class="font-semibold">{{ pricing.other_charges_heading }}</p>
                <p class="text-muted-foreground">
                    {{ pricing.other_charges_message }}
                </p>
            </div>
        </div>
    </div>
</template>
