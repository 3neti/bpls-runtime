<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Download, Eye, FileText, Plus, Upload, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    destroy,
    download,
    store,
} from '@/actions/App/Http/Controllers/Citizen/PermitApplicationDocumentController';
import InputError from '@/components/InputError.vue';
import AuthenticatedPdfPreview from '@/components/permit-applications/AuthenticatedPdfPreview.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type DocumentType = {
    code: string;
    label: string;
    allows_multiple: boolean;
};
type ApplicationDocument = {
    id: number;
    label: string;
    document_type: string | null;
    original_name: string;
    size_bytes: number;
    version: number | null;
    mime_type?: string;
    view_url?: string;
    download_url?: string;
};
type PendingDocument = {
    key: number;
    document_type: string;
    label: string;
    file: File;
};

const props = withDefaults(
    defineProps<{
        documentTypes: DocumentType[];
        documents?: ApplicationDocument[];
        pendingDocuments?: PendingDocument[];
        applicationId?: number;
        editable?: boolean;
        returnTo?: 'edit' | 'show';
        boundaryError?: string;
    }>(),
    {
        documents: () => [],
        pendingDocuments: () => [],
        applicationId: undefined,
        editable: true,
        returnTo: 'edit',
        boundaryError: undefined,
    },
);
const emit = defineEmits<{
    queue: [document: Omit<PendingDocument, 'key'>];
    'remove-pending': [key: number];
    'save-draft': [];
    'invalidate-lodging-ceremony': [];
}>();

const dialog = ref<HTMLDialogElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const localError = ref('');
const selectedType = ref(props.documentTypes[0]?.code ?? '');
const selectedFile = ref<File | null>(null);
const viewerOpen = ref(false);
const selectedDocument = ref<ApplicationDocument | null>(null);
const uploadForm = useForm({
    document_type: '',
    file: null as File | null,
    return_to: props.returnTo,
});
const selectedDefinition = computed(() =>
    props.documentTypes.find((type) => type.code === selectedType.value),
);
const replacesExisting = computed(
    () =>
        selectedDefinition.value?.allows_multiple === false &&
        [...props.documents, ...props.pendingDocuments].some(
            (document) =>
                document.document_type === selectedDefinition.value?.code,
        ),
);

function openModal(): void {
    localError.value = '';
    selectedFile.value = null;
    uploadForm.clearErrors();
    uploadForm.reset();
    selectedType.value = props.documentTypes[0]?.code ?? '';

    if (fileInput.value) {
        fileInput.value.value = '';
    }

    dialog.value?.showModal();
}

function selectFile(event: Event): void {
    selectedFile.value = (event.target as HTMLInputElement).files?.[0] ?? null;
    localError.value = '';
}

function confirmDocument(): void {
    const definition = selectedDefinition.value;

    if (!definition || !selectedFile.value) {
        localError.value = 'Choose a document type and file.';

        return;
    }

    if (props.applicationId === undefined) {
        emit('queue', {
            document_type: definition.code,
            label: definition.label,
            file: selectedFile.value,
        });
        dialog.value?.close();
        emit('save-draft');

        return;
    }

    uploadForm.document_type = definition.code;
    uploadForm.file = selectedFile.value;
    uploadForm.return_to = props.returnTo;
    emit('invalidate-lodging-ceremony');
    uploadForm.post(store.url({ permitApplication: props.applicationId }), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => dialog.value?.close(),
    });
}

function removeDocument(documentId: number): void {
    if (props.applicationId === undefined) {
        return;
    }

    emit('invalidate-lodging-ceremony');
    router.delete(
        destroy.url({
            permitApplication: props.applicationId,
            document: documentId,
        }),
        {
            data: { return_to: props.returnTo },
            preserveScroll: true,
        },
    );
}

function openDocument(document: ApplicationDocument): void {
    if (!document.view_url) {
        return;
    }

    selectedDocument.value = document;
    viewerOpen.value = true;
}

function isImage(document: ApplicationDocument): boolean {
    return document.mime_type?.startsWith('image/') ?? false;
}

function isPdf(document: ApplicationDocument): boolean {
    return document.mime_type === 'application/pdf';
}

function fileSize(bytes: number): string {
    return bytes < 1024 * 1024
        ? `${Math.max(1, Math.round(bytes / 1024))} KB`
        : `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
</script>

<template>
    <section
        data-testid="application-document-pillbox"
        class="grid gap-2"
        aria-labelledby="application-documents-label"
    >
        <div class="flex items-center justify-between gap-3">
            <div>
                <h3
                    id="application-documents-label"
                    class="text-sm font-black uppercase"
                >
                    Applicant Documents
                </h3>
                <p class="text-xs text-stone-600 dark:text-stone-300">
                    PDF, JPG, or PNG up to 10 MB.
                </p>
            </div>
            <Button
                v-if="editable"
                type="button"
                variant="outline"
                size="sm"
                :disabled="documentTypes.length === 0"
                aria-label="Add applicant document"
                @click="openModal"
            >
                <Plus /> Add
            </Button>
        </div>

        <div
            class="flex min-h-11 flex-wrap items-center gap-2 border border-stone-400 bg-white p-2 dark:bg-stone-950"
            role="list"
            aria-label="Applicant documents"
        >
            <p
                v-if="documents.length === 0 && pendingDocuments.length === 0"
                class="px-1 text-xs text-stone-500"
            >
                No documents added.
            </p>

            <span
                v-for="document in documents"
                :key="`stored-${document.id}`"
                role="listitem"
                class="inline-flex max-w-full items-center gap-1 rounded-full border border-blue-300 bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-950 dark:border-blue-700 dark:bg-blue-950/50 dark:text-blue-100"
                :title="`${document.original_name} · ${fileSize(document.size_bytes)} · version ${document.version ?? 1}`"
            >
                <FileText class="size-3.5 shrink-0" />
                <button
                    v-if="document.view_url"
                    type="button"
                    class="truncate underline-offset-2 hover:underline"
                    :aria-label="`View ${document.label}`"
                    @click="openDocument(document)"
                >
                    {{ document.label }}
                </button>
                <a
                    v-else-if="applicationId"
                    class="truncate underline-offset-2 hover:underline"
                    :href="
                        document.download_url ??
                        download.url({
                            permitApplication: applicationId,
                            document: document.id,
                        })
                    "
                    >{{ document.label }}</a
                >
                <span v-else class="truncate">{{ document.label }}</span>
                <button
                    v-if="document.view_url"
                    type="button"
                    class="rounded-full p-0.5 hover:bg-blue-200 dark:hover:bg-blue-800"
                    :aria-label="`View ${document.label}`"
                    @click="openDocument(document)"
                >
                    <Eye class="size-3.5" />
                </button>
                <a
                    v-if="document.download_url"
                    :href="document.download_url"
                    class="rounded-full p-0.5 hover:bg-blue-200 dark:hover:bg-blue-800"
                    :aria-label="`Download ${document.label}`"
                >
                    <Download class="size-3.5" />
                </a>
                <button
                    v-if="editable"
                    type="button"
                    class="rounded-full p-0.5 hover:bg-blue-200 dark:hover:bg-blue-800"
                    :aria-label="`Remove ${document.label}`"
                    @click="removeDocument(document.id)"
                >
                    <X class="size-3.5" />
                </button>
            </span>

            <span
                v-for="document in pendingDocuments"
                :key="`pending-${document.key}`"
                role="listitem"
                class="inline-flex max-w-full items-center gap-1 rounded-full border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-950 dark:border-amber-700 dark:bg-amber-950/50 dark:text-amber-100"
                :title="document.file.name"
            >
                <Upload class="size-3.5 shrink-0" />
                <span class="truncate">{{ document.label }}</span>
                <button
                    type="button"
                    class="rounded-full p-0.5 hover:bg-amber-200 dark:hover:bg-amber-800"
                    :aria-label="`Remove pending ${document.label}`"
                    @click="emit('remove-pending', document.key)"
                >
                    <X class="size-3.5" />
                </button>
            </span>
        </div>

        <InputError :message="boundaryError" />

        <dialog
            ref="dialog"
            class="m-auto w-[min(92vw,30rem)] rounded-xl border bg-background p-0 text-foreground shadow-2xl backdrop:bg-black/50"
        >
            <div class="flex items-center justify-between border-b p-4">
                <h2 class="font-bold">
                    {{ replacesExisting ? 'Replace document' : 'Add document' }}
                </h2>
                <button
                    type="button"
                    aria-label="Close document modal"
                    @click="dialog?.close()"
                >
                    <X class="size-5" />
                </button>
            </div>
            <div class="grid gap-4 p-4">
                <div class="grid gap-2">
                    <label
                        for="application-document-type"
                        class="text-sm font-semibold"
                    >
                        Document type
                    </label>
                    <select
                        id="application-document-type"
                        v-model="selectedType"
                        class="h-10 border border-stone-400 bg-white px-3 text-sm dark:bg-stone-950"
                    >
                        <option
                            v-for="type in documentTypes"
                            :key="type.code"
                            :value="type.code"
                        >
                            {{ type.label }}
                        </option>
                    </select>
                    <InputError :message="uploadForm.errors.document_type" />
                </div>
                <div class="grid gap-2">
                    <label
                        for="application-document-file"
                        class="text-sm font-semibold"
                    >
                        File
                    </label>
                    <input
                        id="application-document-file"
                        ref="fileInput"
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png"
                        class="min-w-0 text-sm"
                        @change="selectFile"
                    />
                    <InputError
                        :message="uploadForm.errors.file ?? localError"
                    />
                </div>
                <progress
                    v-if="uploadForm.progress"
                    class="h-2 w-full"
                    :value="uploadForm.progress.percentage"
                    max="100"
                >
                    {{ uploadForm.progress.percentage }}%
                </progress>
                <p v-if="replacesExisting" class="text-xs text-amber-700">
                    Confirming replaces the current active document of this
                    type.
                </p>
                <div class="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="dialog?.close()"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        :disabled="uploadForm.processing"
                        @click="confirmDocument"
                    >
                        {{ uploadForm.processing ? 'Uploading…' : 'Confirm' }}
                    </Button>
                </div>
            </div>
        </dialog>

        <Dialog v-model:open="viewerOpen">
            <DialogContent
                class="grid h-[min(90vh,56rem)] max-w-5xl grid-rows-[auto_minmax(0,1fr)] gap-0 overflow-hidden p-0"
                style="
                    height: min(calc(100dvh - 1rem), 56rem);
                    max-height: calc(100% - 1rem);
                "
            >
                <DialogHeader class="border-b p-4 pr-12 sm:p-5 sm:pr-14">
                    <DialogTitle>{{ selectedDocument?.label }}</DialogTitle>
                    <DialogDescription>
                        {{ selectedDocument?.original_name }}
                    </DialogDescription>
                    <a
                        v-if="selectedDocument?.download_url"
                        :href="selectedDocument.download_url"
                        class="mt-2 inline-flex w-fit items-center gap-2 rounded-md border px-3 py-2 text-sm font-semibold"
                    >
                        <Download class="size-4" aria-hidden="true" />
                        Download
                    </a>
                </DialogHeader>
                <div class="min-h-0 bg-muted/40 p-2 sm:p-4">
                    <img
                        v-if="selectedDocument && isImage(selectedDocument)"
                        :src="selectedDocument.view_url"
                        :alt="selectedDocument.label"
                        class="size-full object-contain"
                    />
                    <AuthenticatedPdfPreview
                        v-else-if="selectedDocument && isPdf(selectedDocument)"
                        :src="selectedDocument.view_url!"
                        :title="selectedDocument.label"
                    />
                    <div
                        v-else-if="selectedDocument"
                        class="flex size-full flex-col items-center justify-center gap-4 text-center"
                    >
                        <FileText class="size-12 text-muted-foreground" />
                        <p class="max-w-md text-sm text-muted-foreground">
                            This file type cannot be previewed here. Download it
                            to review the document.
                        </p>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </section>
</template>
