<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Building2,
    Mail,
    MapPin,
    Phone,
    UserRound,
} from '@lucide/vue';
import BusinessController from '@/actions/App/Http/Controllers/Citizen/BusinessController';
import CitizenIdentityController from '@/actions/App/Http/Controllers/Citizen/CitizenIdentityController';
import ProfileController from '@/actions/App/Http/Controllers/Citizen/ProfileController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';

type IdentityBusiness = {
    id: number;
    name: string;
    trade_name: string | null;
};

defineProps<{
    identity: {
        linked: boolean;
        owner: {
            id: number;
            name: string;
            email: string | null;
            phone: string | null;
            address: string | null;
        } | null;
        businesses: IdentityBusiness[];
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'My Businesses', href: ProfileController() },
    { title: 'Owner Identity', href: CitizenIdentityController() },
];

setLayoutProps({ breadcrumbs });
</script>

<template>
    <div class="contents">
        <Head title="Owner Identity" />

        <main class="flex h-full flex-1 flex-col gap-6 p-4">
            <section class="flex flex-wrap items-start justify-between gap-4">
                <div class="space-y-1">
                    <p class="text-sm font-medium text-muted-foreground">
                        Citizen Profile
                    </p>
                    <h1 class="text-2xl font-semibold text-foreground">
                        Owner Identity
                    </h1>
                    <p
                        class="max-w-2xl text-sm leading-6 text-muted-foreground"
                    >
                        Canonical owner facts linked to this portal account.
                    </p>
                </div>
                <Button as-child variant="outline">
                    <Link :href="ProfileController()">
                        <ArrowLeft />
                        My Businesses
                    </Link>
                </Button>
            </section>

            <section
                v-if="!identity.linked"
                data-testid="citizen-identity-unlinked"
                class="grid gap-5 rounded-xl border border-dashed border-sidebar-border bg-background p-6 sm:grid-cols-[auto_1fr] sm:items-start"
            >
                <div class="rounded-full bg-muted p-3 text-muted-foreground">
                    <UserRound class="size-6" aria-hidden="true" />
                </div>
                <div class="space-y-2">
                    <h2 class="font-semibold text-foreground">
                        No business owner is linked to this account
                    </h2>
                    <p
                        class="max-w-2xl text-sm leading-6 text-muted-foreground"
                    >
                        There is no owner identity to display. Submitted records
                        do not create ownership, and registry records cannot be
                        searched or claimed here.
                    </p>
                </div>
            </section>

            <template v-else-if="identity.owner">
                <section
                    data-testid="citizen-identity-detail"
                    class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border"
                >
                    <header
                        class="flex flex-wrap items-center gap-4 border-b border-sidebar-border/70 p-5 dark:border-sidebar-border"
                    >
                        <div
                            class="rounded-full bg-primary/10 p-3 text-primary"
                        >
                            <UserRound class="size-6" aria-hidden="true" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p
                                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                Linked Business Owner
                            </p>
                            <h2
                                class="truncate text-xl font-semibold text-foreground"
                            >
                                {{ identity.owner.name }}
                            </h2>
                        </div>
                        <Badge variant="secondary">
                            {{ identity.businesses.length }}
                            {{
                                identity.businesses.length === 1
                                    ? 'business'
                                    : 'businesses'
                            }}
                        </Badge>
                    </header>

                    <dl class="grid gap-px bg-sidebar-border/70 sm:grid-cols-2">
                        <div class="flex gap-3 bg-background p-5">
                            <Mail
                                class="mt-0.5 size-5 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <div class="min-w-0">
                                <dt class="text-xs text-muted-foreground">
                                    Email
                                </dt>
                                <dd class="text-sm break-words text-foreground">
                                    {{ identity.owner.email ?? 'Not recorded' }}
                                </dd>
                            </div>
                        </div>
                        <div class="flex gap-3 bg-background p-5">
                            <Phone
                                class="mt-0.5 size-5 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <div class="min-w-0">
                                <dt class="text-xs text-muted-foreground">
                                    Phone
                                </dt>
                                <dd class="text-sm break-words text-foreground">
                                    {{ identity.owner.phone ?? 'Not recorded' }}
                                </dd>
                            </div>
                        </div>
                        <div class="flex gap-3 bg-background p-5 sm:col-span-2">
                            <MapPin
                                class="mt-0.5 size-5 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <div class="min-w-0">
                                <dt class="text-xs text-muted-foreground">
                                    Address
                                </dt>
                                <dd class="text-sm break-words text-foreground">
                                    {{
                                        identity.owner.address ?? 'Not recorded'
                                    }}
                                </dd>
                            </div>
                        </div>
                    </dl>
                </section>

                <section class="grid gap-3" aria-labelledby="owned-businesses">
                    <div>
                        <h2
                            id="owned-businesses"
                            class="text-lg font-semibold text-foreground"
                        >
                            Owned businesses
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Businesses reached through the explicit owner link.
                        </p>
                    </div>

                    <div
                        v-if="identity.businesses.length === 0"
                        class="rounded-xl border border-dashed border-sidebar-border p-6 text-sm text-muted-foreground"
                    >
                        No businesses are recorded for this owner.
                    </div>

                    <Link
                        v-for="business in identity.businesses"
                        v-else
                        :key="business.id"
                        :href="BusinessController(business.id)"
                        data-testid="citizen-identity-business-link"
                        class="group flex items-center gap-4 rounded-xl border border-sidebar-border/70 bg-background p-4 transition-colors hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none dark:border-sidebar-border"
                    >
                        <div
                            class="rounded-lg bg-muted p-2.5 text-muted-foreground"
                        >
                            <Building2 class="size-5" aria-hidden="true" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium text-foreground">
                                {{ business.name }}
                            </p>
                            <p
                                v-if="business.trade_name"
                                class="truncate text-sm text-muted-foreground"
                            >
                                Trading as {{ business.trade_name }}
                            </p>
                        </div>
                        <ArrowRight
                            class="size-5 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                            aria-hidden="true"
                        />
                    </Link>
                </section>
            </template>
        </main>
    </div>
</template>
