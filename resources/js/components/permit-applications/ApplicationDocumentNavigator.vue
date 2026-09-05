<script setup lang="ts">
type ApplicationPage = 'application' | 'processing';

defineProps<{
    activePage: ApplicationPage;
    declarationState: string;
    routingStatus: string;
    officeCount: number;
    resolvedDeterminationCount: number;
    requiredDeterminationCount: number;
    paymentOrderCount: number;
    emergingTotalAmountCents: number | null;
    unresolvedChargeCount: number;
}>();

const emit = defineEmits<{
    select: [page: ApplicationPage];
}>();

function money(amountCents: number | null): string {
    if (amountCents === null) {
        return 'Not yet available';
    }

    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amountCents / 100);
}
</script>

<template>
    <nav
        data-testid="application-document-navigator"
        aria-label="Application Form pages"
        class="sticky top-0 z-20 border-b border-slate-300 bg-[#f6f0df]/95 p-2 shadow-sm backdrop-blur sm:p-3 dark:border-slate-700 dark:bg-slate-950/95 print:hidden"
    >
        <div
            role="tablist"
            aria-label="Application Form Page 1 and Page 2"
            class="grid min-w-0 grid-cols-2 gap-2"
        >
            <button
                type="button"
                role="tab"
                :aria-selected="activePage === 'application'"
                data-testid="application-form-page-1-tab"
                :class="
                    activePage === 'application'
                        ? 'border-sky-700 bg-sky-700 text-white shadow-sm'
                        : 'border-slate-300 bg-white text-slate-700 hover:border-sky-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200'
                "
                class="min-w-0 rounded-lg border px-3 py-2.5 text-left outline-none focus-visible:ring-2 focus-visible:ring-amber-500 sm:px-4"
                @click="emit('select', 'application')"
            >
                <span
                    class="block text-[10px] font-black tracking-wider uppercase sm:text-xs"
                    >Page 1</span
                >
                <span class="block truncate text-xs font-black sm:text-sm"
                    >Applicant Declaration</span
                >
                <span
                    class="mt-1 block text-[10px] font-bold uppercase sm:text-xs"
                >
                    {{
                        declarationState === 'frozen'
                            ? 'Frozen'
                            : declarationState
                    }}
                </span>
            </button>

            <button
                type="button"
                role="tab"
                :aria-selected="activePage === 'processing'"
                data-testid="application-form-page-2-tab"
                :class="
                    activePage === 'processing'
                        ? 'border-amber-500 bg-amber-300 text-amber-950 shadow-sm'
                        : 'border-slate-300 bg-white text-slate-700 hover:border-amber-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200'
                "
                class="min-w-0 rounded-lg border px-3 py-2.5 text-left outline-none focus-visible:ring-2 focus-visible:ring-amber-500 sm:px-4"
                @click="emit('select', 'processing')"
            >
                <span
                    class="block text-[10px] font-black tracking-wider uppercase sm:text-xs"
                    >Page 2</span
                >
                <span class="block truncate text-xs font-black sm:text-sm"
                    >Municipal Processing</span
                >
                <span
                    class="mt-1 block text-[10px] font-bold uppercase sm:text-xs"
                >
                    Living · {{ officeCount }} offices ·
                    {{ resolvedDeterminationCount }}/{{
                        requiredDeterminationCount
                    }}
                    determinations
                </span>
            </button>
        </div>

        <div
            v-if="activePage === 'processing'"
            data-testid="application-form-page-2-summary"
            class="mt-2 grid min-w-0 grid-cols-2 gap-2 text-[10px] sm:grid-cols-4 sm:text-xs"
        >
            <p
                class="min-w-0 rounded-md bg-white/80 px-2 py-1.5 dark:bg-slate-900"
            >
                <span class="block text-slate-500 uppercase">Routing</span>
                <strong class="block truncate capitalize">{{
                    routingStatus.replaceAll('_', ' ')
                }}</strong>
            </p>
            <p
                class="min-w-0 rounded-md bg-white/80 px-2 py-1.5 dark:bg-slate-900"
            >
                <span class="block text-slate-500 uppercase">PPOs</span>
                <strong>{{ paymentOrderCount }}</strong>
            </p>
            <p
                class="min-w-0 rounded-md bg-white/80 px-2 py-1.5 dark:bg-slate-900"
            >
                <span class="block text-slate-500 uppercase"
                    >Emerging total</span
                >
                <strong class="block truncate">{{
                    money(emergingTotalAmountCents)
                }}</strong>
            </p>
            <p
                class="min-w-0 rounded-md bg-white/80 px-2 py-1.5 dark:bg-slate-900"
            >
                <span class="block text-slate-500 uppercase"
                    >Unresolved charges</span
                >
                <strong>{{ unresolvedChargeCount }}</strong>
            </p>
        </div>
    </nav>
</template>
