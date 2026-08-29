<script setup lang="ts">
import { Building2, Star } from '@lucide/vue';
import { computed } from 'vue';
import ChargeStatusChip from '@/components/services-and-fees/ChargeStatusChip.vue';
import { CONCERNED_OFFICES } from '@/lib/price-book';
import type { ConcernedOfficeCode } from '@/lib/price-book';
import { OFFICE_DETERMINED_STATUS } from '@/lib/pricing-status';

const props = withDefaults(
    defineProps<{
        emphasizedOfficeCode?: ConcernedOfficeCode | null;
    }>(),
    {
        emphasizedOfficeCode: null,
    },
);

const orderedOffices = computed(() => {
    if (!props.emphasizedOfficeCode) {
        return CONCERNED_OFFICES;
    }

    const emphasized = CONCERNED_OFFICES.find(
        (office) => office.code === props.emphasizedOfficeCode,
    );

    if (!emphasized) {
        return CONCERNED_OFFICES;
    }

    return [
        emphasized,
        ...CONCERNED_OFFICES.filter(
            (office) => office.code !== props.emphasizedOfficeCode,
        ),
    ];
});
</script>

<template>
    <div class="space-y-3">
        <p class="text-sm text-muted-foreground">
            No concerned office has an accepted official amount recorded yet.
            Each office's contribution is determined case-by-case and is never a
            synthetic walkthrough figure.
        </p>

        <article
            v-for="office in orderedOffices"
            :key="office.code"
            class="flex min-w-0 items-start justify-between gap-3 rounded-xl border p-4"
            :class="
                office.code === emphasizedOfficeCode
                    ? 'border-primary/40 bg-primary/5'
                    : 'bg-background'
            "
        >
            <div class="flex min-w-0 items-start gap-3">
                <component
                    :is="
                        office.code === emphasizedOfficeCode ? Star : Building2
                    "
                    class="mt-0.5 size-5 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
                <div>
                    <p class="font-medium">
                        {{ office.label }}
                        <span
                            v-if="office.code === emphasizedOfficeCode"
                            class="ml-1 text-xs font-normal text-muted-foreground"
                        >
                            (your office)
                        </span>
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Determined by {{ office.label }} when applicable.
                    </p>
                </div>
            </div>
            <ChargeStatusChip :status="OFFICE_DETERMINED_STATUS" />
        </article>
    </div>
</template>
