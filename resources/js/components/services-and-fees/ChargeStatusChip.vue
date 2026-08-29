<script setup lang="ts">
import {
    Building2,
    CheckCircle2,
    Circle,
    Clock3,
    FlaskConical,
    ShieldQuestion,
} from '@lucide/vue';
import { computed } from 'vue';
import { toneClasses } from '@/lib/pricing-status';
import type { PricingStatusInfo } from '@/lib/pricing-status';

const props = defineProps<{
    status: PricingStatusInfo;
}>();

const icon = computed(() => {
    switch (props.status.key) {
        case 'in_force':
            return CheckCircle2;
        case 'recorded_confirmation_required':
            return Clock3;
        case 'calculated_during_assessment':
            return Circle;
        case 'determined_by_office':
            return Building2;
        case 'test_data_hidden':
            return FlaskConical;
        case 'not_commissioned':
        default:
            return ShieldQuestion;
    }
});
</script>

<template>
    <span
        class="inline-flex w-fit shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium whitespace-nowrap"
        :class="toneClasses(status.tone)"
    >
        <component :is="icon" class="size-3.5 shrink-0" aria-hidden="true" />
        {{ status.label }}
    </span>
</template>
