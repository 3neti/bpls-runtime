<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Calculator, Eye, Plus, Search, WalletCards, X } from '@lucide/vue';
import { ref } from 'vue';
import { show as showPaymentSchedule } from '@/actions/App/Http/Controllers/Staff/AssessmentPaymentScheduleController';
import { store as assess } from '@/actions/App/Http/Controllers/Staff/PermitApplicationAssessmentController';
import {
    create,
    index,
    show,
} from '@/actions/App/Http/Controllers/Staff/PermitApplicationController';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type PermitApplicationRow = {
    id: number;
    application_number: string | null;
    type: string;
    status: string;
    application_year: number;
    business: {
        name: string;
        owner: {
            name: string;
        };
    };
    lines: {
        id: number;
        line_of_business: {
            name: string | null;
        };
        declared_gross_sales_cents: number;
        capital_investment_cents: number;
        quantity: number;
    }[];
    latest_assessment: {
        id: number;
        sequence: number;
        status: string;
        total_amount_cents: number;
        assessed_at: string | null;
    } | null;
    latest_payment_schedule: {
        id: number;
        sequence: number;
        status: string;
        total_amount_cents: number;
        paid_amount_cents: number;
    } | null;
    can_continue: boolean;
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
    permitApplications: {
        data: PermitApplicationRow[];
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
    can: {
        create_permit_applications: boolean;
        assess_permit_applications: boolean;
        update_permit_application_status: boolean;
    };
}>();

const search = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? '');

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Permit Applications',
        href: index(),
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

function applyFilters(): void {
    router.get(
        index.url({
            query: {
                q: search.value || undefined,
                status: status.value || undefined,
            },
        }),
        {},
        { preserveState: true, replace: true },
    );
}

function clearFilters(): void {
    search.value = '';
    status.value = '';
    router.get(index.url(), {}, { preserveState: true, replace: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Permit Applications" />

        <main class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <section class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        Permit Applications
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Staff intake and review surface for business permit
                        applications.
                    </p>
                </div>
                <Button v-if="can.create_permit_applications" as-child>
                    <Link :href="create()">
                        <Plus />
                        New Application
                    </Link>
                </Button>
            </section>

            <form
                class="flex flex-col gap-3 rounded-lg border border-sidebar-border/70 bg-background p-4 md:flex-row md:items-end dark:border-sidebar-border"
                @submit.prevent="applyFilters"
            >
                <div class="grid flex-1 gap-2">
                    <label
                        for="permit_application_q"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Search applications
                    </label>
                    <Input
                        id="permit_application_q"
                        v-model="search"
                        name="q"
                        placeholder="Application, tracking reference, business, or owner"
                    />
                </div>
                <div class="grid gap-2 md:w-56">
                    <label
                        for="permit_application_status"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Status
                    </label>
                    <select
                        id="permit_application_status"
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
                aria-label="All applications records"
            >
                <div
                    v-if="permitApplications.data.length === 0"
                    class="px-4 py-10 text-center text-sm text-muted-foreground"
                >
                    No permit applications match the current search and status.
                </div>

                <ul
                    v-else
                    class="divide-y divide-border md:hidden"
                    aria-label="All applications"
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

                        <dl
                            class="grid min-w-0 grid-cols-2 gap-x-4 gap-y-3 text-sm"
                        >
                            <div class="col-span-2 min-w-0">
                                <dt class="text-xs text-muted-foreground">
                                    Business
                                </dt>
                                <dd class="font-medium break-words">
                                    {{ permitApplication.business.name }}
                                </dd>
                                <dd
                                    class="text-xs break-words text-muted-foreground"
                                >
                                    {{ permitApplication.business.owner.name }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Activity
                                </dt>
                                <dd>
                                    {{ permitApplication.lines.length }} line
                                </dd>
                                <dd
                                    v-if="permitApplication.lines[0]"
                                    class="text-xs break-words text-muted-foreground"
                                >
                                    {{
                                        money(
                                            permitApplication.lines[0]
                                                .declared_gross_sales_cents,
                                        )
                                    }}
                                    gross
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Assessment
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
                                <dd v-else class="text-muted-foreground">
                                    Not assessed
                                </dd>
                            </div>
                        </dl>

                        <div
                            class="flex flex-wrap items-center gap-2 border-t pt-3"
                        >
                            <Button as-child variant="outline" size="sm">
                                <Link :href="show(permitApplication.id)">
                                    <Eye />
                                    View
                                </Link>
                            </Button>
                            <Link
                                v-if="
                                    can.assess_permit_applications &&
                                    permitApplication.can_continue
                                "
                                :href="assess(permitApplication.id)"
                                method="post"
                                as="button"
                                :class="buttonVariants({ size: 'sm' })"
                            >
                                <Calculator />
                                Assess
                            </Link>
                            <Button
                                v-if="permitApplication.latest_payment_schedule"
                                as-child
                                variant="outline"
                                size="sm"
                            >
                                <Link
                                    :href="
                                        showPaymentSchedule(
                                            permitApplication
                                                .latest_payment_schedule.id,
                                        )
                                    "
                                >
                                    <WalletCards />
                                    Payment
                                </Link>
                            </Button>
                            <span
                                v-if="!permitApplication.can_continue"
                                class="text-xs text-muted-foreground"
                            >
                                Terminal
                            </span>
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
                                    Activity
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Assessment
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
                                        {{ permitApplication.business.name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{
                                            permitApplication.business.owner
                                                .name
                                        }}
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
                                    <div>
                                        {{ permitApplication.lines.length }}
                                        line
                                    </div>
                                    <div
                                        v-if="permitApplication.lines[0]"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{
                                            money(
                                                permitApplication.lines[0]
                                                    .declared_gross_sales_cents,
                                            )
                                        }}
                                        gross
                                    </div>
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
                                            as-child
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link
                                                :href="
                                                    show(permitApplication.id)
                                                "
                                            >
                                                <Eye />
                                                View
                                            </Link>
                                        </Button>
                                        <Link
                                            v-if="
                                                can.assess_permit_applications &&
                                                permitApplication.can_continue
                                            "
                                            :href="assess(permitApplication.id)"
                                            method="post"
                                            as="button"
                                            :class="
                                                buttonVariants({ size: 'sm' })
                                            "
                                        >
                                            <Calculator />
                                            Assess
                                        </Link>
                                        <Button
                                            v-if="
                                                permitApplication.latest_payment_schedule
                                            "
                                            as-child
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link
                                                :href="
                                                    showPaymentSchedule(
                                                        permitApplication
                                                            .latest_payment_schedule
                                                            .id,
                                                    )
                                                "
                                            >
                                                <WalletCards />
                                                Payment
                                            </Link>
                                        </Button>
                                        <span
                                            v-if="
                                                !permitApplication.can_continue
                                            "
                                            class="text-xs text-muted-foreground"
                                        >
                                            Terminal
                                        </span>
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
                aria-label="All applications pagination"
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
