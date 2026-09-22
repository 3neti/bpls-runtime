import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { chromium } from 'playwright';
import { createServer } from 'vite';

const artifactDir = resolve(
    'tests/Frontend/artifacts/payment-confirmation-resilience',
);
mkdirSync(artifactDir, { recursive: true });

const now = () => new Date().toISOString();
const future = () => new Date(Date.now() + 30 * 60 * 1000).toISOString();

const statusQueues = {
    citizen: [
        {
            paid: false,
            status: 'awaiting_payment',
            last_checked_at: '2026-09-22T01:59:56Z',
        },
        {
            paid: false,
            status: 'error',
            reconciliation_state: 'error',
            last_checked_at: '2026-09-22T02:00:00Z',
        },
        {
            paid: false,
            status: 'awaiting_payment',
            last_checked_at: '2026-09-22T02:00:00Z',
        },
        {
            paid: true,
            status: 'paid',
            collection_id: 7001,
            receipt_id: null,
            last_checked_at: '2026-09-22T02:00:04Z',
        },
    ],
    citizenApplication: [
        {
            paid: false,
            status: 'awaiting_payment',
            last_checked_at: '2026-09-22T02:04:56Z',
        },
        {
            paid: false,
            status: 'expired',
            last_checked_at: '2026-09-22T02:05:00Z',
        },
        {
            paid: false,
            status: 'needs_review',
            reconciliation_state: 'needs_review',
            last_checked_at: '2026-09-22T02:05:04Z',
        },
    ],
    staff: [
        {
            paid: false,
            status: 'awaiting_payment',
            last_checked_at: '2026-09-22T02:09:56Z',
        },
        {
            paid: false,
            status: 'awaiting_payment',
            last_checked_at: '2026-09-22T02:10:00Z',
        },
        {
            paid: true,
            status: 'paid',
            collection_id: 7002,
            receipt_id: null,
            last_checked_at: '2026-09-22T02:10:04Z',
        },
    ],
    application: [
        {
            paid: false,
            status: 'expired',
            last_checked_at: '2026-09-22T02:20:00Z',
        },
        {
            paid: false,
            status: 'error',
            reconciliation_state: 'error',
            last_checked_at: '2026-09-22T02:20:02Z',
        },
        {
            paid: false,
            status: 'needs_review',
            reconciliation_state: 'needs_review',
            last_checked_at: '2026-09-22T02:20:04Z',
        },
    ],
};

const calls = {
    citizen: 0,
    citizenApplication: 0,
    staff: 0,
    application: 0,
};

let citizenOutageHeld = false;
let citizenCallsSinceHold = 0;
let citizenRecoveryCalls = 0;
let staffSettled = false;

function nextStatus(key) {
    calls[key]++;

    if (key === 'citizen') {
        if (citizenOutageHeld) {
            citizenCallsSinceHold++;

            return citizenCallsSinceHold === 1
                ? statusQueues.citizen[0]
                : statusQueues.citizen[1];
        }

        if (citizenRecoveryCalls === 0) {
            citizenRecoveryCalls++;

            return statusQueues.citizen[2];
        }

        citizenRecoveryCalls++;

        return statusQueues.citizen[3];
    }

    if (
        key === 'staff' &&
        statusQueues.staff[(calls[key] - 1) % statusQueues.staff.length].paid
    ) {
        staffSettled = true;
    }

    const queue = statusQueues[key];

    return queue[(calls[key] - 1) % queue.length];
}

function json(res, payload, status = 200) {
    res.statusCode = status;
    res.setHeader('Content-Type', 'application/json');
    res.end(JSON.stringify(payload));
}

const qrDataUrl =
    'data:image/svg+xml;utf8,' +
    encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128"><rect width="128" height="128" fill="white"/><path d="M12 12h40v40H12zM76 12h40v40H76zM12 76h40v40H12z" fill="black"/></svg>',
    );

function paymentSchedule(overrides = {}) {
    return {
        id: 66,
        sequence: 1,
        status: 'pending',
        payment_mode: 'full',
        total_amount_cents: 397500,
        paid_amount_cents: 0,
        balance_amount_cents: 397500,
        due_on: null,
        prepared_by: 'Synthetic Assessment Officer',
        created_at: now(),
        assessment: {
            id: 218,
            sequence: 1,
            status: 'approved',
            total_amount_cents: 397500,
        },
        permit_application: {
            id: 295,
            display_reference: 'SUB-SYNTHETIC-QR',
            application_number: null,
            type: 'new',
            status: 'pending_payment',
            application_year: 2026,
            business_name: 'Payment Resilience Browser Fixture',
            trade_name: null,
            owner_name: 'Synthetic Owner',
        },
        lines: [],
        collections: [],
        payment_policy_boundary: {
            status: 'resolved',
            can_calculate_surcharge: false,
            can_calculate_interest: false,
            can_validate_pil: false,
            can_calculate_deficiency_tax: false,
            can_split_installments: false,
            can_assign_statutory_due_dates: false,
            supported_payment_modes: ['qr_ph'],
            blocked_calculations: [],
            artifact_statement: 'Synthetic schedule fixture.',
            software_knows: {},
            unresolved_policy: [],
        },
        online_payment_boundary: {
            status: 'available',
            can_pay_online: true,
            can_reconcile_online: true,
            payment_status: 'awaiting_payment',
            attempt_status: 'awaiting_payment',
            attempt_expires_at: future(),
            blocked_transitions: [],
            artifact_statement: 'Synthetic online payment fixture.',
            pay_code: 'QRPH-RESILIENCE',
            payment_reference: 'synthetic-payment-reference',
            attempt_reference: 'synthetic-active-attempt',
        },
        current_qr_ph_attempt: {
            amount_cents: 397500,
            status: 'awaiting_payment',
            expires_at: future(),
            qr_data_url: qrDataUrl,
        },
        artifact_statement:
            'Payment Schedule is payable through synthetic QR Ph.',
        ...overrides,
    };
}

function permitApplicationSummary() {
    return {
        released_permit: null,
        id: 295,
        display_reference: 'SUB-SYNTHETIC-QR',
        application_number: null,
        type: 'new',
        status: 'pending_payment',
        application_year: 2026,
        business_permit_evaluation_url: null,
        business_name: 'Payment Resilience Browser Fixture',
        activity_count: 1,
        saved_at: now(),
        owner: {
            name: 'Synthetic Owner',
            email: 'synthetic@example.test',
            phone: null,
            address: 'Synthetic Address',
        },
        business: {
            name: 'Payment Resilience Browser Fixture',
            trade_name: null,
            registration_number: 'SYNTH-295',
            address: 'Synthetic Business Address',
            barangay: 'Poblacion',
        },
        lines: [],
        documents: [],
        documentary_readiness: {
            received_document_count: 0,
            requirement_catalog_status: 'not_required',
            submission_readiness: 'submitted',
            statement: 'Synthetic document evidence already lodged.',
        },
        draft_boundary: {
            is_draft: false,
            assessment_started: true,
            official_application_number_assigned: false,
            statement: 'Application is lodged.',
        },
        submission_boundary: {
            statement: 'Synthetic submitted application.',
        },
        processing: {
            has_entered_municipal_processing: true,
            current_stage: 'payment',
            application_status: 'pending_payment',
            statement: 'Awaiting QR Ph payment confirmation.',
            clearance_summary: {
                completed: 4,
                total: 4,
            },
            assessment: {
                id: 218,
                sequence: 1,
                status: 'approved',
                total_amount_cents: 397500,
                treasurer_decision: {
                    action: 'approved',
                },
            },
            payment_schedule: {
                id: 67,
                sequence: 1,
                status: 'pending',
                payment_mode: 'full',
                total_amount_cents: 397500,
                paid_amount_cents: 0,
                balance_amount_cents: 397500,
                online_payment_boundary: {
                    pay_code: 'QRPH-APPLICATION',
                },
            },
            collection: null,
            authority_review: null,
        },
        permit_artifact: {
            available: false,
            can_issue: false,
            can_release: false,
            label: 'Permit not issued',
            policy_note: 'Synthetic payment still pending.',
            status: 'not_ready',
            verification_reference: null,
            verification_status: 'not_ready',
        },
        timeline: [],
        can_edit: false,
        can_submit: false,
        can_upload_documents: false,
        can_view_documents: true,
        can_view_financials: true,
    };
}

function classicHandoff(settled = false) {
    return {
        payment_id: 9001,
        pay_code: 'QRPH-RESILIENCE',
        external_reference: 'synthetic-payment-reference',
        amount_cents: 397500,
        currency: 'PHP',
        status: settled ? 'collected' : 'awaiting_payment',
        is_current: !settled,
        is_settled: settled,
        resolution: settled ? 'settled' : 'active',
        server_now: now(),
        history: [
            {
                id: 10,
                reference: 'synthetic-expired-attempt',
                status: 'awaiting_payment',
                expired: true,
            },
            {
                id: 11,
                reference: 'synthetic-active-attempt',
                status: 'awaiting_payment',
                expired: false,
            },
        ],
        attempt: {
            id: 11,
            reference: 'synthetic-active-attempt',
            provider: 'synthetic',
            amount_cents: 397500,
            status: 'awaiting_payment',
            expires_at: future(),
            qr_data_url: qrDataUrl,
        },
    };
}

function application() {
    return {
        id: 295,
        official_receipts: [],
        payment: {
            payable: {
                amount_cents: 397500,
                currency: 'PHP',
            },
            payment_request: {
                attempt_resolution: 'active',
                state: 'awaiting_payment',
                pay_code: 'QRPH-RESILIENCE',
                external_reference: 'synthetic-payment-reference',
                currency: 'PHP',
                target_amount_cents: 397500,
                collected_total_cents: 0,
                consumer_status: 'awaiting_payment',
                provider_status: 'awaiting_payment',
                confirmed_at: null,
                collection_id: null,
                collection_reference: null,
                active_attempt: {
                    reference: 'synthetic-active-attempt',
                    status: 'awaiting_payment',
                    provider: 'synthetic',
                    amount_cents: 397500,
                    expires_at: future(),
                    qr_data_url: qrDataUrl,
                },
            },
            reconciliation: {
                integration: 'x-change',
                provider: 'synthetic',
                payment_rail: 'qr_ph',
                channel: 'online',
                approved_amount_cents: 397500,
                paid_amount_cents: 0,
                collected_amount_cents: 0,
                remaining_balance_cents: 397500,
                confirmed_at: null,
                collection_reference: null,
                required_receipt_group_count: 0,
                issued_receipt_group_count: 0,
                receipt_groups: [],
                receipt_count: 0,
                total_receipted_cents: 0,
                unreceipted_amount_cents: 0,
                receipt_coverage_complete: false,
                totals_reconciled: false,
                status: 'awaiting_payment',
                synthetic: true,
            },
        },
    };
}

const server = await createServer({
    configFile: false,
    root: process.cwd(),
    cacheDir: 'storage/framework/testing/payment-resilience-vite',
    plugins: [
        vue(),
        tailwindcss(),
        {
            name: 'payment-confirmation-resilience-fixture',
            resolveId(id) {
                if (id === 'synthetic-layout') {
                    return '\0synthetic-layout';
                }

                if (id === 'synthetic-application-payment') {
                    return '\0synthetic-application-payment';
                }

                if (id === 'synthetic-executable-document') {
                    return '\0synthetic-executable-document';
                }
            },
            load(id) {
                if (id === '\0synthetic-layout') {
                    return "import {h} from 'vue'; export default {setup(_, {slots}) {return () => h('div', {class: 'min-h-screen bg-background p-4'}, slots.default?.());}}";
                }

                if (id === '\0synthetic-executable-document') {
                    return "import {h} from 'vue'; export default {props: ['document'], setup() {return () => h('section', {'data-testid': 'synthetic-executable-document', class: 'rounded border p-3 text-sm'}, 'Synthetic executable document omitted from payment-status browser fixture.');}}";
                }

                if (id === '\0synthetic-application-payment') {
                    return `
                        import {createApp, h, ref} from 'vue';
                        import Sheet from '/resources/js/components/permit-applications/IpilPaymentContinuationSheet.vue';
                        const application = ${JSON.stringify(application())};
                        createApp({
                            setup() {
                                const checking = ref(false);
                                const message = ref(null);
                                const lastChecked = ref('Not checked on this screen');
                                async function check() {
                                    if (checking.value) return;
                                    checking.value = true;
                                    try {
                                        const response = await fetch('/application/payment-status', {headers: {Accept: 'application/json'}});
                                        const result = await response.json();
                                        lastChecked.value = result.last_checked_at ?? new Date().toLocaleTimeString('en-PH');
                                        if (!response.ok || result.status === 'temporarily_unavailable' || result.status === 'error' || result.reconciliation_state === 'error') {
                                            message.value = 'Payment checking is temporarily unavailable. Please check again; do not pay again solely because confirmation is delayed.';
                                        } else if (result.reconciliation_state === 'needs_review' || result.status === 'needs_review') {
                                            message.value = 'Needs review. Ask Treasury to check the payment evidence; do not pay again.';
                                        } else if (result.status === 'expired') {
                                            message.value = 'QR expired; payment is not yet confirmed. Check before paying again.';
                                        } else {
                                            message.value = 'Awaiting payment confirmation.';
                                        }
                                    } finally {
                                        checking.value = false;
                                    }
                                }
                                return () => h('main', {class: 'p-4'}, [
                                    h(Sheet, {
                                        application,
                                        checking: checking.value,
                                        checkMessage: message.value,
                                        lastChecked: lastChecked.value,
                                        statusUrl: '/application/payment-status',
                                        onCheck: check,
                                    }),
                                ]);
                            },
                        }).mount('#app');
                    `;
                }
            },
            configureServer(server) {
                server.middlewares.use((req, res, next) => {
                    const requestUrl = new URL(
                        req.url ?? '/',
                        'http://localhost',
                    );

                    if (requestUrl.pathname === '/test-state') {
                        return json(res, { calls });
                    }

                    if (requestUrl.pathname === '/test-hold-citizen-outage') {
                        citizenOutageHeld = true;
                        citizenCallsSinceHold = 0;
                        citizenRecoveryCalls = 0;

                        return json(res, { held: true });
                    }

                    if (
                        requestUrl.pathname === '/test-release-citizen-outage'
                    ) {
                        citizenOutageHeld = false;
                        citizenRecoveryCalls = 0;

                        return json(res, { held: false });
                    }

                    if (
                        requestUrl.pathname ===
                        '/citizen/payment-schedules/66/qr-ph/status'
                    ) {
                        const payload = nextStatus('citizen');

                        return json(res, payload, payload.httpStatus ?? 200);
                    }

                    if (
                        requestUrl.pathname ===
                        '/citizen/payment-schedules/67/qr-ph/status'
                    ) {
                        const payload = nextStatus('citizenApplication');

                        return json(res, payload, payload.httpStatus ?? 200);
                    }

                    if (
                        requestUrl.pathname ===
                        '/staff/payment-schedules/66/qr-ph/status'
                    ) {
                        const payload = nextStatus('staff');

                        return json(res, payload, payload.httpStatus ?? 200);
                    }

                    if (requestUrl.pathname === '/application/payment-status') {
                        const payload = nextStatus('application');

                        return json(res, payload, payload.httpStatus ?? 200);
                    }

                    if (requestUrl.pathname === '/application') {
                        const html = `<meta name="viewport" content="width=device-width,initial-scale=1"><div id="app"></div><script type="module">import '/resources/css/app.css'; import 'synthetic-application-payment';</script>`;

                        return server
                            .transformIndexHtml('/application', html)
                            .then((body) => {
                                res.setHeader('Content-Type', 'text/html');
                                res.end(body);
                            });
                    }

                    const pageByPath = {
                        '/citizen': {
                            component: 'CitizenPayment',
                            module: '/resources/js/pages/citizen/payment-schedules/Show.vue',
                            props: { paymentSchedule: paymentSchedule() },
                        },
                        '/citizen-application': {
                            component: 'CitizenApplication',
                            module: '/resources/js/pages/citizen/permit-applications/Show.vue',
                            props: {
                                permitApplication: permitApplicationSummary(),
                                applicationDocumentTypes: [],
                                executableDocument: null,
                            },
                        },
                        '/staff': {
                            component: 'StaffPayment',
                            module: '/resources/js/pages/payment-schedules/Show.vue',
                            props: {
                                paymentSchedule: paymentSchedule({
                                    status: staffSettled ? 'paid' : 'pending',
                                    paid_amount_cents: staffSettled
                                        ? 397500
                                        : 0,
                                    permit_application: {
                                        id: 295,
                                        application_number: null,
                                        type: 'new',
                                        status: 'pending_payment',
                                        application_year: 2026,
                                        business_name:
                                            'Payment Resilience Browser Fixture',
                                        owner_name: 'Synthetic Owner',
                                    },
                                }),
                                collectionMethods: [],
                                receiptReconciliation: null,
                                classicPaymentSimulationUrl:
                                    '/synthetic-simulation-disabled',
                                classicPaymentHandoff:
                                    classicHandoff(staffSettled),
                                can: {
                                    view_permit_application: true,
                                    view_collections: true,
                                    record_collections: true,
                                    issue_receipts: true,
                                    view_receipts: true,
                                    check_qr_ph: true,
                                    initiate_qr_ph: false,
                                    simulate_classic_payment: false,
                                },
                            },
                        },
                        '/staff-denied': {
                            component: 'StaffPaymentDenied',
                            module: '/resources/js/pages/payment-schedules/Show.vue',
                            props: {
                                paymentSchedule: paymentSchedule({
                                    permit_application: {
                                        id: 295,
                                        application_number: null,
                                        type: 'new',
                                        status: 'pending_payment',
                                        application_year: 2026,
                                        business_name:
                                            'Payment Resilience Browser Fixture',
                                        owner_name: 'Synthetic Owner',
                                    },
                                }),
                                collectionMethods: [],
                                receiptReconciliation: null,
                                classicPaymentSimulationUrl:
                                    '/synthetic-simulation-disabled',
                                classicPaymentHandoff: classicHandoff(),
                                can: {
                                    view_permit_application: true,
                                    view_collections: true,
                                    record_collections: true,
                                    issue_receipts: true,
                                    view_receipts: true,
                                    check_qr_ph: false,
                                    initiate_qr_ph: false,
                                    simulate_classic_payment: false,
                                },
                            },
                        },
                    };
                    const fixture = pageByPath[requestUrl.pathname];

                    if (!fixture) {
                        return next();
                    }

                    const page = {
                        component: fixture.component,
                        props: { errors: {}, ...fixture.props },
                        url: requestUrl.pathname,
                        version: null,
                    };

                    if (req.headers['x-inertia']) {
                        res.setHeader('X-Inertia', 'true');

                        return json(res, page);
                    }

                    const html = `<meta name="viewport" content="width=device-width,initial-scale=1"><div id="app"></div><script type="module">
                        import '/resources/css/app.css';
                        import {createApp,h} from 'vue';
                        import {createInertiaApp} from '@inertiajs/vue3';
                        import Page from '${fixture.module}';
                        createInertiaApp({page:${JSON.stringify(page)},resolve:()=>Page,setup:({el,App,props,plugin})=>createApp({render:()=>h(App,props)}).use(plugin).mount(el)});
                    </script>`;

                    server
                        .transformIndexHtml(requestUrl.pathname, html)
                        .then((body) => {
                            res.setHeader('Content-Type', 'text/html');
                            res.end(body);
                        });
                });
            },
        },
    ],
    resolve: {
        alias: [
            {
                find: '@/layouts/AppLayout.vue',
                replacement: 'synthetic-layout',
            },
            {
                find: '@/components/permit-applications/IpilExecutableDocument.vue',
                replacement: 'synthetic-executable-document',
            },
            { find: '@', replacement: resolve('resources/js') },
        ],
    },
    server: { host: '127.0.0.1', port: 0 },
});

await server.listen();
const baseUrl = `http://127.0.0.1:${server.httpServer.address().port}`;

async function assertNoHorizontalOverflow(page) {
    assert.equal(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
        true,
    );
}

async function waitForStatusJson(page, path, action) {
    const responsePromise = page.waitForResponse(
        (response) => new URL(response.url()).pathname === path,
    );
    await action();
    const response = await responsePromise;
    const body = await response.json();

    return { response, body };
}

async function run() {
    const browser = await chromium.launch({ headless: true });

    try {
        for (const viewport of [
            { name: 'desktop', width: 1280, height: 900 },
            { name: 'mobile-390x844', width: 390, height: 844 },
        ]) {
            const page = await browser.newPage({
                viewport: { width: viewport.width, height: viewport.height },
            });
            const pageErrors = [];
            page.on('pageerror', (error) => pageErrors.push(error.message));

            staffSettled = false;
            await fetch(`${baseUrl}/test-hold-citizen-outage`);
            await page.goto(`${baseUrl}/citizen`);
            await page.getByTestId('citizen-check-payment').waitFor();
            await page.getByText(/Awaiting payment confirmation/i).waitFor();
            assert.equal(await page.getByTestId('qr-ph-generate').count(), 0);
            await waitForStatusJson(
                page,
                '/citizen/payment-schedules/66/qr-ph/status',
                () => page.getByTestId('citizen-check-payment').click(),
            );
            await page.getByText(/temporarily unavailable/i).waitFor();
            await page.getByText(/do not pay again/i).waitFor();
            await page.getByText(/Last check:/i).waitFor();
            await page.screenshot({
                path: `${artifactDir}/citizen-${viewport.name}-offline.png`,
                fullPage: true,
            });
            await fetch(`${baseUrl}/test-release-citizen-outage`);
            await page.getByTestId('citizen-check-payment').click();
            await page.getByText(/Awaiting payment confirmation/i).waitFor();
            const citizenPaid = await waitForStatusJson(
                page,
                '/citizen/payment-schedules/66/qr-ph/status',
                () => page.getByTestId('citizen-check-payment').click(),
            );
            assert.equal(citizenPaid.body.paid, true);
            await assertNoHorizontalOverflow(page);

            await page.goto(`${baseUrl}/citizen-application`);
            await page
                .getByTestId('citizen-application-check-payment')
                .waitFor();
            await page.getByText(/Awaiting payment confirmation/i).waitFor();
            await page.getByTestId('citizen-application-check-payment').click();
            await page
                .getByText(/QR expired; payment is not yet confirmed/i)
                .waitFor();
            await page.getByTestId('citizen-application-check-payment').click();
            await page.getByText(/Needs review/i).waitFor();
            await page.screenshot({
                path: `${artifactDir}/citizen-application-${viewport.name}-needs-review.png`,
                fullPage: true,
            });
            await assertNoHorizontalOverflow(page);

            await page.goto(`${baseUrl}/staff`);
            await page.getByTestId('staff-check-payment').waitFor();
            await page
                .getByTestId('staff-qr-ph-message')
                .getByText(/Awaiting payment confirmation/i)
                .waitFor();
            assert.equal(
                await page.getByTestId('staff-qr-ph-generate').count(),
                0,
            );
            assert.equal(
                await page.getByTestId('classic-payment-simulate').count(),
                0,
            );
            await page.getByText(/Last check: /i).waitFor();
            const staffAwaiting = await waitForStatusJson(
                page,
                '/staff/payment-schedules/66/qr-ph/status',
                () => page.getByTestId('staff-check-payment').click(),
            );
            assert.equal(staffAwaiting.body.paid, false);
            const staffPaid = await waitForStatusJson(
                page,
                '/staff/payment-schedules/66/qr-ph/status',
                () => page.getByTestId('staff-check-payment').click(),
            );
            assert.equal(staffPaid.body.paid, true);
            await page
                .getByRole('heading', { name: 'Payment complete' })
                .waitFor();
            await page.screenshot({
                path: `${artifactDir}/staff-${viewport.name}-paid.png`,
                fullPage: true,
            });
            await assertNoHorizontalOverflow(page);

            const staffCallsBeforeDenied = calls.staff;
            await page.goto(`${baseUrl}/staff-denied`);
            await page.getByTestId('staff-qr-ph-payment').waitFor();
            await page.getByText('QRPH-RESILIENCE').waitFor();
            assert.equal(
                await page.getByTestId('staff-check-payment').count(),
                0,
            );
            assert.equal(
                await page.getByTestId('classic-payment-simulate').count(),
                0,
            );
            assert.equal(
                await page.getByTestId('staff-qr-ph-generate').count(),
                0,
            );
            await page.waitForTimeout(500);
            assert.equal(calls.staff, staffCallsBeforeDenied);
            await page.screenshot({
                path: `${artifactDir}/staff-denied-${viewport.name}.png`,
                fullPage: true,
            });
            await assertNoHorizontalOverflow(page);

            await page.goto(`${baseUrl}/application`);
            await page.getByTestId('application-check-payment').waitFor();
            await page.getByTestId('application-check-payment').click();
            await page
                .getByText(/QR expired; payment is not yet confirmed/i)
                .waitFor();
            await page.getByTestId('application-check-payment').click();
            await page.getByText(/temporarily unavailable/i).waitFor();
            await page.getByText(/do not pay again/i).waitFor();
            await page.getByTestId('application-check-payment').click();
            await page.getByText(/Needs review/i).waitFor();
            await page.screenshot({
                path: `${artifactDir}/application-${viewport.name}-needs-review.png`,
                fullPage: true,
            });
            await assertNoHorizontalOverflow(page);

            assert.deepEqual(pageErrors, []);
            await page.close();
        }

        const state = await (await fetch(`${baseUrl}/test-state`)).json();
        assert.equal(state.calls.citizen, 8);
        assert.equal(state.calls.citizenApplication, 6);
        assert.equal(state.calls.staff, 6);
        assert.equal(state.calls.application, 6);

        console.log(
            `PASS: payment confirmation resilience browser fixture exercised Citizen, staff, and application payment surfaces at desktop and 390x844; screenshots in ${artifactDir}`,
        );
    } finally {
        await browser.close();
        await server.close();
    }
}

await run();
