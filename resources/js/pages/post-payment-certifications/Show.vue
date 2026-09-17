<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { index as workIndex } from '@/actions/App/Http/Controllers/Staff/MunicipalWorkInboxController';
import { store } from '@/actions/App/Http/Controllers/Staff/PostPaymentCertificationController';
import AppLayout from '@/layouts/AppLayout.vue';

defineProps<{
    certification: {
        id: number;
        application_id: number;
        tracking_reference: string | null;
        office: string;
        status: string;
        result: string | null;
        remarks: string | null;
        certified_at: string | null;
        receipt_number: string;
        series: string | null;
        amount_cents: number;
        collection_id: number;
    };
    applicationUrl: string;
    submitUrl: string;
}>();
</script>

<template>
    <Head title="Certify payment and receipt" />
    <AppLayout>
        <main class="mx-auto w-full max-w-2xl space-y-5 p-4">
            <h1 class="text-xl font-semibold">Certify payment and receipt</h1>
            <p>UAT evidence only · No production office authority</p>
            <Link :href="workIndex()" class="inline-block underline"
                >Back to My Work</Link
            >
            <Link :href="applicationUrl" class="block break-all underline">
                Application {{ certification.application_id }} ·
                {{ certification.tracking_reference }}
            </Link>
            <dl
                class="grid grid-cols-1 gap-2 rounded border p-4 sm:grid-cols-2"
            >
                <dt>Routed office</dt>
                <dd>{{ certification.office }}</dd>
                <dt>Official Receipt</dt>
                <dd>
                    {{ certification.receipt_number
                    }}{{
                        certification.series ? ` / ${certification.series}` : ''
                    }}
                </dd>
                <dt>Bound amount</dt>
                <dd>
                    {{
                        (certification.amount_cents / 100).toLocaleString(
                            'en-PH',
                            { style: 'currency', currency: 'PHP' },
                        )
                    }}
                </dd>
                <dt>Collection</dt>
                <dd>{{ certification.collection_id }}</dd>
            </dl>
            <Form
                v-if="certification.status === 'pending'"
                v-bind="store.form(certification.id)"
                v-slot="{ errors, processing }"
                class="space-y-4"
            >
                <label class="block"
                    >Result
                    <select
                        name="result"
                        class="mt-1 block w-full rounded border p-2"
                        required
                    >
                        <option value="certified">Certified</option>
                        <option value="returned">Returned</option>
                    </select>
                </label>
                <label class="block"
                    >Review remarks
                    <textarea
                        name="remarks"
                        maxlength="2000"
                        class="mt-1 block w-full rounded border p-2"
                    />
                </label>
                <p v-for="(error, key) in errors" :key="key" role="alert">
                    {{ error }}
                </p>
                <button
                    type="submit"
                    :disabled="processing"
                    class="rounded bg-stone-900 px-4 py-2 text-white disabled:opacity-50"
                >
                    Confirm certification
                </button>
            </Form>
            <section v-else role="status" class="space-y-2 rounded border p-4">
                <p>Certification recorded: {{ certification.result }}</p>
                <p>{{ certification.certified_at }}</p>
                <p class="break-words">{{ certification.remarks }}</p>
            </section>
        </main>
    </AppLayout>
</template>
