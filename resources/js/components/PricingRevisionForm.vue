<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { proposeRevision } from '@/actions/App/Http/Controllers/Staff/FeeRuleController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    ruleId: number;
    method: string;
    basis: string;
    ranges: {
        min_basis_cents: number;
        max_basis_cents: number | null;
        amount_cents: number;
    }[];
    accountId: number | null;
    groupId: number | null;
    accounts: { id: number; code: string; name: string | null }[];
    groups: { id: number; code: string; name: string }[];
}>();
const brackets = ref(
    props.ranges.map((r) => ({
        min: (r.min_basis_cents / 100).toFixed(2),
        max:
            r.max_basis_cents === null
                ? ''
                : (r.max_basis_cents / 100).toFixed(2),
        amount: (r.amount_cents / 100).toFixed(2),
    })),
);
const pesos = ref('');
const saved = ref(false);
const form = useForm({
    proposed_amount_minor: 0,
    effective_from: '',
    effective_until: '',
    reason: '',
    authority: '',
    definition: {
        revenue_account_id: props.accountId,
        group_id: props.groupId,
        ranges: undefined as
            | {
                  min_basis_cents: number;
                  max_basis_cents: number | null;
                  amount_cents: number;
              }[]
            | undefined,
    },
});

function minor(value: string): number | null {
    if (!/^\d{1,12}(\.\d{1,2})?$/.test(value.trim())) {
        return null;
    }

    const [whole, fraction = ''] = value.trim().split('.');

    return Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
}

function submit() {
    if (form.processing) {
        return;
    }

    saved.value = false;
    form.clearErrors();
    const value = pesos.value.trim();

    if (props.method !== 'range' && minor(value) === null) {
        form.setError(
            'proposed_amount_minor',
            'Enter pesos with up to two decimal places, without commas.',
        );

        return;
    }

    form.proposed_amount_minor = props.method === 'range' ? 0 : minor(value)!;

    if (props.method === 'range') {
        if (
            !brackets.value.length ||
            brackets.value.some(
                (r) =>
                    minor(r.min) === null ||
                    minor(r.amount) === null ||
                    (r.max !== '' && minor(r.max) === null),
            )
        ) {
            form.setError(
                'proposed_amount_minor',
                'Enter valid bracket bounds and peso amounts.',
            );

            return;
        }

        form.definition.ranges = brackets.value.map((r) => ({
            min_basis_cents: minor(r.min)!,
            max_basis_cents: r.max === '' ? null : minor(r.max),
            amount_cents: minor(r.amount)!,
        }));
    }

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
        <div v-if="method !== 'range'" class="grid gap-2">
            <Label for="revision-pesos">{{
                method === 'formula'
                    ? 'Unit rate per employee (₱)'
                    : 'Proposed amount (₱)'
            }}</Label>
            <Input
                id="revision-pesos"
                v-model="pesos"
                inputmode="decimal"
                placeholder="e.g. 25.00"
                required
                :disabled="form.processing"
            />
        </div>
        <p v-if="method === 'formula'" class="text-xs text-muted-foreground">
            Formula: frozen employee count × unit rate.
        </p>
        <div v-if="method === 'range'" class="grid gap-3">
            <p class="text-sm">
                {{
                    basis === 'business_area_square_meters'
                        ? 'Area brackets (m²)'
                        : 'Basis brackets (₱)'
                }}
                · inclusive bounds
            </p>
            <fieldset
                v-for="(bracket, i) in brackets"
                :key="i"
                class="grid gap-2 rounded border p-3"
            >
                <legend class="text-sm">Bracket {{ i + 1 }}</legend>
                <Label :for="`bracket-min-${i}`">From</Label
                ><Input
                    :id="`bracket-min-${i}`"
                    v-model="bracket.min"
                    inputmode="decimal"
                    required
                />
                <Label :for="`bracket-max-${i}`"
                    >Through (blank = no upper limit)</Label
                ><Input
                    :id="`bracket-max-${i}`"
                    v-model="bracket.max"
                    inputmode="decimal"
                />
                <Label :for="`bracket-amount-${i}`">Fee (₱)</Label
                ><Input
                    :id="`bracket-amount-${i}`"
                    v-model="bracket.amount"
                    inputmode="decimal"
                    required
                />
                <Button
                    type="button"
                    variant="ghost"
                    :disabled="form.processing"
                    @click="brackets.splice(i, 1)"
                    >Remove bracket</Button
                >
            </fieldset>
            <Button
                type="button"
                variant="outline"
                :disabled="form.processing"
                @click="brackets.push({ min: '', max: '', amount: '' })"
                >Add bracket</Button
            >
        </div>
        <div class="grid gap-2">
            <Label for="revision-account">Revenue account</Label>
            <select
                id="revision-account"
                v-model="form.definition.revenue_account_id"
                class="h-9 min-w-0 rounded border bg-background px-2"
                :disabled="form.processing"
            >
                <option :value="null">Not mapped</option>
                <option
                    v-for="account in accounts"
                    :key="account.id"
                    :value="account.id"
                >
                    {{ account.code }} · {{ account.name }}
                </option>
            </select>
            <Label for="revision-group">Fee group</Label>
            <select
                id="revision-group"
                v-model="form.definition.group_id"
                class="h-9 min-w-0 rounded border bg-background px-2"
                :disabled="form.processing"
            >
                <option :value="null">Ungrouped</option>
                <option
                    v-for="group in groups"
                    :key="group.id"
                    :value="group.id"
                >
                    {{ group.name }}
                </option>
            </select>
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
