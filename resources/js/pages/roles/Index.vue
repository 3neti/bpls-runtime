<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { Check, Minus, ShieldCheck, TriangleAlert } from '@lucide/vue';
import { index } from '@/actions/App/Http/Controllers/Staff/RolePermissionController';
import AdministrationScopePanel from '@/components/administration/AdministrationScopePanel.vue';
import { Badge } from '@/components/ui/badge';
import type { BreadcrumbItem } from '@/types';

type PermissionState = {
    code: string;
    assigned: boolean;
    effective: boolean;
    source: 'admin_override' | 'assigned' | 'none';
};

type Role = {
    id: number;
    name: string;
    code: string;
    description: string | null;
    user_count: number;
    is_system: boolean;
    access_mode: 'admin_override' | 'assigned_permissions';
    assigned_permission_count: number;
    effective_permission_count: number;
    permissions: PermissionState[];
    unknown_assigned_permission_codes: string[];
};

type Permission = {
    code: string;
    name: string;
    description: string | null;
    area: string;
    catalog_status: 'stored' | 'missing';
};

defineProps<{
    summary: {
        role_count: number;
        assigned_user_count: number;
        canonical_permission_count: number;
        stored_permission_count: number;
        missing_permission_count: number;
        unknown_permission_count: number;
        catalog_in_sync: boolean;
    };
    roles: Role[];
    permissions: Permission[];
    catalog_drift: {
        missing_permission_codes: string[];
        unknown_permission_codes: string[];
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Roles & Permissions',
        href: index(),
    },
];

setLayoutProps({ breadcrumbs });

function permissionState(role: Role, code: string): PermissionState {
    return (
        role.permissions.find((permission) => permission.code === code) ?? {
            code,
            assigned: false,
            effective: false,
            source: 'none',
        }
    );
}

function accessLabel(source: PermissionState['source']): string {
    if (source === 'admin_override') {
        return 'Granted by Admin override';
    }

    if (source === 'assigned') {
        return 'Assigned';
    }

    return 'Not granted';
}
</script>

<template>
    <div class="contents">
        <Head title="Roles & Permissions" />

        <main class="flex h-full min-w-0 flex-1 flex-col gap-5 p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        Roles & Permissions
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Current effective access by role.
                    </p>
                </div>
                <Badge
                    :variant="summary.catalog_in_sync ? 'outline' : 'secondary'"
                    data-testid="permission-catalog-status"
                    :data-catalog-in-sync="summary.catalog_in_sync"
                >
                    <ShieldCheck v-if="summary.catalog_in_sync" />
                    <TriangleAlert v-else />
                    {{
                        summary.catalog_in_sync
                            ? 'Catalog in sync'
                            : 'Catalog drift detected'
                    }}
                </Badge>
            </section>

            <AdministrationScopePanel
                available="Inspect current roles, stored permission assignments, effective access, user counts, and permission-catalog differences."
                evidence="Stored assignments and effective runtime access remain distinct; the Admin override is shown as its own access source."
                unavailable="Role creation or editing, permission assignment, user-role assignment, and catalog reconciliation."
            />

            <section
                class="grid border border-sidebar-border/70 sm:grid-cols-2 lg:grid-cols-4 dark:border-sidebar-border"
                data-testid="role-permission-summary"
                :data-role-count="summary.role_count"
                :data-permission-count="summary.canonical_permission_count"
            >
                <div class="p-4">
                    <p class="text-xs font-medium text-muted-foreground">
                        Roles
                    </p>
                    <p class="mt-1 text-xl font-semibold text-foreground">
                        {{ summary.role_count }}
                    </p>
                </div>
                <div class="border-t p-4 sm:border-t-0 sm:border-l">
                    <p class="text-xs font-medium text-muted-foreground">
                        Assigned users
                    </p>
                    <p class="mt-1 text-xl font-semibold text-foreground">
                        {{ summary.assigned_user_count }}
                    </p>
                </div>
                <div class="border-t p-4 lg:border-t-0 lg:border-l">
                    <p class="text-xs font-medium text-muted-foreground">
                        Runtime permissions
                    </p>
                    <p class="mt-1 text-xl font-semibold text-foreground">
                        {{ summary.canonical_permission_count }}
                    </p>
                </div>
                <div class="border-t p-4 sm:border-l lg:border-t-0">
                    <p class="text-xs font-medium text-muted-foreground">
                        Catalog differences
                    </p>
                    <p class="mt-1 text-xl font-semibold text-foreground">
                        {{
                            summary.missing_permission_count +
                            summary.unknown_permission_count
                        }}
                    </p>
                </div>
            </section>

            <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="role in roles"
                    :key="role.id"
                    class="rounded-lg border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border"
                    data-testid="role-summary"
                    :data-role-id="role.id"
                    :data-role-code="role.code"
                    :data-access-mode="role.access_mode"
                    :data-user-count="role.user_count"
                    :data-assigned-permission-count="
                        role.assigned_permission_count
                    "
                    :data-effective-permission-count="
                        role.effective_permission_count
                    "
                    :data-unknown-assigned-permission-count="
                        role.unknown_assigned_permission_codes.length
                    "
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="font-medium text-foreground">
                                {{ role.name }}
                            </h2>
                            <p
                                class="mt-1 font-mono text-xs break-all text-muted-foreground"
                            >
                                {{ role.code }}
                            </p>
                        </div>
                        <Badge v-if="role.is_system" variant="outline">
                            System
                        </Badge>
                    </div>
                    <dl class="mt-4 grid grid-cols-3 gap-3 text-sm">
                        <div>
                            <dt class="text-muted-foreground">Users</dt>
                            <dd class="font-medium text-foreground">
                                {{ role.user_count }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Assigned</dt>
                            <dd class="font-medium text-foreground">
                                {{ role.assigned_permission_count }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Effective</dt>
                            <dd class="font-medium text-foreground">
                                {{ role.effective_permission_count }}
                            </dd>
                        </div>
                    </dl>
                    <p
                        v-if="role.access_mode === 'admin_override'"
                        class="mt-3 text-xs text-muted-foreground"
                    >
                        Admin access is granted by the runtime role override.
                    </p>
                </article>
            </section>

            <section
                class="min-w-0 border border-sidebar-border/70 dark:border-sidebar-border"
                data-testid="role-permission-matrix"
            >
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] border-collapse text-sm">
                        <thead class="bg-muted/40 text-left">
                            <tr>
                                <th class="w-[330px] px-4 py-3 font-medium">
                                    Permission
                                </th>
                                <th
                                    v-for="role in roles"
                                    :key="role.id"
                                    class="min-w-[120px] px-3 py-3 text-center font-medium"
                                >
                                    {{ role.name }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="permission in permissions"
                                :key="permission.code"
                                class="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                :data-permission-code="permission.code"
                            >
                                <th class="px-4 py-3 text-left font-normal">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <span
                                            class="font-medium text-foreground"
                                        >
                                            {{ permission.name }}
                                        </span>
                                        <Badge
                                            v-if="
                                                permission.catalog_status ===
                                                'missing'
                                            "
                                            variant="secondary"
                                        >
                                            Missing row
                                        </Badge>
                                    </div>
                                    <p
                                        class="mt-1 font-mono text-xs text-muted-foreground"
                                    >
                                        {{ permission.code }}
                                    </p>
                                </th>
                                <td
                                    v-for="role in roles"
                                    :key="role.id"
                                    class="px-3 py-3 text-center"
                                    :data-role-code="role.code"
                                    :data-access-source="
                                        permissionState(role, permission.code)
                                            .source
                                    "
                                >
                                    <span
                                        class="inline-flex size-8 items-center justify-center"
                                        :class="
                                            permissionState(
                                                role,
                                                permission.code,
                                            ).effective
                                                ? 'text-emerald-700 dark:text-emerald-400'
                                                : 'text-muted-foreground'
                                        "
                                        :title="
                                            accessLabel(
                                                permissionState(
                                                    role,
                                                    permission.code,
                                                ).source,
                                            )
                                        "
                                    >
                                        <Check
                                            v-if="
                                                permissionState(
                                                    role,
                                                    permission.code,
                                                ).effective
                                            "
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        <Minus
                                            v-else
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        <span class="sr-only">
                                            {{
                                                accessLabel(
                                                    permissionState(
                                                        role,
                                                        permission.code,
                                                    ).source,
                                                )
                                            }}
                                        </span>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                v-if="!summary.catalog_in_sync"
                class="border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100"
                data-testid="permission-catalog-drift"
            >
                <h2 class="font-medium">Permission catalog differences</h2>
                <p
                    v-if="catalog_drift.missing_permission_codes.length"
                    class="mt-2"
                >
                    Missing stored rows:
                    <span class="font-mono break-all">
                        {{ catalog_drift.missing_permission_codes.join(', ') }}
                    </span>
                </p>
                <p
                    v-if="catalog_drift.unknown_permission_codes.length"
                    class="mt-2"
                >
                    Unknown stored rows:
                    <span class="font-mono break-all">
                        {{ catalog_drift.unknown_permission_codes.join(', ') }}
                    </span>
                </p>
            </section>
        </main>
    </div>
</template>
