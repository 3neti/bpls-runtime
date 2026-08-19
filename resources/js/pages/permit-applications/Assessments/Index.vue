<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, Calculator, Eye } from '@lucide/vue';
import {
    index as assessmentIndex,
    show,
    store,
} from '@/actions/App/Http/Controllers/Staff/PermitApplicationAssessmentController';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type LatestAssessment = {
    id: number;
    sequence: number;
    status: string;
    total_amount_cents: number;
    assessed_at: string | null;
    decision: {
        action: 'approved' | 'returned_for_correction';
        decided_at: string;
    } | null;
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
    assessment_policy_boundary: {
        status: string;
        reason: string;
        blocked_at: string | null;
    } | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

defineProps<{
    permitApplications: {
        data: PermitApplicationRow[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    can: {
        assess_permit_applications: boolean;
    };
    errors?: {
        assessment_policy?: string;
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

function statusLabel(value: string): string {
    return value.replaceAll('_', ' ');
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Permit Assessments" />

        <main class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
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
                v-if="errors?.assessment_policy"
                class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
            >
                <div class="flex items-start gap-3">
                    <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                    <div>
                        <p class="font-medium">Assessment policy boundary</p>
                        <p class="mt-1">
                            {{ errors.assessment_policy }}
                        </p>
                    </div>
                </div>
            </section>

            <section
                class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background dark:border-sidebar-border"
                aria-label="Assessment queue records"
            >
                <div
                    v-if="permitApplications.data.length === 0"
                    class="px-4 py-10 text-center text-sm text-muted-foreground"
                >
                    No permit applications are available for assessment.
                </div>

                <ul
                    v-else
                    class="divide-y divide-border md:hidden"
                    aria-label="Assessment queue"
                >
                    <li
                        v-for="permitApplication in permitApplications.data"
                        :key="permitApplication.id"
                        class="grid min-w-0 gap-4 p-4"
                    >
                        <div
                            class="flex min-w-0 items-start justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <p class="font-medium break-words">
                                    {{
                                        permitApplication.application_number ??
                                        `Application #${permitApplication.id}`
                                    }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ permitApplication.application_year }} ·
                                    <span class="capitalize">{{
                                        permitApplication.type
                                    }}</span>
                                </p>
                            </div>
                            <Badge
                                variant="secondary"
                                class="shrink-0 capitalize"
                            >
                                {{ statusLabel(permitApplication.status) }}
                            </Badge>
                        </div>

                        <div
                            v-if="permitApplication.assessment_policy_boundary"
                            class="min-w-0 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
                        >
                            <p class="font-medium">
                                Assessment policy boundary
                            </p>
                            <p class="mt-1 break-words">
                                {{
                                    permitApplication.assessment_policy_boundary
                                        .reason
                                }}
                            </p>
                        </div>

                        <dl
                            class="grid min-w-0 grid-cols-2 gap-x-4 gap-y-3 text-sm"
                        >
                            <div class="col-span-2 min-w-0">
                                <dt class="text-xs text-muted-foreground">
                                    Business
                                </dt>
                                <dd class="font-medium break-words">
                                    {{ permitApplication.business_name }}
                                </dd>
                                <dd
                                    class="text-xs break-words text-muted-foreground"
                                >
                                    {{ permitApplication.owner_name }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Lines
                                </dt>
                                <dd>{{ permitApplication.line_count }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Latest assessment
                                </dt>
                                <dd
                                    v-if="permitApplication.latest_assessment"
                                    class="font-medium"
                                >
                                    {{
                                        money(
                                            permitApplication.latest_assessment
                                                .total_amount_cents,
                                        )
                                    }}
                                </dd>
                                <dd
                                    v-if="permitApplication.latest_assessment"
                                    class="text-xs text-muted-foreground"
                                >
                                    Sequence
                                    {{
                                        permitApplication.latest_assessment
                                            .sequence
                                    }}
                                </dd>
                                <dd
                                    v-if="permitApplication.latest_assessment"
                                    class="text-xs font-medium capitalize"
                                >
                                    {{
                                        permitApplication.latest_assessment
                                            .decision
                                            ? statusLabel(
                                                  permitApplication
                                                      .latest_assessment
                                                      .decision.action,
                                              )
                                            : 'Awaiting Treasurer approval'
                                    }}
                                </dd>
                                <dd v-else class="text-muted-foreground">
                                    Not assessed
                                </dd>
                            </div>
                        </dl>

                        <div class="flex flex-wrap gap-2 border-t pt-3">
                            <Button
                                v-if="permitApplication.latest_assessment"
                                as-child
                                variant="outline"
                                size="sm"
                            >
                                <Link
                                    :href="
                                        show(
                                            permitApplication.latest_assessment
                                                .id,
                                        )
                                    "
                                >
                                    <Eye />
                                    View
                                </Link>
                            </Button>
                            <Link
                                v-if="can.assess_permit_applications"
                                :href="store(permitApplication.id)"
                                method="post"
                                as="button"
                                :class="buttonVariants({ size: 'sm' })"
                            >
                                <Calculator />
                                Assess
                            </Link>
                        </div>
                    </li>
                </ul>

                <div
                    v-if="permitApplications.data.length > 0"
                    class="hidden overflow-x-auto md:block"
                >
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
                                    <div
                                        v-if="
                                            permitApplication.assessment_policy_boundary
                                        "
                                        class="mt-2 max-w-[260px] rounded-md border border-amber-300 bg-amber-50 px-2 py-1 text-xs text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
                                    >
                                        <div class="font-medium">
                                            Assessment policy boundary
                                        </div>
                                        <div class="mt-1 break-words">
                                            {{
                                                permitApplication
                                                    .assessment_policy_boundary
                                                    .reason
                                            }}
                                        </div>
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
                                            statusLabel(
                                                permitApplication.status,
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
                                        <div
                                            class="text-xs font-medium capitalize"
                                        >
                                            {{
                                                permitApplication
                                                    .latest_assessment.decision
                                                    ? statusLabel(
                                                          permitApplication
                                                              .latest_assessment
                                                              .decision.action,
                                                      )
                                                    : 'Awaiting Treasurer approval'
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
                                            v-if="
                                                can.assess_permit_applications
                                            "
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
                        </tbody>
                    </table>
                </div>
            </section>

            <nav
                v-if="permitApplications.links.length > 3"
                class="flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between"
                aria-label="Assessment queue pagination"
            >
                <div class="text-muted-foreground">
                    Showing {{ permitApplications.from ?? 0 }} to
                    {{ permitApplications.to ?? 0 }} of
                    {{ permitApplications.total }}
                </div>
                <div class="flex flex-wrap gap-1">
                    <template
                        v-for="link in permitApplications.links"
                        :key="link.label"
                    >
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            :aria-current="link.active ? 'page' : undefined"
                            :class="[
                                'rounded-md border px-3 py-1.5 outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                link.active
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-sidebar-border/70 text-foreground',
                            ]"
                        >
                            <span v-html="link.label" />
                        </Link>
                        <span
                            v-else
                            class="rounded-md border border-sidebar-border/70 px-3 py-1.5 text-muted-foreground opacity-50"
                            aria-disabled="true"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </nav>
        </main>
    </AppLayout>
</template>
