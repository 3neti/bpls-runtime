<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, FilePlus2 } from '@lucide/vue';
import {
    index,
    show,
} from '@/actions/App/Http/Controllers/Staff/BillingGroupController';
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
};

const props = defineProps<{
    billingGroup: BillingGroup;
    can: { create_record: boolean };
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
        </main>
    </AppLayout>
</template>
