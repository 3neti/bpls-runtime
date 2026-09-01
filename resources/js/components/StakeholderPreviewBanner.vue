<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ArrowRight, ShieldAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    enterLaboratory,
    switchMethod,
} from '@/actions/App/Http/Controllers/StakeholderPreviewController';

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

function handlePersonaChange(event: Event): void {
    const persona = (event.target as HTMLSelectElement).value;

    if (persona !== preview.value?.current_persona) {
        switchPersona(persona);
    }
}

function returnToLaboratory(): void {
    switchingTo.value = 'laboratory';
    router.post(
        enterLaboratory().url,
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
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-2">
            <div
                class="flex min-w-0 items-center justify-between gap-3 sm:items-start"
            >
                <div class="flex min-w-0 items-center gap-2">
                    <ShieldAlert class="size-5 shrink-0" aria-hidden="true" />
                    <div class="min-w-0">
                        <p
                            class="text-xs font-extrabold tracking-wide sm:text-sm"
                        >
                            Preview Environment · Sample Data
                        </p>
                        <p
                            v-if="preview.current_label"
                            class="text-xs leading-4 font-medium"
                        >
                            Exploring as {{ preview.current_label }}. This
                            preview role does not grant municipal authority.
                        </p>
                        <p
                            v-else-if="preview.cleanroom_actor"
                            class="text-xs leading-4 font-medium"
                        >
                            Cleanroom actor:
                            {{ preview.cleanroom_actor.label }} · Complete the
                            real product form, then return to the Laboratory.
                        </p>
                    </div>
                </div>

                <label
                    v-if="preview.current_persona"
                    class="grid shrink-0 gap-1 text-xs font-semibold sm:hidden"
                >
                    <span class="sr-only">Switch preview role</span>
                    <select
                        :value="preview.current_persona"
                        :disabled="switchingTo !== null"
                        class="h-9 max-w-40 rounded-md border border-amber-900/40 bg-white px-2 text-xs font-semibold text-amber-950 shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-amber-950 disabled:opacity-70"
                        aria-label="Switch preview role"
                        @change="handlePersonaChange"
                    >
                        <option
                            v-for="persona in preview.personas"
                            :key="persona.key"
                            :value="persona.key"
                        >
                            {{ persona.label }}
                        </option>
                    </select>
                </label>
                <button
                    v-if="preview.cleanroom_actor"
                    type="button"
                    :disabled="switchingTo !== null"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-amber-950 bg-amber-950 px-3 py-2 text-xs font-bold text-white shadow-sm transition outline-none hover:bg-amber-800 focus-visible:ring-2 focus-visible:ring-amber-950 focus-visible:ring-offset-2 focus-visible:ring-offset-amber-300 disabled:cursor-wait disabled:opacity-60"
                    @click="returnToLaboratory"
                >
                    Continue in Laboratory
                    <ArrowRight class="size-4" aria-hidden="true" />
                </button>
            </div>

            <div
                v-if="preview.current_persona"
                class="hidden flex-wrap items-center gap-1.5 sm:flex"
            >
                <span class="mr-1 text-xs font-semibold">
                    Switch preview role:
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
