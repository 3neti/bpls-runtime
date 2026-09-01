<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Building2,
    Download,
    FileCheck2,
    FileText,
    ReceiptText,
    UserRound,
} from '@lucide/vue';
import { computed } from 'vue';
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
type AssessmentCharge = {
    id: number;
    code: string;
    name: string;
    category: string;
    amount_cents: number;
};
type AssessmentChargeGroup = {
    key: string;
    label: string;
    subtotal_amount_cents: number;
    charges: AssessmentCharge[];
};
type Payment = {
    id: number;
    status: string;
    channel: string;
    method: string;
    amount_cents: number;
    received_at: string;
    receipt: {
        id: number;
        status: string;
        receipt_number: string;
        amount_cents: number;
        issued_at: string;
    } | null;
};
type Payable = {
    id: number;
    status: string;
    payment_mode: string;
    due_on: string | null;
    total_amount_cents: number;
    paid_amount_cents: number;
    amount_due_cents: number;
    payments: Payment[];
};
type BusinessApplication = {
    id: number;
    citizen_label: string;
    record_reference: string;
    official_application_number: string | null;
    tracking_reference: string | null;
    type: string;
    status: string;
    application_year: number;
    designation: 'current' | 'historical';
    saved_at: string | null;
    lines_of_business: BusinessActivity[];
    assessment: {
        id: number;
        sequence: number;
        status: string;
        citizen_status: string;
        total_amount_cents: number;
        assessed_at: string | null;
        charge_groups: AssessmentChargeGroup[];
    } | null;
    payable: Payable | null;
    permit: {
        issuance_status: string;
        release_status: string;
        status_label: string;
        issued_at: string | null;
        released_at: string | null;
        artifact: null;
        clearances: { completed: number; total: number };
        statement: string;
    };
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
const registrationFacts = computed(() =>
    [
        ['Municipal owner', props.business.owner.name],
        ['Registration number', props.business.registration_number],
        ['Ownership type', props.business.ownership_type],
        ['Organization name', props.business.organization_name],
        ['Address', props.business.address],
        ['Barangay', props.business.barangay],
        ['Registered on', props.business.registered_on],
        ['Started on', props.business.started_on],
        ['Established on', props.business.established_on],
        ['Business email', props.business.email],
        ['Contact number', props.business.contact_number],
    ].filter((fact): fact is [string, string] => Boolean(fact[1])),
);

setLayoutProps({ breadcrumbs });

function humanize(value: string): string {
    return value.replaceAll('_', ' ').replaceAll('-', ' ');
}

function titleCase(value: string): string {
    return humanize(value).replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function pesos(amountCents: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amountCents / 100);
}

function date(value: string): string {
    return new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium' }).format(
        new Date(value.includes('T') ? value : `${value}T00:00:00`),
    );
}

function fileSize(bytes: number): string {
    return `${new Intl.NumberFormat('en-PH', { maximumFractionDigits: 1 }).format(bytes / 1024)} KB`;
}
</script>

<template>
    <div class="contents">
        <Head :title="business.name" />

        <main class="flex h-full flex-1 flex-col gap-8 p-4 sm:p-6">
            <section class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 space-y-1">
                    <p class="text-sm font-medium text-muted-foreground">
                        My business
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
                    <Link :href="ProfileController()"
                        ><ArrowLeft /> My Businesses</Link
                    >
                </Button>
            </section>

            <section
                v-if="currentApplication"
                data-testid="citizen-business-current-activity"
                class="grid gap-5 rounded-xl bg-primary/5 p-5 ring-1 ring-primary/15 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
            >
                <div class="min-w-0 space-y-3">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-wide text-primary uppercase"
                        >
                            Current Permit Activity
                        </p>
                        <h2 class="mt-1 text-xl font-semibold text-foreground">
                            {{ currentApplication.citizen_label }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{ currentApplication.record_reference }} ·
                            {{ titleCase(currentApplication.status) }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Badge variant="secondary"
                            >Application in progress</Badge
                        >
                        <Badge
                            v-if="currentApplication.assessment"
                            variant="outline"
                        >
                            Assessment
                            {{
                                humanize(
                                    currentApplication.assessment
                                        .citizen_status,
                                )
                            }}
                        </Badge>
                        <Badge
                            v-if="currentApplication.payable"
                            variant="outline"
                        >
                            Payable
                            {{ humanize(currentApplication.payable.status) }}
                        </Badge>
                        <Badge variant="outline">{{
                            currentApplication.permit.status_label
                        }}</Badge>
                    </div>
                </div>
                <Button as-child>
                    <Link :href="showPermitApplication(currentApplication.id)">
                        View current application <ArrowRight />
                    </Link>
                </Button>
            </section>

            <section class="grid gap-3" aria-labelledby="permits-applications">
                <div>
                    <h2
                        id="permits-applications"
                        class="text-lg font-semibold text-foreground"
                    >
                        Permits &amp; Applications
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Each application is separate from its Assessment,
                        Payable, and permit record.
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
                    <div
                        class="grid gap-4 p-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center"
                    >
                        <div class="min-w-0 space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3
                                    class="text-lg font-semibold text-foreground"
                                >
                                    {{ permitApplication.citizen_label }}
                                </h3>
                                <Badge
                                    :variant="
                                        permitApplication.designation ===
                                        'current'
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{
                                        titleCase(permitApplication.designation)
                                    }}
                                </Badge>
                            </div>
                            <p class="text-sm font-medium text-foreground">
                                {{ permitApplication.record_reference }}
                            </p>
                            <p
                                v-if="
                                    permitApplication.official_application_number
                                "
                                class="text-xs text-muted-foreground"
                            >
                                Official application number
                                {{
                                    permitApplication.official_application_number
                                }}
                            </p>
                            <p
                                v-if="permitApplication.tracking_reference"
                                class="text-xs break-all text-muted-foreground"
                            >
                                Tracking reference
                                {{ permitApplication.tracking_reference }}
                            </p>
                        </div>
                        <div
                            class="flex flex-wrap gap-2 lg:max-w-md lg:justify-end"
                        >
                            <Badge variant="outline"
                                >Application:
                                {{ titleCase(permitApplication.status) }}</Badge
                            >
                            <Badge
                                v-if="permitApplication.assessment"
                                variant="outline"
                            >
                                Assessment:
                                {{
                                    titleCase(
                                        permitApplication.assessment
                                            .citizen_status,
                                    )
                                }}
                            </Badge>
                            <Badge
                                v-if="permitApplication.payable"
                                variant="outline"
                            >
                                Payable:
                                {{
                                    titleCase(permitApplication.payable.status)
                                }}
                            </Badge>
                            <Badge variant="outline">{{
                                permitApplication.permit.status_label
                            }}</Badge>
                        </div>
                    </div>
                    <footer
                        class="flex flex-wrap items-center justify-between gap-3 border-t border-sidebar-border/70 p-4 dark:border-sidebar-border"
                    >
                        <p class="text-xs text-muted-foreground">
                            {{ permitApplication.permit.statement }}
                        </p>
                        <Button as-child variant="outline" size="sm">
                            <Link
                                :href="
                                    showPermitApplication(permitApplication.id)
                                "
                            >
                                View application <ArrowRight />
                            </Link>
                        </Button>
                    </footer>
                </article>
            </section>

            <section class="grid gap-3" aria-labelledby="assessment-payments">
                <div>
                    <h2
                        id="assessment-payments"
                        class="text-lg font-semibold text-foreground"
                    >
                        Assessment &amp; Payments
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Canonical Assessment charges reconciled once against
                        payment and remaining balance.
                    </p>
                </div>
                <article
                    v-for="permitApplication in business.permit_applications"
                    :key="`financial-${permitApplication.id}`"
                    data-testid="citizen-business-financial-reconciliation"
                    :data-application-id="permitApplication.id"
                    class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border"
                >
                    <header
                        class="flex flex-wrap items-center justify-between gap-3 border-b border-sidebar-border/70 p-5 dark:border-sidebar-border"
                    >
                        <div>
                            <p
                                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                Assessment &amp; Payments
                            </p>
                            <h3 class="text-lg font-semibold text-foreground">
                                {{ permitApplication.citizen_label }}
                            </h3>
                        </div>
                        <Badge
                            v-if="permitApplication.assessment"
                            variant="secondary"
                        >
                            Assessment
                            {{
                                titleCase(
                                    permitApplication.assessment.citizen_status,
                                )
                            }}
                        </Badge>
                    </header>

                    <div
                        v-if="permitApplication.assessment"
                        class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_minmax(16rem,0.65fr)]"
                    >
                        <div class="min-w-0">
                            <h4 class="text-sm font-semibold text-foreground">
                                Charge build-up
                            </h4>
                            <div
                                class="mt-3 divide-y divide-sidebar-border/70 rounded-lg border border-sidebar-border/70"
                            >
                                <div
                                    v-for="group in permitApplication.assessment
                                        .charge_groups"
                                    :key="group.key"
                                    class="grid grid-cols-[minmax(0,1fr)_auto] gap-3 p-3 text-sm"
                                >
                                    <div class="min-w-0">
                                        <p
                                            class="font-medium break-words text-foreground"
                                        >
                                            {{ group.label }}
                                        </p>
                                        <p
                                            v-if="group.charges.length > 1"
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ group.charges.length }} immutable
                                            Assessment charges
                                        </p>
                                    </div>
                                    <p
                                        class="font-medium text-foreground tabular-nums"
                                    >
                                        {{ pesos(group.subtotal_amount_cents) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <dl
                            class="grid content-start gap-3 rounded-lg bg-muted/40 p-4"
                        >
                            <div
                                class="flex items-center justify-between gap-4 text-sm"
                            >
                                <dt class="text-muted-foreground">
                                    Total assessed
                                </dt>
                                <dd
                                    class="font-semibold text-foreground tabular-nums"
                                >
                                    {{
                                        pesos(
                                            permitApplication.assessment
                                                .total_amount_cents,
                                        )
                                    }}
                                </dd>
                            </div>
                            <div
                                class="flex items-center justify-between gap-4 text-sm"
                            >
                                <dt class="text-muted-foreground">
                                    Amount paid
                                </dt>
                                <dd
                                    class="font-semibold text-foreground tabular-nums"
                                >
                                    {{
                                        pesos(
                                            permitApplication.payable
                                                ?.paid_amount_cents ?? 0,
                                        )
                                    }}
                                </dd>
                            </div>
                            <div
                                class="flex items-end justify-between gap-4 border-t border-sidebar-border pt-3"
                            >
                                <dt class="font-semibold text-foreground">
                                    Remaining balance
                                </dt>
                                <dd
                                    data-testid="citizen-business-remaining-balance"
                                    class="text-2xl font-semibold tracking-tight text-foreground tabular-nums"
                                >
                                    {{
                                        pesos(
                                            permitApplication.payable
                                                ?.amount_due_cents ?? 0,
                                        )
                                    }}
                                </dd>
                            </div>
                            <Button
                                v-if="permitApplication.payable"
                                as-child
                                variant="outline"
                                class="mt-1 w-full"
                            >
                                <Link
                                    :href="
                                        showPaymentSchedule(
                                            permitApplication.payable.id,
                                        )
                                    "
                                >
                                    View Payable <ArrowRight />
                                </Link>
                            </Button>
                        </dl>
                    </div>
                    <div
                        v-else
                        class="flex items-center gap-3 p-5 text-sm text-muted-foreground"
                    >
                        <ReceiptText class="size-5" aria-hidden="true" /> No
                        canonical Assessment is recorded for this application.
                    </div>

                    <div
                        v-if="permitApplication.payable?.payments.length"
                        class="border-t border-sidebar-border/70 p-5 dark:border-sidebar-border"
                    >
                        <h4 class="text-sm font-semibold text-foreground">
                            Payment &amp; receipt history
                        </h4>
                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                            <div
                                v-for="payment in permitApplication.payable
                                    .payments"
                                :key="payment.id"
                                class="rounded-lg border border-sidebar-border/70 p-3 text-sm"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div>
                                        <p class="font-medium text-foreground">
                                            {{ pesos(payment.amount_cents) }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ titleCase(payment.method) }} ·
                                            {{ date(payment.received_at) }}
                                        </p>
                                    </div>
                                    <Badge variant="outline">{{
                                        titleCase(payment.status)
                                    }}</Badge>
                                </div>
                                <p
                                    v-if="payment.receipt"
                                    class="mt-3 text-xs text-muted-foreground"
                                >
                                    Receipt
                                    {{ payment.receipt.receipt_number }} ·
                                    {{ titleCase(payment.receipt.status) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </article>
            </section>

            <section class="grid gap-3" aria-labelledby="business-activities">
                <div>
                    <h2
                        id="business-activities"
                        class="text-lg font-semibold text-foreground"
                    >
                        Business Activities
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Declared lines of business remain grouped by their
                        application year.
                    </p>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <article
                        v-for="permitApplication in business.permit_applications"
                        :key="`activities-${permitApplication.id}`"
                        class="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border"
                    >
                        <h3 class="font-semibold text-foreground">
                            {{ permitApplication.citizen_label }}
                        </h3>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <Badge
                                v-for="activity in permitApplication.lines_of_business"
                                :key="`${permitApplication.id}-${activity.code ?? activity.name}`"
                                variant="outline"
                            >
                                {{ activity.name }}
                            </Badge>
                            <span
                                v-if="
                                    permitApplication.lines_of_business
                                        .length === 0
                                "
                                class="text-sm text-muted-foreground"
                            >
                                No declared activities are recorded.
                            </span>
                        </div>
                    </article>
                </div>
            </section>

            <section
                class="grid gap-3"
                aria-labelledby="business-registration"
                data-testid="citizen-business-detail"
            >
                <div>
                    <h2
                        id="business-registration"
                        class="text-lg font-semibold text-foreground"
                    >
                        Business Registration
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Existing municipal business facts only; missing
                        registration data is not inferred.
                    </p>
                </div>
                <div
                    class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
                >
                    <header
                        class="flex flex-wrap items-center gap-4 border-b border-sidebar-border/70 p-5 dark:border-sidebar-border"
                    >
                        <div class="rounded-lg bg-primary/10 p-3 text-primary">
                            <Building2 class="size-6" aria-hidden="true" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p
                                class="font-semibold break-words text-foreground"
                            >
                                {{ business.name }}
                            </p>
                            <Link
                                :href="CitizenIdentityController()"
                                class="inline-flex max-w-full items-center gap-2 text-sm text-muted-foreground underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
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
                    </header>
                    <dl
                        v-if="registrationFacts.length"
                        class="grid gap-px bg-sidebar-border/70 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <div
                            v-for="fact in registrationFacts"
                            :key="fact[0]"
                            class="bg-background p-5"
                        >
                            <dt class="text-xs text-muted-foreground">
                                {{ fact[0] }}
                            </dt>
                            <dd
                                class="mt-1 text-sm break-words text-foreground"
                            >
                                {{
                                    fact[0].endsWith(' on')
                                        ? date(fact[1])
                                        : fact[0] === 'Ownership type'
                                          ? titleCase(fact[1])
                                          : fact[1]
                                }}
                            </dd>
                        </div>
                    </dl>
                    <div v-else class="p-5 text-sm text-muted-foreground">
                        No additional canonical registration details are
                        recorded for this business.
                    </div>
                </div>
            </section>

            <section
                class="grid gap-3"
                aria-labelledby="documents"
                data-testid="citizen-business-documents-registration"
            >
                <div>
                    <h2
                        id="documents"
                        class="text-lg font-semibold text-foreground"
                    >
                        Documents
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Uploaded evidence and issued permit artifacts appear
                        only when a canonical record exists.
                    </p>
                </div>
                <div
                    v-if="
                        business.documents_and_registration.documents.length ===
                        0
                    "
                    class="flex items-start gap-3 rounded-xl border border-dashed border-sidebar-border p-6 text-sm text-muted-foreground"
                >
                    <FileText
                        class="mt-0.5 size-5 shrink-0"
                        aria-hidden="true"
                    />
                    <p>
                        No canonical uploaded documents or issued permit
                        artifacts are recorded for these applications.
                    </p>
                </div>
                <div v-else class="grid gap-3 md:grid-cols-2">
                    <article
                        v-for="document in business.documents_and_registration
                            .documents"
                        :key="document.id"
                        class="flex min-w-0 items-start gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                    >
                        <FileCheck2
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
                            <p class="text-xs text-muted-foreground">
                                {{ document.application_year }} ·
                                {{ titleCase(document.application_type) }} ·
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
                            >
                                <Download />
                            </a>
                        </Button>
                    </article>
                </div>
            </section>
        </main>
    </div>
</template>
