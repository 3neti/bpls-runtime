<script setup lang="ts">
import { Head, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { Search, ShieldCheck, UserPlus, X } from '@lucide/vue';
import { ref } from 'vue';
import {
    provisionLaboratory,
    store,
    update,
} from '@/actions/App/Http/Controllers/Staff/UserAccessController';
import { index } from '@/actions/App/Http/Controllers/Staff/UserDirectoryController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { BreadcrumbItem } from '@/types';

type Role = { name: string; code: string };
type UserRow = {
    id: number;
    name: string;
    email: string;
    roles: Role[];
    access_status: 'active' | 'suspended';
    access_expires_at: string | null;
    access_audit_count: number;
    business_owner: { id: number; name: string } | null;
};
type PaginationLink = { url: string | null; label: string; active: boolean };
type RoleOption = { label: string; value: string; user_count: number };

const props = defineProps<{
    users: { data: UserRow[]; links: PaginationLink[]; total: number };
    filters: { q: string; role: string | null };
    roles: RoleOption[];
    assignable_roles: RoleOption[];
    summary: {
        user_count: number;
        verified_user_count: number;
        linked_owner_count: number;
        unassigned_role_count: number;
        suspended_user_count: number;
    };
    capabilities: {
        provision_users: boolean;
        manage_user_access: boolean;
        provision_laboratory_actors: boolean;
    };
}>();

const search = ref(props.filters.q ?? '');
const role = ref(props.filters.role ?? '');
const showCreate = ref(false);
const selectedUser = ref<UserRow | null>(null);
const createForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: [] as string[],
    access_expires_at: '',
    reason: '',
});
const accessForm = useForm({
    roles: [] as string[],
    access_status: 'active' as 'active' | 'suspended',
    access_expires_at: '',
    reason: '',
});
const laboratoryForm = useForm({
    reason: 'Provision the standard Lifecycle Laboratory actor set.',
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Users & Access', href: index() },
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
        { preserveState: true, replace: true },
    );
}
function clearFilters(): void {
    search.value = '';
    role.value = '';
    router.get(index.url(), {}, { preserveState: true, replace: true });
}
function submitCreate(): void {
    createForm.submit(store(), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            showCreate.value = false;
        },
    });
}
function manage(user: UserRow): void {
    selectedUser.value = user;
    accessForm.roles = user.roles.map((item) => item.code);
    accessForm.access_status = user.access_status;
    accessForm.access_expires_at = user.access_expires_at?.slice(0, 10) ?? '';
    accessForm.reason = '';
    accessForm.clearErrors();
}
function submitAccess(): void {
    if (selectedUser.value === null) {
        return;
    }

    accessForm.submit(update(selectedUser.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            selectedUser.value = null;
            accessForm.reset();
        },
    });
}
function paginationLabel(label: string): string {
    return label.replace('&laquo;', '‹').replace('&raquo;', '›');
}
</script>

<template>
    <div class="contents">
        <Head title="Users & Access" />
        <main class="flex h-full min-w-0 flex-1 flex-col gap-5 p-4">
            <section class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Users & Access</h1>
                    <p class="text-sm text-muted-foreground">
                        Provision municipal accounts and assign institutional
                        roles.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="capabilities.provision_laboratory_actors"
                        variant="outline"
                        :disabled="laboratoryForm.processing"
                        @click="
                            laboratoryForm.submit(provisionLaboratory(), {
                                preserveScroll: true,
                            })
                        "
                    >
                        <ShieldCheck /> Provision laboratory actors
                    </Button>
                    <Button
                        v-if="capabilities.provision_users"
                        @click="showCreate = !showCreate"
                        ><UserPlus /> New staff account</Button
                    >
                </div>
            </section>

            <section
                class="grid border sm:grid-cols-2 lg:grid-cols-5"
                data-testid="user-directory-summary"
                :data-user-count="summary.user_count"
                :data-unassigned-role-count="summary.unassigned_role_count"
            >
                <div class="p-4">
                    <p class="text-xs text-muted-foreground">Accounts</p>
                    <p class="text-xl font-semibold">
                        {{ summary.user_count }}
                    </p>
                </div>
                <div class="border-t p-4 sm:border-t-0 sm:border-l">
                    <p class="text-xs text-muted-foreground">Verified</p>
                    <p class="text-xl font-semibold">
                        {{ summary.verified_user_count }}
                    </p>
                </div>
                <div class="border-t p-4 lg:border-t-0 lg:border-l">
                    <p class="text-xs text-muted-foreground">Owner linked</p>
                    <p class="text-xl font-semibold">
                        {{ summary.linked_owner_count }}
                    </p>
                </div>
                <div class="border-t p-4 sm:border-l lg:border-t-0">
                    <p class="text-xs text-muted-foreground">Unassigned</p>
                    <p class="text-xl font-semibold">
                        {{ summary.unassigned_role_count }}
                    </p>
                </div>
                <div class="border-t p-4 lg:border-t-0 lg:border-l">
                    <p class="text-xs text-muted-foreground">Suspended</p>
                    <p class="text-xl font-semibold">
                        {{ summary.suspended_user_count }}
                    </p>
                </div>
            </section>

            <form
                v-if="showCreate"
                class="grid gap-4 rounded-lg border bg-background p-4 lg:grid-cols-2"
                data-testid="staff-account-form"
                @submit.prevent="submitCreate"
            >
                <h2 class="font-semibold lg:col-span-2">
                    Provision staff account
                </h2>
                <label class="grid gap-1 text-sm"
                    >Name<Input v-model="createForm.name" required /><span
                        class="text-xs text-destructive"
                        >{{ createForm.errors.name }}</span
                    ></label
                >
                <label class="grid gap-1 text-sm"
                    >Email<Input
                        v-model="createForm.email"
                        type="email"
                        required
                    /><span class="text-xs text-destructive">{{
                        createForm.errors.email
                    }}</span></label
                >
                <label class="grid gap-1 text-sm"
                    >Temporary password<Input
                        v-model="createForm.password"
                        type="password"
                        required
                /></label>
                <label class="grid gap-1 text-sm"
                    >Confirm password<Input
                        v-model="createForm.password_confirmation"
                        type="password"
                        required
                /></label>
                <fieldset class="grid gap-2 lg:col-span-2">
                    <legend class="text-sm font-medium">
                        Institutional roles
                    </legend>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <label
                            v-for="option in assignable_roles"
                            :key="option.value"
                            class="flex items-center gap-2 text-sm"
                            ><input
                                v-model="createForm.roles"
                                type="checkbox"
                                :value="option.value"
                            />{{ option.label }}</label
                        >
                    </div>
                    <span class="text-xs text-destructive">{{
                        createForm.errors.roles
                    }}</span>
                </fieldset>
                <label class="grid gap-1 text-sm"
                    >Access expires (optional)<Input
                        v-model="createForm.access_expires_at"
                        type="date"
                /></label>
                <label class="grid gap-1 text-sm"
                    >Reason<Input
                        v-model="createForm.reason"
                        required
                        placeholder="Appointment or access basis"
                    /><span class="text-xs text-destructive">{{
                        createForm.errors.reason
                    }}</span></label
                >
                <div class="flex gap-2 lg:col-span-2">
                    <Button type="submit" :disabled="createForm.processing"
                        >Create account</Button
                    ><Button
                        type="button"
                        variant="outline"
                        @click="showCreate = false"
                        >Cancel</Button
                    >
                </div>
            </form>

            <form
                v-if="selectedUser"
                class="grid gap-4 rounded-lg border bg-background p-4"
                data-testid="staff-access-form"
                @submit.prevent="submitAccess"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">
                            Manage {{ selectedUser.name }}
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            {{ selectedUser.email }}
                        </p>
                    </div>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        @click="selectedUser = null"
                        ><X
                    /></Button>
                </div>
                <fieldset class="grid gap-2">
                    <legend class="text-sm font-medium">Roles</legend>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        <label
                            v-for="option in assignable_roles"
                            :key="option.value"
                            class="flex items-center gap-2 text-sm"
                            ><input
                                v-model="accessForm.roles"
                                type="checkbox"
                                :value="option.value"
                            />{{ option.label }}</label
                        >
                    </div>
                </fieldset>
                <div class="grid gap-3 md:grid-cols-3">
                    <label class="grid gap-1 text-sm"
                        >Status<select
                            v-model="accessForm.access_status"
                            class="h-9 rounded-md border bg-transparent px-3"
                        >
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                        </select></label
                    >
                    <label class="grid gap-1 text-sm"
                        >Expires<Input
                            v-model="accessForm.access_expires_at"
                            type="date"
                    /></label>
                    <label class="grid gap-1 text-sm"
                        >Reason<Input v-model="accessForm.reason" required
                    /></label>
                </div>
                <span class="text-xs text-destructive">{{
                    accessForm.errors.roles || accessForm.errors.reason
                }}</span>
                <div>
                    <Button type="submit" :disabled="accessForm.processing"
                        >Save access</Button
                    >
                </div>
            </form>

            <form
                class="flex flex-col gap-3 rounded-lg border bg-background p-4 md:flex-row md:items-end"
                @submit.prevent="applyFilters"
            >
                <label class="grid flex-1 gap-1 text-sm"
                    >Search<Input
                        v-model="search"
                        placeholder="Name, email, role, or linked owner"
                /></label>
                <label class="grid gap-1 text-sm md:w-56"
                    >Role<select
                        v-model="role"
                        class="h-9 rounded-md border bg-transparent px-3"
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
                    </select></label
                >
                <Button type="submit"><Search /> Search</Button
                ><Button type="button" variant="outline" @click="clearFilters"
                    ><X /> Clear</Button
                >
            </form>

            <section
                class="min-w-0 overflow-hidden border bg-background"
                data-testid="user-directory"
            >
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[860px] text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="px-4 py-3">Account</th>
                                <th class="px-4 py-3">Roles</th>
                                <th class="px-4 py-3">Access</th>
                                <th class="px-4 py-3">Owner link</th>
                                <th class="px-4 py-3">Audit</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="user in users.data"
                                :key="user.id"
                                class="border-b last:border-0"
                                data-testid="user-directory-row"
                            >
                                <td class="px-4 py-3">
                                    <p class="font-medium">{{ user.name }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ user.email }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <Badge
                                            v-for="item in user.roles"
                                            :key="item.code"
                                            variant="secondary"
                                            >{{ item.name }}</Badge
                                        ><span v-if="user.roles.length === 0"
                                            >Unassigned</span
                                        >
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        :variant="
                                            user.access_status === 'active'
                                                ? 'outline'
                                                : 'destructive'
                                        "
                                        >{{ user.access_status }}</Badge
                                    >
                                    <p
                                        v-if="user.access_expires_at"
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        Until
                                        {{
                                            user.access_expires_at.slice(0, 10)
                                        }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    {{ user.business_owner?.name ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ user.access_audit_count }} changes
                                </td>
                                <td class="px-4 py-3">
                                    <Button
                                        v-if="capabilities.manage_user_access"
                                        size="sm"
                                        variant="outline"
                                        @click="manage(user)"
                                        >Manage</Button
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <nav class="flex flex-wrap gap-1" aria-label="Pagination">
                <Button
                    v-for="link in users.links"
                    :key="link.label"
                    size="sm"
                    :variant="link.active ? 'default' : 'outline'"
                    :disabled="link.url === null"
                    @click="link.url && router.visit(link.url)"
                    >{{ paginationLabel(link.label) }}</Button
                >
            </nav>
        </main>
    </div>
</template>
