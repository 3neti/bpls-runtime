import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const [manifestPath, baseUrl = 'http://bpls-runtime.test'] = process.argv.slice(2);

if (!manifestPath) {
    console.error('Manifest path is required.');
    process.exit(1);
}

const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const runDirectory = path.dirname(manifestPath);
const screenshotsDirectory = path.join(runDirectory, 'browser', 'screenshots');
fs.mkdirSync(screenshotsDirectory, { recursive: true });
fs.mkdirSync(path.join(runDirectory, 'browser'), { recursive: true });

if (manifest.schema_version !== 'application.lifecycle-evidence.v1') {
    fail('Unsupported manifest schema.');
}

if (manifest.scenario?.key !== 'storyboard_terminal_state_visibility') {
    fail('Unsupported scenario key.');
}

const email = process.env.LIFECYCLE_BROWSER_EMAIL;
const password = process.env.LIFECYCLE_BROWSER_PASSWORD;

if (!email || !password) {
    fail('LIFECYCLE_BROWSER_EMAIL and LIFECYCLE_BROWSER_PASSWORD are required for browser evidence.');
}

const consoleErrors = [];
const failedRequests = [];
const actionLog = [];
const checks = [];
const screenshots = {};

let browser;

try {
    browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
    });
    const page = await context.newPage();

    page.on('console', (message) => {
        if (['error', 'warning'].includes(message.type())) {
            consoleErrors.push({
                type: message.type(),
                text: redact(message.text()),
            });
        }
    });

    page.on('requestfailed', (request) => {
        failedRequests.push({
            url: request.url(),
            method: request.method(),
            failure: request.failure()?.errorText ?? 'unknown',
        });
    });

    await authenticate(page, baseUrl, email, password);
    await inspectList(page, baseUrl);
    await inspectDetail(page, baseUrl);
    await inspectMobile(page, baseUrl);
} catch (error) {
    checks.push(check('browser-exception', 'Browser runner completed without exception', true, false, {
        message: redact(error instanceof Error ? error.message : String(error)),
    }));
} finally {
    await browser?.close().catch(() => {});
}

const passed = checks.every((entry) => entry.passed) && consoleErrors.length === 0 && failedRequests.length === 0;
const report = {
    result: {
        passed,
    },
    checks,
    console_errors: consoleErrors,
    failed_requests: failedRequests,
    artifacts: {
        screenshots,
    },
};

fs.writeFileSync(path.join(runDirectory, 'browser', 'report.json'), `${JSON.stringify(report, null, 2)}\n`);
fs.writeFileSync(path.join(runDirectory, 'browser', 'console-errors.json'), `${JSON.stringify(consoleErrors, null, 2)}\n`);
fs.writeFileSync(path.join(runDirectory, 'browser', 'failed-requests.json'), `${JSON.stringify(failedRequests, null, 2)}\n`);
fs.writeFileSync(path.join(runDirectory, 'browser', 'action-log.jsonl'), `${actionLog.map((entry) => JSON.stringify(entry)).join('\n')}\n`);

if (!passed) {
    process.exit(1);
}

async function authenticate(targetPage, targetBaseUrl, actorEmail, actorPassword) {
    await targetPage.goto(`${targetBaseUrl}/login`, { waitUntil: 'networkidle' });
    actionLog.push(stepLog('login-opened', 'Open login screen'));
    await targetPage.locator('#email').fill(actorEmail);
    await targetPage.locator('#password').fill(actorPassword);
    actionLog.push(stepLog('credentials-entered', 'Enter configured browser credentials', { redacted: true }));
    await targetPage.getByRole('button', { name: /log in/i }).click();
    await targetPage.waitForURL(/dashboard|storyboards/, { timeout: 10000 });
    checks.push(check('authenticated', 'Authenticate as manifest operator', true, true));
}

async function inspectList(targetPage, targetBaseUrl) {
    const listUrl = `${targetBaseUrl}${manifest.resources.list_url}`;
    await targetPage.goto(listUrl, { waitUntil: 'networkidle' });
    await targetPage.getByText(manifest.resources.public_reference.replace('STORYBOARD-', ''), { exact: false }).first().waitFor({ timeout: 10000 }).catch(() => {});
    const titleVisible = await targetPage.getByText(`Lifecycle scenario ${manifest.run_id}`).first().isVisible().catch(() => false);
    checks.push(check('list-title-visible', 'List screen shows prepared storyboard title', true, titleVisible, {
        url: listUrl,
        storyboard_id: manifest.resources.record_id,
    }));
    await screenshot(targetPage, '01-list', 'browser/screenshots/01-list.png');
}

async function inspectDetail(targetPage, targetBaseUrl) {
    const detailUrl = `${targetBaseUrl}${manifest.resources.detail_url}`;
    await targetPage.goto(detailUrl, { waitUntil: 'networkidle' });
    const titleVisible = await hasTitleInputValue(targetPage);
    const frameOneVisible = await targetPage.getByText('Intake confirmation').first().isVisible().catch(() => false);
    const pdfVisible = await targetPage.getByText('pdf').first().isVisible().catch(() => false);
    const completedVisible = await targetPage.getByText('completed').first().isVisible().catch(() => false);
    const videoVisible = await targetPage.getByText('video').first().isVisible().catch(() => false);
    const pendingVisible = await targetPage.getByText('pending').first().isVisible().catch(() => false);

    checks.push(check('detail-title-visible', 'Detail screen shows exact prepared storyboard', true, titleVisible, { url: detailUrl }));
    checks.push(check('detail-frame-visible', 'Detail screen shows prepared frame', true, frameOneVisible));
    checks.push(check('detail-pdf-export-visible', 'Detail screen shows completed PDF export', true, pdfVisible && completedVisible));
    checks.push(check('detail-video-export-visible', 'Detail screen shows pending video export', true, videoVisible && pendingVisible));
    await screenshot(targetPage, '02-detail', 'browser/screenshots/02-detail.png');
}

async function inspectMobile(targetPage, targetBaseUrl) {
    await targetPage.setViewportSize({ width: 390, height: 844 });
    await targetPage.goto(`${targetBaseUrl}${manifest.resources.detail_url}`, { waitUntil: 'networkidle' });
    const titleVisible = await hasTitleInputValue(targetPage);
    const horizontalOverflow = await targetPage.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);

    checks.push(check('mobile-title-visible', 'Mobile detail keeps exact storyboard title visible', true, titleVisible));
    checks.push(check('mobile-no-horizontal-overflow', 'Mobile detail has no horizontal overflow', false, horizontalOverflow));
    await screenshot(targetPage, '03-mobile-detail', 'browser/screenshots/03-mobile-detail.png');
}

async function screenshot(targetPage, label, relativePath) {
    await targetPage.screenshot({
        path: path.join(runDirectory, relativePath),
        fullPage: true,
    });
    screenshots[label] = relativePath;
    actionLog.push(stepLog(`${label}-screenshot`, `Capture ${label} screenshot`, { screenshot: relativePath }));
}

function check(key, action, expected, actual, evidence = {}) {
    return {
        key,
        actor: 'operator',
        action,
        expected,
        actual,
        passed: expected === actual,
        occurred_at: new Date().toISOString(),
        evidence,
    };
}

function stepLog(key, action, evidence = {}) {
    return {
        key,
        actor: 'operator',
        action,
        occurred_at: new Date().toISOString(),
        evidence,
    };
}

async function hasTitleInputValue(targetPage) {
    return await targetPage.locator('#title')
        .inputValue()
        .then((value) => value === `Lifecycle scenario ${manifest.run_id}`)
        .catch(() => false);
}

function fail(message) {
    const directory = manifestPath ? path.dirname(manifestPath) : process.cwd();
    fs.mkdirSync(path.join(directory, 'browser'), { recursive: true });
    fs.writeFileSync(path.join(directory, 'browser', 'report.json'), `${JSON.stringify({
        result: { passed: false },
        error: message,
    }, null, 2)}\n`);
    console.error(message);
    process.exit(1);
}

function redact(value) {
    return String(value)
        .replace(password ?? '', '[redacted]')
        .replace(email ?? '', '[redacted-email]');
}
