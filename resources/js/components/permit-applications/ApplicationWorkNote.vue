<script setup lang="ts">
import {
    AlertTriangle,
    ArrowUpRight,
    Check,
    Clock3,
    LockKeyhole,
} from '@lucide/vue';
import { computed } from 'vue';

type ApplicationWorkNote = {
    id: string;
    actor_key: string;
    actor_label: string;
    instruction: string;
    section: string;
    anchor: string;
    state: string;
    state_label: string;
    tone: string;
    actionable: boolean;
    action_label: string | null;
    action_url: string | null;
    completed_at: string | null;
    blocking_reason: string | null;
};

const props = defineProps<{
    note: ApplicationWorkNote;
    index: number;
}>();
const emit = defineEmits<{
    activate: [note: ApplicationWorkNote];
}>();

const paperClass = computed(
    () =>
        ({
            cream: 'bg-[#fff4c7] border-[#d9bd64]',
            yellow: 'bg-[#ffe477] border-[#d3ad25]',
            blue: 'bg-[#ccecff] border-[#72b9de]',
            orange: 'bg-[#ffd19c] border-[#db8f3d]',
            green: 'bg-[#cdeebd] border-[#78b966]',
            violet: 'bg-[#e5d5ff] border-[#aa84dc]',
        })[props.note.tone] ?? 'bg-[#ffe477] border-[#d3ad25]',
);
const rotationClass = computed(
    () =>
        ['rotate-[-0.65deg]', 'rotate-[0.5deg]', 'rotate-[-0.25deg]'][
            props.index % 3
        ],
);

function completedDate(value: string | null): string | null {
    if (!value) {
        return null;
    }

    return new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium' }).format(
        new Date(value),
    );
}
</script>

<template>
    <article
        data-testid="application-work-note"
        :data-note-id="note.id"
        :data-note-actor="note.actor_key"
        :data-note-state="note.state"
        :data-actionable="note.actionable"
        :aria-label="`${note.actor_label}: ${note.instruction}. ${note.state_label}`"
        :class="[
            paperClass,
            rotationClass,
            note.actionable
                ? 'translate-y-[-2px] shadow-[7px_9px_0_rgba(15,23,42,0.2)] ring-2 ring-slate-950/10'
                : note.state === 'anticipated' || note.state === 'waiting'
                  ? 'opacity-65 shadow-[3px_5px_0_rgba(15,23,42,0.1)]'
                  : 'shadow-[4px_6px_0_rgba(15,23,42,0.14)]',
        ]"
        class="relative min-w-0 overflow-hidden border p-4 text-slate-950 transition sm:p-5"
    >
        <span
            aria-hidden="true"
            class="absolute top-0 left-1/2 h-4 w-20 -translate-x-1/2 -translate-y-1/2 rotate-[-2deg] bg-white/55 shadow-sm"
        />
        <div class="flex items-start justify-between gap-3 pt-1">
            <div class="min-w-0">
                <p class="text-[11px] font-black tracking-[0.18em] uppercase">
                    {{ note.actor_label }}
                </p>
                <p class="mt-2 leading-5 font-black break-words">
                    {{ note.instruction }}
                </p>
            </div>
            <span
                class="flex size-7 shrink-0 items-center justify-center rounded-full bg-white/55"
                aria-hidden="true"
            >
                <Check v-if="note.state === 'completed'" class="size-4" />
                <AlertTriangle
                    v-else-if="
                        note.state === 'blocked' || note.state === 'returned'
                    "
                    class="size-4"
                />
                <LockKeyhole
                    v-else-if="note.state === 'not_commissioned'"
                    class="size-4"
                />
                <ArrowUpRight v-else-if="note.actionable" class="size-4" />
                <Clock3 v-else class="size-4" />
            </span>
        </div>

        <div class="mt-4 border-t border-slate-950/15 pt-3">
            <p
                v-if="note.state === 'completed'"
                class="mb-2 inline-flex rotate-[-1deg] items-center gap-1 rounded-sm border-2 border-emerald-800 px-2 py-1 text-[10px] font-black tracking-[0.14em] text-emerald-900 uppercase"
            >
                <Check class="size-3" aria-hidden="true" /> Completed & recorded
            </p>
            <p class="text-xs leading-5 font-bold">
                {{ note.state_label }}
                <span
                    v-if="completedDate(note.completed_at)"
                    class="block font-medium"
                >
                    {{ completedDate(note.completed_at) }}
                </span>
            </p>
            <p
                v-if="note.blocking_reason"
                class="mt-2 text-xs leading-5 text-slate-700"
            >
                {{ note.blocking_reason }}
            </p>
        </div>

        <button
            v-if="note.actionable && note.action_url"
            type="button"
            class="mt-4 flex min-h-10 w-full items-center justify-between gap-3 rounded-md bg-slate-950 px-3 py-2 text-sm font-black text-white outline-none hover:bg-slate-800 focus-visible:ring-2 focus-visible:ring-slate-950 focus-visible:ring-offset-2"
            @click="emit('activate', note)"
        >
            <span>{{ note.action_label ?? 'Open work' }}</span>
            <ArrowUpRight class="size-4 shrink-0" aria-hidden="true" />
        </button>
    </article>
</template>
