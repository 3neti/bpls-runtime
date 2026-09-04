<script setup lang="ts">
import { ExternalLink, QrCode, ReceiptText, ShieldCheck } from '@lucide/vue';
import { computed, ref } from 'vue';

type Task = {
    key: string;
    label: string;
    section: string;
    href: string | null;
};
type ApplicationData = {
    schema_version: string;
    identity: Record<string, any>;
    applicant: Record<string, any>;
    business: Record<string, any>;
    declaration: Record<string, any>;
    routing: Record<string, any>;
    offices: Record<string, any>[];
    financial: Record<string, any>;
    payment: Record<string, any>;
    official_receipts: Record<string, any>[];
    post_payment: Record<string, any>;
    permit: Record<string, any>;
    documents: Record<string, any>[];
    actor_context: {
        actor_label: string;
        role_code: string | null;
        current_tasks: Task[];
    };
    tabs: { key: string; label: string }[];
};

const props = withDefaults(
    defineProps<{
        application: ApplicationData;
        mode?: 'workspace' | 'palette' | 'mobile' | 'print';
        initialTab?: string;
    }>(),
    { mode: 'workspace', initialTab: 'application' },
);

const activeTab = ref(
    props.application.tabs.some((tab) => tab.key === props.initialTab)
        ? props.initialTab
        : 'application',
);
const tasks = computed(() => props.application.actor_context.current_tasks);
const snapshot = computed(() => props.application.declaration.snapshot ?? {});

function money(minor: number | null | undefined): string {
    if (minor === null || minor === undefined) {
        return 'Pending';
    }

    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(minor / 100);
}

function label(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return 'Pending';
    }

    return String(value).replaceAll('_', ' ');
}
</script>

<template>
    <article
        data-testid="executable-application"
        :data-projection-mode="mode"
        class="min-w-0 overflow-hidden rounded-2xl border border-slate-300 bg-[#f6f0df] text-slate-950 shadow-xl dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
    >
        <header
            class="border-b border-slate-300 bg-[#123f72] px-4 py-5 text-white sm:px-6 dark:border-slate-700"
        >
            <div
                class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="min-w-0">
                    <p
                        class="text-xs font-bold tracking-[0.18em] text-sky-200 uppercase"
                    >
                        Executable Business Permit Application
                    </p>
                    <h2
                        class="mt-1 text-2xl font-black tracking-tight break-words sm:text-3xl"
                    >
                        {{ application.business.name }}
                    </h2>
                    <p class="mt-1 text-sm text-slate-200">
                        {{ application.identity.application_year }} ·
                        {{ label(application.identity.type) }} ·
                        {{
                            application.identity.tracking_reference ??
                            'Tracking reference pending'
                        }}
                    </p>
                </div>
                <span
                    class="w-fit rounded-full bg-white/12 px-3 py-1.5 text-xs font-bold uppercase"
                >
                    {{ label(application.identity.status) }}
                </span>
            </div>
        </header>

        <nav
            class="overflow-x-auto border-b border-slate-300 bg-white/80 dark:border-slate-700 dark:bg-slate-900"
            aria-label="Application artifacts"
        >
            <div
                class="flex min-w-max"
                role="tablist"
                aria-label="Application sections"
            >
                <button
                    v-for="(tab, index) in application.tabs"
                    :key="tab.key"
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === tab.key"
                    :data-testid="`application-tab-${tab.key}`"
                    :class="[
                        [
                            'border-sky-500',
                            'border-amber-500',
                            'border-violet-500',
                            'border-emerald-500',
                            'border-rose-500',
                        ][index],
                        activeTab === tab.key
                            ? 'bg-[#f6f0df] text-slate-950 dark:bg-slate-950 dark:text-white'
                            : 'bg-white text-slate-600 dark:bg-slate-900 dark:text-slate-300',
                    ]"
                    class="border-t-4 px-4 py-3 text-sm font-black tracking-wide uppercase outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-inset sm:px-6"
                    @click="activeTab = tab.key"
                >
                    {{ tab.label }}
                </button>
            </div>
        </nav>

        <div
            class="grid min-w-0 gap-5 p-3 sm:p-6 lg:grid-cols-[minmax(0,1fr)_17rem]"
        >
            <section
                class="min-w-0 rounded-xl border border-slate-300 bg-white p-4 sm:p-6 dark:border-slate-700 dark:bg-slate-900"
            >
                <div
                    v-if="activeTab === 'application'"
                    data-testid="application-page-1"
                    class="space-y-5"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-b-2 border-slate-900 pb-3 dark:border-slate-300"
                    >
                        <div>
                            <p class="text-xs font-black uppercase">Page 1</p>
                            <h3 class="text-xl font-black">
                                Applicant Declaration
                            </h3>
                        </div>
                        <span
                            class="rounded bg-emerald-100 px-2.5 py-1 text-xs font-black text-emerald-900 uppercase"
                            >{{ application.declaration.state }}</span
                        >
                    </div>
                    <p
                        class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                    >
                        What the applicant declared. Once submitted, this page
                        is the frozen evidentiary snapshot.
                    </p>
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt
                                class="text-xs font-bold text-slate-500 uppercase"
                            >
                                Applicant
                            </dt>
                            <dd class="mt-1 font-semibold break-words">
                                {{ application.applicant.name }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-bold text-slate-500 uppercase"
                            >
                                Business
                            </dt>
                            <dd class="mt-1 font-semibold break-words">
                                {{ application.business.name }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-bold text-slate-500 uppercase"
                            >
                                Business address
                            </dt>
                            <dd class="mt-1 break-words">
                                {{
                                    application.business.address ??
                                    snapshot.business_address?.street ??
                                    'Pending'
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-bold text-slate-500 uppercase"
                            >
                                Declaration hash
                            </dt>
                            <dd class="mt-1 font-mono text-xs break-all">
                                {{
                                    application.declaration.snapshot_hash ??
                                    'Not frozen'
                                }}
                            </dd>
                        </div>
                    </dl>
                    <div>
                        <h4 class="text-xs font-black uppercase">
                            Declared lines of business
                        </h4>
                        <ul class="mt-2 grid gap-2">
                            <li
                                v-for="line in application.business
                                    .lines_of_business"
                                :key="line.application_line_id"
                                class="rounded-lg bg-slate-100 px-3 py-2 text-sm break-words dark:bg-slate-800"
                            >
                                {{ line.code }} · {{ line.name }}
                            </li>
                        </ul>
                    </div>
                </div>

                <div
                    v-else-if="activeTab === 'processing'"
                    data-testid="application-page-2"
                    class="space-y-5"
                >
                    <div
                        class="border-b-2 border-slate-900 pb-3 dark:border-slate-300"
                    >
                        <p class="text-xs font-black uppercase">Page 2</p>
                        <h3 class="text-xl font-black">Municipal Processing</h3>
                    </div>
                    <p
                        class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                    >
                        A living projection of what the Municipality has done.
                        Canonical actions and records remain authoritative.
                    </p>
                    <div class="rounded-lg bg-sky-50 p-4 dark:bg-sky-950/40">
                        <p
                            class="text-xs font-black text-sky-800 uppercase dark:text-sky-300"
                        >
                            BPLO routing
                        </p>
                        <p class="mt-1 font-semibold">
                            {{ label(application.routing.status) }}
                        </p>
                        <p
                            v-if="application.routing.reason"
                            class="mt-1 text-sm"
                        >
                            {{ application.routing.reason }}
                        </p>
                    </div>
                    <div
                        v-if="application.offices.length"
                        class="grid gap-3 md:grid-cols-2"
                    >
                        <article
                            v-for="office in application.offices"
                            :key="office.code"
                            class="min-w-0 rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                        >
                            <div class="flex flex-wrap justify-between gap-2">
                                <h4 class="font-black">{{ office.label }}</h4>
                                <span class="text-xs font-bold uppercase">{{
                                    label(office.status)
                                }}</span>
                            </div>
                            <ul class="mt-3 grid gap-2 text-sm">
                                <li
                                    v-for="item in office.responsibilities"
                                    :key="item.id"
                                    class="flex min-w-0 justify-between gap-3"
                                >
                                    <span class="min-w-0 break-words">{{
                                        item.label
                                    }}</span
                                    ><span class="shrink-0 font-semibold">{{
                                        money(item.amount_cents)
                                    }}</span>
                                </li>
                            </ul>
                            <p class="mt-3 text-xs text-slate-500">
                                {{ office.paperless_payment_order_count }}
                                paperless payment order(s) ·
                                {{
                                    office.certification?.statement ??
                                    'Certification pending'
                                }}
                            </p>
                        </article>
                    </div>
                    <p
                        v-else
                        class="rounded-lg border border-dashed border-slate-300 p-5 text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300"
                    >
                        Concerned-office work will appear after the canonical
                        BPLO routing determination.
                    </p>
                </div>

                <div v-else-if="activeTab === 'assessment'" class="space-y-5">
                    <div>
                        <p
                            class="text-xs font-black text-violet-700 uppercase dark:text-violet-300"
                        >
                            Frozen financial artifact
                        </p>
                        <h3 class="text-xl font-black">
                            Computation / Assessment Slip
                        </h3>
                    </div>
                    <div
                        v-if="application.financial.assessment"
                        class="space-y-4"
                    >
                        <div
                            class="rounded-lg bg-violet-50 p-4 dark:bg-violet-950/30"
                        >
                            <p class="text-sm">
                                Assessment #{{
                                    application.financial.assessment.sequence
                                }}
                            </p>
                            <p class="mt-1 text-3xl font-black">
                                {{
                                    money(
                                        application.financial.assessment
                                            .total_amount_cents,
                                    )
                                }}
                            </p>
                            <p
                                class="mt-1 font-mono text-[11px] break-all text-slate-500"
                            >
                                PriceReport
                                {{
                                    application.financial.assessment
                                        .price_report_fingerprint
                                }}
                            </p>
                        </div>
                        <div class="grid gap-2">
                            <div
                                v-for="component in application.financial
                                    .price_report?.components ?? []"
                                :key="component.exact_once_key"
                                class="flex min-w-0 justify-between gap-4 border-b border-slate-100 py-2 text-sm dark:border-slate-800"
                            >
                                <span class="min-w-0 break-words">{{
                                    component.label
                                }}</span
                                ><strong class="shrink-0">{{
                                    money(component.resolved_minor)
                                }}</strong>
                            </div>
                        </div>
                        <p class="text-sm">
                            Municipal Treasurer:
                            <strong>{{
                                label(
                                    application.financial.treasurer_decision
                                        ?.action,
                                )
                            }}</strong>
                        </p>
                    </div>
                    <p
                        v-else
                        class="rounded-lg border border-dashed border-slate-300 p-5 text-sm dark:border-slate-700"
                    >
                        Assessment and frozen PriceReport are pending canonical
                        preparation.
                    </p>
                </div>

                <div v-else-if="activeTab === 'payment'" class="space-y-5">
                    <div>
                        <p
                            class="text-xs font-black text-emerald-700 uppercase dark:text-emerald-300"
                        >
                            Treasury artifacts
                        </p>
                        <h3 class="text-xl font-black">
                            Payment & Official Receipt
                        </h3>
                    </div>
                    <div
                        v-if="application.payment.payable"
                        class="grid gap-3 sm:grid-cols-3"
                    >
                        <div
                            class="rounded-lg bg-slate-100 p-3 dark:bg-slate-800"
                        >
                            <p class="text-xs uppercase">Payable</p>
                            <strong>{{
                                money(
                                    application.payment.payable
                                        .total_amount_cents,
                                )
                            }}</strong>
                        </div>
                        <div
                            class="rounded-lg bg-slate-100 p-3 dark:bg-slate-800"
                        >
                            <p class="text-xs uppercase">Collected</p>
                            <strong>{{
                                money(
                                    application.payment.payable
                                        .paid_amount_cents,
                                )
                            }}</strong>
                        </div>
                        <div class="rounded-lg bg-amber-100 p-3 text-amber-950">
                            <p class="text-xs uppercase">Balance</p>
                            <strong>{{
                                money(
                                    application.payment.payable
                                        .balance_amount_cents,
                                )
                            }}</strong>
                        </div>
                    </div>
                    <p
                        v-else
                        class="rounded-lg border border-dashed border-slate-300 p-5 text-sm dark:border-slate-700"
                    >
                        Payable is pending exact Treasurer approval and payment
                        scheduling.
                    </p>
                    <article
                        v-for="receipt in application.official_receipts"
                        :key="receipt.receipt_number"
                        class="rounded-xl border-2 border-emerald-700 p-4"
                    >
                        <div class="flex flex-wrap justify-between gap-3">
                            <div>
                                <p class="text-xs font-black uppercase">
                                    Accountable Form No.
                                    {{ receipt.accountable_form_number }}
                                </p>
                                <h4 class="text-lg font-black">
                                    Official Receipt
                                </h4>
                            </div>
                            <div class="text-right">
                                <p class="text-xs font-black">
                                    {{ receipt.copy_designation }}
                                </p>
                                <p class="font-mono text-lg text-red-700">
                                    {{ receipt.receipt_number }}
                                </p>
                            </div>
                        </div>
                        <dl class="mt-4 grid gap-2 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-xs uppercase">Agency / Fund</dt>
                                <dd>
                                    {{
                                        receipt.agency ??
                                        'Pending canonical detail'
                                    }}
                                    /
                                    {{
                                        receipt.fund ??
                                        'Pending canonical detail'
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase">Payor</dt>
                                <dd>
                                    {{
                                        receipt.payor ??
                                        'Pending canonical detail'
                                    }}
                                </dd>
                            </div>
                        </dl>
                        <div class="mt-4 grid gap-2">
                            <div
                                v-for="row in receipt.collection_rows"
                                :key="`${row.nature_of_collection}-${row.account_code}`"
                                class="grid min-w-0 grid-cols-[minmax(0,1fr)_auto] gap-3 text-sm"
                            >
                                <span class="break-words"
                                    >{{ row.nature_of_collection }} ·
                                    {{
                                        row.account_code ??
                                        'Account code pending'
                                    }}</span
                                ><strong>{{ money(row.amount_minor) }}</strong>
                            </div>
                        </div>
                        <p
                            class="mt-4 border-t pt-3 text-right text-xl font-black"
                        >
                            Total {{ money(receipt.total_amount_minor) }}
                        </p>
                        <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-xs uppercase">
                                    Amount in words
                                </dt>
                                <dd>
                                    {{
                                        receipt.amount_in_words ??
                                        'Pending canonical detail'
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase">
                                    Payment instrument
                                </dt>
                                <dd>
                                    {{
                                        label(receipt.payment_instrument?.type)
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase">
                                    Collecting Officer
                                </dt>
                                <dd>
                                    {{
                                        receipt.collecting_officer ??
                                        'Pending canonical detail'
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase">Date</dt>
                                <dd>
                                    {{
                                        receipt.issued_on ??
                                        'Pending canonical detail'
                                    }}
                                </dd>
                            </div>
                        </dl>
                    </article>
                    <div
                        v-if="application.official_receipts.length === 0"
                        class="rounded-lg border border-dashed border-slate-300 p-5 text-sm dark:border-slate-700"
                    >
                        <ReceiptText class="mb-2 size-5" />No Official Receipt
                        projection exists until a canonical Receipt is issued
                        from Collection truth.
                    </div>
                </div>

                <div v-else class="space-y-5">
                    <div>
                        <p
                            class="text-xs font-black text-rose-700 uppercase dark:text-rose-300"
                        >
                            Final authority artifact
                        </p>
                        <h3 class="text-xl font-black">Business Permit</h3>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <p class="text-xs uppercase">Permit no.</p>
                            <strong>{{
                                application.permit.permit_number ?? 'Pending'
                            }}</strong>
                        </div>
                        <div>
                            <p class="text-xs uppercase">Date issued</p>
                            <strong>{{
                                application.permit.issued_on ?? 'Pending'
                            }}</strong>
                        </div>
                        <div>
                            <p class="text-xs uppercase">Valid until</p>
                            <strong>{{
                                application.permit.valid_until ?? 'Pending'
                            }}</strong>
                        </div>
                    </div>
                    <div
                        class="rounded-lg bg-rose-50 p-4 text-sm leading-6 text-rose-950 dark:bg-rose-950/30 dark:text-rose-100"
                    >
                        <strong>{{
                            application.permit.official_receipt_bound
                                ? 'Official Receipt linked.'
                                : 'Official Receipt required.'
                        }}</strong>
                        {{ application.permit.statement }}
                    </div>
                    <dl class="grid gap-3 text-sm">
                        <div>
                            <dt class="text-xs font-bold uppercase">
                                Business
                            </dt>
                            <dd class="text-lg font-black break-words">
                                {{ application.permit.business_name }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-bold uppercase">
                                Owner / Operator
                            </dt>
                            <dd class="break-words">
                                {{ application.permit.owner_operator }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-bold uppercase">Address</dt>
                            <dd class="break-words">
                                {{
                                    application.permit.business_address ??
                                    'Pending'
                                }}
                            </dd>
                        </div>
                    </dl>
                    <div>
                        <p class="text-xs font-bold uppercase">
                            Lines of business
                        </p>
                        <ul class="mt-2 grid gap-1 text-sm">
                            <li
                                v-for="(lob, index) in application.permit
                                    .lines_of_business"
                                :key="index"
                                class="break-words"
                            >
                                {{ lob }}
                            </li>
                        </ul>
                    </div>
                    <div class="grid gap-4 text-sm md:grid-cols-2">
                        <div>
                            <p class="text-xs font-bold uppercase">
                                Conditions
                            </p>
                            <ol class="mt-2 grid list-decimal gap-1 pl-5">
                                <li
                                    v-for="condition in application.permit
                                        .conditions"
                                    :key="condition"
                                    class="break-words"
                                >
                                    {{ condition }}
                                </li>
                            </ol>
                        </div>
                        <dl>
                            <dt class="text-xs font-bold uppercase">
                                Issuing authority
                            </dt>
                            <dd class="mt-2 font-black">
                                {{
                                    application.permit.issuing_authority.office
                                }}
                            </dd>
                            <dd class="break-words">
                                {{
                                    application.permit.issuing_authority.name ??
                                    'Authority identity unresolved'
                                }}
                            </dd>
                            <dd class="mt-1 text-xs uppercase">
                                {{
                                    label(
                                        application.permit.issuing_authority
                                            .authority_status,
                                    )
                                }}
                            </dd>
                            <a
                                v-if="application.permit.printable_artifact_url"
                                :href="
                                    application.permit.printable_artifact_url
                                "
                                class="mt-3 inline-flex items-center gap-1 font-semibold underline"
                                >Printable artifact
                                <ExternalLink class="size-3.5"
                            /></a>
                        </dl>
                    </div>
                    <div
                        class="flex flex-col gap-3 rounded-lg border border-slate-200 p-4 sm:flex-row sm:items-center dark:border-slate-700"
                    >
                        <QrCode class="size-10 shrink-0" />
                        <div class="min-w-0">
                            <p class="font-black">
                                Public verification identity
                            </p>
                            <p class="font-mono text-xs break-all">
                                {{ application.permit.verification.reference }}
                            </p>
                            <a
                                :href="application.permit.verification.view_url"
                                class="mt-1 inline-flex items-center gap-1 text-sm font-semibold underline"
                                >Open verification
                                <ExternalLink class="size-3.5"
                            /></a>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="min-w-0">
                <div
                    v-if="tasks.length"
                    data-testid="your-task-post-it"
                    class="rotate-[-1deg] bg-[#ffe66f] p-5 text-slate-950 shadow-[5px_7px_0_rgba(15,23,42,0.18)]"
                >
                    <p class="text-xs font-black tracking-[0.2em] uppercase">
                        Your Task
                    </p>
                    <p class="mt-1 text-sm font-semibold">
                        {{ application.actor_context.actor_label }}
                    </p>
                    <ul class="mt-4 grid gap-3">
                        <li v-for="task in tasks" :key="task.key">
                            <a
                                v-if="task.href"
                                :href="task.href"
                                class="flex items-start justify-between gap-2 font-black underline decoration-2 underline-offset-4"
                                ><span>{{ task.label }}</span
                                ><ExternalLink
                                    class="mt-0.5 size-4 shrink-0" /></a
                            ><span v-else class="font-black">{{
                                task.label
                            }}</span>
                        </li>
                    </ul>
                </div>
                <div
                    v-else
                    data-testid="no-current-task"
                    class="rounded-xl border border-slate-300 bg-white/60 p-4 text-sm dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="flex items-center gap-2 font-bold">
                        <ShieldCheck class="size-4" />No current action
                    </div>
                    <p class="mt-2 text-slate-600 dark:text-slate-300">
                        This actor has no legitimate actionable responsibility
                        on the Application now.
                    </p>
                </div>
            </aside>
        </div>
    </article>
</template>
