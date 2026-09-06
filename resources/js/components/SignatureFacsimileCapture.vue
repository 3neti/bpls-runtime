<script setup lang="ts">
import { PenLine, RotateCcw, Trash2, X } from '@lucide/vue';
import { nextTick, ref } from 'vue';
import { Button } from '@/components/ui/button';

const props = withDefaults(
    defineProps<{ name?: string; required?: boolean; error?: string }>(),
    { name: 'signature_facsimile', required: true, error: undefined },
);
const emit = defineEmits<{ selected: [file: File | null] }>();

const dialog = ref<HTMLDialogElement | null>(null);
const canvas = ref<HTMLCanvasElement | null>(null);
const hasDrawing = ref(false);
const previewUrl = ref('');
const isDrawing = ref(false);
let context: CanvasRenderingContext2D | null = null;

async function openCapture(): Promise<void> {
    dialog.value?.showModal();
    await nextTick();
    window.requestAnimationFrame(initializeCanvas);
}

function initializeCanvas(): void {
    if (!canvas.value) {
        return;
    }

    const rectangle = canvas.value.getBoundingClientRect();
    const pixelRatio = window.devicePixelRatio || 1;
    canvas.value.width = Math.max(1, Math.round(rectangle.width * pixelRatio));
    canvas.value.height = Math.max(
        1,
        Math.round(rectangle.height * pixelRatio),
    );

    context = canvas.value.getContext('2d');

    if (!context) {
        return;
    }

    context.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
    context.strokeStyle = '#111827';
    context.lineWidth = 2;
    context.lineCap = 'round';
    context.lineJoin = 'round';
    hasDrawing.value = false;
}

function canvasPoint(event: PointerEvent): { x: number; y: number } | null {
    if (!canvas.value) {
        return null;
    }

    const rectangle = canvas.value.getBoundingClientRect();

    return {
        x: event.clientX - rectangle.left,
        y: event.clientY - rectangle.top,
    };
}

function startDrawing(event: PointerEvent): void {
    const point = canvasPoint(event);

    if (!canvas.value || !context || !point) {
        return;
    }

    event.preventDefault();
    canvas.value.setPointerCapture(event.pointerId);
    context.beginPath();
    context.moveTo(point.x, point.y);
    isDrawing.value = true;
}

function draw(event: PointerEvent): void {
    if (!isDrawing.value || !context) {
        return;
    }

    const point = canvasPoint(event);

    if (!point) {
        return;
    }

    event.preventDefault();
    context.lineTo(point.x, point.y);
    context.stroke();
    hasDrawing.value = true;
}

function stopDrawing(event: PointerEvent): void {
    if (!isDrawing.value) {
        return;
    }

    context?.closePath();
    isDrawing.value = false;

    if (canvas.value?.hasPointerCapture(event.pointerId)) {
        canvas.value.releasePointerCapture(event.pointerId);
    }
}

function clearDrawing(): void {
    if (!canvas.value || !context) {
        return;
    }

    context.save();
    context.setTransform(1, 0, 0, 1, 0, 0);
    context.clearRect(0, 0, canvas.value.width, canvas.value.height);
    context.restore();
    hasDrawing.value = false;
}

function useSignature(): void {
    if (!canvas.value || !hasDrawing.value) {
        return;
    }

    const preview = canvas.value.toDataURL('image/png');

    canvas.value.toBlob((blob) => {
        if (!blob) {
            return;
        }

        previewUrl.value = preview;
        emit(
            'selected',
            new File([blob], 'signature-facsimile.png', {
                type: 'image/png',
                lastModified: Date.now(),
            }),
        );
        dialog.value?.close();
    }, 'image/png');
}

function removeSignature(): void {
    previewUrl.value = '';
    clearDrawing();
    emit('selected', null);
}
</script>

<template>
    <div
        class="grid gap-2"
        :data-field-name="props.name"
        data-testid="signature-facsimile-control"
    >
        <div
            v-if="previewUrl"
            class="grid gap-3 rounded-lg border border-emerald-300 bg-emerald-50/60 p-3 dark:border-emerald-800 dark:bg-emerald-950/20"
            data-testid="signature-facsimile-preview"
        >
            <div class="flex items-center justify-between gap-3">
                <p
                    class="text-sm font-semibold text-emerald-900 dark:text-emerald-100"
                >
                    Signature captured
                </p>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="removeSignature"
                >
                    <Trash2 />Remove
                </Button>
            </div>
            <div class="rounded-md border bg-white p-2">
                <img
                    :src="previewUrl"
                    alt="Preview of the captured signature facsimile"
                    class="mx-auto h-24 max-w-full object-contain"
                />
            </div>
            <Button type="button" variant="outline" @click="openCapture">
                <PenLine />Change signature
            </Button>
        </div>

        <Button v-else type="button" variant="outline" @click="openCapture">
            <PenLine />Sign here
        </Button>
        <p v-if="error" class="text-xs text-red-600">{{ error }}</p>

        <dialog
            ref="dialog"
            class="m-auto w-[min(92vw,32rem)] rounded-xl border bg-background p-0 text-foreground shadow-2xl backdrop:bg-black/50"
            @close="isDrawing = false"
        >
            <div class="flex items-center justify-between border-b p-4">
                <div>
                    <h2 class="font-bold">Sign here</h2>
                    <p class="text-xs text-muted-foreground">
                        Draw your signature using your finger, stylus, or mouse.
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-md p-1 hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    aria-label="Close signature capture"
                    @click="dialog?.close()"
                >
                    <X class="size-5" />
                </button>
            </div>
            <div class="grid gap-4 p-4">
                <div
                    class="relative overflow-hidden rounded-md border-2 border-dashed border-stone-300 bg-white"
                >
                    <canvas
                        ref="canvas"
                        class="block h-48 w-full cursor-crosshair touch-none"
                        role="img"
                        aria-label="Signature drawing area"
                        :aria-required="props.required"
                        data-testid="signature-facsimile-canvas"
                        @pointerdown="startDrawing"
                        @pointermove="draw"
                        @pointerup="stopDrawing"
                        @pointercancel="stopDrawing"
                    />
                    <span
                        v-if="!hasDrawing"
                        class="pointer-events-none absolute inset-0 grid place-items-center text-sm text-stone-400"
                    >
                        Draw inside this box
                    </span>
                </div>
                <div
                    class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between"
                >
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="!hasDrawing"
                        @click="clearDrawing"
                    >
                        <RotateCcw />Clear
                    </Button>
                    <Button
                        type="button"
                        :disabled="!hasDrawing"
                        @click="useSignature"
                    >
                        Use signature
                    </Button>
                </div>
                <p class="text-xs text-muted-foreground">
                    This captures visual facsimile evidence for this act only.
                </p>
            </div>
        </dialog>
    </div>
</template>
