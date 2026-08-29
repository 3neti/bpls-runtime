<script setup lang="ts">
import { CheckCircle2, Clock3, MinusCircle, RotateCcw } from '@lucide/vue';
import { computed } from 'vue';
import { componentToneClasses } from '@/lib/evaluationPresentation';
import type { ComponentStatus } from '@/lib/evaluationPresentation';

const props = defineProps<{ status: ComponentStatus }>();

const icon = computed(() => {
    switch (props.status.key) {
        case 'in_total':
            return CheckCircle2;
        case 'awaiting_office':
            return Clock3;
        case 'superseded':
            return RotateCcw;
        case 'not_applicable':
        default:
            return MinusCircle;
    }
});
</script>

<template>
    <span
        class="inline-flex w-fit shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium"
        :class="componentToneClasses(status.tone)"
    >
        <component :is="icon" class="size-3.5 shrink-0" aria-hidden="true" />
        {{ status.label }}
    </span>
</template>
