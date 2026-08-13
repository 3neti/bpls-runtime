<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Calculator, Eye } from '@lucide/vue';
import { Button, buttonVariants } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    index as assessmentIndex,
    show,
    store,
} from '@/actions/App/Http/Controllers/Staff/PermitApplicationAssessmentController';
import type { BreadcrumbItem } from '@/types';

type LatestAssessment = {
    id: number;
    sequence: number;
    status: string;
    total_amount_cents: number;
    assessed_at: string | null;
};

type PermitApplicationRow = {
    id: number;
    application_number: string | null;
    type: string;
    status: string;
    application_year: number;
    business_name: string;
    owner_name: string;
    line_count: number;
    latest_assessment: LatestAssessment | null;
};

defineProps<{
    permitApplications: {
        data: PermitApplicationRow[];
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Permit Assessments',
        href: assessmentIndex(),
    },
];

function money(amountCents: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amountCents / 100);
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Permit Assessments" />

        <main class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <section class="flex flex-col gap-2">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 class="text-xl font-semibold text-foreground">
                            Permit Assessments
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            Review permit applications and compute assessment
                            snapshots.
                        </p>
                    </div>
                </div>
            </section>

            <section
                class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background dark:border-sidebar-border"
            >
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[860px] text-sm">
                        <thead
                            class="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3 font-medium">
                                    Application
                                </th>
                                <th class="px-4 py-3 font-medium">Business</th>
                                <th class="px-4 py-3 font-medium">Type</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Lines
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Latest assessment
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="permitApplication in permitApplications.data"
                                :key="permitApplication.id"
                                class="border-b last:border-b-0"
                            >
                                <td class="px-4 py-3 align-top">
                                    <div class="font-medium">
                                        {{
                                            permitApplication.application_number ??
                                            `Application #${permitApplication.id}`
                                        }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ permitApplication.application_year }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="font-medium">
                                        {{ permitApplication.business_name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ permitApplication.owner_name }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top capitalize">
                                    {{ permitApplication.type }}
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <Badge
                                        variant="secondary"
                                        class="capitalize"
                                    >
                                        {{
                                            permitApplication.status.replace(
                                                '_',
                                                ' ',
                                            )
                                        }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-right align-top">
                                    {{ permitApplication.line_count }}
                                </td>
                                <td class="px-4 py-3 text-right align-top">
                                    <div
                                        v-if="
                                            permitApplication.latest_assessment
                                        "
                                    >
                                        <div class="font-medium">
                                            {{
                                                money(
                                                    permitApplication
                                                        .latest_assessment
                                                        .total_amount_cents,
                                                )
                                            }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            Sequence
                                            {{
                                                permitApplication
                                                    .latest_assessment.sequence
                                            }}
                                        </div>
                                    </div>
                                    <span v-else class="text-muted-foreground"
                                        >Not assessed</span
                                    >
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            v-if="
                                                permitApplication.latest_assessment
                                            "
                                            as-child
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link
                                                :href="
                                                    show(
                                                        permitApplication
                                                            .latest_assessment
                                                            .id,
                                                    )
                                                "
                                            >
                                                <Eye />
                                                View
                                            </Link>
                                        </Button>
                                        <Link
                                            :href="store(permitApplication.id)"
                                            method="post"
                                            as="button"
                                            :class="
                                                buttonVariants({ size: 'sm' })
                                            "
                                        >
                                            <Calculator />
                                            Assess
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="permitApplications.data.length === 0">
                                <td
                                    colspan="7"
                                    class="px-4 py-10 text-center text-muted-foreground"
                                >
                                    No permit applications are available for
                                    assessment.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
