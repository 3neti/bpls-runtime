<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, Search, X } from '@lucide/vue';
import { ref } from 'vue';
import {
    index,
    show,
} from '@/actions/App/Http/Controllers/Staff/AssessmentPaymentScheduleController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type PaymentScheduleRow = {
    id: number;
    sequence: number;
    status: string;
    payment_mode: string;
    due_on: string | null;
    total_amount_cents: number;
    paid_amount_cents: number;
    created_at: string | null;
    assessment: {
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
    paymentSchedules: {
        data: PaymentScheduleRow[];
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
        title: 'Payment Schedules',
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
        <Head title="Payment Schedules" />

        <main class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        Payment Schedules
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Treasury queue for assessment payment schedules and
                        balances.
                    </p>
                </div>
                <div class="text-sm text-muted-foreground">
                    {{ paymentSchedules.total }} schedule<span
                        v-if="paymentSchedules.total !== 1"
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
                        for="payment_schedule_q"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Search
                    </label>
                    <Input
                        id="payment_schedule_q"
                        v-model="search"
                        name="q"
                        placeholder="Application, business, owner, schedule"
                    />
                </div>
                <div class="grid gap-2 md:w-56">
                    <label
                        for="payment_schedule_status"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Status
                    </label>
                    <select
                        id="payment_schedule_status"
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
                aria-label="Payment schedule records"
            >
                <div
                    v-if="paymentSchedules.data.length === 0"
                    class="px-4 py-10 text-center text-sm text-muted-foreground"
                >
                    No payment schedules match the current filters.
                </div>

                <ul
                    v-else
                    class="divide-y divide-border md:hidden"
                    aria-label="Payment schedules"
                >
                    <li
                        v-for="paymentSchedule in paymentSchedules.data"
                        :key="paymentSchedule.id"
                        class="grid min-w-0 gap-4 p-4"
                    >
                        <div
                            class="flex min-w-0 items-start justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <p class="font-medium">
                                    Schedule #{{ paymentSchedule.sequence }}
                                </p>
                                <p
                                    class="text-xs break-words text-muted-foreground capitalize"
                                >
                                    {{ paymentSchedule.payment_mode }} ·
                                    {{
                                        paymentSchedule.due_on ?? 'No due date'
                                    }}
                                </p>
                            </div>
                            <Badge
                                variant="secondary"
                                class="shrink-0 capitalize"
                            >
                                {{ statusLabel(paymentSchedule.status) }}
                            </Badge>
                        </div>

                        <dl
                            class="grid min-w-0 grid-cols-2 gap-x-4 gap-y-3 text-sm"
                        >
                            <div class="col-span-2 min-w-0">
                                <dt class="text-xs text-muted-foreground">
                                    Application
                                </dt>
                                <dd class="font-medium break-words">
                                    {{
                                        paymentSchedule.permit_application
                                            .application_number ??
                                        `Application #${paymentSchedule.permit_application.id}`
                                    }}
                                </dd>
                                <dd class="text-xs text-muted-foreground">
                                    {{
                                        paymentSchedule.permit_application
                                            .application_year
                                    }}
                                </dd>
                            </div>
                            <div class="col-span-2 min-w-0">
                                <dt class="text-xs text-muted-foreground">
                                    Business
                                </dt>
                                <dd class="font-medium break-words">
                                    {{
                                        paymentSchedule.permit_application
                                            .business_name
                                    }}
                                </dd>
                                <dd
                                    class="text-xs break-words text-muted-foreground"
                                >
                                    {{
                                        paymentSchedule.permit_application
                                            .owner_name
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Paid
                                </dt>
                                <dd>
                                    {{
                                        money(paymentSchedule.paid_amount_cents)
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Balance
                                </dt>
                                <dd class="font-medium">
                                    {{
                                        money(
                                            paymentSchedule.total_amount_cents -
                                                paymentSchedule.paid_amount_cents,
                                        )
                                    }}
                                </dd>
                                <dd class="text-xs text-muted-foreground">
                                    Total
                                    {{
                                        money(
                                            paymentSchedule.total_amount_cents,
                                        )
                                    }}
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
                                <Link :href="show(paymentSchedule.id)">
                                    <Eye />
                                    View
                                </Link>
                            </Button>
                        </div>
                    </li>
                </ul>

                <div
                    v-if="paymentSchedules.data.length > 0"
                    class="hidden overflow-x-auto md:block"
                >
                    <table class="w-full min-w-[900px] text-sm">
                        <thead
                            class="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3 font-medium">Schedule</th>
                                <th class="px-4 py-3 font-medium">
                                    Application
                                </th>
                                <th class="px-4 py-3 font-medium">Business</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Paid
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Balance
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="paymentSchedule in paymentSchedules.data"
                                :key="paymentSchedule.id"
                                class="border-b last:border-b-0"
                            >
                                <td class="px-4 py-3 align-top">
                                    <div class="font-medium">
                                        Schedule #{{ paymentSchedule.sequence }}
                                    </div>
                                    <div
                                        class="text-xs text-muted-foreground capitalize"
                                    >
                                        {{ paymentSchedule.payment_mode }} ·
                                        {{
                                            paymentSchedule.due_on ??
                                            'No due date'
                                        }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="font-medium">
                                        {{
                                            paymentSchedule.permit_application
                                                .application_number ??
                                            `Application #${paymentSchedule.permit_application.id}`
                                        }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{
                                            paymentSchedule.permit_application
                                                .application_year
                                        }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="font-medium">
                                        {{
                                            paymentSchedule.permit_application
                                                .business_name
                                        }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{
                                            paymentSchedule.permit_application
                                                .owner_name
                                        }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <Badge
                                        variant="secondary"
                                        class="capitalize"
                                    >
                                        {{
                                            statusLabel(paymentSchedule.status)
                                        }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-right align-top">
                                    {{
                                        money(paymentSchedule.paid_amount_cents)
                                    }}
                                </td>
                                <td class="px-4 py-3 text-right align-top">
                                    <div class="font-medium">
                                        {{
                                            money(
                                                paymentSchedule.total_amount_cents -
                                                    paymentSchedule.paid_amount_cents,
                                            )
                                        }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        Total
                                        {{
                                            money(
                                                paymentSchedule.total_amount_cents,
                                            )
                                        }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="flex justify-end">
                                        <Button
                                            as-child
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link
                                                :href="show(paymentSchedule.id)"
                                            >
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
                v-if="paymentSchedules.links.length > 3"
                class="flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between"
                aria-label="Payment schedules pagination"
            >
                <div class="text-muted-foreground">
                    Showing {{ paymentSchedules.from ?? 0 }} to
                    {{ paymentSchedules.to ?? 0 }} of
                    {{ paymentSchedules.total }}
                </div>
                <div class="flex flex-wrap gap-1">
                    <template
                        v-for="link in paymentSchedules.links"
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
