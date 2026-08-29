<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ExternalLink } from '@lucide/vue';
import { computed } from 'vue';
import { show as showFeeRule } from '@/actions/App/Http/Controllers/Staff/FeeRuleController';
import ChargeStatusChip from '@/components/services-and-fees/ChargeStatusChip.vue';
import FeeScheduleBrackets from '@/components/services-and-fees/FeeScheduleBrackets.vue';
import { Button } from '@/components/ui/button';
import { recordedValueLabel, ruleStatus } from '@/lib/pricing-status';
import type { InternalFeeRule } from '@/types';

const props = defineProps<{
    entry: InternalFeeRule;
}>();

const status = computed(() => ruleStatus(props.entry));
</script>

<template>
    <article class="min-w-0 rounded-xl border bg-background p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    {{ entry.line_of_business?.name ?? 'Line of business' }}
                </p>
                <p class="mt-1 font-medium break-words">{{ entry.name }}</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Basis: {{ entry.basis.replaceAll('_', ' ') }}
                </p>
            </div>
            <div class="flex flex-col items-end gap-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-semibold tabular-nums">{{
                        recordedValueLabel(entry)
                    }}</span>
                    <ChargeStatusChip :status="status" />
                </div>
                <p
                    v-if="entry.policy_note"
                    class="max-w-xs text-right text-xs text-muted-foreground"
                >
                    {{ entry.policy_note }}
                </p>
            </div>
        </div>

        <p class="mt-3 text-sm leading-6 text-muted-foreground">
            {{ entry.plain_language_status }}
        </p>

        <FeeScheduleBrackets
            v-if="entry.range_count > 0"
            :ranges="entry.ranges"
            class="mt-4"
        />

        <div
            class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t pt-3"
        >
            <p class="text-xs text-muted-foreground">
                {{ entry.legal_basis ?? 'No legal basis recorded.' }}
            </p>
            <Button variant="outline" size="sm" as-child>
                <Link :href="showFeeRule(entry.id)">
                    Full record in Taxes & Fees
                    <ExternalLink aria-hidden="true" />
                </Link>
            </Button>
        </div>
    </article>
</template>
