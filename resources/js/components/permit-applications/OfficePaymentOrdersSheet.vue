<script setup lang="ts">
defineProps<{ offices: Record<string, any>[]; applicationYear: number }>();

function money(amountCents: number | null | undefined): string {
    if (amountCents === null || amountCents === undefined) {
        return '—';
    }

    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amountCents / 100);
}
</script>

<template>
    <article
        data-testid="office-payment-orders-sheet"
        class="mx-auto min-h-[48rem] w-full max-w-[48rem] bg-[#f9fcff] p-5 text-slate-950 shadow-xl sm:p-8"
    >
        <header class="border-b-2 border-slate-900 pb-4 text-center">
            <p class="text-[10px] font-black tracking-[0.2em] uppercase">
                Municipality of Ipil · Business Permit and Licensing
            </p>
            <h3
                class="mt-2 font-serif text-2xl font-black uppercase sm:text-3xl"
            >
                Office Payment Orders
            </h3>
            <p class="mt-1 text-xs font-bold">
                Application Year {{ applicationYear }}
            </p>
        </header>

        <div
            v-if="offices.some((office) => office.payment_orders.length > 0)"
            class="mt-6 grid gap-6"
        >
            <section
                v-for="office in offices.filter(
                    (candidate) => candidate.payment_orders.length > 0,
                )"
                :key="office.code"
                class="break-inside-avoid"
            >
                <div
                    class="flex flex-wrap items-end justify-between gap-2 border-b border-slate-800 pb-1"
                >
                    <h4 class="font-serif text-lg font-black uppercase">
                        {{ office.label }}
                    </h4>
                    <p class="text-[10px] font-bold uppercase">
                        {{ office.paperless_payment_order_count }} order(s) ·
                        {{ office.status.replaceAll('_', ' ') }}
                    </p>
                </div>
                <div
                    v-for="order in office.payment_orders"
                    :key="order.id"
                    class="mt-3 break-inside-avoid"
                >
                    <p class="text-[10px] font-bold uppercase">
                        Payment Order {{ order.sequence }} · Issued
                        {{ order.issued_at }}
                    </p>
                    <div class="mt-1 grid gap-1">
                        <div
                            v-for="line in order.lines"
                            :key="line.id"
                            class="grid min-w-0 grid-cols-[minmax(0,1fr)_auto] gap-3 border-b border-dotted border-slate-400 py-2 text-sm"
                        >
                            <span class="min-w-0 break-words">
                                {{ line.name }}
                                <small
                                    class="block font-mono text-[9px] break-all"
                                    >{{ line.code }}</small
                                >
                            </span>
                            <strong class="font-mono">{{
                                money(line.amount_cents)
                            }}</strong>
                        </div>
                    </div>
                </div>
                <p class="mt-2 text-right text-sm font-black">
                    Office contribution {{ money(office.total_amount_cents) }}
                </p>
            </section>
        </div>
        <div
            v-else
            class="mt-10 border border-dashed border-slate-400 p-8 text-center text-sm"
        >
            No Office Payment Order is attached until BPLO routing and canonical
            office work create one.
        </div>

        <footer
            class="mt-10 border-t border-slate-500 pt-3 text-[10px] leading-4"
        >
            This continuation sheet projects canonical office responsibilities
            and issued Paperless Payment Orders. It does not create an
            Assessment.
        </footer>
    </article>
</template>
