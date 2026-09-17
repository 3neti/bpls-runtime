<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { login } from '@/routes';
import { create, index } from '@/routes/citizen/permit-applications';
import { index as servicesAndFeesIndex } from '@/routes/services-and-fees';

defineProps<{ isNonProduction: boolean }>();
</script>

<template>
    <div class="min-h-svh bg-background text-foreground">
        <Head title="Business Permit and Licensing System" />
        <header class="border-b">
            <div class="mx-auto max-w-5xl px-5 py-6 sm:px-8">
                <p class="text-lg font-semibold">Municipality of Ipil</p>
            </div>
        </header>
        <main
            class="mx-auto grid max-w-5xl gap-8 px-5 py-8 sm:gap-10 sm:px-8 sm:py-12"
        >
            <div class="grid gap-3">
                <h1
                    class="max-w-3xl text-3xl font-semibold tracking-tight sm:text-4xl"
                >
                    Business Permit and Licensing System
                </h1>
                <p class="max-w-2xl leading-7 text-muted-foreground">
                    Start a business permit application or continue your
                    existing application.
                </p>
            </div>
            <nav aria-label="BPLS services" class="grid gap-4 sm:grid-cols-2">
                <Link
                    :href="create()"
                    class="grid gap-2 rounded-xl border border-primary bg-primary p-5 text-primary-foreground outline-none hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-4 focus-visible:ring-offset-background sm:p-6"
                >
                    <span class="text-lg font-semibold"
                        >Apply for a Business Permit</span
                    >
                    <span class="text-sm leading-6"
                        >Log in or create a citizen account to start.</span
                    >
                </Link>
                <Link
                    :href="index()"
                    class="grid gap-2 rounded-xl border bg-card p-5 outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-4 focus-visible:ring-offset-background sm:p-6"
                >
                    <span class="text-lg font-semibold"
                        >Track or Continue Application</span
                    >
                    <span class="text-sm leading-6 text-muted-foreground"
                        >Log in to view your applications and resume a
                        draft.</span
                    >
                </Link>
                <Link
                    :href="login()"
                    class="grid gap-2 rounded-xl border bg-card p-5 outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-4 focus-visible:ring-offset-background sm:p-6"
                >
                    <span class="text-lg font-semibold">Staff Login</span>
                    <span class="text-sm leading-6 text-muted-foreground"
                        >Municipal personnel: use your assigned account.</span
                    >
                </Link>
                <section
                    aria-labelledby="verify-heading"
                    class="grid content-start gap-2 rounded-xl border bg-card p-5 sm:p-6"
                >
                    <h2 id="verify-heading" class="text-lg font-semibold">
                        Verify Business Permit
                    </h2>
                    <p class="text-sm leading-6 text-muted-foreground">
                        Use the QR code or verification link shown on the
                        Permit. Verification is available through that exact
                        link, not a name search.
                    </p>
                </section>
            </nav>
            <Link
                :href="servicesAndFeesIndex()"
                class="w-fit rounded-md py-2 font-medium underline underline-offset-4 outline-none hover:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-4 focus-visible:ring-offset-background"
                >Municipal Schedule of Fees</Link
            >
            <aside
                v-if="isNonProduction"
                aria-label="Testing environment"
                class="grid gap-2 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
            >
                <p class="font-semibold">UAT · Not for production use</p>
                <p>
                    Test authority is synthetic, payments are simulated, and
                    municipal policy remains provisional. Test permits have no
                    production legal effect.
                </p>
            </aside>
        </main>
    </div>
</template>
