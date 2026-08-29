<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Banknote,
    Building2,
    ClipboardList,
    ExternalLink,
    Landmark,
    ListTree,
    Scale,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { index as feeRuleIndex } from '@/actions/App/Http/Controllers/Staff/FeeRuleController';
import { index as serviceCatalogIndex } from '@/actions/App/Http/Controllers/Staff/MunicipalServiceCatalogController';
import PageHeader from '@/components/PageHeader.vue';
import FeeMenuLens from '@/components/services-and-fees/FeeMenuLens.vue';
import LineOfBusinessLens from '@/components/services-and-fees/LineOfBusinessLens.vue';
import OfficeLens from '@/components/services-and-fees/OfficeLens.vue';
import ServiceLens from '@/components/services-and-fees/ServiceLens.vue';
import { Button } from '@/components/ui/button';
import { usePriceBookAudience } from '@/composables/usePriceBookAudience';
import type { PriceBookLens } from '@/composables/usePriceBookAudience';
import AppLayout from '@/layouts/AppLayout.vue';
import { ruleStatus } from '@/lib/pricing-status';
import type { BreadcrumbItem, MunicipalPriceList } from '@/types';

const props = defineProps<{
    priceList: MunicipalPriceList;
}>();

const page = usePage();
const audience = usePriceBookAudience();

const lensMeta: Record<PriceBookLens, { label: string; icon: typeof ClipboardList }> = {
    service: { label: 'By Service', icon: ClipboardList },
    fee: { label: 'Fee Menu', icon: Banknote },
    lineOfBusiness: { label: 'By Line of Business', icon: ListTree },
    office: { label: 'By Office', icon: Building2 },
};

const activeLens = ref<PriceBookLens>(audience.value.defaultLens);

watch(
    () => audience.value.key,
    () => {
        activeLens.value = audience.value.defaultLens;
    },
);

const audienceIcon = computed(() => {
    switch (audience.value.key) {
        case 'treasury':
            return Landmark;
        case 'concerned_office':
            return Building2;
        case 'cashier':
            return Banknote;
        case 'management':
            return Scale;
        default:
            return ClipboardList;
    }
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Services & Fees',
        href: serviceCatalogIndex(),
    },
];

const recordedAwaitingConfirmationCount = computed(() =>
    props.priceList.services.reduce((total, service) => {
        const rules = service.internal?.rules ?? [];
        const schedules = service.internal?.line_of_business_pricing ?? [];

        return (
            total +
            [...rules, ...schedules].filter(
                (rule) => ruleStatus(rule).key === 'recorded_confirmation_required',
            ).length
        );
    }, 0),
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Services & Fees" />

        <main class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <PageHeader
                    eyebrow="Municipal pricing control"
                    title="Services & Fees"
                    description="What BPLS currently knows about our prices, rates, and schedules — and what still needs municipal confirmation."
                />
                <Button
                    v-if="page.props.auth.can_view_fee_rules"
                    variant="outline"
                    as-child
                >
                    <Link :href="feeRuleIndex()">
                        Open Taxes & Fees
                        <ExternalLink aria-hidden="true" />
                    </Link>
                </Button>
            </div>

            <aside
                class="flex items-start gap-3 rounded-xl border bg-muted/40 p-4"
                :aria-label="audience.title"
            >
                <component
                    :is="audienceIcon"
                    class="mt-0.5 size-5 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
                <div class="space-y-1">
                    <p class="font-semibold">{{ audience.title }}</p>
                    <p class="text-sm leading-6 text-muted-foreground">
                        {{ audience.message }}
                    </p>
                </div>
            </aside>

            <section
                aria-label="Price list summary"
                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
            >
                <div class="rounded-xl border bg-card p-4">
                    <p class="text-sm text-muted-foreground">BPLS services</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">
                        {{ priceList.catalog.service_count }}
                    </p>
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <p class="text-sm text-muted-foreground">In force</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">
                        {{ priceList.catalog.confirmed_exact_charge_count }}
                    </p>
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <p class="text-sm text-muted-foreground">
                        Recorded, confirmation required
                    </p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">
                        {{ recordedAwaitingConfirmationCount }}
                    </p>
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <p class="text-sm text-muted-foreground">Effective view</p>
                    <p class="mt-1 text-base font-semibold">
                        {{ priceList.catalog.as_of_date }}
                    </p>
                </div>
            </section>

            <nav
                aria-label="Price Book lens"
                class="flex flex-wrap gap-2 border-b pb-2"
            >
                <button
                    v-for="lens in audience.lenses"
                    :key="lens"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    :class="
                        activeLens === lens
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                    "
                    :aria-pressed="activeLens === lens"
                    @click="activeLens = lens"
                >
                    <component :is="lensMeta[lens].icon" class="size-4" aria-hidden="true" />
                    {{ lensMeta[lens].label }}
                </button>
            </nav>

            <FeeMenuLens
                v-if="activeLens === 'fee'"
                :price-list="priceList"
                :concise="audience.concise"
            />
            <LineOfBusinessLens
                v-else-if="activeLens === 'lineOfBusiness'"
                :price-list="priceList"
            />
            <OfficeLens
                v-else-if="activeLens === 'office'"
                :emphasized-office-code="audience.emphasizedOfficeCode"
            />
            <ServiceLens
                v-else
                :price-list="priceList"
                :concise="audience.concise"
            />

            <aside
                class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
            >
                <p class="font-semibold">Read-only commissioning boundary</p>
                <p class="mt-1">
                    This screen cannot edit, approve, publish, supersede, or
                    delete fee rules. Future maintenance must preserve actor,
                    before/after values, legal basis, effective date, approval,
                    audit history, and future-only effect without rewriting past
                    assessments.
                </p>
            </aside>
        </main>
    </AppLayout>
</template>
