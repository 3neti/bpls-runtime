<script setup lang="ts">
import { Search, TableProperties } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { officeLabel } from '@/lib/evaluationPresentation';
import { index as feeMatrixIndex } from '@/routes/staff/fee-matrix';

type Fee = {
    id: number;
    code: string;
    name: string;
    family: 'application_wide' | 'line_of_business';
    line_of_business_id: number | null;
    line_of_business_name: string | null;
    responsible_office: string | null;
    currency: 'PHP';
    amount_minor: number | null;
    status: string;
    effective_from: string;
    effective_until: string | null;
    legal_basis: string | null;
    version: number | null;
};

type Matrix = {
    schema_version: string;
    currency: 'PHP';
    read_only: true;
    application_wide: Fee[];
    line_of_businesses: { id: number; name: string; fees: Fee[] }[];
};

const open = ref(false);
const loading = ref(false);
const matrix = ref<Matrix | null>(null);
const loadFailed = ref(false);
const query = ref('');
const lens = ref<'all' | 'application_wide' | 'line_of_business'>('all');
const contextOffice = ref<string | null>(null);
const contextLineOfBusinessId = ref<number | null>(null);

const statusLabels: Record<string, string> = {
    in_force: 'In Force',
    municipal_confirmation_required:
        'Recorded — Municipal Confirmation Required',
    concerned_office_determined: 'Concerned-office Determined',
    not_commissioned: 'Not Commissioned',
};

const money = (minor: number | null): string =>
    minor === null
        ? 'Exact amount not commissioned'
        : new Intl.NumberFormat('en-PH', {
              style: 'currency',
              currency: 'PHP',
          }).format(minor / 100);

const includesQuery = (fee: Fee): boolean => {
    const needle = query.value.trim().toLowerCase();

    return (
        needle === '' ||
        [fee.name, fee.code, fee.line_of_business_name, fee.responsible_office]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(needle))
    );
};

const applicationFees = computed(() =>
    lens.value === 'line_of_business'
        ? []
        : (matrix.value?.application_wide ?? []).filter(includesQuery),
);
const lineGroups = computed(() =>
    lens.value === 'application_wide'
        ? []
        : (matrix.value?.line_of_businesses ?? [])
              .map((group) => ({
                  ...group,
                  fees: group.fees.filter(includesQuery),
              }))
              .filter((group) => group.fees.length > 0),
);

async function load(): Promise<void> {
    loading.value = true;
    loadFailed.value = false;
    const params = new URLSearchParams();

    if (contextOffice.value) {
        params.set('office', contextOffice.value);
    }

    if (contextLineOfBusinessId.value) {
        params.set(
            'line_of_business_id',
            String(contextLineOfBusinessId.value),
        );
    }

    try {
        const response = await fetch(
            `${feeMatrixIndex().url}?${params.toString()}`,
            {
                headers: { Accept: 'application/json' },
            },
        );
        matrix.value = response.ok ? ((await response.json()) as Matrix) : null;
        loadFailed.value = !response.ok;
    } catch {
        matrix.value = null;
        loadFailed.value = true;
    } finally {
        loading.value = false;
    }
}

function openFromContext(event: Event): void {
    const detail = (event as CustomEvent).detail as
        { office?: string; lineOfBusinessId?: number } | undefined;
    contextOffice.value = detail?.office ?? null;
    contextLineOfBusinessId.value = detail?.lineOfBusinessId ?? null;
    query.value = '';
    lens.value = 'all';
    open.value = true;
}

watch(open, (isOpen) => {
    if (isOpen) {
        void load();
    }
});
onMounted(() => window.addEventListener('open-fee-matrix', openFromContext));
onBeforeUnmount(() =>
    window.removeEventListener('open-fee-matrix', openFromContext),
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button
                class="fixed right-4 bottom-4 z-40 rounded-full shadow-lg sm:right-6 sm:bottom-6"
                data-testid="fee-matrix-quick-look"
            >
                <TableProperties aria-hidden="true" />
                Fees
            </Button>
        </DialogTrigger>
        <DialogContent
            class="h-[100dvh] w-screen max-w-none overflow-y-auto rounded-none p-0 sm:h-auto sm:max-h-[88vh] sm:w-[min(920px,calc(100vw-3rem))] sm:rounded-xl"
            data-testid="fee-matrix-dialog"
        >
            <DialogHeader class="sticky top-0 z-10 border-b bg-background p-5">
                <DialogTitle>Municipal Fee Matrix</DialogTitle>
                <DialogDescription>
                    Read-only current municipal pricing knowledge. Proposed and
                    uncommissioned entries never execute.
                </DialogDescription>
                <p
                    v-if="contextOffice"
                    class="mt-2 rounded-lg bg-muted/60 p-3 text-sm leading-5"
                >
                    Showing entries relevant to
                    <strong>{{ officeLabel(contextOffice) }}</strong>
                    <template v-if="contextLineOfBusinessId">
                        and this Line of Business</template
                    >. Use these as reference; closing this sheet leaves the
                    case determination unchanged.
                </p>
                <div class="relative mt-2">
                    <Search
                        class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        v-model="query"
                        class="pl-9"
                        placeholder="Search fee, office, or Line of Business"
                    />
                </div>
                <div class="flex flex-wrap gap-2 pt-2" aria-label="Fee family">
                    <Button
                        v-for="option in [
                            ['all', 'All'],
                            ['application_wide', 'Application-wide'],
                            ['line_of_business', 'By LOB'],
                        ] as const"
                        :key="option[0]"
                        size="sm"
                        :variant="lens === option[0] ? 'default' : 'outline'"
                        @click="lens = option[0]"
                    >
                        {{ option[1] }}
                    </Button>
                </div>
            </DialogHeader>

            <div class="grid gap-6 p-5">
                <p v-if="loading" class="text-sm text-muted-foreground">
                    Loading current fee matrix…
                </p>
                <p
                    v-else-if="loadFailed"
                    class="rounded-lg border border-destructive/30 bg-destructive/5 p-5 text-sm"
                >
                    The current Fee Matrix could not be loaded. Your case
                    determination has not been changed. Close this sheet and try
                    again.
                </p>
                <template v-else-if="matrix">
                    <section v-if="applicationFees.length" class="grid gap-2">
                        <h3 class="text-sm font-bold tracking-wide uppercase">
                            Application-wide Fees
                        </h3>
                        <article
                            v-for="fee in applicationFees"
                            :key="fee.id"
                            class="grid gap-1 rounded-lg border p-3 sm:grid-cols-[1fr_auto]"
                        >
                            <div>
                                <p class="font-semibold">{{ fee.name }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ fee.code }} ·
                                    {{ statusLabels[fee.status] }}
                                </p>
                            </div>
                            <p class="font-semibold tabular-nums">
                                {{ money(fee.amount_minor) }}
                            </p>
                        </article>
                    </section>

                    <section
                        v-for="group in lineGroups"
                        :key="group.id"
                        class="grid gap-2"
                    >
                        <h3 class="text-sm font-bold tracking-wide uppercase">
                            {{ group.name }}
                        </h3>
                        <article
                            v-for="fee in group.fees"
                            :key="fee.id"
                            class="grid gap-1 rounded-lg border p-3 sm:grid-cols-[1fr_auto]"
                        >
                            <div>
                                <p class="font-semibold">{{ fee.name }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        fee.responsible_office ?? 'Municipality'
                                    }}
                                    · {{ statusLabels[fee.status] }}
                                </p>
                            </div>
                            <p class="font-semibold tabular-nums">
                                {{ money(fee.amount_minor) }}
                            </p>
                        </article>
                    </section>
                    <p
                        v-if="!applicationFees.length && !lineGroups.length"
                        class="rounded-lg border border-dashed p-5 text-sm text-muted-foreground"
                    >
                        No current fee entry matches this office, Line of
                        Business, and search. The case amount must still be
                        supported by the office's evidence and recorded reason;
                        this empty result does not set the amount to zero.
                    </p>
                </template>
            </div>
        </DialogContent>
    </Dialog>
</template>
