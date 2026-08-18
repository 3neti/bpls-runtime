<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, CircleCheck, ShieldAlert } from '@lucide/vue';
import { index as reportCatalogIndex } from '@/actions/App/Http/Controllers/Staff/ReportCatalogController';
import { reportFamilyDetails } from '@/lib/reportCatalog';
import type { ReportAvailability, ReportFamily } from '@/lib/reportCatalog';

const props = defineProps<{
    family: ReportFamily;
    availability: ReportAvailability;
}>();

const familyDetails = reportFamilyDetails[props.family];
</script>

<template>
    <section
        class="flex flex-col gap-3 rounded-lg border border-sidebar-border/70 bg-muted/30 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-sidebar-border"
        :data-report-family="family"
        :data-report-availability="availability"
    >
        <div class="flex min-w-0 items-start gap-3">
            <CircleCheck
                v-if="availability === 'working'"
                class="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400"
                aria-hidden="true"
            />
            <ShieldAlert
                v-else
                class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400"
                aria-hidden="true"
            />
            <div class="min-w-0">
                <p class="text-sm font-semibold text-foreground">
                    {{ familyDetails.title }}
                </p>
                <p class="text-xs leading-5 text-muted-foreground">
                    {{ familyDetails.description }}
                </p>
            </div>
        </div>

        <Link
            :href="reportCatalogIndex()"
            class="inline-flex min-h-9 shrink-0 items-center gap-2 rounded-md px-3 text-sm font-medium text-foreground outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring"
        >
            All reports
            <ArrowRight class="size-4" aria-hidden="true" />
        </Link>
    </section>
</template>
