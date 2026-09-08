<script setup lang="ts">
import { Download, Eye, FileText, Image as ImageIcon } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type ApplicantDocumentReferenceItem = {
    document_id: number;
    label: string;
    original_name: string;
    mime_type: string;
    size_bytes: number;
    view_url: string;
    download_url: string;
};

const props = defineProps<{
    documents: ApplicantDocumentReferenceItem[];
}>();

const viewerOpen = ref(false);
const selectedDocumentId = ref<number | null>(null);
const selectedDocument = computed(
    () =>
        props.documents.find(
            (document) => document.document_id === selectedDocumentId.value,
        ) ?? null,
);

function openDocument(document: ApplicantDocumentReferenceItem): void {
    selectedDocumentId.value = document.document_id;
    viewerOpen.value = true;
}

function isImage(document: ApplicantDocumentReferenceItem): boolean {
    return document.mime_type.startsWith('image/');
}

function isPdf(document: ApplicantDocumentReferenceItem): boolean {
    return document.mime_type === 'application/pdf';
}

function fileSize(sizeBytes: number): string {
    if (sizeBytes < 1024) {
        return `${sizeBytes} B`;
    }

    return `${(sizeBytes / 1024).toFixed(sizeBytes < 10_240 ? 1 : 0)} KB`;
}
</script>

<template>
    <section
        v-if="documents.length"
        class="grid gap-3"
        aria-labelledby="applicant-document-reference-heading"
        data-testid="applicant-document-reference"
    >
        <div>
            <h3 id="applicant-document-reference-heading" class="font-bold">
                Applicant documents
            </h3>
            <p class="mt-1 text-sm text-muted-foreground">
                Open the submitted evidence while determining the official Lines
                of Business.
            </p>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <article
                v-for="document in documents"
                :key="document.document_id"
                class="min-w-0 overflow-hidden rounded-lg border bg-background"
            >
                <button
                    type="button"
                    class="block w-full text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden"
                    :aria-label="`View ${document.label}`"
                    @click="openDocument(document)"
                >
                    <span
                        class="relative flex aspect-[4/3] items-center justify-center overflow-hidden border-b bg-muted/40"
                    >
                        <img
                            v-if="isImage(document)"
                            :src="document.view_url"
                            :alt="`Thumbnail of ${document.label}`"
                            class="size-full object-cover"
                        />
                        <span
                            v-else-if="isPdf(document)"
                            class="flex size-full flex-col items-center justify-center gap-2 bg-white text-slate-500 dark:bg-slate-950 dark:text-slate-400"
                        >
                            <FileText class="size-10" aria-hidden="true" />
                            <span
                                class="text-[10px] font-black tracking-[0.18em] uppercase"
                            >
                                PDF
                            </span>
                        </span>
                        <FileText
                            v-else
                            class="size-10 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <span
                            class="absolute right-2 bottom-2 flex size-7 items-center justify-center rounded-full bg-background/95 shadow-sm"
                        >
                            <Eye class="size-3.5" aria-hidden="true" />
                        </span>
                    </span>
                    <span class="block p-3">
                        <span class="block truncate text-sm font-semibold">
                            {{ document.label }}
                        </span>
                        <span
                            class="mt-1 block truncate text-xs text-muted-foreground"
                        >
                            {{ document.original_name }} ·
                            {{ fileSize(document.size_bytes) }}
                        </span>
                    </span>
                </button>
            </article>
        </div>

        <Dialog v-model:open="viewerOpen">
            <DialogContent
                class="grid h-[min(90vh,56rem)] max-w-5xl grid-rows-[auto_minmax(0,1fr)] gap-0 overflow-hidden p-0"
            >
                <DialogHeader class="border-b p-4 pr-12 sm:p-5 sm:pr-14">
                    <div class="flex min-w-0 items-start gap-3">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <ImageIcon
                                v-if="
                                    selectedDocument &&
                                    isImage(selectedDocument)
                                "
                                class="size-4"
                                aria-hidden="true"
                            />
                            <FileText
                                v-else
                                class="size-4"
                                aria-hidden="true"
                            />
                        </span>
                        <div class="min-w-0">
                            <DialogTitle class="truncate">
                                {{ selectedDocument?.label }}
                            </DialogTitle>
                            <DialogDescription class="mt-1 truncate">
                                {{ selectedDocument?.original_name }}
                            </DialogDescription>
                        </div>
                        <a
                            v-if="selectedDocument"
                            :href="selectedDocument.download_url"
                            class="ml-auto inline-flex shrink-0 items-center gap-2 rounded-md border px-3 py-2 text-sm font-semibold"
                        >
                            <Download class="size-4" aria-hidden="true" />
                            <span class="hidden sm:inline">Download</span>
                        </a>
                    </div>
                </DialogHeader>

                <div class="min-h-0 bg-muted/40 p-2 sm:p-4">
                    <img
                        v-if="selectedDocument && isImage(selectedDocument)"
                        :src="selectedDocument.view_url"
                        :alt="selectedDocument.label"
                        class="size-full object-contain"
                    />
                    <iframe
                        v-else-if="selectedDocument && isPdf(selectedDocument)"
                        :src="selectedDocument.view_url"
                        :title="selectedDocument.label"
                        class="size-full rounded-md border bg-white"
                    />
                    <div
                        v-else-if="selectedDocument"
                        class="flex size-full flex-col items-center justify-center gap-4 text-center"
                    >
                        <FileText
                            class="size-12 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p class="max-w-md text-sm text-muted-foreground">
                            This file type cannot be previewed here. Download it
                            to review the document.
                        </p>
                        <a
                            :href="selectedDocument.download_url"
                            class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground"
                        >
                            <Download class="size-4" aria-hidden="true" />
                            Download document
                        </a>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </section>
</template>
