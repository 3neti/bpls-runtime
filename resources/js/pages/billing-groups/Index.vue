<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Plus, Trash2, WalletCards } from '@lucide/vue';
import {
    store,
    index,
    show,
} from '@/actions/App/Http/Controllers/Staff/BillingGroupController';
import AdministrationScopePanel from '@/components/administration/AdministrationScopePanel.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type BillingGroupRow = {
    id: number;
    name: string;
    description: string | null;
    acceptance_status: string;
    is_active: boolean;
    fields_count: number;
    records_count: number;
};

type FieldDefinition = {
    key: string;
    name: string;
    field_type: string;
    is_required: boolean;
    is_unique: boolean;
    options: string[];
    placeholder: string;
    default_value: string;
};

const props = defineProps<{
    billingGroups: { data: BillingGroupRow[] };
    fieldTypes: { value: string; label: string }[];
    can: { manage: boolean };
    policyNote: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Other Collections Setup', href: index() },
];

const form = useForm<{
    name: string;
    description: string;
    fields: FieldDefinition[];
}>({
    name: '',
    description: '',
    fields: [emptyField()],
});

function emptyField(): FieldDefinition {
    return {
        key: '',
        name: '',
        field_type: 'text',
        is_required: false,
        is_unique: false,
        options: [],
        placeholder: '',
        default_value: '',
    };
}

function addField(): void {
    form.fields.push(emptyField());
}

function removeField(indexToRemove: number): void {
    if (form.fields.length > 1) {
        form.fields.splice(indexToRemove, 1);
    }
}

function submit(): void {
    form.post(store.url(), {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Other Collections Setup" />

        <main class="flex h-full flex-1 flex-col gap-4 p-4">
            <section>
                <h1 class="text-xl font-semibold text-foreground">
                    Other Collections Setup
                </h1>
                <p class="text-sm text-muted-foreground">
                    Prepare provisional record forms for municipal collections
                    outside the business-permit workflow.
                </p>
            </section>

            <AdministrationScopePanel
                available="Review provisional collection types and, where authorized, prepare the fields each type may need."
                evidence="These definitions are draft setup only. They do not establish an amount due or authorize Treasury collection."
                unavailable="Calculating liability, collecting payment, issuing receipts or official numbers, and activating unconfirmed financial policy."
            />

            <section
                class="border-l-4 border-amber-500 bg-amber-50 p-4 text-sm text-amber-950 dark:bg-amber-950/30 dark:text-amber-100"
                data-testid="billing-group-policy-boundary"
            >
                This setup is provisional. It can prepare sample record forms,
                but it cannot calculate an amount due, collect payment, issue a
                receipt, or create an official transaction number.
            </section>

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_26rem]">
                <section
                    class="overflow-hidden border border-sidebar-border/70 bg-background dark:border-sidebar-border"
                >
                    <div
                        v-if="billingGroups.data.length === 0"
                        class="flex min-h-64 flex-col items-center justify-center gap-3 p-8 text-center"
                    >
                        <WalletCards class="size-10 text-muted-foreground" />
                        <p class="text-sm text-muted-foreground">
                            No provisional collection types have been prepared.
                        </p>
                    </div>
                    <div v-else class="divide-y divide-sidebar-border/70">
                        <Link
                            v-for="billingGroup in billingGroups.data"
                            :key="billingGroup.id"
                            :href="show(billingGroup.id)"
                            class="grid gap-2 p-4 transition-colors hover:bg-muted/50"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <h2 class="font-medium text-foreground">
                                    {{ billingGroup.name }}
                                </h2>
                                <span
                                    class="border border-amber-500/50 px-2 py-0.5 text-xs font-medium text-amber-800 dark:text-amber-200"
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
                            <p class="text-xs text-muted-foreground">
                                {{ billingGroup.fields_count }} fields ·
                                {{ billingGroup.records_count }} sample records
                            </p>
                        </Link>
                    </div>
                </section>

                <form
                    v-if="can.manage"
                    class="flex flex-col gap-4 border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                    data-testid="billing-group-definition-form"
                    @submit.prevent="submit"
                >
                    <div>
                        <h2 class="font-semibold text-foreground">
                            Add provisional collection type
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Set up the fields for review. This does not enable
                            billing or collection.
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="billing_group_name">Name</Label>
                        <Input
                            id="billing_group_name"
                            v-model="form.name"
                            required
                        />
                        <p
                            v-if="form.errors.name"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="billing_group_description"
                            >Description</Label
                        >
                        <textarea
                            id="billing_group_description"
                            v-model="form.description"
                            rows="3"
                            class="w-full border border-input bg-background px-3 py-2 text-sm"
                        />
                    </div>

                    <div class="grid gap-3">
                        <div class="flex items-center justify-between gap-2">
                            <Label>Record fields</Label>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="addField"
                            >
                                <Plus /> Add field
                            </Button>
                        </div>

                        <div
                            v-for="(field, fieldIndex) in form.fields"
                            :key="fieldIndex"
                            class="grid gap-3 border border-sidebar-border/70 p-3"
                        >
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <span
                                    class="text-xs font-medium text-muted-foreground"
                                >
                                    Field {{ fieldIndex + 1 }}
                                </span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :disabled="form.fields.length === 1"
                                    title="Remove field"
                                    @click="removeField(fieldIndex)"
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                            <Input
                                v-model="field.name"
                                placeholder="Field label"
                                required
                            />
                            <Input
                                v-model="field.key"
                                placeholder="Internal field key"
                                required
                            />
                            <select
                                v-model="field.field_type"
                                class="h-9 border border-input bg-background px-3 text-sm"
                            >
                                <option
                                    v-for="fieldType in props.fieldTypes"
                                    :key="fieldType.value"
                                    :value="fieldType.value"
                                >
                                    {{ fieldType.label }}
                                </option>
                            </select>
                            <Input
                                v-model="field.placeholder"
                                placeholder="Placeholder (optional)"
                            />
                            <Input
                                v-model="field.default_value"
                                placeholder="Default value (optional)"
                            />
                            <Input
                                v-if="field.field_type === 'dropdown'"
                                :model-value="field.options.join(', ')"
                                placeholder="Options separated by commas"
                                @update:model-value="
                                    field.options = String($event)
                                        .split(',')
                                        .map((option) => option.trim())
                                        .filter(Boolean)
                                "
                            />
                            <div class="flex flex-wrap gap-4 text-sm">
                                <label class="flex items-center gap-2">
                                    <input
                                        v-model="field.is_required"
                                        type="checkbox"
                                    />
                                    Required if this setup is approved
                                </label>
                                <label class="flex items-center gap-2">
                                    <input
                                        v-model="field.is_unique"
                                        type="checkbox"
                                    />
                                    Unique if this setup is approved
                                </label>
                            </div>
                        </div>
                        <p
                            v-if="form.errors.fields"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.fields }}
                        </p>
                    </div>

                    <Button type="submit" :disabled="form.processing">
                        {{
                            form.processing
                                ? 'Saving…'
                                : 'Save provisional type'
                        }}
                    </Button>
                </form>
            </div>
        </main>
    </AppLayout>
</template>
