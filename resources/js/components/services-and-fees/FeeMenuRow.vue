<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { ref } from 'vue';
import ChargeStatusChip from '@/components/services-and-fees/ChargeStatusChip.vue';
import FeeScheduleBrackets from '@/components/services-and-fees/FeeScheduleBrackets.vue';
import type { PricingStatusInfo } from '@/lib/pricing-status';
import type { FeeRuleRangePreview } from '@/types';

withDefaults(
    defineProps<{
        name: string;
        code?: string | null;
        priceLabel: string;
        status: PricingStatusInfo;
        appliesTo?: string[];
        office?: string | null;
        basis?: string | null;
        effectiveFrom?: string | null;
        effectiveUntil?: string | null;
        legalBasis?: string | null;
        policyNote?: string | null;
        ranges?: FeeRuleRangePreview[];
        concise?: boolean;
    }>(),
    {
        code: null,
        appliesTo: () => [],
        office: null,
        basis: null,
        effectiveFrom: null,
        effectiveUntil: null,
        legalBasis: null,
        policyNote: null,
        ranges: () => [],
        concise: false,
    },
);

const open = ref(false);
</script>

<template>
    <div class="min-w-0 rounded-xl border bg-background">
        <div class="flex flex-wrap items-start justify-between gap-3 p-4">
            <div class="min-w-0">
                <p class="text-base font-semibold break-words">{{ name }}</p>
                <p
                    v-if="code && !concise"
                    class="mt-0.5 font-mono text-xs break-all text-muted-foreground"
                >
                    {{ code }}
                </p>
                <p
                    v-if="appliesTo.length > 0"
                    class="mt-1.5 text-xs text-muted-foreground"
                >
                    Applies to: {{ appliesTo.join(', ') }}
                </p>
                <p v-if="office" class="mt-1.5 text-xs text-muted-foreground">
                    Office: {{ office }}
                </p>
            </div>
            <div class="flex flex-col items-end gap-1.5">
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <span class="text-lg font-semibold tabular-nums">{{
                        priceLabel
                    }}</span>
                    <ChargeStatusChip :status="status" />
                </div>
                <p
                    v-if="policyNote"
                    class="max-w-xs text-right text-xs text-muted-foreground"
                >
                    {{ policyNote }}
                </p>
            </div>
        </div>

        <template v-if="!concise && (legalBasis || ranges.length > 0)">
            <button
                type="button"
                class="flex w-full items-center gap-1.5 border-t px-4 py-2.5 text-left text-xs font-medium text-muted-foreground outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
                :aria-expanded="open"
                @click="open = !open"
            >
                <ChevronDown
                    class="size-3.5 shrink-0 transition-transform"
                    :class="open ? 'rotate-180' : ''"
                    aria-hidden="true"
                />
                {{ open ? 'Hide details' : 'Show legal basis and detail' }}
            </button>
            <div v-if="open" class="space-y-3 border-t p-4 text-sm">
                <p v-if="basis" class="text-muted-foreground">
                    Basis: {{ basis.replaceAll('_', ' ') }}
                </p>
                <p v-if="effectiveFrom" class="text-muted-foreground">
                    Effective {{ effectiveFrom }} to
                    {{ effectiveUntil ?? 'present' }}
                </p>
                <p v-if="legalBasis" class="leading-6">{{ legalBasis }}</p>
                <FeeScheduleBrackets
                    v-if="ranges.length > 0"
                    :ranges="ranges"
                />
            </div>
        </template>
    </div>
</template>
