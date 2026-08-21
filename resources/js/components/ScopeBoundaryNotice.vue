<script setup lang="ts">
import { ShieldAlert } from '@lucide/vue';

withDefaults(
    defineProps<{
        title?: string;
    }>(),
    {
        title: 'Preview scope',
    },
);
</script>

<template>
    <aside
        class="rounded-xl border border-amber-300/70 bg-amber-50 p-4 text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
        aria-labelledby="preview-scope-title"
    >
        <div class="flex items-start gap-3">
            <ShieldAlert class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <div class="space-y-1">
                <h2 id="preview-scope-title" class="font-semibold">
                    {{ title }}
                </h2>
                <p class="text-sm leading-6">
                    The sample journey includes final permit review and a
                    preview release step for stakeholder testing. It does not
                    create official numbering, real signing authority, municipal
                    release, permit validity, or legal effect.
                </p>
            </div>
        </div>
    </aside>
</template>
