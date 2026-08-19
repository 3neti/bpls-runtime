<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    Building2,
    ClipboardCheck,
    Landmark,
    ShieldAlert,
    UserRound,
    UsersRound,
    WalletCards,
} from '@lucide/vue';
import { ref } from 'vue';
import {
    enter,
    walkthrough,
} from '@/actions/App/Http/Controllers/StakeholderPreviewController';
import type { StakeholderPreviewPersona } from '@/types';

defineProps<{
    personas: StakeholderPreviewPersona[];
}>();

const entering = ref<string | null>(null);

const personaIcons = {
    citizen: UserRound,
    bplo: ClipboardCheck,
    treasury: WalletCards,
    management: UsersRound,
};

function enterAs(persona: StakeholderPreviewPersona): void {
    entering.value = persona.key;
    router.post(
        enter(persona.key).url,
        {},
        {
            onFinish: () => {
                entering.value = null;
            },
        },
    );
}
</script>

<template>
    <Head title="BPLS Stakeholder Preview" />

    <main class="min-h-svh bg-zinc-950 text-white">
        <div
            class="border-b border-amber-400/50 bg-amber-300 px-5 py-3 text-center text-sm font-extrabold tracking-wide text-amber-950"
        >
            STAKEHOLDER PREVIEW / UAT — SYNTHETIC DATA — NOT PRODUCTION
        </div>

        <div
            class="mx-auto flex max-w-6xl flex-col gap-10 px-5 py-10 sm:px-8 sm:py-14"
        >
            <header class="grid gap-8 lg:grid-cols-[1fr_auto] lg:items-end">
                <div class="space-y-5">
                    <div class="flex items-center gap-2 text-sm text-zinc-300">
                        <Landmark class="size-5" aria-hidden="true" />
                        <span>Municipality of Ipil</span>
                    </div>
                    <div class="space-y-3">
                        <p class="text-sm font-semibold text-amber-300">
                            BPLS Stakeholder Preview
                        </p>
                        <h1
                            class="max-w-3xl text-4xl font-semibold tracking-tight sm:text-5xl"
                        >
                            Choose how you would like to review the system.
                        </h1>
                        <p
                            class="max-w-3xl text-base leading-7 text-zinc-300 sm:text-lg"
                        >
                            Open a simulated stakeholder perspective with one
                            click. No username or password is needed.
                        </p>
                    </div>
                </div>

                <Link
                    :href="walkthrough()"
                    class="inline-flex w-fit items-center gap-2 rounded-lg border border-zinc-700 px-4 py-3 text-sm font-semibold outline-none hover:bg-zinc-900 focus-visible:ring-2 focus-visible:ring-amber-300"
                >
                    <Building2 class="size-4" aria-hidden="true" />
                    Open Board / Stakeholder Walkthrough
                </Link>
            </header>

            <section
                class="grid gap-4 sm:grid-cols-2"
                aria-label="Preview personas"
            >
                <article
                    v-for="persona in personas"
                    :key="persona.key"
                    class="flex min-h-60 flex-col justify-between gap-7 rounded-2xl border border-zinc-800 bg-zinc-900 p-6 shadow-xl"
                >
                    <div class="space-y-4">
                        <component
                            :is="personaIcons[persona.key]"
                            class="size-7 text-amber-300"
                            aria-hidden="true"
                        />
                        <div class="space-y-2">
                            <h2 class="text-2xl font-semibold">
                                {{ persona.label }}
                            </h2>
                            <p class="text-sm leading-6 text-zinc-300">
                                {{ persona.description }}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="inline-flex w-full items-center justify-between gap-3 rounded-lg bg-white px-4 py-3 text-sm font-bold text-zinc-950 outline-none hover:bg-amber-200 focus-visible:ring-2 focus-visible:ring-amber-300 disabled:cursor-wait disabled:opacity-60"
                        :disabled="entering !== null"
                        @click="enterAs(persona)"
                    >
                        <span>
                            {{
                                entering === persona.key
                                    ? 'Entering…'
                                    : `Enter as ${persona.label}`
                            }}
                        </span>
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </button>
                </article>
            </section>

            <aside
                class="grid gap-4 rounded-2xl border border-amber-400/30 bg-amber-300/10 p-5 text-sm leading-6 text-zinc-200 sm:grid-cols-[auto_1fr]"
            >
                <ShieldAlert class="size-6 text-amber-300" aria-hidden="true" />
                <div class="space-y-2">
                    <p class="font-semibold text-white">
                        About these preview roles
                    </p>
                    <p>
                        These personas are simulated stakeholder perspectives
                        for evaluation, not final municipal role or permission
                        policy. Some functions remain intentionally unavailable
                        pending municipal policy or authority.
                    </p>
                    <p>
                        Preview data can be restored by the preview
                        administrator.
                    </p>
                </div>
            </aside>
        </div>
    </main>
</template>
