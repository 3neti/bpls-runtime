import assert from 'node:assert/strict';
import { resolve } from 'node:path';
import test from 'node:test';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { chromium } from 'playwright';
import { createServer } from 'vite';

test(
    'ordinary Permit workspace renders blocked, authorized, issued and released states responsively',
    { timeout: 90000 },
    async () => {
        const phases = [
            {
                heading: 'Mayoral Authorization',
                action: null,
                completion: null,
            },
            {
                heading: 'Mayoral Authorization',
                action: 'authorize',
                completion: null,
            },
            {
                heading: 'Business Permit Issuance',
                action: 'issue',
                completion: {
                    authorized_at: '2026-09-20T14:36:06Z',
                    issued_at: null,
                    released_at: null,
                    permit_number: null,
                },
            },
            {
                heading: 'Business Permit Release',
                action: 'release',
                completion: {
                    authorized_at: '2026-09-20T14:36:06Z',
                    issued_at: '2026-09-20T14:37:46Z',
                    released_at: null,
                    permit_number: 'BP-2026-TEST',
                },
            },
            {
                heading: 'Business Permit Released',
                action: null,
                completion: {
                    authorized_at: '2026-09-20T14:36:06Z',
                    issued_at: '2026-09-20T14:37:46Z',
                    released_at: '2026-09-20T14:40:00Z',
                    permit_number: 'BP-2026-TEST',
                },
            },
        ];
        const server = await createServer({
            configFile: false,
            root: process.cwd(),
            cacheDir: resolve(
                '/tmp',
                `bpls-permit-workspace-vite-${process.pid}`,
            ),
            resolve: {
                alias: [
                    {
                        find: '@inertiajs/vue3',
                        replacement: '/test-inertia.js',
                    },
                    {
                        find: '@/layouts/AppLayout.vue',
                        replacement: '/test-layout.js',
                    },
                    { find: '@', replacement: resolve('resources/js') },
                ],
            },
            plugins: [
                vue(),
                tailwindcss(),
                {
                    name: 'permit-workspace-test',
                    resolveId(id) {
                        if (
                            id === '/test-inertia.js' ||
                            id === '/test-layout.js'
                        ) {
                            return '\0' + id;
                        }
                    },
                    load(id) {
                        if (id === '\0/test-inertia.js') {
                            return `import { h, reactive } from 'vue';
                                export const Head = () => null;
                                export const Link = (props, { attrs, slots }) => h('a', attrs, slots.default?.());
                                export const useForm = (data) => reactive({ ...data, errors: {}, processing: false,
                                    post() { window.testPosts = (window.testPosts || 0) + 1; this.processing = true; } });`;
                        }

                        if (id === '\0/test-layout.js') {
                            return `import { h } from 'vue'; export default (props, { slots }) => h('div', slots.default?.());`;
                        }
                    },
                    configureServer(viteServer) {
                        viteServer.middlewares.use(
                            (request, response, next) => {
                                const path = new URL(
                                    request.url,
                                    'http://localhost',
                                );

                                if (
                                    path.pathname !== '/' ||
                                    path.searchParams.has('html-proxy')
                                ) {
                                    return next();
                                }

                                const index = Number(
                                    path.searchParams.get('phase') ?? 0,
                                );
                                const phase = phases[index];
                                const props = {
                                    application: {
                                        id: 1,
                                        tracking_reference:
                                            'SUB-TEST-WORKSPACE',
                                        business_name: 'Fresh Fish Walkthrough',
                                        year: 2026,
                                        type: 'new',
                                    },
                                    readiness: {
                                        prerequisites: {
                                            application_eligible: true,
                                            canonical_collection: index > 0,
                                            issued_official_receipts: index > 0,
                                            complete_receipt_coverage:
                                                index > 0,
                                            official_receipt_totals_reconcile:
                                                index > 0,
                                            all_required_post_payment_certifications:
                                                index > 0,
                                            mayoral_authorization_recorded:
                                                index > 1,
                                        },
                                        receipt_total_cents:
                                            index > 0 ? 397500 : 0,
                                        receipt_numbers: ['9000001', '9000002'],
                                    },
                                    completion: phase.completion && {
                                        ...phase.completion,
                                        authorization_fingerprint: 'a'.repeat(
                                            64,
                                        ),
                                    },
                                    action: phase.action,
                                    applicationUrl: '/application',
                                    permitUrl:
                                        index >= 3 ? '/permit.pdf' : null,
                                    verificationUrl:
                                        index === 4 ? '/verify' : null,
                                };
                                const html = `<meta name="viewport" content="width=device-width, initial-scale=1"><div id="app"></div>
                        <script type="module">import '/resources/css/app.css'; import { createApp, h } from 'vue';
                        import Page from '/resources/js/pages/ordinary-uat-permit/Show.vue';
                        createApp({ render: () => h(Page, ${JSON.stringify(props)}) }).mount('#app');</script>`;
                                viteServer
                                    .transformIndexHtml(request.url, html)
                                    .then((body) => {
                                        response.setHeader(
                                            'Content-Type',
                                            'text/html',
                                        );
                                        response.end(body);
                                    })
                                    .catch(next);
                            },
                        );
                    },
                },
            ],
            server: {
                host: '127.0.0.1',
                port: 0,
                watch: { ignored: ['**/storage/**', '**/database/**'] },
            },
        });
        let browser;

        try {
            await server.listen();
            browser = await chromium.launch({ headless: true });

            for (const viewport of [
                { width: 1440, height: 900 },
                { width: 390, height: 844 },
            ]) {
                for (const [index, phase] of phases.entries()) {
                    const page = await browser.newPage({ viewport });
                    const errors = [];
                    page.on('pageerror', (error) => errors.push(error.message));
                    page.on('console', (message) => {
                        if (message.type() === 'error') {
                            errors.push(message.text());
                        }
                    });
                    await page.goto(
                        `http://127.0.0.1:${server.httpServer.address().port}/?phase=${index}`,
                    );
                    await page
                        .getByRole('heading', {
                            name: phase.heading,
                            exact: true,
                        })
                        .waitFor();
                    assert.equal(
                        await page.locator('details').getAttribute('open'),
                        null,
                    );
                    assert.match(
                        await page.locator('main').innerText(),
                        /Test environment · Not valid for official use/,
                    );

                    if (index === 0) {
                        assert.match(
                            await page.locator('aside').innerText(),
                            /Still required[\s\S]*Payment recorded/,
                        );
                    }

                    if (index > 1) {
                        assert.match(
                            await page.locator('main').innerText(),
                            /10:36/,
                        );
                    }

                    if (index === 3) {
                        assert.match(
                            await page.locator('main').innerText(),
                            /Issued · Awaiting BPLO release/,
                        );
                    }

                    if (index === 4) {
                        assert.equal(
                            await page
                                .getByRole('link', {
                                    name: 'Verify Permit',
                                    exact: true,
                                })
                                .count(),
                            1,
                        );
                        assert.equal(
                            await page.locator('aside button').count(),
                            0,
                        );
                    }

                    assert.equal(
                        await page.evaluate(
                            () =>
                                document.documentElement.scrollWidth <=
                                innerWidth,
                        ),
                        true,
                    );

                    if (phase.action) {
                        const action = page.locator('aside button');
                        assert.equal(
                            await action.count(),
                            1,
                            JSON.stringify({
                                index,
                                text: await page.locator('main').innerText(),
                            }),
                        );
                        await action.click();
                        await page.getByRole('dialog').waitFor();
                        await page
                            .getByRole('button', {
                                name: 'Cancel',
                                exact: true,
                            })
                            .click();
                        await page
                            .getByRole('dialog')
                            .waitFor({ state: 'hidden' });
                        assert.equal(
                            await page.evaluate(() => window.testPosts || 0),
                            0,
                        );
                        await action.click();
                        await page
                            .getByRole('dialog')
                            .getByRole('button')
                            .last()
                            .click();
                        await page
                            .getByRole('dialog')
                            .waitFor({ state: 'hidden' });
                        assert.equal(
                            await page.evaluate(() => window.testPosts),
                            1,
                        );
                        assert.equal(await action.isDisabled(), true);
                    }

                    assert.deepEqual(errors, []);
                    await page.close();
                }
            }
        } finally {
            await browser?.close();
            await server.close();
        }
    },
);
