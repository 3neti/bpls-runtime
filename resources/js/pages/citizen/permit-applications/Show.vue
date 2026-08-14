<script setup lang="ts">
import { Head, Link, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Download,
    FilePenLine,
    FilePlus2,
    Paperclip,
    Upload,
} from '@lucide/vue';
import {
    create,
    edit,
    index,
    show,
} from '@/actions/App/Http/Controllers/Citizen/PermitApplicationController';
import {
    download as downloadDocument,
    store as storeDocument,
} from '@/actions/App/Http/Controllers/Citizen/PermitApplicationDocumentController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem } from '@/types';

type PermitApplication = {
    id: number;
    display_reference: string;
    application_number: string | null;
    type: string;
    status: string;
    application_year: number;
    business_name: string;
    activity_count: number;
    saved_at: string | null;
    owner: {
        name: string;
        email: string | null;
        phone: string | null;
        address: string | null;
    };
    business: {
        name: string;
        trade_name: string | null;
        registration_number: string | null;
        address: string | null;
        barangay: string | null;
    };
    lines: {
        id: number;
        line_of_business: {
            code: string | null;
            name: string | null;
        };
        declared_gross_sales_cents: number;
        capital_investment_cents: number;
        quantity: number;
        started_on: string | null;
    }[];
    documents: {
        id: number;
        label: string;
        original_name: string;
        mime_type: string;
        size_bytes: number;
        remarks: string | null;
        uploaded_at: string;
        uploaded_by: string;
    }[];
    documentary_readiness: {
        received_document_count: number;
        requirement_catalog_status: string;
        submission_readiness: string;
        statement: string;
    };
    draft_boundary: {
        is_draft: boolean;
        assessment_started: boolean;
        official_application_number_assigned: boolean;
        statement: string;
    };
    can_edit: boolean;
    can_upload_documents: boolean;
    can_view_documents: boolean;
};

const props = defineProps<{
    permitApplication: PermitApplication;
}>();

const documentForm = useForm({
    label: '',
    file: null as File | null,
    remarks: '',
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'My Permit Applications',
        href: index(),
    },
    {
        title: props.permitApplication.display_reference,
        href: show(props.permitApplication.id),
    },
];

setLayoutProps({ breadcrumbs });

function money(amountCents: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amountCents / 100);
}

function dateTime(value: string): string {
    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function fileSize(sizeBytes: number): string {
    if (sizeBytes < 1024) {
        return `${sizeBytes} B`;
    }

    return `${(sizeBytes / 1024).toFixed(1)} KB`;
}

function selectDocument(event: Event): void {
    const input = event.target as HTMLInputElement;
    documentForm.file = input.files?.[0] ?? null;
}

function uploadDocument(): void {
    documentForm.post(storeDocument.url(props.permitApplication.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => documentForm.reset(),
    });
}

function documentBoundaryError(): string | undefined {
    return (documentForm.errors as Record<string, string | undefined>)
        .document;
}
</script>

<template>
    <div class="contents">
        <Head :title="permitApplication.display_reference" />

        <main class="flex h-full flex-1 flex-col gap-4 p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-xl font-semibold text-foreground">
                            {{ permitApplication.display_reference }}
                        </h1>
                        <Badge variant="secondary" class="capitalize">
                            {{ permitApplication.status.replace('_', ' ') }}
                        </Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ permitApplication.business.name }} ·
                        {{ permitApplication.application_year }} new permit
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button v-if="permitApplication.can_edit" as-child variant="outline">
                        <Link :href="edit(permitApplication.id)">
                            <FilePenLine />
                            Edit Draft
                        </Link>
                    </Button>
                    <Button as-child variant="outline">
                        <Link :href="index()">
                            <ArrowLeft />
                            Back
                        </Link>
                    </Button>
                    <Button as-child>
                        <Link :href="create()">
                            <FilePlus2 />
                            New Draft
                        </Link>
                    </Button>
                </div>
            </section>

            <section
                data-testid="citizen-draft-boundary"
                :data-application-status="permitApplication.status"
                class="border-l-4 border-amber-500 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:bg-amber-950/30 dark:text-amber-100"
            >
                <p class="font-medium">Citizen draft boundary</p>
                <p class="mt-1">{{ permitApplication.draft_boundary.statement }}</p>
            </section>

            <section class="grid gap-4 md:grid-cols-2">
                <div class="grid content-start gap-3 border-t pt-4">
                    <h2 class="text-sm font-semibold text-foreground">Owner</h2>
                    <dl class="grid gap-2 text-sm">
                        <div>
                            <dt class="text-muted-foreground">Name</dt>
                            <dd class="font-medium">{{ permitApplication.owner.name }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Contact</dt>
                            <dd>{{ permitApplication.owner.email || 'Not recorded' }}</dd>
                            <dd>{{ permitApplication.owner.phone || 'Not recorded' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Address</dt>
                            <dd>{{ permitApplication.owner.address || 'Not recorded' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="grid content-start gap-3 border-t pt-4">
                    <h2 class="text-sm font-semibold text-foreground">Business</h2>
                    <dl class="grid gap-2 text-sm">
                        <div>
                            <dt class="text-muted-foreground">Registered name</dt>
                            <dd class="font-medium">{{ permitApplication.business.name }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Trade name</dt>
                            <dd>{{ permitApplication.business.trade_name || 'Not recorded' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Registration number</dt>
                            <dd>{{ permitApplication.business.registration_number || 'Not recorded' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Address</dt>
                            <dd>
                                {{ permitApplication.business.address || 'Not recorded' }}
                                <span v-if="permitApplication.business.barangay">
                                    · {{ permitApplication.business.barangay }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="grid gap-3 border-t pt-4">
                <div>
                    <h2 class="text-sm font-semibold text-foreground">
                        Business activities
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        Declared values saved with this draft.
                    </p>
                </div>
                <div class="grid gap-3 md:hidden">
                    <article
                        v-for="line in permitApplication.lines"
                        :key="line.id"
                        data-testid="citizen-business-activity-mobile-row"
                        :data-activity-id="line.id"
                        :data-activity-code="line.line_of_business.code"
                        :data-gross-sales-cents="line.declared_gross_sales_cents"
                        :data-capital-investment-cents="line.capital_investment_cents"
                        :data-quantity="line.quantity"
                        :data-started-on="line.started_on"
                        class="grid gap-3 border-t pt-3 text-sm first:border-t-0 first:pt-0"
                    >
                        <div>
                            <p class="font-medium text-foreground">
                                {{ line.line_of_business.name || 'Unknown activity' }}
                            </p>
                            <p class="break-all text-xs text-muted-foreground">
                                {{ line.line_of_business.code || 'No code' }}
                            </p>
                        </div>
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-2">
                            <div>
                                <dt class="text-xs text-muted-foreground">Gross sales</dt>
                                <dd>{{ money(line.declared_gross_sales_cents) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">Capital</dt>
                                <dd>{{ money(line.capital_investment_cents) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">Quantity</dt>
                                <dd>{{ line.quantity }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">Started</dt>
                                <dd>{{ line.started_on || 'Not recorded' }}</dd>
                            </div>
                        </dl>
                    </article>
                </div>
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead class="border-b text-left text-xs text-muted-foreground uppercase">
                            <tr>
                                <th class="py-2 pr-4 font-medium">Activity</th>
                                <th class="px-4 py-2 text-right font-medium">Gross sales</th>
                                <th class="px-4 py-2 text-right font-medium">Capital</th>
                                <th class="px-4 py-2 text-right font-medium">Quantity</th>
                                <th class="py-2 pl-4 font-medium">Started</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="line in permitApplication.lines"
                                :key="line.id"
                                data-testid="citizen-business-activity-row"
                                :data-activity-id="line.id"
                                :data-activity-code="line.line_of_business.code"
                                :data-gross-sales-cents="line.declared_gross_sales_cents"
                                :data-capital-investment-cents="line.capital_investment_cents"
                                :data-quantity="line.quantity"
                                :data-started-on="line.started_on"
                                class="border-b last:border-b-0"
                            >
                                <td class="py-3 pr-4">
                                    <div class="font-medium">
                                        {{ line.line_of_business.name || 'Unknown activity' }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ line.line_of_business.code || 'No code' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{ money(line.declared_gross_sales_cents) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{ money(line.capital_investment_cents) }}
                                </td>
                                <td class="px-4 py-3 text-right">{{ line.quantity }}</td>
                                <td class="py-3 pl-4">{{ line.started_on || 'Not recorded' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                v-if="permitApplication.can_view_documents"
                data-testid="citizen-supporting-documents"
                class="grid gap-4 border-t pt-4"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-start gap-2">
                        <Paperclip class="mt-0.5 size-4 text-muted-foreground" />
                        <div>
                            <h2 class="text-sm font-semibold text-foreground">
                                Supporting documents
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                Attach evidence to this draft for later municipal
                                review.
                            </p>
                        </div>
                    </div>
                    <Badge variant="outline">
                        {{ permitApplication.documents.length }} received
                    </Badge>
                </div>

                <div
                    data-testid="citizen-documentary-readiness"
                    :data-document-count="
                        permitApplication.documentary_readiness
                            .received_document_count
                    "
                    :data-submission-readiness="
                        permitApplication.documentary_readiness
                            .submission_readiness
                    "
                    class="border-l-4 border-amber-500 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:bg-amber-950/30 dark:text-amber-100"
                >
                    <p class="font-medium">Documentary readiness boundary</p>
                    <p class="mt-1">
                        {{ permitApplication.documentary_readiness.statement }}
                    </p>
                </div>

                <form
                    v-if="permitApplication.can_upload_documents"
                    data-testid="citizen-document-upload-form"
                    class="grid gap-3 border-y border-border py-4 md:grid-cols-2"
                    enctype="multipart/form-data"
                    @submit.prevent="uploadDocument"
                >
                    <InputError
                        :message="documentBoundaryError()"
                        class="md:col-span-2"
                    />
                    <div class="grid gap-2">
                        <Label for="citizen-document-label">Document label</Label>
                        <Input
                            id="citizen-document-label"
                            v-model="documentForm.label"
                            maxlength="120"
                            required
                        />
                        <InputError :message="documentForm.errors.label" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="citizen-document-file">File</Label>
                        <Input
                            id="citizen-document-file"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            required
                            @change="selectDocument"
                        />
                        <p class="text-xs text-muted-foreground">
                            PDF, JPG, or PNG up to 10 MB.
                        </p>
                        <InputError :message="documentForm.errors.file" />
                    </div>
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="citizen-document-remarks">Remarks</Label>
                        <textarea
                            id="citizen-document-remarks"
                            v-model="documentForm.remarks"
                            rows="2"
                            maxlength="1000"
                            class="flex min-h-16 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs ring-offset-background transition-colors placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        />
                        <InputError :message="documentForm.errors.remarks" />
                    </div>
                    <progress
                        v-if="documentForm.progress"
                        class="h-2 w-full md:col-span-2"
                        :value="documentForm.progress.percentage"
                        max="100"
                    >
                        {{ documentForm.progress.percentage }}%
                    </progress>
                    <div class="md:col-span-2">
                        <Button
                            type="submit"
                            :disabled="documentForm.processing"
                        >
                            <Upload />
                            Add document
                        </Button>
                    </div>
                </form>

                <p
                    v-if="permitApplication.documents.length === 0"
                    class="py-2 text-sm text-muted-foreground"
                >
                    No supporting documents added to this draft.
                </p>
                <ul v-else class="divide-y divide-border">
                    <li
                        v-for="document in permitApplication.documents"
                        :key="document.id"
                        data-testid="citizen-supporting-document"
                        :data-document-id="document.id"
                        :data-document-label="document.label"
                        class="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium break-words">
                                {{ document.label }}
                            </p>
                            <p class="text-xs break-all text-muted-foreground">
                                {{ document.original_name }} ·
                                {{ fileSize(document.size_bytes) }}
                            </p>
                            <p
                                v-if="document.remarks"
                                class="mt-1 text-sm break-words text-muted-foreground"
                            >
                                {{ document.remarks }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ dateTime(document.uploaded_at) }} ·
                                {{ document.uploaded_by }}
                            </p>
                        </div>
                        <Button as-child variant="outline" size="sm">
                            <a
                                :href="
                                    downloadDocument.url({
                                        permitApplication:
                                            permitApplication.id,
                                        document: document.id,
                                    })
                                "
                            >
                                <Download />
                                Download
                            </a>
                        </Button>
                    </li>
                </ul>
            </section>
        </main>
    </div>
</template>
