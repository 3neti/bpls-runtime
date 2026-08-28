<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Building2, CheckCircle2 } from '@lucide/vue';
import { dashboard, login, register } from '@/routes';
import { index as servicesAndFeesIndex } from '@/routes/services-and-fees';
</script>

<template>
    <Head title="Business Permit and Licensing System" />

    <main class="min-h-svh bg-background text-foreground">
        <header class="border-b">
            <div
                class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-4 sm:px-8"
            >
                <div class="min-w-0">
                    <p class="font-semibold">Municipality of Ipil</p>
                    <p
                        class="truncate text-xs text-muted-foreground sm:text-sm"
                    >
                        Business Permit and Licensing System
                    </p>
                </div>
                <nav
                    aria-label="Account"
                    class="flex shrink-0 items-center gap-2"
                >
                    <Link
                        :href="servicesAndFeesIndex()"
                        class="hidden rounded-md px-3 py-2 text-sm font-medium outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring sm:inline-flex"
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
                            class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground outline-none hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            Register
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <section
            class="mx-auto grid max-w-6xl gap-10 px-5 py-14 sm:px-8 sm:py-20 lg:grid-cols-[1.2fr_0.8fr] lg:items-center"
        >
            <div class="space-y-6">
                <p class="text-sm font-medium text-muted-foreground">
                    Municipal online service
                </p>
                <div class="space-y-4">
                    <h1
                        class="max-w-3xl text-4xl font-semibold tracking-tight sm:text-5xl"
                    >
                        Business permits, clearly tracked from application to
                        municipal review.
                    </h1>
                    <p
                        class="max-w-2xl text-base leading-7 text-muted-foreground sm:text-lg"
                    >
                        Start a permit application, provide supporting
                        information, and follow the progress recorded by the
                        Municipality of Ipil.
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <Link
                        :href="servicesAndFeesIndex()"
                        class="inline-flex items-center gap-2 rounded-md border px-5 py-3 text-sm font-medium outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        View Services & Fees
                    </Link>
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="inline-flex items-center gap-2 rounded-md bg-primary px-5 py-3 text-sm font-medium text-primary-foreground outline-none hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        Continue to BPLS
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                    <template v-else>
                        <Link
                            :href="register()"
                            class="inline-flex items-center gap-2 rounded-md bg-primary px-5 py-3 text-sm font-medium text-primary-foreground outline-none hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            Create an account
                            <ArrowRight class="size-4" aria-hidden="true" />
                        </Link>
                        <Link
                            :href="login()"
                            class="rounded-md border px-5 py-3 text-sm font-medium outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            Log in
                        </Link>
                    </template>
                </div>
            </div>

            <aside
                class="rounded-2xl border bg-card p-6 shadow-sm sm:p-8"
                aria-labelledby="service-scope-heading"
            >
                <Building2
                    class="size-8 text-muted-foreground"
                    aria-hidden="true"
                />
                <h2
                    id="service-scope-heading"
                    class="mt-5 text-xl font-semibold"
                >
                    What you can do here
                </h2>
                <ul class="mt-5 space-y-4 text-sm leading-6">
                    <li class="flex gap-3">
                        <CheckCircle2
                            class="mt-0.5 size-5 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <span
                            >Create and submit a business permit
                            application.</span
                        >
                    </li>
                    <li class="flex gap-3">
                        <CheckCircle2
                            class="mt-0.5 size-5 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <span
                            >Review municipal processing, assessment, payment,
                            clearance, and artifact progress recorded for your
                            application.</span
                        >
                    </li>
                    <li class="flex gap-3">
                        <CheckCircle2
                            class="mt-0.5 size-5 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <span
                            >Receive account notices about municipal receipt and
                            processing.</span
                        >
                    </li>
                </ul>
                <p
                    class="mt-6 border-t pt-5 text-sm leading-6 text-muted-foreground"
                >
                    The current service ends at
                    <strong class="text-foreground"
                        >Ready for Authority Review</strong
                    >. Permit issuance, release, validity, and legal effect are
                    not available.
                </p>
            </aside>
        </section>
    </main>
</template>
