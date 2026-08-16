<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, FilePlus2, Plus, Trash2 } from '@lucide/vue';
import {
    index,
    show,
} from '@/actions/App/Http/Controllers/Staff/BillingGroupController';
import { store as storeReconciliation } from '@/actions/App/Http/Controllers/Staff/BillingGroupReconciliationController';
import { store } from '@/actions/App/Http/Controllers/Staff/BillingGroupRecordController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type BillingGroupField = {
    id: number;
    key: string;
    name: string;
    field_type: string;
    is_required: boolean;
    is_unique: boolean;
    options: string[];
    placeholder: string | null;
    default_value: string | null;
};

type BillingGroupRecord = {
    id: number;
    draft_reference: string;
    status: string;
    description: string | null;
    record_date: string | null;
    payor_name: string | null;
    field_values: Record<string, string>;
    financial_readiness: {
        status: string;
        can_create_liability: boolean;
        can_collect: boolean;
        can_issue_receipt: boolean;
        blocked_by: string[];
        missing_required_fields: string[];
        requirements: Array<{
            key: string;
            label: string;
            status: string;
            reason: string;
        }>;
        reason: string;
    };
    created_by: string;
    created_at: string | null;
};

type BillingGroup = {
    id: number;
    name: string;
    description: string | null;
    acceptance_status: string;
    is_active: boolean;
    fields: BillingGroupField[];
    records: BillingGroupRecord[];
    reconciliations: Array<{
        id: number;
        version: number;
        evidence_type: string;
        evidence_reference: string;
        source_excerpt: string | null;
        operational_interpretation: string | null;
        unresolved_questions: string[];
        reconciliation_status: string;
        execution_status: string;
        execution_reason: string;
        recorded_by: string;
        created_at: string | null;
    }>;
};

const props = defineProps<{
    billingGroup: BillingGroup;
    can: {
        create_record: boolean;
        record_reconciliation_evidence: boolean;
    };
    evidenceTypes: Array<{ value: string; label: string }>;
    policyNote: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Billing Groups', href: index() },
    { title: props.billingGroup.name, href: show(props.billingGroup.id) },
];

const form = useForm<{
    description: string;
    record_date: string;
    payor_name: string;
    field_values: Record<string, string>;
}>({
    description: '',
    record_date: '',
    payor_name: '',
    field_values: Object.fromEntries(
        props.billingGroup.fields.map((field) => [
            field.key,
            field.default_value ?? '',
        ]),
    ),
});

function submit(): void {
    form.post(store.url(props.billingGroup.id), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function fieldLabel(value: string): string {
    return value.replaceAll('_', ' ');
}

const reconciliationForm = useForm<{
    evidence_type: string;
    evidence_reference: string;
    source_excerpt: string;
    operational_interpretation: string;
    unresolved_questions: string[];
}>({
    evidence_type: props.evidenceTypes[0]?.value ?? '',
    evidence_reference: '',
    source_excerpt: '',
    operational_interpretation: '',
    unresolved_questions: [''],
});

function addUnresolvedQuestion(): void {
    reconciliationForm.unresolved_questions.push('');
}

function removeUnresolvedQuestion(index: number): void {
    if (reconciliationForm.unresolved_questions.length > 1) {
        reconciliationForm.unresolved_questions.splice(index, 1);
    }
}

function submitReconciliationEvidence(): void {
    reconciliationForm.post(storeReconciliation.url(props.billingGroup.id), {
        preserveScroll: true,
        onSuccess: () => reconciliationForm.reset(),
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="billingGroup.name" />

        <main class="flex h-full flex-1 flex-col gap-4 p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-xl font-semibold text-foreground">
                            {{ billingGroup.name }}
                        </h1>
                        <span
                            class="border border-amber-500/50 px-2 py-0.5 text-xs text-amber-800 dark:text-amber-200"
                        >
                            {{ billingGroup.acceptance_status }}
                        </span>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{
                            billingGroup.description ||
                            'No description recorded.'
                        }}
                    </p>
                </div>
                <Button as-child variant="outline">
                    <Link :href="index()"
                        ><ArrowLeft /> Back to billing groups</Link
                    >
                </Button>
            </section>

            <section
                class="border-l-4 border-amber-500 bg-amber-50 p-4 text-sm text-amber-950 dark:bg-amber-950/30 dark:text-amber-100"
                data-testid="billing-record-policy-boundary"
            >
                {{ policyNote }}
            </section>

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_28rem]">
                <section
                    class="overflow-hidden border border-sidebar-border/70 bg-background dark:border-sidebar-border"
                >
                    <div class="border-b border-sidebar-border/70 p-4">
                        <h2 class="font-semibold">Draft records</h2>
                    </div>
                    <div
                        v-if="billingGroup.records.length === 0"
                        class="flex min-h-56 flex-col items-center justify-center gap-2 p-8 text-center"
                    >
                        <FilePlus2 class="size-9 text-muted-foreground" />
                        <p class="text-sm text-muted-foreground">
                            No draft records prepared.
                        </p>
                    </div>
                    <div v-else class="divide-y divide-sidebar-border/70">
                        <article
                            v-for="record in billingGroup.records"
                            :key="record.id"
                            class="grid gap-3 p-4"
                            data-testid="billing-group-draft-record"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <strong class="font-mono text-sm">{{
                                    record.draft_reference
                                }}</strong>
                                <span class="border px-2 py-0.5 text-xs">{{
                                    record.status
                                }}</span>
                            </div>
                            <div class="grid gap-2 text-sm sm:grid-cols-2">
                                <p>
                                    <span class="text-muted-foreground"
                                        >Payor:</span
                                    >
                                    {{ record.payor_name || 'Not recorded' }}
                                </p>
                                <p>
                                    <span class="text-muted-foreground"
                                        >Record date:</span
                                    >
                                    {{ record.record_date || 'Not recorded' }}
                                </p>
                                <p class="sm:col-span-2">
                                    <span class="text-muted-foreground"
                                        >Description:</span
                                    >
                                    {{ record.description || 'Not recorded' }}
                                </p>
                            </div>
                            <dl
                                v-if="Object.keys(record.field_values).length"
                                class="grid gap-2 border-t pt-3 text-sm sm:grid-cols-2"
                            >
                                <div
                                    v-for="(value, key) in record.field_values"
                                    :key="key"
                                >
                                    <dt class="text-xs text-muted-foreground">
                                        {{ fieldLabel(String(key)) }}
                                    </dt>
                                    <dd>{{ value }}</dd>
                                </div>
                            </dl>
                            <p class="text-xs text-muted-foreground">
                                Prepared by {{ record.created_by }}
                            </p>
                            <section
                                class="grid gap-3 border-l-4 border-amber-500 bg-amber-50 p-3 text-sm text-amber-950 dark:bg-amber-950/30 dark:text-amber-100"
                                data-testid="billing-group-financial-readiness"
                                :data-readiness-status="
                                    record.financial_readiness.status
                                "
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <strong>Financial readiness</strong>
                                    <span
                                        class="border border-amber-500/50 px-2 py-0.5 text-xs uppercase"
                                    >
                                        {{ record.financial_readiness.status }}
                                    </span>
                                </div>
                                <p>{{ record.financial_readiness.reason }}</p>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div
                                        v-for="requirement in record
                                            .financial_readiness.requirements"
                                        :key="requirement.key"
                                        class="border border-amber-500/30 bg-background/60 p-2"
                                        :data-requirement="requirement.key"
                                        :data-status="requirement.status"
                                    >
                                        <div
                                            class="flex items-start justify-between gap-2"
                                        >
                                            <span class="font-medium">{{
                                                requirement.label
                                            }}</span>
                                            <span
                                                class="text-xs text-muted-foreground uppercase"
                                                >{{ requirement.status }}</span
                                            >
                                        </div>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{ requirement.reason }}
                                        </p>
                                    </div>
                                </div>
                                <p class="text-xs font-medium">
                                    Liability, collection, and receipt actions
                                    are unavailable.
                                </p>
                            </section>
                        </article>
                    </div>
                </section>

                <form
                    v-if="can.create_record && billingGroup.is_active"
                    class="grid content-start gap-4 border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                    data-testid="billing-group-draft-form"
                    @submit.prevent="submit"
                >
                    <div>
                        <h2 class="font-semibold">Prepare draft record</h2>
                        <p class="text-xs text-muted-foreground">
                            Incomplete values are allowed. This action has no
                            financial effect.
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="payor_name">Payor name</Label>
                        <Input id="payor_name" v-model="form.payor_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="record_date">Record date</Label>
                        <Input
                            id="record_date"
                            v-model="form.record_date"
                            type="date"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="record_description">Description</Label>
                        <textarea
                            id="record_description"
                            v-model="form.description"
                            rows="3"
                            class="w-full border border-input bg-background px-3 py-2 text-sm"
                        />
                    </div>

                    <div
                        v-for="field in billingGroup.fields"
                        :key="field.id"
                        class="grid gap-2"
                    >
                        <Label :for="`field_${field.key}`">
                            {{ field.name }}
                            <span
                                v-if="field.is_required"
                                class="text-xs text-muted-foreground"
                                >(required later)</span
                            >
                        </Label>
                        <select
                            v-if="field.field_type === 'dropdown'"
                            :id="`field_${field.key}`"
                            v-model="form.field_values[field.key]"
                            class="h-9 border border-input bg-background px-3 text-sm"
                        >
                            <option value="">Not recorded</option>
                            <option
                                v-for="option in field.options"
                                :key="option"
                                :value="option"
                            >
                                {{ option }}
                            </option>
                        </select>
                        <select
                            v-else-if="field.field_type === 'checkbox'"
                            :id="`field_${field.key}`"
                            v-model="form.field_values[field.key]"
                            class="h-9 border border-input bg-background px-3 text-sm"
                        >
                            <option value="">Not recorded</option>
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                        <Input
                            v-else
                            :id="`field_${field.key}`"
                            v-model="form.field_values[field.key]"
                            :type="
                                field.field_type === 'date'
                                    ? 'date'
                                    : field.field_type === 'number' ||
                                        field.field_type === 'currency'
                                      ? 'number'
                                      : 'text'
                            "
                            :step="
                                field.field_type === 'currency'
                                    ? '0.01'
                                    : undefined
                            "
                            :placeholder="field.placeholder || undefined"
                        />
                        <p
                            v-if="form.errors[`field_values.${field.key}`]"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors[`field_values.${field.key}`] }}
                        </p>
                    </div>

                    <p
                        v-if="form.errors.field_values"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.field_values }}
                    </p>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Preparing…' : 'Prepare draft' }}
                    </Button>
                </form>
            </div>

            <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_28rem]">
                <div
                    class="overflow-hidden border border-sidebar-border/70 bg-background dark:border-sidebar-border"
                >
                    <div class="border-b border-sidebar-border/70 p-4">
                        <h2 class="font-semibold">
                            Reconciliation evidence history
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Immutable evidence versions support a later
                            municipal decision. They do not authorize execution.
                        </p>
                    </div>
                    <div
                        v-if="billingGroup.reconciliations.length === 0"
                        class="p-6 text-sm text-muted-foreground"
                        data-testid="billing-group-no-reconciliation-evidence"
                    >
                        No reconciliation evidence recorded.
                    </div>
                    <div v-else class="divide-y divide-sidebar-border/70">
                        <article
                            v-for="reconciliation in billingGroup.reconciliations"
                            :key="reconciliation.id"
                            class="grid gap-3 p-4"
                            data-testid="billing-group-reconciliation-evidence"
                            :data-version="reconciliation.version"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-2"
                            >
                                <div>
                                    <strong class="text-sm"
                                        >Evidence version
                                        {{ reconciliation.version }}</strong
                                    >
                                    <p class="text-xs text-muted-foreground">
                                        {{
                                            fieldLabel(
                                                reconciliation.evidence_type,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2 text-xs">
                                    <span class="border px-2 py-0.5">{{
                                        fieldLabel(
                                            reconciliation.reconciliation_status,
                                        )
                                    }}</span>
                                    <span
                                        class="border border-amber-500/50 px-2 py-0.5 text-amber-800 dark:text-amber-200"
                                        >{{
                                            reconciliation.execution_status
                                        }}</span
                                    >
                                </div>
                            </div>
                            <div class="grid gap-1 text-sm">
                                <p>
                                    <span class="text-muted-foreground"
                                        >Reference:</span
                                    >
                                    {{ reconciliation.evidence_reference }}
                                </p>
                                <p v-if="reconciliation.source_excerpt">
                                    <span class="text-muted-foreground"
                                        >Source evidence:</span
                                    >
                                    {{ reconciliation.source_excerpt }}
                                </p>
                                <p
                                    v-if="
                                        reconciliation.operational_interpretation
                                    "
                                >
                                    <span class="text-muted-foreground"
                                        >Candidate interpretation:</span
                                    >
                                    {{
                                        reconciliation.operational_interpretation
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs font-medium">
                                    Unresolved questions
                                </p>
                                <ul
                                    class="mt-1 list-disc space-y-1 pl-5 text-sm text-muted-foreground"
                                >
                                    <li
                                        v-for="question in reconciliation.unresolved_questions"
                                        :key="question"
                                    >
                                        {{ question }}
                                    </li>
                                </ul>
                            </div>
                            <p
                                class="border-l-2 border-amber-500 pl-3 text-xs text-muted-foreground"
                            >
                                {{ reconciliation.execution_reason }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Recorded by {{ reconciliation.recorded_by }}
                            </p>
                        </article>
                    </div>
                </div>

                <form
                    v-if="can.record_reconciliation_evidence"
                    class="grid content-start gap-4 border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                    data-testid="billing-group-reconciliation-form"
                    @submit.prevent="submitReconciliationEvidence"
                >
                    <div>
                        <h2 class="font-semibold">
                            Record reconciliation evidence
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            This preserves evidence only. It cannot accept the
                            definition or enable financial execution.
                        </p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="evidence_type">Evidence type</Label>
                        <select
                            id="evidence_type"
                            v-model="reconciliationForm.evidence_type"
                            class="h-9 border border-input bg-background px-3 text-sm"
                        >
                            <option
                                v-for="type in evidenceTypes"
                                :key="type.value"
                                :value="type.value"
                            >
                                {{ type.label }}
                            </option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="evidence_reference"
                            >Evidence reference</Label
                        >
                        <Input
                            id="evidence_reference"
                            v-model="reconciliationForm.evidence_reference"
                            required
                        />
                        <p
                            v-if="reconciliationForm.errors.evidence_reference"
                            class="text-sm text-destructive"
                        >
                            {{ reconciliationForm.errors.evidence_reference }}
                        </p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="source_excerpt">Source evidence</Label>
                        <textarea
                            id="source_excerpt"
                            v-model="reconciliationForm.source_excerpt"
                            rows="3"
                            class="w-full border border-input bg-background px-3 py-2 text-sm"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="operational_interpretation"
                            >Candidate operational interpretation</Label
                        >
                        <textarea
                            id="operational_interpretation"
                            v-model="
                                reconciliationForm.operational_interpretation
                            "
                            rows="3"
                            class="w-full border border-input bg-background px-3 py-2 text-sm"
                        />
                    </div>
                    <div class="grid gap-2">
                        <div class="flex items-center justify-between gap-2">
                            <Label>Unresolved questions</Label>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="addUnresolvedQuestion"
                            >
                                <Plus /> Add question
                            </Button>
                        </div>
                        <div
                            v-for="(
                                _, index
                            ) in reconciliationForm.unresolved_questions"
                            :key="index"
                            class="flex items-center gap-2"
                        >
                            <Input
                                v-model="
                                    reconciliationForm.unresolved_questions[
                                        index
                                    ]
                                "
                                :aria-label="`Unresolved question ${index + 1}`"
                                required
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :disabled="
                                    reconciliationForm.unresolved_questions
                                        .length === 1
                                "
                                :aria-label="`Remove unresolved question ${index + 1}`"
                                title="Remove question"
                                @click="removeUnresolvedQuestion(index)"
                            >
                                <Trash2 />
                            </Button>
                        </div>
                        <p
                            v-if="
                                reconciliationForm.errors.unresolved_questions
                            "
                            class="text-sm text-destructive"
                        >
                            {{ reconciliationForm.errors.unresolved_questions }}
                        </p>
                    </div>
                    <Button
                        type="submit"
                        :disabled="reconciliationForm.processing"
                    >
                        {{
                            reconciliationForm.processing
                                ? 'Recording…'
                                : 'Record evidence version'
                        }}
                    </Button>
                </form>
            </section>
        </main>
    </AppLayout>
</template>
