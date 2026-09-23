<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    index as catalog,
    show,
} from '@/actions/App/Http/Controllers/Staff/FeeRuleController';
import {
    index,
    publish,
    storeGroup,
    recordReview,
} from '@/actions/App/Http/Controllers/Staff/PricingMaintenanceController';
import PricingRevisionForm from '@/components/PricingRevisionForm.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

type Rule = {
    id: number;
    name: string;
    code: string;
    category: string;
    division: string | null;
    method: string;
    amount_display: string;
    price_year: number;
    publication_id: number | null;
    basis: string;
    revenue_code: string | null;
    is_active: boolean;
};
type Detail = Rule & {
    source_basis: string;
    revenue_account_id: number | null;
    group_id: number | null;
    ranges: {
        min_basis_cents: number;
        max_basis_cents: number | null;
        amount_cents: number;
    }[];
    legal_basis: string | null;
    effective_from: string;
    effective_until: string | null;
    snapshot_sha256: string | null;
    reconciliation_id: number | null;
    decision_reference: string | null;
    decision_authority: string | null;
    execution_status: string | null;
    execution_reason: string | null;
    review_status: string;
    revisions: {
        id: number;
        version: number;
        status: string;
        can_publish: boolean;
        amount_display: string;
        effective_from: string;
        effective_until: string | null;
        reason: string;
        authority: string;
    }[];
    reviews: { id: number; reference: string; recorded_at: string | null }[];
};
const props = defineProps<{
    rules: {
        data: Rule[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    selected: Detail | null;
    filters: { search: string; category: string; status: string };
    categories: { value: string; label: string }[];
    canManage: boolean;
    accounts: { id: number; code: string; name: string | null }[];
    groups: { id: number; code: string; name: string }[];
    summary: { rules: number; missing_accounts: number };
}>();
const search = ref(props.filters.search);
const category = ref(props.filters.category);
const status = ref(props.filters.status);
const review = useForm({
    review_reference: '',
    snapshot_sha256: props.selected?.snapshot_sha256 ?? '',
    reconciliation_id: props.selected?.reconciliation_id ?? 0,
});
const submitted = ref(false);
const publication = useForm({});
const groupForm = useForm({
    code: '',
    name: '',
    parent_id: null as number | null,
});
function saveGroup() {
    groupForm.post(storeGroup.url(), {
        preserveScroll: true,
        onSuccess: () => groupForm.reset(),
    });
}
const publishCandidate = ref<number | null>(null);
function publishRevision(id: number) {
    publication.post(publish.url(id), {
        preserveScroll: true,
        onSuccess: () => {
            publishCandidate.value = null;
        },
    });
}
watch(
    () => props.selected,
    (selected) => {
        submitted.value = false;
        publishCandidate.value = null;
        review.reset();
        review.clearErrors();
        review.snapshot_sha256 = selected?.snapshot_sha256 ?? '';
        review.reconciliation_id = selected?.reconciliation_id ?? 0;
    },
);
const canReview = computed(
    () => props.canManage && !!props.selected?.snapshot_sha256,
);
function filter() {
    submitted.value = false;
    router.get(index.url(), {
        search: search.value,
        category: category.value,
        status: status.value,
    });
}
function saveReview() {
    if (!props.selected || !canReview.value) {
        return;
    }

    review.post(recordReview.url(props.selected.id), {
        preserveScroll: true,
        onSuccess: () => {
            submitted.value = true;
        },
    });
}
</script>

<template>
    <Head title="Pricing Maintenance" />
    <AppLayout
        :breadcrumbs="[{ title: 'Pricing Maintenance', href: index.url() }]"
    >
        <div class="flex min-w-0 flex-col gap-6 p-4 lg:p-6">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p
                        class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                    >
                        Municipality of Ipil · Treasury
                    </p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight">
                        Pricing Maintenance
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Find a fee. Review its basis. Prepare a change.
                    </p>
                </div>
                <Button variant="outline" as-child
                    ><Link :href="catalog()"
                        >Municipal Schedule of Fees</Link
                    ></Button
                >
            </header>
            <details v-if="canManage" class="rounded-lg border p-4">
                <summary class="cursor-pointer text-sm font-medium">
                    Fee groups · Add group
                </summary>
                <form
                    class="mt-3 grid gap-3 sm:grid-cols-2"
                    @submit.prevent="saveGroup"
                >
                    <div class="grid gap-2">
                        <Label for="group-code">Group code</Label
                        ><Input
                            id="group-code"
                            v-model="groupForm.code"
                            required
                            placeholder="e.g. REGULATORY"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="group-name">Group name</Label
                        ><Input
                            id="group-name"
                            v-model="groupForm.name"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="group-parent">Parent group</Label
                        ><select
                            id="group-parent"
                            v-model="groupForm.parent_id"
                            class="h-9 rounded border bg-background px-2"
                        >
                            <option :value="null">Top level</option>
                            <option
                                v-for="group in groups"
                                :key="group.id"
                                :value="group.id"
                            >
                                {{ group.name }}
                            </option>
                        </select>
                    </div>
                    <Button type="submit" :disabled="groupForm.processing"
                        >Add group</Button
                    >
                    <p
                        v-for="(error, key) in groupForm.errors"
                        :key="key"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ error }}
                    </p>
                </form>
            </details>
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border bg-card p-4">
                    <p class="text-sm text-muted-foreground">Catalogue rules</p>
                    <p class="mt-1 text-2xl font-semibold">
                        {{ summary.rules }}
                    </p>
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <p class="text-sm text-muted-foreground">
                        Without account mapping
                    </p>
                    <p class="mt-1 text-2xl font-semibold">
                        {{ summary.missing_accounts }}
                    </p>
                </div>
                <div class="rounded-xl border bg-muted/30 p-4">
                    <p class="font-medium">Versioned pricing</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Publish effective-dated revisions. Existing assessments
                        stay frozen.
                    </p>
                </div>
            </div>
            <form
                class="grid items-end gap-3 rounded-xl border p-4 sm:grid-cols-[minmax(0,1fr)_auto_auto_auto]"
                @submit.prevent="filter"
            >
                <div class="grid gap-2">
                    <Label for="pricing-search"
                        >Search fees or revenue codes</Label
                    ><Input
                        id="pricing-search"
                        v-model="search"
                        placeholder="e.g. Laminated ID"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="pricing-category">Category</Label
                    ><select
                        id="pricing-category"
                        v-model="category"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">All categories</option>
                        <option
                            v-for="option in categories"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label for="pricing-status">Catalogue status</Label
                    ><select
                        id="pricing-status"
                        v-model="status"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <Button type="submit">Search</Button>
            </form>
            <div
                class="grid min-w-0 items-start gap-5 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]"
            >
                <section
                    class="min-w-0 rounded-xl border bg-card"
                    aria-label="Fee catalogue"
                >
                    <div class="flex items-center justify-between border-b p-4">
                        <h2 class="font-semibold">Fees and taxes</h2>
                        <span class="text-sm text-muted-foreground"
                            >{{ rules.total }} results</span
                        >
                    </div>
                    <div
                        v-if="!rules.data.length"
                        class="p-8 text-center text-muted-foreground"
                    >
                        No matching fees. Try another search or clear the
                        filters.
                    </div>
                    <Link
                        v-for="rule in rules.data"
                        :key="rule.id"
                        :href="
                            index({
                                query: {
                                    ...filters,
                                    rule: rule.id,
                                    page: rules.current_page,
                                },
                            })
                        "
                        :aria-current="
                            selected?.id === rule.id ? 'true' : undefined
                        "
                        class="flex min-w-0 flex-wrap justify-between gap-3 border-b p-4 transition-colors last:border-0 hover:bg-muted/50 focus-visible:outline-2 focus-visible:outline-primary"
                        :class="
                            selected?.id === rule.id
                                ? 'border-l-4 border-l-primary bg-muted/40'
                                : ''
                        "
                    >
                        <div class="min-w-0 flex-1">
                            <p class="font-medium break-words">
                                {{ rule.name }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ rule.category }} ·
                                {{ rule.division ?? 'No business division' }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Revenue code {{ rule.revenue_code ?? '—' }}
                            </p>
                        </div>
                        <div class="max-w-full text-right">
                            <p class="text-sm font-semibold break-words">
                                {{ rule.amount_display }}
                            </p>
                            <Badge variant="secondary" class="mt-2">{{
                                rule.is_active ? 'Active' : 'Inactive'
                            }}</Badge>
                        </div>
                    </Link>
                    <div
                        class="flex items-center justify-between gap-2 border-t p-4"
                    >
                        <Button
                            v-if="rules.prev_page_url"
                            variant="outline"
                            as-child
                            ><Link :href="rules.prev_page_url"
                                >Previous</Link
                            ></Button
                        ><span v-else />
                        <span class="text-xs text-muted-foreground"
                            >{{ rules.current_page }} /
                            {{ rules.last_page }}</span
                        >
                        <Button
                            v-if="rules.next_page_url"
                            variant="outline"
                            as-child
                            ><Link :href="rules.next_page_url"
                                >Next</Link
                            ></Button
                        ><span v-else />
                    </div>
                </section>
                <section
                    v-if="selected"
                    :key="selected.id"
                    class="min-w-0 rounded-xl border bg-card p-5"
                    aria-label="Selected fee"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <p
                            class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            Selected fee
                        </p>
                        <Badge variant="outline">{{
                            selected.review_status
                        }}</Badge>
                    </div>
                    <h2 class="mt-3 text-xl font-semibold break-words">
                        {{ selected.name }}
                    </h2>
                    <div class="my-5 rounded-lg bg-muted/40 p-4">
                        <p class="mb-1 text-xs text-muted-foreground">
                            {{ selected.price_year }} pricing ·
                            {{
                                selected.publication_id
                                    ? 'Published revision'
                                    : 'Source catalogue'
                            }}
                        </p>
                        <p class="text-2xl font-semibold break-words">
                            {{ selected.amount_display }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ selected.basis }}
                        </p>
                    </div>
                    <dl class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-muted-foreground">Revenue code</dt>
                            <dd class="mt-1 break-words">
                                {{ selected.revenue_code ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Method</dt>
                            <dd class="mt-1 capitalize">
                                {{ selected.method }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">
                                Effective from
                            </dt>
                            <dd class="mt-1">{{ selected.effective_from }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">
                                Effective until
                            </dt>
                            <dd class="mt-1">
                                {{ selected.effective_until ?? 'Open-ended' }}
                            </dd>
                        </div>
                    </dl>
                    <div class="mt-5 border-t pt-4">
                        <h3 class="text-sm font-semibold">
                            Policy &amp; authority
                        </h3>
                        <p class="mt-2 text-sm break-words">
                            {{
                                selected.legal_basis ??
                                'Authority reference not recorded.'
                            }}
                        </p>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{
                                selected.execution_reason ??
                                'No reconciliation decision recorded.'
                            }}
                        </p>
                        <p
                            v-if="selected.decision_reference"
                            class="mt-2 text-xs break-words"
                        >
                            {{ selected.decision_authority }} ·
                            {{ selected.decision_reference }}
                        </p>
                    </div>
                    <PricingRevisionForm
                        v-if="
                            canManage &&
                            (selected.source_basis === 'none' ||
                                selected.source_basis === 'employee_count' ||
                                selected.method === 'range')
                        "
                        :key="selected.id"
                        :rule-id="selected.id"
                        :method="selected.method"
                        :basis="selected.source_basis"
                        :ranges="selected.ranges"
                        :account-id="selected.revenue_account_id"
                        :group-id="selected.group_id"
                        :accounts="accounts"
                        :groups="groups"
                    />
                    <Button v-else-if="canManage" as-child class="mt-5 w-full"
                        ><Link :href="show(selected.id)"
                            >Review calculation &amp; revisions</Link
                        ></Button
                    >
                    <details
                        v-if="selected.revisions.length"
                        class="mt-5 border-t pt-4"
                    >
                        <summary class="cursor-pointer text-sm font-medium">
                            Recent price proposals ({{
                                selected.revisions.length
                            }})
                        </summary>
                        <ul class="mt-3 grid gap-3 text-sm">
                            <li
                                v-for="entry in selected.revisions"
                                :key="entry.id"
                                class="rounded-lg bg-muted/40 p-3"
                            >
                                <p class="font-medium">
                                    Revision {{ entry.version }} ·
                                    {{ entry.amount_display }}
                                </p>
                                <p class="mt-1 capitalize">
                                    {{ entry.status
                                    }}{{
                                        entry.status === 'published'
                                            ? ' · Effective-date lookup'
                                            : ' · Not executable'
                                    }}
                                </p>
                                <p class="mt-1">
                                    {{ entry.effective_from }} →
                                    {{ entry.effective_until ?? 'Open-ended' }}
                                </p>
                                <p class="mt-2 break-words">
                                    {{ entry.reason }}
                                </p>
                                <p
                                    class="mt-1 break-words text-muted-foreground"
                                >
                                    {{ entry.authority }}
                                </p>
                                <div
                                    v-if="canManage && entry.can_publish"
                                    class="mt-3 grid gap-2"
                                >
                                    <Button
                                        v-if="publishCandidate !== entry.id"
                                        variant="outline"
                                        @click="publishCandidate = entry.id"
                                        >Publish revision
                                        {{ entry.version }}</Button
                                    >
                                    <template v-else>
                                        <p>
                                            Publish
                                            {{ entry.amount_display }} from
                                            {{ entry.effective_from }}? Lookup
                                            uses January 1 of the application
                                            tax year. Frozen assessments will
                                            not change.
                                        </p>
                                        <Button
                                            :disabled="publication.processing"
                                            @click="publishRevision(entry.id)"
                                            >{{
                                                publication.processing
                                                    ? 'Publishing…'
                                                    : 'Confirm publication'
                                            }}</Button
                                        >
                                        <Button
                                            variant="ghost"
                                            :disabled="publication.processing"
                                            @click="publishCandidate = null"
                                            >Cancel</Button
                                        >
                                    </template>
                                </div>
                            </li>
                        </ul>
                    </details>
                    <p
                        v-for="(error, key) in publication.errors"
                        :key="key"
                        role="alert"
                        class="mt-3 text-sm text-destructive"
                    >
                        {{ error }}
                    </p>
                    <form
                        v-if="canReview"
                        class="mt-5 grid gap-3 border-t pt-4"
                        @submit.prevent="saveReview"
                    >
                        <Label for="review-reference">Review reference</Label
                        ><Input
                            id="review-reference"
                            v-model="review.review_reference"
                            required
                            maxlength="1000"
                            placeholder="Memo or review record reference"
                        />
                        <p
                            v-for="(error, key) in review.errors"
                            :key="key"
                            class="text-sm text-destructive"
                            role="alert"
                        >
                            {{ error }}
                        </p>
                        <Button
                            type="submit"
                            variant="outline"
                            :disabled="review.processing"
                            >{{
                                review.processing
                                    ? 'Recording…'
                                    : 'Record this content review'
                            }}</Button
                        >
                        <p class="text-xs text-muted-foreground">
                            Records the version you reviewed. Does not publish
                            or approve pricing.
                        </p>
                    </form>
                    <p
                        v-else-if="canManage"
                        class="mt-5 rounded-lg bg-muted p-3 text-sm text-muted-foreground"
                    >
                        Record a reconciliation decision before capturing a
                        content review.
                    </p>
                    <p v-if="submitted" role="status" class="mt-3 text-sm">
                        Review recorded. Current prices are unchanged.
                    </p>
                    <details class="mt-5 border-t pt-4">
                        <summary class="cursor-pointer text-sm font-medium">
                            History &amp; source identity
                        </summary>
                        <p class="mt-3 font-mono text-xs break-all">
                            {{ selected.code }}
                        </p>
                        <ul class="mt-3 grid gap-2 text-sm">
                            <li
                                v-for="entry in selected.reviews"
                                :key="entry.id"
                                class="rounded-md bg-muted/40 p-3"
                            >
                                <p class="break-words">{{ entry.reference }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{
                                        entry.recorded_at
                                            ? new Date(
                                                  entry.recorded_at,
                                              ).toLocaleString()
                                            : '—'
                                    }}
                                </p>
                            </li>
                            <li
                                v-if="!selected.reviews.length"
                                class="text-muted-foreground"
                            >
                                No content reviews yet.
                            </li>
                        </ul>
                    </details>
                </section>
                <section
                    v-else
                    class="rounded-xl border border-dashed p-8 text-center text-muted-foreground"
                >
                    Select a fee to view its basis and prepare a change.
                </section>
            </div>
        </div>
    </AppLayout>
</template>
