<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Archive,
    Building2,
    FileText,
    Search,
    UserRound,
    X,
} from '@lucide/vue';
import { ref } from 'vue';
import {
    application,
    business,
    index,
    owner,
} from '@/actions/App/Http/Controllers/Staff/IpilHistoricalRecordController';
import HistoricalRecordBanner from '@/components/ipil-history/HistoricalRecordBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Row = Record<string, any>;
type LinkRow = { url: string | null; label: string; active: boolean };
const props = defineProps<{
    businesses: {
        data: Row[];
        links: LinkRow[];
        from: number | null;
        to: number | null;
        total: number;
    };
    matches: Record<string, Row[]>;
    filters: Record<string, string | number | null>;
    options: Record<string, (string | number)[]>;
    anchors: Record<string, string | number>;
}>();
const form = ref({
    q: String(props.filters.q ?? ''),
    year: String(props.filters.year ?? ''),
    type: String(props.filters.type ?? ''),
    status: String(props.filters.status ?? ''),
    barangay: String(props.filters.barangay ?? ''),
    classification: String(props.filters.classification ?? ''),
    sort: String(props.filters.sort ?? 'name'),
    direction: String(props.filters.direction ?? 'asc'),
});
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Historical Ipil Records', href: index() },
];
const matchCount = () =>
    Object.values(props.matches).reduce((sum, rows) => sum + rows.length, 0);
function apply(): void {
    router.get(
        index.url({
            query: Object.fromEntries(
                Object.entries(form.value).map(([k, v]) => [k, v || undefined]),
            ),
        }),
        {},
        { preserveState: true, replace: true },
    );
}
function clear(): void {
    router.get(index.url(), {}, { preserveState: true, replace: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Historical Ipil Records" />
        <main class="flex min-w-0 flex-1 flex-col gap-4 p-4">
            <header>
                <h1 class="text-xl font-semibold">Historical Ipil Records</h1>
                <p class="text-sm text-muted-foreground">
                    Find rescued businesses, owners, applications, permits, and
                    official-receipt claims.
                </p>
            </header>
            <HistoricalRecordBanner />
            <section
                class="grid grid-cols-2 gap-2 lg:grid-cols-4"
                aria-label="Gate 6 evidence anchors"
            >
                <div
                    v-for="(value, label) in anchors"
                    :key="label"
                    class="rounded-lg border bg-background p-3"
                >
                    <p class="text-xs text-muted-foreground">
                        {{ String(label).replaceAll('_', ' ') }}
                    </p>
                    <p class="font-semibold tabular-nums">{{ value }}</p>
                </div>
            </section>
            <form
                class="grid gap-3 rounded-lg border bg-background p-4 lg:grid-cols-4"
                @submit.prevent="apply"
            >
                <div class="grid gap-1 lg:col-span-2">
                    <label
                        for="history_q"
                        class="text-xs font-medium text-muted-foreground uppercase"
                        >Unified search</label
                    ><Input
                        id="history_q"
                        v-model="form.q"
                        placeholder="Business, owner, application, permit, or exact OR"
                    />
                </div>
                <label
                    class="grid gap-1 text-xs font-medium text-muted-foreground uppercase"
                    >Year<select
                        v-model="form.year"
                        class="h-9 rounded-md border bg-transparent px-3 text-sm normal-case"
                    >
                        <option value="">All years</option>
                        <option v-for="v in options.years" :key="v" :value="v">
                            {{ v }}
                        </option>
                    </select></label
                >
                <label
                    class="grid gap-1 text-xs font-medium text-muted-foreground uppercase"
                    >Transaction type<select
                        v-model="form.type"
                        class="h-9 rounded-md border bg-transparent px-3 text-sm normal-case"
                    >
                        <option value="">All types</option>
                        <option v-for="v in options.types" :key="v" :value="v">
                            {{ v }}
                        </option>
                    </select></label
                >
                <label
                    class="grid gap-1 text-xs font-medium text-muted-foreground uppercase"
                    >Legacy status<select
                        v-model="form.status"
                        class="h-9 rounded-md border bg-transparent px-3 text-sm normal-case"
                    >
                        <option value="">All statuses</option>
                        <option
                            v-for="v in options.statuses"
                            :key="v"
                            :value="v"
                        >
                            {{ v }}
                        </option>
                    </select></label
                >
                <label
                    class="grid gap-1 text-xs font-medium text-muted-foreground uppercase"
                    >Barangay<select
                        v-model="form.barangay"
                        class="h-9 rounded-md border bg-transparent px-3 text-sm normal-case"
                    >
                        <option value="">All source barangays</option>
                        <option
                            v-for="v in options.barangays"
                            :key="v"
                            :value="v"
                        >
                            {{ v }}
                        </option>
                    </select></label
                >
                <label
                    class="grid gap-1 text-xs font-medium text-muted-foreground uppercase"
                    >Historical classification<Input
                        v-model="form.classification"
                        placeholder="Source classification"
                        class="normal-case"
                /></label>
                <div class="flex items-end gap-2">
                    <Button type="submit"><Search /> Search</Button
                    ><Button type="button" variant="outline" @click="clear"
                        ><X /> Clear</Button
                    >
                </div>
            </form>

            <section v-if="form.q" class="rounded-lg border bg-background p-4">
                <h2 class="font-semibold">
                    Unified matches
                    <Badge variant="secondary">{{ matchCount() }}</Badge>
                </h2>
                <p
                    v-if="matchCount() === 0"
                    class="mt-3 text-sm text-muted-foreground"
                >
                    No exact or partial historical evidence matched.
                </p>
                <div class="mt-3 grid gap-4 lg:grid-cols-2">
                    <div v-if="matches.businesses.length">
                        <h3
                            class="text-xs font-semibold text-muted-foreground uppercase"
                        >
                            Businesses
                        </h3>
                        <Link
                            v-for="r in matches.businesses"
                            :key="r.id"
                            :href="business(r.id)"
                            class="mt-2 flex items-center gap-2 text-sm font-medium hover:underline"
                            ><Building2 class="size-4" />{{ r.name }}</Link
                        >
                    </div>
                    <div v-if="matches.owners.length">
                        <h3
                            class="text-xs font-semibold text-muted-foreground uppercase"
                        >
                            Owners
                        </h3>
                        <Link
                            v-for="r in matches.owners"
                            :key="r.id"
                            :href="owner(r.id)"
                            class="mt-2 flex items-center gap-2 text-sm font-medium hover:underline"
                            ><UserRound class="size-4" />{{ r.name }}</Link
                        >
                    </div>
                    <div v-if="matches.applications.length">
                        <h3
                            class="text-xs font-semibold text-muted-foreground uppercase"
                        >
                            Applications
                        </h3>
                        <Link
                            v-for="r in matches.applications"
                            :key="r.id"
                            :href="application(r.id)"
                            class="mt-2 flex items-center gap-2 text-sm font-medium hover:underline"
                            ><FileText class="size-4" />{{
                                r.source_application_number ||
                                `Source application ${r.id}`
                            }}
                            · {{ r.business_name }}</Link
                        >
                    </div>
                    <div
                        v-if="matches.permits.length || matches.receipts.length"
                    >
                        <h3
                            class="text-xs font-semibold text-muted-foreground uppercase"
                        >
                            Permit / OR claims
                        </h3>
                        <template
                            v-for="r in [
                                ...matches.permits,
                                ...matches.receipts,
                            ]"
                            :key="`${r.permit_number || r.receipt_number}-${r.id}`"
                            ><Link
                                v-if="r.application_id"
                                :href="application(r.application_id)"
                                class="mt-2 block text-sm font-medium hover:underline"
                                >{{ r.permit_number || r.receipt_number }}
                                <Badge
                                    v-if="r.is_duplicate_claim"
                                    variant="destructive"
                                    >Duplicate OR claim</Badge
                                ></Link
                            >
                            <p v-else class="mt-2 text-sm text-amber-700">
                                {{ r.permit_number || r.receipt_number }} ·
                                orphaned source claim
                            </p></template
                        >
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border bg-background">
                <div
                    class="flex flex-wrap items-center justify-between gap-2 border-b p-4"
                >
                    <div>
                        <h2 class="font-semibold">Business Directory</h2>
                        <p class="text-xs text-muted-foreground">
                            {{ businesses.from ?? 0 }}–{{
                                businesses.to ?? 0
                            }}
                            of {{ businesses.total }} rescued businesses
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <select
                            v-model="form.sort"
                            aria-label="Sort businesses"
                            class="h-9 rounded-md border bg-transparent px-2 text-sm"
                            @change="apply"
                        >
                            <option value="name">Business</option>
                            <option value="owner">Owner</option>
                            <option value="barangay">Barangay</option>
                            <option value="applications">
                                Applications
                            </option></select
                        ><select
                            v-model="form.direction"
                            aria-label="Sort direction"
                            class="h-9 rounded-md border bg-transparent px-2 text-sm"
                            @change="apply"
                        >
                            <option value="asc">Ascending</option>
                            <option value="desc">Descending</option>
                        </select>
                    </div>
                </div>
                <ul class="divide-y">
                    <li
                        v-for="row in businesses.data"
                        :key="row.id"
                        class="grid gap-2 p-4 sm:grid-cols-[1fr_auto]"
                    >
                        <div>
                            <Link
                                :href="business(row.id)"
                                class="font-semibold hover:underline"
                                >{{ row.name }}</Link
                            >
                            <p class="text-sm text-muted-foreground">
                                <Link
                                    :href="owner(row.owner_id)"
                                    class="hover:underline"
                                    >{{ row.owner_name }}</Link
                                >
                                ·
                                {{
                                    row.barangay_literal ||
                                    'Barangay not recorded'
                                }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2 text-sm">
                            <Badge
                                v-if="row.collision_candidate"
                                variant="outline"
                                >Possible duplicate</Badge
                            ><span
                                >{{ row.application_count }} applications</span
                            >
                        </div>
                    </li>
                </ul>
                <nav
                    class="flex flex-wrap gap-1 border-t p-3"
                    aria-label="Business directory pages"
                >
                    <Button
                        v-for="link in businesses.links"
                        :key="link.label"
                        as-child
                        :variant="link.active ? 'default' : 'outline'"
                        size="sm"
                        :disabled="!link.url"
                        ><Link v-if="link.url" :href="link.url" preserve-scroll
                            ><span v-html="link.label" /></Link
                        ><span v-else v-html="link.label"
                    /></Button>
                </nav>
            </section>
            <p class="flex items-center gap-2 text-xs text-muted-foreground">
                <Archive class="size-4" />Source values remain evidence.
                Barangay and classification candidates are not accepted
                canonical mappings.
            </p>
        </main>
    </AppLayout>
</template>
