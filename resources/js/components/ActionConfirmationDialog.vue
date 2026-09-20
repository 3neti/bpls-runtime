<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

defineProps<{ context?: string }>();
const open = ref(false);
const title = ref('Confirm action');
const description = ref('');
const label = ref('Confirm');
let settle: ((accepted: boolean) => void) | null = null;
let trigger: HTMLElement | null = null;

function answer(accepted: boolean): void {
    const resolve = settle;
    settle = null;
    open.value = false;
    resolve?.(accepted);
}

function ask(
    action: string,
    explanation: string,
    buttonLabel = action,
): Promise<boolean> {
    if (settle) {
        return Promise.resolve(false);
    }

    trigger =
        document.activeElement instanceof HTMLElement
            ? document.activeElement
            : null;
    title.value = action;
    description.value = explanation;
    label.value = buttonLabel;
    open.value = true;

    return new Promise((resolve) => {
        settle = resolve;
    });
}

function restoreFocus(event: Event): void {
    event.preventDefault();

    if (trigger?.isConnected) {
        trigger.focus();
    }
}
onBeforeUnmount(() => answer(false));
defineExpose({ ask });
</script>

<template>
    <Dialog
        :open="open"
        @update:open="
            (value) => {
                if (!value) answer(false);
            }
        "
    >
        <DialogContent
            :show-close-button="false"
            @close-auto-focus="restoreFocus"
        >
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>
            <p
                v-if="context"
                class="min-w-0 rounded border bg-muted/30 p-3 text-sm break-words"
            >
                {{ context }}
            </p>
            <DialogFooter>
                <Button type="button" variant="outline" @click="answer(false)"
                    >Cancel</Button
                >
                <Button
                    type="button"
                    class="whitespace-normal"
                    @click="answer(true)"
                    >{{ label }}</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
