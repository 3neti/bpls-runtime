<script setup lang="ts">
import { router, useHttp } from '@inertiajs/vue3';
import { ExternalLink, Printer, QrCode, ReceiptText } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import type { ApplicantDocumentReferenceItem } from '@/components/permit-applications/ApplicantDocumentReference.vue';
import ApplicationAttachmentRail from '@/components/permit-applications/ApplicationAttachmentRail.vue';
import ApplicationDocumentNavigator from '@/components/permit-applications/ApplicationDocumentNavigator.vue';
import ApplicationFeeCatalogueSheet from '@/components/permit-applications/ApplicationFeeCatalogueSheet.vue';
import ApplicationWorkNote from '@/components/permit-applications/ApplicationWorkNote.vue';
import BploRoutingTaskSheet from '@/components/permit-applications/BploRoutingTaskSheet.vue';
import IpilBusinessPermit from '@/components/permit-applications/IpilBusinessPermit.vue';
import IpilExecutableDocument from '@/components/permit-applications/IpilExecutableDocument.vue';
import IpilPaymentContinuationSheet from '@/components/permit-applications/IpilPaymentContinuationSheet.vue';
import OfficePaymentOrdersSheet from '@/components/permit-applications/OfficePaymentOrdersSheet.vue';
import type { MunicipalScheduleOfFees } from '@/types/municipal-schedule-of-fees';

type Task = {
    key: string;
    label: string;
    section: string;
    href: string | null;
};
type WorkNote = {
    id: string;
    actor_key: string;
    actor_label: string;
    instruction: string;
    section: string;
    anchor: string;
    state: string;
    state_label: string;
    tone: string;
    actionable: boolean;
    action_label: string | null;
    action_url: string | null;
    completed_at: string | null;
    blocking_reason: string | null;
};
type Attachment = {
    key: string;
    sequence: number;
    label: string;
    short_label: string;
    target: string;
    state: string;
    available: boolean;
    tone: string;
};
type PermitPresentation = {
    state: string;
    permit_number: string | null;
    issued_on: string | null;
    valid_until: string | null;
    business_name: string;
    owner_operator: string;
    business_address: string | null;
    lines_of_business: string[];
    conditions: string[];
    issuing_authority: {
        office: string;
        name: string | null;
        authority_status: string;
        signature_reference?: string | null;
        production_authority?: boolean;
    };
    official_receipts: {
        receipt_group_key: string;
        receipt_group_label: string;
        receipt_number: string;
        series: string | null;
        amount_minor: number;
    }[];
    official_receipt_bound: boolean;
    semantic_classification: string;
    production_authority: boolean;
    verification: {
        reference: string;
        view_url: string;
        qr_data_url: string;
    };
    printable_artifact_url: string | null;
    statement: string;
    issued: boolean;
    released: boolean;
    blockers: string[];
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
    schedule_of_payment: Record<string, any> | null;
    official_receipts: Record<string, any>[];
    post_payment: Record<string, any>;
    permit: PermitPresentation;
    documents: Record<string, any>[];
    applicant_documents: ApplicantDocumentReferenceItem[];
    signature_evidence: Record<string, any>[];
    schedule_of_fees: MunicipalScheduleOfFees;
    attachments: Attachment[];
    actor_context: {
        actor_label: string;
        role_code: string | null;
        current_tasks: Task[];
        available_affordances: Task[];
        work_notes: WorkNote[];
    };
    tabs: { key: string; label: string }[];
};

const props = withDefaults(
    defineProps<{
        application: ApplicationData;
        document?: any | null;
        mode?:
            | 'workspace'
            | 'office'
            | 'palette'
            | 'mobile'
            | 'print'
            | 'processing-print';
        initialTab?: string;
        initialTask?: string;
        recentCertificationOffice?: string | null;
        routingTask?: any | null;
        interactiveTaskRouting?: boolean;
        showRoutingTask?: boolean;
    }>(),
    {
        mode: 'workspace',
        initialTab: 'application',
        initialTask: '',
        recentCertificationOffice: null,
        routingTask: null,
        interactiveTaskRouting: false,
        showRoutingTask: true,
    },
);

const applicationPacketTargets = [
    'application',
    'processing',
    'schedule_of_fees',
    'payment_orders',
    'assessment',
    'payment',
    'permit',
];
const activeTab = ref(
    applicationPacketTargets.includes(props.initialTab)
        ? props.initialTab
        : 'application',
);
const lastApplicationPage = ref<'application' | 'processing'>(
    props.initialTab === 'processing' ? 'processing' : 'application',
);
const activeApplicationPage = computed<
    'application' | 'processing' | 'payment'
>(() =>
    activeTab.value === 'processing' || activeTab.value === 'payment'
        ? activeTab.value
        : 'application',
);
const preferredPaymentAttachment = (): string =>
    props.application.official_receipts.length > 0
        ? 'official_receipt'
        : 'qr_ph';
const attachmentKeyForTab = (tab: string): string => {
    if (tab === 'application' || tab === 'processing') {
        return 'application_form';
    }

    if (tab === 'payment') {
        return preferredPaymentAttachment();
    }

    return tab;
};
const activeAttachmentKey = ref(attachmentKeyForTab(activeTab.value));
const workNotes = computed(
    () => props.application.actor_context.work_notes ?? [],
);
const currentWorkNote = computed(
    () => workNotes.value.find((note) => note.actionable) ?? null,
);
const officePaymentOrderWork = computed(() => {
    if (props.mode !== 'office' || !props.routingTask?.routing) {
        return null;
    }

    const authorized = new Set<string>(
        props.routingTask.financial_editor
            ?.authorized_payment_order_office_codes ?? [],
    );

    return (
        props.routingTask.routing.works.find((work: { office_code: string }) =>
            authorized.has(work.office_code),
        ) ?? null
    );
});
const officePaymentOrderNote = computed<WorkNote | null>(() => {
    const work = officePaymentOrderWork.value;

    if (!work) {
        return null;
    }

    const completed = work.payment_orders.length > 0;

    return {
        id: 'office_payment_order',
        actor_key: work.office_code,
        actor_label: work.office_label,
        instruction: work.required_work || 'Prepare office Payment Order',
        section: 'processing',
        anchor: 'office_' + work.office_code,
        state: completed ? 'completed' : 'ready',
        state_label: completed ? 'Payment Order finalized' : 'Ready',
        tone: 'blue',
        actionable: !completed,
        action_label: completed ? null : 'Open Payment Order',
        action_url: completed ? null : '#payment-order',
        completed_at: work.payment_orders[0]?.issued_at ?? null,
        blocking_reason: work.situational_reason || null,
    };
});
const officeApplicationNote = computed(
    () => officePaymentOrderNote.value ?? currentWorkNote.value,
);
const officeTaskLabel = computed(() =>
    props.routingTask?.financial_editor?.can_assign_treasury_lobs
        ? 'Treasury Classification'
        : 'Payment Order',
);
const applicationStatusLabel = computed(() => {
    if (
        props.mode === 'office' &&
        props.routingTask?.financial_editor?.can_assign_treasury_lobs
    ) {
        const count =
            props.routingTask.financial_editor.treasury_assignments?.length ??
            0;

        return count
            ? `${count} ${count === 1 ? 'Line of Business' : 'Lines of Business'}`
            : 'Treasury classification pending';
    }

    if (props.mode === 'office' && props.routingTask?.routing?.works) {
        const works = props.routingTask.routing.works as {
            payment_orders: unknown[];
        }[];
        const finalized = works.filter(
            (work) => work.payment_orders.length > 0,
        ).length;

        return `${finalized}/${works.length} Payment Orders`;
    }

    if (props.application.permit.released) {
        return 'Released';
    }

    if (props.application.permit.issued) {
        return 'Issued';
    }

    if (props.application.payment.reconciliation?.totals_reconciled) {
        return 'Payment complete';
    }

    return label(props.application.identity.status);
});
const snapshot = computed(() => props.application.declaration.snapshot ?? {});
const activeTask = ref(props.initialTask);
const officeMobileView = ref<'application' | 'payment-order'>('payment-order');
const statusRequest = useHttp({});
const paymentCheckMessage = ref<string | null>(null);
const paymentStatusUrl = computed(
    () =>
        props.application.actor_context.available_affordances?.find(
            (affordance) => affordance.key === 'check_payment_status',
        )?.href ?? null,
);
let paymentPollTimer: ReturnType<typeof setInterval> | null = null;
const page2Summary = computed(() => {
    const projection = props.document?.page_2_assessment;
    const offices = Array.isArray(projection?.offices)
        ? projection.offices
        : props.application.offices;

    return {
        officeCount: offices.length,
        resolvedDeterminationCount: offices.reduce(
            (total: number, office: Record<string, any>) =>
                total +
                Number(
                    office.resolved_determination_count ??
                        office.responsibilities?.filter(
                            (item: Record<string, any>) =>
                                item.resolution === 'resolved',
                        ).length ??
                        0,
                ),
            0,
        ),
        requiredDeterminationCount: offices.reduce(
            (total: number, office: Record<string, any>) =>
                total +
                Number(
                    office.required_determination_count ??
                        office.responsibilities?.length ??
                        0,
                ),
            0,
        ),
        paymentOrderCount: offices.reduce(
            (total: number, office: Record<string, any>) =>
                total +
                Number(
                    office.payment_order_count ??
                        office.paperless_payment_order_count ??
                        0,
                ),
            0,
        ),
        processingSummary: projection?.processing_summary ?? null,
        totalLabel:
            projection?.total_label ??
            (props.application.financial?.assessment
                ? 'Assessment total'
                : 'Current total'),
        emergingTotalAmountCents:
            projection?.emerging_total_amount_cents ??
            props.application.financial?.evaluation?.working_paper
                ?.grand_total_amount_cents ??
            null,
        unresolvedChargeCount: Number(
            projection?.required_unresolved_charge_count ??
                props.application.financial?.evaluation?.working_paper
                    ?.required_unresolved_charge_count ??
                0,
        ),
    };
});

watch(
    () => props.initialTab,
    (tab) => {
        if (applicationPacketTargets.includes(tab)) {
            activeTab.value = tab;
            activeAttachmentKey.value = attachmentKeyForTab(tab);
        }
    },
);

watch(
    [activeTab, () => props.application.payment.payment_request],
    () => {
        stopPaymentPolling();

        const request = props.application.payment.payment_request;
        const expiresAt = request?.active_attempt?.expires_at;

        if (
            activeTab.value === 'payment' &&
            request?.state !== 'collected' &&
            paymentStatusUrl.value &&
            request?.active_attempt?.qr_data_url &&
            expiresAt &&
            new Date(expiresAt).getTime() > Date.now()
        ) {
            paymentPollTimer = setInterval(() => void checkPayment(), 4000);
        }
    },
    { immediate: true },
);

function stopPaymentPolling(): void {
    if (paymentPollTimer !== null) {
        clearInterval(paymentPollTimer);
        paymentPollTimer = null;
    }
}

async function checkPayment(): Promise<void> {
    const statusUrl = paymentStatusUrl.value;

    if (!statusUrl || statusRequest.processing) {
        return;
    }

    try {
        const result = (await statusRequest.get(statusUrl)) as {
            paid: boolean;
            status: string;
        };

        if (result.paid) {
            stopPaymentPolling();
            paymentCheckMessage.value =
                'Payment confirmed. Refreshing the Application record.';
            router.reload();
        } else if (result.status === 'expired') {
            stopPaymentPolling();
            paymentCheckMessage.value =
                'The QR expired without a canonical Collection.';
        } else {
            paymentCheckMessage.value = 'No confirmed Collection yet.';
        }
    } catch {
        paymentCheckMessage.value =
            'Payment confirmation is temporarily unavailable. No Application facts were changed.';
    }
}

onBeforeUnmount(stopPaymentPolling);
watch(activeTab, (tab) => {
    if (tab === 'application' || tab === 'processing') {
        lastApplicationPage.value = tab;
    }
});
watch(
    () => props.initialTask,
    (task) => {
        activeTask.value = task;
    },
);

function activateWorkNote(note: WorkNote): void {
    if (!note.actionable || !note.action_url) {
        return;
    }

    if (note.id === 'office_payment_order') {
        officeMobileView.value = 'payment-order';

        return;
    }

    if (props.interactiveTaskRouting && note.id === 'bplo_routing') {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', 'processing');
        url.searchParams.set('task', 'bplo-routing');
        router.visit(`${url.pathname}${url.search}`);

        return;
    }

    if (
        note.id.startsWith('post_payment_') ||
        note.id === 'permit_authority_review' ||
        note.id === 'permit_release'
    ) {
        router.post(note.action_url, {}, { preserveScroll: true });

        return;
    }

    router.visit(note.action_url);
}

function artifactIsActive(key: string): boolean {
    return activeAttachmentKey.value === key;
}

function selectApplicationForm(): void {
    activeAttachmentKey.value = 'application_form';
    activeTab.value = lastApplicationPage.value;
}

function selectAttachment(attachment: Attachment): void {
    activeAttachmentKey.value = attachment.key;
    activeTab.value = attachment.target;
}

function selectApplicationPage(page: 'application' | 'processing' | 'payment') {
    activeTab.value = page;
    activeAttachmentKey.value =
        page === 'payment' ? preferredPaymentAttachment() : 'application_form';
}

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

function permitBlockerLabel(blocker: string): string {
    if (blocker !== 'all_required_post_payment_certifications') {
        return label(blocker);
    }

    const readiness = props.application.post_payment?.readiness ?? {};
    const required = Array.isArray(readiness.required_offices)
        ? readiness.required_offices
        : [];
    const certified = Array.isArray(readiness.certified_offices)
        ? readiness.certified_offices
        : [];
    const pending = required.filter(
        (office: string) => !certified.includes(office),
    );
    const officeLabels = pending.map(
        (code: string) =>
            props.application.offices.find((office) => office.code === code)
                ?.label ?? label(code),
    );

    return `Post-payment certifications incomplete — ${certified.length}/${required.length}. Still required: ${officeLabels.join(', ') || 'none'}`;
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
                    <component
                        :is="mode === 'office' ? 'h1' : 'h2'"
                        class="mt-1 text-2xl font-black tracking-tight break-words sm:text-3xl"
                    >
                        {{ application.business.name }}
                    </component>
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
                    {{ applicationStatusLabel }}
                </span>
            </div>
        </header>

        <div
            v-if="mode === 'office' && showRoutingTask"
            class="grid grid-cols-2 border-b border-slate-300 bg-white p-1.5 lg:hidden dark:border-slate-700 dark:bg-slate-900 print:hidden"
            aria-label="Office workspace view"
        >
            <button
                type="button"
                :aria-pressed="officeMobileView === 'application'"
                :class="
                    officeMobileView === 'application'
                        ? 'bg-[#123f72] text-white shadow-sm'
                        : 'text-slate-600 dark:text-slate-300'
                "
                class="rounded-md px-3 py-2 text-sm font-bold"
                @click="officeMobileView = 'application'"
            >
                Application
            </button>
            <button
                type="button"
                :aria-pressed="officeMobileView === 'payment-order'"
                :class="
                    officeMobileView === 'payment-order'
                        ? 'bg-[#123f72] text-white shadow-sm'
                        : 'text-slate-600 dark:text-slate-300'
                "
                class="rounded-md px-3 py-2 text-sm font-bold"
                @click="officeMobileView = 'payment-order'"
            >
                {{ officeTaskLabel }}
            </button>
        </div>

        <div
            class="flex gap-1.5 overflow-x-auto border-b border-slate-300 bg-white/80 p-2 lg:hidden dark:border-slate-700 dark:bg-slate-900 print:hidden"
            :class="{
                hidden:
                    mode === 'office' &&
                    showRoutingTask &&
                    officeMobileView !== 'application',
            }"
            aria-label="Application packet"
        >
            <button
                type="button"
                data-testid="application-tab-application_form"
                :aria-current="
                    artifactIsActive('application_form') ? 'page' : undefined
                "
                :class="
                    artifactIsActive('application_form')
                        ? 'opacity-100 shadow-md'
                        : 'opacity-80'
                "
                class="min-h-16 min-w-[9.5rem] rounded-sm border border-t-4 border-sky-700 bg-sky-100 px-3 py-2 text-left text-sky-950 outline-none focus-visible:ring-2 focus-visible:ring-amber-600"
                @click="selectApplicationForm"
            >
                <span
                    class="block text-[9px] font-black tracking-[0.18em] uppercase"
                    >Bound document</span
                >
                <span class="mt-0.5 block text-xs font-black uppercase"
                    >Application Form</span
                >
                <span class="mt-1 block text-[9px] font-bold uppercase"
                    >Pages 1–3</span
                >
            </button>
            <ApplicationAttachmentRail
                :attachments="application.attachments"
                :active-key="activeAttachmentKey"
                @select="selectAttachment"
            />
        </div>

        <div
            :class="
                mode === 'office' &&
                showRoutingTask &&
                routingTask &&
                activeTask === 'bplo-routing'
                    ? 'lg:grid-cols-[minmax(0,3fr)_minmax(22rem,2fr)]'
                    : mode === 'office'
                      ? 'lg:grid-cols-1'
                      : routingTask &&
                          showRoutingTask &&
                          activeTask === 'bplo-routing'
                        ? 'xl:grid-cols-[minmax(0,1fr)_minmax(22rem,28rem)_19rem]'
                        : 'lg:grid-cols-[minmax(0,1fr)_19rem]'
            "
            class="grid min-w-0 gap-5 p-3 sm:p-6"
        >
            <section
                data-testid="application-document-canvas"
                :class="[
                    mode === 'office' &&
                    showRoutingTask &&
                    officeMobileView !== 'application'
                        ? 'hidden lg:block'
                        : '',
                    document &&
                    (activeTab === 'application' ||
                        activeTab === 'processing' ||
                        activeTab === 'payment' ||
                        activeTab === 'schedule_of_fees' ||
                        activeTab === 'payment_orders')
                        ? 'bg-stone-100 p-0 dark:bg-stone-950'
                        : 'bg-white p-4 sm:p-6 dark:bg-slate-900',
                ]"
                class="min-w-0 overflow-hidden rounded-xl border border-slate-300 dark:border-slate-700"
            >
                <section
                    v-if="mode === 'office' && officeApplicationNote"
                    class="border-b border-slate-300 bg-amber-50 p-3 dark:border-slate-700 dark:bg-amber-950/20"
                    data-testid="office-application-work-note"
                >
                    <ApplicationWorkNote
                        :note="officeApplicationNote"
                        :index="0"
                        @activate="activateWorkNote"
                    />
                </section>
                <ApplicationDocumentNavigator
                    v-if="
                        activeTab === 'application' ||
                        activeTab === 'processing' ||
                        activeTab === 'payment'
                    "
                    :active-page="activeApplicationPage"
                    :declaration-state="application.declaration.state"
                    :routing-status="application.routing.status"
                    :office-count="page2Summary.officeCount"
                    :resolved-determination-count="
                        page2Summary.resolvedDeterminationCount
                    "
                    :required-determination-count="
                        page2Summary.requiredDeterminationCount
                    "
                    :payment-order-count="page2Summary.paymentOrderCount"
                    :processing-summary="page2Summary.processingSummary"
                    :total-label="page2Summary.totalLabel"
                    :emerging-total-amount-cents="
                        page2Summary.emergingTotalAmountCents
                    "
                    :unresolved-charge-count="
                        page2Summary.unresolvedChargeCount
                    "
                    :has-payable="Boolean(application.payment.payable)"
                    :payment-state="
                        application.payment.payment_request?.state ??
                        application.payment.state
                    "
                    @select="selectApplicationPage"
                />
                <div
                    v-if="activeTab === 'application'"
                    data-testid="application-page-1"
                >
                    <IpilExecutableDocument
                        v-if="document"
                        :document="document"
                        page="page_1"
                    />
                    <template v-else>
                        <div
                            class="flex flex-wrap items-center justify-between gap-3 border-b-2 border-slate-900 pb-3 dark:border-slate-300"
                        >
                            <div>
                                <p class="text-xs font-black uppercase">
                                    Page 1
                                </p>
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
                            What the applicant declared. Once submitted, this
                            page is the frozen evidentiary snapshot.
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
                                Nature / Description of Business
                            </h4>
                            <p
                                class="mt-2 rounded-lg bg-slate-100 px-3 py-2 text-sm break-words dark:bg-slate-800"
                            >
                                {{
                                    application.business
                                        .applicant_activity_description ??
                                    'Not recorded'
                                }}
                            </p>
                        </div>
                    </template>
                </div>

                <div
                    v-else-if="activeTab === 'processing'"
                    data-testid="application-page-2"
                >
                    <IpilExecutableDocument
                        v-if="document"
                        :document="document"
                        page="page_2"
                        :recent-certification-office="recentCertificationOffice"
                    />
                    <div v-else class="space-y-5">
                        <div
                            class="border-b-2 border-slate-900 pb-3 dark:border-slate-300"
                        >
                            <p class="text-xs font-black uppercase">Page 2</p>
                            <h3 class="text-xl font-black">
                                Municipal Processing
                            </h3>
                        </div>
                        <p
                            class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                        >
                            A living projection of what the Municipality has
                            done. Canonical actions and records remain
                            authoritative.
                        </p>
                        <div
                            class="rounded-lg bg-sky-50 p-4 dark:bg-sky-950/40"
                        >
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
                                <div
                                    class="flex flex-wrap justify-between gap-2"
                                >
                                    <h4 class="font-black">
                                        {{ office.label }}
                                    </h4>
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
                            Concerned-office work will appear after the
                            canonical BPLO routing determination.
                        </p>
                    </div>
                </div>

                <div
                    v-else-if="activeTab === 'schedule_of_fees'"
                    class="bg-stone-100 p-2 sm:p-5"
                >
                    <ApplicationFeeCatalogueSheet
                        :schedule="application.schedule_of_fees"
                    />
                </div>

                <div
                    v-else-if="activeTab === 'payment_orders'"
                    class="bg-stone-100 p-2 sm:p-5"
                >
                    <OfficePaymentOrdersSheet
                        :offices="application.offices"
                        :application-year="
                            application.identity.application_year
                        "
                    />
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
                    <section
                        v-if="application.schedule_of_payment"
                        class="rounded-xl border border-slate-300 p-4 dark:border-slate-700"
                        data-testid="application-schedule-of-payment"
                    >
                        <h3 class="font-black">Schedule of Payment</h3>
                        <div
                            v-for="group in application.schedule_of_payment
                                .groups"
                            :key="group.key"
                            class="mt-3 border-t border-slate-200 pt-3 dark:border-slate-700"
                        >
                            <div
                                class="flex justify-between gap-3 text-sm font-bold"
                            >
                                <span>{{ group.label }}</span
                                ><span>{{ money(group.subtotal_minor) }}</span>
                            </div>
                        </div>
                        <div
                            class="mt-4 flex justify-between border-t-2 border-slate-900 pt-3 text-lg font-black dark:border-slate-200"
                        >
                            <span>Total</span
                            ><span>{{
                                money(
                                    application.schedule_of_payment
                                        .grand_total_minor,
                                )
                            }}</span>
                        </div>
                    </section>
                    <IpilPaymentContinuationSheet
                        :application="application"
                        :checking="statusRequest.processing"
                        :check-message="paymentCheckMessage"
                        :status-url="paymentStatusUrl"
                        @check="checkPayment"
                    />
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
                    <template v-if="application.permit.issued">
                        <div
                            class="flex flex-wrap items-center justify-between gap-3 print:hidden"
                        >
                            <div>
                                <p
                                    class="text-xs font-black text-rose-700 uppercase dark:text-rose-300"
                                >
                                    Attachment F · Final authority artifact
                                </p>
                                <h3 class="text-xl font-black">
                                    Business Permit
                                </h3>
                            </div>
                            <a
                                v-if="application.permit.printable_artifact_url"
                                :href="
                                    application.permit.printable_artifact_url
                                "
                                target="_blank"
                                class="inline-flex min-h-10 items-center gap-2 rounded-md bg-sky-900 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800"
                            >
                                <Printer class="size-4" /> Open PDF
                            </a>
                        </div>
                        <IpilBusinessPermit
                            :permit="application.permit"
                            :verification="application.permit.verification"
                            :application-year="
                                application.identity.application_year
                            "
                        />
                    </template>
                    <template v-else>
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
                                    application.permit.permit_number ??
                                    'Pending'
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
                        <div
                            v-if="application.permit.official_receipts?.length"
                            class="rounded-lg border border-rose-200 p-4"
                        >
                            <p class="text-xs font-bold uppercase">
                                Official Receipts
                            </p>
                            <div
                                v-for="receipt in application.permit
                                    .official_receipts"
                                :key="receipt.receipt_group_key"
                                class="mt-2 flex flex-wrap justify-between gap-2 text-sm"
                            >
                                <span>{{ receipt.receipt_group_label }}</span>
                                <strong
                                    >{{ receipt.receipt_number
                                    }}<span v-if="receipt.series">
                                        · {{ receipt.series }}</span
                                    ></strong
                                >
                            </div>
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
                                <dt class="text-xs font-bold uppercase">
                                    Address
                                </dt>
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
                                        application.permit.issuing_authority
                                            .office
                                    }}
                                </dd>
                                <dd class="break-words">
                                    {{
                                        application.permit.issuing_authority
                                            .name ??
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
                                    v-if="
                                        application.permit
                                            .printable_artifact_url
                                    "
                                    :href="
                                        application.permit
                                            .printable_artifact_url
                                    "
                                    class="mt-3 inline-flex items-center gap-1 font-semibold underline"
                                    >Printable artifact
                                    <ExternalLink class="size-3.5"
                                /></a>
                            </dl>
                        </div>
                        <div
                            v-if="application.permit.issued"
                            class="flex flex-col gap-3 rounded-lg border border-slate-200 p-4 sm:flex-row sm:items-center dark:border-slate-700"
                        >
                            <QrCode class="size-10 shrink-0" />
                            <div class="min-w-0">
                                <p class="font-black">
                                    Public verification identity
                                </p>
                                <p class="font-mono text-xs break-all">
                                    {{
                                        application.permit.verification
                                            .reference
                                    }}
                                </p>
                                <a
                                    :href="
                                        application.permit.verification.view_url
                                    "
                                    class="mt-1 inline-flex items-center gap-1 text-sm font-semibold underline"
                                    >Open verification
                                    <ExternalLink class="size-3.5"
                                /></a>
                            </div>
                        </div>
                        <div
                            v-else-if="application.permit.blockers.length > 0"
                            class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
                        >
                            <p class="font-black uppercase">
                                Pending before issuance
                            </p>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                <li
                                    v-for="blocker in application.permit
                                        .blockers"
                                    :key="blocker"
                                >
                                    {{ permitBlockerLabel(blocker) }}
                                </li>
                            </ul>
                        </div>
                    </template>
                </div>
            </section>

            <BploRoutingTaskSheet
                v-if="
                    routingTask &&
                    showRoutingTask &&
                    activeTask === 'bplo-routing' &&
                    activeTab === 'processing'
                "
                :task="routingTask"
                :documents="application.applicant_documents"
                mode="sheet"
                class="self-start lg:sticky lg:top-4"
                :class="{
                    'hidden lg:block':
                        mode === 'office' &&
                        officeMobileView !== 'payment-order',
                }"
            />

            <aside
                v-if="mode !== 'office'"
                :class="{ 'print:hidden': mode !== 'processing-print' }"
                class="min-w-0"
                aria-label="Executable Application palette"
            >
                <div class="space-y-5 lg:sticky lg:top-4">
                    <section
                        class="hidden overflow-hidden rounded-xl border border-slate-300 bg-white/80 lg:block dark:border-slate-700 dark:bg-slate-900"
                    >
                        <div
                            class="border-b border-slate-200 p-4 dark:border-slate-700"
                        >
                            <p
                                class="text-[11px] font-black tracking-[0.18em] text-slate-500 uppercase"
                            >
                                Executable palette
                            </p>
                            <p class="mt-1 text-sm font-bold break-words">
                                {{ application.actor_context.actor_label }}
                            </p>
                        </div>
                        <div
                            class="grid gap-1.5 p-2"
                            aria-label="Application packet palette"
                        >
                            <button
                                type="button"
                                data-testid="palette-tab-application_form"
                                :aria-current="
                                    artifactIsActive('application_form')
                                        ? 'page'
                                        : undefined
                                "
                                :class="
                                    artifactIsActive('application_form')
                                        ? 'opacity-100 shadow-md'
                                        : 'opacity-80'
                                "
                                class="min-h-16 w-full rounded-sm border border-l-4 border-sky-700 bg-sky-100 px-3 py-2 text-left text-sky-950 outline-none focus-visible:ring-2 focus-visible:ring-amber-600"
                                @click="selectApplicationForm"
                            >
                                <span
                                    class="block text-[9px] font-black tracking-[0.18em] uppercase"
                                    >Bound document</span
                                >
                                <span
                                    class="mt-0.5 block text-xs font-black uppercase"
                                    >Application Form</span
                                >
                                <span
                                    class="mt-1 block text-[9px] font-bold uppercase"
                                    >Pages 1–3</span
                                >
                            </button>
                            <ApplicationAttachmentRail
                                :attachments="application.attachments"
                                :active-key="activeAttachmentKey"
                                desktop
                                @select="selectAttachment"
                            />
                        </div>
                    </section>

                    <section
                        v-if="currentWorkNote"
                        data-testid="application-current-work-note"
                    >
                        <p
                            class="mb-2 text-[11px] font-black tracking-[0.18em] text-slate-500 uppercase"
                        >
                            Current action
                        </p>
                        <ApplicationWorkNote
                            :note="currentWorkNote"
                            :index="0"
                            @activate="activateWorkNote"
                        />
                    </section>

                    <details
                        data-testid="application-activity"
                        class="rounded-xl border border-slate-300 bg-white/80 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <summary
                            class="cursor-pointer px-4 py-3 text-sm font-semibold"
                        >
                            Activity · {{ workNotes.length }} entries
                        </summary>
                        <div
                            class="grid max-h-[70vh] gap-4 overflow-y-auto border-t border-slate-200 p-4 dark:border-slate-700"
                        >
                            <ApplicationWorkNote
                                v-for="(note, index) in workNotes"
                                :key="note.id"
                                :note="note"
                                :index="index"
                                @activate="activateWorkNote"
                            />
                        </div>
                    </details>
                </div>
            </aside>
        </div>
    </article>
</template>
