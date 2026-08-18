<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, Search, X } from '@lucide/vue';
import { ref } from 'vue';
import {
    index,
    show,
} from '@/actions/App/Http/Controllers/Staff/ReceiptController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type ReceiptRow = {
    id: number;
    status: string;
    numbering_authority: string;
    receipt_number: string;
    amount_cents: number;
    issued_at: string;
    issued_by: string | null;
    collection: {
        id: number;
        status: string;
        method: string;
        payer_name: string | null;
        reference_number: string | null;
    };
    payment_schedule: {
        id: number;
        sequence: number;
        status: string;
    };
    permit_application: {
        id: number;
        application_number: string | null;
        status: string;
        application_year: number;
        business_name: string;
        trade_name: string | null;
        owner_name: string;
    };
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Option = {
    label: string;
    value: string;
};

const props = defineProps<{
    receipts: {
        data: ReceiptRow[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: {
        q: string;
        status: string | null;
    };
    statuses: Option[];
}>();

const search = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? '');

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Receipts',
        href: index(),
    },
];

function applyFilters(): void {
    router.get(
        index.url({
            query: {
                q: search.value || undefined,
                status: status.value || undefined,
            },
        }),
        {},
        {
            preserveState: true,
            replace: true,
        },
    );
}

function clearFilters(): void {
    search.value = '';
    status.value = '';
    router.get(index.url(), {}, { preserveState: true, replace: true });
}

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
        <Head title="Receipts" />

        <main class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        Receipts
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Treasury receipt queue for issued and void-boundary
                        receipt evidence.
                    </p>
                </div>
                <div class="text-sm text-muted-foreground">
                    {{ receipts.total }} receipt<span
                        v-if="receipts.total !== 1"
                        >s</span
                    >
                </div>
            </section>

            <form
                class="flex flex-col gap-3 rounded-lg border border-sidebar-border/70 bg-background p-4 md:flex-row md:items-end dark:border-sidebar-border"
                @submit.prevent="applyFilters"
            >
                <div class="grid flex-1 gap-2">
                    <label
                        for="receipt_q"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Search
                    </label>
                    <Input
                        id="receipt_q"
                        v-model="search"
                        name="q"
                        placeholder="Receipt, application, business, payer"
                    />
                </div>
                <div class="grid gap-2 md:w-56">
                    <label
                        for="receipt_status"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Status
                    </label>
                    <select
                        id="receipt_status"
                        v-model="status"
                        name="status"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="">All statuses</option>
                        <option
                            v-for="option in statuses"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <Button type="submit" class="flex-1 sm:flex-none">
                        <Search />
                        Search
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="flex-1 sm:flex-none"
                        @click="clearFilters"
                    >
                        <X />
                        Clear
                    </Button>
                </div>
            </form>

            <section
                class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background dark:border-sidebar-border"
                aria-label="Receipt records"
            >
                <div
                    v-if="receipts.data.length === 0"
                    class="px-4 py-10 text-center text-sm text-muted-foreground"
                >
                    No receipts match the current filters.
                </div>

                <ul
                    v-else
                    class="divide-y divide-border md:hidden"
                    aria-label="Receipts"
                >
                    <li
                        v-for="receipt in receipts.data"
                        :key="receipt.id"
                        class="grid min-w-0 gap-4 p-4"
                    >
                        <div
                            class="flex min-w-0 items-start justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <p class="font-medium break-words">
                                    {{ receipt.receipt_number }}
                                </p>
                                <p
                                    class="text-xs break-words text-muted-foreground capitalize"
                                >
                                    {{ receipt.numbering_authority }} numbering
                                </p>
                            </div>
                            <div class="shrink-0 text-right">
                                <Badge variant="secondary" class="capitalize">
                                    {{ statusLabel(receipt.status) }}
                                </Badge>
                                <p
                                    class="mt-1 text-xs text-muted-foreground capitalize"
                                >
                                    Collection
                                    {{ statusLabel(receipt.collection.status) }}
                                </p>
                            </div>
                        </div>

                        <dl
                            class="grid min-w-0 grid-cols-2 gap-x-4 gap-y-3 text-sm"
                        >
                            <div class="col-span-2 min-w-0">
                                <dt class="text-xs text-muted-foreground">
                                    Payer
                                </dt>
                                <dd class="font-medium break-words">
                                    {{
                                        receipt.collection.payer_name ??
                                        receipt.permit_application.owner_name
                                    }}
                                </dd>
                                <dd
                                    class="text-xs break-words text-muted-foreground"
                                >
                                    {{
                                        receipt.collection.reference_number ??
                                        statusLabel(receipt.collection.method)
                                    }}
                                </dd>
                            </div>
                            <div class="col-span-2 min-w-0">
                                <dt class="text-xs text-muted-foreground">
                                    Application
                                </dt>
                                <dd class="font-medium break-words">
                                    {{
                                        receipt.permit_application
                                            .application_number ??
                                        `Application #${receipt.permit_application.id}`
                                    }}
                                </dd>
                                <dd
                                    class="text-xs break-words text-muted-foreground"
                                >
                                    {{
                                        receipt.permit_application.business_name
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Amount
                                </dt>
                                <dd class="font-medium">
                                    {{ money(receipt.amount_cents) }}
                                </dd>
                                <dd class="text-xs text-muted-foreground">
                                    Schedule #{{
                                        receipt.payment_schedule.sequence
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Issued
                                </dt>
                                <dd class="break-words">
                                    {{ receipt.issued_at }}
                                </dd>
                                <dd
                                    class="text-xs break-words text-muted-foreground"
                                >
                                    {{ receipt.issued_by ?? 'System' }}
                                </dd>
                            </div>
                        </dl>

                        <div class="flex border-t pt-3">
                            <Button
                                as-child
                                variant="outline"
                                size="sm"
                                class="w-full"
                            >
                                <Link :href="show(receipt.id)">
                                    <Eye />
                                    View
                                </Link>
                            </Button>
                        </div>
                    </li>
                </ul>

                <div
                    v-if="receipts.data.length > 0"
                    class="hidden overflow-x-auto md:block"
                >
                    <table class="w-full min-w-[900px] text-sm">
                        <thead
                            class="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3 font-medium">Receipt</th>
                                <th class="px-4 py-3 font-medium">Payer</th>
                                <th class="px-4 py-3 font-medium">
                                    Application
                                </th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Amount
                                </th>
                                <th class="px-4 py-3 font-medium">Issued</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="receipt in receipts.data"
                                :key="receipt.id"
                                class="border-b last:border-b-0"
                            >
                                <td class="px-4 py-3 align-top">
                                    <div class="font-medium">
                                        {{ receipt.receipt_number }}
                                    </div>
                                    <div
                                        class="text-xs text-muted-foreground capitalize"
                                    >
                                        {{ receipt.numbering_authority }}
                                        numbering
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="font-medium">
                                        {{
                                            receipt.collection.payer_name ??
                                            receipt.permit_application
                                                .owner_name
                                        }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{
                                            receipt.collection
                                                .reference_number ??
                                            receipt.collection.method.replaceAll(
                                                '_',
                                                ' ',
                                            )
                                        }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="font-medium">
                                        {{
                                            receipt.permit_application
                                                .application_number ??
                                            `Application #${receipt.permit_application.id}`
                                        }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{
                                            receipt.permit_application
                                                .business_name
                                        }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <Badge
                                        variant="secondary"
                                        class="capitalize"
                                    >
                                        {{ statusLabel(receipt.status) }}
                                    </Badge>
                                    <div
                                        class="mt-1 text-xs text-muted-foreground capitalize"
                                    >
                                        Collection
                                        {{
                                            receipt.collection.status.replaceAll(
                                                '_',
                                                ' ',
                                            )
                                        }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right align-top">
                                    <div class="font-medium">
                                        {{ money(receipt.amount_cents) }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        Schedule #{{
                                            receipt.payment_schedule.sequence
                                        }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div>{{ receipt.issued_at }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ receipt.issued_by ?? 'System' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="flex justify-end">
                                        <Button
                                            as-child
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link :href="show(receipt.id)">
                                                <Eye />
                                                View
                                            </Link>
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <nav
                v-if="receipts.links.length > 3"
                class="flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between"
                aria-label="Receipts pagination"
            >
                <div class="text-muted-foreground">
                    Showing {{ receipts.from ?? 0 }} to
                    {{ receipts.to ?? 0 }} of {{ receipts.total }}
                </div>
                <div class="flex flex-wrap gap-1">
                    <template v-for="link in receipts.links" :key="link.label">
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
