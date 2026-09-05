<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { PencilLine, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { proposeRevision } from '@/actions/App/Http/Controllers/Staff/FeeRuleController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    MunicipalFeeScheduleRow,
    MunicipalScheduleOfFees,
} from '@/types/municipal-schedule-of-fees';

defineProps<{ schedule: MunicipalScheduleOfFees }>();

const page = usePage();
const selectedRow = ref<MunicipalFeeScheduleRow | null>(null);
const proposedAmountPesos = ref(0);
const revisionForm = useForm({
    proposed_amount_minor: 0,
    effective_from: '',
    effective_until: '',
    reason: '',
    authority: '',
});
const canManageFeeRules = computed(() =>
    Boolean(
        (page.props.auth as { can_manage_fee_rules?: boolean } | undefined)
            ?.can_manage_fee_rules,
    ),
);

function money(amountMinor: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amountMinor / 100);
}

function amount(row: MunicipalFeeScheduleRow): string {
    const value =
        row.amount_minor !== null
            ? money(row.amount_minor)
            : row.rate_basis_points !== null
              ? `${Number(row.rate_basis_points) / 100}%`
              : 'Case-based';

    return row.is_ceiling ? `Up to ${value}` : value;
}

function status(row: MunicipalFeeScheduleRow): string {
    return {
        available: 'Available',
        for_confirmation: 'For confirmation',
        needs_determination: 'Needs determination',
    }[row.status];
}

function beginRevision(row: MunicipalFeeScheduleRow): void {
    if (!canManageFeeRules.value || !row.revision_eligible) {
        return;
    }

    selectedRow.value = row;
    proposedAmountPesos.value =
        row.amount_minor === null ? 0 : row.amount_minor / 100;
    revisionForm.reset();
    revisionForm.clearErrors();
}

function cancelRevision(): void {
    selectedRow.value = null;
    proposedAmountPesos.value = 0;
    revisionForm.reset();
    revisionForm.clearErrors();
}

function submitRevision(): void {
    if (
        selectedRow.value?.fee_rule_id === null ||
        selectedRow.value?.fee_rule_id === undefined
    ) {
        return;
    }

    revisionForm.proposed_amount_minor = Math.round(
        proposedAmountPesos.value * 100,
    );
    revisionForm.post(proposeRevision(selectedRow.value.fee_rule_id).url, {
        preserveScroll: true,
        onSuccess: cancelRevision,
    });
}
</script>

<template>
    <article
        data-testid="municipal-schedule-of-fees-sheet"
        class="mx-auto w-full max-w-[58rem] overflow-hidden border border-stone-500 bg-[#fffdf7] text-stone-950 shadow-[0_16px_40px_rgba(28,25,23,0.16)] print:max-w-none print:border-stone-900 print:shadow-none"
    >
        <header
            class="border-b-2 border-stone-900 px-4 py-5 text-center sm:px-7 sm:py-7 print:px-5 print:py-5"
        >
            <p class="text-[10px] font-bold tracking-[0.18em] uppercase">
                Republic of the Philippines
            </p>
            <p class="mt-1 text-[11px] font-bold uppercase">
                Province of Zamboanga Sibugay · Municipality of Ipil
            </p>
            <h3
                class="mt-4 font-serif text-2xl font-black tracking-[0.06em] uppercase sm:text-3xl"
            >
                {{ schedule.title }}
            </h3>
            <p class="mt-2 text-xs font-bold uppercase">
                {{ schedule.scope }}
            </p>
            <div
                class="mx-auto mt-4 grid max-w-xl grid-cols-2 border border-stone-900 text-left text-[10px] sm:text-xs"
            >
                <p class="border-r border-stone-900 px-3 py-2">
                    <span class="block font-bold uppercase"
                        >Application year</span
                    >
                    {{ schedule.application_year }}
                </p>
                <p class="px-3 py-2">
                    <span class="block font-bold uppercase"
                        >Effective view</span
                    >
                    {{ schedule.as_of_date }}
                </p>
            </div>
        </header>

        <div class="grid gap-0 px-3 py-4 sm:px-6 sm:py-6 print:px-4">
            <section
                v-for="(category, categoryIndex) in schedule.categories"
                :key="category.key"
                class="border-x border-b border-stone-900 first:border-t"
                :data-testid="`schedule-category-${category.key}`"
            >
                <h4
                    class="border-b border-stone-900 bg-stone-200 px-3 py-2 font-serif text-xs font-black tracking-wide uppercase sm:text-sm print:bg-stone-200"
                >
                    {{ categoryIndex + 1 }}. {{ category.label }}
                </h4>

                <div class="grid sm:hidden">
                    <article
                        v-for="row in category.rows"
                        :key="row.id"
                        class="grid min-w-0 gap-2 border-b border-stone-400 px-3 py-3 last:border-b-0"
                    >
                        <div
                            class="flex min-w-0 items-start justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <p class="font-semibold break-words">
                                    {{ row.service }}
                                </p>
                                <p
                                    class="mt-0.5 font-mono text-[9px] break-all"
                                >
                                    {{ row.code }}
                                </p>
                            </div>
                            <strong class="shrink-0 text-right text-sm">{{
                                amount(row)
                            }}</strong>
                        </div>
                        <p class="text-xs leading-5 break-words">
                            {{ row.basis || 'As applicable' }}
                        </p>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-[9px] font-bold uppercase">{{
                                status(row)
                            }}</span>
                            <button
                                v-if="
                                    canManageFeeRules && row.revision_eligible
                                "
                                type="button"
                                class="inline-flex items-center gap-1 text-[10px] font-bold underline underline-offset-2 print:hidden"
                                :aria-label="`Propose change to ${row.service}`"
                                @click="beginRevision(row)"
                            >
                                <PencilLine class="size-3" aria-hidden="true" />
                                Propose change
                            </button>
                        </div>
                    </article>
                </div>

                <table
                    class="hidden w-full table-fixed border-collapse sm:table"
                >
                    <thead class="print:table-header-group">
                        <tr
                            class="border-b border-stone-900 text-left text-[9px] uppercase"
                        >
                            <th class="w-[28%] px-2 py-2 font-bold">
                                Fee or charge
                            </th>
                            <th
                                class="w-[27%] border-l border-stone-500 px-2 py-2 font-bold"
                            >
                                Basis / applicability
                            </th>
                            <th
                                class="w-[16%] border-l border-stone-500 px-2 py-2 text-right font-bold"
                            >
                                Amount / rate
                            </th>
                            <th
                                class="w-[17%] border-l border-stone-500 px-2 py-2 font-bold"
                            >
                                Revenue Code
                            </th>
                            <th
                                class="w-[12%] border-l border-stone-500 px-2 py-2 font-bold"
                            >
                                Status
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in category.rows"
                            :key="row.id"
                            class="break-inside-avoid border-b border-stone-400 last:border-b-0"
                        >
                            <td
                                class="px-2 py-2 align-top text-xs font-semibold break-words"
                            >
                                {{ row.service }}
                            </td>
                            <td
                                class="border-l border-stone-400 px-2 py-2 align-top text-[11px] leading-4 break-words"
                            >
                                {{ row.basis || 'As applicable' }}
                            </td>
                            <td
                                class="border-l border-stone-400 px-2 py-2 text-right align-top text-xs font-bold tabular-nums"
                            >
                                {{ amount(row) }}
                            </td>
                            <td
                                class="border-l border-stone-400 px-2 py-2 align-top font-mono text-[9px] break-all"
                            >
                                {{ row.code }}
                            </td>
                            <td
                                class="border-l border-stone-400 px-2 py-2 align-top text-[9px] font-bold uppercase"
                            >
                                <span>{{ status(row) }}</span>
                                <button
                                    v-if="
                                        canManageFeeRules &&
                                        row.revision_eligible
                                    "
                                    type="button"
                                    class="mt-2 inline-flex items-center gap-1 text-left font-bold underline underline-offset-2 print:hidden"
                                    :aria-label="`Propose change to ${row.service}`"
                                    @click="beginRevision(row)"
                                >
                                    <PencilLine
                                        class="size-3"
                                        aria-hidden="true"
                                    />
                                    Propose change
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <form
                    v-if="
                        selectedRow &&
                        category.rows.some((row) => row.id === selectedRow?.id)
                    "
                    data-testid="schedule-inline-revision-form"
                    class="grid gap-4 border-t-2 border-stone-900 bg-amber-50 px-3 py-4 sm:grid-cols-2 sm:px-4 print:hidden"
                    @submit.prevent="submitRevision"
                >
                    <div
                        class="flex items-start justify-between gap-4 sm:col-span-2"
                    >
                        <div>
                            <p
                                class="text-[10px] font-black tracking-wide uppercase"
                            >
                                Proposed change to the Municipal Schedule of
                                Fees
                            </p>
                            <p class="mt-1 font-semibold">
                                {{ selectedRow.service }}
                            </p>
                            <p class="text-xs">
                                Current amount: {{ amount(selectedRow) }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-sm border border-stone-700 p-1"
                            aria-label="Cancel proposed change"
                            @click="cancelRevision"
                        >
                            <X class="size-4" aria-hidden="true" />
                        </button>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="schedule_proposed_amount"
                            >Proposed amount (PHP)</Label
                        >
                        <Input
                            id="schedule_proposed_amount"
                            v-model.number="proposedAmountPesos"
                            type="number"
                            min="0"
                            step="0.01"
                            required
                        />
                        <p
                            v-if="revisionForm.errors.proposed_amount_minor"
                            class="text-xs text-destructive"
                        >
                            {{ revisionForm.errors.proposed_amount_minor }}
                        </p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="schedule_effective_from"
                            >Effective from</Label
                        >
                        <Input
                            id="schedule_effective_from"
                            v-model="revisionForm.effective_from"
                            type="date"
                            required
                        />
                        <p
                            v-if="revisionForm.errors.effective_from"
                            class="text-xs text-destructive"
                        >
                            {{ revisionForm.errors.effective_from }}
                        </p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="schedule_effective_until"
                            >Effective until</Label
                        >
                        <Input
                            id="schedule_effective_until"
                            v-model="revisionForm.effective_until"
                            type="date"
                        />
                        <p
                            v-if="revisionForm.errors.effective_until"
                            class="text-xs text-destructive"
                        >
                            {{ revisionForm.errors.effective_until }}
                        </p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="schedule_authority"
                            >Authority or supporting reference</Label
                        >
                        <Input
                            id="schedule_authority"
                            v-model="revisionForm.authority"
                            required
                        />
                        <p
                            v-if="revisionForm.errors.authority"
                            class="text-xs text-destructive"
                        >
                            {{ revisionForm.errors.authority }}
                        </p>
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="schedule_reason"
                            >Reason for proposed change</Label
                        >
                        <textarea
                            id="schedule_reason"
                            v-model="revisionForm.reason"
                            required
                            rows="3"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                        <p
                            v-if="revisionForm.errors.reason"
                            class="text-xs text-destructive"
                        >
                            {{ revisionForm.errors.reason }}
                        </p>
                    </div>
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 sm:col-span-2"
                    >
                        <p class="max-w-xl text-[10px] leading-4">
                            This records an append-only proposal. The published
                            amount and existing Assessments do not change.
                        </p>
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="revisionForm.processing"
                        >
                            {{
                                revisionForm.processing
                                    ? 'Recording…'
                                    : 'Propose change'
                            }}
                        </Button>
                    </div>
                </form>
            </section>
        </div>

        <footer class="border-t border-stone-900 px-4 py-3 text-[9px] sm:px-7">
            <p>
                Prepared from the municipal fee-rule and Revenue Code schedule
                records effective for the year shown.
            </p>
        </footer>
    </article>
</template>
