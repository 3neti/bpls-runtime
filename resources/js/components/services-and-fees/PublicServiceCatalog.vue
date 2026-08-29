<script setup lang="ts">
import { CircleHelp, ClipboardList, ReceiptText } from '@lucide/vue';
import CommonFeesSection from '@/components/services-and-fees/CommonFeesSection.vue';
import ServiceOfferingCard from '@/components/services-and-fees/ServiceOfferingCard.vue';
import type { MunicipalPriceList } from '@/types';

defineProps<{
    priceList: MunicipalPriceList;
}>();
</script>

<template>
    <div class="space-y-10">
        <header class="space-y-3">
            <p class="text-sm font-medium text-muted-foreground">
                Municipality of Ipil · Business permits
            </p>
            <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">
                Services & Fees
            </h1>
            <p class="max-w-3xl text-base leading-7 text-muted-foreground">
                A read-only price book for Business Permit and Licensing
                services: what you can apply for, which services are available
                online, and the charges the Municipality currently confirms.
            </p>
        </header>

        <section
            aria-label="How to read this price book"
            class="grid gap-3 rounded-xl border bg-muted/40 p-4 sm:grid-cols-3 sm:p-5"
        >
            <div class="flex items-start gap-3">
                <ClipboardList
                    class="mt-0.5 size-5 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
                <p class="text-sm leading-6">
                    <span class="font-medium">Five services.</span> Only New
                    Business Permit is available to start online today.
                </p>
            </div>
            <div class="flex items-start gap-3">
                <ReceiptText
                    class="mt-0.5 size-5 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
                <p class="text-sm leading-6">
                    <span class="font-medium">One confirmed charge.</span> It is
                    a component of the cost, not the full assessment.
                </p>
            </div>
            <div class="flex items-start gap-3">
                <CircleHelp
                    class="mt-0.5 size-5 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
                <p class="text-sm leading-6">
                    <span class="font-medium">Other charges may apply.</span>
                    Business tax and office findings depend on your business
                    facts.
                </p>
            </div>
        </section>

        <CommonFeesSection :price-list="priceList" />

        <section
            aria-label="Business permit services"
            class="grid min-w-0 gap-4 lg:grid-cols-2"
        >
            <ServiceOfferingCard
                v-for="service in priceList.services"
                :key="service.code"
                :service="service"
                :class="
                    service.code === 'new_business_permit'
                        ? 'lg:col-span-2'
                        : ''
                "
            />
        </section>

        <aside class="rounded-xl border bg-muted/40 p-4 text-sm leading-6">
            <p class="font-semibold">Why your final assessment may differ</p>
            <p class="mt-1 max-w-4xl text-muted-foreground">
                This is a read-only service menu as of
                {{ priceList.catalog.as_of_date }}. A confirmed charge is not a
                complete assessment. Your declared business tax basis, line of
                business, and findings from concerned municipal offices can all
                change the final amount you are asked to pay.
            </p>
        </aside>
    </div>
</template>
