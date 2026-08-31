<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Building2,
    CalendarDays,
    CircleDollarSign,
    FileText,
    MapPin,
    ReceiptText,
    UserRound,
} from '@lucide/vue';
import BusinessController from '@/actions/App/Http/Controllers/Citizen/BusinessController';
import CitizenIdentityController from '@/actions/App/Http/Controllers/Citizen/CitizenIdentityController';
import { show as showPaymentSchedule } from '@/actions/App/Http/Controllers/Citizen/PaymentScheduleController';
import { show as showPermitApplication } from '@/actions/App/Http/Controllers/Citizen/PermitApplicationController';
import ProfileController from '@/actions/App/Http/Controllers/Citizen/ProfileController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';

type BusinessActivity = {
    code: string | null;
    name: string;
};

type BusinessApplication = {
    id: number;
    display_reference: string;
    type: string;
    status: string;
    application_year: number;
    saved_at: string | null;
    lines_of_business: BusinessActivity[];
    assessment: {
        id: number;
        sequence: number;
        status: string;
        total_amount_cents: number;
        assessed_at: string | null;
    } | null;
    payable: {
        id: number;
        status: string;
        total_amount_cents: number;
        paid_amount_cents: number;
        amount_due_cents: number;
    } | null;
};

const props = defineProps<{
    business: {
        id: number;
        name: string;
        trade_name: string | null;
        registration_number: string | null;
        address: string | null;
        barangay: string | null;
        ownership_type: string | null;
        organization_name: string | null;
        contact_number: string | null;
        email: string | null;
        established_on: string | null;
        started_on: string | null;
        registered_on: string | null;
        owner: { id: number; name: string };
        permit_applications: BusinessApplication[];
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'My Businesses', href: ProfileController() },
    { title: props.business.name, href: BusinessController(props.business.id) },
];

setLayoutProps({ breadcrumbs });

function sentenceCase(value: string): string {
    return value.replaceAll('_', ' ').replaceAll('-', ' ');
}

function pesos(amountCents: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amountCents / 100);
}

function date(value: string | null): string {
    if (value === null) {
        return 'Not recorded';
    }

    return new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium' }).format(
        new Date(`${value}T00:00:00`),
    );
}
</script>

<template>
    <div class="contents">
        <Head :title="business.name" />

        <main class="flex h-full flex-1 flex-col gap-6 p-4">
            <section class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 space-y-1">
                    <p class="text-sm font-medium text-muted-foreground">
                        Business Detail
                    </p>
                    <h1
                        class="text-2xl font-semibold break-words text-foreground"
                    >
                        {{ business.name }}
                    </h1>
                    <p
                        v-if="business.trade_name"
                        class="text-sm text-muted-foreground"
                    >
                        Trading as {{ business.trade_name }}
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
                data-testid="citizen-business-detail"
                class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border"
            >
                <header
                    class="flex flex-wrap items-center gap-4 border-b border-sidebar-border/70 p-5 dark:border-sidebar-border"
                >
                    <div class="rounded-lg bg-primary/10 p-3 text-primary">
                        <Building2 class="size-6" aria-hidden="true" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p
                            class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            Canonical business record
                        </p>
                        <Link
                            :href="CitizenIdentityController()"
                            class="inline-flex max-w-full items-center gap-2 text-sm font-medium text-foreground underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <UserRound
                                class="size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <span class="truncate">{{
                                business.owner.name
                            }}</span>
                        </Link>
                    </div>
                    <Badge variant="secondary">
                        {{ business.permit_applications.length }}
                        {{
                            business.permit_applications.length === 1
                                ? 'application'
                                : 'applications'
                        }}
                    </Badge>
                </header>

                <dl
                    class="grid gap-px bg-sidebar-border/70 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div class="bg-background p-5">
                        <dt class="text-xs text-muted-foreground">Address</dt>
                        <dd class="mt-1 text-sm break-words text-foreground">
                            {{ business.address ?? 'Not recorded' }}
                        </dd>
                    </div>
                    <div class="bg-background p-5">
                        <dt class="text-xs text-muted-foreground">Barangay</dt>
                        <dd class="mt-1 text-sm break-words text-foreground">
                            {{ business.barangay ?? 'Not recorded' }}
                        </dd>
                    </div>
                    <div class="bg-background p-5">
                        <dt class="text-xs text-muted-foreground">
                            Ownership type
                        </dt>
                        <dd class="mt-1 text-sm text-foreground capitalize">
                            {{
                                business.ownership_type
                                    ? sentenceCase(business.ownership_type)
                                    : 'Not recorded'
                            }}
                        </dd>
                    </div>
                    <div class="bg-background p-5">
                        <dt class="text-xs text-muted-foreground">
                            Organization name
                        </dt>
                        <dd class="mt-1 text-sm break-words text-foreground">
                            {{ business.organization_name ?? 'Not recorded' }}
                        </dd>
                    </div>
                    <div class="bg-background p-5">
                        <dt class="text-xs text-muted-foreground">
                            Registration number
                        </dt>
                        <dd class="mt-1 text-sm break-all text-foreground">
                            {{ business.registration_number ?? 'Not recorded' }}
                        </dd>
                    </div>
                    <div class="bg-background p-5">
                        <dt class="text-xs text-muted-foreground">
                            Registered on
                        </dt>
                        <dd class="mt-1 text-sm text-foreground">
                            {{ date(business.registered_on) }}
                        </dd>
                    </div>
                    <div class="bg-background p-5">
                        <dt class="text-xs text-muted-foreground">
                            Started on
                        </dt>
                        <dd class="mt-1 text-sm text-foreground">
                            {{ date(business.started_on) }}
                        </dd>
                    </div>
                    <div class="bg-background p-5">
                        <dt class="text-xs text-muted-foreground">
                            Established on
                        </dt>
                        <dd class="mt-1 text-sm text-foreground">
                            {{ date(business.established_on) }}
                        </dd>
                    </div>
                    <div class="bg-background p-5">
                        <dt class="text-xs text-muted-foreground">
                            Business email
                        </dt>
                        <dd class="mt-1 text-sm break-words text-foreground">
                            {{ business.email ?? 'Not recorded' }}
                        </dd>
                    </div>
                    <div class="bg-background p-5">
                        <dt class="text-xs text-muted-foreground">
                            Contact number
                        </dt>
                        <dd class="mt-1 text-sm break-words text-foreground">
                            {{ business.contact_number ?? 'Not recorded' }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="grid gap-3" aria-labelledby="permit-activity">
                <div>
                    <h2
                        id="permit-activity"
                        class="text-lg font-semibold text-foreground"
                    >
                        Permit activity
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Applications, declared activities, Assessment, and
                        Payable truth for this business.
                    </p>
                </div>

                <div
                    v-if="business.permit_applications.length === 0"
                    class="flex items-center gap-3 rounded-xl border border-dashed border-sidebar-border p-6 text-sm text-muted-foreground"
                >
                    <FileText class="size-5" aria-hidden="true" />
                    No permit applications are recorded for this business.
                </div>

                <article
                    v-for="permitApplication in business.permit_applications"
                    v-else
                    :key="permitApplication.id"
                    data-testid="citizen-business-permit-activity"
                    :data-application-id="permitApplication.id"
                    class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border"
                >
                    <header
                        class="flex flex-wrap items-center gap-3 border-b border-sidebar-border/70 p-5 dark:border-sidebar-border"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="font-medium break-words text-foreground">
                                {{ permitApplication.display_reference }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Application year
                                {{ permitApplication.application_year }}
                            </p>
                        </div>
                        <Badge variant="secondary" class="capitalize">
                            {{ sentenceCase(permitApplication.type) }}
                        </Badge>
                        <Badge variant="outline" class="capitalize">
                            {{ sentenceCase(permitApplication.status) }}
                        </Badge>
                    </header>

                    <div
                        class="grid gap-5 p-5 xl:grid-cols-[minmax(0,1fr)_minmax(15rem,0.7fr)]"
                    >
                        <div class="space-y-3">
                            <div class="flex items-center gap-2">
                                <MapPin
                                    class="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <h3
                                    class="text-sm font-semibold text-foreground"
                                >
                                    Lines of business / activities
                                </h3>
                            </div>
                            <div
                                v-if="
                                    permitApplication.lines_of_business.length >
                                    0
                                "
                                class="flex flex-wrap gap-2"
                            >
                                <Badge
                                    v-for="activity in permitApplication.lines_of_business"
                                    :key="`${permitApplication.id}-${activity.code ?? activity.name}`"
                                    variant="outline"
                                >
                                    {{ activity.name }}
                                </Badge>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">
                                No declared activities are recorded.
                            </p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                            <div
                                v-if="permitApplication.assessment"
                                class="flex gap-3 rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                            >
                                <ReceiptText
                                    class="mt-0.5 size-5 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <div>
                                    <p
                                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                    >
                                        Assessment
                                    </p>
                                    <p class="font-semibold text-foreground">
                                        {{
                                            pesos(
                                                permitApplication.assessment
                                                    .total_amount_cents,
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-xs text-muted-foreground capitalize"
                                    >
                                        {{
                                            sentenceCase(
                                                permitApplication.assessment
                                                    .status,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="permitApplication.payable"
                                class="flex gap-3 rounded-lg bg-primary/5 p-4 ring-1 ring-primary/15"
                            >
                                <CircleDollarSign
                                    class="mt-0.5 size-5 text-primary"
                                    aria-hidden="true"
                                />
                                <div>
                                    <p
                                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                    >
                                        Amount Due
                                    </p>
                                    <p
                                        class="text-lg font-semibold text-foreground"
                                    >
                                        {{
                                            pesos(
                                                permitApplication.payable
                                                    .amount_due_cents,
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-xs text-muted-foreground capitalize"
                                    >
                                        Payable ·
                                        {{
                                            sentenceCase(
                                                permitApplication.payable
                                                    .status,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <footer
                        class="flex flex-wrap gap-2 border-t border-sidebar-border/70 p-4 dark:border-sidebar-border"
                    >
                        <Button as-child variant="outline" size="sm">
                            <Link
                                :href="
                                    showPermitApplication(permitApplication.id)
                                "
                            >
                                View application
                                <ArrowRight />
                            </Link>
                        </Button>
                        <Button
                            v-if="permitApplication.payable"
                            as-child
                            variant="ghost"
                            size="sm"
                        >
                            <Link
                                :href="
                                    showPaymentSchedule(
                                        permitApplication.payable.id,
                                    )
                                "
                            >
                                <CalendarDays />
                                View Payable
                            </Link>
                        </Button>
                    </footer>
                </article>
            </section>
        </main>
    </div>
</template>
