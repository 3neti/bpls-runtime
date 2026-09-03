import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const [baseUrl, evidenceDirectory] = process.argv.slice(2);

if (!baseUrl || !evidenceDirectory) {
    throw new Error(
        'Usage: node scripts/stakeholder-preview-uat-browser-runner.mjs <base-url> <evidence-directory>',
    );
}

const personas = [
    'Citizen',
    'BPLO',
    'Treasury',
    'Management',
    'Engineering',
    'MPDO / MPDC',
    'Assessor',
    'Health',
    'MENRO',
    "Mayor's Office",
    'Releasing Officer',
];
const workflowPersonas = new Set(personas.slice(4));
const entryButtonName = (persona) =>
    persona === 'Citizen' ? 'Open empty Citizen view' : `Enter as ${persona}`;
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
    return value
        .toLowerCase()
        .replaceAll(/[^a-z0-9]+/g, '-')
        .replaceAll(/(^-|-$)/g, '');
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
                .getByRole('button', { name: entryButtonName(persona) })
                .click();
            await page.waitForURL('**/dashboard');
            await page
                .getByText('Preview Environment · Sample Data', { exact: true })
                .waitFor();
            await page.getByRole('heading', { name: 'What to try' }).waitFor();

            if (workflowPersonas.has(persona)) {
                await page.goto(
                    `${baseUrl.replace(/\/$/, '')}/stakeholder-preview/workflow`,
                    {
                        waitUntil: 'networkidle',
                    },
                );
                await page
                    .getByRole('heading', { name: `${persona} Workspace` })
                    .waitFor();
                check(
                    `${viewport.name}-${slug(persona)}-workspace-visible`,
                    true,
                );
            }

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
                .getByRole('button', { name: entryButtonName('Citizen') })
                .click();
            await page.waitForURL('**/dashboard');
            await page.goto(
                `${baseUrl.replace(/\/$/, '')}/citizen/permit-applications/create`,
                { waitUntil: 'networkidle' },
            );
            await page.getByTestId('permit-application-lab-helper').waitFor();
            const specimenPool = page.getByTestId(
                'permit-application-lab-specimen',
            );
            check(
                'citizen-permit-helper-offers-legacy-specimen-pool',
                (await specimenPool.locator('option').count()) >= 2,
            );
            await page
                .locator('[name="business_name"]')
                .fill('Tester-entered business remains unchanged');
            await page.getByTestId('fill-remaining-permit-fields').click();
            check(
                'citizen-permit-helper-preserves-entered-values',
                (await page.locator('[name="business_name"]').inputValue()) ===
                    'Tester-entered business remains unchanged',
            );
            await page.getByTestId('clear-permit-helper-values').click();
            await specimenPool.selectOption({ index: 1 });
            await page.getByTestId('load-permit-legacy-specimen').click();
            check(
                'citizen-permit-helper-loads-selected-legacy-business',
                (await page.locator('[name="business_name"]').inputValue()) !==
                    'Tester-entered business remains unchanged',
            );
            check(
                'citizen-permit-helper-fills-ipil-geography',
                (await page
                    .locator('[name="business_barangay"]')
                    .inputValue()) !== '' &&
                    (
                        await page
                            .locator('[name="business_city_municipality"]')
                            .inputValue()
                    ).toLowerCase() === 'ipil' &&
                    (
                        await page
                            .locator('[name="business_province"]')
                            .inputValue()
                    ).toLowerCase() === 'zamboanga sibugay' &&
                    (await page
                        .locator('[name="business_street"]')
                        .inputValue()) !== '',
            );
            check(
                'citizen-permit-helper-resolves-catalog-activity',
                (await page
                    .locator('[name="lines[0][line_of_business_id]"]')
                    .inputValue()) !== '' &&
                    Number(
                        await page
                            .locator(
                                '[name="lines[0][capital_investment_pesos]"]',
                            )
                            .inputValue(),
                    ) > 0,
            );
            check(
                'citizen-permit-helper-leaves-undertaking-manual',
                !(await page
                    .locator('[name="undertaking_accepted"]')
                    .isChecked()),
            );
            await page.getByTestId('clear-permit-helper-values').click();
            check(
                'citizen-permit-helper-clears-only-helper-values',
                (await page
                    .locator('[name="business_barangay"]')
                    .inputValue()) === '' &&
                    (await page
                        .locator('[name="business_street"]')
                        .inputValue()) === '' &&
                    (await page
                        .locator('[name="business_name"]')
                        .inputValue()) ===
                        'Tester-entered business remains unchanged' &&
                    (await page
                        .locator('[name="lines[0][line_of_business_id]"]')
                        .inputValue()) === '' &&
                    (await page
                        .locator('[name="lines[0][capital_investment_pesos]"]')
                        .inputValue()) === '',
            );
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
            application_console_error_or_warning_count: consoleMessages.length,
            failed_internal_request_count: failedRequests.length,
            unexpected_response_count: unexpectedResponses.length,
            unexpected_external_resource_count: externalRequests.length,
            horizontal_overflow_count: checks.filter(
                (item) => item.key.endsWith('-no-overflow') && !item.passed,
            ).length,
        },
        checks,
        console_messages: consoleMessages,
        failed_requests: failedRequests,
        unexpected_responses: unexpectedResponses,
        external_requests: externalRequests,
        artifacts: {
            screenshots: Object.fromEntries(
                fs
                    .readdirSync(screenshotsDirectory)
                    .map((filename) => [
                        filename.replace(/\.png$/, ''),
                        `browser/screenshots/${filename}`,
                    ]),
            ),
        },
    };

    fs.writeFileSync(
        path.join(evidenceDirectory, 'managed-report.json'),
        `${JSON.stringify(report, null, 2)}\n`,
    );
    process.stdout.write(`${JSON.stringify(report.result)}\n`);
} finally {
    await browser.close();
}
