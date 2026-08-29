<script setup lang="ts">
import { CircleAlert } from '@lucide/vue';
import ChargeStatusChip from '@/components/services-and-fees/ChargeStatusChip.vue';
import { money } from '@/lib/pricing-status';
import type { PricingStatusInfo } from '@/lib/pricing-status';
import type { MunicipalServiceOffering } from '@/types';

defineProps<{
    pricing: MunicipalServiceOffering['pricing'];
}>();

const inForceStatus: PricingStatusInfo = {
    key: 'in_force',
    label: 'In Force',
    tone: 'green',
};
</script>

<template>
    <div class="space-y-5">
        <div v-if="pricing.confirmed_charges.length > 0" class="space-y-3">
            <div>
                <p class="text-xs font-semibold tracking-wide uppercase">
                    Currently confirmed charge
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    One confirmed component of the final assessment
                </p>
            </div>
            <div
                v-for="charge in pricing.confirmed_charges"
                :key="charge.traceability.fee_rule_id"
                class="rounded-xl border border-emerald-300 bg-emerald-50/70 p-4 text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-50"
            >
                <div
                    class="flex flex-wrap items-start justify-between gap-x-4 gap-y-3"
                >
                    <div>
                        <p class="font-medium">{{ charge.label }}</p>
                        <p
                            class="mt-1 text-xs text-emerald-800 dark:text-emerald-200"
                        >
                            {{ charge.traceability.legal_basis }}
                        </p>
                    </div>
                    <div class="text-left sm:text-right">
                        <p
                            class="text-2xl font-semibold tracking-tight tabular-nums"
                        >
                            {{ money(charge.amount_cents) }}
                            <span class="text-sm font-medium"
                                >/ {{ charge.cadence }}</span
                            >
                        </p>
                        <ChargeStatusChip
                            :status="inForceStatus"
                            class="mt-2"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div
            class="flex items-start gap-3 rounded-xl bg-muted/60 p-4 text-sm leading-6"
        >
            <CircleAlert
                class="mt-0.5 size-5 shrink-0 text-muted-foreground"
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
