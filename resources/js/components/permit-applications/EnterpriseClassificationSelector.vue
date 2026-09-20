<script setup lang="ts">
import { computed, ref } from 'vue';
import { money } from '@/lib/evaluationPresentation';
import { manualTreasuryDetermination } from '@/lib/treasuryEnterprise';
import type {
    EnterpriseSchedule,
    ManualTreasuryDetermination,
} from '@/lib/treasuryEnterprise';

defineProps<{ schedule: EnterpriseSchedule; modelValue: string }>();
const emit = defineEmits<{
    'update:modelValue': [value: string];
    manual: [value: ManualTreasuryDetermination | null];
}>();
const amount = ref('');
const basis = ref('');
const manual = computed(() =>
    manualTreasuryDetermination(amount.value, basis.value),
);
const confirmed = ref(false);
function invalidate(): void {
    if (confirmed.value) {
        emit('manual', null);
    }

    confirmed.value = false;
}
function confirmAmount(): void {
    if (!manual.value) {
        return;
    }

    emit('manual', manual.value);
    confirmed.value = true;
}
function chooseClassification(event: Event): void {
    confirmed.value = false;
    emit('update:modelValue', (event.target as HTMLSelectElement).value);
}
function clearDetermination(): void {
    amount.value = '';
    basis.value = '';
    confirmed.value = false;
    emit('manual', null);
}
</script>

<template>
    <section
        class="grid min-w-0 gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950"
    >
        <section
            v-if="schedule.manual_determination_available"
            class="grid min-w-0 gap-2 border-b border-amber-300 pb-3"
        >
            <strong>Determine Mayor’s Permit Fee</strong>
            <label class="grid gap-1"
                >Amount (₱)
                <input
                    v-model="amount"
                    type="text"
                    inputmode="decimal"
                    aria-label="Mayor’s Permit amount (pesos)"
                    class="h-11 min-w-0 rounded border bg-background px-3 text-foreground"
                    @input="invalidate"
                />
            </label>
            <label class="grid gap-1"
                >Basis / reference
                <textarea
                    v-model="basis"
                    maxlength="1000"
                    rows="2"
                    aria-label="Mayor’s Permit amount basis"
                    class="min-w-0 rounded border bg-background p-2 text-foreground"
                    @input="invalidate"
                />
            </label>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="min-h-11 rounded border bg-background px-3 font-semibold disabled:opacity-50"
                    :disabled="!manual || confirmed"
                    @click="confirmAmount"
                >
                    {{ confirmed ? 'Amount staged' : 'Confirm amount' }}
                </button>
                <button
                    type="button"
                    class="min-h-11 rounded border bg-background px-3 font-semibold disabled:opacity-50"
                    :disabled="!amount && !basis && !confirmed && !modelValue"
                    @click="clearDetermination"
                >
                    Clear
                </button>
            </div>
            <p>
                Local/UAT test only. Saves officer, amount and basis with
                Confirm Treasury. Not municipal policy.
            </p>
        </section>
        <label class="grid min-w-0 gap-2 font-semibold">
            Enterprise Classification
            <select
                aria-label="Enterprise Classification"
                class="h-11 w-full max-w-full min-w-0 rounded-md border bg-background px-3 text-foreground"
                :value="modelValue"
                @change="chooseClassification"
            >
                <option value="">Choose classification</option>
                <option
                    v-for="(amount, classification) in schedule.bands"
                    :key="classification"
                    :value="classification"
                >
                    {{ classification }} — {{ money(amount) }}
                </option>
            </select>
        </label>
        <p>
            Alternative: choose a classification, separate from the official
            Line of Business, only when its synthetic-UAT basis for this
            Application has been recorded; its scheduled Mayor’s Permit Fee
            cannot be edited here. If the classification is not established,
            stop for municipal confirmation, or use the manual test
            determination above if available.
        </p>
        <p class="font-bold">PROVISIONAL UAT SCHEDULE — NOT MUNICIPAL POLICY</p>
        <p class="text-xs break-words">
            UAT only · {{ schedule.id }} · {{ schedule.version }}. Not derived
            from applicant data; not production policy.
        </p>
    </section>
</template>
