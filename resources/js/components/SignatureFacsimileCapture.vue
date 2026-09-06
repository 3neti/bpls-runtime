<script setup lang="ts">
import { PenLine, X } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';

withDefaults(
    defineProps<{ name?: string; required?: boolean; error?: string }>(),
    { name: 'signature_facsimile', required: true, error: undefined },
);
const emit = defineEmits<{ selected: [file: File | null] }>();

const dialog = ref<HTMLDialogElement | null>(null);
const selectedName = ref('');

function selected(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    selectedName.value = file?.name ?? '';
    emit('selected', file);
}
</script>

<template>
    <div class="grid gap-2">
        <Button type="button" variant="outline" @click="dialog?.showModal()">
            <PenLine />Sign here
        </Button>
        <p v-if="selectedName" class="text-xs">Captured: {{ selectedName }}</p>
        <p v-if="error" class="text-xs text-red-600">{{ error }}</p>
        <dialog
            ref="dialog"
            class="m-auto w-[min(92vw,28rem)] rounded-xl border bg-background p-0 text-foreground shadow-2xl backdrop:bg-black/50"
        >
            <div class="flex items-center justify-between border-b p-4">
                <h2 class="font-bold">Signature facsimile</h2>
                <button
                    type="button"
                    aria-label="Close"
                    @click="dialog?.close()"
                >
                    <X class="size-5" />
                </button>
            </div>
            <div class="grid gap-4 p-4">
                <p class="text-sm text-muted-foreground">
                    Capture or choose an image of your signature. This is visual
                    facsimile evidence for this act only.
                </p>
                <input
                    :name="name"
                    type="file"
                    accept="image/*"
                    capture="environment"
                    :required="required"
                    class="min-w-0 text-sm"
                    @change="selected"
                />
                <Button type="button" @click="dialog?.close()"
                    >Use signature</Button
                >
            </div>
        </dialog>
    </div>
</template>
