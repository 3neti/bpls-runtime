<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { Eye, FilePlus2 } from '@lucide/vue';
import {
    create,
    index,
    show,
} from '@/actions/App/Http/Controllers/Citizen/PermitApplicationController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';

type PermitApplicationRow = {
    id: number;
    display_reference: string;
    application_number: string | null;
    type: string;
    status: string;
    business_name: string;
    activity_count: number;
    saved_at: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

defineProps<{
    permitApplications: {
        data: PermitApplicationRow[];
        links: PaginationLink[];
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'My Permit Applications',
        href: index(),
    },
];

setLayoutProps({ breadcrumbs });

function savedDate(value: string | null): string {
    if (value === null) {
        return 'Not recorded';
    }

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function paginationLabel(label: string): string {
    return label.replace('&laquo;', '‹').replace('&raquo;', '›');
}
</script>

<template>
    <div class="contents">
        <Head title="My Permit Applications" />

        <main class="flex h-full flex-1 flex-col gap-4 p-4">
            <section class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        My Permit Applications
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Saved applications associated with your account.
                    </p>
                </div>
                <Button as-child>
                    <Link :href="create()">
                        <FilePlus2 />
                        New Draft
                    </Link>
                </Button>
            </section>

            <section
                v-if="permitApplications.data.length === 0"
                class="border border-dashed border-sidebar-border p-8 text-center"
            >
                <p class="font-medium text-foreground">
                    No permit applications saved
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Start a new draft when your business information is ready.
                </p>
            </section>

            <section v-else class="grid gap-3">
                <article
                    v-for="permitApplication in permitApplications.data"
                    :key="permitApplication.id"
                    data-testid="citizen-permit-application-row"
                    :data-application-id="permitApplication.id"
                    :data-application-status="permitApplication.status"
                    class="grid gap-3 rounded-lg border border-sidebar-border/70 bg-background p-4 md:grid-cols-[minmax(0,1fr)_auto_auto] md:items-center dark:border-sidebar-border"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="truncate font-medium text-foreground">
                                {{ permitApplication.business_name }}
                            </h2>
                            <Badge variant="secondary" class="capitalize">
                                {{ permitApplication.status.replace('_', ' ') }}
                            </Badge>
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ permitApplication.display_reference }} ·
                            {{ permitApplication.activity_count }}
                            {{
                                permitApplication.activity_count === 1
                                    ? 'activity'
                                    : 'activities'
                            }}
                        </p>
                    </div>
                    <p class="text-xs text-muted-foreground md:text-right">
                        Saved {{ savedDate(permitApplication.saved_at) }}
                    </p>
                    <Button as-child variant="outline" size="sm">
                        <Link :href="show(permitApplication.id)">
                            <Eye />
                            Review
                        </Link>
                    </Button>
                </article>
            </section>

            <nav
                v-if="permitApplications.links.length > 3"
                class="flex flex-wrap justify-center gap-1"
                aria-label="Permit application pages"
            >
                <Button
                    v-for="link in permitApplications.links"
                    :key="link.label"
                    as-child
                    size="sm"
                    :variant="link.active ? 'default' : 'outline'"
                    :disabled="link.url === null"
                >
                    <Link v-if="link.url" :href="link.url">
                        <span>{{ paginationLabel(link.label) }}</span>
                    </Link>
                    <span v-else>{{ paginationLabel(link.label) }}</span>
                </Button>
            </nav>
        </main>
    </div>
</template>
