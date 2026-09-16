import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { chromium } from 'playwright';
import { createServer } from 'vite';

// Actual Vue/Inertia page and CSS, synthetic props only. Laravel request/auth
// behavior is independently exercised by PublicHomeTest and the Fortify suite.
test('public home exposes ordinary links with responsive keyboard access, never engineering controls', async () => {
    const html = `<meta name="viewport" content="width=device-width, initial-scale=1"><div id="app"></div><script type="module">
        import '/resources/css/app.css';
        import { createApp, h } from 'vue';
        import { createInertiaApp } from '@inertiajs/vue3';
        import Home from '/resources/js/pages/Welcome.vue';
        const kind = new URLSearchParams(location.search).get('actor') || 'guest';
        const props = { isNonProduction: true, errors: {}, auth: { user: kind === 'guest' ? null : { name: 'Synthetic '+kind } }, stakeholder_preview: { enabled: true, show_engineering_controls: kind === 'engineer', personas: [{key: 'fixture'}] } };
        createInertiaApp({ page: { component: 'Welcome', props, url: '/', version: null }, resolve: () => Home, setup: ({ el, App, props, plugin }) => createApp({ render: () => h(App, props) }).use(plugin).mount(el) });
    </script>`;
    const server = await createServer({
        configFile: false,
        root: process.cwd(),
        plugins: [
            vue(),
            tailwindcss(),
            {
                name: 'public-home-test-page',
                configureServer(server) {
                    server.middlewares.use((request, response, next) => {
                        if (request.url?.split('?')[0] !== '/') {
                            return next();
                        }

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
                `Synthetic home fixture: http://127.0.0.1:${server.httpServer.address().port}/`,
            );
            await new Promise(() => {});
        }

        browser = await chromium.launch({ headless: true });
        const base = `http://127.0.0.1:${server.httpServer.address().port}`;
        const screenshots = resolve('storage/app/private/gate10b-browser');
        await mkdir(screenshots, { recursive: true });
        const actions = [
            [
                'Apply for a Business Permit',
                '/citizen/permit-applications/create',
            ],
            ['Track or Continue Application', '/citizen/permit-applications'],
            ['Staff Login', '/login'],
            ['Municipal Schedule of Fees', '/services-and-fees'],
        ];

        for (const viewport of [
            { width: 1440, height: 900 },
            { width: 390, height: 844 },
        ]) {
            for (const actor of ['guest', 'citizen', 'staff', 'engineer']) {
                const page = await browser.newPage({ viewport });
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                page.on('console', (message) => {
                    if (message.type() === 'error') {
                        errors.push(message.text());
                    }
                });
                page.on('requestfailed', (request) =>
                    errors.push(request.url()),
                );
                page.on('response', (response) => {
                    if (response.status() >= 400) {
                        errors.push(`${response.status()} ${response.url()}`);
                    }
                });
                await page.goto(`${base}/?actor=${actor}`);
                await page
                    .getByRole('heading', {
                        name: 'Business Permit and Licensing System',
                        exact: true,
                    })
                    .waitFor();
                assert.equal(await page.locator('a').count(), 4);
                assert.equal(
                    await page.locator('input, select, button').count(),
                    0,
                );
                assert.doesNotMatch(
                    await page.locator('body').innerText(),
                    /Laboratory|specimen|switch.*role|Ready for Authority Review/i,
                );
                assert.match(
                    await page.locator('body').innerText(),
                    /payments are simulated/,
                );
                assert.match(
                    await page.locator('body').innerText(),
                    /QR code or verification link/,
                );

                for (const [name, href] of actions) {
                    const link = page.getByRole('link', { name, exact: false });
                    assert.equal(await link.getAttribute('href'), href);
                    await page.keyboard.press('Tab');
                    assert.equal(
                        await link.evaluate(
                            (el) => el === document.activeElement,
                        ),
                        true,
                    );
                    assert.equal(
                        await link.evaluate((el) =>
                            el.matches(':focus-visible'),
                        ),
                        true,
                    );
                    assert.notEqual(
                        await link.evaluate(
                            (el) => getComputedStyle(el).boxShadow,
                        ),
                        'none',
                    );
                    const box = await link.boundingBox();
                    assert.ok(
                        box.x >= 0 &&
                            box.x + box.width <= viewport.width &&
                            box.height >= 40,
                    );
                    assert.equal(
                        await link.evaluate(
                            (el) => el.scrollWidth <= el.clientWidth,
                        ),
                        true,
                    );
                }

                assert.equal(
                    await page.evaluate(
                        () =>
                            document.documentElement.scrollWidth <= innerWidth,
                    ),
                    true,
                );

                if (actor === 'guest') {
                    await page.screenshot({
                        path: `${screenshots}/home-${viewport.width}x${viewport.height}.png`,
                        fullPage: true,
                    });

                    for (const [name, href] of actions) {
                        let requests = 0;
                        await page.route(`${base}${href}`, async (route) => {
                            requests++;
                            assert.equal(route.request().method(), 'GET');
                            assert.equal(
                                route.request().headers()['x-inertia'],
                                'true',
                            );
                            await route.fulfill({
                                status: 200,
                                headers: { 'X-Inertia': 'true' },
                                json: {
                                    component: 'Welcome',
                                    props: { isNonProduction: true },
                                    url: href,
                                    version: null,
                                },
                            });
                        });
                        await page
                            .getByRole('link', { name, exact: false })
                            .click();
                        await page.waitForURL(`${base}${href}`);
                        assert.equal(requests, 1);
                        await page.goto(base);
                        await page
                            .getByRole('heading', {
                                name: 'Business Permit and Licensing System',
                                exact: true,
                            })
                            .waitFor();
                    }
                }

                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser?.close();
        await server.close();
    }
});
