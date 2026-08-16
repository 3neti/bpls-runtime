<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { Bell, Check, ExternalLink } from '@lucide/vue';
import {
    index,
    update,
} from '@/actions/App/Http/Controllers/Citizen/NotificationController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';

type CitizenNotification = {
    id: string;
    kind: string;
    title: string;
    message: string;
    business_name: string | null;
    tracking_reference: string | null;
    received_at: string | null;
    read_at: string | null;
    permit_application_url: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

defineProps<{
    notifications: {
        data: CitizenNotification[];
        links: PaginationLink[];
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Notices',
        href: index(),
    },
];

setLayoutProps({ breadcrumbs });

function displayDate(value: string | null): string {
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
        <Head title="Notices" />

        <main class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <section>
                <h1 class="text-xl font-semibold text-foreground">Notices</h1>
                <p class="text-sm text-muted-foreground">
                    Updates recorded for your permit applications.
                </p>
            </section>

            <section
                v-if="notifications.data.length === 0"
                class="border border-dashed border-sidebar-border p-8 text-center"
                data-testid="citizen-notifications-empty"
            >
                <Bell class="mx-auto mb-3 size-6 text-muted-foreground" />
                <p class="font-medium text-foreground">No notices recorded</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Application updates will appear here when they are recorded.
                </p>
            </section>

            <section v-else class="grid gap-3">
                <article
                    v-for="notification in notifications.data"
                    :key="notification.id"
                    class="grid gap-4 rounded-lg border border-sidebar-border/70 bg-background p-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-start dark:border-sidebar-border"
                    data-testid="citizen-notification"
                    :data-notification-id="notification.id"
                    :data-notification-kind="notification.kind"
                    :data-tracking-reference="notification.tracking_reference"
                    :data-read="notification.read_at !== null"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-medium text-foreground">
                                {{ notification.title }}
                            </h2>
                            <Badge
                                :variant="
                                    notification.read_at
                                        ? 'outline'
                                        : 'secondary'
                                "
                            >
                                {{ notification.read_at ? 'Read' : 'New' }}
                            </Badge>
                        </div>
                        <p class="mt-2 text-sm text-foreground">
                            {{ notification.message }}
                        </p>
                        <dl
                            class="mt-3 grid gap-1 text-sm text-muted-foreground"
                        >
                            <div
                                v-if="notification.business_name"
                                class="grid gap-0.5 sm:grid-cols-[max-content_minmax(0,1fr)] sm:gap-2"
                            >
                                <dt class="font-medium text-foreground">
                                    Business
                                </dt>
                                <dd class="min-w-0 break-words">
                                    {{ notification.business_name }}
                                </dd>
                            </div>
                            <div
                                v-if="notification.tracking_reference"
                                class="grid gap-0.5 sm:grid-cols-[max-content_minmax(0,1fr)] sm:gap-2"
                            >
                                <dt class="font-medium text-foreground">
                                    Submission reference
                                </dt>
                                <dd class="font-mono text-xs break-all">
                                    {{ notification.tracking_reference }}
                                </dd>
                            </div>
                        </dl>
                        <p class="mt-3 text-xs text-muted-foreground">
                            {{ displayDate(notification.received_at) }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2 md:justify-end">
                        <Button
                            v-if="notification.permit_application_url"
                            as-child
                            variant="outline"
                            size="sm"
                        >
                            <Link :href="notification.permit_application_url">
                                <ExternalLink />
                                View application
                            </Link>
                        </Button>
                        <Button
                            v-if="notification.read_at === null"
                            as-child
                            variant="secondary"
                            size="sm"
                        >
                            <Link
                                :href="update(notification.id)"
                                method="patch"
                                as="button"
                            >
                                <Check />
                                Mark read
                            </Link>
                        </Button>
                    </div>
                </article>
            </section>

            <nav
                v-if="notifications.links.length > 3"
                class="flex flex-wrap gap-2"
                aria-label="Notice pages"
            >
                <template v-for="link in notifications.links" :key="link.label">
                    <Button
                        v-if="link.url"
                        as-child
                        :variant="link.active ? 'default' : 'outline'"
                        size="sm"
                    >
                        <Link :href="link.url" preserve-scroll>
                            {{ paginationLabel(link.label) }}
                        </Link>
                    </Button>
                    <Button v-else disabled variant="outline" size="sm">
                        {{ paginationLabel(link.label) }}
                    </Button>
                </template>
            </nav>
        </main>
    </div>
</template>
