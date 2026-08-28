<script setup lang="ts">
import { CheckCircle2, Clock3 } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';

defineProps<{
    availability: 'available_online' | 'staff_assisted_being_completed';
    label: string;
}>();
</script>

<template>
    <Badge
        :variant="availability === 'available_online' ? 'default' : 'secondary'"
        class="gap-1.5"
    >
        <CheckCircle2
            v-if="availability === 'available_online'"
            aria-hidden="true"
        />
        <Clock3 v-else aria-hidden="true" />
        {{ label }}
    </Badge>
</template>
