import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const [manifestPath, baseUrl = 'http://bpls-runtime.test'] =
    process.argv.slice(2);

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

const supportedScenarios = [
    'new_permit_lifecycle_authority_boundary',
    'manual_collection_receipt_visibility',
    'storyboard_terminal_state_visibility',
    'permit_application_cancelled_visibility',
    'amendment_permit_lifecycle_foundation',
    'permit_application_pending_payment_visibility',
    'renewal_permit_lifecycle_foundation',
    'revenue_code_fee_catalog_visibility',
    'retirement_permit_lifecycle_foundation',
    'transfer_permit_lifecycle_foundation',
];

if (!supportedScenarios.includes(manifest.scenario?.key)) {
    fail('Unsupported scenario key.');
}

const email = process.env.LIFECYCLE_BROWSER_EMAIL;
const password = process.env.LIFECYCLE_BROWSER_PASSWORD;

if (!email || !password) {
    fail(
        'LIFECYCLE_BROWSER_EMAIL and LIFECYCLE_BROWSER_PASSWORD are required for browser evidence.',
    );
}

const consoleErrors = [];
const failedRequests = [];
const actionLog = [];
const checks = [];
const screenshots = {};
const permitArtifactEvidence = {};
const verificationEvidence = {};
const receiptVoidBoundaryEvidence = {};
const documentEvidence = {};
const assessmentEvidence = {};
const onlinePaymentBoundaryEvidence = {};
const reportEvidence = {};
const renewalPolicyEvidence = {};
const amendmentPolicyEvidence = {};
const transferPolicyEvidence = {};
const retirementPolicyEvidence = {};
const feeCatalogEvidence = {};

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

    if (manifest.scenario.key === 'storyboard_terminal_state_visibility') {
        await inspectStoryboardList(page, baseUrl);
        await inspectStoryboardDetail(page, baseUrl);
        await inspectStoryboardMobile(page, baseUrl);
    }

    if (manifest.scenario.key === 'permit_application_cancelled_visibility') {
        await inspectPermitApplicationList(page, baseUrl);
        await inspectPermitApplicationDetail(page, baseUrl);
        await inspectPermitApplicationMobile(page, baseUrl);
    }

    if (manifest.scenario.key === 'revenue_code_fee_catalog_visibility') {
        await inspectRevenueCodeFeeCatalogList(page, baseUrl);
        await inspectRevenueCodeFeeCatalogDetail(page, baseUrl);
        await inspectRevenueCodeFeeCatalogMobile(page, baseUrl);
    }

    if (
        [
            'amendment_permit_lifecycle_foundation',
            'permit_application_pending_payment_visibility',
            'renewal_permit_lifecycle_foundation',
            'retirement_permit_lifecycle_foundation',
            'transfer_permit_lifecycle_foundation',
        ].includes(manifest.scenario.key)
    ) {
        await inspectPendingPaymentPermitApplicationList(page, baseUrl);
        await inspectPendingPaymentPermitApplicationDetail(page, baseUrl);
        await inspectPendingPaymentAssessmentDetail(page, baseUrl);
        await inspectPendingPaymentScheduleDetail(page, baseUrl);
        await inspectPendingPaymentUnpaidEstablishmentsReport(page, baseUrl);
        await inspectPendingPaymentTopTaxDueReport(page, baseUrl);
        await inspectPendingPaymentPermitApplicationMobile(page, baseUrl);
    }

    if (
        [
            'new_permit_lifecycle_authority_boundary',
            'manual_collection_receipt_visibility',
        ].includes(manifest.scenario.key)
    ) {
        await inspectManualReceiptPaymentScheduleQueue(page, baseUrl);
        await inspectManualReceiptQueue(page, baseUrl);
        await inspectManualReceiptDailyCollectionReport(page, baseUrl);
        await inspectManualReceiptRevenueSourceReport(page, baseUrl);
        await inspectManualReceiptPaidEstablishmentsReport(page, baseUrl);
        await inspectManualReceiptPaymentSchedule(page, baseUrl);
        await inspectManualReceiptDetail(page, baseUrl);
        await inspectManualReceiptPermitReleaseBoundary(page, baseUrl);
        await inspectManualReceiptPermitVerificationBoundary(page, baseUrl);
        await inspectManualReceiptPdf(page, baseUrl);
        await inspectManualReceiptMobile(page, baseUrl);
    }
} catch (error) {
    checks.push(
        check(
            'browser-exception',
            'Browser runner completed without exception',
            true,
            false,
            {
                message: redact(
                    error instanceof Error ? error.message : String(error),
                ),
            },
        ),
    );
} finally {
    await browser?.close().catch(() => {});
}

const passed =
    checks.every((entry) => entry.passed) &&
    consoleErrors.length === 0 &&
    failedRequests.length === 0;
const report = {
    result: {
        passed,
    },
    checks,
    console_errors: consoleErrors,
    failed_requests: failedRequests,
    permit_artifact: permitArtifactEvidence,
    verification: verificationEvidence,
    receipt_void_boundary: receiptVoidBoundaryEvidence,
    documents: documentEvidence,
    assessment: assessmentEvidence,
    online_payment_boundary: onlinePaymentBoundaryEvidence,
    reports: reportEvidence,
    renewal_policy: renewalPolicyEvidence,
    amendment_policy: amendmentPolicyEvidence,
    transfer_policy: transferPolicyEvidence,
    retirement_policy: retirementPolicyEvidence,
    fee_catalog: feeCatalogEvidence,
    artifacts: {
        screenshots,
    },
};

fs.writeFileSync(
    path.join(runDirectory, 'browser', 'report.json'),
    `${JSON.stringify(report, null, 2)}\n`,
);
fs.writeFileSync(
    path.join(runDirectory, 'browser', 'console-errors.json'),
    `${JSON.stringify(consoleErrors, null, 2)}\n`,
);
fs.writeFileSync(
    path.join(runDirectory, 'browser', 'failed-requests.json'),
    `${JSON.stringify(failedRequests, null, 2)}\n`,
);
fs.writeFileSync(
    path.join(runDirectory, 'browser', 'action-log.jsonl'),
    `${actionLog.map((entry) => JSON.stringify(entry)).join('\n')}\n`,
);

if (!passed) {
    process.exit(1);
}

async function authenticate(
    targetPage,
    targetBaseUrl,
    actorEmail,
    actorPassword,
) {
    await targetPage.goto(`${targetBaseUrl}/login`, {
        waitUntil: 'networkidle',
    });
    actionLog.push(stepLog('login-opened', 'Open login screen'));
    await targetPage.locator('#email').fill(actorEmail);
    await targetPage.locator('#password').fill(actorPassword);
    actionLog.push(
        stepLog('credentials-entered', 'Enter configured browser credentials', {
            redacted: true,
        }),
    );
    await targetPage.getByRole('button', { name: /log in/i }).click();
    await targetPage.waitForURL(/dashboard|storyboards/, { timeout: 10000 });
    checks.push(
        check('authenticated', 'Authenticate as manifest operator', true, true),
    );
}

async function inspectStoryboardList(targetPage, targetBaseUrl) {
    const listUrl = `${targetBaseUrl}${manifest.resources.list_url}`;
    await targetPage.goto(listUrl, { waitUntil: 'networkidle' });
    await targetPage
        .getByText(
            manifest.resources.public_reference.replace('STORYBOARD-', ''),
            { exact: false },
        )
        .first()
        .waitFor({ timeout: 10000 })
        .catch(() => {});
    const titleVisible = await targetPage
        .getByText(`Lifecycle scenario ${manifest.run_id}`)
        .first()
        .isVisible()
        .catch(() => false);
    checks.push(
        check(
            'list-title-visible',
            'List screen shows prepared storyboard title',
            true,
            titleVisible,
            {
                url: listUrl,
                storyboard_id: manifest.resources.record_id,
            },
        ),
    );
    await screenshot(targetPage, '01-list', 'browser/screenshots/01-list.png');
}

async function inspectStoryboardDetail(targetPage, targetBaseUrl) {
    const detailUrl = `${targetBaseUrl}${manifest.resources.detail_url}`;
    await targetPage.goto(detailUrl, { waitUntil: 'networkidle' });
    const titleVisible = await hasTitleInputValue(targetPage);
    const frameOneVisible = await targetPage
        .getByText('Intake confirmation')
        .first()
        .isVisible()
        .catch(() => false);
    const pdfVisible = await targetPage
        .getByText('pdf')
        .first()
        .isVisible()
        .catch(() => false);
    const completedVisible = await targetPage
        .getByText('completed')
        .first()
        .isVisible()
        .catch(() => false);
    const videoVisible = await targetPage
        .getByText('video')
        .first()
        .isVisible()
        .catch(() => false);
    const pendingVisible = await targetPage
        .getByText('pending')
        .first()
        .isVisible()
        .catch(() => false);

    checks.push(
        check(
            'detail-title-visible',
            'Detail screen shows exact prepared storyboard',
            true,
            titleVisible,
            { url: detailUrl },
        ),
    );
    checks.push(
        check(
            'detail-frame-visible',
            'Detail screen shows prepared frame',
            true,
            frameOneVisible,
        ),
    );
    checks.push(
        check(
            'detail-pdf-export-visible',
            'Detail screen shows completed PDF export',
            true,
            pdfVisible && completedVisible,
        ),
    );
    checks.push(
        check(
            'detail-video-export-visible',
            'Detail screen shows pending video export',
            true,
            videoVisible && pendingVisible,
        ),
    );
    await screenshot(
        targetPage,
        '02-detail',
        'browser/screenshots/02-detail.png',
    );
}

async function inspectStoryboardMobile(targetPage, targetBaseUrl) {
    await targetPage.setViewportSize({ width: 390, height: 844 });
    await targetPage.goto(`${targetBaseUrl}${manifest.resources.detail_url}`, {
        waitUntil: 'networkidle',
    });
    const titleVisible = await hasTitleInputValue(targetPage);
    const horizontalOverflow = await targetPage.evaluate(
        () =>
            document.documentElement.scrollWidth >
            document.documentElement.clientWidth + 1,
    );

    checks.push(
        check(
            'mobile-title-visible',
            'Mobile detail keeps exact storyboard title visible',
            true,
            titleVisible,
        ),
    );
    checks.push(
        check(
            'mobile-no-horizontal-overflow',
            'Mobile detail has no horizontal overflow',
            false,
            horizontalOverflow,
        ),
    );
    await screenshot(
        targetPage,
        '03-mobile-detail',
        'browser/screenshots/03-mobile-detail.png',
    );
}

async function inspectPermitApplicationList(targetPage, targetBaseUrl) {
    const listUrl = `${targetBaseUrl}${manifest.resources.list_url}`;
    await targetPage.goto(listUrl, { waitUntil: 'networkidle' });
    const recordVisible = await targetPage
        .getByText(manifest.resources.public_reference, { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const cancelledVisible = await targetPage
        .getByText('cancelled', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const terminalVisible = await targetPage
        .getByText('Terminal', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const assessVisible = await targetPage
        .getByRole('button', { name: /assess/i })
        .isVisible()
        .catch(() => false);

    checks.push(
        check(
            'list-record-visible',
            'List screen shows prepared permit application',
            true,
            recordVisible,
            {
                url: listUrl,
                permit_application_id: manifest.resources.record_id,
            },
        ),
    );
    checks.push(
        check(
            'list-cancelled-status-visible',
            'List screen shows cancelled status',
            true,
            cancelledVisible,
        ),
    );
    checks.push(
        check(
            'list-terminal-marker-visible',
            'List screen marks record as terminal',
            true,
            terminalVisible,
        ),
    );
    checks.push(
        check(
            'list-assess-action-unavailable',
            'List screen does not offer assessment continuation for terminal record',
            false,
            assessVisible,
        ),
    );
    await screenshot(targetPage, '01-list', 'browser/screenshots/01-list.png');
}

async function inspectPermitApplicationDetail(targetPage, targetBaseUrl) {
    const detailUrl = `${targetBaseUrl}${manifest.resources.detail_url}`;
    await targetPage.goto(detailUrl, { waitUntil: 'networkidle' });
    const recordVisible = await targetPage
        .getByText(manifest.resources.public_reference, { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const cancelledVisible = await targetPage
        .getByText('cancelled', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const terminalEvidenceVisible = await targetPage
        .getByText('Terminal status evidence', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const canContinueNoVisible = await targetPage
        .getByText('No', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const assessVisible = await targetPage
        .getByRole('button', { name: /assess/i })
        .isVisible()
        .catch(() => false);
    const cancelVisible = await targetPage
        .getByRole('button', { name: /cancel/i })
        .isVisible()
        .catch(() => false);

    checks.push(
        check(
            'detail-record-visible',
            'Detail screen shows exact prepared permit application',
            true,
            recordVisible,
            { url: detailUrl },
        ),
    );
    checks.push(
        check(
            'detail-cancelled-status-visible',
            'Detail screen shows cancelled status',
            true,
            cancelledVisible,
        ),
    );
    checks.push(
        check(
            'detail-terminal-evidence-visible',
            'Detail screen shows terminal status evidence',
            true,
            terminalEvidenceVisible,
        ),
    );
    checks.push(
        check(
            'detail-can-continue-false-visible',
            'Detail screen shows continuation is unavailable',
            true,
            canContinueNoVisible,
        ),
    );
    checks.push(
        check(
            'detail-assess-action-unavailable',
            'Detail screen does not offer assessment continuation for terminal record',
            false,
            assessVisible,
        ),
    );
    checks.push(
        check(
            'detail-cancel-action-unavailable',
            'Detail screen does not offer repeat cancellation for terminal record',
            false,
            cancelVisible,
        ),
    );
    await screenshot(
        targetPage,
        '02-detail',
        'browser/screenshots/02-detail.png',
    );
}

async function inspectPermitApplicationMobile(targetPage, targetBaseUrl) {
    await targetPage.setViewportSize({ width: 390, height: 844 });
    await targetPage.goto(`${targetBaseUrl}${manifest.resources.detail_url}`, {
        waitUntil: 'networkidle',
    });
    const recordVisible = await targetPage
        .getByText(manifest.resources.public_reference, { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const terminalEvidenceVisible = await targetPage
        .getByText('Terminal status evidence', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const horizontalOverflow = await targetPage.evaluate(
        () =>
            document.documentElement.scrollWidth >
            document.documentElement.clientWidth + 1,
    );

    checks.push(
        check(
            'mobile-record-visible',
            'Mobile detail keeps exact permit application visible',
            true,
            recordVisible,
        ),
    );
    checks.push(
        check(
            'mobile-terminal-evidence-visible',
            'Mobile detail keeps terminal evidence visible',
            true,
            terminalEvidenceVisible,
        ),
    );
    checks.push(
        check(
            'mobile-no-horizontal-overflow',
            'Mobile detail has no horizontal overflow',
            false,
            horizontalOverflow,
        ),
    );
    await screenshot(
        targetPage,
        '03-mobile-detail',
        'browser/screenshots/03-mobile-detail.png',
    );
}

async function inspectPendingPaymentPermitApplicationList(
    targetPage,
    targetBaseUrl,
) {
    const listUrl = `${targetBaseUrl}${manifest.resources.list_url}`;
    await targetPage.goto(listUrl, { waitUntil: 'networkidle' });
    const recordVisible = await targetPage
        .getByText(manifest.resources.public_reference, { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const pendingPaymentVisible = await targetPage
        .getByText('pending payment', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const paymentActionVisible = await targetPage
        .getByRole('link', { name: /payment/i })
        .first()
        .isVisible()
        .catch(() => false);

    checks.push(
        check(
            'list-record-visible',
            'List screen shows prepared permit application',
            true,
            recordVisible,
            {
                url: listUrl,
                permit_application_id: manifest.resources.record_id,
            },
        ),
    );
    checks.push(
        check(
            'list-pending-payment-status-visible',
            'List screen shows pending payment status',
            true,
            pendingPaymentVisible,
        ),
    );
    checks.push(
        check(
            'list-payment-action-visible',
            'List screen links to the prepared payment schedule',
            true,
            paymentActionVisible,
        ),
    );
    await screenshot(targetPage, '01-list', 'browser/screenshots/01-list.png');
}

async function inspectRevenueCodeFeeCatalogList(targetPage, targetBaseUrl) {
    const listUrl = `${targetBaseUrl}${manifest.resources.list_url}`;
    await targetPage.goto(listUrl, { waitUntil: 'networkidle' });
    const ruleVisible = await targetPage
        .getByText(manifest.resources.fee_rule_code, { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const policyBoundaryVisible = await targetPage
        .getByText('new business initial local business tax exemption', {
            exact: false,
        })
        .first()
        .isVisible()
        .catch(() => false);
    const actionVisible = await targetPage
        .getByRole('link', { name: `View ${manifest.resources.fee_rule_code}` })
        .first()
        .isVisible()
        .catch(() => false);

    checks.push(
        check(
            'fee-catalog-list-rule-visible',
            'Fee catalog list shows exact Revenue Code rule',
            true,
            ruleVisible,
            {
                url: listUrl,
                fee_rule_id: manifest.resources.record_id,
            },
        ),
    );
    checks.push(
        check(
            'fee-catalog-list-policy-boundary-visible',
            'Fee catalog list shows unresolved policy boundary label',
            true,
            policyBoundaryVisible,
        ),
    );
    checks.push(
        check(
            'fee-catalog-list-detail-action-visible',
            'Fee catalog list links to exact rule detail',
            true,
            actionVisible,
        ),
    );
    await screenshot(
        targetPage,
        '01-fee-catalog-list',
        'browser/screenshots/01-fee-catalog-list.png',
    );
}

async function inspectRevenueCodeFeeCatalogDetail(targetPage, targetBaseUrl) {
    const detailUrl = `${targetBaseUrl}${manifest.resources.detail_url}`;
    await targetPage.goto(detailUrl, { waitUntil: 'networkidle' });
    const headingVisible = await targetPage
        .getByRole('heading', { name: manifest.resources.fee_rule_code })
        .first()
        .isVisible()
        .catch(() => false);
    const nameVisible = await targetPage
        .getByText(manifest.resources.fee_rule_name, { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const lineOfBusinessVisible = await targetPage
        .getByText(manifest.resources.line_of_business, { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const legalBasisVisible = await targetPage
        .getByText('Section 2A.02(b)', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const rangeAmountVisible = await targetPage
        .getByText(uiMoneyFromCents(manifest.resources.first_range_amount_cents), {
            exact: false,
        })
        .first()
        .isVisible()
        .catch(() => false);
    const policyBoundaryVisible = await targetPage
        .getByText('new business initial local business tax exemption', {
            exact: false,
        })
        .first()
        .isVisible()
        .catch(() => false);

    checks.push(
        check(
            'fee-catalog-detail-rule-visible',
            'Fee rule detail shows exact Revenue Code rule',
            true,
            headingVisible && nameVisible,
            {
                url: detailUrl,
                fee_rule_id: manifest.resources.record_id,
            },
        ),
    );
    checks.push(
        check(
            'fee-catalog-detail-evidence-visible',
            'Fee rule detail shows line of business, legal basis, and range amount',
            true,
            lineOfBusinessVisible && legalBasisVisible && rangeAmountVisible,
        ),
    );
    checks.push(
        check(
            'fee-catalog-detail-policy-boundary-visible',
            'Fee rule detail shows policy boundary',
            true,
            policyBoundaryVisible,
        ),
    );

    feeCatalogEvidence.fee_rule_code = manifest.resources.fee_rule_code;
    feeCatalogEvidence.detail_visible = headingVisible && nameVisible;
    feeCatalogEvidence.policy_boundary_visible = policyBoundaryVisible;
    feeCatalogEvidence.range_amount_visible = rangeAmountVisible;
    feeCatalogEvidence.legal_basis_visible = legalBasisVisible;

    await screenshot(
        targetPage,
        '02-fee-rule-detail',
        'browser/screenshots/02-fee-rule-detail.png',
    );
}

async function inspectRevenueCodeFeeCatalogMobile(targetPage, targetBaseUrl) {
    const detailUrl = `${targetBaseUrl}${manifest.resources.detail_url}`;
    await targetPage.setViewportSize({ width: 390, height: 844 });
    await targetPage.goto(detailUrl, { waitUntil: 'networkidle' });
    const headingVisible = await targetPage
        .getByRole('heading', { name: manifest.resources.fee_rule_code })
        .first()
        .isVisible()
        .catch(() => false);
    const policyBoundaryVisible = await targetPage
        .getByText('Read-only policy boundary', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const horizontalOverflow = await targetPage.evaluate(
        () =>
            document.documentElement.scrollWidth >
            document.documentElement.clientWidth + 1,
    );

    checks.push(
        check(
            'fee-catalog-mobile-rule-visible',
            'Mobile fee rule detail keeps exact rule visible',
            true,
            headingVisible,
        ),
    );
    checks.push(
        check(
            'fee-catalog-mobile-policy-boundary-visible',
            'Mobile fee rule detail keeps policy boundary visible',
            true,
            policyBoundaryVisible,
        ),
    );
    checks.push(
        check(
            'fee-catalog-mobile-no-horizontal-overflow',
            'Mobile fee rule detail has no horizontal overflow',
            false,
            horizontalOverflow,
        ),
    );
    await screenshot(
        targetPage,
        '03-fee-rule-mobile',
        'browser/screenshots/03-fee-rule-mobile.png',
    );
}

async function inspectPendingPaymentPermitApplicationDetail(
    targetPage,
    targetBaseUrl,
) {
    const detailUrl = `${targetBaseUrl}${manifest.resources.detail_url}`;
    await targetPage.goto(detailUrl, { waitUntil: 'networkidle' });
    const recordVisible = await targetPage
        .getByText(manifest.resources.public_reference, { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const pendingPaymentVisible = await targetPage
        .getByText('pending payment', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const paymentScheduleVisible = await targetPage
        .getByText('Payment schedule', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const schedulePendingVisible = await targetPage
        .getByText('pending', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const paymentLinkVisible = await targetPage
        .getByRole('link', { name: /payment schedule/i })
        .first()
        .isVisible()
        .catch(() => false);
    const amendmentPolicyBoundaryVisible =
        await amendmentPolicyBoundaryIsVisible(targetPage);
    const renewalPolicyBoundaryVisible =
        await renewalPolicyBoundaryIsVisible(targetPage);
    const transferPolicyBoundaryVisible =
        await transferPolicyBoundaryIsVisible(targetPage);
    const retirementPolicyBoundaryVisible =
        await retirementPolicyBoundaryIsVisible(targetPage);

    checks.push(
        check(
            'detail-record-visible',
            'Detail screen shows exact prepared permit application',
            true,
            recordVisible,
            { url: detailUrl },
        ),
    );
    checks.push(
        check(
            'detail-pending-payment-status-visible',
            'Detail screen shows pending payment status',
            true,
            pendingPaymentVisible,
        ),
    );
    checks.push(
        check(
            'detail-payment-schedule-visible',
            'Detail screen shows latest payment schedule evidence',
            true,
            paymentScheduleVisible && schedulePendingVisible,
        ),
    );
    checks.push(
        check(
            'detail-payment-schedule-link-visible',
            'Detail screen links to the exact payment schedule',
            true,
            paymentLinkVisible,
        ),
    );

    if (manifest.resources.application_type === 'renewal') {
        checks.push(
            check(
                'detail-renewal-policy-boundary-visible',
                'Detail screen shows renewal policy boundary',
                true,
                renewalPolicyBoundaryVisible,
            ),
        );
        reportRenewalPolicy(
            renewalPolicyBoundaryVisible ? 'policy_boundary' : 'missing',
            renewalPolicyBoundaryVisible,
        );
    }

    if (manifest.resources.application_type === 'amendment') {
        checks.push(
            check(
                'detail-amendment-policy-boundary-visible',
                'Detail screen shows amendment policy boundary',
                true,
                amendmentPolicyBoundaryVisible,
            ),
        );
        reportAmendmentPolicy(
            amendmentPolicyBoundaryVisible ? 'policy_boundary' : 'missing',
            amendmentPolicyBoundaryVisible,
        );
    }

    if (manifest.resources.application_type === 'transfer') {
        checks.push(
            check(
                'detail-transfer-policy-boundary-visible',
                'Detail screen shows transfer policy boundary',
                true,
                transferPolicyBoundaryVisible,
            ),
        );
        reportTransferPolicy(
            transferPolicyBoundaryVisible ? 'policy_boundary' : 'missing',
            transferPolicyBoundaryVisible,
        );
    }

    if (manifest.resources.application_type === 'retirement') {
        checks.push(
            check(
                'detail-retirement-policy-boundary-visible',
                'Detail screen shows retirement policy boundary',
                true,
                retirementPolicyBoundaryVisible,
            ),
        );
        reportRetirementPolicy(
            retirementPolicyBoundaryVisible ? 'policy_boundary' : 'missing',
            retirementPolicyBoundaryVisible,
        );
    }

    await screenshot(
        targetPage,
        '02-detail',
        'browser/screenshots/02-detail.png',
    );
}

async function inspectPendingPaymentAssessmentDetail(
    targetPage,
    targetBaseUrl,
) {
    const assessmentUrl = `${targetBaseUrl}${manifest.resources.assessment_url}`;
    await targetPage.goto(assessmentUrl, { waitUntil: 'networkidle' });
    const codeVisible = await targetPage
        .getByText(manifest.resources.range_fee_rule_code, { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const rangeVisible = await targetPage
        .getByText('range', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const basisVisible = await targetPage
        .getByText('declared gross sales', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const businessTaxNameVisible = await targetPage
        .getByText(manifest.resources.business_tax_name, { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const businessTaxCategoryVisible = await bodyTextMatches(
        targetPage,
        /\btax\b/i,
    );
    const lineOfBusinessVisible = await targetPage
        .getByText(manifest.resources.business_tax_line_of_business, {
            exact: true,
        })
        .first()
        .isVisible()
        .catch(() => false);
    const basisAmountVisible = await targetPage
        .getByText(
            uiMoneyFromCents(manifest.resources.range_basis_amount_cents),
            {
                exact: false,
            },
        )
        .first()
        .isVisible()
        .catch(() => false);
    const amountVisible = await targetPage
        .getByText(uiMoneyFromCents(manifest.resources.range_amount_cents), {
            exact: false,
        })
        .first()
        .isVisible()
        .catch(() => false);

    checks.push(
        check(
            'assessment-range-code-visible',
            'Assessment page shows exact range fee rule code',
            true,
            codeVisible,
            {
                url: assessmentUrl,
                assessment_id: manifest.resources.assessment_id,
                assessment_line_id: manifest.resources.range_assessment_line_id,
            },
        ),
    );
    checks.push(
        check(
            'assessment-range-basis-visible',
            'Assessment page shows range calculation and gross-sales basis',
            true,
            rangeVisible && basisVisible && basisAmountVisible,
        ),
    );
    checks.push(
        check(
            'assessment-range-amount-visible',
            'Assessment page shows persisted range amount',
            true,
            amountVisible,
        ),
    );
    checks.push(
        check(
            'assessment-business-tax-visible',
            'Assessment page shows gross-sales business tax meaning',
            true,
            businessTaxNameVisible &&
                businessTaxCategoryVisible &&
                lineOfBusinessVisible,
            {
                name_visible: businessTaxNameVisible,
                category_visible: businessTaxCategoryVisible,
                line_of_business_visible: lineOfBusinessVisible,
            },
        ),
    );
    reportAssessmentRange(
        manifest.resources.range_fee_rule_code,
        manifest.resources.range_calculation_type,
        manifest.resources.range_basis,
        manifest.resources.range_basis_amount_cents,
        manifest.resources.range_amount_cents,
    );
    reportBusinessTaxAssessment(
        manifest.resources.business_tax_code,
        manifest.resources.business_tax_name,
        manifest.resources.business_tax_category,
        manifest.resources.business_tax_line_of_business,
        manifest.resources.business_tax_basis,
        manifest.resources.business_tax_declared_gross_sales_cents,
        manifest.resources.business_tax_amount_cents,
    );
    await screenshot(
        targetPage,
        '03-assessment',
        'browser/screenshots/03-assessment.png',
    );
}

async function inspectPendingPaymentScheduleDetail(targetPage, targetBaseUrl) {
    const scheduleUrl = `${targetBaseUrl}${manifest.resources.payment_schedule_url}`;
    await targetPage.goto(scheduleUrl, { waitUntil: 'networkidle' });
    const recordVisible = await targetPage
        .getByText(manifest.resources.public_reference, { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const pendingVisible = await targetPage
        .getByText('pending', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const amountVisible = await targetPage
        .getByText(
            uiMoneyFromCents(manifest.resources.assessment_total_amount_cents),
            {
                exact: false,
            },
        )
        .first()
        .isVisible()
        .catch(() => false);

    checks.push(
        check(
            'payment-schedule-record-visible',
            'Payment schedule detail shows exact permit application',
            true,
            recordVisible,
            { url: scheduleUrl },
        ),
    );
    checks.push(
        check(
            'payment-schedule-pending-visible',
            'Payment schedule detail shows pending collection status',
            true,
            pendingVisible,
        ),
    );
    checks.push(
        check(
            'payment-schedule-amount-visible',
            'Payment schedule detail shows computed amount due',
            true,
            amountVisible,
            {
                total_amount_cents:
                    manifest.resources.assessment_total_amount_cents,
            },
        ),
    );
    await screenshot(
        targetPage,
        '04-payment-schedule',
        'browser/screenshots/04-payment-schedule.png',
    );
}

async function inspectPendingPaymentUnpaidEstablishmentsReport(
    targetPage,
    targetBaseUrl,
) {
    const reportUrl = `${targetBaseUrl}${manifest.resources.unpaid_establishments_report_url}`;
    await targetPage.goto(reportUrl, { waitUntil: 'networkidle' });
    const applicationVisible = await targetPage
        .getByText(manifest.resources.application_number, { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const businessVisible = await targetPage
        .getByText(manifest.resources.unpaid_establishment_business_name, {
            exact: false,
        })
        .first()
        .isVisible()
        .catch(() => false);
    const scopeVisible = await targetPage
        .getByText(
            'Pending and partially paid permit payment schedules for the selected application year.',
            {
                exact: true,
            },
        )
        .first()
        .isVisible()
        .catch(() => false);
    const policyVisible = await targetPage
        .getByText('does not calculate legal delinquency', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const csvExportVisible = await targetPage
        .getByRole('link', { name: /export csv/i })
        .first()
        .isVisible()
        .catch(() => false);

    checks.push(
        check(
            'unpaid-establishments-report-application-visible',
            'Unpaid establishments report shows exact pending permit application',
            true,
            applicationVisible,
            {
                url: reportUrl,
                application_number: manifest.resources.application_number,
            },
        ),
    );
    checks.push(
        check(
            'unpaid-establishments-report-business-visible',
            'Unpaid establishments report shows exact business name',
            true,
            businessVisible,
        ),
    );
    checks.push(
        check(
            'unpaid-establishments-report-scope-visible',
            'Unpaid establishments report keeps delinquency policy boundary visible',
            true,
            scopeVisible && policyVisible,
        ),
    );
    checks.push(
        check(
            'unpaid-establishments-report-csv-visible',
            'Unpaid establishments report offers CSV export',
            true,
            csvExportVisible,
        ),
    );
    reportUnpaidEstablishments(
        manifest.resources.application_number,
        manifest.resources.unpaid_establishment_business_name,
        applicationVisible,
        csvExportVisible,
    );
    await screenshot(
        targetPage,
        '05-unpaid-establishments-report',
        'browser/screenshots/05-unpaid-establishments-report.png',
    );
}

async function inspectPendingPaymentTopTaxDueReport(targetPage, targetBaseUrl) {
    const reportUrl = `${targetBaseUrl}${manifest.resources.top_tax_due_report_url}`;
    await targetPage.goto(reportUrl, { waitUntil: 'networkidle' });
    const applicationVisible = await targetPage
        .getByText(manifest.resources.application_number, { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const businessVisible = await targetPage
        .getByText(manifest.resources.top_tax_due_business_name, {
            exact: false,
        })
        .first()
        .isVisible()
        .catch(() => false);
    const taxAmountVisible = await targetPage
        .getByText(uiMoneyFromCents(manifest.resources.top_tax_due_cents), {
            exact: false,
        })
        .first()
        .isVisible()
        .catch(() => false);
    const scopeVisible = await targetPage
        .getByText(
            'Top establishments by persisted tax assessment lines for the selected application year.',
            {
                exact: true,
            },
        )
        .first()
        .isVisible()
        .catch(() => false);
    const policyVisible = await targetPage
        .getByText('does not calculate legal delinquency', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const csvExportVisible = await targetPage
        .getByRole('link', { name: /export csv/i })
        .first()
        .isVisible()
        .catch(() => false);

    checks.push(
        check(
            'top-tax-due-report-application-visible',
            'Top tax due report shows exact pending permit application',
            true,
            applicationVisible,
            {
                url: reportUrl,
                application_number: manifest.resources.application_number,
            },
        ),
    );
    checks.push(
        check(
            'top-tax-due-report-business-visible',
            'Top tax due report shows exact business name',
            true,
            businessVisible,
        ),
    );
    checks.push(
        check(
            'top-tax-due-report-tax-visible',
            'Top tax due report shows exact persisted assessment tax-line total',
            true,
            taxAmountVisible,
            {
                tax_due_cents: manifest.resources.top_tax_due_cents,
            },
        ),
    );
    checks.push(
        check(
            'top-tax-due-report-scope-visible',
            'Top tax due report keeps tax-due policy boundary visible',
            true,
            scopeVisible && policyVisible,
        ),
    );
    checks.push(
        check(
            'top-tax-due-report-csv-visible',
            'Top tax due report offers CSV export',
            true,
            csvExportVisible,
        ),
    );
    reportTopTaxDue(
        manifest.resources.application_number,
        manifest.resources.top_tax_due_business_name,
        manifest.resources.top_tax_due_cents,
        applicationVisible,
        csvExportVisible,
    );
    await screenshot(
        targetPage,
        '06-top-tax-due-report',
        'browser/screenshots/06-top-tax-due-report.png',
    );
}

async function inspectPendingPaymentPermitApplicationMobile(
    targetPage,
    targetBaseUrl,
) {
    await targetPage.setViewportSize({ width: 390, height: 844 });
    await targetPage.goto(`${targetBaseUrl}${manifest.resources.detail_url}`, {
        waitUntil: 'networkidle',
    });
    const recordVisible = await targetPage
        .getByText(manifest.resources.public_reference, { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const pendingPaymentVisible = await targetPage
        .getByText('pending payment', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const amendmentPolicyBoundaryVisible =
        await amendmentPolicyBoundaryIsVisible(targetPage);
    const renewalPolicyBoundaryVisible =
        await renewalPolicyBoundaryIsVisible(targetPage);
    const transferPolicyBoundaryVisible =
        await transferPolicyBoundaryIsVisible(targetPage);
    const retirementPolicyBoundaryVisible =
        await retirementPolicyBoundaryIsVisible(targetPage);
    const horizontalOverflow = await targetPage.evaluate(
        () =>
            document.documentElement.scrollWidth >
            document.documentElement.clientWidth + 1,
    );

    checks.push(
        check(
            'mobile-record-visible',
            'Mobile detail keeps exact permit application visible',
            true,
            recordVisible,
        ),
    );
    checks.push(
        check(
            'mobile-pending-payment-visible',
            'Mobile detail keeps pending payment state visible',
            true,
            pendingPaymentVisible,
        ),
    );

    if (manifest.resources.application_type === 'renewal') {
        checks.push(
            check(
                'mobile-renewal-policy-boundary-visible',
                'Mobile detail keeps renewal policy boundary visible',
                true,
                renewalPolicyBoundaryVisible,
            ),
        );
    }

    if (manifest.resources.application_type === 'amendment') {
        checks.push(
            check(
                'mobile-amendment-policy-boundary-visible',
                'Mobile detail keeps amendment policy boundary visible',
                true,
                amendmentPolicyBoundaryVisible,
            ),
        );
    }

    if (manifest.resources.application_type === 'transfer') {
        checks.push(
            check(
                'mobile-transfer-policy-boundary-visible',
                'Mobile detail keeps transfer policy boundary visible',
                true,
                transferPolicyBoundaryVisible,
            ),
        );
    }

    if (manifest.resources.application_type === 'retirement') {
        checks.push(
            check(
                'mobile-retirement-policy-boundary-visible',
                'Mobile detail keeps retirement policy boundary visible',
                true,
                retirementPolicyBoundaryVisible,
            ),
        );
    }

    checks.push(
        check(
            'mobile-no-horizontal-overflow',
            'Mobile detail has no horizontal overflow',
            false,
            horizontalOverflow,
        ),
    );
    await screenshot(
        targetPage,
        '07-mobile-detail',
        'browser/screenshots/07-mobile-detail.png',
    );
}

async function inspectManualReceiptPaymentSchedule(targetPage, targetBaseUrl) {
    const scheduleUrl = `${targetBaseUrl}${manifest.resources.payment_schedule_url}`;
    await targetPage.goto(scheduleUrl, { waitUntil: 'networkidle' });
    const applicationVisible = await targetPage
        .getByText(manifest.resources.application_number, { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const paidVisible = await bodyTextMatches(targetPage, /\bpaid\b/i);
    const receiptedVisible = await targetPage
        .getByText('receipted', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const receiptNumberVisible = await targetPage
        .getByText(manifest.resources.public_reference, { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const balancePaidVisible = await hasZeroCurrencyAmount(targetPage);
    const onlinePaymentBoundaryVisible =
        await onlinePaymentBoundaryIsVisible(targetPage);

    checks.push(
        check(
            'schedule-application-visible',
            'Payment schedule shows exact permit application',
            true,
            applicationVisible,
            { url: scheduleUrl },
        ),
    );
    checks.push(
        check(
            'schedule-paid-visible',
            'Payment schedule shows paid status and zero balance',
            true,
            paidVisible && balancePaidVisible,
        ),
    );
    checks.push(
        check(
            'schedule-receipted-visible',
            'Collection history shows receipted status',
            true,
            receiptedVisible,
        ),
    );
    checks.push(
        check(
            'schedule-receipt-number-visible',
            'Collection history links the exact manual receipt',
            true,
            receiptNumberVisible,
        ),
    );
    checks.push(
        check(
            'schedule-online-payment-boundary-visible',
            'Payment schedule shows online payment and reconciliation boundary',
            true,
            onlinePaymentBoundaryVisible,
        ),
    );
    reportOnlinePaymentBoundary(
        onlinePaymentBoundaryVisible ? 'blocked' : 'missing',
        onlinePaymentBoundaryVisible,
    );
    await screenshot(
        targetPage,
        '01-payment-schedule',
        'browser/screenshots/01-payment-schedule.png',
    );
}

async function inspectManualReceiptPaymentScheduleQueue(
    targetPage,
    targetBaseUrl,
) {
    const queueUrl = `${targetBaseUrl}${manifest.resources.payment_schedule_queue_url}`;
    await targetPage.goto(queueUrl, { waitUntil: 'networkidle' });
    const applicationVisible = await targetPage
        .getByText(manifest.resources.application_number, { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const paidVisible = await bodyTextMatches(targetPage, /\bpaid\b/i);
    const viewVisible = await targetPage
        .getByRole('link', { name: /view/i })
        .first()
        .isVisible()
        .catch(() => false);

    checks.push(
        check(
            'payment-schedule-queue-application-visible',
            'Payment schedule queue shows exact prepared application',
            true,
            applicationVisible,
            {
                url: queueUrl,
                payment_schedule_id: manifest.resources.payment_schedule_id,
            },
        ),
    );
    checks.push(
        check(
            'payment-schedule-queue-paid-visible',
            'Payment schedule queue shows paid status',
            true,
            paidVisible,
        ),
    );
    checks.push(
        check(
            'payment-schedule-queue-detail-link-visible',
            'Payment schedule queue offers detail navigation',
            true,
            viewVisible,
        ),
    );
    await screenshot(
        targetPage,
        '01-payment-schedule-queue',
        'browser/screenshots/01-payment-schedule-queue.png',
    );
}

async function inspectManualReceiptQueue(targetPage, targetBaseUrl) {
    const queueUrl = `${targetBaseUrl}${manifest.resources.receipt_queue_url}`;
    await targetPage.goto(queueUrl, { waitUntil: 'networkidle' });
    const receiptVisible = await targetPage
        .getByText(manifest.resources.public_reference, { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const applicationVisible = await targetPage
        .getByText(manifest.resources.application_number, { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const issuedVisible = await bodyTextMatches(targetPage, /\bissued\b/i);
    const receiptedVisible = await bodyTextMatches(
        targetPage,
        /\breceipted\b/i,
    );
    const viewVisible = await targetPage
        .getByRole('link', { name: /view/i })
        .first()
        .isVisible()
        .catch(() => false);

    checks.push(
        check(
            'receipt-queue-receipt-visible',
            'Receipt queue shows exact manual receipt',
            true,
            receiptVisible,
            {
                url: queueUrl,
                receipt_id: manifest.resources.record_id,
            },
        ),
    );
    checks.push(
        check(
            'receipt-queue-application-visible',
            'Receipt queue shows exact permit application',
            true,
            applicationVisible,
        ),
    );
    checks.push(
        check(
            'receipt-queue-issued-receipted-visible',
            'Receipt queue shows issued receipt and receipted collection state',
            true,
            issuedVisible && receiptedVisible,
        ),
    );
    checks.push(
        check(
            'receipt-queue-detail-link-visible',
            'Receipt queue offers detail navigation',
            true,
            viewVisible,
        ),
    );
    await screenshot(
        targetPage,
        '02-receipt-queue',
        'browser/screenshots/02-receipt-queue.png',
    );
}

async function inspectManualReceiptDailyCollectionReport(
    targetPage,
    targetBaseUrl,
) {
    const reportUrl = `${targetBaseUrl}${manifest.resources.daily_collection_report_url}`;
    await targetPage.goto(reportUrl, { waitUntil: 'networkidle' });
    const receiptVisible = await targetPage
        .getByText(manifest.resources.public_reference, { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const applicationVisible = await targetPage
        .getByText(manifest.resources.application_number, { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const amountVisible = await targetPage
        .getByText(uiMoneyFromCents(manifest.resources.assessment_total_amount_cents), {
            exact: false,
        })
        .first()
        .isVisible()
        .catch(() => false);
    const scopeVisible = await targetPage
        .getByText('Receipted permit collections with issued receipts only.', {
            exact: true,
        })
        .first()
        .isVisible()
        .catch(() => false);
    const csvExportVisible = await targetPage
        .getByRole('link', { name: /export csv/i })
        .first()
        .isVisible()
        .catch(() => false);

    checks.push(
        check(
            'daily-collection-report-receipt-visible',
            'Daily collection report shows exact manual receipt',
            true,
            receiptVisible,
            {
                url: reportUrl,
                receipt_id: manifest.resources.record_id,
            },
        ),
    );
    checks.push(
        check(
            'daily-collection-report-application-visible',
            'Daily collection report shows exact permit application',
            true,
            applicationVisible,
        ),
    );
    checks.push(
        check(
            'daily-collection-report-amount-visible',
            'Daily collection report shows canonical collection amount',
            true,
            amountVisible,
        ),
    );
    checks.push(
        check(
            'daily-collection-report-scope-visible',
            'Daily collection report keeps reporting scope visible',
            true,
            scopeVisible,
        ),
    );
    checks.push(
        check(
            'daily-collection-report-csv-visible',
            'Daily collection report offers CSV export',
            true,
            csvExportVisible,
        ),
    );
    reportDailyCollection(
        manifest.resources.public_reference,
        manifest.resources.assessment_total_amount_cents,
        scopeVisible,
        csvExportVisible,
    );
    await screenshot(
        targetPage,
        '02-daily-collection-report',
        'browser/screenshots/02-daily-collection-report.png',
    );
}

async function inspectManualReceiptRevenueSourceReport(
    targetPage,
    targetBaseUrl,
) {
    const reportUrl = `${targetBaseUrl}${manifest.resources.revenue_source_report_url}`;
    await targetPage.goto(reportUrl, { waitUntil: 'networkidle' });
    const sourceCodeVisible = await targetPage
        .getByText(manifest.resources.revenue_source_code, { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const scopeVisible = await targetPage
        .getByText(
            'Receipted permit collection allocations with issued receipts only.',
            {
                exact: true,
            },
        )
        .first()
        .isVisible()
        .catch(() => false);
    const policyVisible = await targetPage
        .getByText('Official revenue account codes', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const csvExportVisible = await targetPage
        .getByRole('link', { name: /export csv/i })
        .first()
        .isVisible()
        .catch(() => false);

    checks.push(
        check(
            'revenue-source-report-source-visible',
            'Revenue source report shows scenario allocation source',
            true,
            sourceCodeVisible,
            {
                url: reportUrl,
                source_code: manifest.resources.revenue_source_code,
            },
        ),
    );
    checks.push(
        check(
            'revenue-source-report-scope-visible',
            'Revenue source report keeps report scope visible',
            true,
            scopeVisible && policyVisible,
        ),
    );
    checks.push(
        check(
            'revenue-source-report-csv-visible',
            'Revenue source report offers CSV export',
            true,
            csvExportVisible,
        ),
    );
    reportRevenueSource(
        manifest.resources.revenue_source_code,
        sourceCodeVisible,
        csvExportVisible,
    );
    await screenshot(
        targetPage,
        '02-revenue-source-report',
        'browser/screenshots/02-revenue-source-report.png',
    );
}

async function inspectManualReceiptPaidEstablishmentsReport(
    targetPage,
    targetBaseUrl,
) {
    const reportUrl = `${targetBaseUrl}${manifest.resources.paid_establishments_report_url}`;
    await targetPage.goto(reportUrl, { waitUntil: 'networkidle' });
    const applicationVisible = await targetPage
        .getByText(manifest.resources.application_number, { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const businessVisible = await targetPage
        .getByText(manifest.resources.paid_establishment_business_name, {
            exact: false,
        })
        .first()
        .isVisible()
        .catch(() => false);
    const scopeVisible = await targetPage
        .getByText(
            'Paid permit payment schedules for the selected application year.',
            {
                exact: true,
            },
        )
        .first()
        .isVisible()
        .catch(() => false);
    const policyVisible = await targetPage
        .getByText('does not imply permit issuance', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const csvExportVisible = await targetPage
        .getByRole('link', { name: /export csv/i })
        .first()
        .isVisible()
        .catch(() => false);

    checks.push(
        check(
            'paid-establishments-report-application-visible',
            'Paid establishments report shows exact paid permit application',
            true,
            applicationVisible,
            {
                url: reportUrl,
                application_number: manifest.resources.application_number,
            },
        ),
    );
    checks.push(
        check(
            'paid-establishments-report-business-visible',
            'Paid establishments report shows exact business name',
            true,
            businessVisible,
        ),
    );
    checks.push(
        check(
            'paid-establishments-report-scope-visible',
            'Paid establishments report keeps authority-boundary scope visible',
            true,
            scopeVisible && policyVisible,
        ),
    );
    checks.push(
        check(
            'paid-establishments-report-csv-visible',
            'Paid establishments report offers CSV export',
            true,
            csvExportVisible,
        ),
    );
    reportPaidEstablishments(
        manifest.resources.application_number,
        manifest.resources.paid_establishment_business_name,
        applicationVisible,
        csvExportVisible,
    );
    await screenshot(
        targetPage,
        '02-paid-establishments-report',
        'browser/screenshots/02-paid-establishments-report.png',
    );
}

async function inspectManualReceiptDetail(targetPage, targetBaseUrl) {
    const receiptUrl = `${targetBaseUrl}${manifest.resources.receipt_url}`;
    await targetPage.goto(receiptUrl, { waitUntil: 'networkidle' });
    const receiptVisible = await targetPage
        .getByText(manifest.resources.public_reference, { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const applicationVisible = await targetPage
        .getByText(manifest.resources.application_number, { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const issuedVisible = await targetPage
        .getByText('issued', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const manualVisible = await targetPage
        .getByText('manual numbering', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const policyGapVisible = await targetPage
        .getByText(
            'Automatic receipt numbering authority remains unresolved.',
            { exact: true },
        )
        .first()
        .isVisible()
        .catch(() => false);
    const voidBoundaryReference =
        manifest.resources.receipt_void_boundary_reference;
    const voidBoundaryVisible = await targetPage
        .getByText('Void / reversal boundary', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const voidReferenceVisible = await targetPage
        .getByText(voidBoundaryReference, { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const voidBlockedVisible = await targetPage
        .getByText('blocked', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const canVoidNoVisible =
        (await targetPage
            .getByText('Can void', { exact: true })
            .first()
            .isVisible()
            .catch(() => false)) &&
        (await targetPage
            .getByText('No', { exact: true })
            .first()
            .isVisible()
            .catch(() => false));
    const voidPolicyVisible = await targetPage
        .getByText(
            'Receipt void, reversal, receipt-number reuse, and reconciliation policy remain unresolved',
            { exact: false },
        )
        .first()
        .isVisible()
        .catch(() => false);
    const voidButton = targetPage.getByRole('button', {
        name: /void unavailable/i,
    });
    const voidButtonVisible = await voidButton.isVisible().catch(() => false);
    const voidButtonDisabled = await voidButton.isDisabled().catch(() => false);

    checks.push(
        check(
            'receipt-number-visible',
            'Receipt detail shows exact manual receipt number',
            true,
            receiptVisible,
            { url: receiptUrl },
        ),
    );
    checks.push(
        check(
            'receipt-application-visible',
            'Receipt detail shows exact permit application',
            true,
            applicationVisible,
        ),
    );
    checks.push(
        check(
            'receipt-issued-manual-visible',
            'Receipt detail shows issued status and manual numbering',
            true,
            issuedVisible && manualVisible,
        ),
    );
    checks.push(
        check(
            'receipt-policy-gap-visible',
            'Receipt detail keeps unresolved numbering policy visible',
            true,
            policyGapVisible,
        ),
    );
    checks.push(
        check(
            'receipt-void-boundary-visible',
            'Receipt detail shows unresolved void boundary',
            true,
            voidBoundaryVisible,
            { reference: voidBoundaryReference },
        ),
    );
    checks.push(
        check(
            'receipt-void-reference-visible',
            'Receipt detail shows exact void boundary reference',
            true,
            voidReferenceVisible,
        ),
    );
    checks.push(
        check(
            'receipt-void-blocked-visible',
            'Receipt detail shows voiding is blocked',
            true,
            voidBlockedVisible && canVoidNoVisible && voidPolicyVisible,
        ),
    );
    checks.push(
        check(
            'receipt-void-action-disabled',
            'Receipt detail keeps void action disabled',
            true,
            voidButtonVisible && voidButtonDisabled,
        ),
    );
    reportReceiptVoidBoundary(
        voidBoundaryReference,
        'blocked',
        false,
        'issued',
        'receipted',
    );
    await screenshot(
        targetPage,
        '02-receipt-detail',
        'browser/screenshots/02-receipt-detail.png',
    );
}

async function inspectManualReceiptPermitReleaseBoundary(
    targetPage,
    targetBaseUrl,
) {
    const permitUrl = `${targetBaseUrl}${manifest.resources.permit_application_url}`;
    await targetPage.goto(permitUrl, { waitUntil: 'networkidle' });
    const applicationVisible = await targetPage
        .getByText(manifest.resources.application_number, { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const clearanceChecklistVisible = await targetPage
        .getByText('Clearance checklist', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const clearanceSummaryVisible = await targetPage
        .getByText('3 / 3 complete', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const bploClearanceVisible = await targetPage
        .getByText('BPLO review', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const treasuryClearanceVisible = await targetPage
        .getByText('Treasury payment evidence', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const releaseAuthorityClearanceVisible = await targetPage
        .getByText('Release authority', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const authorityReadyVisible = await targetPage
        .getByText('Ready', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const receiptIssuedVisible = await targetPage
        .getByText('Receipt issued', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const canReleaseNoVisible =
        (await targetPage
            .getByText('Can release', { exact: true })
            .first()
            .isVisible()
            .catch(() => false)) &&
        (await targetPage
            .getByText('No', { exact: true })
            .first()
            .isVisible()
            .catch(() => false));
    const authorityReviewReasonVisible = await targetPage
        .getByText(
            'Payment, receipt, clearance, and permit artifact evidence may be ready for authority review',
            { exact: false },
        )
        .first()
        .isVisible()
        .catch(() => false);
    const boundaryVisible = await targetPage
        .getByText('Permit release boundary', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const unavailableVisible = await targetPage
        .getByText('Release unavailable', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const reasonVisible = await targetPage
        .getByText(
            'Clearance completion, permit issuance authority, signatories, QR verification, and legacy Released status semantics remain unresolved.',
            { exact: true },
        )
        .first()
        .isVisible()
        .catch(() => false);
    const pendingPaymentVisible = await targetPage
        .getByText('pending payment', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const authorityBoundaryVisible = await targetPage
        .getByText('Authority boundary', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const softwareKnowsVisible = await targetPage
        .getByText('Software knows', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const humanAuthorityDecidesVisible = await targetPage
        .getByText('Human authority decides', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const softwareRecordsVisible = await targetPage
        .getByText('Software records', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const artifactStatementVisible = await targetPage
        .getByText('Generated permit artifacts support authority review', {
            exact: false,
        })
        .first()
        .isVisible()
        .catch(() => false);
    const permitArtifactPanelVisible = await targetPage
        .getByText('Permit artifact', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const permitArtifactLabelVisible = await targetPage
        .getByText("Mayor's Permit Artifact", { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const permitArtifactStatusVisible = await targetPage
        .getByText('generated artifact available', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const permitArtifactLegalEffectVisible = await targetPage
        .getByText('Not legally effective', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const permitArtifactReferenceVisible = await targetPage
        .getByText(manifest.resources.permit_verification_reference, {
            exact: false,
        })
        .first()
        .isVisible()
        .catch(() => false);
    const permitArtifactOpenVisible = await targetPage
        .getByRole('link', { name: /open artifact/i })
        .first()
        .isVisible()
        .catch(() => false);

    checks.push(
        check(
            'permit-application-visible',
            'Permit detail shows exact permit application',
            true,
            applicationVisible,
            { url: permitUrl },
        ),
    );
    checks.push(
        check(
            'permit-clearance-checklist-visible',
            'Permit detail shows clearance checklist',
            true,
            clearanceChecklistVisible,
        ),
    );
    checks.push(
        check(
            'permit-clearance-summary-visible',
            'Permit detail shows all clearances complete',
            true,
            clearanceSummaryVisible,
        ),
    );
    checks.push(
        check(
            'permit-bplo-clearance-visible',
            'Permit detail shows BPLO clearance evidence',
            true,
            bploClearanceVisible,
        ),
    );
    checks.push(
        check(
            'permit-treasury-clearance-visible',
            'Permit detail shows Treasury clearance evidence',
            true,
            treasuryClearanceVisible,
        ),
    );
    checks.push(
        check(
            'permit-release-authority-clearance-visible',
            'Permit detail shows release authority boundary item',
            true,
            releaseAuthorityClearanceVisible,
        ),
    );
    checks.push(
        check(
            'permit-authority-review-ready-visible',
            'Permit detail shows authority review readiness',
            true,
            authorityReadyVisible,
        ),
    );
    checks.push(
        check(
            'permit-receipt-issued-prerequisite-visible',
            'Permit detail shows receipt issued prerequisite',
            true,
            receiptIssuedVisible,
        ),
    );
    checks.push(
        check(
            'permit-can-release-false-visible',
            'Permit detail shows release is still unavailable',
            true,
            canReleaseNoVisible,
        ),
    );
    checks.push(
        check(
            'permit-authority-review-reason-visible',
            'Permit detail explains authority review boundary',
            true,
            authorityReviewReasonVisible,
        ),
    );
    checks.push(
        check(
            'permit-release-boundary-visible',
            'Permit detail shows release boundary',
            true,
            boundaryVisible,
        ),
    );
    checks.push(
        check(
            'permit-release-unavailable-visible',
            'Permit detail shows release unavailable action',
            true,
            unavailableVisible,
        ),
    );
    checks.push(
        check(
            'permit-release-boundary-reason-visible',
            'Permit detail shows unresolved release policy reason',
            true,
            reasonVisible,
        ),
    );
    checks.push(
        check(
            'permit-pending-payment-status-visible',
            'Permit detail keeps pending payment status after blocked release attempt',
            true,
            pendingPaymentVisible,
        ),
    );
    checks.push(
        check(
            'permit-authority-boundary-visible',
            'Permit detail shows the authority boundary',
            true,
            authorityBoundaryVisible,
        ),
    );
    checks.push(
        check(
            'permit-authority-boundary-software-knows-visible',
            'Permit detail separates facts software can know',
            true,
            softwareKnowsVisible,
        ),
    );
    checks.push(
        check(
            'permit-authority-boundary-human-decides-visible',
            'Permit detail separates human authority decisions',
            true,
            humanAuthorityDecidesVisible,
        ),
    );
    checks.push(
        check(
            'permit-authority-boundary-software-records-visible',
            'Permit detail separates facts software records after authority decision',
            true,
            softwareRecordsVisible,
        ),
    );
    checks.push(
        check(
            'permit-authority-boundary-artifact-statement-visible',
            'Permit detail states artifact generation does not issue or release a permit',
            true,
            artifactStatementVisible,
        ),
    );
    checks.push(
        check(
            'permit-artifact-panel-visible',
            'Permit detail shows the permit artifact panel',
            true,
            permitArtifactPanelVisible,
        ),
    );
    checks.push(
        check(
            'permit-artifact-label-visible',
            'Permit artifact panel shows the generated artifact label',
            true,
            permitArtifactLabelVisible,
        ),
    );
    checks.push(
        check(
            'permit-artifact-status-visible',
            'Permit artifact panel shows generated artifact status',
            true,
            permitArtifactStatusVisible,
        ),
    );
    checks.push(
        check(
            'permit-artifact-not-legally-effective-visible',
            'Permit artifact panel states artifact is not legally effective',
            true,
            permitArtifactLegalEffectVisible,
        ),
    );
    checks.push(
        check(
            'permit-artifact-reference-visible',
            'Permit artifact panel shows exact verification reference',
            true,
            permitArtifactReferenceVisible,
            {
                reference: manifest.resources.permit_verification_reference,
            },
        ),
    );
    checks.push(
        check(
            'permit-artifact-open-affordance-visible',
            'Permit artifact panel exposes the permit artifact PDF affordance',
            true,
            permitArtifactOpenVisible,
            {
                url: `${targetBaseUrl}${manifest.resources.permit_pdf_url}`,
            },
        ),
    );
    reportPermitArtifact(
        manifest.resources.permit_pdf_url,
        manifest.resources.permit_verification_reference,
        permitArtifactPanelVisible,
        permitArtifactLegalEffectVisible,
        permitArtifactOpenVisible,
    );
    await screenshot(
        targetPage,
        '03-permit-release-boundary',
        'browser/screenshots/03-permit-release-boundary.png',
    );
}

async function inspectManualReceiptPermitVerificationBoundary(
    targetPage,
    targetBaseUrl,
) {
    const permitUrl = `${targetBaseUrl}${manifest.resources.permit_application_url}`;
    await targetPage.goto(permitUrl, { waitUntil: 'networkidle' });
    const reference = manifest.resources.permit_verification_reference;
    const verificationUrl = `${targetBaseUrl}${manifest.resources.permit_verification_url}`;
    const verificationSectionVisible = await targetPage
        .getByText('Verification boundary', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const referenceVisible = await targetPage
        .getByText(reference, { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const artifactOnlyVisible = await targetPage
        .getByText(/artifact only/i)
        .first()
        .isVisible()
        .catch(() => false);
    const canVerifyReleaseNoVisible =
        (await targetPage
            .getByText('Can verify release', { exact: true })
            .first()
            .isVisible()
            .catch(() => false)) &&
        (await targetPage
            .getByText('No', { exact: true })
            .first()
            .isVisible()
            .catch(() => false));
    const publicRouteVisible = await targetPage
        .getByText(manifest.resources.permit_verification_url, { exact: false })
        .first()
        .isVisible()
        .catch(() => false);

    checks.push(
        check(
            'permit-verification-section-visible',
            'Permit detail shows verification boundary section',
            true,
            verificationSectionVisible,
            { url: permitUrl },
        ),
    );
    checks.push(
        check(
            'permit-verification-reference-visible',
            'Permit detail shows exact verification reference',
            true,
            referenceVisible,
            { reference },
        ),
    );
    checks.push(
        check(
            'permit-verification-artifact-only-visible',
            'Permit detail shows verification is artifact-only',
            true,
            artifactOnlyVisible,
        ),
    );
    checks.push(
        check(
            'permit-verification-release-false-visible',
            'Permit detail shows public verification does not verify release',
            true,
            canVerifyReleaseNoVisible,
        ),
    );
    checks.push(
        check(
            'permit-verification-url-visible',
            'Permit detail shows public verification URL',
            true,
            publicRouteVisible,
        ),
    );

    const applicationFormPdfResponse = await targetPage.request.get(
        `${targetBaseUrl}${manifest.resources.application_form_pdf_url}`,
    );
    const applicationFormPdfContentType =
        applicationFormPdfResponse.headers()['content-type'] ?? '';
    const applicationFormPdfBody = await applicationFormPdfResponse.text();
    const applicationFormPdfVisible =
        applicationFormPdfResponse.ok() &&
        applicationFormPdfContentType.includes('application/pdf') &&
        applicationFormPdfBody.startsWith('%PDF-1.4') &&
        applicationFormPdfBody.includes('Business Application Form Artifact') &&
        applicationFormPdfBody.includes(
            manifest.resources.application_number,
        ) &&
        applicationFormPdfBody.includes(
            'Application form artifact renders currently captured intake facts',
        );
    checks.push(
        check(
            'application-form-pdf-intake-facts-visible',
            'Application form PDF contains exact prepared intake facts',
            true,
            applicationFormPdfVisible,
            {
                url: `${targetBaseUrl}${manifest.resources.application_form_pdf_url}`,
                status: applicationFormPdfResponse.status(),
                content_type: applicationFormPdfContentType,
                application_number: manifest.resources.application_number,
            },
        ),
    );
    reportApplicationFormPdf(
        applicationFormPdfVisible,
        manifest.resources.application_number,
        applicationFormPdfResponse.status(),
        applicationFormPdfContentType,
    );

    const assessmentPdfResponse = await targetPage.request.get(
        `${targetBaseUrl}${manifest.resources.assessment_pdf_url}`,
    );
    const assessmentPdfContentType =
        assessmentPdfResponse.headers()['content-type'] ?? '';
    const assessmentPdfBody = await assessmentPdfResponse.text();
    const assessmentPdfVisible =
        assessmentPdfResponse.ok() &&
        assessmentPdfContentType.includes('application/pdf') &&
        assessmentPdfBody.startsWith('%PDF-1.4') &&
        assessmentPdfBody.includes('Assessment Sheet Artifact') &&
        assessmentPdfBody.includes(manifest.resources.application_number) &&
        assessmentPdfBody.includes(
            moneyFromCents(manifest.resources.assessment_total_amount_cents),
        ) &&
        assessmentPdfBody.includes(
            'This artifact renders persisted assessment lines and does not recalculate fees or',
        );
    checks.push(
        check(
            'assessment-pdf-snapshot-visible',
            'Assessment PDF contains exact prepared persisted assessment snapshot',
            true,
            assessmentPdfVisible,
            {
                url: `${targetBaseUrl}${manifest.resources.assessment_pdf_url}`,
                status: assessmentPdfResponse.status(),
                content_type: assessmentPdfContentType,
                assessment_id: manifest.resources.assessment_id,
                application_number: manifest.resources.application_number,
            },
        ),
    );
    reportAssessmentPdf(
        assessmentPdfVisible,
        manifest.resources.assessment_id,
        manifest.resources.assessment_total_amount_cents,
        assessmentPdfResponse.status(),
        assessmentPdfContentType,
    );

    const permitPdfResponse = await targetPage.request.get(
        `${targetBaseUrl}${manifest.resources.permit_pdf_url}`,
    );
    const permitPdfContentType =
        permitPdfResponse.headers()['content-type'] ?? '';
    const permitPdfBody = await permitPdfResponse.text();
    const permitPdfVisible =
        permitPdfResponse.ok() &&
        permitPdfContentType.includes('application/pdf') &&
        permitPdfBody.startsWith('%PDF-1.4') &&
        permitPdfBody.includes(reference) &&
        permitPdfBody.includes('VERIFICATION BOUNDARY') &&
        permitPdfBody.includes('AUTHORITY BOUNDARY') &&
        permitPdfBody.includes(
            'Generated permit artifacts support authority review',
        );
    checks.push(
        check(
            'permit-pdf-verification-reference-visible',
            'Permit PDF contains exact verification reference',
            true,
            permitPdfVisible,
            {
                url: `${targetBaseUrl}${manifest.resources.permit_pdf_url}`,
                status: permitPdfResponse.status(),
                content_type: permitPdfContentType,
                reference,
            },
        ),
    );

    const publicResponse = await targetPage.request.get(verificationUrl);
    const publicJson = publicResponse.ok()
        ? await publicResponse.json().catch(() => null)
        : null;
    const publicReference = publicJson?.verification?.reference ?? null;
    const publicStatus = publicJson?.verification?.status ?? null;
    const canVerifyRelease =
        publicJson?.verification?.can_verify_release ?? null;
    const released = publicJson?.verification?.released ?? null;
    const publicAuthorityBoundaryStatus =
        publicJson?.release_readiness?.authority_boundary?.status ?? null;
    const publicAuthorityBoundaryStatement =
        publicJson?.release_readiness?.authority_boundary?.artifact_statement ??
        null;
    checks.push(
        check(
            'public-verification-reference-matches',
            'Public verification route returns exact reference',
            true,
            publicReference === reference,
            {
                url: verificationUrl,
                status: publicResponse.status(),
                reference: publicReference,
            },
        ),
    );
    checks.push(
        check(
            'public-verification-artifact-only',
            'Public verification route remains artifact-only',
            true,
            publicStatus === 'artifact_only' &&
                canVerifyRelease === false &&
                released === false,
            {
                status: publicStatus,
                can_verify_release: canVerifyRelease,
                released,
            },
        ),
    );
    checks.push(
        check(
            'public-verification-authority-boundary',
            'Public verification route exposes artifact authority boundary',
            true,
            publicAuthorityBoundaryStatus === 'ready_for_authority_review' &&
                publicAuthorityBoundaryStatement?.includes(
                    'do not issue, release, or make a permit legally effective',
                ),
            {
                authority_boundary_status: publicAuthorityBoundaryStatus,
                authority_boundary_statement: publicAuthorityBoundaryStatement,
            },
        ),
    );

    reportVerification(reference, publicStatus, canVerifyRelease, released);
    await screenshot(
        targetPage,
        '04-permit-verification-boundary',
        'browser/screenshots/04-permit-verification-boundary.png',
    );
}

async function inspectManualReceiptPdf(targetPage, targetBaseUrl) {
    const pdfResponse = await targetPage.request.get(
        `${targetBaseUrl}${manifest.resources.receipt_pdf_url}`,
    );
    const contentType = pdfResponse.headers()['content-type'] ?? '';
    const body = await pdfResponse.text();
    const pdfVisible =
        pdfResponse.ok() &&
        contentType.includes('application/pdf') &&
        body.startsWith('%PDF-1.4') &&
        body.includes(manifest.resources.public_reference);

    checks.push(
        check(
            'receipt-pdf-available',
            'Receipt PDF route returns the exact generated receipt artifact',
            true,
            pdfVisible,
            {
                url: `${targetBaseUrl}${manifest.resources.receipt_pdf_url}`,
                status: pdfResponse.status(),
                content_type: contentType,
            },
        ),
    );
}

async function inspectManualReceiptMobile(targetPage, targetBaseUrl) {
    await targetPage.setViewportSize({ width: 390, height: 844 });
    await targetPage.goto(`${targetBaseUrl}${manifest.resources.receipt_url}`, {
        waitUntil: 'networkidle',
    });
    const receiptVisible = await targetPage
        .getByText(manifest.resources.public_reference, { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const issuedVisible = await targetPage
        .getByText('issued', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const horizontalOverflow = await targetPage.evaluate(
        () =>
            document.documentElement.scrollWidth >
            document.documentElement.clientWidth + 1,
    );

    checks.push(
        check(
            'mobile-receipt-visible',
            'Mobile receipt keeps exact receipt number visible',
            true,
            receiptVisible,
        ),
    );
    checks.push(
        check(
            'mobile-issued-visible',
            'Mobile receipt keeps issued status visible',
            true,
            issuedVisible,
        ),
    );
    checks.push(
        check(
            'mobile-no-horizontal-overflow',
            'Mobile receipt has no horizontal overflow',
            false,
            horizontalOverflow,
        ),
    );
    await screenshot(
        targetPage,
        '05-mobile-receipt',
        'browser/screenshots/05-mobile-receipt.png',
    );
}

async function screenshot(targetPage, label, relativePath) {
    await targetPage.screenshot({
        path: path.join(runDirectory, relativePath),
        fullPage: true,
    });
    screenshots[label] = relativePath;
    actionLog.push(
        stepLog(`${label}-screenshot`, `Capture ${label} screenshot`, {
            screenshot: relativePath,
        }),
    );
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

function reportVerification(
    reference,
    publicStatus,
    canVerifyRelease,
    released,
) {
    verificationEvidence.reference = reference;
    verificationEvidence.public_status = publicStatus;
    verificationEvidence.can_verify_release = canVerifyRelease;
    verificationEvidence.released = released;
}

function reportPermitArtifact(
    permitPdfUrl,
    verificationReference,
    panelVisible,
    notLegallyEffectiveVisible,
    openAffordanceVisible,
) {
    permitArtifactEvidence.permit_pdf_url = permitPdfUrl;
    permitArtifactEvidence.verification_reference = verificationReference;
    permitArtifactEvidence.panel_visible = panelVisible;
    permitArtifactEvidence.not_legally_effective_visible =
        notLegallyEffectiveVisible;
    permitArtifactEvidence.open_affordance_visible = openAffordanceVisible;
}

function reportReceiptVoidBoundary(
    reference,
    status,
    canVoid,
    receiptStatus,
    collectionStatus,
) {
    receiptVoidBoundaryEvidence.reference = reference;
    receiptVoidBoundaryEvidence.status = status;
    receiptVoidBoundaryEvidence.can_void = canVoid;
    receiptVoidBoundaryEvidence.receipt_status = receiptStatus;
    receiptVoidBoundaryEvidence.collection_status = collectionStatus;
}

function reportApplicationFormPdf(
    available,
    applicationNumber,
    status,
    contentType,
) {
    documentEvidence.application_form = {
        available,
        application_number: applicationNumber,
        status,
        content_type: contentType,
    };
}

function reportAssessmentPdf(
    available,
    assessmentId,
    totalAmountCents,
    status,
    contentType,
) {
    documentEvidence.assessment = {
        available,
        assessment_id: assessmentId,
        total_amount_cents: totalAmountCents,
        status,
        content_type: contentType,
    };
}

function reportAssessmentRange(
    code,
    calculationType,
    basis,
    basisAmountCents,
    amountCents,
) {
    assessmentEvidence.range_line = {
        code,
        calculation_type: calculationType,
        basis,
        basis_amount_cents: basisAmountCents,
        amount_cents: amountCents,
    };
}

function reportBusinessTaxAssessment(
    code,
    name,
    category,
    lineOfBusiness,
    basis,
    declaredGrossSalesCents,
    amountCents,
) {
    assessmentEvidence.business_tax = {
        code,
        name,
        category,
        line_of_business: lineOfBusiness,
        basis,
        declared_gross_sales_cents: declaredGrossSalesCents,
        amount_cents: amountCents,
    };
}

function reportOnlinePaymentBoundary(status, unresolvedVisible) {
    onlinePaymentBoundaryEvidence.status = status;
    onlinePaymentBoundaryEvidence.can_pay_online = false;
    onlinePaymentBoundaryEvidence.can_reconcile_online = false;
    onlinePaymentBoundaryEvidence.unresolved_visible = unresolvedVisible;
}

function reportDailyCollection(
    receiptNumber,
    amountCents,
    scopeVisible,
    csvExportVisible,
) {
    reportEvidence.daily_collection = {
        receipt_number: receiptNumber,
        amount_cents: amountCents,
        scope_visible: scopeVisible,
        csv_export_visible: csvExportVisible,
    };
}

function reportRevenueSource(sourceCode, sourceVisible, csvExportVisible) {
    reportEvidence.revenue_source = {
        source_code: sourceCode,
        source_visible: sourceVisible,
        csv_export_visible: csvExportVisible,
    };
}

function reportPaidEstablishments(
    applicationNumber,
    businessName,
    applicationVisible,
    csvExportVisible,
) {
    reportEvidence.paid_establishments = {
        application_number: applicationNumber,
        business_name: businessName,
        application_visible: applicationVisible,
        csv_export_visible: csvExportVisible,
    };
}

function reportUnpaidEstablishments(
    applicationNumber,
    businessName,
    applicationVisible,
    csvExportVisible,
) {
    reportEvidence.unpaid_establishments = {
        application_number: applicationNumber,
        business_name: businessName,
        application_visible: applicationVisible,
        csv_export_visible: csvExportVisible,
    };
}

function reportTopTaxDue(
    applicationNumber,
    businessName,
    taxDueCents,
    applicationVisible,
    csvExportVisible,
) {
    reportEvidence.top_tax_due = {
        application_number: applicationNumber,
        business_name: businessName,
        tax_due_cents: taxDueCents,
        application_visible: applicationVisible,
        csv_export_visible: csvExportVisible,
    };
}

function reportRenewalPolicy(status, unresolvedVisible) {
    renewalPolicyEvidence.status = status;
    renewalPolicyEvidence.unresolved_visible = unresolvedVisible;
}

function reportAmendmentPolicy(status, unresolvedVisible) {
    amendmentPolicyEvidence.status = status;
    amendmentPolicyEvidence.unresolved_visible = unresolvedVisible;
}

function reportTransferPolicy(status, unresolvedVisible) {
    transferPolicyEvidence.status = status;
    transferPolicyEvidence.unresolved_visible = unresolvedVisible;
}

function reportRetirementPolicy(status, unresolvedVisible) {
    retirementPolicyEvidence.status = status;
    retirementPolicyEvidence.unresolved_visible = unresolvedVisible;
}

function moneyFromCents(amountCents) {
    return `PHP ${Number(amountCents / 100).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function uiMoneyFromCents(amountCents) {
    return `₱${Number(amountCents / 100).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

async function hasTitleInputValue(targetPage) {
    return await targetPage
        .locator('#title')
        .inputValue()
        .then((value) => value === `Lifecycle scenario ${manifest.run_id}`)
        .catch(() => false);
}

async function hasZeroCurrencyAmount(targetPage) {
    return await targetPage
        .locator('body')
        .innerText()
        .then(
            (text) =>
                text.includes('₱0.00') ||
                text.includes('PHP 0.00') ||
                /\b0\.00\b/.test(text),
        )
        .catch(() => false);
}

async function bodyTextMatches(targetPage, pattern) {
    return await targetPage
        .locator('body')
        .innerText()
        .then((text) => pattern.test(text))
        .catch(() => false);
}

async function onlinePaymentBoundaryIsVisible(targetPage) {
    const boundaryVisible = await targetPage
        .getByText('Online payment boundary', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const onlineNoVisible =
        (await targetPage
            .getByText('Can pay online', { exact: true })
            .first()
            .isVisible()
            .catch(() => false)) &&
        (await targetPage
            .getByText('No', { exact: true })
            .first()
            .isVisible()
            .catch(() => false));
    const reconciliationVisible = await targetPage
        .getByText('Unresolved reconciliation policy', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const policyVisible = await targetPage
        .getByText('settlement, reconciliation, refunds, chargebacks', {
            exact: false,
        })
        .first()
        .isVisible()
        .catch(() => false);

    return (
        boundaryVisible && onlineNoVisible && reconciliationVisible && policyVisible
    );
}

async function renewalPolicyBoundaryIsVisible(targetPage) {
    const boundaryVisible = await targetPage
        .getByText('Renewal policy boundary', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const unresolvedVisible = await targetPage
        .getByText('Unresolved renewal policy', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const policyVisible = await targetPage
        .getByText('PIL applicability and calculation', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);

    return boundaryVisible && unresolvedVisible && policyVisible;
}

async function amendmentPolicyBoundaryIsVisible(targetPage) {
    const boundaryVisible = await targetPage
        .getByText('Amendment policy boundary', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const unresolvedVisible = await targetPage
        .getByText('Unresolved amendment policy', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const policyVisible = await targetPage
        .getByText('amended-field semantics', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);

    return boundaryVisible && unresolvedVisible && policyVisible;
}

async function transferPolicyBoundaryIsVisible(targetPage) {
    const boundaryVisible = await targetPage
        .getByText('Transfer policy boundary', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const unresolvedVisible = await targetPage
        .getByText('Unresolved transfer policy', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const policyVisible = await targetPage
        .getByText('legal effect remain unresolved', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);

    return boundaryVisible && unresolvedVisible && policyVisible;
}

async function retirementPolicyBoundaryIsVisible(targetPage) {
    const boundaryVisible = await targetPage
        .getByText('Retirement policy boundary', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const unresolvedVisible = await targetPage
        .getByText('Unresolved retirement policy', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const policyVisible = await targetPage
        .getByText('legal retirement effect remain unresolved', {
            exact: false,
        })
        .first()
        .isVisible()
        .catch(() => false);

    return boundaryVisible && unresolvedVisible && policyVisible;
}

function fail(message) {
    const directory = manifestPath ? path.dirname(manifestPath) : process.cwd();
    fs.mkdirSync(path.join(directory, 'browser'), { recursive: true });
    fs.writeFileSync(
        path.join(directory, 'browser', 'report.json'),
        `${JSON.stringify(
            {
                result: { passed: false },
                error: message,
            },
            null,
            2,
        )}\n`,
    );
    console.error(message);
    process.exit(1);
}

function redact(value) {
    return String(value)
        .replace(password ?? '', '[redacted]')
        .replace(email ?? '', '[redacted-email]');
}
