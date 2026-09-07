<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ExecutableApplication from '@/components/permit-applications/ExecutableApplication.vue';
import AppLayout from '@/layouts/AppLayout.vue';

defineProps<{
    application: any;
    document: any;
    focus: string;
    initialTab: string;
    recentCertificationOffice: string | null;
    routingTask: any | null;
    scenario: { id: string; run_id: string };
}>();

function actorName(label: string): string {
    return label.replace(/^Cleanroom \S+\s+/, '');
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head title="Executable Application" />
    <main
        class="mx-auto w-full max-w-[96rem] overflow-x-clip px-3 py-5 sm:px-6 lg:px-8"
    >
        <div class="mb-4">
            <p
                class="text-xs font-bold tracking-wider text-slate-500 uppercase"
            >
                Cleanroom
            </p>
            <h1 class="text-2xl font-black">
                Application ·
                {{ actorName(application.actor_context.actor_label) }}
            </h1>
        </div>
        <ExecutableApplication
            :application="application"
            :document="document"
            mode="workspace"
            :initial-tab="initialTab"
            :initial-task="focus"
            :recent-certification-office="recentCertificationOffice"
            :routing-task="routingTask"
            interactive-task-routing
        />
    </main>
</template>
