<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    application,
    business,
    index,
    owner as ownerRoute,
} from '@/actions/App/Http/Controllers/Staff/IpilHistoricalRecordController';
import HistoricalRecordBanner from '@/components/ipil-history/HistoricalRecordBanner.vue';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
type Row = Record<string, any>;
const props = defineProps<{
    owner: Row;
    businesses: Row[];
    applications: Row[];
}>();
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Historical Ipil Records', href: index() },
    { title: props.owner.name, href: ownerRoute(props.owner.id) },
];
</script>
<template>
    <AppLayout :breadcrumbs="breadcrumbs"
        ><Head :title="`Historical Owner · ${owner.name}`" />
        <main class="flex min-w-0 flex-1 flex-col gap-4 p-4">
            <header>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-semibold">{{ owner.name }}</h1>
                    <Badge v-if="owner.collision_candidate" variant="outline"
                        >Possible duplicate · not merged</Badge
                    >
                </div>
                <p class="text-sm text-muted-foreground">
                    Historical owner evidence; this person is not a BPLS User.
                </p>
            </header>
            <HistoricalRecordBanner />
            <section
                class="grid gap-3 rounded-lg border bg-background p-4 sm:grid-cols-2"
            >
                <div>
                    <p class="text-xs text-muted-foreground uppercase">
                        Contact
                    </p>
                    <p>{{ owner.email || 'Not recorded' }}</p>
                    <p>{{ owner.phone || 'Not recorded' }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground uppercase">
                        Source address
                    </p>
                    <p>{{ owner.address || 'Not recorded' }}</p>
                    <p>
                        {{ owner.barangay_literal || 'Barangay not recorded' }}
                    </p>
                </div>
            </section>
            <section class="rounded-lg border bg-background">
                <h2 class="border-b p-4 font-semibold">
                    Businesses ({{ businesses.length }})
                </h2>
                <ul class="divide-y">
                    <li v-for="row in businesses" :key="row.id" class="p-4">
                        <Link
                            :href="business(row.id)"
                            class="font-semibold hover:underline"
                            >{{ row.name }}</Link
                        >
                        <p class="text-sm text-muted-foreground">
                            {{
                                row.registration_number ||
                                'Registration not recorded'
                            }}
                            · {{ row.application_count }} applications
                        </p>
                    </li>
                </ul>
            </section>
            <section class="rounded-lg border bg-background">
                <h2 class="border-b p-4 font-semibold">
                    Application history ({{ applications.length }})
                </h2>
                <ul class="divide-y">
                    <li
                        v-for="row in applications"
                        :key="row.id"
                        class="flex flex-wrap justify-between gap-2 p-4"
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
                                {{ row.business_name }}
                            </p>
                        </div>
                        <p class="text-sm">
                            {{ row.application_year }} · {{ row.source_type }} ·
                            {{ row.source_status }}
                        </p>
                    </li>
                </ul>
            </section>
        </main></AppLayout
    >
</template>
