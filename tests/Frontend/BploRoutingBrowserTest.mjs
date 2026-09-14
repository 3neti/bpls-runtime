import assert from 'node:assert/strict';
import { resolve } from 'node:path';
import test from 'node:test';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { chromium } from 'playwright';
import { createServer } from 'vite';

test('rendered routing form acknowledges JSON independently of navigation on desktop and mobile', async () => {
    const task = {
        schema_version: 'test',
        application: {
            id: 999,
            business_name: 'Synthetic Routing',
            owner_name: 'Synthetic Owner',
            commissioned_path: true,
            lines: [],
            year: 2026,
            type: 'new',
        },
        routing: null,
        suggestion: null,
        can_determine: true,
        manual_confirmation_required: true,
        office_options: ['assessor', 'engineering', 'health', 'menro'].map(
            (code) => ({ code, label: code }),
        ),
        financial_editor: {
            catalog_status: 'synthetic',
            office_fee_options: {},
            line_of_business_options: [],
            treasury_assignments: [],
            authorized_payment_order_office_codes: [],
            can_assign_treasury_lobs: false,
        },
    };
    const html = `<meta name="viewport" content="width=device-width, initial-scale=1"><div id="app"></div><script type="module">
        import '/resources/css/app.css';
        import { createApp, h } from 'vue';
        import { createInertiaApp } from '@inertiajs/vue3';
        import Routing from '/resources/js/components/permit-applications/BploRoutingTaskSheet.vue';
        createInertiaApp({ page: { component: 'Routing', props: { task: ${JSON.stringify(task)}, errors: {} }, url: '/', version: null }, resolve: () => Routing, setup: ({ el, App, props, plugin }) => createApp({ render: () => h(App, props) }).use(plugin).mount(el) });
    </script>`;
    let fixturePosts = 0;
    const server = await createServer({
        configFile: false,
        root: process.cwd(),
        plugins: [
            vue(),
            tailwindcss(),
            {
                name: 'routing-test-page',
                configureServer(server) {
                    server.middlewares.use((request, response, next) => {
                        if (
                            process.argv.includes('--serve') &&
                            request.url ===
                                '/staff/permit-applications/999/bplo-routing' &&
                            request.method === 'POST'
                        ) {
                            fixturePosts++;
                            response.setHeader(
                                'Content-Type',
                                'application/json',
                            );
                            response.end(
                                JSON.stringify({
                                    status: 'recorded',
                                    permit_application_id: 999,
                                    routing_determination_id: 31,
                                }),
                            );
                            return;
                        }
                        if (request.url === '/test-state') {
                            response.setHeader(
                                'Content-Type',
                                'application/json',
                            );
                            response.end(
                                JSON.stringify({
                                    posts: fixturePosts,
                                    synthetic_only: true,
                                }),
                            );
                            return;
                        }
                        if (request.url !== '/') return next();
                        server.transformIndexHtml('/', html).then((body) => {
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
        if (process.argv.includes('--serve')) {
            console.log(
                `Synthetic browser fixture: http://127.0.0.1:${server.httpServer.address().port}/`,
            );
            await new Promise(() => {});
        }
        browser = await chromium.launch({ headless: true });
        const port = server.httpServer.address().port;
        for (const viewport of [
            { width: 1280, height: 900 },
            { width: 390, height: 844 },
        ]) {
            const page = await browser.newPage({ viewport });
            const errors = [];
            page.on('pageerror', (error) => errors.push(error.message));
            let posts = 0;
            await page.route(
                '**/staff/permit-applications/999/bplo-routing',
                async (route) => {
                    posts++;
                    assert.match(
                        route.request().headers().accept,
                        /application\/json/,
                    );
                    assert.equal(
                        route.request().headers()['x-inertia'],
                        undefined,
                    );
                    assert.equal(
                        route.request().postDataJSON().selected_work.length,
                        4,
                    );
                    await route.fulfill({
                        json: {
                            status: 'recorded',
                            permit_application_id: 999,
                            routing_determination_id: 31,
                        },
                    });
                },
            );
            await page.goto(`http://127.0.0.1:${port}/`);
            const choices = page
                .getByTestId('concerned-office-option')
                .getByRole('checkbox');
            await choices.first().waitFor();
            for (const choice of await choices.all()) await choice.check();
            await page.getByTestId('confirm-bplo-routing').click();
            await page
                .getByRole('status')
                .filter({ hasText: 'routing recorded' })
                .waitFor();
            assert.equal(
                await page.getByTestId('confirm-bplo-routing').isDisabled(),
                true,
            );
            assert.equal(
                await page
                    .getByRole('link', { name: 'Return to My Work' })
                    .getAttribute('href'),
                '/staff/work',
            );
            assert.equal(posts, 1);
            assert.deepEqual(errors, []);
            assert.equal(
                await page.evaluate(
                    () =>
                        document.documentElement.scrollWidth <=
                        window.innerWidth,
                ),
                true,
            );
            await page.close();
        }
        const page = await browser.newPage();
        let posts = 0;
        await page.route(
            '**/staff/permit-applications/999/bplo-routing',
            async (route) => {
                posts++;
                await route.abort('failed');
            },
        );
        await page.goto(`http://127.0.0.1:${port}/`);
        await page
            .getByTestId('concerned-office-option')
            .getByRole('checkbox')
            .first()
            .check();
        await page.getByTestId('confirm-bplo-routing').click();
        await page
            .getByRole('status')
            .filter({ hasText: 'outcome is unconfirmed' })
            .waitFor();
        assert.equal(
            await page.getByTestId('confirm-bplo-routing').isDisabled(),
            true,
        );
        assert.equal(
            await page
                .getByRole('button', { name: 'Reload routing record' })
                .isVisible(),
            true,
        );
        assert.equal(posts, 1);
        await page.close();
    } finally {
        await browser?.close();
        await server.close();
    }
});
