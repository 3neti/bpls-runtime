<script setup lang="ts">
import { Archive, LockKeyhole } from '@lucide/vue';
</script>

<template>
    <aside
        class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
    >
        <div class="flex items-start gap-3">
            <Archive class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <div class="space-y-1">
                <h2 class="font-semibold">
                    Historical Ipil Record · Read only
                </h2>
                <p class="text-sm leading-6">
                    Rescued source evidence is shown as recorded. It cannot
                    create or continue an application, recalculate fees, issue a
                    receipt or permit, or change the Nelson operational
                    lifecycle.
                </p>
            </div>
            <LockKeyhole class="ml-auto size-5 shrink-0" aria-hidden="true" />
        </div>
    </aside>
</template>
