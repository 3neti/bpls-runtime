<script setup lang="ts">
type Attachment = {
    key: string;
    sequence: number;
    label: string;
    short_label: string;
    target: string;
    state: string;
    available: boolean;
    tone: string;
};

defineProps<{
    attachments: Attachment[];
    activeKey: string;
    desktop?: boolean;
}>();

const emit = defineEmits<{ select: [attachment: Attachment] }>();

const toneClasses: Record<string, string> = {
    amber: 'border-amber-500 bg-amber-100 text-amber-950',
    sky: 'border-sky-500 bg-sky-100 text-sky-950',
    violet: 'border-violet-500 bg-violet-100 text-violet-950',
    emerald: 'border-emerald-600 bg-emerald-100 text-emerald-950',
    rose: 'border-rose-500 bg-rose-100 text-rose-950',
    stone: 'border-stone-500 bg-stone-100 text-stone-950',
};
</script>

<template>
    <nav
        data-testid="application-attachment-rail"
        aria-label="Application attachments"
        role="tablist"
        :class="desktop ? 'grid' : 'flex min-w-max'"
        class="gap-1.5 print:hidden"
    >
        <button
            type="button"
            role="tab"
            :class="[
                desktop ? 'w-full border-l-4' : 'min-w-[9.5rem] border-t-4',
                toneClasses[attachment.tone] ?? toneClasses.stone,
                activeKey === attachment.key
                    ? 'translate-x-0 opacity-100 shadow-md'
                    : attachment.available
                      ? 'opacity-80 hover:opacity-100'
                      : 'opacity-55 hover:opacity-80',
            ]"
            class="min-h-16 rounded-sm border px-3 py-2 text-left transition outline-none focus-visible:ring-2 focus-visible:ring-amber-600"
            v-for="attachment in attachments"
            :key="attachment.key"
            :data-testid="`application-attachment-${attachment.key}`"
            :aria-current="activeKey === attachment.key ? 'page' : undefined"
            :aria-selected="activeKey === attachment.key"
            @click="emit('select', attachment)"
        >
            <span
                class="block text-[9px] font-black tracking-[0.18em] uppercase"
            >
                Attachment {{ String.fromCharCode(64 + attachment.sequence) }}
            </span>
            <span class="mt-0.5 block text-xs leading-4 font-black uppercase">
                {{ attachment.short_label }}
            </span>
            <span class="mt-1 block text-[9px] font-bold uppercase">
                {{ attachment.state.replaceAll('_', ' ') }}
            </span>
        </button>
    </nav>
</template>
