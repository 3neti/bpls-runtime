<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    Download,
    FileText,
    Film,
    Image,
    Plus,
    Save,
    Trash2,
} from '@lucide/vue';
import { computed } from 'vue';
import {
    create,
    edit,
    exportPdf,
    exportVideo,
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/Staff/StoryboardController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type StoryboardFrame = {
    id: number | null;
    position: number;
    title: string;
    image_path: string | null;
    existing_image_path: string | null;
    image_url: string | null;
    image: File | null;
    description: string;
    dialogue: string;
    duration_seconds: number;
};

type StoryboardExport = {
    id: number;
    format: string;
    status: string;
    download_url: string | null;
    failure_message: string | null;
    completed_at: string | null;
    created_at: string | null;
};

type Storyboard = {
    id: number;
    title: string;
    summary: string | null;
    frames: Omit<StoryboardFrame, 'image'>[];
    exports: StoryboardExport[];
};

const props = defineProps<{
    storyboard: Storyboard | null;
    durationLimits: {
        min: number;
        max: number;
    };
}>();

const isEditing = computed(() => props.storyboard !== null);

const form = useForm({
    title: props.storyboard?.title ?? '',
    summary: props.storyboard?.summary ?? '',
    frames: props.storyboard?.frames.map((frame) => ({
        id: frame.id,
        position: frame.position,
        title: frame.title,
        image_path: frame.image_path,
        existing_image_path: frame.image_path,
        image_url: frame.image_url,
        image: null,
        description: frame.description ?? '',
        dialogue: frame.dialogue ?? '',
        duration_seconds: frame.duration_seconds,
    })) ?? [emptyFrame()],
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Storyboards',
        href: index(),
    },
    {
        title: isEditing.value ? 'Edit Storyboard' : 'New Storyboard',
        href:
            isEditing.value && props.storyboard
                ? edit(props.storyboard.id)
                : create(),
    },
];

const totalDuration = computed(() =>
    form.frames.reduce(
        (total, frame) => total + Number(frame.duration_seconds || 0),
        0,
    ),
);

const latestExports = computed(() => props.storyboard?.exports ?? []);

function emptyFrame(): StoryboardFrame {
    return {
        id: null,
        position: 1,
        title: '',
        image_path: null,
        existing_image_path: null,
        image_url: null,
        image: null,
        description: '',
        dialogue: '',
        duration_seconds: 5,
    };
}

function addFrame(): void {
    form.frames.push({
        ...emptyFrame(),
        position: form.frames.length + 1,
    });
    normalizePositions();
}

function removeFrame(index: number): void {
    if (form.frames.length === 1) {
        return;
    }

    form.frames.splice(index, 1);
    normalizePositions();
}

function moveFrame(index: number, direction: -1 | 1): void {
    const target = index + direction;

    if (target < 0 || target >= form.frames.length) {
        return;
    }

    const [frame] = form.frames.splice(index, 1);
    form.frames.splice(target, 0, frame);
    normalizePositions();
}

function normalizePositions(): void {
    form.frames.forEach((frame, index) => {
        frame.position = index + 1;
    });
}

function updateImage(event: Event, frame: StoryboardFrame): void {
    const input = event.target as HTMLInputElement;
    const image = input.files?.[0] ?? null;

    frame.image = image;
    frame.image_url = image ? URL.createObjectURL(image) : frame.image_url;
}

function submit(): void {
    normalizePositions();

    if (props.storyboard) {
        form.post(update.form(props.storyboard.id).action, {
            forceFormData: true,
        });

        return;
    }

    form.post(store.url(), {
        forceFormData: true,
    });
}

function requestPdfExport(): void {
    if (!props.storyboard) {
        return;
    }

    router.post(exportPdf.url(props.storyboard.id));
}

function requestVideoExport(): void {
    if (!props.storyboard) {
        return;
    }

    router.post(exportVideo.url(props.storyboard.id));
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="isEditing ? 'Edit Storyboard' : 'New Storyboard'" />

        <main class="flex h-full flex-1 flex-col gap-4 p-4">
            <section class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        {{ isEditing ? 'Edit Storyboard' : 'New Storyboard' }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ form.frames.length }} frames, {{ totalDuration }}
                        seconds total.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button as-child variant="outline">
                        <Link :href="index()">
                            <ArrowLeft />
                            Back
                        </Link>
                    </Button>
                    <Button
                        v-if="storyboard"
                        type="button"
                        variant="outline"
                        @click="requestPdfExport"
                    >
                        <FileText />
                        Export PDF
                    </Button>
                    <Button
                        v-if="storyboard"
                        type="button"
                        variant="outline"
                        @click="requestVideoExport"
                    >
                        <Film />
                        Export Video
                    </Button>
                    <Button
                        type="button"
                        :disabled="form.processing"
                        @click="submit"
                    >
                        <Save />
                        Save
                    </Button>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_380px]">
                <form class="grid gap-4" @submit.prevent="submit">
                    <section
                        class="grid gap-4 rounded-lg border border-sidebar-border/70 bg-background p-4 md:grid-cols-2 dark:border-sidebar-border"
                    >
                        <div class="grid gap-2">
                            <Label for="title">Storyboard title</Label>
                            <Input id="title" v-model="form.title" required />
                            <InputError :message="form.errors.title" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="summary">Summary</Label>
                            <textarea
                                id="summary"
                                v-model="form.summary"
                                rows="3"
                                class="flex min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs ring-offset-background transition-colors placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            />
                            <InputError :message="form.errors.summary" />
                        </div>
                    </section>

                    <section class="grid gap-3">
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <h2 class="text-sm font-semibold text-foreground">
                                Frames
                            </h2>
                            <Button
                                type="button"
                                variant="outline"
                                @click="addFrame"
                            >
                                <Plus />
                                Add Frame
                            </Button>
                        </div>

                        <article
                            v-for="(frame, frameIndex) in form.frames"
                            :key="frameIndex"
                            class="grid gap-4 rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <h3
                                    class="text-sm font-semibold text-foreground"
                                >
                                    Frame {{ frameIndex + 1 }}
                                </h3>
                                <div class="flex gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        :disabled="frameIndex === 0"
                                        @click="moveFrame(frameIndex, -1)"
                                    >
                                        <ArrowUp />
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        :disabled="
                                            frameIndex ===
                                            form.frames.length - 1
                                        "
                                        @click="moveFrame(frameIndex, 1)"
                                    >
                                        <ArrowDown />
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        :disabled="form.frames.length === 1"
                                        @click="removeFrame(frameIndex)"
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                            </div>

                            <div class="grid gap-4 lg:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label :for="`frame-title-${frameIndex}`">
                                        Title / scene label
                                    </Label>
                                    <Input
                                        :id="`frame-title-${frameIndex}`"
                                        v-model="frame.title"
                                        required
                                    />
                                    <InputError
                                        :message="
                                            form.errors[
                                                `frames.${frameIndex}.title`
                                            ]
                                        "
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label
                                        :for="`frame-duration-${frameIndex}`"
                                    >
                                        Duration seconds
                                    </Label>
                                    <Input
                                        :id="`frame-duration-${frameIndex}`"
                                        v-model.number="frame.duration_seconds"
                                        type="number"
                                        :min="durationLimits.min"
                                        :max="durationLimits.max"
                                        required
                                    />
                                    <InputError
                                        :message="
                                            form.errors[
                                                `frames.${frameIndex}.duration_seconds`
                                            ]
                                        "
                                    />
                                </div>
                            </div>

                            <div class="grid gap-4 lg:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label :for="`frame-image-${frameIndex}`">
                                        Image
                                    </Label>
                                    <Input
                                        :id="`frame-image-${frameIndex}`"
                                        type="file"
                                        accept="image/*"
                                        @change="updateImage($event, frame)"
                                    />
                                    <input
                                        type="hidden"
                                        :name="`frames[${frameIndex}][existing_image_path]`"
                                        :value="frame.image_path ?? ''"
                                    />
                                    <InputError
                                        :message="
                                            form.errors[
                                                `frames.${frameIndex}.image`
                                            ]
                                        "
                                    />
                                </div>
                                <div
                                    class="flex min-h-28 items-center justify-center rounded-md border border-dashed border-sidebar-border/70 bg-muted/30 text-sm text-muted-foreground"
                                >
                                    <img
                                        v-if="frame.image_url"
                                        :src="frame.image_url"
                                        alt=""
                                        class="h-28 w-full object-cover"
                                    />
                                    <span
                                        v-else
                                        class="flex items-center gap-2"
                                    >
                                        <Image class="size-4" />
                                        Visual placeholder
                                    </span>
                                </div>
                            </div>

                            <div class="grid gap-4 lg:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label
                                        :for="`frame-description-${frameIndex}`"
                                    >
                                        Description / action notes
                                    </Label>
                                    <textarea
                                        :id="`frame-description-${frameIndex}`"
                                        v-model="frame.description"
                                        rows="4"
                                        class="flex min-h-28 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs ring-offset-background transition-colors placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                    />
                                    <InputError
                                        :message="
                                            form.errors[
                                                `frames.${frameIndex}.description`
                                            ]
                                        "
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label
                                        :for="`frame-dialogue-${frameIndex}`"
                                    >
                                        Dialogue / voiceover
                                    </Label>
                                    <textarea
                                        :id="`frame-dialogue-${frameIndex}`"
                                        v-model="frame.dialogue"
                                        rows="4"
                                        class="flex min-h-28 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs ring-offset-background transition-colors placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                    />
                                    <InputError
                                        :message="
                                            form.errors[
                                                `frames.${frameIndex}.dialogue`
                                            ]
                                        "
                                    />
                                </div>
                            </div>
                        </article>
                    </section>
                </form>

                <aside class="grid content-start gap-4">
                    <section
                        class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                    >
                        <h2 class="text-sm font-semibold text-foreground">
                            Ordered Preview
                        </h2>
                        <div class="mt-3 grid gap-3">
                            <article
                                v-for="(frame, frameIndex) in form.frames"
                                :key="`preview-${frameIndex}`"
                                class="grid gap-2 rounded-md border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                            >
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <h3
                                        class="truncate text-sm font-medium text-foreground"
                                    >
                                        {{ frameIndex + 1 }}.
                                        {{ frame.title || 'Untitled frame' }}
                                    </h3>
                                    <span
                                        class="shrink-0 text-xs text-muted-foreground"
                                    >
                                        {{ frame.duration_seconds || 0 }} sec
                                    </span>
                                </div>
                                <div
                                    class="flex h-24 items-center justify-center overflow-hidden rounded border border-dashed border-sidebar-border/70 bg-muted/30 text-xs text-muted-foreground"
                                >
                                    <img
                                        v-if="frame.image_url"
                                        :src="frame.image_url"
                                        alt=""
                                        class="h-full w-full object-cover"
                                    />
                                    <span v-else>Visual placeholder</span>
                                </div>
                                <p
                                    class="line-clamp-3 text-xs text-muted-foreground"
                                >
                                    {{
                                        frame.description ||
                                        'No action notes recorded.'
                                    }}
                                </p>
                                <p
                                    class="line-clamp-2 text-xs text-muted-foreground"
                                >
                                    {{
                                        frame.dialogue ||
                                        'No dialogue recorded.'
                                    }}
                                </p>
                            </article>
                        </div>
                    </section>

                    <section
                        v-if="storyboard"
                        class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                    >
                        <h2 class="text-sm font-semibold text-foreground">
                            Exports
                        </h2>
                        <div class="mt-3 grid gap-2">
                            <div
                                v-if="latestExports.length === 0"
                                class="text-sm text-muted-foreground"
                            >
                                No exports requested yet.
                            </div>
                            <article
                                v-for="storyboardExport in latestExports"
                                :key="storyboardExport.id"
                                class="grid gap-2 rounded-md border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border"
                            >
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <span class="font-medium uppercase">
                                        {{ storyboardExport.format }}
                                    </span>
                                    <span class="text-muted-foreground">
                                        {{ storyboardExport.status }}
                                    </span>
                                </div>
                                <a
                                    v-if="storyboardExport.download_url"
                                    :href="storyboardExport.download_url"
                                    class="inline-flex items-center gap-2 text-sm font-medium text-primary"
                                    download
                                >
                                    <Download class="size-4" />
                                    Download
                                </a>
                                <p
                                    v-if="storyboardExport.failure_message"
                                    class="text-xs text-destructive"
                                >
                                    {{ storyboardExport.failure_message }}
                                </p>
                            </article>
                        </div>
                    </section>
                </aside>
            </section>
        </main>
    </AppLayout>
</template>
