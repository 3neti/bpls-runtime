<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Search, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { index as feeRulesIndex } from '@/actions/App/Http/Controllers/Staff/FeeRuleController';
import {
    index,
    reconcile,
} from '@/actions/App/Http/Controllers/Staff/LegacyFeeCatalogController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Candidate = {
    id: number;
    name: string;
    calculation_type: 'constant' | 'range' | 'formula';
    legacy_fee_category: string | null;
    application_types: string[];
    amount_minor: number | null;
    range_field: string | null;
    ranges: {
        minimum_basis_minor: number | null;
        maximum_basis_minor: number | null;
        amount_minor: number | null;
        formula: string | null;
    }[];
    range_count: number;
    formula: string | null;
    applicability_scope: string;
    division_name: string | null;
    applicable_group_names: string[];
    applicable_group_count: number;
    overrides: {
        group_name: string | null;
        amount_minor: number | null;
        range_field: string | null;
        range_count: number;
        reason: string | null;
    }[];
    override_count: number;
    duplicate_name_count: number;
    exact_name_target_count: number;
    blockers: string[];
    review_disposition: string;
    review_reason: string | null;
    fee_rule: {
        id: number;
        code: string;
        name: string;
        active: boolean;
    } | null;
    source_payload_sha256: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

const props = defineProps<{
    filters: {
        q: string;
        calculation_type: string;
        scope: string;
        disposition: string;
    };
    batch: {
        id: number;
        run_reference: string;
        source_title: string;
        captured_at: string | null;
        archive_sha256: string;
        manifest_sha256: string;
    } | null;
    summary: {
        total: number;
        constant: number;
        range: number;
        formula: number;
        proposed: number;
        quarantined: number;
        executable: number;
    };
    candidates: {
        data: Candidate[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    feeRuleOptions: {
        id: number;
        code: string;
        name: string;
        active: boolean;
    }[];
    canManage: boolean;
}>();

const search = ref(props.filters.q);
const calculationType = ref(props.filters.calculation_type);
const scope = ref(props.filters.scope);
const disposition = ref(props.filters.disposition);
const selectedCandidateId = ref<number | null>(null);
const selectedCandidate = computed(
    () =>
        props.candidates.data.find(
            (candidate) => candidate.id === selectedCandidateId.value,
        ) ?? null,
);
const reviewForm = useForm({
    action: 'create_proposed',
    fee_rule_id: null as number | null,
    reason: '',
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Price List', href: feeRulesIndex() },
    { title: 'Legacy candidates', href: index() },
];

function query(): Record<string, string | undefined> {
    return {
        q: search.value || undefined,
        calculation_type: calculationType.value || undefined,
        scope: scope.value || undefined,
        disposition: disposition.value || undefined,
    };
}

function applyFilters(): void {
    router.get(index.url({ query: query() }), {}, { preserveState: true });
}

function clearFilters(): void {
    search.value = '';
    calculationType.value = '';
    scope.value = '';
    disposition.value = '';
    applyFilters();
}

function openReview(candidate: Candidate): void {
    selectedCandidateId.value = candidate.id;
    reviewForm.reset();
    reviewForm.action =
        candidate.review_disposition === 'pending'
            ? 'create_proposed'
            : candidate.review_disposition;
    reviewForm.fee_rule_id = candidate.fee_rule?.id ?? null;
    reviewForm.reason = candidate.review_reason ?? '';
}

function submitReview(): void {
    if (selectedCandidate.value === null) {
        return;
    }

    reviewForm.post(reconcile(selectedCandidate.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            selectedCandidateId.value = null;
            reviewForm.reset();
        },
    });
}

function money(amountMinor: number | null): string {
    if (amountMinor === null) {
        return '—';
    }

    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amountMinor / 100);
}

function label(value: string | null): string {
    return value ? value.replaceAll('_', ' ') : '—';
}

function value(candidate: Candidate): string {
    if (candidate.calculation_type === 'constant') {
        return money(candidate.amount_minor);
    }

    if (candidate.calculation_type === 'range') {
        return `${candidate.range_count} bands`;
    }

    return 'Formula';
}

function decodePaginationLabel(value: string): string {
    return value
        .replace('&laquo; Previous', 'Previous')
        .replace('Next &raquo;', 'Next')
        .replace('&laquo;', 'Previous')
        .replace('&raquo;', 'Next');
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Legacy Price List Candidates" />

        <main class="flex h-full flex-1 flex-col gap-4 p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p
                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Municipality of Ipil
                    </p>
                    <h1 class="text-xl font-semibold">Legacy Price List</h1>
                    <p class="text-sm text-muted-foreground">
                        Review the names and values currently used by the legacy
                        system.
                    </p>
                </div>
                <Button as-child variant="outline">
                    <Link :href="feeRulesIndex()">Active Price List</Link>
                </Button>
            </section>

            <section
                v-if="batch"
                class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6"
            >
                <div class="rounded-lg border bg-card p-3">
                    <p class="text-xs text-muted-foreground">Definitions</p>
                    <p class="text-xl font-semibold">{{ summary.total }}</p>
                </div>
                <div class="rounded-lg border bg-card p-3">
                    <p class="text-xs text-muted-foreground">Fixed</p>
                    <p class="text-xl font-semibold">{{ summary.constant }}</p>
                </div>
                <div class="rounded-lg border bg-card p-3">
                    <p class="text-xs text-muted-foreground">Range</p>
                    <p class="text-xl font-semibold">{{ summary.range }}</p>
                </div>
                <div class="rounded-lg border bg-card p-3">
                    <p class="text-xs text-muted-foreground">Formula</p>
                    <p class="text-xl font-semibold">{{ summary.formula }}</p>
                </div>
                <div class="rounded-lg border bg-card p-3">
                    <p class="text-xs text-muted-foreground">Reviewed</p>
                    <p class="text-xl font-semibold">
                        {{ summary.proposed + summary.quarantined }}
                    </p>
                </div>
                <div class="rounded-lg border bg-card p-3">
                    <p class="text-xs text-muted-foreground">Executable</p>
                    <p class="text-xl font-semibold">
                        {{ summary.executable }}
                    </p>
                </div>
            </section>

            <section
                v-else
                class="rounded-lg border border-dashed p-8 text-center"
            >
                <h2 class="font-medium">No staged legacy catalogue</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Stage and characterize the authenticated snapshot to begin
                    review.
                </p>
            </section>

            <details
                v-if="batch"
                class="rounded-lg border bg-muted/20 px-4 py-3"
            >
                <summary class="cursor-pointer text-sm font-medium">
                    Source evidence
                </summary>
                <dl class="mt-3 grid gap-2 text-xs md:grid-cols-2">
                    <div>
                        <dt class="text-muted-foreground">Snapshot</dt>
                        <dd>
                            {{ batch.source_title }} · {{ batch.captured_at }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Run</dt>
                        <dd>{{ batch.run_reference }}</dd>
                    </div>
                    <div class="break-all">
                        <dt class="text-muted-foreground">Archive SHA-256</dt>
                        <dd>{{ batch.archive_sha256 }}</dd>
                    </div>
                    <div class="break-all">
                        <dt class="text-muted-foreground">Manifest SHA-256</dt>
                        <dd>{{ batch.manifest_sha256 }}</dd>
                    </div>
                </dl>
            </details>

            <form
                v-if="batch"
                class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-5"
                @submit.prevent="applyFilters"
            >
                <div class="relative md:col-span-2">
                    <Search
                        class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        class="pl-9"
                        placeholder="Search name or division"
                    />
                </div>
                <select
                    v-model="calculationType"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                >
                    <option value="">All calculations</option>
                    <option value="constant">Fixed</option>
                    <option value="range">Range</option>
                    <option value="formula">Formula</option>
                </select>
                <select
                    v-model="scope"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                >
                    <option value="">All applicability</option>
                    <option value="application_wide">Application-wide</option>
                    <option value="direct_group">Direct LOB</option>
                    <option value="inherited_division">
                        Inherited by division
                    </option>
                </select>
                <div class="flex gap-2">
                    <Button type="submit" class="flex-1">Apply</Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        @click="clearFilters"
                    >
                        <X class="size-4" />
                    </Button>
                </div>
            </form>

            <section
                v-if="batch"
                class="overflow-hidden rounded-lg border bg-card"
            >
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead
                            class="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3">Legacy name</th>
                                <th class="px-4 py-3">Value</th>
                                <th class="px-4 py-3">Applies to</th>
                                <th class="px-4 py-3">Review</th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="candidate in candidates.data"
                                :key="candidate.id"
                                class="align-top"
                            >
                                <td class="px-4 py-3">
                                    <p class="font-medium">
                                        {{ candidate.name }}
                                    </p>
                                    <p
                                        class="mt-0.5 text-xs text-muted-foreground"
                                    >
                                        {{
                                            candidate.legacy_fee_category ||
                                            'Uncategorized'
                                        }}
                                        ·
                                        {{
                                            candidate.application_types.join(
                                                ', ',
                                            ) || 'All applications'
                                        }}
                                    </p>
                                    <details class="mt-2 text-xs">
                                        <summary
                                            class="cursor-pointer text-muted-foreground"
                                        >
                                            Evidence
                                        </summary>
                                        <div
                                            class="mt-2 space-y-1 rounded bg-muted/40 p-2"
                                        >
                                            <p
                                                v-if="candidate.formula"
                                                class="font-mono break-all"
                                            >
                                                {{ candidate.formula }}
                                            </p>
                                            <p v-if="candidate.range_field">
                                                Basis:
                                                {{ candidate.range_field }}
                                            </p>
                                            <p>
                                                {{ candidate.range_count }}
                                                bands ·
                                                {{ candidate.override_count }}
                                                overrides
                                            </p>
                                            <p
                                                v-if="
                                                    candidate.duplicate_name_count >
                                                    1
                                                "
                                            >
                                                {{
                                                    candidate.duplicate_name_count
                                                }}
                                                legacy definitions share this
                                                name.
                                            </p>
                                            <p class="break-all">
                                                SHA-256
                                                {{
                                                    candidate.source_payload_sha256
                                                }}
                                            </p>
                                        </div>
                                    </details>
                                </td>
                                <td class="px-4 py-3 font-medium">
                                    {{ value(candidate) }}
                                </td>
                                <td class="px-4 py-3">
                                    <p>
                                        {{
                                            candidate.division_name ||
                                            label(candidate.applicability_scope)
                                        }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ candidate.applicable_group_count }}
                                        Line{{
                                            candidate.applicable_group_count ===
                                            1
                                                ? ''
                                                : 's'
                                        }}
                                        of Business
                                    </p>
                                    <details
                                        v-if="candidate.applicable_group_count"
                                        class="mt-1 text-xs"
                                    >
                                        <summary
                                            class="cursor-pointer text-muted-foreground"
                                        >
                                            View names
                                        </summary>
                                        <p class="mt-1 max-w-md">
                                            {{
                                                candidate.applicable_group_names.join(
                                                    ' · ',
                                                )
                                            }}
                                        </p>
                                    </details>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        :variant="
                                            candidate.review_disposition ===
                                            'quarantine'
                                                ? 'destructive'
                                                : 'secondary'
                                        "
                                    >
                                        {{
                                            label(candidate.review_disposition)
                                        }}
                                    </Badge>
                                    <p
                                        v-if="candidate.fee_rule"
                                        class="mt-1 text-xs"
                                    >
                                        {{ candidate.fee_rule.name }} ·
                                        {{
                                            candidate.fee_rule.active
                                                ? 'Active'
                                                : 'Inactive'
                                        }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Button
                                        v-if="canManage"
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        @click="openReview(candidate)"
                                    >
                                        Review
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="candidates.data.length === 0">
                                <td
                                    colspan="5"
                                    class="px-4 py-10 text-center text-muted-foreground"
                                >
                                    No candidates match these filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3 text-sm"
                >
                    <p class="text-muted-foreground">
                        {{ candidates.from ?? 0 }}–{{ candidates.to ?? 0 }} of
                        {{ candidates.total }}
                    </p>
                    <div class="flex flex-wrap gap-1">
                        <Button
                            v-for="link in candidates.links"
                            :key="link.label"
                            as-child
                            size="sm"
                            :variant="link.active ? 'default' : 'outline'"
                            :disabled="!link.url"
                        >
                            <Link v-if="link.url" :href="link.url">{{
                                decodePaginationLabel(link.label)
                            }}</Link>
                            <span v-else>{{
                                decodePaginationLabel(link.label)
                            }}</span>
                        </Button>
                    </div>
                </div>
            </section>

            <section
                v-if="selectedCandidate"
                class="sticky bottom-4 z-10 rounded-xl border bg-background p-4 shadow-xl"
            >
                <form
                    class="grid gap-3 lg:grid-cols-[1fr_220px_1fr_auto] lg:items-end"
                    @submit.prevent="submitReview"
                >
                    <div>
                        <p class="text-xs text-muted-foreground">Reviewing</p>
                        <p class="font-medium">{{ selectedCandidate.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            No action here activates a fee.
                        </p>
                    </div>
                    <div class="grid gap-1">
                        <label class="text-xs font-medium">Disposition</label>
                        <select
                            v-model="reviewForm.action"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="create_proposed">
                                Create inactive proposal
                            </option>
                            <option value="map_existing">
                                Propose existing mapping
                            </option>
                            <option value="quarantine">Quarantine</option>
                        </select>
                    </div>
                    <div
                        v-if="reviewForm.action === 'map_existing'"
                        class="grid gap-1"
                    >
                        <label class="text-xs font-medium"
                            >Existing Price List rule</label
                        >
                        <select
                            v-model="reviewForm.fee_rule_id"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                        >
                            <option :value="null">Select a rule</option>
                            <option
                                v-for="rule in feeRuleOptions"
                                :key="rule.id"
                                :value="rule.id"
                            >
                                {{ rule.name }} · {{ rule.code }}
                            </option>
                        </select>
                    </div>
                    <div v-else class="grid gap-1">
                        <label class="text-xs font-medium">Review note</label>
                        <Input
                            v-model="reviewForm.reason"
                            required
                            placeholder="Why this disposition is appropriate"
                        />
                    </div>
                    <div class="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="selectedCandidateId = null"
                            >Cancel</Button
                        >
                        <Button type="submit" :disabled="reviewForm.processing"
                            >Record</Button
                        >
                    </div>
                    <div
                        v-if="reviewForm.action === 'map_existing'"
                        class="grid gap-1 lg:col-start-3"
                    >
                        <label class="text-xs font-medium">Review note</label>
                        <Input
                            v-model="reviewForm.reason"
                            required
                            placeholder="Why this mapping is proposed"
                        />
                    </div>
                </form>
            </section>
        </main>
    </AppLayout>
</template>
