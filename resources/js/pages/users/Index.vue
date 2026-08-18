<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import { Search, UserRoundCheck, Users, X } from '@lucide/vue';
import { ref } from 'vue';
import { index } from '@/actions/App/Http/Controllers/Staff/UserDirectoryController';
import AdministrationScopePanel from '@/components/administration/AdministrationScopePanel.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { BreadcrumbItem } from '@/types';

type UserRow = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    created_at: string | null;
    role: {
        name: string;
        code: string;
    } | null;
    business_owner: {
        id: number;
        name: string;
    } | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type RoleOption = {
    label: string;
    value: string;
    user_count: number;
};

const props = defineProps<{
    users: {
        data: UserRow[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: {
        q: string;
        role: string | null;
    };
    roles: RoleOption[];
    summary: {
        user_count: number;
        verified_user_count: number;
        linked_owner_count: number;
        unassigned_role_count: number;
        role_distribution: Record<string, number>;
    };
}>();

const search = ref(props.filters.q ?? '');
const role = ref(props.filters.role ?? '');

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Users',
        href: index(),
    },
];

setLayoutProps({ breadcrumbs });

function applyFilters(): void {
    router.get(
        index.url({
            query: {
                q: search.value || undefined,
                role: role.value || undefined,
            },
        }),
        {},
        {
            preserveState: true,
            replace: true,
        },
    );
}

function clearFilters(): void {
    search.value = '';
    role.value = '';
    router.get(index.url(), {}, { preserveState: true, replace: true });
}

function paginationLabel(label: string): string {
    return label.replace('&laquo;', '‹').replace('&raquo;', '›');
}
</script>

<template>
    <div class="contents">
        <Head title="Users" />

        <main class="flex h-full min-w-0 flex-1 flex-col gap-5 p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">Users</h1>
                    <p class="text-sm text-muted-foreground">
                        Application accounts and their current identity links.
                    </p>
                </div>
                <Badge variant="outline">
                    <Users />
                    {{ summary.user_count }} account<span
                        v-if="summary.user_count !== 1"
                        >s</span
                    >
                </Badge>
            </section>

            <AdministrationScopePanel
                available="Search and inspect application accounts, verification state, current role labels, and recorded legal-owner links."
                evidence="An account, a legal BusinessOwner identity, and an application submission actor are separate facts. This directory shows only links recorded by the canonical model."
                unavailable="Account provisioning, role assignment, activation or deactivation, password reset, and account mutation."
            />

            <section
                class="grid border border-sidebar-border/70 sm:grid-cols-2 lg:grid-cols-4 dark:border-sidebar-border"
                data-testid="user-directory-summary"
                :data-user-count="summary.user_count"
                :data-verified-user-count="summary.verified_user_count"
                :data-linked-owner-count="summary.linked_owner_count"
                :data-unassigned-role-count="summary.unassigned_role_count"
            >
                <div class="p-4">
                    <p class="text-xs font-medium text-muted-foreground">
                        Accounts
                    </p>
                    <p class="mt-1 text-xl font-semibold text-foreground">
                        {{ summary.user_count }}
                    </p>
                </div>
                <div class="border-t p-4 sm:border-t-0 sm:border-l">
                    <p class="text-xs font-medium text-muted-foreground">
                        Email verified
                    </p>
                    <p class="mt-1 text-xl font-semibold text-foreground">
                        {{ summary.verified_user_count }}
                    </p>
                </div>
                <div class="border-t p-4 lg:border-t-0 lg:border-l">
                    <p class="text-xs font-medium text-muted-foreground">
                        Owner linked
                    </p>
                    <p class="mt-1 text-xl font-semibold text-foreground">
                        {{ summary.linked_owner_count }}
                    </p>
                </div>
                <div class="border-t p-4 sm:border-l lg:border-t-0">
                    <p class="text-xs font-medium text-muted-foreground">
                        Role unassigned
                    </p>
                    <p class="mt-1 text-xl font-semibold text-foreground">
                        {{ summary.unassigned_role_count }}
                    </p>
                </div>
            </section>

            <section
                class="flex flex-wrap gap-2"
                data-testid="user-role-distribution"
            >
                <Badge
                    v-for="option in roles"
                    :key="option.value"
                    variant="secondary"
                    :data-role-code="option.value"
                    :data-user-count="option.user_count"
                >
                    {{ option.label }}: {{ option.user_count }}
                </Badge>
            </section>

            <form
                class="flex flex-col gap-3 rounded-lg border border-sidebar-border/70 bg-background p-4 md:flex-row md:items-end dark:border-sidebar-border"
                @submit.prevent="applyFilters"
            >
                <div class="grid flex-1 gap-2">
                    <label
                        for="user_q"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Search
                    </label>
                    <Input
                        id="user_q"
                        v-model="search"
                        name="q"
                        placeholder="Name, email, role, or linked owner"
                    />
                </div>
                <div class="grid gap-2 md:w-56">
                    <label
                        for="user_role"
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Role
                    </label>
                    <select
                        id="user_role"
                        v-model="role"
                        name="role"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="">All roles</option>
                        <option value="unassigned">Unassigned</option>
                        <option
                            v-for="option in roles"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <Button type="submit">
                        <Search />
                        Search
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        @click="clearFilters"
                    >
                        <X />
                        Clear
                    </Button>
                </div>
            </form>

            <section
                class="min-w-0 overflow-hidden border border-sidebar-border/70 bg-background dark:border-sidebar-border"
                data-testid="user-directory"
            >
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px] text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="px-4 py-3 font-medium">Account</th>
                                <th class="px-4 py-3 font-medium">Role</th>
                                <th class="px-4 py-3 font-medium">
                                    Verification
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    Legal owner link
                                </th>
                                <th class="px-4 py-3 font-medium">Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="user in users.data"
                                :key="user.id"
                                class="border-b last:border-b-0"
                                data-testid="user-directory-row"
                                :data-user-id="user.id"
                                :data-role-code="
                                    user.role?.code ?? 'unassigned'
                                "
                                :data-email-verified="
                                    user.email_verified_at !== null
                                "
                                :data-owner-linked="
                                    user.business_owner !== null
                                "
                            >
                                <td class="px-4 py-3">
                                    <p class="font-medium text-foreground">
                                        {{ user.name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ user.email }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge v-if="user.role" variant="outline">
                                        {{ user.role.name }}
                                    </Badge>
                                    <span v-else class="text-muted-foreground">
                                        Unassigned
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        v-if="user.email_verified_at"
                                        class="inline-flex items-center gap-2 text-emerald-700 dark:text-emerald-400"
                                    >
                                        <UserRoundCheck class="size-4" />
                                        Verified
                                    </span>
                                    <span v-else class="text-muted-foreground">
                                        Pending
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <template v-if="user.business_owner">
                                        <p class="font-medium text-foreground">
                                            {{ user.business_owner.name }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Owner #{{ user.business_owner.id }}
                                        </p>
                                    </template>
                                    <span v-else class="text-muted-foreground">
                                        Not linked
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{
                                        user.created_at
                                            ? new Date(
                                                  user.created_at,
                                              ).toLocaleDateString('en-PH')
                                            : 'Unknown'
                                    }}
                                </td>
                            </tr>
                            <tr v-if="users.data.length === 0">
                                <td
                                    colspan="5"
                                    class="px-4 py-10 text-center text-muted-foreground"
                                >
                                    No users match the current filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <nav
                v-if="users.links.length > 3"
                class="flex flex-wrap items-center justify-between gap-3"
                aria-label="User directory pagination"
            >
                <p class="text-sm text-muted-foreground">
                    Showing {{ users.from ?? 0 }} to {{ users.to ?? 0 }} of
                    {{ users.total }}
                </p>
                <div class="flex flex-wrap gap-1">
                    <Button
                        v-for="link in users.links"
                        :key="link.label"
                        as-child
                        size="sm"
                        :variant="link.active ? 'default' : 'outline'"
                        :disabled="link.url === null"
                    >
                        <Link
                            :href="link.url ?? '#'"
                            preserve-state
                            :aria-disabled="link.url === null"
                        >
                            {{ paginationLabel(link.label) }}
                        </Link>
                    </Button>
                </div>
            </nav>
        </main>
    </div>
</template>
