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
    engineering: Building2,
    mpdo: Building2,
    assessor: Building2,
    health: Building2,
    menro: Building2,
    mayor_office: Landmark,
    releasing: ClipboardCheck,
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
    <Head title="Business Permit and Licensing System" />

    <main class="min-h-svh bg-zinc-950 text-white">
        <div
            class="border-b border-amber-400/50 bg-amber-300 px-5 py-2 text-center text-xs font-semibold tracking-wide text-amber-950"
        >
            Preview Environment · Sample Data
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
                            Business Permit and Licensing System
                        </p>
                        <h1
                            class="max-w-3xl text-4xl font-semibold tracking-tight sm:text-5xl"
                        >
                            Choose a perspective
                        </h1>
                        <p
                            class="max-w-3xl text-base leading-7 text-zinc-300 sm:text-lg"
                        >
                            Select a role to explore its common tasks using
                            prepared sample records. No username or password is
                            needed.
                        </p>
                    </div>
                </div>

                <Link
                    :href="walkthrough()"
                    class="inline-flex w-fit items-center gap-2 rounded-lg border border-zinc-700 px-4 py-3 text-sm font-semibold outline-none hover:bg-zinc-900 focus-visible:ring-2 focus-visible:ring-amber-300"
                >
                    <Building2 class="size-4" aria-hidden="true" />
                    Open Quick Start
                </Link>
            </header>

            <section
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
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
                    <p class="font-semibold text-white">About the preview</p>
                    <p>
                        These roles are provided for evaluation and do not set
                        final municipal access policy. Some functions remain
                        unavailable until the Municipality confirms the
                        responsible office or authority.
                    </p>
                    <p>
                        All records shown here are sample data and can be
                        restored by the preview administrator.
                    </p>
                </div>
            </aside>
        </div>
    </main>
</template>
