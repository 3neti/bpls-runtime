<script setup lang="ts">
import type { EnterpriseSchedule } from '@/lib/treasuryEnterprise';

defineProps<{ schedule: EnterpriseSchedule; modelValue: string }>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
</script>

<template>
    <section
        class="grid min-w-0 gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950"
    >
        <label class="grid min-w-0 gap-2 font-semibold">
            Enterprise Classification
            <select
                aria-label="Enterprise Classification"
                class="h-11 w-full max-w-full min-w-0 rounded-md border bg-background px-3 text-foreground"
                :value="modelValue"
                @change="
                    emit(
                        'update:modelValue',
                        ($event.target as HTMLSelectElement).value,
                    )
                "
            >
                <option value="">Choose classification</option>
                <option
                    v-for="(_, classification) in schedule.bands"
                    :key="classification"
                    :value="classification"
                >
                    {{ classification }}
                </option>
            </select>
        </label>
        <p>
            Required before Treasury confirmation. Treasury chooses the
            classification; the schedule determines the Mayor’s Permit Fee.
        </p>
        <p class="font-bold">
            PROVISIONAL MUNICIPAL POLICY — PENDING IPIL OFFICER CONFIRMATION
        </p>
        <p class="text-xs break-words">
            UAT only · {{ schedule.id }} · {{ schedule.version }}. Not derived
            from applicant data; not production policy.
        </p>
    </section>
</template>
