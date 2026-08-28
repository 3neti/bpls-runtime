<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PublicServiceCatalog from '@/components/services-and-fees/PublicServiceCatalog.vue';
import { dashboard, home, login, register } from '@/routes';
import { index as servicesAndFeesIndex } from '@/routes/services-and-fees';
import type { MunicipalPriceList } from '@/types';

defineProps<{
    priceList: MunicipalPriceList;
}>();
</script>

<template>
    <div class="min-h-svh bg-background text-foreground">
        <Head title="Services & Fees" />

        <header class="border-b bg-background/95">
            <div
                class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-4 sm:px-8"
            >
                <Link :href="home()" class="min-w-0 outline-none">
                    <p class="font-semibold">Municipality of Ipil</p>
                    <p
                        class="truncate text-xs text-muted-foreground sm:text-sm"
                    >
                        Business Permit and Licensing System
                    </p>
                </Link>
                <nav
                    aria-label="Catalog and account"
                    class="flex items-center gap-2"
                >
                    <Link
                        :href="servicesAndFeesIndex()"
                        aria-current="page"
                        class="hidden rounded-md bg-muted px-3 py-2 text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-ring sm:inline-flex"
                    >
                        Services & Fees
                    </Link>
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground outline-none hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        Open overview
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-md px-3 py-2 text-sm font-medium outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            Log in
                        </Link>
                        <Link
                            :href="register()"
                            class="hidden rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground outline-none hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring sm:inline-flex"
                        >
                            Register
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-5 py-10 sm:px-8 sm:py-14">
            <PublicServiceCatalog :price-list="priceList" />
        </main>
    </div>
</template>
