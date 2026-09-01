<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import {
    ArrowRight,
    Building2,
    CircleDollarSign,
    FilePlus2,
    FileText,
    UserRound,
} from '@lucide/vue';
import BusinessController from '@/actions/App/Http/Controllers/Citizen/BusinessController';
import CitizenIdentityController from '@/actions/App/Http/Controllers/Citizen/CitizenIdentityController';
import {
    create as createPermitApplication,
    show as showPermitApplication,
} from '@/actions/App/Http/Controllers/Citizen/PermitApplicationController';
import ProfileController from '@/actions/App/Http/Controllers/Citizen/ProfileController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';

type CitizenPermitApplication = {
    id: number;
    display_reference: string;
    type: string;
    status: string;
    application_year: number;
    saved_at: string | null;
    lines_of_business: string[];
    payable: {
        status: string;
        amount_due_cents: number;
    } | null;
};

type CitizenBusiness = {
    id: number;
    name: string;
    trade_name: string | null;
    application_count: number;
    current_application: {
        id: number;
        type: string;
        status: string;
        application_year: number;
    } | null;
    amount_due: CitizenPermitApplication['payable'];
    permit_applications: CitizenPermitApplication[];
};

defineProps<{
    profile: {
        linked: boolean;
        owner: {
            id: number;
            name: string;
        } | null;
        businesses: CitizenBusiness[];
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'My Businesses',
        href: ProfileController(),
    },
];

setLayoutProps({ breadcrumbs });

function sentenceCase(value: string): string {
    return value.replaceAll('_', ' ');
}

function pesos(amountCents: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amountCents / 100);
}
</script>

<template>
    <div class="contents">
        <Head title="My Businesses" />

        <main class="flex h-full flex-1 flex-col gap-6 p-4">
            <section class="flex flex-wrap items-start justify-between gap-4">
                <div class="space-y-1">
                    <p class="text-sm font-medium text-muted-foreground">
                        Citizen Profile
                    </p>
                    <h1 class="text-2xl font-semibold text-foreground">
                        My Businesses
                    </h1>
                    <p
                        class="max-w-2xl text-sm leading-6 text-muted-foreground"
                    >
                        Businesses and permit applications visible through your
                        municipality-linked owner record.
                    </p>
                </div>
                <Button as-child>
                    <Link :href="createPermitApplication()">
                        <FilePlus2 />
                        New Permit Draft
                    </Link>
                </Button>
            </section>

            <section
                v-if="!profile.linked"
                data-testid="citizen-profile-unlinked"
                class="grid gap-5 rounded-xl border border-dashed border-sidebar-border bg-background p-6 sm:grid-cols-[auto_1fr] sm:items-start"
            >
                <div class="rounded-full bg-muted p-3 text-muted-foreground">
                    <UserRound class="size-6" aria-hidden="true" />
                </div>
                <div class="space-y-3">
                    <div class="space-y-1">
                        <h2 class="font-semibold text-foreground">
                            No business owner is linked to this account
                        </h2>
                        <p
                            class="max-w-2xl text-sm leading-6 text-muted-foreground"
                        >
                            My Businesses is empty because this portal account
                            has no established Business Owner relationship.
                            Existing registry records cannot be searched or
                            claimed from this screen.
                        </p>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        You may start a new permit draft when you are ready to
                        provide new owner and business information.
                    </p>
                </div>
            </section>

            <template v-else>
                <section
                    data-testid="citizen-profile-owner"
                    class="flex flex-wrap items-center gap-4 rounded-xl border border-sidebar-border/70 bg-background p-5 dark:border-sidebar-border"
                >
                    <div class="rounded-full bg-primary/10 p-3 text-primary">
                        <UserRound class="size-6" aria-hidden="true" />
                    </div>
                    <div class="min-w-0">
                        <p
                            class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            Linked Business Owner
                        </p>
                        <h2 class="truncate text-lg font-semibold">
                            <Link
                                :href="CitizenIdentityController()"
                                class="text-foreground underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                {{ profile.owner?.name }}
                            </Link>
                        </h2>
                    </div>
                    <Badge variant="secondary" class="sm:ml-auto">
                        {{ profile.businesses.length }}
                        {{
                            profile.businesses.length === 1
                                ? 'business'
                                : 'businesses'
                        }}
                    </Badge>
                </section>

                <section
                    v-if="profile.businesses.length === 0"
                    data-testid="citizen-profile-no-businesses"
                    class="grid justify-items-center gap-3 rounded-xl border border-dashed border-sidebar-border p-8 text-center"
                >
                    <Building2
                        class="size-8 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <div>
                        <h2 class="font-semibold text-foreground">
                            No businesses recorded for this owner
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Permit applications will appear here after a
                            business record exists.
                        </p>
                    </div>
                </section>

                <section v-else class="grid gap-4" aria-label="My Businesses">
                    <article
                        v-for="business in profile.businesses"
                        :key="business.id"
                        data-testid="citizen-business-card"
                        :data-business-id="business.id"
                        class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border"
                    >
                        <header
                            class="flex flex-wrap items-center gap-4 border-b border-sidebar-border/70 p-5 dark:border-sidebar-border"
                        >
                            <div
                                class="rounded-lg bg-muted p-2.5 text-muted-foreground"
                            >
                                <Building2 class="size-5" aria-hidden="true" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h2 class="truncate text-lg font-semibold">
                                    <Link
                                        :href="BusinessController(business.id)"
                                        class="text-foreground underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        {{ business.name }}
                                    </Link>
                                </h2>
                                <p
                                    v-if="business.trade_name"
                                    class="truncate text-sm text-muted-foreground"
                                >
                                    Trading as {{ business.trade_name }}
                                </p>
                            </div>
                            <Badge variant="outline">
                                {{ business.application_count }}
                                {{
                                    business.application_count === 1
                                        ? 'application'
                                        : 'applications'
                                }}
                            </Badge>
                            <Button as-child variant="ghost" size="sm">
                                <Link :href="BusinessController(business.id)">
                                    Business details
                                    <ArrowRight />
                                </Link>
                            </Button>
                        </header>

                        <div
                            v-if="business.current_application"
                            class="grid gap-4 border-b border-sidebar-border/70 bg-primary/5 p-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center dark:border-sidebar-border"
                        >
                            <div>
                                <p
                                    class="text-xs font-semibold tracking-wide text-primary uppercase"
                                >
                                    Current permit activity
                                </p>
                                <p
                                    class="font-semibold text-foreground capitalize"
                                >
                                    {{
                                        business.current_application
                                            .application_year
                                    }}
                                    ·
                                    {{
                                        sentenceCase(
                                            business.current_application.type,
                                        )
                                    }}
                                </p>
                                <p
                                    class="text-sm text-muted-foreground capitalize"
                                >
                                    {{
                                        sentenceCase(
                                            business.current_application.status,
                                        )
                                    }}
                                </p>
                            </div>
                            <div
                                v-if="business.amount_due"
                                class="sm:text-right"
                            >
                                <p
                                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                >
                                    Amount Due
                                </p>
                                <p
                                    class="text-2xl font-semibold text-foreground"
                                >
                                    {{
                                        pesos(
                                            business.amount_due
                                                .amount_due_cents,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="business.permit_applications.length === 0"
                            class="flex items-center gap-3 p-5 text-sm text-muted-foreground"
                        >
                            <FileText class="size-5" aria-hidden="true" />
                            No permit applications recorded for this business.
                        </div>

                        <div
                            v-else
                            class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                        >
                            <div
                                v-for="permitApplication in business.permit_applications"
                                :key="permitApplication.id"
                                data-testid="citizen-business-application"
                                :data-application-id="permitApplication.id"
                                class="grid gap-4 p-5 lg:grid-cols-[minmax(0,1fr)_auto_auto] lg:items-center"
                            >
                                <div class="min-w-0 space-y-2">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <p class="font-medium text-foreground">
                                            {{
                                                permitApplication.display_reference
                                            }}
                                        </p>
                                        <Badge
                                            variant="secondary"
                                            class="capitalize"
                                        >
                                            {{
                                                sentenceCase(
                                                    permitApplication.type,
                                                )
                                            }}
                                        </Badge>
                                        <Badge
                                            variant="outline"
                                            class="capitalize"
                                        >
                                            {{
                                                sentenceCase(
                                                    permitApplication.status,
                                                )
                                            }}
                                        </Badge>
                                    </div>
                                    <p class="text-sm text-muted-foreground">
                                        <span
                                            v-if="
                                                permitApplication
                                                    .lines_of_business.length >
                                                0
                                            "
                                        >
                                            {{
                                                permitApplication.lines_of_business.join(
                                                    ' + ',
                                                )
                                            }}
                                        </span>
                                        <span v-else
                                            >No declared lines of business</span
                                        >
                                    </p>
                                </div>

                                <Badge
                                    v-if="permitApplication.payable"
                                    variant="secondary"
                                    class="justify-self-start"
                                >
                                    <CircleDollarSign /> Payable
                                </Badge>

                                <Button as-child variant="outline" size="sm">
                                    <Link
                                        :href="
                                            showPermitApplication(
                                                permitApplication.id,
                                            )
                                        "
                                    >
                                        View application
                                        <ArrowRight />
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </article>
                </section>
            </template>
        </main>
    </div>
</template>
