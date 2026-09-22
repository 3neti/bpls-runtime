import assert from 'node:assert/strict';
import { resolve } from 'node:path';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { chromium } from 'playwright';
import { createServer } from 'vite';

// Synthetic-only form boundary fixture: this verifies that the UAT simulation
// form submits the exact active attempt. Real-provider-shaped handoffs are
// covered by PaymentConfirmationResilienceBrowserTest.mjs and do not expose the
// simulation control.
let submitted = [];
const props = () => ({
    errors: {},
    paymentSchedule: {
        id: 63,
        sequence: 1,
        status: 'pending',
        payment_mode: 'full',
        total_amount_cents: 417500,
        paid_amount_cents: 0,
        due_on: null,
        prepared_by: 'Synthetic officer',
        created_at: null,
        assessment: { id: 215, sequence: 1, status: 'computed' },
        permit_application: {
            id: 291,
            application_number: null,
            type: 'new',
            status: 'pending_payment',
            application_year: 2026,
            business_name: 'Synthetic Business',
            owner_name: 'Synthetic Owner',
        },
        lines: [],
        collections: [],
        payment_policy_boundary: {
            status: 'blocked',
            supported_payment_modes: [],
            blocked_calculations: [],
            software_knows: {},
            unresolved_policy: [],
            artifact_statement: 'Synthetic fixture',
        },
        online_payment_boundary: {
            status: 'available',
            can_pay_online: true,
            blocked_transitions: [],
            software_knows: {},
            unresolved_policy: [],
            artifact_statement: 'Synthetic fixture',
        },
    },
    collectionMethods: [],
    receiptReconciliation: null,
    can: {
        record_collections: true,
        view_collections: true,
        issue_receipts: true,
        view_receipts: true,
        initiate_qr_ph: false,
        simulate_classic_payment: true,
    },
    classicPaymentSimulationUrl: '/synthetic-confirm',
    classicPaymentHandoff: {
        payment_id: 5,
        pay_code: 'TEST',
        external_reference: 'synthetic-payable-5',
        amount_cents: 417500,
        currency: 'PHP',
        status: 'awaiting_payment',
        is_current: true,
        resolution: 'active',
        server_now: new Date().toISOString(),
        history: [
            {
                id: 7,
                reference: 'synthetic-expired-7',
                status: 'awaiting_payment',
                expired: true,
            },
            {
                id: 8,
                reference: 'synthetic-active-8',
                status: 'awaiting_payment',
                expired: false,
            },
        ],
        attempt: {
            id: 8,
            reference: 'synthetic-active-8',
            provider: 'synthetic',
            amount_cents: 417500,
            status: 'awaiting_payment',
            expires_at: new Date(Date.now() + 3600000).toISOString(),
            qr_data_url: null,
        },
    },
});
const server = await createServer({
    cacheDir: 'storage/framework/testing/qr-handoff-vite',
    configFile: false,
    root: process.cwd(),
    plugins: [
        vue(),
        tailwindcss(),
        {
            name: 'synthetic-handoff',
            resolveId(id) {
                if (id === 'synthetic-layout') {
                    return '\0synthetic-layout';
                }
            },
            load(id) {
                if (id === '\0synthetic-layout') {
                    return "import {h} from 'vue'; export default {setup(_, {slots}) {return () => h('div', slots.default?.());}}";
                }
            },
            configureServer(server) {
                server.middlewares.use((req, res, next) => {
                    if (
                        req.url === '/synthetic-confirm' &&
                        req.method === 'POST'
                    ) {
                        let body = '';
                        req.on('data', (chunk) => {
                            body += chunk;
                        });
                        req.on('end', () => {
                            submitted.push(body);
                            res.statusCode = 303;
                            res.setHeader('Location', '/');
                            res.end();
                        });

                        return;
                    }

                    if (req.url === '/test-state') {
                        res.setHeader('Content-Type', 'application/json');
                        res.end(
                            JSON.stringify({ submitted, synthetic_only: true }),
                        );

                        return;
                    }

                    const requestUrl = new URL(
                        req.url ?? '/',
                        'http://localhost',
                    );

                    if (requestUrl.pathname !== '/') {
                        return next();
                    }

                    const page = {
                        component: 'Handoff',
                        props: props(),
                        url: requestUrl.pathname + requestUrl.search,
                        version: null,
                    };

                    if (req.headers['x-inertia']) {
                        res.setHeader('X-Inertia', 'true');
                        res.setHeader('Content-Type', 'application/json');
                        res.end(JSON.stringify(page));

                        return;
                    }

                    const html = `<meta name="viewport" content="width=device-width,initial-scale=1"><div id="app"></div><script type="module">
                    import '/resources/css/app.css';
                    import {createApp,h} from 'vue'; import {createInertiaApp} from '@inertiajs/vue3';
                    import Handoff from '/resources/js/pages/payment-schedules/Show.vue';
                    createInertiaApp({page:${JSON.stringify(page)},resolve:()=>Handoff,setup:({el,App,props,plugin})=>createApp({render:()=>h(App,props)}).use(plugin).mount(el)});
                </script>`;
                    server.transformIndexHtml('/', html).then((body) => {
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
            { find: '@', replacement: resolve('resources/js') },
        ],
    },
    server: { host: '127.0.0.1', port: 0 },
});
await server.listen();
const url = `http://127.0.0.1:${server.httpServer.address().port}/`;

if (process.argv.includes('--serve')) {
    console.log(`Synthetic handoff fixture: ${url}`);
    await new Promise(() => {});
} else {
    let browser;

    try {
        browser = await chromium.launch({ headless: true });

        for (const viewport of [
            { width: 1280, height: 900 },
            { width: 390, height: 844 },
        ]) {
            const page = await browser.newPage({ viewport });
            const errors = [];
            page.on('pageerror', (error) => errors.push(error.message));
            await page.goto(url);
            await page.getByTestId('classic-payment-simulate').waitFor();
            const disclosure = page.getByTestId(
                'classic-payment-simulation-disclosure',
            );
            await disclosure.waitFor();
            await assert.doesNotReject(() =>
                disclosure.getByText('UAT / Test Payment').waitFor(),
            );
            await assert.doesNotReject(() =>
                disclosure
                    .getByText(
                        'This simulates successful QR Ph payment for testing only. No production funds are moved, and this action has no production or legal effect.',
                    )
                    .waitFor(),
            );
            assert.equal(
                await page.evaluate(() => {
                    const disclosure = document.querySelector(
                        '[data-testid="classic-payment-simulation-disclosure"]',
                    );
                    const action = document.querySelector(
                        '[data-testid="classic-payment-simulate"]',
                    );

                    return Boolean(
                        disclosure &&
                        action &&
                        disclosure.compareDocumentPosition(action) &
                            Node.DOCUMENT_POSITION_FOLLOWING,
                    );
                }),
                true,
            );
            assert.equal(
                await page.getByTestId('staff-qr-ph-generate').count(),
                0,
            );
            assert.equal(
                await page.getByText('Record over-the-counter payment').count(),
                0,
            );
            assert.equal(
                await page.locator('input[name="attempt_id"]').inputValue(),
                '8',
            );
            await Promise.all([
                page.waitForResponse(
                    (response) =>
                        new URL(response.url()).pathname ===
                            '/synthetic-confirm' &&
                        response.request().method() === 'POST',
                ),
                page.getByTestId('classic-payment-simulate').click(),
            ]);
            assert.match(
                submitted.at(-1),
                /(?:"attempt_id":"?8"?|attempt_id=8)/,
            );
            assert.deepEqual(errors, []);
            assert.equal(
                await page.evaluate(
                    () => document.documentElement.scrollWidth <= innerWidth,
                ),
                true,
            );
            await page.close();
        }

        console.log(
            'PASS: actual Cashier form submits exact attempt on desktop and 390×844',
        );
    } finally {
        await browser?.close();
        await server.close();
    }
}
