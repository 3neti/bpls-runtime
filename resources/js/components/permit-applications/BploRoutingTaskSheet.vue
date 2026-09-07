<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { Check, ChevronRight, FileClock, Route } from '@lucide/vue';
import { useNow } from '@vueuse/core';
import { computed, reactive, ref } from 'vue';
import { store as recordBploRouting } from '@/actions/App/Http/Controllers/Staff/BploRoutingDeterminationController';
import FinancialLineItemEditor from '@/components/permit-applications/FinancialLineItemEditor.vue';
import SignatureFacsimileCapture from '@/components/SignatureFacsimileCapture.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dateTime, money } from '@/lib/evaluationPresentation';

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
            }[]
        >;
        line_of_business_options: {
            id: number;
            code: string;
            name: string;
            default_items: {
                fee_rule_id: number;
                code: string;
                name: string;
                amount_cents: number;
            }[];
        }[];
        treasury_assignments: {
            id: number;
            name: string;
            items: { name: string; amount_cents: number }[];
        }[];
        can_confirm_payment_orders: boolean;
        can_assign_treasury_lobs: boolean;
    };
    can_determine: boolean;
    manual_confirmation_required: boolean;
};

const props = withDefaults(
    defineProps<{
        task: BploRoutingTask;
        mode?: 'embedded' | 'sheet';
    }>(),
    { mode: 'sheet' },
);

const page = usePage();
const pending = ref(false);
const officeItems = reactive<
    Record<
        number,
        {
            fee_rule_id: number;
            code: string;
            name: string;
            amount_cents: number;
        }[]
    >
>({});
const officeSignatures = reactive<Record<number, File | null>>({});
const treasurySelections = ref<
    {
        line_of_business_id: number;
        items: {
            fee_rule_id: number;
            code: string;
            name: string;
            amount_cents: number;
        }[];
    }[]
>([]);
const selectedTreasuryLob = ref<number | null>(null);

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

function submit(): void {
    if (pending.value || selectedCount.value === 0) {
        return;
    }

    pending.value = true;
    useForm({
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
    }).post(recordBploRouting(props.task.application.id).url, {
        preserveScroll: true,
        onFinish: () => {
            pending.value = false;
        },
    });
}

function confirmPaymentOrder(work: RoutingWork): void {
    const signature = officeSignatures[work.id];
    const items = officeItems[work.id] ?? [];

    if (!signature || items.length === 0) {
        return;
    }

    useForm({ items, signature_facsimile: signature }).post(
        `/staff/permit-applications/${props.task.application.id}/office-payment-orders/${work.id}`,
        { preserveScroll: true },
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

    treasurySelections.value.push({
        line_of_business_id: option.id,
        items: option.default_items.map((item) => ({ ...item })),
    });
    selectedTreasuryLob.value = null;
}

function confirmTreasuryLobs(): void {
    if (treasurySelections.value.length === 0) {
        return;
    }

    useForm({ selections: treasurySelections.value }).post(
        `/staff/permit-applications/${props.task.application.id}/treasury-lines-of-business`,
        { preserveScroll: true },
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
    ).map((item) => ({
        id: item.fee_rule_id,
        code: item.code,
        name: item.name,
        default_amount_cents: item.amount_cents,
    }));
}
</script>

<template>
    <section
        data-testid="bplo-routing-task-sheet"
        :data-task-mode="mode"
        class="min-w-0 overflow-hidden rounded-xl border-2 border-[#1f416b]/35 bg-white shadow-sm dark:bg-slate-900"
        aria-labelledby="bplo-routing-task-title"
    >
        <header class="bg-[#1f416b] px-4 py-4 text-white sm:px-5">
            <p class="text-xs font-bold tracking-[0.18em] uppercase">
                BPLO task sheet
            </p>
            <h2 id="bplo-routing-task-title" class="mt-1 text-xl font-black">
                {{
                    task.routing
                        ? task.application.commissioned_path
                            ? 'Routing confirmed'
                            : 'Concerned-office routing recorded'
                        : 'Record concerned-office routing'
                }}
            </h2>
            <p class="mt-1 text-sm text-white/80">
                {{ task.application.business_name }} ·
                {{ task.application.year }} {{ task.application.type }}
            </p>
        </header>

        <div v-if="task.routing" class="grid gap-4 p-4 sm:p-5">
            <div
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

            <div
                data-testid="recorded-concerned-office-list"
                :class="
                    task.application.commissioned_path
                        ? 'divide-y rounded-xl border bg-background'
                        : 'grid gap-3'
                "
            >
                <article
                    v-for="work in task.routing.works"
                    :key="work.id"
                    :class="[
                        'min-w-0 p-4',
                        task.application.commissioned_path
                            ? ''
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
                                v-if="task.application.commissioned_path"
                                class="size-3"
                                aria-hidden="true"
                            />
                            <span
                                :class="{
                                    'sr-only':
                                        task.application.commissioned_path,
                                }"
                                >Routed</span
                            >
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
                            task.financial_editor.can_confirm_payment_orders
                        "
                        class="mt-4 grid gap-3 border-t pt-4"
                    >
                        <p
                            v-if="
                                task.application.commissioned_path &&
                                task.financial_editor.catalog_status ===
                                    'awaiting_nelson_source'
                            "
                            class="text-xs font-semibold text-amber-700 dark:text-amber-300"
                        >
                            Synthetic preview fee menu · awaiting Nelson source
                        </p>
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
                                !(
                                    officeItems[work.id]?.length &&
                                    officeSignatures[work.id]
                                )
                            "
                            @click="confirmPaymentOrder(work)"
                            >Confirm Payment Order</Button
                        >
                    </div>
                </article>
            </div>

            <section
                v-if="
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
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <select
                            v-model="selectedTreasuryLob"
                            class="h-9 min-w-0 flex-1 rounded-md border bg-background px-3 text-sm"
                        >
                            <option :value="null">
                                Select Line of Business
                            </option>
                            <option
                                v-for="line in task.financial_editor
                                    .line_of_business_options"
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
                        :disabled="treasurySelections.length === 0"
                        @click="confirmTreasuryLobs"
                        >Confirm Treasury Classification</Button
                    >
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

            <div
                class="sticky bottom-0 -mx-4 flex flex-col gap-3 border-t bg-white/95 px-4 py-3 backdrop-blur sm:flex-row sm:items-center sm:justify-between dark:bg-slate-900/95"
            >
                <p class="text-sm font-semibold">
                    {{ selectedCount }} selected
                </p>
                <Button
                    type="submit"
                    :disabled="pending || selectedCount === 0"
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
