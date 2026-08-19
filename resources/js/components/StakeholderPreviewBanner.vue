<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ShieldAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import { switchMethod } from '@/actions/App/Http/Controllers/StakeholderPreviewController';

const page = usePage();
const switchingTo = ref<string | null>(null);
const preview = computed(() => page.props.stakeholder_preview);

function switchPersona(persona: string): void {
    switchingTo.value = persona;
    router.post(
        switchMethod(persona).url,
        {},
        {
            onFinish: () => {
                switchingTo.value = null;
            },
        },
    );
}
</script>

<template>
    <section
        v-if="preview?.enabled"
        class="sticky top-0 z-50 border-b border-amber-800 bg-amber-300 px-3 py-2 text-amber-950 shadow-sm dark:border-amber-300 dark:bg-amber-400 dark:text-amber-950"
        aria-label="Stakeholder preview status"
        data-test="stakeholder-preview-banner"
    >
        <div
            class="mx-auto flex w-full max-w-7xl flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex min-w-0 items-center gap-2">
                <ShieldAlert class="size-5 shrink-0" aria-hidden="true" />
                <p class="text-xs font-extrabold tracking-wide sm:text-sm">
                    STAKEHOLDER PREVIEW / UAT — SYNTHETIC DATA — NOT PRODUCTION
                </p>
            </div>

            <div
                v-if="preview.current_persona"
                class="flex flex-wrap items-center gap-1.5"
            >
                <span class="mr-1 text-xs font-semibold">
                    Switch Preview Role:
                </span>
                <button
                    v-for="persona in preview.personas"
                    :key="persona.key"
                    type="button"
                    class="rounded-md border border-amber-900/30 bg-white/70 px-2.5 py-1 text-xs font-semibold outline-none hover:bg-white focus-visible:ring-2 focus-visible:ring-amber-950 disabled:cursor-default disabled:bg-amber-950 disabled:text-white"
                    :disabled="
                        persona.key === preview.current_persona ||
                        switchingTo !== null
                    "
                    :aria-pressed="persona.key === preview.current_persona"
                    @click="switchPersona(persona.key)"
                >
                    {{ persona.label }}
                </button>
            </div>
        </div>
    </section>
</template>
