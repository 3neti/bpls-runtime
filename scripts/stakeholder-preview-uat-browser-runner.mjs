import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const [baseUrl, evidenceDirectory] = process.argv.slice(2);

if (!baseUrl || !evidenceDirectory) {
    throw new Error(
        'Usage: node scripts/stakeholder-preview-uat-browser-runner.mjs <base-url> <evidence-directory>',
    );
}

const personas = ['Citizen', 'BPLO', 'Treasury', 'Management'];
const viewports = [
    { name: 'desktop', width: 1440, height: 900 },
    { name: 'mobile', width: 390, height: 844 },
];
const screenshotsDirectory = path.join(evidenceDirectory, 'screenshots');
const checks = [];
const consoleMessages = [];
const failedRequests = [];
const unexpectedResponses = [];
const externalRequests = [];

fs.mkdirSync(screenshotsDirectory, { recursive: true });

function check(key, passed, detail = null) {
    checks.push({ key, passed, detail });

    if (!passed) {
        throw new Error(
            `Browser check failed: ${key}${detail ? ` (${detail})` : ''}`,
        );
    }
}

function slug(value) {
    return value.toLowerCase().replaceAll(' ', '-');
}

const browser = await chromium.launch({ headless: true });

try {
    for (const viewport of viewports) {
        const context = await browser.newContext({
            viewport: { width: viewport.width, height: viewport.height },
        });
        const page = await context.newPage();

        page.on('console', (message) => {
            if (['error', 'warning'].includes(message.type())) {
                consoleMessages.push({
                    viewport: viewport.name,
                    type: message.type(),
                    text: message.text(),
                });
            }
        });
        page.on('requestfailed', (request) => {
            failedRequests.push({
                viewport: viewport.name,
                method: request.method(),
                url: request.url(),
                error: request.failure()?.errorText ?? 'unknown',
            });
        });
        page.on('response', (response) => {
            const requestUrl = new URL(response.url());
            const expectedOrigin = new URL(baseUrl).origin;

            if (requestUrl.origin !== expectedOrigin) {
                externalRequests.push(response.url());
            }

            if (
                response.status() >= 400 &&
                !response.url().endsWith('/staff/users')
            ) {
                unexpectedResponses.push({
                    viewport: viewport.name,
                    status: response.status(),
                    url: response.url(),
                });
            }
        });

        await page.goto(baseUrl, { waitUntil: 'networkidle' });
        await page
            .getByText('BPLS Stakeholder Preview', { exact: true })
            .waitFor();
        check(
            `${viewport.name}-launcher-no-overflow`,
            await page.evaluate(
                () => document.documentElement.scrollWidth <= window.innerWidth,
            ),
        );
        await page.screenshot({
            path: path.join(
                screenshotsDirectory,
                `${viewport.name}-launcher.png`,
            ),
            fullPage: true,
        });

        for (const persona of personas) {
            await page.goto(baseUrl, { waitUntil: 'networkidle' });
            await page
                .getByRole('button', { name: `Enter as ${persona}` })
                .click();
            await page.waitForURL('**/dashboard');
            await page
                .getByText(
                    'STAKEHOLDER PREVIEW / UAT — SYNTHETIC DATA — NOT PRODUCTION',
                    { exact: true },
                )
                .waitFor();
            await page.getByRole('heading', { name: 'What to try' }).waitFor();
            check(
                `${viewport.name}-${slug(persona)}-no-overflow`,
                await page.evaluate(
                    () =>
                        document.documentElement.scrollWidth <=
                        window.innerWidth,
                ),
            );
            await page.screenshot({
                path: path.join(
                    screenshotsDirectory,
                    `${viewport.name}-${slug(persona)}.png`,
                ),
                fullPage: true,
            });
        }

        if (viewport.name === 'desktop') {
            for (const source of personas) {
                for (const target of personas) {
                    if (source === target) {
                        continue;
                    }

                    await page.goto(baseUrl, { waitUntil: 'networkidle' });
                    await page
                        .getByRole('button', { name: `Enter as ${source}` })
                        .click();
                    await page.waitForURL('**/dashboard');
                    await page
                        .getByRole('button', { name: target, exact: true })
                        .click();
                    await page.waitForLoadState('networkidle');
                    check(
                        `switch-${slug(source)}-to-${slug(target)}`,
                        await page
                            .getByRole('button', { name: target, exact: true })
                            .isDisabled(),
                    );
                }
            }

            await page.goto(baseUrl, { waitUntil: 'networkidle' });
            await page
                .getByRole('button', { name: 'Enter as Citizen' })
                .click();
            await page.waitForURL('**/dashboard');
            const forbiddenResponse = await page.goto(
                `${baseUrl}/staff/users`,
                { waitUntil: 'networkidle' },
            );
            check(
                'citizen-direct-staff-route-forbidden',
                forbiddenResponse?.status() === 403,
                String(forbiddenResponse?.status()),
            );

            await page.goto(`${baseUrl}/dashboard`, {
                waitUntil: 'networkidle',
            });
            await page.locator('[data-test="sidebar-menu-button"]').click();
            await page.locator('[data-test="logout-button"]').click();
            await page.waitForURL(baseUrl.replace(/\/$/, '') + '/');
            await page
                .getByText('BPLS Stakeholder Preview', { exact: true })
                .waitFor();
            check('logout-returns-to-launcher', true);
        }

        await context.close();
    }

    check(
        'zero-console-errors-or-warnings',
        consoleMessages.length === 0,
        JSON.stringify(consoleMessages),
    );
    check(
        'zero-failed-requests',
        failedRequests.length === 0,
        JSON.stringify(failedRequests),
    );
    check(
        'zero-unexpected-http-responses',
        unexpectedResponses.length === 0,
        JSON.stringify(unexpectedResponses),
    );
    check(
        'zero-external-requests',
        externalRequests.length === 0,
        JSON.stringify(externalRequests),
    );

    const report = {
        result: {
            passed: checks.every((item) => item.passed),
            check_count: checks.length,
            screenshot_count: fs.readdirSync(screenshotsDirectory).length,
            console_error_or_warning_count: consoleMessages.length,
            failed_request_count: failedRequests.length,
            unexpected_response_count: unexpectedResponses.length,
            external_request_count: externalRequests.length,
        },
        checks,
        console_messages: consoleMessages,
        failed_requests: failedRequests,
        unexpected_responses: unexpectedResponses,
        external_requests: externalRequests,
    };

    fs.writeFileSync(
        path.join(evidenceDirectory, 'report.json'),
        `${JSON.stringify(report, null, 2)}\n`,
    );
    process.stdout.write(`${JSON.stringify(report.result)}\n`);
} finally {
    await browser.close();
}
