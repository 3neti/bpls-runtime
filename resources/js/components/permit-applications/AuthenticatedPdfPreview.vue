<script setup lang="ts">
import { ChevronLeft, ChevronRight, FileWarning } from '@lucide/vue';
import { getDocument, GlobalWorkerOptions } from 'pdfjs-dist';
import type {
    PDFDocumentLoadingTask,
    PDFDocumentProxy,
    RenderTask,
} from 'pdfjs-dist';
import pdfWorkerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

GlobalWorkerOptions.workerSrc = pdfWorkerUrl;

const props = defineProps<{
    src: string;
    title: string;
}>();

const container = ref<HTMLElement | null>(null);
const canvas = ref<HTMLCanvasElement | null>(null);
const loading = ref(true);
const error = ref('');
const pageNumber = ref(1);
const pageCount = ref(0);
const pageText = ref('');
let document: PDFDocumentProxy | null = null;
let loadingTask: PDFDocumentLoadingTask | null = null;
let renderTask: RenderTask | null = null;
let fetchController: AbortController | null = null;
let resizeObserver: ResizeObserver | null = null;
let resizeFrame: number | null = null;
let loadSequence = 0;
let renderSequence = 0;

function isRenderCancellation(reason: unknown): boolean {
    return (
        reason instanceof Error && reason.name === 'RenderingCancelledException'
    );
}

async function renderPage(
    sequence: number,
    requestedPage: number,
): Promise<void> {
    if (sequence !== loadSequence || requestedPage !== pageNumber.value) {
        return;
    }

    const sourceDocument = document;
    const generation = ++renderSequence;

    if (!sourceDocument || !canvas.value || !container.value) {
        return;
    }

    renderTask?.cancel();
    let page;

    try {
        page = await sourceDocument.getPage(requestedPage);
    } catch (reason) {
        if (generation !== renderSequence || sequence !== loadSequence) {
            return;
        }

        throw reason;
    }

    if (
        generation !== renderSequence ||
        sequence !== loadSequence ||
        sourceDocument !== document ||
        requestedPage !== pageNumber.value ||
        !canvas.value ||
        !container.value
    ) {
        return;
    }

    const baseViewport = page.getViewport({ scale: 1 });
    const availableWidth = Math.max(1, container.value.clientWidth - 16);
    const cssScale = Math.min(2, availableWidth / baseViewport.width);
    const viewport = page.getViewport({ scale: cssScale });
    const outputScale = Math.max(1, window.devicePixelRatio || 1);
    const context = canvas.value.getContext('2d');

    if (!context) {
        throw new Error('The document canvas is unavailable.');
    }

    canvas.value.width = Math.floor(viewport.width * outputScale);
    canvas.value.height = Math.floor(viewport.height * outputScale);
    canvas.value.style.width = `${Math.floor(viewport.width)}px`;
    canvas.value.style.height = `${Math.floor(viewport.height)}px`;

    const nextRenderTask = page.render({
        canvas: canvas.value,
        canvasContext: context,
        viewport,
        transform:
            outputScale === 1
                ? undefined
                : [outputScale, 0, 0, outputScale, 0, 0],
    });
    renderTask = nextRenderTask;

    try {
        await nextRenderTask.promise;
    } catch (reason) {
        if (generation !== renderSequence || sequence !== loadSequence) {
            return;
        }

        throw reason;
    }

    if (
        generation !== renderSequence ||
        sequence !== loadSequence ||
        sourceDocument !== document ||
        requestedPage !== pageNumber.value
    ) {
        return;
    }

    let text;

    try {
        text = await page.getTextContent();
    } catch (reason) {
        if (generation !== renderSequence || sequence !== loadSequence) {
            return;
        }

        throw reason;
    }

    if (
        generation !== renderSequence ||
        sequence !== loadSequence ||
        sourceDocument !== document ||
        requestedPage !== pageNumber.value
    ) {
        return;
    }

    pageText.value = text.items
        .map((item) => ('str' in item ? item.str : ''))
        .filter(Boolean)
        .join(' ');
}

async function load(): Promise<void> {
    const sequence = ++loadSequence;
    renderSequence++;
    fetchController?.abort();
    fetchController = new AbortController();
    const previousLoadingTask = loadingTask;
    loadingTask = null;
    document = null;
    void previousLoadingTask?.destroy();
    loading.value = true;
    error.value = '';
    pageText.value = '';
    pageNumber.value = 1;
    pageCount.value = 0;
    renderTask?.cancel();

    if (canvas.value) {
        canvas.value.width = 0;
        canvas.value.height = 0;
    }

    try {
        const response = await fetch(props.src, {
            credentials: 'same-origin',
            headers: { Accept: 'application/pdf' },
            signal: fetchController.signal,
        });

        if (sequence !== loadSequence) {
            return;
        }

        if (!response.ok) {
            throw new Error(`Document preview returned ${response.status}.`);
        }

        const nextLoadingTask = getDocument({
            data: await response.arrayBuffer(),
        });

        if (sequence !== loadSequence) {
            await nextLoadingTask.destroy();

            return;
        }

        const pdf = await nextLoadingTask.promise;

        if (sequence !== loadSequence) {
            await nextLoadingTask.destroy();

            return;
        }

        loadingTask = nextLoadingTask;
        document = pdf;
        pageCount.value = pdf.numPages;
        await nextTick();

        if (sequence !== loadSequence) {
            return;
        }

        await renderPage(sequence, 1);
    } catch (reason) {
        if (
            sequence === loadSequence &&
            !(reason instanceof DOMException && reason.name === 'AbortError') &&
            !isRenderCancellation(reason)
        ) {
            error.value =
                reason instanceof Error
                    ? reason.message
                    : 'The document preview is unavailable.';
        }
    } finally {
        if (sequence === loadSequence) {
            loading.value = false;
        }
    }
}

async function showPage(nextPage: number): Promise<void> {
    if (nextPage < 1 || nextPage > pageCount.value) {
        return;
    }

    const sequence = loadSequence;
    pageNumber.value = nextPage;
    loading.value = true;
    error.value = '';

    try {
        await renderPage(sequence, nextPage);
    } catch (reason) {
        if (sequence === loadSequence && !isRenderCancellation(reason)) {
            error.value =
                reason instanceof Error
                    ? reason.message
                    : 'The document page is unavailable.';
        }
    } finally {
        if (sequence === loadSequence) {
            loading.value = false;
        }
    }
}

function scheduleResizeRender(): void {
    if (!document || loading.value || error.value || resizeFrame !== null) {
        return;
    }

    const sequence = loadSequence;
    const requestedPage = pageNumber.value;
    resizeFrame = window.requestAnimationFrame(() => {
        resizeFrame = null;
        void renderPage(sequence, requestedPage).catch((reason: unknown) => {
            if (sequence === loadSequence && !isRenderCancellation(reason)) {
                error.value =
                    reason instanceof Error
                        ? reason.message
                        : 'The resized document page is unavailable.';
            }
        });
    });
}

watch(() => props.src, load);
onMounted(() => {
    resizeObserver = new ResizeObserver(scheduleResizeRender);

    if (container.value) {
        resizeObserver.observe(container.value);
    }

    void load();
});
onBeforeUnmount(() => {
    loadSequence++;
    renderSequence++;
    fetchController?.abort();
    renderTask?.cancel();
    resizeObserver?.disconnect();

    if (resizeFrame !== null) {
        window.cancelAnimationFrame(resizeFrame);
    }

    void loadingTask?.destroy();
});
</script>

<template>
    <section
        ref="container"
        class="relative flex size-full min-h-0 max-w-full min-w-0 flex-col overflow-hidden rounded-md border bg-slate-200"
        :aria-label="`${title} PDF preview`"
    >
        <div
            v-if="pageCount > 1"
            class="flex items-center justify-center gap-3 border-b bg-background p-2"
        >
            <button
                type="button"
                class="rounded border p-1 disabled:opacity-40"
                :disabled="pageNumber <= 1 || loading"
                aria-label="Previous PDF page"
                @click="showPage(pageNumber - 1)"
            >
                <ChevronLeft class="size-4" />
            </button>
            <span class="text-xs font-semibold">
                Page {{ pageNumber }} of {{ pageCount }}
            </span>
            <button
                type="button"
                class="rounded border p-1 disabled:opacity-40"
                :disabled="pageNumber >= pageCount || loading"
                aria-label="Next PDF page"
                @click="showPage(pageNumber + 1)"
            >
                <ChevronRight class="size-4" />
            </button>
        </div>

        <div class="min-h-0 max-w-full min-w-0 flex-1 overflow-auto p-2">
            <canvas
                ref="canvas"
                class="mx-auto max-w-full bg-white shadow"
                :aria-label="`${title}, page ${pageNumber}`"
            />
            <p data-testid="pdf-text-layer" class="sr-only">
                {{ pageText }}
            </p>
        </div>

        <div
            v-if="loading"
            role="status"
            class="absolute inset-0 flex items-center justify-center bg-background/85 text-sm font-semibold"
        >
            Rendering document…
        </div>
        <div
            v-else-if="error"
            role="alert"
            class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-background p-6 text-center"
        >
            <FileWarning class="size-10 text-amber-600" aria-hidden="true" />
            <p class="font-semibold">Preview unavailable</p>
            <p class="max-w-md text-sm text-muted-foreground">
                {{ error }} Use Download to open the original file.
            </p>
        </div>
    </section>
</template>
