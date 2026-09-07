<script setup lang="ts">
import { CheckCircle2, Clock3 } from '@lucide/vue';
import type { ConcernedOfficePaymentOrderSummary } from '@/types';

defineProps<{ summary: ConcernedOfficePaymentOrderSummary }>();

function money(cents: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(cents / 100);
}
</script>

<template>
    <section
        class="overflow-hidden rounded-2xl border bg-card shadow-xs"
        aria-labelledby="concerned-office-payment-orders-heading"
        data-testid="concerned-office-payment-order-summary"
    >
        <header
            class="flex items-center justify-between gap-3 border-b p-4 sm:p-5"
        >
            <div class="min-w-0">
                <h2
                    id="concerned-office-payment-orders-heading"
                    class="font-semibold"
                >
                    Payment Order summary
                </h2>
            </div>
            <span
                :class="
                    summary.all_finalized
                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200'
                        : 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200'
                "
                class="inline-flex w-fit items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold"
            >
                <CheckCircle2
                    v-if="summary.all_finalized"
                    class="size-3.5"
                    aria-hidden="true"
                />
                <Clock3 v-else class="size-3.5" aria-hidden="true" />
                {{ summary.finalized_office_count }} of
                {{ summary.required_office_count }} finalized
            </span>
        </header>

        <div class="grid gap-3 p-4 sm:p-5">
            <dl class="grid gap-2 text-sm">
                <div
                    v-for="office in summary.offices"
                    :key="office.routing_work_id"
                    class="flex min-w-0 items-baseline justify-between gap-4 border-b border-dashed pb-2"
                >
                    <dt class="min-w-0 break-words">
                        {{ office.office_label }}
                    </dt>
                    <dd class="shrink-0 font-medium tabular-nums">
                        {{
                            office.status === 'finalized' &&
                            office.total_amount_cents !== null
                                ? money(office.total_amount_cents)
                                : 'TBD'
                        }}
                    </dd>
                </div>
            </dl>

            <div
                class="flex flex-wrap items-baseline justify-between gap-3 rounded-xl bg-muted/50 p-4"
            >
                <span class="font-medium">Office Payment Order subtotal</span>
                <strong
                    class="text-xl tabular-nums"
                    data-testid="concerned-office-payment-order-subtotal"
                >
                    {{
                        summary.finalized_subtotal_amount_cents === null
                            ? 'TBD'
                            : money(summary.finalized_subtotal_amount_cents)
                    }}
                </strong>
            </div>
        </div>
    </section>
</template>
