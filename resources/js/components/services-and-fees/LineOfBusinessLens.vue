<script setup lang="ts">
import { computed } from 'vue';
import LineOfBusinessScheduleCard from '@/components/services-and-fees/LineOfBusinessScheduleCard.vue';
import { collectLineOfBusinessEntries } from '@/lib/price-book';
import type { MunicipalPriceList } from '@/types';

const props = defineProps<{
    priceList: MunicipalPriceList;
}>();

const entries = computed(() => collectLineOfBusinessEntries(props.priceList));
</script>

<template>
    <div class="space-y-3">
        <p v-if="entries.length === 0" class="text-sm text-muted-foreground">
            No Line-of-Business schedule is recorded yet.
        </p>
        <template v-else>
            <p class="text-sm text-muted-foreground">
                Recorded Revenue Code schedules by Line of Business, browsable
                across every service that references them.
            </p>
            <LineOfBusinessScheduleCard
                v-for="entry in entries"
                :key="entry.id"
                :entry="entry"
            />
        </template>
    </div>
</template>
