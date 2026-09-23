<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { proposeRevision } from '@/actions/App/Http/Controllers/Staff/FeeRuleController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{ ruleId: number }>();
const pesos = ref('');
const saved = ref(false);
const form = useForm({
    proposed_amount_minor: 0,
    effective_from: '',
    effective_until: '',
    reason: '',
    authority: '',
});

function submit() {
    if (form.processing) {
        return;
    }

    saved.value = false;
    form.clearErrors();
    const value = pesos.value.trim();

    if (!/^\d{1,12}(\.\d{1,2})?$/.test(value)) {
        form.setError(
            'proposed_amount_minor',
            'Enter pesos with up to two decimal places, without commas.',
        );

        return;
    }

    const [whole, fraction = ''] = value.split('.');
    form.proposed_amount_minor =
        Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
    form.post(proposeRevision.url(props.ruleId), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            pesos.value = '';
            saved.value = true;
        },
    });
}
</script>

<template>
    <form
        class="mt-5 grid gap-3 rounded-lg border p-4"
        @submit.prevent="submit"
    >
        <h3 class="font-semibold">Prepare price revision</h3>
        <p class="text-xs text-muted-foreground">
            Proposal only. Current prices stay unchanged.
        </p>
        <div class="grid gap-2">
            <Label for="revision-pesos">Proposed amount (₱)</Label>
            <Input
                id="revision-pesos"
                v-model="pesos"
                inputmode="decimal"
                placeholder="e.g. 25.00"
                required
                :disabled="form.processing"
            />
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="grid min-w-0 gap-2">
                <Label for="revision-from">Effective from</Label>
                <Input
                    id="revision-from"
                    v-model="form.effective_from"
                    type="date"
                    required
                    :disabled="form.processing"
                />
            </div>
            <div class="grid min-w-0 gap-2">
                <Label for="revision-until">Until (optional)</Label>
                <Input
                    id="revision-until"
                    v-model="form.effective_until"
                    type="date"
                    :min="form.effective_from"
                    :disabled="form.processing"
                />
            </div>
        </div>
        <div class="grid gap-2">
            <Label for="revision-reason">Reason for change</Label>
            <Input
                id="revision-reason"
                v-model="form.reason"
                required
                maxlength="2000"
                :disabled="form.processing"
            />
        </div>
        <div class="grid gap-2">
            <Label for="revision-authority">Policy / authority reference</Label>
            <Input
                id="revision-authority"
                v-model="form.authority"
                required
                maxlength="2000"
                :disabled="form.processing"
            />
        </div>
        <p
            v-for="(error, key) in form.errors"
            :key="key"
            class="text-sm text-destructive"
            role="alert"
        >
            {{ error }}
        </p>
        <Button type="submit" :disabled="form.processing">{{
            form.processing ? 'Saving…' : 'Save proposed revision'
        }}</Button>
        <p v-if="saved" role="status" class="text-sm">
            Revision saved. Current prices are unchanged.
        </p>
    </form>
</template>
