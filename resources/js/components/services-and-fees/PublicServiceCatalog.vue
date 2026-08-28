<script setup lang="ts">
import ServiceOfferingCard from '@/components/services-and-fees/ServiceOfferingCard.vue';
import type { MunicipalPriceList } from '@/types';

defineProps<{
    priceList: MunicipalPriceList;
}>();
</script>

<template>
    <div class="space-y-8">
        <header class="space-y-3">
            <p class="text-sm font-medium text-muted-foreground">
                Municipality of Ipil · Business permits
            </p>
            <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">
                Services & Fees
            </h1>
            <p class="max-w-3xl text-base leading-7 text-muted-foreground">
                See which business-permit services are available, the charges
                currently confirmed by the Municipality, and why a final
                assessment may include other applicable charges.
            </p>
        </header>

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
            <p class="font-semibold">About the prices shown</p>
            <p class="mt-1 max-w-4xl text-muted-foreground">
                This is a read-only service menu as of
                {{ priceList.catalog.as_of_date }}. A confirmed charge is not a
                complete assessment. Business details, applicable municipal
                rules, and concerned-office findings may change the final
                amount.
            </p>
        </aside>
    </div>
</template>
