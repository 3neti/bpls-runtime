<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Building2,
    FileEdit,
    FileX,
    RefreshCw,
    Repeat,
} from '@lucide/vue';
import { computed } from 'vue';
import PriceDisplay from '@/components/services-and-fees/PriceDisplay.vue';
import PricingStatusBadge from '@/components/services-and-fees/PricingStatusBadge.vue';
import { Button } from '@/components/ui/button';
import type { MunicipalServiceOffering } from '@/types';

const props = defineProps<{
    service: MunicipalServiceOffering;
}>();

const icon = computed(() => {
    switch (props.service.code) {
        case 'new_business_permit':
            return Building2;
        case 'renewal':
            return RefreshCw;
        case 'amendment':
            return FileEdit;
        case 'transfer':
            return Repeat;
        case 'retirement_closure':
        default:
            return FileX;
    }
});
</script>

<template>
    <article
        class="flex min-w-0 flex-col gap-4 rounded-2xl border bg-card p-5 text-card-foreground shadow-xs sm:p-6"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex min-w-0 items-start gap-3">
                <div class="rounded-xl bg-muted p-2.5">
                    <component
                        :is="icon"
                        class="size-5 text-muted-foreground"
                        aria-hidden="true"
                    />
                </div>
                <div class="min-w-0 space-y-1.5">
                    <h2 class="text-lg font-semibold tracking-tight">
                        {{ service.name }}
                    </h2>
                    <p class="text-sm leading-6 text-muted-foreground">
                        {{ service.description }}
                    </p>
                </div>
            </div>
            <PricingStatusBadge
                :availability="service.availability"
                :label="service.availability_label"
            />
        </div>

        <PriceDisplay
            v-if="service.code === 'new_business_permit'"
            :pricing="service.pricing"
        />
        <div
            v-else
            class="flex items-start gap-3 rounded-lg bg-muted/60 p-4 text-sm leading-6"
        >
            <div>
                <p class="font-medium">Municipal confirmation still required</p>
                <p class="mt-1 text-muted-foreground">
                    {{ service.pricing.other_charges_message }}
                </p>
            </div>
        </div>

        <div class="mt-auto border-t pt-4">
            <Button
                v-if="service.can_start_online && service.start_url"
                as-child
            >
                <Link :href="service.start_url">
                    Start Application
                    <ArrowRight aria-hidden="true" />
                </Link>
            </Button>
            <p v-else class="text-sm text-muted-foreground">
                Contact BPLO staff for assistance with this service.
            </p>
        </div>
    </article>
</template>
