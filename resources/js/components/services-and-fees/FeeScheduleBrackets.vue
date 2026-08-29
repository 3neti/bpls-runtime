<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed, ref } from 'vue';
import { money } from '@/lib/pricing-status';
import type { FeeRuleRangePreview } from '@/types';

const props = withDefaults(
    defineProps<{
        ranges: FeeRuleRangePreview[];
        initialVisibleCount?: number;
    }>(),
    {
        initialVisibleCount: 6,
    },
);

const expanded = ref(false);

const sortedRanges = computed(() =>
    [...props.ranges].sort((a, b) => a.min_basis_cents - b.min_basis_cents),
);

const visibleRanges = computed(() =>
    expanded.value
        ? sortedRanges.value
        : sortedRanges.value.slice(0, props.initialVisibleCount),
);

const hiddenCount = computed(
    () => sortedRanges.value.length - visibleRanges.value.length,
);

function basisRangeLabel(range: FeeRuleRangePreview): string {
    if (range.max_basis_cents === null) {
        return `${money(range.min_basis_cents)} and above`;
    }

    return `${money(range.min_basis_cents)} – ${money(range.max_basis_cents)}`;
}

function amountLabel(range: FeeRuleRangePreview): string {
    if (range.rate_basis_points !== null) {
        return `${range.rate_basis_points / 100}%`;
    }

    return money(range.amount_cents);
}
</script>

<template>
    <div class="min-w-0">
        <p
            class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
        >
            Recorded brackets ({{ sortedRanges.length }})
        </p>

        <ul class="mt-2 grid gap-1.5" role="list">
            <li
                v-for="(range, index) in visibleRanges"
                :key="index"
                class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5 rounded-lg border bg-muted/40 px-3 py-2 text-sm"
            >
                <span class="text-muted-foreground">{{
                    basisRangeLabel(range)
                }}</span>
                <span class="font-medium tabular-nums">{{
                    amountLabel(range)
                }}</span>
            </li>
        </ul>

        <button
            v-if="hiddenCount > 0 || expanded"
            type="button"
            class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-primary outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
            :aria-expanded="expanded"
            @click="expanded = !expanded"
        >
            <ChevronDown
                class="size-4 transition-transform"
                :class="expanded ? 'rotate-180' : ''"
                aria-hidden="true"
            />
            {{
                expanded
                    ? 'Show fewer brackets'
                    : `Show all ${sortedRanges.length} brackets`
            }}
        </button>
    </div>
</template>
