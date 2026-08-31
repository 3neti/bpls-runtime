<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import PublicServiceCatalog from '@/components/services-and-fees/PublicServiceCatalog.vue';
import { index } from '@/routes/citizen/services-and-fees';
import type { BreadcrumbItem, MunicipalPriceList } from '@/types';

defineProps<{
    priceList: MunicipalPriceList;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Services & Fees',
        href: index(),
    },
];

setLayoutProps({ breadcrumbs });
</script>

<template>
    <div class="contents">
        <Head title="Services & Fees" />

        <main class="flex min-w-0 flex-1 flex-col px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto w-full max-w-6xl">
                <PublicServiceCatalog :price-list="priceList" />
            </div>
        </main>
    </div>
</template>
