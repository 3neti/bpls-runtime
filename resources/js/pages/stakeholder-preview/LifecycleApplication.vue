<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ExecutableApplication from '@/components/permit-applications/ExecutableApplication.vue';
import AppLayout from '@/layouts/AppLayout.vue';

defineProps<{
    application: any;
    document: any;
    focus: string;
    scenario: { id: string; run_id: string };
}>();
defineOptions({ layout: AppLayout });
</script>

<template>
    <Head title="Executable Application" />
    <main
        class="mx-auto w-full max-w-7xl overflow-x-clip px-3 py-5 sm:px-6 lg:px-8"
    >
        <div class="mb-4">
            <p
                class="text-xs font-bold tracking-wider text-slate-500 uppercase"
            >
                Lifecycle Laboratory · {{ scenario.id }}
            </p>
            <h1 class="text-2xl font-black">
                The same Application, focused for
                {{ application.actor_context.actor_label }}
            </h1>
        </div>
        <ExecutableApplication
            :application="application"
            :document="document"
            mode="workspace"
            :initial-tab="
                application.actor_context.current_tasks[0]?.section ??
                'application'
            "
        />
    </main>
</template>
