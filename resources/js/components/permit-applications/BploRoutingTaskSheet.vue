<script setup lang="ts">
import { useForm, useHttp, usePage } from '@inertiajs/vue3';
import { Check, ChevronRight, FileClock, Route } from '@lucide/vue';
import { useNow } from '@vueuse/core';
import { computed, reactive, ref } from 'vue';
import { store as recordBploRouting } from '@/actions/App/Http/Controllers/Staff/BploRoutingDeterminationController';
import ApplicantDocumentReference from '@/components/permit-applications/ApplicantDocumentReference.vue';
import type { ApplicantDocumentReferenceItem } from '@/components/permit-applications/ApplicantDocumentReference.vue';
import EnterpriseClassificationSelector from '@/components/permit-applications/EnterpriseClassificationSelector.vue';
import FinancialLineItemEditor from '@/components/permit-applications/FinancialLineItemEditor.vue';
import SignatureFacsimileCapture from '@/components/SignatureFacsimileCapture.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dateTime, money } from '@/lib/evaluationPresentation';
import { financialLineItemsResolved } from '@/lib/financialLineItems';
import type { EnterpriseSchedule } from '@/lib/treasuryEnterprise';
import {
    applyEnterpriseClassification,
    treasuryConfirmationReason,
} from '@/lib/treasuryEnterprise';
import { index as workInbox } from '@/routes/staff/work';

type RoutingLine = {
    id: number | null;
    line_of_business_id: number | null;
    line_of_business_name: string | null;
};

type SuggestedWork = {
    office_code: string;
    office_label: string;
    situational_reason: string;
    required_work: string;
    permit_application_line_id: number;
};

type RoutingWork = SuggestedWork & {
    id: number;
    line_of_business_name: string | null;
    payment_orders: {
        id: number;
        sequence: number;
        status: string;
        total_amount_cents: number;
        issued_by: string;
        issued_at: string;
        signature_facsimile_data_url: string | null;
    }[];
};

type BploRoutingTask = {
    schema_version: string;
    application: {
        id: number;
        application_number: string | null;
        tracking_reference: string | null;
        business_name: string;
        owner_name: string;
        type: string;
        year: number;
        submitted_at: string | null;
        business_activity_description: string | null;
        commissioned_path: boolean;
        lines: RoutingLine[];
    };
    routing: {
        id: number;
        determined_by: string;
        determined_at: string;
        situational_context: string;
        origin: string;
        works: RoutingWork[];
    } | null;
    suggestion: {
        id: number;
        profile_version: string;
        status: string;
        situational_context: string;
        suggested_work: SuggestedWork[];
        lodged_at: string;
        review_due_at: string;
    } | null;
    office_options: { code: string; label: string }[];
    financial_editor: {
        catalog_status: string;
        office_fee_options: Record<
            string,
            {
                id: number;
                code: string;
                name: string;
                default_amount_cents: number;
                provenance?: {
                    catalog_version?: string | null;
                    classification?: string | null;
                    source_name?: string | null;
                    effective_from?: string | null;
                    effective_until?: string | null;
                    is_active?: boolean;
                };
                exact_once_key?: string | null;
                calculation?: {
                    explanation?: string | null;
                    rule_signature?: string;
                };
            }[]
        >;
        menro_determination: {
            id: number;
            scope: string;
            fee_rule_id: number;
            source_identity: number;
            code: string;
            basis: string;
            application_area_square_meters: number;
            calculation_basis_centi_square_meters: number;
            operative_range_min_centi_square_meters: number;
            operative_range_max_centi_square_meters: number;
            amount_minor: number;
            schedule_version: string;
            source_evidence: string;
            classification: string;
            production_authority: boolean;
            reason: string;
            actor: string | null;
            determined_at: string;
            fingerprint: string;
            warning: string;
        } | null;
        can_record_menro_determination: boolean;
        menro_determination_proposal: {
            scope: string;
            scope_label: string;
            fee_rule_id: number;
            source_identity: number;
            code: string;
            basis: string;
            application_area_square_meters: number;
            calculation_basis_centi_square_meters: number;
            operative_range_min_centi_square_meters: number;
            operative_range_max_centi_square_meters: number;
            amount_minor: number;
            schedule_version: string;
            source_evidence: string;
            classification: string;
            production_authority: boolean;
            reason: string;
            actor_statement: string;
            timestamp_statement: string;
            warning: string;
        } | null;
        line_of_business_options: {
            id: number;
            code: string;
            name: string;
            default_items: {
                enterprise_schedule?: EnterpriseSchedule | null;
                resolution_status?: 'resolved' | 'unresolved';
                resolution_message?: string | null;
                fee_rule_id: number;
                code: string;
                name: string;
                amount_cents: number;
                exact_once_key?: string | null;
                calculation?: {
                    explanation?: string | null;
                    rule_signature?: string;
                };
            }[];
        }[];
        treasury_assignments: {
            id: number;
            name: string;
            enterprise_determination?: {
                classification: string;
                determined_by_id: number;
                determined_at: string;
                schedule: EnterpriseSchedule;
            } | null;
            items: { name: string; amount_cents: number }[];
        }[];
        authorized_payment_order_office_codes: string[];
        can_assign_treasury_lobs: boolean;
    };
    can_determine: boolean;
    manual_confirmation_required: boolean;
};

const props = withDefaults(
    defineProps<{
        task: BploRoutingTask;
        mode?: 'embedded' | 'sheet';
        documents?: ApplicantDocumentReferenceItem[];
    }>(),
    { mode: 'sheet', documents: () => [] },
);

const page = usePage();
const pending = ref(false);
const routingMessage = ref('');
const routingAcknowledged = ref(false);
const routingOutcomeUnconfirmed = ref(false);
let routingTimer: number | undefined;
const officeItems = reactive<
    Record<
        number,
        {
            fee_rule_id: number;
            code: string;
            name: string;
            amount_cents: number;
            exact_once_key?: string | null;
            calculation?: {
                explanation?: string | null;
                rule_signature?: string;
            };
        }[]
    >
>({});
const officeSignatures = reactive<Record<number, File | null>>({});
const officePaymentOrderPending = reactive<Record<number, boolean>>({});
const officePaymentOrderMessages = reactive<Record<number, string>>({});
const officePaymentOrderTimers = new Map<number, number>();
const treasurySelections = ref<
    {
        line_of_business_id: number;
        enterprise_classification?: string;
        enterprise_schedule_fingerprint?: string;
        items: {
            amount_locked?: boolean;
            resolution_status?: 'resolved' | 'unresolved';
            resolution_message?: string | null;
            fee_rule_id: number;
            code: string;
            name: string;
            amount_cents: number;
            exact_once_key?: string | null;
            calculation?: {
                explanation?: string | null;
                rule_signature?: string;
            };
        }[];
    }[]
>([]);
const selectedTreasuryLob = ref<number | null>(null);
const treasuryLobSearch = ref('');
const treasuryPending = ref(false);
const menroDeterminationPending = ref(false);
const treasuryErrors = computed(() =>
    Object.entries(page.props.errors)
        .filter(([key]) => key.startsWith('selections'))
        .map(([, message]) => message),
);

for (const work of props.task.routing?.works ?? []) {
    officeItems[work.id] = [];
    officeSignatures[work.id] = null;
}

const routingContext = ref(
    props.task.application.commissioned_path
        ? 'Concerned offices selected by BPLO checklist.'
        : (props.task.suggestion?.situational_context ?? ''),
);
const routingLines = props.task.application.commissioned_path
    ? [{ id: null, line_of_business_id: null, line_of_business_name: null }]
    : props.task.application.lines;
const candidates = props.task.office_options.flatMap((office) =>
    routingLines.map((line) => ({
        key: `${office.code}-${line.id}`,
        office,
        line,
    })),
);
const suggestedWork = new Map(
    (props.task.suggestion?.suggested_work ?? []).map((work) => [
        `${work.office_code}-${work.permit_application_line_id}`,
        work,
    ]),
);
const drafts = reactive(
    Object.fromEntries(
        candidates.map((candidate) => {
            const suggestion = suggestedWork.get(candidate.key);

            return [
                candidate.key,
                {
                    selected: suggestion !== undefined,
                    reason: suggestion?.situational_reason ?? '',
                    requiredWork: suggestion?.required_work ?? '',
                },
            ];
        }),
    ) as Record<
        string,
        { selected: boolean; reason: string; requiredWork: string }
    >,
);
const officeGroups = computed(() =>
    props.task.office_options.map((office) => ({
        office,
        candidates: candidates.filter(
            (candidate) => candidate.office.code === office.code,
        ),
    })),
);
const selectedCount = computed(
    () =>
        candidates.filter((candidate) => drafts[candidate.key].selected).length,
);
const authorizedPaymentOrderWorkIds = computed(
    () =>
        new Set(
            (props.task.routing?.works ?? [])
                .filter((work) =>
                    props.task.financial_editor.authorized_payment_order_office_codes.includes(
                        work.office_code,
                    ),
                )
                .map((work) => work.id),
        ),
);
const activePaymentOrderWorkIds = computed(
    () =>
        new Set(
            (props.task.routing?.works ?? [])
                .filter(
                    (work) =>
                        work.payment_orders.length === 0 &&
                        props.task.financial_editor.authorized_payment_order_office_codes.includes(
                            work.office_code,
                        ),
                )
                .map((work) => work.id),
        ),
);
const displayedRoutingWorks = computed(() =>
    authorizedPaymentOrderWorkIds.value.size > 0
        ? (props.task.routing?.works ?? []).filter((work) =>
              authorizedPaymentOrderWorkIds.value.has(work.id),
          )
        : (props.task.routing?.works ?? []),
);
const finalizedOfficeCount = computed(
    () =>
        (props.task.routing?.works ?? []).filter(
            (work) => work.payment_orders.length > 0,
        ).length,
);
const isTreasuryActor = computed(
    () => props.task.financial_editor.can_assign_treasury_lobs,
);
const paymentOrderTotal = computed(() =>
    (props.task.routing?.works ?? []).reduce(
        (total, work) =>
            total +
            work.payment_orders.reduce(
                (officeTotal, order) => officeTotal + order.total_amount_cents,
                0,
            ),
        0,
    ),
);
const allPaymentOrdersFinalized = computed(
    () =>
        (props.task.routing?.works.length ?? 0) > 0 &&
        (props.task.routing?.works ?? []).every(
            (work) => work.payment_orders.length > 0,
        ),
);
const clock = useNow({ interval: 1_000 });
const countdown = computed(() => {
    if (
        props.task.manual_confirmation_required ||
        !props.task.suggestion ||
        props.task.routing
    ) {
        return null;
    }

    const remaining = Math.max(
        0,
        Math.ceil(
            (new Date(props.task.suggestion.review_due_at).getTime() -
                clock.value.getTime()) /
                1_000,
        ),
    );

    if (remaining === 0) {
        return 'Review window elapsed';
    }

    return `${Math.floor(remaining / 60)}:${String(remaining % 60).padStart(2, '0')} remaining`;
});

function applySuggestedRouting(): void {
    const hasSuggestion = suggestedWork.size > 0;

    candidates.forEach((candidate) => {
        const draft = drafts[candidate.key];
        const suggestion = suggestedWork.get(candidate.key);
        draft.selected = hasSuggestion ? suggestion !== undefined : true;

        if (suggestion) {
            draft.reason = suggestion.situational_reason;
            draft.requiredWork = suggestion.required_work;
        }
    });
}

async function submit(): Promise<void> {
    if (
        pending.value ||
        routingAcknowledged.value ||
        routingOutcomeUnconfirmed.value ||
        props.task.routing !== null ||
        selectedCount.value === 0
    ) {
        return;
    }

    pending.value = true;
    routingMessage.value = '';
    routingTimer = window.setTimeout(() => {
        routingMessage.value =
            'BPLS is still recording the route. Do not submit it again. The page will continue when the canonical record is available.';
    }, 10_000);
    const request = useHttp({
        situational_context: routingContext.value,
        selected_work: candidates
            .filter((candidate) => drafts[candidate.key].selected)
            .map((candidate) => ({
                office_code: candidate.office.code,
                office_label: candidate.office.label,
                situational_reason: drafts[candidate.key].reason,
                required_work: drafts[candidate.key].requiredWork,
                permit_application_line_id: candidate.line.id,
            })),
    });

    try {
        const response = await request.post(
            recordBploRouting(props.task.application.id).url,
        );

        if (request.hasErrors) {
            routingMessage.value =
                Object.values(request.errors).find(
                    (message): message is string => typeof message === 'string',
                ) ??
                'The route could not be recorded. Review the selected offices.';

            return;
        }

        if (
            typeof response !== 'object' ||
            response === null ||
            !('status' in response) ||
            response.status !== 'recorded' ||
            !('permit_application_id' in response) ||
            response.permit_application_id !== props.task.application.id ||
            !('routing_determination_id' in response) ||
            !Number.isInteger(response.routing_determination_id) ||
            Number(response.routing_determination_id) < 1
        ) {
            throw new Error('Routing acknowledgement unavailable.');
        }

        routingAcknowledged.value = true;
        routingMessage.value =
            'Concerned-office routing recorded. The office Payment Orders are ready.';
    } catch {
        routingOutcomeUnconfirmed.value = true;
        routingMessage.value =
            'Routing outcome is unconfirmed. It may already be recorded. Do not submit again. Reload the routing record to check before taking another action.';
    } finally {
        pending.value = false;

        if (routingTimer !== undefined) {
            window.clearTimeout(routingTimer);
            routingTimer = undefined;
        }
    }
}

function reviewRoutingRecord(): void {
    window.location.reload();
}

function confirmPaymentOrder(work: RoutingWork): void {
    const signature = officeSignatures[work.id];
    const items = officeItems[work.id] ?? [];

    if (
        officePaymentOrderPending[work.id] ||
        !signature ||
        items.length === 0
    ) {
        return;
    }

    officePaymentOrderPending[work.id] = true;
    officePaymentOrderMessages[work.id] = '';
    officePaymentOrderTimers.set(
        work.id,
        window.setTimeout(() => {
            officePaymentOrderMessages[work.id] =
                'BPLS is still confirming this Payment Order. Do not submit it again. If this message remains, report the request as incomplete.';
        }, 15_000),
    );

    const form = useForm({ items, signature_facsimile: signature });
    form.post(
        `/staff/permit-applications/${props.task.application.id}/office-payment-orders/${work.id}`,
        {
            preserveScroll: true,
            onError: (errors) => {
                officePaymentOrderMessages[work.id] =
                    Object.values(errors).find(
                        (message): message is string =>
                            typeof message === 'string',
                    ) ??
                    'The Payment Order could not be confirmed. Review its items and signature.';
            },
            onHttpException: (response) => {
                officePaymentOrderMessages[work.id] =
                    response.status === 401 || response.status === 419
                        ? 'Your session expired before the Payment Order was confirmed. Sign in again, review the order, and submit it once.'
                        : 'BPLS could not confirm the Payment Order. No finalized order was recorded. Please try again or report this task.';

                return false;
            },
            onNetworkError: () => {
                officePaymentOrderMessages[work.id] =
                    'The Payment Order could not reach BPLS. No finalized order was recorded. Check the connection before trying again.';

                return false;
            },
            onFinish: () => {
                officePaymentOrderPending[work.id] = false;
                const timer = officePaymentOrderTimers.get(work.id);

                if (timer !== undefined) {
                    window.clearTimeout(timer);
                    officePaymentOrderTimers.delete(work.id);
                }
            },
        },
    );
}

function recordProvisionalMenroDetermination(): void {
    if (menroDeterminationPending.value) {
        return;
    }

    const proposal = props.task.financial_editor.menro_determination_proposal;

    if (!proposal) {
        return;
    }

    useForm({
        scope: proposal.scope,
        fee_rule_id: proposal.fee_rule_id,
        code: proposal.code,
        basis: proposal.basis,
        application_area_square_meters: proposal.application_area_square_meters,
        calculation_basis_centi_square_meters:
            proposal.calculation_basis_centi_square_meters,
        operative_range_min_centi_square_meters:
            proposal.operative_range_min_centi_square_meters,
        operative_range_max_centi_square_meters:
            proposal.operative_range_max_centi_square_meters,
        amount_minor: proposal.amount_minor,
        schedule_version: proposal.schedule_version,
        source_evidence: proposal.source_evidence,
        classification: proposal.classification,
        production_authority: proposal.production_authority,
        reason: proposal.reason,
    }).post(
        `/staff/permit-applications/${props.task.application.id}/menro-fee-determination`,
        {
            preserveScroll: true,
            onStart: () => {
                menroDeterminationPending.value = true;
            },
            onFinish: () => {
                menroDeterminationPending.value = false;
            },
        },
    );
}

function addTreasuryLob(): void {
    const option = props.task.financial_editor.line_of_business_options.find(
        (candidate) => candidate.id === selectedTreasuryLob.value,
    );

    if (
        !option ||
        treasurySelections.value.some(
            (item) => item.line_of_business_id === option.id,
        )
    ) {
        return;
    }

    const existingItems = treasurySelections.value.flatMap(
        (selection) => selection.items,
    );
    treasurySelections.value.push({
        line_of_business_id: option.id,
        items: option.default_items
            .filter(
                (item) =>
                    !item.exact_once_key ||
                    !existingItems.some(
                        (existing) =>
                            existing.exact_once_key === item.exact_once_key &&
                            existing.calculation?.rule_signature ===
                                item.calculation?.rule_signature,
                    ),
            )
            .map((item) => ({ ...item })),
    });
    selectedTreasuryLob.value = null;
    treasuryLobSearch.value = '';
}

function confirmTreasuryLobs(): void {
    if (!treasurySelectionsReady.value) {
        return;
    }

    useForm({ selections: treasurySelections.value }).post(
        `/staff/permit-applications/${props.task.application.id}/treasury-lines-of-business`,
        {
            preserveScroll: true,
            onStart: () => {
                treasuryPending.value = true;
            },
            onFinish: () => {
                treasuryPending.value = false;
            },
        },
    );
}

function removeTreasuryLob(lineOfBusinessId: number): void {
    treasurySelections.value = treasurySelections.value.filter(
        (item) => item.line_of_business_id !== lineOfBusinessId,
    );
}

function treasuryFeeOptions(lineOfBusinessId: number) {
    return (
        props.task.financial_editor.line_of_business_options.find(
            (line) => line.id === lineOfBusinessId,
        )?.default_items ?? []
    )
        .filter((item) => !item.enterprise_schedule)
        .map((item) => ({
            id: item.fee_rule_id,
            code: item.code,
            name: item.name,
            default_amount_cents: item.amount_cents,
            exact_once_key: item.exact_once_key,
            calculation: item.calculation,
            resolution_status: item.resolution_status,
            resolution_message: item.resolution_message,
        }));
}

function enterpriseFee(lineOfBusinessId: number) {
    return props.task.financial_editor.line_of_business_options
        .find((line) => line.id === lineOfBusinessId)
        ?.default_items.find((item) => item.enterprise_schedule);
}

function determineEnterprise(
    selection: (typeof treasurySelections.value)[number],
    classification: string,
): void {
    const fee = enterpriseFee(selection.line_of_business_id);

    if (!fee?.enterprise_schedule) {
        return;
    }

    selection.enterprise_classification = classification;
    selection.enterprise_schedule_fingerprint =
        fee.enterprise_schedule.fingerprint;
    selection.items = applyEnterpriseClassification(
        selection.items,
        fee.fee_rule_id,
        fee.enterprise_schedule,
        classification,
    );
}

function actorLabel(name: string): string {
    return name.replace(/^Cleanroom\s+\S+\s+/, '');
}

const treasurySelectionsReady = computed(
    () =>
        !treasuryPending.value &&
        treasurySelections.value.length > 0 &&
        treasurySelections.value.every((selection) =>
            financialLineItemsResolved(selection.items),
        ) &&
        treasurySelections.value.some(
            (selection) => selection.items.length > 0,
        ),
);
const treasuryConfirmReason = computed(() =>
    treasuryConfirmationReason(
        treasurySelections.value.map((selection) => ({
            items: selection.items,
            requiresEnterpriseClassification: Boolean(
                enterpriseFee(selection.line_of_business_id)
                    ?.enterprise_schedule,
            ),
            enterpriseClassification: selection.enterprise_classification,
            enterpriseFeeId: enterpriseFee(selection.line_of_business_id)
                ?.fee_rule_id,
        })),
        treasuryPending.value,
    ),
);
const filteredTreasuryLobOptions = computed(() => {
    const query = treasuryLobSearch.value.trim().toLocaleLowerCase();
    const options = props.task.financial_editor.line_of_business_options;

    return query === ''
        ? options
        : options.filter((line) =>
              `${line.name} ${line.code}`.toLocaleLowerCase().includes(query),
          );
});
</script>

<template>
    <section
        data-testid="bplo-routing-task-sheet"
        :data-task-mode="mode"
        :class="[
            'min-w-0 overflow-hidden rounded-xl bg-white shadow-sm dark:bg-slate-900',
            task.application.commissioned_path
                ? 'border'
                : 'border-2 border-[#1f416b]/35',
        ]"
        aria-labelledby="bplo-routing-task-title"
    >
        <p
            v-for="error in treasuryErrors"
            :key="error"
            role="alert"
            class="p-4 text-sm text-destructive"
        >
            {{ error }}
        </p>
        <header
            :class="[
                'px-4 py-4 sm:px-5',
                task.application.commissioned_path
                    ? 'border-b bg-card'
                    : 'bg-[#1f416b] text-white',
            ]"
        >
            <p
                :class="[
                    'text-xs font-bold tracking-[0.18em] uppercase',
                    task.application.commissioned_path ? 'text-primary' : '',
                ]"
            >
                {{
                    task.application.commissioned_path
                        ? 'Current work'
                        : 'BPLO task sheet'
                }}
            </p>
            <h2 id="bplo-routing-task-title" class="mt-1 text-xl font-black">
                {{
                    task.application.commissioned_path && task.routing
                        ? isTreasuryActor
                            ? 'Treasury classification'
                            : authorizedPaymentOrderWorkIds.size
                              ? `${displayedRoutingWorks[0].office_label} Payment Order`
                              : 'Payment Orders'
                        : task.routing
                          ? task.application.commissioned_path
                              ? 'Routing confirmed'
                              : 'Concerned-office routing recorded'
                          : 'Record concerned-office routing'
                }}
            </h2>
            <p
                :class="[
                    'mt-1 text-sm',
                    task.application.commissioned_path
                        ? 'text-muted-foreground'
                        : 'text-white/80',
                ]"
            >
                <template
                    v-if="task.application.commissioned_path && task.routing"
                >
                    <template v-if="isTreasuryActor">
                        {{
                            task.financial_editor.treasury_assignments.length
                                ? `${task.financial_editor.treasury_assignments.length} Lines of Business assigned`
                                : 'Assign official Lines of Business'
                        }}
                    </template>
                    <template v-else>
                        {{ finalizedOfficeCount }} of
                        {{ task.routing.works.length }} finalized
                    </template>
                </template>
                <template v-else>
                    {{ task.application.business_name }} ·
                    {{ task.application.year }} {{ task.application.type }}
                </template>
            </p>
        </header>

        <div v-if="task.routing" class="grid gap-4 p-4 sm:p-5">
            <div
                v-if="!task.application.commissioned_path"
                class="flex flex-col gap-3 rounded-xl bg-emerald-50 p-4 text-emerald-950 sm:flex-row sm:items-start sm:justify-between dark:bg-emerald-950/30 dark:text-emerald-100"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="flex size-8 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white"
                    >
                        <Check class="size-4" aria-hidden="true" />
                    </span>
                    <div>
                        <p class="font-black">
                            {{
                                task.application.commissioned_path
                                    ? 'Concerned offices confirmed'
                                    : 'Written to Application Page 2'
                            }}
                        </p>
                        <p class="mt-1 text-sm">
                            {{ task.routing.determined_by }} ·
                            {{ dateTime(task.routing.determined_at) }}
                        </p>
                    </div>
                </div>
                <Badge variant="outline" class="w-fit shrink-0">
                    {{ task.routing.works.length }}
                    {{
                        task.application.commissioned_path
                            ? task.routing.works.length === 1
                                ? 'office'
                                : 'offices'
                            : 'routed work item(s)'
                    }}
                </Badge>
            </div>

            <details
                v-if="!task.application.commissioned_path"
                class="group rounded-xl border bg-background"
            >
                <summary
                    class="flex cursor-pointer list-none items-center justify-between gap-3 p-3 text-sm font-medium"
                >
                    Recorded situational context
                    <ChevronRight
                        class="size-4 transition-transform group-open:rotate-90"
                        aria-hidden="true"
                    />
                </summary>
                <p class="border-t p-3 text-sm leading-6 text-muted-foreground">
                    {{ task.routing.situational_context }}
                </p>
            </details>

            <section
                v-if="isTreasuryActor && task.application.commissioned_path"
                class="grid w-full min-w-0 grid-cols-1 gap-5 rounded-xl border-2 border-primary/40 bg-primary/5 p-4 sm:p-5"
                aria-labelledby="treasury-classification-heading"
                data-testid="treasury-classification-workspace"
            >
                <div>
                    <p
                        class="text-xs font-bold tracking-[0.16em] text-primary uppercase"
                    >
                        Current task
                    </p>
                    <h3
                        id="treasury-classification-heading"
                        class="mt-1 text-xl font-black"
                    >
                        Assign official Lines of Business
                    </h3>
                    <p class="mt-2 text-sm leading-6 text-muted-foreground">
                        Classify the applicant’s frozen business description,
                        then review the payment items for each selected Line of
                        Business.
                    </p>
                </div>

                <div
                    class="min-w-0 rounded-lg border bg-background p-4 break-words"
                >
                    <p
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Applicant’s frozen business description
                    </p>
                    <p class="mt-2 text-sm leading-6 font-medium">
                        {{
                            task.application.business_activity_description ??
                            'Not recorded'
                        }}
                    </p>
                </div>

                <div class="max-w-full min-w-0">
                    <ApplicantDocumentReference :documents="documents" />
                </div>

                <div
                    v-if="task.financial_editor.treasury_assignments.length"
                    class="grid gap-3"
                >
                    <h4 class="font-bold">Assigned Lines of Business</h4>
                    <article
                        v-for="assignment in task.financial_editor
                            .treasury_assignments"
                        :key="assignment.id"
                        class="rounded-lg border bg-background p-4"
                    >
                        <strong>{{ assignment.name }}</strong>
                        <p
                            v-if="assignment.enterprise_determination"
                            class="text-sm break-words"
                        >
                            Enterprise Classification:
                            {{
                                assignment.enterprise_determination
                                    .classification
                            }}
                            · Treasury actor #{{
                                assignment.enterprise_determination
                                    .determined_by_id
                            }}
                            ·
                            {{
                                dateTime(
                                    assignment.enterprise_determination
                                        .determined_at,
                                )
                            }}<br />
                            Provisional UAT policy — pending Ipil Officer
                            confirmation ·
                            {{
                                assignment.enterprise_determination.schedule
                                    .version
                            }}
                        </p>
                        <p
                            v-for="item in assignment.items"
                            :key="item.name"
                            class="mt-2 flex justify-between gap-3 text-sm"
                        >
                            <span>{{ item.name }}</span>
                            <span class="font-semibold">{{
                                money(item.amount_cents)
                            }}</span>
                        </p>
                    </article>
                </div>

                <template v-else-if="allPaymentOrdersFinalized">
                    <div class="grid min-w-0 grid-cols-1 gap-2">
                        <label
                            for="treasury-line-of-business"
                            class="font-bold"
                        >
                            Select Line of Business
                        </label>
                        <Input
                            v-model="treasuryLobSearch"
                            type="search"
                            aria-label="Search Line of Business catalogue"
                            placeholder="Search the Ipil Line of Business catalogue"
                            autocomplete="off"
                        />
                        <div class="flex min-w-0 flex-col gap-2 sm:flex-row">
                            <select
                                id="treasury-line-of-business"
                                v-model="selectedTreasuryLob"
                                class="h-11 w-full min-w-0 flex-1 rounded-md border bg-background px-3 text-sm"
                            >
                                <option :value="null">
                                    Choose an official classification
                                </option>
                                <option
                                    v-for="line in filteredTreasuryLobOptions"
                                    :key="line.id"
                                    :value="line.id"
                                >
                                    {{ line.name }}
                                </option>
                            </select>
                            <Button
                                type="button"
                                variant="outline"
                                class="h-auto min-h-11 min-w-0 whitespace-normal"
                                :disabled="selectedTreasuryLob === null"
                                @click="addTreasuryLob"
                            >
                                Add Line of Business
                            </Button>
                        </div>
                    </div>

                    <div
                        v-if="treasurySelections.length"
                        class="grid min-w-0 grid-cols-1 gap-3 border-t border-primary/20 pt-5"
                    >
                        <h4 class="text-lg font-black">
                            Payment items for selected LOB
                        </h4>
                        <article
                            v-for="selection in treasurySelections"
                            :key="selection.line_of_business_id"
                            class="grid min-w-0 grid-cols-1 gap-3 rounded-lg border bg-background p-4"
                        >
                            <div
                                class="flex min-w-0 items-start justify-between gap-3"
                            >
                                <strong class="min-w-0 break-words">{{
                                    task.financial_editor.line_of_business_options.find(
                                        (line) =>
                                            line.id ===
                                            selection.line_of_business_id,
                                    )?.name
                                }}</strong>
                                <button
                                    type="button"
                                    class="shrink-0 text-xs font-semibold text-destructive"
                                    @click="
                                        removeTreasuryLob(
                                            selection.line_of_business_id,
                                        )
                                    "
                                >
                                    Remove
                                </button>
                            </div>
                            <EnterpriseClassificationSelector
                                v-if="
                                    enterpriseFee(selection.line_of_business_id)
                                        ?.enterprise_schedule
                                "
                                :schedule="
                                    enterpriseFee(
                                        selection.line_of_business_id,
                                    )!.enterprise_schedule!
                                "
                                :model-value="
                                    selection.enterprise_classification ?? ''
                                "
                                @update:model-value="
                                    determineEnterprise(selection, $event)
                                "
                            />
                            <FinancialLineItemEditor
                                v-model="selection.items"
                                :options="
                                    treasuryFeeOptions(
                                        selection.line_of_business_id,
                                    )
                                "
                            />
                        </article>
                    </div>

                    <Button
                        type="button"
                        size="lg"
                        class="h-auto min-h-11 w-full min-w-0 whitespace-normal"
                        :disabled="!treasurySelectionsReady"
                        @click="confirmTreasuryLobs"
                    >
                        Confirm Treasury
                    </Button>
                    <p
                        v-if="!treasurySelectionsReady"
                        class="text-xs text-amber-800 dark:text-amber-200"
                        data-testid="treasury-confirm-reason"
                        role="status"
                    >
                        {{ treasuryConfirmReason }}
                    </p>
                </template>

                <div
                    v-else
                    class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
                >
                    Treasury classification becomes available after every
                    concerned office finalizes its Payment Order.
                </div>
            </section>

            <details
                v-if="isTreasuryActor && task.application.commissioned_path"
                class="group rounded-lg border bg-background"
                data-testid="treasury-payment-order-reference"
            >
                <summary
                    class="flex cursor-pointer list-none items-center justify-between gap-3 p-3 text-sm font-medium"
                >
                    <span>
                        Payment Order reference
                        <span class="ml-1 text-muted-foreground">
                            {{ finalizedOfficeCount }} of
                            {{ task.routing.works.length }} finalized ·
                            {{ money(paymentOrderTotal) }}
                        </span>
                    </span>
                    <ChevronRight
                        class="size-4 shrink-0 transition-transform group-open:rotate-90"
                        aria-hidden="true"
                    />
                </summary>
                <div class="grid gap-3 border-t p-3 text-sm">
                    <article
                        v-for="work in task.routing.works"
                        :key="work.id"
                        class="grid gap-2 rounded-md border p-3"
                    >
                        <strong>{{ work.office_label }}</strong>
                        <div
                            v-for="order in work.payment_orders"
                            :key="order.id"
                            class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_8rem] sm:items-center"
                        >
                            <div
                                class="grid grid-cols-[1fr_auto] gap-x-3 gap-y-1"
                            >
                                <span>Payment Order {{ order.sequence }}</span>
                                <span class="font-semibold">{{
                                    money(order.total_amount_cents)
                                }}</span>
                                <span
                                    class="col-span-2 text-xs text-muted-foreground"
                                >
                                    Signed by
                                    {{ actorLabel(order.issued_by) }} ·
                                    {{ dateTime(order.issued_at) }}
                                </span>
                            </div>
                            <figure
                                v-if="order.signature_facsimile_data_url"
                                class="flex h-16 items-center justify-center rounded-md border bg-white px-2 py-1"
                            >
                                <img
                                    :src="order.signature_facsimile_data_url"
                                    :alt="`Captured signature facsimile of ${actorLabel(order.issued_by)}`"
                                    class="max-h-14 max-w-full object-contain"
                                />
                            </figure>
                        </div>
                        <span
                            v-if="work.payment_orders.length === 0"
                            class="text-muted-foreground"
                        >
                            Pending
                        </span>
                    </article>
                </div>
            </details>

            <details
                v-if="isTreasuryActor && task.application.commissioned_path"
                class="group rounded-lg border bg-background"
                data-testid="treasury-routing-decision"
            >
                <summary
                    class="flex cursor-pointer list-none items-center justify-between gap-3 p-3 text-sm font-medium"
                >
                    <span>
                        Routing decision
                        <span class="ml-1 text-muted-foreground">
                            {{ task.routing.works.length }} concerned offices
                            selected by BPLO
                        </span>
                    </span>
                    <ChevronRight
                        class="size-4 shrink-0 transition-transform group-open:rotate-90"
                        aria-hidden="true"
                    />
                </summary>
                <dl class="grid gap-3 border-t p-3 text-sm">
                    <div class="grid gap-1">
                        <dt class="font-semibold">Selected offices</dt>
                        <dd class="text-muted-foreground">
                            {{
                                task.routing.works
                                    .map((work) => work.office_label)
                                    .join(' · ')
                            }}
                        </dd>
                    </div>
                    <div class="grid gap-1">
                        <dt class="font-semibold">Recorded by</dt>
                        <dd class="text-muted-foreground">
                            {{ actorLabel(task.routing.determined_by) }} ·
                            {{ dateTime(task.routing.determined_at) }}
                        </dd>
                    </div>
                    <div class="grid gap-1">
                        <dt class="font-semibold">Reason</dt>
                        <dd class="text-muted-foreground">
                            {{ task.routing.situational_context }}
                        </dd>
                    </div>
                </dl>
            </details>

            <div
                v-if="!isTreasuryActor || !task.application.commissioned_path"
                data-testid="recorded-concerned-office-list"
                :class="
                    task.application.commissioned_path
                        ? 'divide-y rounded-xl border bg-background'
                        : 'grid gap-3'
                "
            >
                <article
                    v-for="work in displayedRoutingWorks"
                    :key="work.id"
                    :class="[
                        'min-w-0 p-4',
                        task.application.commissioned_path
                            ? activePaymentOrderWorkIds.has(work.id)
                                ? 'bg-primary/5'
                                : ''
                            : 'rounded-xl border bg-background',
                    ]"
                    data-testid="recorded-routing-work"
                >
                    <div class="flex flex-wrap justify-between gap-2">
                        <div>
                            <p class="font-black">{{ work.office_label }}</p>
                            <p
                                v-if="!task.application.commissioned_path"
                                class="text-xs text-muted-foreground"
                            >
                                {{
                                    work.line_of_business_name ??
                                    'Application-wide context'
                                }}
                            </p>
                        </div>
                        <Badge variant="outline">
                            <Check
                                v-if="
                                    task.application.commissioned_path &&
                                    work.payment_orders.length
                                "
                                class="size-3"
                                aria-hidden="true"
                            />
                            <span v-if="task.application.commissioned_path">
                                {{
                                    work.payment_orders.length
                                        ? 'Finalized'
                                        : activePaymentOrderWorkIds.has(work.id)
                                          ? 'Your task'
                                          : 'Pending'
                                }}
                            </span>
                            <span v-else>Routed</span>
                        </Badge>
                    </div>
                    <p
                        v-if="!task.application.commissioned_path"
                        class="mt-3 text-sm"
                    >
                        <strong>Required work:</strong> {{ work.required_work }}
                    </p>
                    <p
                        v-if="!task.application.commissioned_path"
                        class="mt-2 text-sm text-muted-foreground"
                    >
                        {{ work.situational_reason }}
                    </p>
                    <div
                        v-if="work.payment_orders.length"
                        class="mt-3 border-t pt-3 text-sm"
                    >
                        <p
                            v-for="order in work.payment_orders"
                            :key="order.id"
                            class="flex justify-between gap-3"
                        >
                            <span>Payment Order {{ order.sequence }}</span>
                            <strong>{{
                                money(order.total_amount_cents)
                            }}</strong>
                        </p>
                    </div>
                    <div
                        v-else-if="
                            task.financial_editor.authorized_payment_order_office_codes.includes(
                                work.office_code,
                            )
                        "
                        class="mt-4 grid gap-3 border-t pt-4"
                    >
                        <p
                            v-if="task.application.commissioned_path"
                            class="text-xs font-semibold text-muted-foreground"
                        >
                            Ipil source-backed fee menu
                        </p>
                        <section
                            v-if="work.office_code === 'menro'"
                            class="grid gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
                            aria-labelledby="menro-determination-heading"
                        >
                            <div>
                                <h3
                                    id="menro-determination-heading"
                                    class="font-black"
                                >
                                    Provisional MENRO determination
                                </h3>
                                <p class="mt-1 text-sm leading-6">
                                    {{
                                        task.financial_editor
                                            .menro_determination_proposal
                                            ?.warning ??
                                        'Synthetic-UAT evidence only; this is not municipal policy.'
                                    }}
                                </p>
                            </div>
                            <dl
                                v-if="task.financial_editor.menro_determination"
                                class="grid gap-2 text-sm sm:grid-cols-2"
                            >
                                <div>
                                    <dt class="font-semibold">
                                        Determination record ID
                                    </dt>
                                    <dd>
                                        #{{
                                            task.financial_editor
                                                .menro_determination.id
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-semibold">Scope</dt>
                                    <dd>
                                        {{
                                            task.financial_editor
                                                .menro_determination.scope ===
                                            'application'
                                                ? 'Application'
                                                : task.financial_editor
                                                      .menro_determination.scope
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-semibold">
                                        Source identity
                                    </dt>
                                    <dd>
                                        {{
                                            task.financial_editor
                                                .menro_determination
                                                .source_identity
                                        }}
                                    </dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="font-semibold">
                                        Canonical code
                                    </dt>
                                    <dd class="font-mono text-xs break-words">
                                        {{
                                            task.financial_editor
                                                .menro_determination.code
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-semibold">Basis</dt>
                                    <dd class="break-words">
                                        {{
                                            task.financial_editor
                                                .menro_determination.basis
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-semibold">Area basis</dt>
                                    <dd>
                                        {{
                                            task.financial_editor
                                                .menro_determination
                                                .application_area_square_meters
                                        }}
                                        m² /
                                        {{
                                            task.financial_editor.menro_determination.calculation_basis_centi_square_meters.toLocaleString()
                                        }}
                                        centi-square-meters
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-semibold">
                                        Operative range
                                    </dt>
                                    <dd>
                                        {{
                                            task.financial_editor.menro_determination.operative_range_min_centi_square_meters.toLocaleString()
                                        }}–{{
                                            task.financial_editor.menro_determination.operative_range_max_centi_square_meters.toLocaleString()
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-semibold">
                                        Provisional amount
                                    </dt>
                                    <dd>
                                        {{
                                            money(
                                                task.financial_editor
                                                    .menro_determination
                                                    .amount_minor,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-semibold">
                                        Schedule / version
                                    </dt>
                                    <dd class="break-words">
                                        {{
                                            task.financial_editor
                                                .menro_determination
                                                .schedule_version
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-semibold">
                                        Source evidence
                                    </dt>
                                    <dd>
                                        {{
                                            task.financial_editor
                                                .menro_determination
                                                .source_evidence
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-semibold">
                                        Classification
                                    </dt>
                                    <dd class="break-words">
                                        {{
                                            task.financial_editor
                                                .menro_determination
                                                .classification
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-semibold">
                                        Production authority
                                    </dt>
                                    <dd>
                                        {{
                                            task.financial_editor
                                                .menro_determination
                                                .production_authority
                                                ? 'Yes'
                                                : 'No'
                                        }}
                                    </dd>
                                </div>
                                <div class="sm:col-span-2">
                                    <dt class="font-semibold">Reason</dt>
                                    <dd class="break-words">
                                        {{
                                            task.financial_editor
                                                .menro_determination.reason
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-semibold">
                                        Recorded actor
                                    </dt>
                                    <dd class="break-words">
                                        {{
                                            task.financial_editor
                                                .menro_determination.actor ??
                                            'Not recorded'
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-semibold">
                                        Recorded timestamp
                                    </dt>
                                    <dd>
                                        {{
                                            dateTime(
                                                task.financial_editor
                                                    .menro_determination
                                                    .determined_at,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div class="sm:col-span-2">
                                    <dt class="font-semibold">Fingerprint</dt>
                                    <dd class="font-mono text-xs break-all">
                                        {{
                                            task.financial_editor
                                                .menro_determination.fingerprint
                                        }}
                                    </dd>
                                </div>
                                <div
                                    class="text-sm font-semibold sm:col-span-2"
                                >
                                    {{
                                        task.financial_editor
                                            .menro_determination.warning
                                    }}
                                </div>
                            </dl>
                            <template
                                v-else-if="
                                    task.financial_editor
                                        .can_record_menro_determination &&
                                    task.financial_editor
                                        .menro_determination_proposal
                                "
                            >
                                <dl class="grid gap-2 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt class="font-semibold">Scope</dt>
                                        <dd>
                                            {{
                                                task.financial_editor
                                                    .menro_determination_proposal
                                                    .scope_label
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold">
                                            Source identity
                                        </dt>
                                        <dd>
                                            {{
                                                task.financial_editor
                                                    .menro_determination_proposal
                                                    .source_identity
                                            }}
                                        </dd>
                                    </div>
                                    <div class="min-w-0">
                                        <dt class="font-semibold">
                                            Canonical code
                                        </dt>
                                        <dd
                                            class="font-mono text-xs break-words"
                                        >
                                            {{
                                                task.financial_editor
                                                    .menro_determination_proposal
                                                    .code
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold">Basis</dt>
                                        <dd class="break-words">
                                            {{
                                                task.financial_editor
                                                    .menro_determination_proposal
                                                    .basis
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold">
                                            Application area
                                        </dt>
                                        <dd>
                                            {{
                                                task.financial_editor
                                                    .menro_determination_proposal
                                                    .application_area_square_meters
                                            }}
                                            m²
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold">
                                            Calculation basis
                                        </dt>
                                        <dd>
                                            {{
                                                task.financial_editor.menro_determination_proposal.calculation_basis_centi_square_meters.toLocaleString()
                                            }}
                                            centi-square-meters
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold">
                                            Operative range
                                        </dt>
                                        <dd>
                                            {{
                                                task.financial_editor.menro_determination_proposal.operative_range_min_centi_square_meters.toLocaleString()
                                            }}–{{
                                                task.financial_editor.menro_determination_proposal.operative_range_max_centi_square_meters.toLocaleString()
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold">Amount</dt>
                                        <dd>
                                            {{
                                                money(
                                                    task.financial_editor
                                                        .menro_determination_proposal
                                                        .amount_minor,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold">
                                            Schedule / version
                                        </dt>
                                        <dd class="break-words">
                                            {{
                                                task.financial_editor
                                                    .menro_determination_proposal
                                                    .schedule_version
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold">
                                            Source evidence
                                        </dt>
                                        <dd>
                                            {{
                                                task.financial_editor
                                                    .menro_determination_proposal
                                                    .source_evidence
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold">
                                            Classification
                                        </dt>
                                        <dd class="break-words">
                                            {{
                                                task.financial_editor
                                                    .menro_determination_proposal
                                                    .classification
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold">
                                            Production authority
                                        </dt>
                                        <dd>
                                            {{
                                                task.financial_editor
                                                    .menro_determination_proposal
                                                    .production_authority
                                                    ? 'Yes'
                                                    : 'No'
                                            }}
                                        </dd>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <dt class="font-semibold">Reason</dt>
                                        <dd class="break-words">
                                            {{
                                                task.financial_editor
                                                    .menro_determination_proposal
                                                    .reason
                                            }}
                                        </dd>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <dt class="font-semibold">Actor</dt>
                                        <dd class="break-words">
                                            {{
                                                task.financial_editor
                                                    .menro_determination_proposal
                                                    .actor_statement
                                            }}
                                        </dd>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <dt class="font-semibold">Timestamp</dt>
                                        <dd class="break-words">
                                            {{
                                                task.financial_editor
                                                    .menro_determination_proposal
                                                    .timestamp_statement
                                            }}
                                        </dd>
                                    </div>
                                </dl>
                                <Button
                                    type="button"
                                    variant="outline"
                                    :disabled="menroDeterminationPending"
                                    @click="recordProvisionalMenroDetermination"
                                >
                                    {{
                                        menroDeterminationPending
                                            ? 'Recording determination…'
                                            : 'Record Provisional Determination'
                                    }}
                                </Button>
                            </template>
                        </section>
                        <FinancialLineItemEditor
                            v-model="officeItems[work.id]"
                            :options="
                                task.financial_editor.office_fee_options[
                                    work.office_code
                                ] ?? []
                            "
                        />
                        <SignatureFacsimileCapture
                            :required="true"
                            @selected="officeSignatures[work.id] = $event"
                        />
                        <Button
                            type="button"
                            :disabled="
                                officePaymentOrderPending[work.id] ||
                                !(
                                    officeItems[work.id]?.length &&
                                    officeSignatures[work.id]
                                )
                            "
                            @click="confirmPaymentOrder(work)"
                            >{{
                                officePaymentOrderPending[work.id]
                                    ? 'Confirming Payment Order…'
                                    : 'Sign & Confirm Payment Order'
                            }}</Button
                        >
                        <p
                            v-if="officePaymentOrderMessages[work.id]"
                            role="alert"
                            class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
                        >
                            {{ officePaymentOrderMessages[work.id] }}
                        </p>
                    </div>
                </article>
            </div>

            <details
                v-if="task.application.commissioned_path && !isTreasuryActor"
                class="group rounded-lg border bg-background"
            >
                <summary
                    class="flex cursor-pointer list-none items-center justify-between gap-3 p-3 text-sm font-medium"
                >
                    Routing record
                    <ChevronRight
                        class="size-4 transition-transform group-open:rotate-90"
                        aria-hidden="true"
                    />
                </summary>
                <p class="border-t p-3 text-sm text-muted-foreground">
                    {{ task.routing.determined_by }} ·
                    {{ dateTime(task.routing.determined_at) }} ·
                    {{ task.routing.works.length }} offices
                </p>
            </details>

            <section
                v-if="
                    !task.application.commissioned_path &&
                    task.financial_editor.can_assign_treasury_lobs &&
                    task.routing.works.every(
                        (work) => work.payment_orders.length > 0,
                    )
                "
                class="grid gap-3 rounded-xl border bg-background p-4"
            >
                <h3 class="font-black">Treasury Lines of Business</h3>
                <div
                    v-if="task.financial_editor.treasury_assignments.length"
                    class="grid gap-2 text-sm"
                >
                    <div
                        v-for="assignment in task.financial_editor
                            .treasury_assignments"
                        :key="assignment.id"
                        class="border-b pb-2"
                    >
                        <strong>{{ assignment.name }}</strong>
                        <p
                            v-if="assignment.enterprise_determination"
                            class="text-sm break-words"
                        >
                            Enterprise Classification:
                            {{
                                assignment.enterprise_determination
                                    .classification
                            }}
                            · Provisional UAT policy — pending Ipil Officer
                            confirmation ·
                            {{
                                assignment.enterprise_determination.schedule
                                    .version
                            }}
                        </p>
                        <p
                            v-for="item in assignment.items"
                            :key="item.name"
                            class="flex justify-between"
                        >
                            <span>{{ item.name }}</span
                            ><span>{{ money(item.amount_cents) }}</span>
                        </p>
                    </div>
                </div>
                <template v-else>
                    <div class="grid gap-2">
                        <Input
                            v-model="treasuryLobSearch"
                            type="search"
                            aria-label="Search Line of Business catalogue"
                            placeholder="Search Line of Business"
                            autocomplete="off"
                        />
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <select
                                v-model="selectedTreasuryLob"
                                class="h-9 min-w-0 flex-1 rounded-md border bg-background px-3 text-sm"
                            >
                                <option :value="null">
                                    Select Line of Business
                                </option>
                                <option
                                    v-for="line in filteredTreasuryLobOptions"
                                    :key="line.id"
                                    :value="line.id"
                                >
                                    {{ line.name }}
                                </option>
                            </select>
                            <Button
                                type="button"
                                variant="outline"
                                @click="addTreasuryLob"
                                >Add</Button
                            >
                        </div>
                    </div>
                    <div
                        v-for="selection in treasurySelections"
                        :key="selection.line_of_business_id"
                        class="grid gap-2 border-b py-3 text-sm"
                    >
                        <div class="flex justify-between">
                            <strong>{{
                                task.financial_editor.line_of_business_options.find(
                                    (line) =>
                                        line.id ===
                                        selection.line_of_business_id,
                                )?.name
                            }}</strong>
                            <button
                                type="button"
                                class="text-xs text-destructive"
                                @click="
                                    removeTreasuryLob(
                                        selection.line_of_business_id,
                                    )
                                "
                            >
                                Remove LOB
                            </button>
                        </div>
                        <EnterpriseClassificationSelector
                            v-if="
                                enterpriseFee(selection.line_of_business_id)
                                    ?.enterprise_schedule
                            "
                            :schedule="
                                enterpriseFee(selection.line_of_business_id)!
                                    .enterprise_schedule!
                            "
                            :model-value="
                                selection.enterprise_classification ?? ''
                            "
                            @update:model-value="
                                determineEnterprise(selection, $event)
                            "
                        />
                        <FinancialLineItemEditor
                            v-model="selection.items"
                            :options="
                                treasuryFeeOptions(
                                    selection.line_of_business_id,
                                )
                            "
                        />
                    </div>
                    <Button
                        type="button"
                        :disabled="!treasurySelectionsReady"
                        @click="confirmTreasuryLobs"
                        >Confirm Treasury</Button
                    >
                    <p
                        v-if="!treasurySelectionsReady"
                        class="text-xs text-amber-800 dark:text-amber-200"
                        data-testid="treasury-confirm-reason"
                        role="status"
                    >
                        {{ treasuryConfirmReason }}
                    </p>
                </template>
            </section>

            <p
                v-if="!task.application.commissioned_path"
                class="text-xs leading-5 text-muted-foreground"
            >
                This routing record assigns office work only. It creates no
                office approval, fee determination, Assessment, or payment
                authority.
            </p>
        </div>

        <form
            v-else-if="task.can_determine"
            class="grid gap-4 p-4 sm:p-5"
            @submit.prevent="submit"
        >
            <div
                v-if="task.suggestion && !task.application.commissioned_path"
                class="grid gap-3 rounded-xl border border-blue-300 bg-blue-50 p-4 text-blue-950 dark:border-blue-800 dark:bg-blue-950/35 dark:text-blue-100"
            >
                <div class="flex items-start gap-3">
                    <FileClock
                        class="mt-0.5 size-5 shrink-0"
                        aria-hidden="true"
                    />
                    <div>
                        <p class="font-black">Routing suggestion ready</p>
                        <p class="mt-1 text-sm leading-6">
                            Review every selected office. The suggestion is not
                            a municipal determination.
                        </p>
                    </div>
                </div>
                <div
                    class="flex flex-wrap items-center justify-between gap-2 text-xs"
                >
                    <span>Profile {{ task.suggestion.profile_version }}</span>
                    <strong v-if="task.manual_confirmation_required">
                        Manual cleanroom confirmation required
                    </strong>
                    <strong v-else-if="countdown">{{ countdown }}</strong>
                </div>
            </div>

            <div
                v-if="!task.application.commissioned_path"
                class="rounded-xl bg-muted/40 p-4 text-sm"
            >
                <p class="font-black">Application</p>
                <p class="mt-1 text-muted-foreground">
                    {{ task.application.owner_name }} ·
                    {{ task.application.lines.length }} declared activities
                </p>
            </div>

            <label
                v-if="!task.application.commissioned_path"
                class="grid gap-1 text-sm font-semibold"
            >
                Situational context
                <textarea
                    v-model="routingContext"
                    required
                    rows="3"
                    class="rounded-md border bg-background px-3 py-2 font-normal"
                    placeholder="Record the application circumstances BPLO considered."
                />
            </label>

            <div
                v-if="!task.application.commissioned_path"
                class="flex flex-wrap items-center gap-3"
            >
                <Button
                    type="button"
                    variant="outline"
                    data-testid="apply-routing-defaults"
                    @click="applySuggestedRouting"
                >
                    <Route class="size-4" aria-hidden="true" />
                    {{
                        task.suggestion
                            ? 'Reapply suggested routing'
                            : 'Select all office checks'
                    }}
                </Button>
                <p class="text-xs text-muted-foreground">
                    Draft only until BPLO records the routing.
                </p>
            </div>

            <fieldset
                v-if="task.application.commissioned_path"
                :disabled="
                    pending || routingAcknowledged || routingOutcomeUnconfirmed
                "
                data-testid="concerned-office-checklist"
                class="divide-y rounded-xl border px-4"
            >
                <legend class="sr-only">Concerned offices</legend>
                <label
                    v-for="candidate in candidates"
                    :key="candidate.key"
                    data-testid="concerned-office-option"
                    class="flex min-h-14 cursor-pointer items-center gap-3 py-3 font-bold"
                >
                    <input
                        v-model="drafts[candidate.key].selected"
                        type="checkbox"
                        class="size-5 shrink-0"
                    />
                    <span class="min-w-0 break-words">
                        {{ candidate.office.label }}
                    </span>
                </label>
            </fieldset>

            <template v-else>
                <fieldset
                    v-for="group in officeGroups"
                    :key="group.office.code"
                    :disabled="
                        pending ||
                        routingAcknowledged ||
                        routingOutcomeUnconfirmed
                    "
                    class="grid gap-3 rounded-xl border p-4"
                >
                    <legend class="px-1 font-black">
                        {{ group.office.label }}
                    </legend>
                    <div
                        v-for="candidate in group.candidates"
                        :key="candidate.key"
                        class="grid gap-3 border-t pt-3 first:border-t-0 first:pt-0"
                    >
                        <label
                            class="flex min-h-11 items-center gap-3 font-semibold"
                        >
                            <input
                                v-model="drafts[candidate.key].selected"
                                type="checkbox"
                            />
                            <span class="min-w-0 break-words">
                                {{
                                    candidate.line.line_of_business_name ??
                                    'Concerned office'
                                }}
                            </span>
                        </label>
                        <template v-if="drafts[candidate.key].selected">
                            <label class="grid gap-1 text-sm">
                                Situational reason
                                <textarea
                                    v-model="drafts[candidate.key].reason"
                                    required
                                    rows="2"
                                    class="rounded-md border bg-background px-3 py-2"
                                />
                            </label>
                            <label class="grid gap-1 text-sm">
                                Required office work
                                <textarea
                                    v-model="drafts[candidate.key].requiredWork"
                                    required
                                    rows="2"
                                    class="rounded-md border bg-background px-3 py-2"
                                />
                            </label>
                        </template>
                    </div>
                </fieldset>
            </template>

            <p
                v-if="page.props.errors.routing"
                class="text-sm text-destructive"
            >
                {{ page.props.errors.routing }}
            </p>
            <p
                v-if="routingMessage"
                role="status"
                class="text-sm text-muted-foreground"
            >
                {{ routingMessage }}
            </p>
            <a
                v-if="routingAcknowledged"
                :href="workInbox.url()"
                class="font-semibold underline"
            >
                Return to My Work
            </a>
            <Button
                v-if="routingOutcomeUnconfirmed"
                type="button"
                variant="outline"
                @click="reviewRoutingRecord"
            >
                Reload routing record
            </Button>

            <div
                class="sticky bottom-0 -mx-4 flex flex-col gap-3 border-t bg-white/95 px-4 py-3 backdrop-blur sm:flex-row sm:items-center sm:justify-between dark:bg-slate-900/95"
            >
                <p class="text-sm font-semibold">
                    {{ selectedCount }} selected
                </p>
                <Button
                    type="submit"
                    :disabled="
                        pending ||
                        routingAcknowledged ||
                        routingOutcomeUnconfirmed ||
                        selectedCount === 0
                    "
                    data-testid="confirm-bplo-routing"
                >
                    {{
                        pending
                            ? 'Confirming routing…'
                            : task.application.commissioned_path
                              ? 'Confirm Routing'
                              : 'Record BPLO routing'
                    }}
                </Button>
            </div>
        </form>

        <div v-else class="p-5 text-sm text-muted-foreground">
            Routing remains visible on Application Page 2. Only an authorized
            BPLO actor can record the determination.
        </div>
    </section>
</template>
