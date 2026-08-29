<script setup lang="ts">
import { computed } from 'vue';
import FeeMenuRow from '@/components/services-and-fees/FeeMenuRow.vue';
import { CONCERNED_OFFICES, collectFeeMenuEntries } from '@/lib/price-book';
import { OFFICE_DETERMINED_STATUS, recordedValueLabel, ruleStatus } from '@/lib/pricing-status';
import type { MunicipalPriceList } from '@/types';

const props = withDefaults(
    defineProps<{
        priceList: MunicipalPriceList;
        concise?: boolean;
    }>(),
    {
        concise: false,
    },
);

const feeEntries = computed(() => collectFeeMenuEntries(props.priceList));
</script>

<template>
    <div class="space-y-3">
        <p class="text-sm text-muted-foreground">
            Every recorded charge, rate, and schedule BPLS currently knows
            about — the Municipality's pricing universe in one place.
        </p>

        <FeeMenuRow
            v-for="entry in feeEntries"
            :key="entry.id"
            :name="entry.name"
            :code="entry.code"
            :price-label="recordedValueLabel(entry)"
            :status="ruleStatus(entry)"
            :applies-to="entry.appliesToServices"
            :office="entry.line_of_business?.name ?? null"
            :basis="entry.basis"
            :effective-from="entry.effective_from"
            :effective-until="entry.effective_until"
            :legal-basis="entry.legal_basis"
            :policy-note="entry.policy_note"
            :ranges="entry.ranges"
            :concise="concise"
        />

        <FeeMenuRow
            v-for="office in CONCERNED_OFFICES"
            :key="office.code"
            :name="office.label"
            price-label="—"
            :status="OFFICE_DETERMINED_STATUS"
            :concise="true"
            :policy-note="`Determined by ${office.label} when applicable`"
        />
    </div>
</template>
