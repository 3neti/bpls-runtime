<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { index as workIndex } from '@/actions/App/Http/Controllers/Staff/MunicipalWorkInboxController';
import { store } from '@/actions/App/Http/Controllers/Staff/OrdinaryUatPermitController';
import AppLayout from '@/layouts/AppLayout.vue';

defineProps<{
    application: { id: number; tracking_reference: string | null };
    readiness: {
        prerequisites: Record<string, boolean>;
        receipt_total_cents: number;
        receipt_numbers: string[];
    };
    completion: null | {
        authorized_at: string | null;
        authorization_fingerprint: string | null;
        issued_at: string | null;
        released_at: string | null;
        permit_number: string | null;
    };
    action: 'authorize' | 'issue' | 'release' | null;
    applicationUrl: string;
    verificationUrl: string | null;
}>();
const labels = {
    authorize: 'Authorize Business Permit issuance',
    issue: 'Issue UAT Business Permit',
    release: 'Release UAT Business Permit',
};
</script>

<template>
    <Head title="Mayoral Authorization and Permit" />
    <AppLayout>
        <Link :href="workIndex()" class="m-4 inline-block underline"
            >Back to My Work</Link
        >
        <main class="mx-auto w-full max-w-2xl min-w-0 space-y-5 p-4">
            <h1 class="text-xl font-semibold">
                Mayoral Authorization and Permit
            </h1>
            <p class="rounded border p-3">
                Synthetic / UAT-only · Production authority: false. No statutory
                electronic or digital signature. No production Permit authority
                or legal effect.
            </p>
            <Link :href="applicationUrl" class="block break-all underline"
                >Application {{ application.id }} ·
                {{ application.tracking_reference }}</Link
            >
            <p>
                Reconciled Official Receipts:
                {{
                    (readiness.receipt_total_cents / 100).toLocaleString(
                        'en-PH',
                        { style: 'currency', currency: 'PHP' },
                    )
                }}
            </p>
            <p class="break-words">
                ORs: {{ readiness.receipt_numbers.join(', ') }}
            </p>
            <ul class="space-y-2">
                <li
                    v-for="(passed, key) in readiness.prerequisites"
                    :key="key"
                    class="break-words"
                >
                    {{ key.replaceAll('_', ' ') }}:
                    {{ passed ? 'Complete' : 'Pending' }}
                </li>
            </ul>
            <section v-if="completion" class="space-y-2 rounded border p-3">
                <p>Mayoral Authorization: {{ completion.authorized_at }}</p>
                <p class="break-all">
                    Frozen authorization:
                    {{ completion.authorization_fingerprint }}
                </p>
                <p>Permit: {{ completion.permit_number ?? 'Not issued' }}</p>
                <p>Issued: {{ completion.issued_at ?? 'Not issued' }}</p>
                <p>Released: {{ completion.released_at ?? 'Not released' }}</p>
            </section>
            <Form
                v-if="action"
                :key="action"
                v-bind="store.form(application.id)"
                v-slot="{ errors, processing }"
                class="space-y-3"
            >
                <input type="hidden" name="ceremony" :value="action" />
                <p v-for="(error, key) in errors" :key="key" role="alert">
                    {{ error }}
                </p>
                <button
                    type="submit"
                    :disabled="processing"
                    class="max-w-full rounded bg-stone-900 px-4 py-3 text-left whitespace-normal text-white disabled:opacity-50"
                >
                    {{ labels[action] }}
                </button>
            </Form>
            <Link
                v-if="verificationUrl"
                :href="verificationUrl"
                class="block underline"
                >Verify released UAT Permit identity</Link
            >
        </main>
    </AppLayout>
</template>
