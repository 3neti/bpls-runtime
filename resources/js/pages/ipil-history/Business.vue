<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ExternalLink } from '@lucide/vue';
import {
    application,
    business as businessRoute,
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
    business: Row;
    applications: Row[];
    documents: Row[];
}>();
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Historical Ipil Records', href: index() },
    { title: props.business.name, href: businessRoute(props.business.id) },
];
</script>
<template>
    <AppLayout :breadcrumbs="breadcrumbs"
        ><Head :title="`Historical Business · ${business.name}`" />
        <main class="flex min-w-0 flex-1 flex-col gap-4 p-4">
            <header>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-semibold">{{ business.name }}</h1>
                    <Badge v-if="business.collision_candidate" variant="outline"
                        >Possible duplicate · not merged</Badge
                    >
                </div>
                <p class="text-sm text-muted-foreground">
                    Business history exactly as rescued from Ipil.
                </p>
            </header>
            <HistoricalRecordBanner />
            <section
                class="grid gap-3 rounded-lg border bg-background p-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <div>
                    <p class="text-xs text-muted-foreground uppercase">Owner</p>
                    <Link
                        :href="owner(business.owner_id)"
                        class="font-medium hover:underline"
                        >{{ business.owner_name }}</Link
                    >
                </div>
                <div>
                    <p class="text-xs text-muted-foreground uppercase">
                        Registration
                    </p>
                    <p>{{ business.registration_number || 'Not recorded' }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground uppercase">
                        Source location
                    </p>
                    <p>{{ business.address || 'Not recorded' }}</p>
                    <p>
                        {{
                            business.barangay_literal || 'Barangay not recorded'
                        }}
                    </p>
                </div>
            </section>
            <section class="rounded-lg border bg-background">
                <h2 class="border-b p-4 font-semibold">
                    Business History ({{ applications.length }} applications)
                </h2>
                <ul class="divide-y">
                    <li
                        v-for="row in applications"
                        :key="row.id"
                        class="grid gap-2 p-4 sm:grid-cols-[1fr_auto]"
                    >
                        <div>
                            <Link
                                :href="application(row.id)"
                                class="font-semibold hover:underline"
                                >{{
                                    row.source_application_number ||
                                    `Source application ${row.id}`
                                }}</Link
                            >
                            <p class="text-sm text-muted-foreground">
                                {{ row.source_type }} · source status
                                {{ row.source_status }}
                            </p>
                        </div>
                        <div class="text-sm sm:text-right">
                            <p>{{ row.application_year }}</p>
                            <p class="text-muted-foreground">
                                Recorded total
                                {{
                                    row.total_fees_decimal ||
                                    row.total_fees_source_lexeme ||
                                    'not recorded'
                                }}
                            </p>
                        </div>
                    </li>
                </ul>
            </section>
            <section class="rounded-lg border bg-background">
                <h2 class="border-b p-4 font-semibold">
                    Uploaded evidence ({{ documents.length }})
                </h2>
                <p
                    v-if="documents.length === 0"
                    class="p-4 text-sm text-muted-foreground"
                >
                    No deterministically associated uploaded evidence is
                    available for this business. Missing evidence remains
                    missing.
                </p>
                <ul v-else class="divide-y">
                    <li
                        v-for="row in documents"
                        :key="row.id"
                        class="flex flex-wrap items-center justify-between gap-2 p-4"
                    >
                        <div>
                            <p class="font-medium">
                                {{ row.document_type || 'Uploaded evidence' }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ row.original_filename }} ·
                                {{ row.source_size_bytes }} bytes
                            </p>
                        </div>
                        <a
                            :href="
                                document.url({
                                    business: business.id,
                                    document: row.id,
                                })
                            "
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-2 text-sm font-medium underline"
                            >View private copy <ExternalLink class="size-4"
                        /></a>
                    </li>
                </ul>
            </section></main
    ></AppLayout>
</template>
