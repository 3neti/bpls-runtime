<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Archive, Check } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LifecycleCleanroomEvidence } from '@/types/lifecycle-cleanroom';

defineOptions({ layout: AppLayout });

defineProps<{ evidence: LifecycleCleanroomEvidence }>();

function date(value: string | null): string {
    return value
        ? new Intl.DateTimeFormat('en-PH', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : 'Not recorded';
}
</script>

<template>
    <Head title="Retained lifecycle evidence" />

    <main class="mx-auto w-full max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
        <Link
            href="/stakeholder-preview/lifecycle-laboratory"
            class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-zinc-600 hover:text-zinc-950 dark:text-zinc-300 dark:hover:text-white"
        >
            <ArrowLeft class="size-4" /> Back to Lifecycle Laboratory
        </Link>

        <section
            class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950"
            data-testid="retained-cleanroom-evidence"
        >
            <header
                class="border-b border-zinc-200 p-5 sm:p-7 dark:border-zinc-800"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-bold tracking-wider text-zinc-500 uppercase"
                        >
                            Retained evidence
                        </p>
                        <h1 class="mt-1 text-2xl font-semibold">
                            {{ evidence.ceremony_label }}
                        </h1>
                        <p
                            class="mt-2 font-mono text-xs break-all text-zinc-500"
                        >
                            {{ evidence.public_id }}
                        </p>
                    </div>
                    <span
                        class="inline-flex items-center gap-2 rounded-full bg-zinc-900 px-3 py-1.5 text-xs font-bold text-white dark:bg-zinc-100 dark:text-zinc-950"
                    >
                        <Archive class="size-3.5" /> {{ evidence.status }}
                    </span>
                </div>
            </header>

            <div class="grid gap-6 p-5 sm:p-7">
                <p
                    class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm leading-6 dark:border-zinc-800 dark:bg-zinc-900"
                >
                    This is immutable laboratory history. Viewing it does not
                    reactivate, advance, reset, or alter the linked municipal
                    record.
                </p>

                <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <dt class="text-zinc-500">Disposition</dt>
                        <dd class="mt-1 font-semibold">
                            {{ evidence.disposition }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Progress</dt>
                        <dd class="mt-1 font-semibold">
                            {{ evidence.progress.completed_steps }} of
                            {{ evidence.progress.total_steps }}
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
                    <div>
                        <dt class="text-zinc-500">Created</dt>
                        <dd class="mt-1">
                            {{ date(evidence.timestamps.created_at) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Completed</dt>
                        <dd class="mt-1">
                            {{ date(evidence.timestamps.completed_at) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Retained</dt>
                        <dd class="mt-1">
                            {{ date(evidence.timestamps.retained_at) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Recorded activity</dt>
                        <dd class="mt-1">{{ evidence.event_count }} events</dd>
                    </div>
                </dl>

                <section>
                    <h2 class="text-lg font-semibold">Ceremony stages</h2>
                    <ol class="mt-3 grid gap-2 sm:grid-cols-2">
                        <li
                            v-for="step in evidence.steps"
                            :key="step.key"
                            class="flex items-start gap-3 rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-800"
                        >
                            <Check
                                v-if="step.status === 'completed'"
                                class="mt-0.5 size-4 shrink-0 text-emerald-600"
                            />
                            <span
                                v-else
                                class="mt-1 size-3 shrink-0 rounded-full border border-zinc-400"
                            />
                            <div>
                                <p class="font-semibold">{{ step.label }}</p>
                                <p
                                    class="mt-0.5 text-xs text-zinc-500 capitalize"
                                >
                                    {{ step.status }}
                                </p>
                            </div>
                        </li>
                    </ol>
                </section>
            </div>
        </section>
    </main>
</template>
