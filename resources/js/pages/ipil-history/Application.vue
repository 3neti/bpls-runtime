<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ExternalLink, TriangleAlert } from '@lucide/vue';
import {
    application as applicationRoute,
    business,
    document,
    index,
    owner,
} from '@/actions/App/Http/Controllers/Staff/IpilHistoricalRecordController';
import HistoricalRecordBanner from '@/components/ipil-history/HistoricalRecordBanner.vue';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
type Row = Record<string, any>;
const props = defineProps<{
    application: Row;
    classifications: Row[];
    measurements: Row[];
    schedules: Row[];
    payments: Row[];
    permits: Row[];
    clearances: Row[];
    documents: Row[];
}>();
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Historical Ipil Records', href: index() },
    {
        title:
            props.application.source_application_number ||
            `Application ${props.application.id}`,
        href: applicationRoute(props.application.id),
    },
];
</script>
<template>
    <AppLayout :breadcrumbs="breadcrumbs"
        ><Head
            :title="`Historical Application · ${application.source_application_number || application.id}`" />
        <main class="flex min-w-0 flex-1 flex-col gap-4 p-4">
            <header>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-semibold">
                        {{
                            application.source_application_number ||
                            `Source application ${application.id}`
                        }}
                    </h1>
                    <Badge variant="secondary">{{
                        application.application_year
                    }}</Badge
                    ><Badge variant="outline">{{
                        application.source_type
                    }}</Badge
                    ><Badge variant="outline">{{
                        application.source_status
                    }}</Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    <Link
                        :href="business(application.business_id)"
                        class="font-medium hover:underline"
                        >{{ application.business_name }}</Link
                    >
                    ·
                    <Link
                        :href="owner(application.owner_id)"
                        class="hover:underline"
                        >{{ application.owner_name }}</Link
                    >
                </p>
            </header>
            <HistoricalRecordBanner />
            <section
                class="grid gap-3 rounded-lg border bg-background p-4 sm:grid-cols-2 lg:grid-cols-4"
            >
                <div>
                    <p class="text-xs text-muted-foreground uppercase">
                        Source total fees
                    </p>
                    <p class="font-semibold">
                        {{
                            application.total_fees_decimal ||
                            application.total_fees_source_lexeme ||
                            'Not recorded'
                        }}
                    </p>
                    <p
                        v-if="application.total_fees_cent_exact === false"
                        class="text-xs text-amber-700"
                    >
                        Not cent-exact in source
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground uppercase">
                        Submitted
                    </p>
                    <p>{{ application.submitted_at || 'Not recorded' }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground uppercase">
                        Operational eligibility
                    </p>
                    <p>Not operational</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground uppercase">
                        Continue workflow
                    </p>
                    <p>Unavailable</p>
                </div>
            </section>
            <div class="grid gap-4 xl:grid-cols-2">
                <section class="rounded-lg border bg-background">
                    <h2 class="border-b p-4 font-semibold">
                        Classifications & measurements
                    </h2>
                    <div class="p-4">
                        <p
                            v-if="
                                !classifications.length && !measurements.length
                            "
                            class="text-sm text-muted-foreground"
                        >
                            None recorded.
                        </p>
                        <ul class="space-y-2">
                            <li
                                v-for="r in classifications"
                                :key="`c${r.id}`"
                                class="text-sm"
                            >
                                <span class="font-medium">{{
                                    r.source_literal ||
                                    'Blank source classification'
                                }}</span
                                ><span
                                    v-if="r.normalized_candidate"
                                    class="text-muted-foreground"
                                >
                                    · candidate {{ r.normalized_candidate }} ({{
                                        r.confidence
                                    }})</span
                                >
                            </li>
                            <li
                                v-for="r in measurements"
                                :key="`m${r.id}`"
                                class="text-sm"
                            >
                                {{ r.source_dataset }} ·
                                {{
                                    r.source_variable ||
                                    'variable not recorded'
                                }}:
                                {{ r.source_quantity_lexeme || 'not recorded' }}
                            </li>
                        </ul>
                    </div>
                </section>
                <section class="rounded-lg border bg-background">
                    <h2 class="border-b p-4 font-semibold">
                        Schedules ({{ schedules.length }})
                    </h2>
                    <ul class="divide-y">
                        <li
                            v-for="r in schedules"
                            :key="r.id"
                            class="p-4 text-sm"
                        >
                            <div class="flex justify-between gap-2">
                                <span class="font-medium">{{
                                    r.source_status
                                }}</span
                                ><span>{{ r.total_amount_decimal }}</span>
                            </div>
                            <p class="text-muted-foreground">
                                Paid {{ r.paid_amount_decimal }} · source
                                {{ r.total_amount_source_lexeme }}
                            </p>
                            <p
                                v-if="
                                    r.missing_application ||
                                    r.total_amount_cent_exact === false
                                "
                                class="mt-1 text-amber-700"
                            >
                                <TriangleAlert
                                    class="mr-1 inline size-4"
                                />Source exception retained
                            </p>
                        </li>
                    </ul>
                </section>
            </div>
            <section class="rounded-lg border bg-background">
                <h2 class="border-b p-4 font-semibold">
                    Historical payments & OR claims ({{ payments.length }})
                </h2>
                <p
                    v-if="!payments.length"
                    class="p-4 text-sm text-muted-foreground"
                >
                    No linked payment evidence.
                </p>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="p-3">Paid</th>
                                <th class="p-3">Status</th>
                                <th class="p-3">Amount</th>
                                <th class="p-3">Transaction</th>
                                <th class="p-3">OR claim</th>
                                <th class="p-3">Evidence</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="r in payments" :key="r.id">
                                <td class="p-3">
                                    {{ r.paid_at || 'Not recorded' }}
                                </td>
                                <td class="p-3">{{ r.source_status }}</td>
                                <td class="p-3">{{ r.amount_decimal }}</td>
                                <td class="p-3">
                                    {{ r.transaction_number || '—' }}
                                </td>
                                <td class="p-3">
                                    {{ r.receipt_number || '—' }}
                                </td>
                                <td class="p-3">
                                    <Badge
                                        v-if="r.is_duplicate_claim"
                                        variant="destructive"
                                        >Duplicate OR claim</Badge
                                    ><span
                                        v-else-if="
                                            r.missing_schedule ||
                                            r.missing_application
                                        "
                                        class="text-amber-700"
                                        >Broken source link</span
                                    ><span v-else>Recorded claim</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <div class="grid gap-4 xl:grid-cols-2">
                <section class="rounded-lg border bg-background">
                    <h2 class="border-b p-4 font-semibold">
                        Permit claims ({{ permits.length }})
                    </h2>
                    <ul class="divide-y">
                        <li
                            v-for="r in permits"
                            :key="r.id"
                            class="p-4 text-sm"
                        >
                            <span class="font-medium">{{
                                r.permit_number || 'Number not recorded'
                            }}</span>
                            · {{ r.source_status || 'status not recorded' }}
                            <p
                                v-if="
                                    r.missing_application ||
                                    r.broken_business_edge ||
                                    r.broken_owner_edge
                                "
                                class="text-amber-700"
                            >
                                Broken source relationship retained
                            </p>
                        </li>
                    </ul>
                </section>
                <section class="rounded-lg border bg-background">
                    <h2 class="border-b p-4 font-semibold">
                        Clearance claims ({{ clearances.length }})
                    </h2>
                    <ul class="max-h-80 divide-y overflow-auto">
                        <li
                            v-for="r in clearances"
                            :key="r.id"
                            class="p-4 text-sm"
                        >
                            <span class="font-medium">{{
                                r.clearance_name || 'Type not recorded'
                            }}</span>
                            ·
                            {{
                                r.source_completed
                                    ? 'source says completed'
                                    : 'not completed in source'
                            }}
                            <p
                                v-if="r.broken_type_reference"
                                class="text-amber-700"
                            >
                                Broken clearance-type reference
                            </p>
                        </li>
                    </ul>
                </section>
            </div>
            <section class="rounded-lg border bg-background">
                <h2 class="border-b p-4 font-semibold">
                    Documents ({{ documents.length }})
                </h2>
                <p
                    v-if="!documents.length"
                    class="p-4 text-sm text-muted-foreground"
                >
                    No deterministically associated document bytes. Missing
                    source evidence stays missing.
                </p>
                <ul v-else class="divide-y">
                    <li
                        v-for="r in documents"
                        :key="r.id"
                        class="flex flex-wrap justify-between gap-2 p-4"
                    >
                        <div>
                            <p class="font-medium">
                                {{ r.document_type || 'Uploaded evidence' }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ r.original_filename }}
                            </p>
                        </div>
                        <a
                            :href="
                                document.url({
                                    business: application.business_id,
                                    document: r.id,
                                })
                            "
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-2 text-sm underline"
                            >View private copy <ExternalLink class="size-4"
                        /></a>
                    </li>
                </ul>
            </section></main
    ></AppLayout>
</template>
