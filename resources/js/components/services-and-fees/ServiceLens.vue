<script setup lang="ts">
import ChargeStatusChip from '@/components/services-and-fees/ChargeStatusChip.vue';
import InternalRuleRow from '@/components/services-and-fees/InternalRuleRow.vue';
import LineOfBusinessScheduleCard from '@/components/services-and-fees/LineOfBusinessScheduleCard.vue';
import PricingStatusBadge from '@/components/services-and-fees/PricingStatusBadge.vue';
import { money, OFFICE_DETERMINED_STATUS } from '@/lib/pricing-status';
import type { MunicipalPriceList } from '@/types';

withDefaults(
    defineProps<{
        priceList: MunicipalPriceList;
        concise?: boolean;
    }>(),
    {
        concise: false,
    },
);
</script>

<template>
    <section aria-label="Service pricing controls" class="space-y-4">
        <article
            v-for="service in priceList.services"
            :key="service.code"
            class="min-w-0 overflow-hidden rounded-2xl border bg-card"
        >
            <div class="space-y-5 p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="max-w-3xl space-y-1">
                        <h2 class="text-lg font-semibold">
                            {{ service.name }}
                        </h2>
                        <p class="text-sm leading-6 text-muted-foreground">
                            {{ service.description }}
                        </p>
                    </div>
                    <PricingStatusBadge
                        :availability="service.availability"
                        :label="service.availability_label"
                    />
                </div>

                <div
                    class="space-y-3 rounded-xl bg-muted/50 p-4"
                >
                    <p class="text-xs font-semibold tracking-wide uppercase">
                        What the public sees
                    </p>
                    <template v-if="service.pricing.confirmed_charges.length > 0">
                        <div
                            v-for="charge in service.pricing.confirmed_charges"
                            :key="charge.traceability.fee_rule_id"
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <p class="font-medium">{{ charge.label }}</p>
                            <p class="text-xl font-semibold tabular-nums">
                                {{ money(charge.amount_cents) }} /
                                {{ charge.cadence }}
                            </p>
                        </div>
                    </template>
                    <p v-else class="text-sm leading-6">
                        Municipal confirmation is still required; no exact
                        service price is published.
                    </p>
                    <p
                        class="border-t pt-3 text-sm leading-6 text-muted-foreground"
                    >
                        {{ service.pricing.other_charges_message }}
                    </p>
                </div>

                <div v-if="!concise" class="grid min-w-0 gap-4">
                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <p
                                class="text-xs font-semibold tracking-wide uppercase"
                            >
                                Recorded charges & rules
                            </p>
                            <span
                                class="text-xs text-muted-foreground tabular-nums"
                            >
                                {{ service.internal?.selected_rule_count ?? 0 }}
                                rule(s)
                            </span>
                        </div>

                        <div
                            v-if="service.internal?.rules.length"
                            class="mt-3 space-y-3"
                        >
                            <InternalRuleRow
                                v-for="rule in service.internal.rules"
                                :key="rule.id"
                                :rule="rule"
                            />
                        </div>
                    </div>

                    <div v-if="service.internal?.line_of_business_pricing.length">
                        <p class="text-xs font-semibold tracking-wide uppercase">
                            Line of business schedules
                        </p>
                        <div class="mt-3 space-y-3">
                            <LineOfBusinessScheduleCard
                                v-for="entry in service.internal.line_of_business_pricing"
                                :key="entry.id"
                                :entry="entry"
                            />
                        </div>
                    </div>

                    <div class="rounded-xl border border-dashed p-4 text-sm leading-6">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-medium">Concerned municipal offices</p>
                            <ChargeStatusChip :status="OFFICE_DETERMINED_STATUS" />
                        </div>
                        <p class="mt-1 text-muted-foreground">
                            {{ service.internal?.office_determined.display }}
                        </p>
                        <p class="mt-2 text-xs text-muted-foreground">
                            No office-specific official amount is asserted
                            here.
                        </p>
                    </div>
                </div>

                <div
                    v-else-if="service.internal?.rules.length"
                    class="space-y-3"
                >
                    <p class="text-xs font-semibold tracking-wide uppercase">
                        Charge components
                    </p>
                    <InternalRuleRow
                        v-for="rule in service.internal.rules"
                        :key="rule.id"
                        :rule="rule"
                        :concise="true"
                    />
                </div>
            </div>
        </article>
    </section>
</template>
