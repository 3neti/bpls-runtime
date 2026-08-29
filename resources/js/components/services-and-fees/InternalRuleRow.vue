<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed, ref } from 'vue';
import ChargeStatusChip from '@/components/services-and-fees/ChargeStatusChip.vue';
import FeeScheduleBrackets from '@/components/services-and-fees/FeeScheduleBrackets.vue';
import {
    recordedValueLabel,
    ruleStatus,
    sourceClassificationLabel,
} from '@/lib/pricing-status';
import type { InternalFeeRule } from '@/types';

const props = withDefaults(
    defineProps<{
        rule: InternalFeeRule;
        concise?: boolean;
    }>(),
    {
        concise: false,
    },
);

const open = ref(false);
const status = computed(() => ruleStatus(props.rule));

function toggle(): void {
    if (!props.concise) {
        open.value = !open.value;
    }
}
</script>

<template>
    <div class="min-w-0 rounded-xl border bg-background">
        <button
            type="button"
            class="flex w-full flex-wrap items-center justify-between gap-3 p-4 text-left outline-none focus-visible:ring-2 focus-visible:ring-ring"
            :class="concise ? 'cursor-default' : ''"
            :aria-expanded="open"
            @click="toggle"
        >
            <div class="min-w-0">
                <p class="font-medium break-words">{{ rule.name }}</p>
                <p
                    v-if="!concise"
                    class="mt-0.5 font-mono text-xs break-all text-muted-foreground"
                >
                    {{ rule.code }}
                </p>
            </div>
            <div class="flex flex-col items-end gap-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-semibold tabular-nums">{{
                        recordedValueLabel(rule)
                    }}</span>
                    <ChargeStatusChip :status="status" />
                </div>
                <p
                    v-if="rule.policy_note"
                    class="max-w-xs text-right text-xs text-muted-foreground"
                >
                    {{ rule.policy_note }}
                </p>
            </div>
            <div v-if="!concise" class="flex items-center">
                <ChevronDown
                    class="size-4 shrink-0 text-muted-foreground transition-transform"
                    :class="open ? 'rotate-180' : ''"
                    aria-hidden="true"
                />
            </div>
        </button>

        <div v-if="open && !concise" class="border-t p-4">
            <p class="text-sm leading-6">{{ rule.plain_language_status }}</p>

            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-muted-foreground uppercase">
                        Source classification
                    </dt>
                    <dd class="mt-1 font-medium">
                        {{ sourceClassificationLabel(rule.source_classification) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground uppercase">
                        Effective period
                    </dt>
                    <dd class="mt-1">
                        {{ rule.effective_from }} to
                        {{ rule.effective_until ?? 'present' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground uppercase">
                        Assessment availability
                    </dt>
                    <dd class="mt-1">{{ rule.automatic_assessment_label }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground uppercase">
                        Evidence version
                    </dt>
                    <dd class="mt-1">
                        <template v-if="rule.reconciliation">
                            Version {{ rule.reconciliation.version }} ·
                            {{ rule.reconciliation.legal_authority }}
                        </template>
                        <template v-else> No accepted version recorded </template>
                    </dd>
                </div>
                <div v-if="rule.legal_basis" class="sm:col-span-2">
                    <dt class="text-xs text-muted-foreground uppercase">
                        Legal and effective basis
                    </dt>
                    <dd class="mt-1 leading-6">{{ rule.legal_basis }}</dd>
                </div>
            </dl>

            <FeeScheduleBrackets
                v-if="rule.range_count > 0"
                :ranges="rule.ranges"
                class="mt-4"
            />
        </div>
    </div>
</template>
