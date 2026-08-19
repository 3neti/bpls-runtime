<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Film, Plus } from '@lucide/vue';
import {
    create,
    edit,
    index,
} from '@/actions/App/Http/Controllers/Staff/StoryboardController';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type StoryboardRow = {
    id: number;
    title: string;
    summary: string | null;
    frames_count: number;
    updated_at: string | null;
};

defineProps<{
    storyboards: {
        data: StoryboardRow[];
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Storyboards',
        href: index(),
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Storyboards" />

        <main class="flex h-full flex-1 flex-col gap-4 p-4">
            <section class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        Storyboards
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Compose frame sequences and export PDF or video files.
                    </p>
                </div>
                <Button as-child>
                    <Link :href="create()">
                        <Plus />
                        New Storyboard
                    </Link>
                </Button>
            </section>

            <section
                class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background dark:border-sidebar-border"
            >
                <div
                    v-if="storyboards.data.length === 0"
                    class="flex min-h-64 flex-col items-center justify-center gap-3 p-8 text-center"
                >
                    <Film class="size-10 text-muted-foreground" />
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">
                            No storyboards yet
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Create the first storyboard workspace.
                        </p>
                    </div>
                </div>

                <div v-else class="divide-y divide-sidebar-border/70">
                    <Link
                        v-for="storyboard in storyboards.data"
                        :key="storyboard.id"
                        :href="edit(storyboard.id)"
                        class="grid gap-1 p-4 transition-colors hover:bg-muted/50"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <h2 class="font-medium text-foreground">
                                {{ storyboard.title }}
                            </h2>
                            <span class="text-sm text-muted-foreground">
                                {{ storyboard.frames_count }} frames
                            </span>
                        </div>
                        <p class="line-clamp-2 text-sm text-muted-foreground">
                            {{ storyboard.summary || 'No summary recorded.' }}
                        </p>
                    </Link>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
