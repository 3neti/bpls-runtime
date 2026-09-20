import assert from 'node:assert/strict';
import { resolve } from 'node:path';
import test from 'node:test';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { chromium } from 'playwright';
import { createServer } from 'vite';

function walkthroughPdf() {
    const sentence = 'HARMLESS WALKTHROUGH DOCUMENT';
    const stream = `BT /F1 18 Tf 72 720 Td (${sentence}) Tj ET`;
    const objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
        `<< /Length ${stream.length} >>\nstream\n${stream}\nendstream`,
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];
    let body = '%PDF-1.4\n';
    const offsets = [0];

    objects.forEach((object, index) => {
        offsets.push(Buffer.byteLength(body));
        body += `${index + 1} 0 obj\n${object}\nendobj\n`;
    });

    const xref = Buffer.byteLength(body);
    body += `xref\n0 ${objects.length + 1}\n0000000000 65535 f \n`;
    body += offsets
        .slice(1)
        .map((offset) => `${String(offset).padStart(10, '0')} 00000 n \n`)
        .join('');
    body += `trailer\n<< /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF`;

    return Buffer.from(body);
}

test('authorized document references open visible PDF and image previews without losing Download', async () => {
    const documents = [
        {
            document_id: 1,
            label: 'DTI Registration',
            original_name: 'registration.pdf',
            mime_type: 'application/pdf',
            size_bytes: 128,
            view_url: '/registration.pdf',
            download_url: '/registration.pdf?download=1',
        },
        {
            document_id: 2,
            label: 'Storefront Photo',
            original_name: 'storefront.png',
            mime_type: 'image/png',
            size_bytes: 68,
            view_url:
                'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1sAAAAASUVORK5CYII=',
            download_url: '/storefront.png?download=1',
        },
        {
            document_id: 3,
            label: 'Malformed PDF',
            original_name: 'malformed.pdf',
            mime_type: 'application/pdf',
            size_bytes: 12,
            view_url: '/malformed.pdf',
            download_url: '/malformed.pdf?download=1',
        },
    ];
    const html = `<meta name="viewport" content="width=device-width, initial-scale=1"><div id="app"></div><script type="module">
        import '/resources/css/app.css';
        import { createApp, h } from 'vue';
        import Reference from '/resources/js/components/permit-applications/ApplicantDocumentReference.vue';
        createApp({ render: () => h(Reference, { documents: ${JSON.stringify(documents)} }) }).mount('#app');
    </script>`;
    const raceHtml = `<meta name="viewport" content="width=device-width, initial-scale=1"><div id="app"></div><script type="module">
        import '/resources/css/app.css';
        import { createApp, h, ref } from 'vue';
        import Preview from '/resources/js/components/permit-applications/AuthenticatedPdfPreview.vue';
        createApp({
            setup() {
                const source = ref('/slow.pdf');
                const mounted = ref(true);
                return () => h('main', { style: 'width: 720px; max-width: 100%; height: 640px' }, [
                    h('button', { onClick: () => { source.value = '/malformed.pdf'; } }, 'Switch PDF'),
                    h('button', { onClick: () => { mounted.value = false; } }, 'Unmount preview'),
                    mounted.value ? h(Preview, { src: source.value, title: 'Race PDF' }) : null,
                ]);
            },
        }).mount('#app');
    </script>`;
    const server = await createServer({
        configFile: false,
        root: process.cwd(),
        cacheDir: resolve('/tmp', `bpls-document-preview-vite-${process.pid}`),
        plugins: [
            vue(),
            tailwindcss(),
            {
                name: 'document-preview-test-page',
                configureServer(viteServer) {
                    viteServer.middlewares.use((request, response, next) => {
                        if (request.url?.startsWith('/registration.pdf')) {
                            response.setHeader(
                                'Content-Type',
                                'application/pdf',
                            );
                            response.end(walkthroughPdf());

                            return;
                        }

                        if (request.url?.startsWith('/malformed.pdf')) {
                            response.setHeader(
                                'Content-Type',
                                'application/pdf',
                            );
                            response.end('%PDF-broken');

                            return;
                        }

                        if (request.url?.startsWith('/slow.pdf')) {
                            response.setHeader(
                                'Content-Type',
                                'application/pdf',
                            );
                            setTimeout(
                                () => response.end(walkthroughPdf()),
                                500,
                            );

                            return;
                        }

                        if (request.url !== '/' && request.url !== '/race') {
                            return next();
                        }

                        viteServer
                            .transformIndexHtml(
                                request.url,
                                request.url === '/race' ? raceHtml : html,
                            )
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
        await page.getByRole('link', { name: 'Download' }).first().waitFor();

        assert.equal(
            await page.getByRole('link', { name: 'Download' }).count(),
            3,
        );
        await page
            .getByRole('button', { name: 'View DTI Registration' })
            .last()
            .click();
        await page.getByRole('dialog').waitFor();
        await page
            .getByTestId('pdf-text-layer')
            .filter({ hasText: 'HARMLESS WALKTHROUGH DOCUMENT' })
            .waitFor();
        await page
            .getByLabel('DTI Registration, page 1', { exact: true })
            .waitFor();
        await page.setViewportSize({ width: 390, height: 844 });
        const pdfCanvas = page.getByLabel('DTI Registration, page 1', {
            exact: true,
        });
        await page.waitForFunction(
            (element) =>
                element.getBoundingClientRect().width <=
                element.parentElement.clientWidth,
            await pdfCanvas.elementHandle(),
        );
        const dialogBox = await page.getByRole('dialog').boundingBox();
        const previewBox = await page
            .getByLabel('DTI Registration PDF preview')
            .boundingBox();
        const mobileCanvasBox = await pdfCanvas.boundingBox();
        const mobileDownloadBox = await page
            .getByRole('dialog')
            .getByRole('link', { name: 'Download DTI Registration' })
            .boundingBox();
        assert.ok(
            dialogBox && previewBox && mobileCanvasBox && mobileDownloadBox,
        );
        assert.ok(dialogBox.x >= 0 && dialogBox.x + dialogBox.width <= 390);
        assert.ok(
            mobileCanvasBox.x >= 0 &&
                mobileCanvasBox.x + mobileCanvasBox.width <= 390,
        );
        assert.ok(
            mobileDownloadBox.x >= 0 &&
                mobileDownloadBox.x + mobileDownloadBox.width <= 390,
        );
        assert.ok(
            previewBox.y + previewBox.height <= dialogBox.y + dialogBox.height,
        );
        assert.equal(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= window.innerWidth,
            ),
            true,
        );
        assert.ok((await page.getByRole('dialog').screenshot()).length > 1_000);

        await page.setViewportSize({ width: 844, height: 390 });
        await page.waitForFunction(
            (element) =>
                element.getBoundingClientRect().width <=
                element.parentElement.clientWidth,
            await pdfCanvas.elementHandle(),
        );
        const landscapeDialogBox = await page.getByRole('dialog').boundingBox();
        const landscapePreviewBox = await page
            .getByLabel('DTI Registration PDF preview')
            .boundingBox();
        assert.ok(landscapeDialogBox && landscapePreviewBox);
        assert.ok(
            landscapePreviewBox.y + landscapePreviewBox.height <=
                landscapeDialogBox.y + landscapeDialogBox.height,
        );
        assert.ok(
            landscapeDialogBox.y + landscapeDialogBox.height <= 390,
            JSON.stringify({ landscapeDialogBox, landscapePreviewBox }),
        );

        await page.keyboard.press('Escape');
        await page.setViewportSize({ width: 390, height: 844 });
        await page
            .getByRole('button', { name: 'View Storefront Photo' })
            .last()
            .click();
        const image = page.getByRole('dialog').getByAltText('Storefront Photo');
        await image.waitFor();
        assert.equal(
            await image.evaluate((element) => element.naturalWidth > 0),
            true,
        );
        assert.equal(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= window.innerWidth,
            ),
            true,
        );

        await page.keyboard.press('Escape');
        await page
            .getByRole('button', { name: 'View Malformed PDF' })
            .last()
            .click();
        await page.getByRole('alert').waitFor();
        assert.equal(
            await page
                .getByTestId('pdf-text-layer')
                .filter({ hasText: 'HARMLESS WALKTHROUGH DOCUMENT' })
                .count(),
            0,
        );
        assert.equal(
            await page
                .getByLabel('Malformed PDF, page 1', { exact: true })
                .evaluate((element) => element.width),
            0,
        );

        await page.goto(`http://127.0.0.1:${port}/race`);
        await page.getByRole('button', { name: 'Switch PDF' }).click();
        await page.getByRole('alert').waitFor();
        assert.equal(
            await page
                .getByTestId('pdf-text-layer')
                .filter({ hasText: 'HARMLESS WALKTHROUGH DOCUMENT' })
                .count(),
            0,
        );
        await page.reload();
        await page.getByRole('button', { name: 'Unmount preview' }).click();
        await page.waitForTimeout(650);
        assert.equal(await page.getByLabel('Race PDF PDF preview').count(), 0);
        assert.deepEqual(errors, []);
    } finally {
        await browser?.close();
        await server.close();
    }
});
