<script setup lang="ts">
type FeeMenu = {
    title: string;
    scope: string;
    as_of_date: string;
    application_year: number;
    classification: string;
    statement: string;
    services: Record<string, any>[];
};

defineProps<{ feeMenu: FeeMenu }>();

function money(amountCents: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amountCents / 100);
}
</script>

<template>
    <article
        data-testid="municipal-fee-menu-sheet"
        class="mx-auto w-full max-w-[48rem] overflow-hidden border border-amber-950/30 bg-[#fffaf0] text-stone-950 shadow-[0_16px_40px_rgba(68,47,23,0.18)] print:max-w-none print:shadow-none"
    >
        <header
            class="border-b-4 border-double border-amber-950 px-5 py-6 text-center sm:px-8"
        >
            <p class="text-[10px] font-black tracking-[0.24em] uppercase">
                Republic of the Philippines · Municipality of Ipil
            </p>
            <h3
                class="mt-2 font-serif text-3xl font-black tracking-tight sm:text-4xl"
            >
                {{ feeMenu.title }}
            </h3>
            <p class="mt-2 text-xs font-bold tracking-wide uppercase">
                {{ feeMenu.scope }} · {{ feeMenu.application_year }}
            </p>
        </header>

        <div class="grid gap-x-10 gap-y-8 px-5 py-7 sm:px-8 md:grid-cols-2">
            <section
                v-for="(service, serviceIndex) in feeMenu.services"
                :key="service.code"
                :class="serviceIndex === 0 ? 'md:col-span-2' : ''"
                class="min-w-0 break-inside-avoid"
            >
                <div
                    class="flex items-baseline gap-3 border-b border-amber-950/40 pb-1"
                >
                    <span class="font-serif text-xl font-black">{{
                        serviceIndex + 1
                    }}</span>
                    <h4
                        class="min-w-0 font-serif text-lg font-black break-words uppercase"
                    >
                        {{ service.name }}
                    </h4>
                </div>
                <p class="mt-2 text-xs leading-5 text-stone-700">
                    {{ service.description }}
                </p>

                <div
                    v-if="service.pricing.confirmed_charges.length"
                    class="mt-4 grid gap-3"
                >
                    <div
                        v-for="charge in service.pricing.confirmed_charges"
                        :key="charge.traceability.rule_code"
                        class="grid min-w-0 grid-cols-[minmax(0,1fr)_auto] items-end gap-2 text-sm"
                    >
                        <span
                            class="min-w-0 overflow-hidden font-semibold break-words"
                        >
                            {{ charge.label }}
                            <span
                                aria-hidden="true"
                                class="ml-1 tracking-widest text-stone-400"
                                >··············</span
                            >
                        </span>
                        <strong class="shrink-0 font-mono">{{
                            money(charge.amount_cents)
                        }}</strong>
                    </div>
                </div>
                <p v-else class="mt-4 text-sm font-bold italic">
                    Amount determined from the Application and concerned-office
                    findings.
                </p>

                <p class="mt-3 text-[10px] leading-4 text-stone-600">
                    {{ service.pricing.other_charges_message }}
                </p>
            </section>
        </div>

        <footer
            class="grid gap-4 border-t-4 border-double border-amber-950 px-5 py-5 sm:grid-cols-[1fr_auto] sm:items-end sm:px-8"
        >
            <div>
                <p class="text-xs font-bold">
                    Effective view: {{ feeMenu.as_of_date }}
                </p>
                <p class="mt-1 max-w-xl text-[10px] leading-4">
                    {{ feeMenu.statement }}
                </p>
            </div>
            <div
                class="w-fit -rotate-2 border-2 border-rose-800 px-3 py-2 text-center text-[10px] font-black tracking-wider text-rose-800 uppercase"
            >
                Reference only<br />Not an Assessment
            </div>
        </footer>
    </article>
</template>
