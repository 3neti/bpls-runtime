<script setup lang="ts">
import NavSection from '@/components/navigation/NavSection.vue';
import type { NavSection as NavigationSection } from '@/types';

defineProps<{
    sections: NavigationSection[];
}>();
</script>

<template>
    <nav aria-label="Primary navigation">
        <NavSection
            v-for="section in sections"
            :key="section.title"
            :section="section"
        />
    </nav>
</template>
