<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import {
    Building2,
    CircleAlert,
    CircleCheck,
    Database,
    ShieldAlert,
    UserRoundCog,
} from '@lucide/vue';
import { index } from '@/actions/App/Http/Controllers/Staff/MunicipalityConfigurationController';
import { Badge } from '@/components/ui/badge';
import type { BreadcrumbItem } from '@/types';

type Signatory = {
    role: string;
    name: string;
    title: string;
    authority_status: string;
};

defineProps<{
    identity: {
        municipality_name: string;
        province: string;
        system_name: string;
    };
    permit_signatories: Signatory[];
    authority: {
        signatory_count: number;
        verified_signatory_count: number;
        unverified_signatory_count: number;
        all_signatories_verified: boolean;
        permit_issuance_authorized: boolean;
        policy_note: string;
    };
    source: {
        type: string;
        persisted_administration: boolean;
        read_only: boolean;
        policy_note: string;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Municipality',
        href: index(),
    },
];

setLayoutProps({ breadcrumbs });

function statusLabel(status: string): string {
    return status.replaceAll('_', ' ');
}
</script>

<template>
    <div class="contents">
        <Head title="Municipality Configuration" />

        <main class="flex h-full min-w-0 flex-1 flex-col gap-5 p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        Municipality Configuration
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Runtime identity, document signatories, and authority
                        readiness.
                    </p>
                </div>
                <Badge variant="outline">
                    <Database />
                    Read only
                </Badge>
            </section>

            <section
                class="grid border border-sidebar-border/70 md:grid-cols-[minmax(0,1fr)_18rem] dark:border-sidebar-border"
                data-testid="municipality-configuration-summary"
                :data-signatory-count="authority.signatory_count"
                :data-verified-signatory-count="
                    authority.verified_signatory_count
                "
                :data-unverified-signatory-count="
                    authority.unverified_signatory_count
                "
                :data-all-signatories-verified="
                    authority.all_signatories_verified
                "
                :data-permit-issuance-authorized="
                    authority.permit_issuance_authorized
                "
                :data-read-only="source.read_only"
                :data-province="identity.province"
                :data-system-name="identity.system_name"
                :data-source-type="source.type"
            >
                <div class="min-w-0 p-5">
                    <div class="flex items-start gap-3">
                        <Building2 class="mt-0.5 size-5 shrink-0" />
                        <div class="min-w-0">
                            <p
                                class="text-xs font-medium text-muted-foreground uppercase"
                            >
                                Local government unit
                            </p>
                            <h2
                                class="mt-1 text-lg font-semibold break-words text-foreground"
                                data-testid="municipality-name"
                            >
                                {{ identity.municipality_name }}
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                {{ identity.province }}
                            </p>
                        </div>
                    </div>
                    <div class="mt-5 border-t pt-4">
                        <p
                            class="text-xs font-medium text-muted-foreground uppercase"
                        >
                            System name
                        </p>
                        <p
                            class="mt-1 text-sm font-medium break-words text-foreground"
                        >
                            {{ identity.system_name }}
                        </p>
                    </div>
                </div>
                <div class="border-t p-5 md:border-t-0 md:border-l">
                    <div class="flex items-start gap-3">
                        <Database class="mt-0.5 size-5 shrink-0" />
                        <div>
                            <p
                                class="text-xs font-medium text-muted-foreground uppercase"
                            >
                                Configuration source
                            </p>
                            <p class="mt-1 text-sm font-medium text-foreground">
                                Runtime configuration
                            </p>
                            <p class="mt-2 text-xs text-muted-foreground">
                                {{ source.policy_note }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section
                class="border border-amber-300 bg-amber-50 p-4 text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
                data-testid="municipality-authority-boundary"
            >
                <div class="flex items-start gap-3">
                    <ShieldAlert class="mt-0.5 size-5 shrink-0" />
                    <div>
                        <h2 class="text-sm font-semibold">
                            Permit authority remains unresolved
                        </h2>
                        <p class="mt-1 text-sm">
                            {{ authority.policy_note }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="min-w-0">
                <div
                    class="mb-3 flex flex-wrap items-center justify-between gap-2"
                >
                    <div>
                        <h2 class="text-base font-semibold text-foreground">
                            Permit signatories
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Configured document evidence and current authority
                            status.
                        </p>
                    </div>
                    <Badge
                        :variant="
                            authority.all_signatories_verified
                                ? 'default'
                                : 'secondary'
                        "
                        data-testid="municipality-authority-status"
                    >
                        <CircleCheck
                            v-if="authority.all_signatories_verified"
                        />
                        <CircleAlert v-else />
                        {{ authority.verified_signatory_count }} of
                        {{ authority.signatory_count }} verified
                    </Badge>
                </div>

                <div
                    class="divide-y border border-sidebar-border/70 dark:border-sidebar-border"
                    data-testid="municipality-signatories"
                >
                    <div
                        v-for="signatory in permit_signatories"
                        :key="`${signatory.role}:${signatory.name}`"
                        class="grid gap-3 p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
                        data-testid="municipality-signatory"
                        :data-role="signatory.role"
                        :data-authority-status="signatory.authority_status"
                    >
                        <div class="flex min-w-0 items-start gap-3">
                            <UserRoundCog class="mt-0.5 size-5 shrink-0" />
                            <div class="min-w-0">
                                <p
                                    class="font-medium break-words text-foreground"
                                >
                                    {{ signatory.name }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{ signatory.title }} · {{ signatory.role }}
                                </p>
                            </div>
                        </div>
                        <Badge
                            :variant="
                                signatory.authority_status === 'verified'
                                    ? 'default'
                                    : 'outline'
                            "
                        >
                            {{ statusLabel(signatory.authority_status) }}
                        </Badge>
                    </div>

                    <div
                        v-if="permit_signatories.length === 0"
                        class="p-5 text-sm text-muted-foreground"
                    >
                        No permit signatories are configured.
                    </div>
                </div>
            </section>
        </main>
    </div>
</template>
