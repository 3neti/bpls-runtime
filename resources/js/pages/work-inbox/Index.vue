<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, Clock3, Inbox, Search, X } from '@lucide/vue';
import { ref } from 'vue';
import { index } from '@/actions/App/Http/Controllers/Staff/MunicipalWorkInboxController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type WorkItem = {
    key: string;
    task_type: string;
    task_label: string;
    office_label: string;
    state: 'action_required';
    received_at: string | null;
    action_url: string;
    application: {
        id: number;
        business_name: string;
        owner_name: string;
        application_number: string | null;
        tracking_reference: string | null;
        type: string;
        year: number;
    };
};

type PaginationLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    workItems: {
        data: WorkItem[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    assignments: Array<{ position: string; role: string }>;
    taskOptions: Array<{ value: string; label: string }>;
    filters: { q: string; task: string; year: number | null };
    counts: {
        action_required: number;
    };
}>();

const search = ref(props.filters.q);
const task = ref(props.filters.task);
const year = ref(props.filters.year?.toString() ?? '');

const breadcrumbs: BreadcrumbItem[] = [{ title: 'My Work', href: index() }];

function applyFilters(): void {
    router.get(
        index.url({
            query: {
                q: search.value || undefined,
                task: task.value || undefined,
                year: year.value || undefined,
            },
        }),
        {},
        { preserveState: true, replace: true },
    );
}

function clearFilters(): void {
    search.value = '';
    task.value = '';
    year.value = '';
    router.get(index.url(), {}, { preserveState: true, replace: true });
}

function formatDate(value: string | null): string {
    if (value === null) {
        return 'Ready now';
    }

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function paginationLabel(value: string): string {
    return value.replace('&laquo;', '‹').replace('&raquo;', '›');
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Municipal Work Inbox" />

        <main class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <section class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <div class="text-xs font-medium text-muted-foreground">
                        My Work
                    </div>
                    <h1 class="text-xl font-semibold">Municipal Work Inbox</h1>
                </div>
                <div class="text-right text-sm text-muted-foreground">
                    <div
                        v-for="assignment in assignments"
                        :key="assignment.role"
                    >
                        {{ assignment.position }}
                    </div>
                    <div v-if="assignments.length === 0">
                        No active municipal position
                    </div>
                </div>
            </section>

            <section aria-label="Work totals">
                <div
                    class="inline-flex min-w-36 items-center justify-between gap-6 rounded-lg border border-primary/30 bg-primary/5 px-4 py-3"
                >
                    <div class="text-xs text-muted-foreground">
                        Action required
                    </div>
                    <div class="text-xl font-semibold">
                        {{ counts.action_required }}
                    </div>
                </div>
            </section>

            <form
                class="grid gap-3 rounded-lg border bg-background p-3 md:grid-cols-[minmax(16rem,1fr)_15rem_8rem_auto] md:items-end"
                @submit.prevent="applyFilters"
            >
                <label
                    class="grid gap-1.5 text-xs font-medium text-muted-foreground"
                >
                    Search
                    <Input
                        v-model="search"
                        placeholder="Business, owner, application, or tracking reference"
                    />
                </label>
                <label
                    class="grid gap-1.5 text-xs font-medium text-muted-foreground"
                >
                    Task
                    <select
                        v-model="task"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">All tasks</option>
                        <option
                            v-for="option in taskOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </label>
                <label
                    class="grid gap-1.5 text-xs font-medium text-muted-foreground"
                >
                    Year
                    <Input
                        v-model="year"
                        inputmode="numeric"
                        placeholder="All"
                    />
                </label>
                <div class="flex gap-2">
                    <Button type="submit"><Search />Search</Button>
                    <Button
                        type="button"
                        variant="outline"
                        aria-label="Clear filters"
                        @click="clearFilters"
                        ><X
                    /></Button>
                </div>
            </form>

            <section class="grid gap-3" aria-label="Assigned municipal work">
                <div
                    v-if="workItems.data.length === 0"
                    class="grid place-items-center gap-2 rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                >
                    <Inbox class="size-7" />
                    <span
                        >No applications require action for this position.</span
                    >
                </div>

                <article
                    v-for="item in workItems.data"
                    :key="item.key"
                    class="grid min-w-0 gap-4 rounded-lg border bg-background p-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-center"
                    data-testid="municipal-work-item"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-semibold">{{ item.task_label }}</h2>
                            <Badge variant="outline">{{
                                item.office_label
                            }}</Badge>
                        </div>
                        <p class="mt-2 truncate text-base font-medium">
                            {{ item.application.business_name }}
                        </p>
                        <p class="truncate text-sm text-muted-foreground">
                            {{ item.application.owner_name }}
                        </p>
                        <div
                            class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground"
                        >
                            <span
                                >{{ item.application.type }} ·
                                {{ item.application.year }}</span
                            >
                            <span>{{
                                item.application.tracking_reference ??
                                item.application.application_number ??
                                `Application #${item.application.id}`
                            }}</span>
                            <span class="inline-flex items-center gap-1"
                                ><Clock3 class="size-3" />{{
                                    formatDate(item.received_at)
                                }}</span
                            >
                        </div>
                    </div>
                    <Button as-child class="w-full md:w-auto">
                        <Link :href="item.action_url"
                            >Open Application<ArrowRight
                        /></Link>
                    </Button>
                </article>
            </section>

            <nav
                v-if="workItems.links.length > 3"
                class="flex flex-wrap gap-1"
                aria-label="Inbox pages"
            >
                <Link
                    v-for="link in workItems.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    class="rounded border px-3 py-2 text-sm"
                    :class="{
                        'bg-primary text-primary-foreground': link.active,
                        'pointer-events-none opacity-40': link.url === null,
                    }"
                >
                    {{ paginationLabel(link.label) }}
                </Link>
            </nav>
        </main>
    </AppLayout>
</template>
