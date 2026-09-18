import assert from 'node:assert/strict';
import { resolve } from 'node:path';
import test from 'node:test';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { chromium } from 'playwright';
import { createServer } from 'vite';

test('Treasury can reach a Line of Business beyond the first 100 catalog options', async () => {
    const target = {
        id: 999,
        code: 'LOB-3A9A93CA46967768',
        name: 'REC- Fresh Fish Retailer',
        default_items: [
            {
                fee_rule_id: 999,
                code: 'FEE-FRESH-FISH',
                name: "Mayor's Permit Fee",
                amount_cents: 100_000,
                resolution_status: 'resolved',
            },
        ],
    };
    const lineOfBusinessOptions = [
        ...Array.from({ length: 150 }, (_, index) => ({
            id: index + 1,
            code: `LOB-CATALOG-${String(index + 1).padStart(3, '0')}`,
            name: `Catalog Activity ${String(index + 1).padStart(3, '0')}`,
            default_items: [],
        })),
        target,
    ];
    const task = {
        schema_version: 'test',
        application: {
            id: 292,
            application_number: null,
            tracking_reference: 'SUB-TEST-292',
            business_name: 'Synthetic Treasury Selector Test',
            owner_name: 'Synthetic Owner',
            type: 'new',
            year: 2025,
            submitted_at: '2026-09-18T00:00:00+08:00',
            business_activity_description: 'Fresh fish retailer',
            commissioned_path: true,
            lines: [],
        },
        routing: {
            id: 1,
            determined_by: 'BPLO Intake',
            determined_at: '2026-09-18T00:00:00+08:00',
            situational_context: 'Synthetic test routing',
            origin: 'bplo_confirmed',
            works: [
                {
                    id: 1,
                    office_code: 'health',
                    office_label: 'Municipal Health Office',
                    situational_reason: 'Synthetic test',
                    required_work: 'Prepare office Payment Order.',
                    permit_application_line_id: 1,
                    line_of_business_name: null,
                    payment_orders: [
                        {
                            id: 1,
                            sequence: 1,
                            status: 'finalized',
                            total_amount_cents: 9_500,
                            issued_by: 'Health',
                            issued_at: '2026-09-18T00:00:00+08:00',
                            signature_facsimile_data_url: null,
                        },
                    ],
                },
            ],
        },
        suggestion: null,
        office_options: [],
        financial_editor: {
            catalog_status: 'synthetic',
            office_fee_options: {},
            menro_determination: null,
            menro_determination_proposal: null,
            can_record_menro_determination: false,
            line_of_business_options: lineOfBusinessOptions,
            treasury_assignments: [],
            authorized_payment_order_office_codes: [],
            can_assign_treasury_lobs: true,
        },
        can_determine: false,
        manual_confirmation_required: false,
    };
    const html = `<meta name="viewport" content="width=device-width, initial-scale=1"><div id="app"></div><script type="module">
        import '/resources/css/app.css';
        import { createApp, h } from 'vue';
        import { createInertiaApp } from '@inertiajs/vue3';
        import Routing from '/resources/js/components/permit-applications/BploRoutingTaskSheet.vue';
        createInertiaApp({ page: { component: 'Routing', props: { task: ${JSON.stringify(task)}, errors: {} }, url: '/', version: null }, resolve: () => Routing, setup: ({ el, App, props, plugin }) => createApp({ render: () => h(App, props) }).use(plugin).mount(el) });
    </script>`;
    const server = await createServer({
        configFile: false,
        root: process.cwd(),
        cacheDir: resolve(
            '/tmp',
            `bpls-treasury-lob-selector-vite-${process.pid}`,
        ),
        plugins: [
            vue(),
            tailwindcss(),
            {
                name: 'treasury-lob-selector-test-page',
                configureServer(viteServer) {
                    viteServer.middlewares.use((request, response, next) => {
                        if (request.url !== '/') {
                            return next();
                        }

                        viteServer
                            .transformIndexHtml('/', html)
                            .then((body) => {
                                response.setHeader('Content-Type', 'text/html');
                                response.end(body);
                            });
                    });
                },
            },
        ],
        resolve: { alias: { '@': resolve('resources/js') } },
        server: { host: '127.0.0.1', port: 0 },
    });
    let browser;

    try {
        await server.listen();
        browser = await chromium.launch({ headless: true });
        const port = server.httpServer.address().port;
        const page = await browser.newPage({
            viewport: { width: 1280, height: 900 },
        });
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));

        await page.goto(`http://127.0.0.1:${port}/`);

        const selector = page.locator('#treasury-line-of-business');
        await selector.waitFor();
        assert.equal(await selector.locator('option').count(), 152);
        assert.equal(
            await selector
                .locator(`option[value="${target.id}"]`)
                .textContent(),
            target.name,
        );

        await page
            .getByRole('searchbox', {
                name: 'Search Line of Business catalogue',
            })
            .fill('Fresh Fish');
        assert.equal(await selector.locator('option').count(), 2);
        await selector.selectOption(String(target.id));
        await page
            .getByRole('button', { name: 'Add Line of Business' })
            .click();

        await page
            .getByRole('heading', { name: 'Payment items for selected LOB' })
            .waitFor();
        assert.equal(
            await page
                .locator('article')
                .filter({ hasText: target.name })
                .filter({ hasText: "Mayor's Permit Fee" })
                .count(),
            1,
        );
        assert.deepEqual(errors, []);
        await page.close();
    } finally {
        await browser?.close();
        await server.close();
    }
});
