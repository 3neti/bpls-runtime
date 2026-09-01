<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Building2,
    CircleDollarSign,
    Download,
    FileText,
    MapPin,
    ReceiptText,
    UserRound,
} from '@lucide/vue';
import BusinessController from '@/actions/App/Http/Controllers/Citizen/BusinessController';
import CitizenIdentityController from '@/actions/App/Http/Controllers/Citizen/CitizenIdentityController';
import { show as showPaymentSchedule } from '@/actions/App/Http/Controllers/Citizen/PaymentScheduleController';
import { show as showPermitApplication } from '@/actions/App/Http/Controllers/Citizen/PermitApplicationController';
import { download as downloadPermitDocument } from '@/actions/App/Http/Controllers/Citizen/PermitApplicationDocumentController';
import ProfileController from '@/actions/App/Http/Controllers/Citizen/ProfileController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';

type BusinessActivity = { code: string | null; name: string };
type Payable = {
    id: number;
    status: string;
    total_amount_cents: number;
    paid_amount_cents: number;
    amount_due_cents: number;
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
    payable: Payable | null;
};
type BusinessDocument = {
    id: number;
    permit_application_id: number;
    application_year: number;
    application_type: string;
    label: string;
    original_name: string;
    mime_type: string;
    size_bytes: number;
    uploaded_at: string;
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
        current_permit_application_id: number | null;
        amount_due: Payable | null;
        permit_applications: BusinessApplication[];
        documents_and_registration: {
            source: string;
            documents: BusinessDocument[];
            statement: string;
        };
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'My Businesses', href: ProfileController() },
    { title: props.business.name, href: BusinessController(props.business.id) },
];
const currentApplication = props.business.permit_applications[0] ?? null;

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
    return value === null
        ? 'Not recorded'
        : new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium' }).format(
              new Date(`${value}T00:00:00`),
          );
}

function fileSize(bytes: number): string {
    return `${new Intl.NumberFormat('en-PH', { maximumFractionDigits: 1 }).format(bytes / 1024)} KB`;
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
                <Button as-child variant="outline"
                    ><Link :href="ProfileController()"
                        ><ArrowLeft /> My Businesses</Link
                    ></Button
                >
            </section>

            <section
                v-if="currentApplication"
                data-testid="citizen-business-current-activity"
                class="grid gap-4 rounded-xl bg-primary/5 p-5 ring-1 ring-primary/15 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
            >
                <div class="min-w-0">
                    <p
                        class="text-xs font-semibold tracking-wide text-primary uppercase"
                    >
                        Current permit activity
                    </p>
                    <p
                        class="mt-1 text-lg font-semibold text-foreground capitalize"
                    >
                        {{ sentenceCase(currentApplication.type) }} ·
                        {{ currentApplication.application_year }}
                    </p>
                    <p class="text-sm text-muted-foreground capitalize">
                        {{ sentenceCase(currentApplication.status) }}
                    </p>
                </div>
                <div v-if="business.amount_due" class="sm:text-right">
                    <p
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Amount Due
                    </p>
                    <p
                        data-testid="citizen-business-amount-due"
                        class="text-3xl font-semibold tracking-tight text-foreground"
                    >
                        {{ pesos(business.amount_due.amount_due_cents) }}
                    </p>
                    <Button as-child variant="link" class="h-auto px-0">
                        <Link
                            :href="showPaymentSchedule(business.amount_due.id)"
                            >View Payable <ArrowRight
                        /></Link>
                    </Button>
                </div>
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
                            /><span class="truncate">{{
                                business.owner.name
                            }}</span>
                        </Link>
                    </div>
                    <Badge variant="secondary"
                        >{{ business.permit_applications.length }} permit
                        applications</Badge
                    >
                </header>

                <dl
                    class="grid gap-px bg-sidebar-border/70 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-for="item in [
                            ['Address', business.address],
                            ['Barangay', business.barangay],
                            [
                                'Ownership type',
                                business.ownership_type
                                    ? sentenceCase(business.ownership_type)
                                    : null,
                            ],
                            ['Organization name', business.organization_name],
                            [
                                'Registration number',
                                business.registration_number,
                            ],
                            ['Registered on', date(business.registered_on)],
                            ['Started on', date(business.started_on)],
                            ['Established on', date(business.established_on)],
                            ['Business email', business.email],
                            ['Contact number', business.contact_number],
                        ]"
                        :key="item[0] ?? ''"
                        class="bg-background p-5"
                    >
                        <dt class="text-xs text-muted-foreground">
                            {{ item[0] }}
                        </dt>
                        <dd class="mt-1 text-sm break-words text-foreground">
                            {{ item[1] ?? 'Not recorded' }}
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
                        Permit activity history
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        New Business Permit and Renewal activity for this one
                        business.
                    </p>
                </div>
                <div
                    v-if="business.permit_applications.length === 0"
                    class="flex items-center gap-3 rounded-xl border border-dashed border-sidebar-border p-6 text-sm text-muted-foreground"
                >
                    <FileText class="size-5" aria-hidden="true" /> No permit
                    applications are recorded for this business.
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
                            <p
                                class="text-lg font-semibold text-foreground capitalize"
                            >
                                {{ permitApplication.application_year }} ·
                                {{ sentenceCase(permitApplication.type) }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ permitApplication.display_reference }}
                            </p>
                        </div>
                        <Badge
                            v-if="
                                permitApplication.id ===
                                business.current_permit_application_id
                            "
                            >Current</Badge
                        >
                        <Badge variant="outline" class="capitalize">{{
                            sentenceCase(permitApplication.status)
                        }}</Badge>
                    </header>
                    <div
                        class="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_auto]"
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
                                    Business activities / LOBs
                                </h3>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Badge
                                    v-for="activity in permitApplication.lines_of_business"
                                    :key="`${permitApplication.id}-${activity.code ?? activity.name}`"
                                    variant="outline"
                                    >{{ activity.name }}</Badge
                                >
                                <span
                                    v-if="
                                        permitApplication.lines_of_business
                                            .length === 0
                                    "
                                    class="text-sm text-muted-foreground"
                                    >No declared activities are recorded.</span
                                >
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge
                                v-if="permitApplication.assessment"
                                variant="secondary"
                                ><ReceiptText /> Assessment recorded</Badge
                            >
                            <Badge
                                v-if="permitApplication.payable"
                                variant="secondary"
                                ><CircleDollarSign /> Payable</Badge
                            >
                        </div>
                    </div>
                    <footer
                        class="flex flex-wrap gap-2 border-t border-sidebar-border/70 p-4 dark:border-sidebar-border"
                    >
                        <Button as-child variant="outline" size="sm"
                            ><Link
                                :href="
                                    showPermitApplication(permitApplication.id)
                                "
                                >View application <ArrowRight /></Link
                        ></Button>
                    </footer>
                </article>
            </section>

            <section
                class="grid gap-3"
                aria-labelledby="documents-registration"
                data-testid="citizen-business-documents-registration"
            >
                <div>
                    <h2
                        id="documents-registration"
                        class="text-lg font-semibold text-foreground"
                    >
                        Documents &amp; Registration
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{ business.documents_and_registration.statement }}
                    </p>
                </div>
                <div
                    v-if="
                        business.documents_and_registration.documents.length ===
                        0
                    "
                    class="flex items-center gap-3 rounded-xl border border-dashed border-sidebar-border p-6 text-sm text-muted-foreground"
                >
                    <FileText class="size-5" aria-hidden="true" /> No canonical
                    uploaded documents are recorded for these applications.
                </div>
                <div v-else class="grid gap-3 md:grid-cols-2">
                    <article
                        v-for="document in business.documents_and_registration
                            .documents"
                        :key="document.id"
                        class="flex min-w-0 items-start gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                    >
                        <FileText
                            class="mt-0.5 size-5 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="font-medium break-words text-foreground">
                                {{ document.label }}
                            </p>
                            <p class="text-sm break-all text-muted-foreground">
                                {{ document.original_name }}
                            </p>
                            <p class="text-xs text-muted-foreground capitalize">
                                {{ document.application_year }}
                                {{ sentenceCase(document.application_type) }} ·
                                {{ fileSize(document.size_bytes) }}
                            </p>
                        </div>
                        <Button as-child variant="ghost" size="icon">
                            <a
                                :href="
                                    downloadPermitDocument({
                                        permitApplication:
                                            document.permit_application_id,
                                        document: document.id,
                                    }).url
                                "
                                :aria-label="`Download ${document.label}`"
                                ><Download
                            /></a>
                        </Button>
                    </article>
                </div>
            </section>
        </main>
    </div>
</template>
