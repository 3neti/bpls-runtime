<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, FlaskConical } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LifecycleCleanroomEvidence } from '@/types/lifecycle-cleanroom';

defineOptions({ layout: AppLayout });

defineProps<{
    evidence: LifecycleCleanroomEvidence;
    workUrl: string;
}>();
</script>

<template>
    <Head title="Lifecycle Laboratory status" />

    <main class="mx-auto w-full max-w-4xl px-4 py-6 sm:px-6 lg:px-8">
        <section
            class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950"
            data-testid="active-cleanroom-status"
        >
            <header
                class="border-b border-zinc-200 p-5 sm:p-7 dark:border-zinc-800"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p
                            class="text-xs font-bold tracking-wider text-zinc-500 uppercase"
                        >
                            Lifecycle Laboratory
                        </p>
                        <h1 class="mt-1 text-2xl font-semibold">
                            {{ evidence.ceremony_label }}
                        </h1>
                    </div>
                    <span
                        class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-900"
                    >
                        {{ evidence.status }}
                    </span>
                </div>
                <p class="mt-3 font-mono text-xs break-all text-zinc-500">
                    {{ evidence.public_id }}
                </p>
            </header>

            <div class="grid gap-5 p-5 sm:p-7">
                <div>
                    <div
                        class="flex items-center justify-between gap-3 text-sm"
                    >
                        <span>Progress</span>
                        <strong
                            >{{ evidence.progress.completed_steps }} of
                            {{ evidence.progress.total_steps }}</strong
                        >
                    </div>
                    <div
                        class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800"
                    >
                        <div
                            class="h-full rounded-full bg-amber-400"
                            :style="{ width: `${evidence.progress.percent}%` }"
                        />
                    </div>
                </div>

                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-zinc-500">Current task</dt>
                        <dd class="mt-1 font-semibold">
                            {{
                                evidence.progress.next_task ??
                                'Ceremony complete'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Next actor</dt>
                        <dd class="mt-1 font-semibold">
                            {{
                                evidence.progress.next_actor ??
                                'No remaining actor'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Application</dt>
                        <dd class="mt-1 font-semibold">
                            {{
                                evidence.application
                                    ? `#${evidence.application.id}`
                                    : 'Not created'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Tracking reference</dt>
                        <dd class="mt-1 font-mono text-xs break-all">
                            {{
                                evidence.application?.tracking_reference ??
                                'Not assigned'
                            }}
                        </dd>
                    </div>
                </dl>

                <div
                    class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950 dark:border-sky-900 dark:bg-sky-950/30 dark:text-sky-100"
                >
                    <div class="flex gap-3">
                        <FlaskConical class="mt-0.5 size-5 shrink-0" />
                        <p>
                            This view reports the active synthetic ceremony.
                            Opening it does not advance the run or change your
                            signed-in actor.
                        </p>
                    </div>
                </div>

                <Link
                    :href="workUrl"
                    class="inline-flex min-h-11 w-fit items-center gap-2 rounded-lg bg-zinc-950 px-4 py-2 text-sm font-semibold text-white dark:bg-amber-300 dark:text-amber-950"
                >
                    Return to my work
                    <ArrowRight class="size-4" />
                </Link>
            </div>
        </section>
    </main>
</template>
