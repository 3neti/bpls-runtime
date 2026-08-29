<script setup lang="ts">
import { computed } from 'vue';
import FeeMenuRow from '@/components/services-and-fees/FeeMenuRow.vue';
import { money } from '@/lib/pricing-status';
import type { PricingStatusInfo } from '@/lib/pricing-status';
import type { MunicipalPriceList } from '@/types';

const props = defineProps<{
    priceList: MunicipalPriceList;
}>();

const inForceStatus: PricingStatusInfo = {
    key: 'in_force',
    label: 'In Force',
    tone: 'green',
};

const confirmedCharges = computed(() =>
    props.priceList.services.flatMap(
        (service) => service.pricing.confirmed_charges,
    ),
);
</script>

<template>
    <section aria-label="Common fees and charges" class="space-y-3">
        <div>
            <h2 class="text-xl font-semibold tracking-tight">
                Common Fees & Charges
            </h2>
            <p class="mt-1 text-sm leading-6 text-muted-foreground">
                The Municipality's confirmed charges at a glance, before you
                open a specific service.
            </p>
        </div>

        <FeeMenuRow
            v-for="charge in confirmedCharges"
            :key="charge.traceability.fee_rule_id"
            :name="charge.label"
            :price-label="`${money(charge.amount_cents)} / ${charge.cadence}`"
            :status="inForceStatus"
            :legal-basis="charge.traceability.legal_basis"
            :effective-from="charge.traceability.effective_from"
            :effective-until="charge.traceability.effective_until"
        />

        <p
            class="rounded-xl border border-dashed p-4 text-sm leading-6 text-muted-foreground"
        >
            Business tax, permit fees, and charges from concerned municipal
            offices may also apply depending on your business information and
            applicable municipal rules.
        </p>
    </section>
</template>
