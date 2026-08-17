<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import {
    BadgeCheck,
    Building2,
    CalendarClock,
    CircleAlert,
    Database,
    FileSignature,
    Link2,
    ShieldAlert,
    UserRoundCog,
} from '@lucide/vue';
import { index } from '@/actions/App/Http/Controllers/Staff/MunicipalityConfigurationController';
import { Badge } from '@/components/ui/badge';
import type { BreadcrumbItem } from '@/types';

type Official = {
    key: string;
    role: string;
    name: string;
    title: string;
    configuration_status: string;
    configured_authority_claim: string;
    authorized_signatory: boolean;
    effective_term: {
        effective_from: string | null;
        effective_until: string | null;
        status: string;
    };
    provenance: {
        source_type: string;
        legacy_fields: string[];
        legacy_source_status: string;
        production_snapshot_status: string;
    };
};

type DocumentAssociation = {
    official_key: string;
    official_role: string;
    document_type: string;
    relationship: string;
    current_runtime_use: boolean;
    legacy_renderer_status: string;
    production_layout_status: string;
    authorizes_signature: boolean;
    authorizes_issuance: boolean;
    authorizes_legal_effect: boolean;
};

type AuthorityStage = {
    key: string;
    status: string;
    satisfied: boolean;
};

defineProps<{
    identity: {
        municipality_name: string;
        province: string;
        system_name: string;
    };
    officials: Official[];
    document_associations: DocumentAssociation[];
    authority_chain: AuthorityStage[];
    authority: {
        official_count: number;
        configured_official_count: number;
        document_association_count: number;
        current_document_association_count: number;
        effective_term_evidence_count: number;
        authorized_signatory_count: number;
        permit_issuance_authorized: boolean;
        permit_release_authorized: boolean;
        legal_effect_authorized: boolean;
        policy_note: string;
    };
    source: {
        type: string;
        legacy_source_status: string;
        production_snapshot_status: string;
        production_settings_record_count: number;
        effective_dates_evidenced: boolean;
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

function label(value: string): string {
    return value.replaceAll('_', ' ');
}

function effectiveTerm(official: Official): string {
    if (official.effective_term.status === 'not_evidenced') {
        return 'No effective term evidenced';
    }

    return `${official.effective_term.effective_from ?? 'Open'} to ${official.effective_term.effective_until ?? 'Open'}`;
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
                        Officials, document associations, and authority
                        evidence.
                    </p>
                </div>
                <Badge variant="outline">
                    <Database />
                    Read only
                </Badge>
            </section>

            <section
                class="grid border border-sidebar-border/70 md:grid-cols-[minmax(0,1fr)_20rem] dark:border-sidebar-border"
                data-testid="municipality-configuration-summary"
                :data-official-count="authority.official_count"
                :data-configured-official-count="
                    authority.configured_official_count
                "
                :data-document-association-count="
                    authority.document_association_count
                "
                :data-current-document-association-count="
                    authority.current_document_association_count
                "
                :data-effective-term-evidence-count="
                    authority.effective_term_evidence_count
                "
                :data-authorized-signatory-count="
                    authority.authorized_signatory_count
                "
                :data-permit-issuance-authorized="
                    authority.permit_issuance_authorized
                "
                :data-permit-release-authorized="
                    authority.permit_release_authorized
                "
                :data-legal-effect-authorized="
                    authority.legal_effect_authorized
                "
                :data-read-only="source.read_only"
                :data-province="identity.province"
                :data-system-name="identity.system_name"
                :data-source-type="source.type"
                :data-production-snapshot-status="
                    source.production_snapshot_status
                "
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
                        <div class="min-w-0">
                            <p
                                class="text-xs font-medium text-muted-foreground uppercase"
                            >
                                Evidence source
                            </p>
                            <p class="mt-1 text-sm font-medium text-foreground">
                                Runtime configuration
                            </p>
                            <p class="mt-2 text-xs text-muted-foreground">
                                Legacy source:
                                {{ label(source.legacy_source_status) }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Production snapshot:
                                {{ label(source.production_snapshot_status) }}
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
                            Document configuration is not municipal authority
                        </h2>
                        <p class="mt-1 text-sm">
                            {{ authority.policy_note }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="min-w-0">
                <div class="mb-3">
                    <h2 class="text-base font-semibold text-foreground">
                        Authority chain
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Each stage remains independent and must carry its own
                        evidence.
                    </p>
                </div>
                <div
                    class="grid border border-sidebar-border/70 sm:grid-cols-2 xl:grid-cols-5 dark:border-sidebar-border"
                    data-testid="municipality-authority-chain"
                >
                    <div
                        v-for="stage in authority_chain"
                        :key="stage.key"
                        class="min-w-0 border-b p-4 last:border-b-0 sm:border-r sm:last:border-r-0 xl:border-b-0"
                        data-testid="municipality-authority-stage"
                        :data-stage="stage.key"
                        :data-status="stage.status"
                        :data-satisfied="stage.satisfied"
                    >
                        <component
                            :is="stage.satisfied ? BadgeCheck : CircleAlert"
                            class="size-5"
                        />
                        <p class="mt-3 text-sm font-medium text-foreground">
                            {{ label(stage.key) }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ label(stage.status) }}
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
                            Configured officials
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Runtime values with source and term evidence shown
                            separately.
                        </p>
                    </div>
                    <Badge variant="secondary">
                        <UserRoundCog />
                        {{ authority.configured_official_count }} configured
                    </Badge>
                </div>

                <div
                    class="divide-y border border-sidebar-border/70 dark:border-sidebar-border"
                    data-testid="municipality-officials"
                >
                    <article
                        v-for="official in officials"
                        :key="official.key"
                        class="grid gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_minmax(14rem,0.75fr)_auto] lg:items-start"
                        data-testid="municipality-official"
                        :data-official-key="official.key"
                        :data-role="official.role"
                        :data-configuration-status="
                            official.configuration_status
                        "
                        :data-authorized-signatory="
                            official.authorized_signatory
                        "
                        :data-effective-term-status="
                            official.effective_term.status
                        "
                        :data-production-status="
                            official.provenance.production_snapshot_status
                        "
                    >
                        <div class="flex min-w-0 items-start gap-3">
                            <UserRoundCog class="mt-0.5 size-5 shrink-0" />
                            <div class="min-w-0">
                                <p
                                    class="font-medium break-words text-foreground"
                                >
                                    {{ official.name }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{ official.title }} · {{ official.role }}
                                </p>
                                <p class="mt-2 text-xs text-muted-foreground">
                                    Legacy setting:
                                    {{
                                        label(
                                            official.provenance
                                                .legacy_source_status,
                                        )
                                    }}
                                    · Production:
                                    {{
                                        label(
                                            official.provenance
                                                .production_snapshot_status,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                        <div class="flex min-w-0 items-start gap-3">
                            <CalendarClock class="mt-0.5 size-5 shrink-0" />
                            <div>
                                <p
                                    class="text-xs font-medium text-muted-foreground uppercase"
                                >
                                    Effective term
                                </p>
                                <p class="mt-1 text-sm text-foreground">
                                    {{ effectiveTerm(official) }}
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 lg:justify-end">
                            <Badge variant="outline">
                                {{ label(official.configuration_status) }}
                            </Badge>
                            <Badge variant="secondary">
                                Signatory authority unresolved
                            </Badge>
                        </div>
                    </article>
                </div>
            </section>

            <section class="min-w-0">
                <div class="mb-3">
                    <h2 class="text-base font-semibold text-foreground">
                        Document associations
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Template use is evidence of presentation behavior, not
                        signing authority.
                    </p>
                </div>
                <div
                    class="overflow-x-auto border border-sidebar-border/70 dark:border-sidebar-border"
                    data-testid="municipality-document-associations"
                >
                    <table class="w-full min-w-[48rem] text-left text-sm">
                        <thead
                            class="border-b bg-muted/40 text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3 font-medium">
                                    Official role
                                </th>
                                <th class="px-4 py-3 font-medium">Document</th>
                                <th class="px-4 py-3 font-medium">
                                    Relationship
                                </th>
                                <th class="px-4 py-3 font-medium">Legacy</th>
                                <th class="px-4 py-3 font-medium">
                                    Production layout
                                </th>
                                <th class="px-4 py-3 font-medium">Authority</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="association in document_associations"
                                :key="`${association.official_key}:${association.document_type}`"
                                data-testid="municipality-document-association"
                                :data-official-key="association.official_key"
                                :data-document-type="association.document_type"
                                :data-current-runtime-use="
                                    association.current_runtime_use
                                "
                                :data-production-layout-status="
                                    association.production_layout_status
                                "
                                :data-authorizes-signature="
                                    association.authorizes_signature
                                "
                            >
                                <td
                                    class="px-4 py-3 font-medium text-foreground"
                                >
                                    <span class="flex items-center gap-2">
                                        <FileSignature
                                            class="size-4 shrink-0"
                                        />
                                        {{ association.official_role }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ label(association.document_type) }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ label(association.relationship) }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{
                                        label(
                                            association.legacy_renderer_status,
                                        )
                                    }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{
                                        label(
                                            association.production_layout_status,
                                        )
                                    }}
                                </td>
                                <td class="px-4 py-3">
                                    <Badge variant="outline">
                                        <Link2 />
                                        Does not authorize
                                    </Badge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</template>
