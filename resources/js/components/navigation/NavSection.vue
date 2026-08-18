<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useSidebar } from '@/components/ui/sidebar/utils';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavSection } from '@/types';

const props = defineProps<{
    section: NavSection;
}>();

const { isMobile, setOpenMobile } = useSidebar();
const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
const hasActiveItem = computed(() =>
    props.section.items.some((item) => isCurrentOrParentUrl(item.href)),
);
const isOpen = ref(true);
const sectionId = computed(
    () =>
        `navigation-${props.section.title
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')}`,
);

function closeMobileNavigation(): void {
    if (isMobile.value) {
        setOpenMobile(false);
    }
}

function toggleSection(): void {
    isOpen.value = !isOpen.value;
}
</script>

<template>
    <SidebarGroup class="px-2 py-1">
        <div>
            <button
                v-if="section.collapsible"
                type="button"
                class="flex min-h-8 w-full items-center justify-between rounded-md px-2 text-left text-xs font-medium text-sidebar-foreground/70 outline-none group-data-[collapsible=icon]/sidebar-wrapper:hidden hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:ring-2 focus-visible:ring-sidebar-ring"
                :aria-expanded="isOpen || hasActiveItem"
                :aria-controls="sectionId"
                @click="toggleSection"
            >
                <span>{{ section.title }}</span>
                <ChevronDown
                    class="size-3.5 transition-transform"
                    :class="{ 'rotate-180': isOpen || hasActiveItem }"
                    aria-hidden="true"
                />
            </button>
            <SidebarGroupLabel v-else>{{ section.title }}</SidebarGroupLabel>

            <SidebarMenu
                v-show="!section.collapsible || isOpen || hasActiveItem"
                :id="sectionId"
                class="mt-0.5"
            >
                <SidebarMenuItem
                    v-for="item in section.items"
                    :key="item.title"
                >
                    <SidebarMenuButton
                        as-child
                        :is-active="isCurrentUrl(item.href)"
                        :tooltip="item.title"
                    >
                        <Link
                            :href="item.href"
                            :aria-current="
                                isCurrentUrl(item.href) ? 'page' : undefined
                            "
                            @click="closeMobileNavigation"
                        >
                            <component :is="item.icon" aria-hidden="true" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </div>
    </SidebarGroup>
</template>
