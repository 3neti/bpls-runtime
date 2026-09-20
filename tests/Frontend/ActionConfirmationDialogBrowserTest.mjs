import assert from 'node:assert/strict';
import { resolve } from 'node:path';
import test from 'node:test';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { chromium } from 'playwright';
import { createServer } from 'vite';

test(
    'confirmation contains focus, cancels without action, and fits desktop and mobile',
    { timeout: 90000 },
    async () => {
        const html = `<meta name="viewport" content="width=device-width, initial-scale=1">
        <div id="app"></div><script type="module">
        import '/resources/css/app.css';
        import { createApp, h, ref } from 'vue';
        import Confirmation from '/resources/js/components/ActionConfirmationDialog.vue';
        createApp({ setup() {
            const dialog = ref(null);
            const count = ref(0);
            return () => h('main', [
                h('button', { id: 'trigger', onClick: async () => {
                    if (await dialog.value.ask('Prepare Assessment?',
                        'This locks the current fees and amounts. Treasurer approval follows separately.',
                        'Prepare Assessment')) count.value++;
                } }, 'Open confirmation'),
                h('output', { id: 'count' }, String(count.value)),
                h(Confirmation, { ref: dialog, context: 'Fresh Fish Walkthrough · 2025 New · ₱3,975.00' }),
            ]);
        } }).mount('#app');
        </script>`;
        const server = await createServer({
            configFile: false,
            root: process.cwd(),
            cacheDir: resolve('/tmp', `bpls-confirmation-vite-${process.pid}`),
            plugins: [
                vue(),
                tailwindcss(),
                {
                    name: 'confirmation-test-page',
                    configureServer(viteServer) {
                        viteServer.middlewares.use(
                            (request, response, next) => {
                                if (request.url !== '/') {
return next();
}

                                viteServer
                                    .transformIndexHtml('/', html)
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
            resolve: { alias: { '@': resolve('resources/js') } },
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
                const page = await browser.newPage({ viewport });
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                page.on('console', (message) => {
                    if (message.type() === 'error') {
errors.push(message.text());
}
                });
                await page.goto(
                    `http://127.0.0.1:${server.httpServer.address().port}/`,
                );
                const trigger = page.getByRole('button', {
                    name: 'Open confirmation',
                });
                await trigger.click();
                const dialog = page.getByRole('dialog', {
                    name: 'Prepare Assessment?',
                });
                await dialog.waitFor();
                assert.match(await dialog.innerText(), /₱3,975\.00/);

                for (let index = 0; index < 5; index++) {
                    await page.keyboard.press('Tab');
                    assert.equal(
                        await dialog.evaluate((element) =>
                            element.contains(document.activeElement),
                        ),
                        true,
                    );
                }

                await page.keyboard.press('Escape');
                await dialog.waitFor({ state: 'hidden' });
                assert.equal(await page.locator('#count').innerText(), '0');
                await page.waitForFunction(
                    () => document.activeElement?.id === 'trigger',
                );
                await trigger.click();
                await page
                    .getByRole('button', { name: 'Cancel', exact: true })
                    .click();
                await dialog.waitFor({ state: 'hidden' });
                assert.equal(await page.locator('#count').innerText(), '0');
                await trigger.click();
                const box = await dialog.boundingBox();
                assert.ok(box.x >= 0 && box.x + box.width <= viewport.width);
                assert.ok(box.y >= 0 && box.y + box.height <= viewport.height);
                assert.equal(
                    await page.evaluate(
                        () =>
                            document.documentElement.scrollWidth <= innerWidth,
                    ),
                    true,
                );
                await page
                    .getByRole('button', {
                        name: 'Prepare Assessment',
                        exact: true,
                    })
                    .click();
                await dialog.waitFor({ state: 'hidden' });
                assert.equal(await page.locator('#count').innerText(), '1');
                await page.waitForFunction(
                    () => document.activeElement?.id === 'trigger',
                );
                assert.deepEqual(errors, []);
                await page.close();
            }
        } finally {
            await browser?.close();
            await server.close();
        }
    },
);
