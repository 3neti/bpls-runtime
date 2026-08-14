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
    'assessment_policy_boundary_visibility',
    'citizen_permit_draft_edit_visibility',
    'citizen_permit_draft_visibility',
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
const timelineEvidence = {};
const supportingDocumentEvidence = {};
const establishmentProfileEvidence = {};
const businessActivityEvidence = {};
const receiptVoidBoundaryEvidence = {};
const documentEvidence = {};
const assessmentEvidence = {};
const assessmentPolicyBoundaryEvidence = {};
const onlinePaymentBoundaryEvidence = {};
const paymentPolicyBoundaryEvidence = {};
const reportEvidence = {};
const renewalPolicyEvidence = {};
const amendmentPolicyEvidence = {};
const transferPolicyEvidence = {};
const retirementPolicyEvidence = {};
const feeCatalogEvidence = {};
const citizenDraftEvidence = {};

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

    if (manifest.scenario.key === 'citizen_permit_draft_visibility') {
        await inspectCitizenPermitDraft(page, baseUrl);
    }

    if (manifest.scenario.key === 'citizen_permit_draft_edit_visibility') {
        await editCitizenPermitDraft(page, baseUrl);
    }

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

    if (manifest.scenario.key === 'assessment_policy_boundary_visibility') {
        await inspectAssessmentPolicyBoundary(page, baseUrl);
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
    timeline: timelineEvidence,
    supporting_document: supportingDocumentEvidence,
    establishment_profile: establishmentProfileEvidence,
    business_activities: businessActivityEvidence,
    receipt_void_boundary: receiptVoidBoundaryEvidence,
    documents: documentEvidence,
    assessment: assessmentEvidence,
    assessment_policy_boundary: assessmentPolicyBoundaryEvidence,
    payment_policy_boundary: paymentPolicyBoundaryEvidence,
    online_payment_boundary: onlinePaymentBoundaryEvidence,
    reports: reportEvidence,
    renewal_policy: renewalPolicyEvidence,
    amendment_policy: amendmentPolicyEvidence,
    transfer_policy: transferPolicyEvidence,
    retirement_policy: retirementPolicyEvidence,
    fee_catalog: feeCatalogEvidence,
    citizen_draft: citizenDraftEvidence,
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
        check('authenticated', 'Authenticate as manifest actor', true, true),
    );
}

async function inspectCitizenPermitDraft(targetPage, targetBaseUrl) {
    const createUrl = `${targetBaseUrl}${manifest.resources.create_url}`;
    await targetPage.goto(createUrl, { waitUntil: 'networkidle' });
    const draftBoundaryVisible = await targetPage
        .getByTestId('citizen-draft-boundary')
        .isVisible()
        .catch(() => false);
    const officialNumberInputVisible = await targetPage
        .locator('#application_number')
        .isVisible()
        .catch(() => false);
    checks.push(
        check(
            'citizen-intake-draft-boundary-visible',
            'Citizen intake explains the draft boundary',
            true,
            draftBoundaryVisible,
        ),
        check(
            'citizen-intake-official-number-unavailable',
            'Citizen intake does not accept an official application number',
            false,
            officialNumberInputVisible,
        ),
    );
    await screenshot(
        targetPage,
        '01-citizen-intake',
        'browser/screenshots/01-citizen-intake.png',
    );

    const listUrl = `${targetBaseUrl}${manifest.resources.list_url}`;
    await targetPage.goto(listUrl, { waitUntil: 'networkidle' });
    const listRow = targetPage.locator(
        `[data-testid="citizen-permit-application-row"][data-application-id="${manifest.resources.record_id}"]`,
    );
    const listRowVisible = await listRow.isVisible().catch(() => false);
    const listStatus = await listRow
        .getAttribute('data-application-status')
        .catch(() => null);
    const staffNavigationVisible = await targetPage
        .getByRole('link', { name: 'Permit Assessments', exact: true })
        .isVisible()
        .catch(() => false);
    checks.push(
        check(
            'citizen-list-exact-draft-visible',
            'Citizen list shows the exact manifest draft',
            true,
            listRowVisible,
            { permit_application_id: manifest.resources.record_id },
        ),
        check(
            'citizen-list-status-matches',
            'Citizen list status matches canonical draft state',
            'draft',
            listStatus,
        ),
        check(
            'citizen-staff-navigation-unavailable',
            'Citizen does not receive staff navigation',
            false,
            staffNavigationVisible,
        ),
    );
    await screenshot(
        targetPage,
        '02-citizen-list',
        'browser/screenshots/02-citizen-list.png',
    );

    const detailUrl = `${targetBaseUrl}${manifest.resources.detail_url}`;
    await targetPage.goto(detailUrl, { waitUntil: 'networkidle' });
    const referenceVisible = await targetPage
        .getByText(manifest.resources.public_reference, { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const detailBoundaryVisible = await targetPage
        .getByTestId('citizen-draft-boundary')
        .isVisible()
        .catch(() => false);
    const assessActionVisible = await targetPage
        .getByRole('button', { name: /assess/i })
        .isVisible()
        .catch(() => false);
    const browserActivities = await targetPage
        .getByTestId('citizen-business-activity-row')
        .evaluateAll((rows) =>
            rows.map((row) => ({
                code: row.dataset.activityCode,
                declared_gross_sales_cents: Number(
                    row.dataset.grossSalesCents,
                ),
                capital_investment_cents: Number(
                    row.dataset.capitalInvestmentCents,
                ),
                quantity: Number(row.dataset.quantity),
                started_on: row.dataset.startedOn,
            })),
        );
    const expectedActivities = manifest.resources.business_activities.map(
        (activity) => ({
            code: activity.code,
            declared_gross_sales_cents:
                activity.declared_gross_sales_cents,
            capital_investment_cents: activity.capital_investment_cents,
            quantity: activity.quantity,
            started_on: activity.started_on,
        }),
    );
    const activitiesMatch =
        JSON.stringify(browserActivities) === JSON.stringify(expectedActivities);
    Object.assign(citizenDraftEvidence, {
        permit_application_id: manifest.resources.record_id,
        display_reference: manifest.resources.public_reference,
        status: 'draft',
        business_activities: browserActivities,
        assessment_action_visible: assessActionVisible,
    });
    checks.push(
        check(
            'citizen-detail-exact-draft-visible',
            'Citizen detail shows the exact manifest draft',
            true,
            referenceVisible,
        ),
        check(
            'citizen-detail-draft-boundary-visible',
            'Citizen detail preserves the draft boundary',
            true,
            detailBoundaryVisible,
        ),
        check(
            'citizen-detail-activities-match',
            'Citizen detail activities match manifest evidence exactly',
            true,
            activitiesMatch,
            { expected: expectedActivities, actual: browserActivities },
        ),
        check(
            'citizen-detail-assessment-unavailable',
            'Citizen draft does not expose assessment action',
            false,
            assessActionVisible,
        ),
    );
    await screenshot(
        targetPage,
        '03-citizen-detail',
        'browser/screenshots/03-citizen-detail.png',
    );

    await targetPage.setViewportSize({ width: 390, height: 844 });
    await targetPage.goto(detailUrl, { waitUntil: 'networkidle' });
    const mobileBoundaryVisible = await targetPage
        .getByTestId('citizen-draft-boundary')
        .isVisible()
        .catch(() => false);
    const mobileActivityCount = await targetPage
        .getByTestId('citizen-business-activity-mobile-row')
        .count();
    const horizontalOverflow = await targetPage.evaluate(
        () =>
            document.documentElement.scrollWidth >
            document.documentElement.clientWidth + 1,
    );
    checks.push(
        check(
            'citizen-mobile-draft-boundary-visible',
            'Mobile citizen detail keeps draft boundary visible',
            true,
            mobileBoundaryVisible,
        ),
        check(
            'citizen-mobile-activities-visible',
            'Mobile citizen detail shows every manifest activity',
            expectedActivities.length,
            mobileActivityCount,
        ),
        check(
            'citizen-mobile-no-horizontal-overflow',
            'Mobile citizen detail has no page-level horizontal overflow',
            false,
            horizontalOverflow,
        ),
    );
    await screenshot(
        targetPage,
        '04-citizen-mobile-detail',
        'browser/screenshots/04-citizen-mobile-detail.png',
    );
}

async function editCitizenPermitDraft(targetPage, targetBaseUrl) {
    const detailUrl = `${targetBaseUrl}${manifest.resources.detail_url}`;
    const editUrl = `${targetBaseUrl}${manifest.resources.edit_url}`;
    const expectedEdit = manifest.resources.expected_edit;

    await targetPage.goto(detailUrl, { waitUntil: 'networkidle' });
    const alreadyEdited = await targetPage
        .getByText(expectedEdit.business_name, { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const editActionVisible = await targetPage
        .getByRole('link', { name: 'Edit Draft', exact: true })
        .isVisible()
        .catch(() => false);
    checks.push(
        check(
            'citizen-edit-action-available',
            'Owned unprocessed draft exposes the edit action',
            true,
            editActionVisible,
        ),
    );
    const beforeEditScreenshot =
        'browser/screenshots/01-citizen-draft-before-edit.png';

    if (
        alreadyEdited &&
        fs.existsSync(path.join(runDirectory, beforeEditScreenshot))
    ) {
        screenshots['01-citizen-draft-before-edit'] = beforeEditScreenshot;
        actionLog.push(
            stepLog(
                '01-citizen-draft-before-edit-screenshot-retained',
                'Retain the original pre-edit screenshot during resume',
                { screenshot: beforeEditScreenshot },
            ),
        );
    } else {
        await screenshot(
            targetPage,
            '01-citizen-draft-before-edit',
            beforeEditScreenshot,
        );
    }

    await targetPage.goto(editUrl, { waitUntil: 'networkidle' });
    const prefilledBusinessName = await targetPage
        .locator('#business_name')
        .inputValue();
    const prefilledActivityCount = await targetPage
        .getByTestId('permit-business-activity-row')
        .count();
    const draftBoundaryVisible = await targetPage
        .getByTestId('citizen-draft-boundary')
        .isVisible()
        .catch(() => false);
    checks.push(
        check(
            'citizen-edit-prefills-exact-business',
            'Edit workspace prefills the exact manifest business',
            alreadyEdited
                ? expectedEdit.business_name
                : manifest.resources.business_name,
            prefilledBusinessName,
        ),
        check(
            'citizen-edit-prefills-all-activities',
            'Edit workspace prefills every manifest activity',
            manifest.resources.business_activities.length,
            prefilledActivityCount,
        ),
        check(
            'citizen-edit-draft-boundary-visible',
            'Edit workspace preserves the citizen draft boundary',
            true,
            draftBoundaryVisible,
        ),
    );

    await targetPage.locator('#owner_phone').fill(expectedEdit.owner_phone);
    await targetPage
        .locator('#business_name')
        .fill(expectedEdit.business_name);
    await targetPage
        .locator('#lines_0_declared_gross_sales')
        .fill(
            (expectedEdit.business_activities[0].declared_gross_sales_cents / 100).toFixed(2),
        );
    await targetPage
        .locator('#lines_0_quantity')
        .fill(String(expectedEdit.business_activities[0].quantity));
    await targetPage
        .locator('#lines_1_capital_investment')
        .fill(
            (expectedEdit.business_activities[1].capital_investment_cents / 100).toFixed(2),
        );
    await targetPage
        .locator('#lines_1_quantity')
        .fill(String(expectedEdit.business_activities[1].quantity));
    await targetPage.evaluate(() => window.scrollTo(0, 0));
    await screenshot(
        targetPage,
        '02-citizen-draft-edit-workspace',
        'browser/screenshots/02-citizen-draft-edit-workspace.png',
    );

    if (alreadyEdited) {
        actionLog.push(
            stepLog(
                'citizen-draft-edit-resumed',
                'Draft already matches the expected edit; skip repeat submission',
                { permit_application_id: manifest.resources.record_id },
            ),
        );
        await targetPage.goto(detailUrl, { waitUntil: 'networkidle' });
    } else {
        actionLog.push(
            stepLog(
                'citizen-draft-edit-entered',
                'Edit manifest-bound citizen draft facts through the real form',
                { permit_application_id: manifest.resources.record_id },
            ),
        );
        await targetPage.getByRole('button', { name: 'Save Changes' }).click();
        await targetPage.waitForURL(
            new RegExp(`/citizen/permit-applications/${manifest.resources.record_id}$`),
            { timeout: 10000 },
        );
    }

    await targetPage.getByText(expectedEdit.business_name, { exact: false }).first().waitFor();

    const browserActivities = await targetPage
        .getByTestId('citizen-business-activity-row')
        .evaluateAll((rows) =>
            rows.map((row) => ({
                code: row.dataset.activityCode,
                declared_gross_sales_cents: Number(row.dataset.grossSalesCents),
                capital_investment_cents: Number(
                    row.dataset.capitalInvestmentCents,
                ),
                quantity: Number(row.dataset.quantity),
                started_on: row.dataset.startedOn,
            })),
        );
    const expectedActivities = expectedEdit.business_activities.map(
        (activity) => ({
            code: activity.code,
            declared_gross_sales_cents: activity.declared_gross_sales_cents,
            capital_investment_cents: activity.capital_investment_cents,
            quantity: activity.quantity,
            started_on: activity.started_on,
        }),
    );
    const activitiesMatch =
        JSON.stringify(browserActivities) === JSON.stringify(expectedActivities);
    const businessVisible = await targetPage
        .getByText(expectedEdit.business_name, { exact: false })
        .first()
        .isVisible();
    const ownerPhoneVisible = await targetPage
        .getByText(expectedEdit.owner_phone, { exact: true })
        .isVisible();
    const assessmentActionVisible = await targetPage
        .getByRole('button', { name: /assess/i })
        .isVisible()
        .catch(() => false);
    Object.assign(citizenDraftEvidence, {
        permit_application_id: manifest.resources.record_id,
        display_reference: manifest.resources.public_reference,
        status: 'draft',
        business_name: expectedEdit.business_name,
        owner_phone: expectedEdit.owner_phone,
        business_activities: browserActivities,
        assessment_action_visible: assessmentActionVisible,
        edit_performed_by_browser: true,
        edit_resumed_without_resubmit: alreadyEdited,
    });
    checks.push(
        check(
            'citizen-edited-business-visible',
            'Updated business name is visible on the exact draft',
            true,
            businessVisible,
        ),
        check(
            'citizen-edited-owner-visible',
            'Updated owner contact is visible on the exact draft',
            true,
            ownerPhoneVisible,
        ),
        check(
            'citizen-edited-activities-match',
            'Updated activities match manifest evidence exactly',
            true,
            activitiesMatch,
            { expected: expectedActivities, actual: browserActivities },
        ),
        check(
            'citizen-edited-draft-remains-unassessed',
            'Editing does not expose assessment action',
            false,
            assessmentActionVisible,
        ),
    );
    await screenshot(
        targetPage,
        '03-citizen-draft-after-edit',
        'browser/screenshots/03-citizen-draft-after-edit.png',
    );

    await targetPage.setViewportSize({ width: 390, height: 844 });
    await targetPage.goto(detailUrl, { waitUntil: 'networkidle' });
    const mobileActivityCount = await targetPage
        .getByTestId('citizen-business-activity-mobile-row')
        .count();
    const horizontalOverflow = await targetPage.evaluate(
        () =>
            document.documentElement.scrollWidth >
            document.documentElement.clientWidth + 1,
    );
    checks.push(
        check(
            'citizen-edited-mobile-activities-visible',
            'Mobile detail shows every edited activity',
            expectedActivities.length,
            mobileActivityCount,
        ),
        check(
            'citizen-edited-mobile-no-horizontal-overflow',
            'Mobile edited draft has no page-level horizontal overflow',
            false,
            horizontalOverflow,
        ),
    );
    await screenshot(
        targetPage,
        '04-citizen-draft-after-edit-mobile',
        'browser/screenshots/04-citizen-draft-after-edit-mobile.png',
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

async function inspectAssessmentPolicyBoundary(targetPage, targetBaseUrl) {
    const indexUrl = `${targetBaseUrl}${manifest.resources.assessment_index_url}`;
    await targetPage.goto(indexUrl, { waitUntil: 'networkidle' });

    const applicationRow = targetPage
        .locator('tr')
        .filter({ hasText: manifest.resources.application_number })
        .first();
    const applicationVisible = await applicationRow
        .isVisible()
        .catch(() => false);

    checks.push(
        check(
            'assessment-policy-boundary-application-visible',
            'Assessment queue shows the prepared formula-boundary application',
            true,
            applicationVisible,
            {
                url: indexUrl,
                permit_application_id: manifest.resources.record_id,
            },
        ),
    );

    await applicationRow.getByRole('button', { name: /assess/i }).click();
    await targetPage.waitForLoadState('networkidle');
    await targetPage
        .waitForFunction(
            (expectedMessage) =>
                document.body.innerText.includes(expectedMessage),
            manifest.resources.expected_policy_message,
            { timeout: 10000 },
        )
        .catch(() => {});

    const boundaryVisible = await targetPage
        .getByText('Assessment policy boundary', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const reasonVisible = await targetPage
        .getByText(manifest.resources.expected_policy_message, {
            exact: false,
        })
        .first()
        .isVisible()
        .catch(() => false);
    const updatedApplicationRow = targetPage
        .locator('tr')
        .filter({ hasText: manifest.resources.application_number })
        .first();
    const rowBoundaryVisible = await updatedApplicationRow
        .getByText('Assessment policy boundary', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const notAssessedVisible = await updatedApplicationRow
        .getByText('Not assessed', { exact: false })
        .first()
        .isVisible()
        .catch(() => false);

    checks.push(
        check(
            'assessment-policy-boundary-visible',
            'Assessment queue shows unsupported formula policy boundary',
            true,
            boundaryVisible && reasonVisible && rowBoundaryVisible,
        ),
    );
    checks.push(
        check(
            'assessment-policy-boundary-no-assessment-created-visible',
            'Assessment queue still shows the application as not assessed',
            true,
            notAssessedVisible,
        ),
    );

    assessmentPolicyBoundaryEvidence.application_number =
        manifest.resources.application_number;
    assessmentPolicyBoundaryEvidence.boundary_visible = boundaryVisible;
    assessmentPolicyBoundaryEvidence.reason_visible = reasonVisible;
    assessmentPolicyBoundaryEvidence.row_boundary_visible = rowBoundaryVisible;
    assessmentPolicyBoundaryEvidence.not_assessed_visible = notAssessedVisible;

    await targetPage.evaluate(() => {
        for (const element of document.querySelectorAll('*')) {
            if (element instanceof HTMLElement) {
                element.scrollLeft = 0;
            }
        }
    });

    await screenshot(
        targetPage,
        '01-assessment-policy-boundary',
        'browser/screenshots/01-assessment-policy-boundary.png',
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
        .getByText(
            uiMoneyFromCents(manifest.resources.first_range_amount_cents),
            {
                exact: false,
            },
        )
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
    const visiblePolicyBoundaries = [];

    for (const policyBoundary of manifest.resources.policy_boundaries ?? []) {
        const visible = await targetPage
            .getByText(uiLabel(policyBoundary), { exact: false })
            .first()
            .isVisible()
            .catch(() => false);

        if (visible) {
            visiblePolicyBoundaries.push(policyBoundary);
        }

        checks.push(
            check(
                `fee-catalog-detail-policy-boundary-${policyBoundary}-visible`,
                `Fee rule detail shows policy boundary ${policyBoundary}`,
                true,
                visible,
            ),
        );
    }

    const visibleApplicationTypes = [];

    for (const applicationType of manifest.resources.application_types ?? []) {
        const visible = await targetPage
            .getByText(uiLabel(applicationType), { exact: false })
            .first()
            .isVisible()
            .catch(() => false);

        if (visible) {
            visibleApplicationTypes.push(applicationType);
        }

        checks.push(
            check(
                `fee-catalog-detail-application-type-${applicationType}-visible`,
                `Fee rule detail shows applicability ${applicationType}`,
                true,
                visible,
            ),
        );
    }

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
    feeCatalogEvidence.policy_boundaries_visible = visiblePolicyBoundaries;
    feeCatalogEvidence.application_types_visible = visibleApplicationTypes;
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
    const paymentPolicyBoundaryVisible = await targetPage
        .getByText('Payment policy boundary', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const installmentVisible = await targetPage
        .getByText(
            'annual, semiannual, and quarterly payment splitting rules',
            {
                exact: false,
            },
        )
        .first()
        .isVisible()
        .catch(() => false);
    const dueDateVisible = await targetPage
        .getByText(
            'statutory due dates and renewal-specific due-date adjustments',
            {
                exact: false,
            },
        )
        .first()
        .isVisible()
        .catch(() => false);
    const surchargeVisible = await targetPage
        .getByText('late-payment surcharge trigger date and base amount', {
            exact: false,
        })
        .first()
        .isVisible()
        .catch(() => false);
    const pilVisible = await targetPage
        .getByText('PIL validation threshold and refusal workflow', {
            exact: false,
        })
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
    checks.push(
        check(
            'payment-schedule-policy-boundary-visible',
            'Payment schedule detail shows installment, due-date, surcharge, interest, PIL, and deficiency boundary',
            true,
            paymentPolicyBoundaryVisible &&
                installmentVisible &&
                dueDateVisible &&
                surchargeVisible &&
                pilVisible,
        ),
    );
    reportPaymentPolicyBoundary(
        paymentPolicyBoundaryVisible ? 'policy_boundary' : 'missing',
        installmentVisible,
        dueDateVisible,
        surchargeVisible,
        pilVisible,
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
        .getByText(
            uiMoneyFromCents(manifest.resources.assessment_total_amount_cents),
            {
                exact: false,
            },
        )
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
    const intakeUrl = `${targetBaseUrl}${manifest.resources.permit_application_create_url}`;
    await targetPage.goto(intakeUrl, { waitUntil: 'networkidle' });
    const establishmentIntake = targetPage.getByTestId(
        'permit-establishment-intake',
    );
    const establishmentIntakeVisible = await establishmentIntake
        .isVisible()
        .catch(() => false);
    const establishmentIntakeControlCount = await establishmentIntake
        .locator(
            '#ownership_type, #organization_name, #occupancy, #building_name, #property_index_number, #business_area_square_meters, #male_employee_count, #female_employee_count, #business_contact_number, #business_email, #established_on, #started_on, #registered_on',
        )
        .count();
    checks.push(
        check(
            'permit-establishment-intake-visible',
            'Staff intake shows every structured establishment-profile control',
            true,
            establishmentIntakeVisible &&
                establishmentIntakeControlCount === 13,
            { url: intakeUrl },
        ),
    );
    establishmentProfileEvidence.intake_form_visible =
        establishmentIntakeVisible && establishmentIntakeControlCount === 13;

    const businessActivityIntake = targetPage.getByTestId(
        'permit-business-activity-intake',
    );
    const initialBusinessActivityCount = await businessActivityIntake
        .getByTestId('permit-business-activity-row')
        .count();
    await businessActivityIntake
        .getByTestId('permit-add-business-activity')
        .click();
    const addedBusinessActivityCount = await businessActivityIntake
        .getByTestId('permit-business-activity-row')
        .count();
    await businessActivityIntake
        .getByRole('button', { name: 'Remove activity 2' })
        .click();
    const removedBusinessActivityCount = await businessActivityIntake
        .getByTestId('permit-business-activity-row')
        .count();
    await businessActivityIntake
        .getByTestId('permit-add-business-activity')
        .click();
    const restoredBusinessActivityCount = await businessActivityIntake
        .getByTestId('permit-business-activity-row')
        .count();
    const businessActivityAddRemoveVerified =
        initialBusinessActivityCount === 1 &&
        addedBusinessActivityCount === 2 &&
        removedBusinessActivityCount === 1 &&
        restoredBusinessActivityCount === 2;
    checks.push(
        check(
            'permit-business-activity-add-remove',
            'Staff intake can add and remove bounded business activity rows without submission',
            true,
            businessActivityAddRemoveVerified,
        ),
    );
    businessActivityEvidence.intake_add_remove_verified =
        businessActivityAddRemoveVerified;
    await businessActivityIntake.scrollIntoViewIfNeeded();
    await screenshot(
        targetPage,
        '00c-business-activity-intake',
        'browser/screenshots/00c-business-activity-intake.png',
    );
    await targetPage.setViewportSize({ width: 390, height: 844 });
    const mobileBusinessActivityIntakeVisible = await businessActivityIntake
        .isVisible()
        .catch(() => false);
    const mobileBusinessActivityIntakeOverflow = await targetPage.evaluate(
        () => document.documentElement.scrollWidth > window.innerWidth,
    );
    checks.push(
        check(
            'mobile-permit-business-activity-intake-visible',
            'Mobile staff intake keeps repeatable business activities visible',
            true,
            mobileBusinessActivityIntakeVisible,
        ),
    );
    checks.push(
        check(
            'mobile-permit-business-activity-intake-no-overflow',
            'Mobile repeatable business activities have no horizontal overflow',
            false,
            mobileBusinessActivityIntakeOverflow,
        ),
    );
    businessActivityEvidence.intake_mobile_visible =
        mobileBusinessActivityIntakeVisible;
    businessActivityEvidence.intake_mobile_horizontal_overflow =
        mobileBusinessActivityIntakeOverflow;
    await businessActivityIntake.scrollIntoViewIfNeeded();
    await screenshot(
        targetPage,
        '00d-mobile-business-activity-intake',
        'browser/screenshots/00d-mobile-business-activity-intake.png',
    );
    await targetPage.setViewportSize({ width: 1440, height: 900 });

    if (establishmentIntakeVisible) {
        await establishmentIntake.scrollIntoViewIfNeeded();
        await screenshot(
            targetPage,
            '00-establishment-intake',
            'browser/screenshots/00-establishment-intake.png',
        );
        await targetPage.setViewportSize({ width: 390, height: 844 });
        const mobileEstablishmentIntakeVisible = await establishmentIntake
            .isVisible()
            .catch(() => false);
        const mobileEstablishmentIntakeOverflow = await targetPage.evaluate(
            () => document.documentElement.scrollWidth > window.innerWidth,
        );
        checks.push(
            check(
                'mobile-permit-establishment-intake-visible',
                'Mobile staff intake keeps the establishment-profile controls visible',
                true,
                mobileEstablishmentIntakeVisible,
            ),
        );
        checks.push(
            check(
                'mobile-permit-establishment-intake-no-overflow',
                'Mobile staff intake has no horizontal overflow',
                false,
                mobileEstablishmentIntakeOverflow,
            ),
        );
        establishmentProfileEvidence.intake_form_mobile_visible =
            mobileEstablishmentIntakeVisible;
        establishmentProfileEvidence.intake_form_mobile_horizontal_overflow =
            mobileEstablishmentIntakeOverflow;
        await establishmentIntake.scrollIntoViewIfNeeded();
        await screenshot(
            targetPage,
            '00b-mobile-establishment-intake',
            'browser/screenshots/00b-mobile-establishment-intake.png',
        );
        await targetPage.setViewportSize({ width: 1440, height: 900 });
    }

    const permitUrl = `${targetBaseUrl}${manifest.resources.permit_application_url}`;
    await targetPage.goto(permitUrl, { waitUntil: 'networkidle' });
    const businessActivities = targetPage.getByTestId(
        'permit-business-activities',
    );
    const businessActivitiesVisible = await businessActivities
        .isVisible()
        .catch(() => false);
    const actualBusinessActivities = await businessActivities
        .getByTestId('permit-business-activity-row')
        .evaluateAll((rows) =>
            rows.map((row) => ({
                id: Number(row.getAttribute('data-business-activity-id')),
                code: row.getAttribute('data-business-activity-code'),
                name: row.getAttribute('data-business-activity-name'),
                declared_gross_sales_cents: Number(
                    row.getAttribute('data-declared-gross-sales-cents'),
                ),
                capital_investment_cents: Number(
                    row.getAttribute('data-capital-investment-cents'),
                ),
                quantity: Number(row.getAttribute('data-quantity')),
                started_on: row.getAttribute('data-started-on'),
            })),
        );
    const businessActivitiesMatch =
        businessActivitiesVisible &&
        JSON.stringify(actualBusinessActivities) ===
            JSON.stringify(manifest.resources.business_activities);
    checks.push(
        check(
            'permit-business-activities-match',
            'Permit detail shows every exact canonical business activity',
            true,
            businessActivitiesMatch,
        ),
    );
    businessActivityEvidence.activities = actualBusinessActivities;
    businessActivityEvidence.detail_visible = businessActivitiesVisible;

    if (businessActivitiesVisible) {
        await businessActivities.scrollIntoViewIfNeeded();
        await screenshot(
            targetPage,
            '03-business-activities',
            'browser/screenshots/03-business-activities.png',
        );
    }

    const applicationVisible = await targetPage
        .getByText(manifest.resources.application_number, { exact: false })
        .first()
        .isVisible()
        .catch(() => false);
    const timeline = targetPage.getByTestId('permit-timeline');
    const timelineVisible = await timeline.isVisible().catch(() => false);
    const timelineEventKeys = await targetPage
        .getByTestId('permit-timeline-event')
        .evaluateAll((elements) =>
            elements
                .map((element) => element.getAttribute('data-timeline-key'))
                .filter((key) => key !== null),
        )
        .catch(() => []);
    const expectedTimelineEventKeys =
        manifest.resources.permit_timeline_event_keys ?? [];
    const establishmentProfile = targetPage.getByTestId(
        'permit-establishment-profile',
    );
    const establishmentProfileVisible = await establishmentProfile
        .isVisible()
        .catch(() => false);
    const establishmentOwnershipType = await targetPage
        .getByTestId('establishment-ownership-type')
        .textContent()
        .catch(() => null);
    const establishmentOccupancy = await targetPage
        .getByTestId('establishment-occupancy')
        .textContent()
        .catch(() => null);
    const establishmentBusinessArea = await targetPage
        .getByTestId('establishment-business-area')
        .textContent()
        .catch(() => null);
    const establishmentEmployeeCounts = await targetPage
        .getByTestId('establishment-employee-counts')
        .textContent()
        .catch(() => null);
    const establishmentStartedOn = await targetPage
        .getByTestId('establishment-started-on')
        .textContent()
        .catch(() => null);
    const establishmentProfileMatches =
        establishmentProfileVisible &&
        normalizedText(establishmentOwnershipType) ===
            manifest.resources.establishment_ownership_type.replaceAll(
                '-',
                ' ',
            ) &&
        normalizedText(establishmentOccupancy) ===
            manifest.resources.establishment_occupancy &&
        normalizedText(establishmentBusinessArea) ===
            `${manifest.resources.establishment_business_area_square_meters} m²` &&
        normalizedText(establishmentEmployeeCounts) ===
            `Male ${manifest.resources.establishment_male_employee_count} · Female ${manifest.resources.establishment_female_employee_count}` &&
        normalizedText(establishmentStartedOn) ===
            manifest.resources.establishment_started_on;
    const supportingDocuments = targetPage.getByTestId(
        'permit-supporting-documents',
    );
    const supportingDocumentsVisible = await supportingDocuments
        .isVisible()
        .catch(() => false);
    const supportingDocument = targetPage.locator(
        `[data-document-id="${manifest.resources.supporting_document_id}"]`,
    );
    const supportingDocumentVisible = await supportingDocument
        .isVisible()
        .catch(() => false);
    const supportingDocumentLabelVisible = await supportingDocument
        .getByText(manifest.resources.supporting_document_label, {
            exact: true,
        })
        .isVisible()
        .catch(() => false);
    const supportingDocumentNameVisible = await supportingDocument
        .getByText(manifest.resources.supporting_document_name, {
            exact: false,
        })
        .isVisible()
        .catch(() => false);
    const supportingDocumentLink = supportingDocument.getByRole('link', {
        name: /download/i,
    });
    const supportingDocumentDownloadUrl = await supportingDocumentLink
        .getAttribute('href')
        .catch(() => null);
    const supportingDocumentResponse = await targetPage.request.get(
        `${targetBaseUrl}${manifest.resources.supporting_document_download_url}`,
    );
    const supportingDocumentContentType =
        supportingDocumentResponse.headers()['content-type'] ?? '';
    const supportingDocumentDisposition =
        supportingDocumentResponse.headers()['content-disposition'] ?? '';
    const supportingDocumentBody = await supportingDocumentResponse.body();
    const supportingDocumentDownloadAvailable =
        supportingDocumentResponse.ok() &&
        supportingDocumentContentType.includes('application/pdf') &&
        supportingDocumentDisposition.includes(
            manifest.resources.supporting_document_name,
        ) &&
        supportingDocumentBody.subarray(0, 8).toString() === '%PDF-1.4';
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
            'permit-application-timeline-visible',
            'Permit detail shows the authoritative application timeline',
            true,
            timelineVisible,
        ),
    );
    checks.push(
        check(
            'permit-application-timeline-events-match',
            'Permit detail shows the exact canonical timeline event keys in order',
            expectedTimelineEventKeys,
            timelineEventKeys,
        ),
    );
    timelineEvidence.event_count = timelineEventKeys.length;
    timelineEvidence.event_keys = timelineEventKeys;

    checks.push(
        check(
            'permit-establishment-profile-matches',
            'Permit detail shows the exact canonical establishment profile',
            true,
            establishmentProfileMatches,
        ),
    );
    establishmentProfileEvidence.ownership_type =
        manifest.resources.establishment_ownership_type;
    establishmentProfileEvidence.occupancy =
        manifest.resources.establishment_occupancy;
    establishmentProfileEvidence.business_area_square_meters =
        manifest.resources.establishment_business_area_square_meters;
    establishmentProfileEvidence.male_employee_count =
        manifest.resources.establishment_male_employee_count;
    establishmentProfileEvidence.female_employee_count =
        manifest.resources.establishment_female_employee_count;
    establishmentProfileEvidence.started_on =
        manifest.resources.establishment_started_on;
    establishmentProfileEvidence.panel_visible = establishmentProfileVisible;

    if (establishmentProfileVisible) {
        await establishmentProfile.scrollIntoViewIfNeeded();
        await screenshot(
            targetPage,
            '03-establishment-profile',
            'browser/screenshots/03-establishment-profile.png',
        );
    }

    checks.push(
        check(
            'permit-supporting-documents-visible',
            'Permit detail shows the supporting-document evidence boundary',
            true,
            supportingDocumentsVisible,
        ),
    );
    checks.push(
        check(
            'permit-supporting-document-exact-record-visible',
            'Permit detail shows the exact supporting document prepared by the terminal runner',
            true,
            supportingDocumentVisible &&
                supportingDocumentLabelVisible &&
                supportingDocumentNameVisible,
            {
                document_id: manifest.resources.supporting_document_id,
            },
        ),
    );
    checks.push(
        check(
            'permit-supporting-document-download-url-matches',
            'Supporting-document affordance points to the exact manifest resource',
            manifest.resources.supporting_document_download_url,
            supportingDocumentDownloadUrl,
        ),
    );
    checks.push(
        check(
            'permit-supporting-document-download-available',
            'Supporting document downloads as the exact private PDF artifact',
            true,
            supportingDocumentDownloadAvailable,
            {
                url: manifest.resources.supporting_document_download_url,
                status: supportingDocumentResponse.status(),
                content_type: supportingDocumentContentType,
            },
        ),
    );
    supportingDocumentEvidence.id = manifest.resources.supporting_document_id;
    supportingDocumentEvidence.label =
        manifest.resources.supporting_document_label;
    supportingDocumentEvidence.original_name =
        manifest.resources.supporting_document_name;
    supportingDocumentEvidence.download_url = supportingDocumentDownloadUrl;
    supportingDocumentEvidence.panel_visible = supportingDocumentsVisible;
    supportingDocumentEvidence.download_available =
        supportingDocumentDownloadAvailable;

    if (supportingDocumentsVisible) {
        await supportingDocuments.scrollIntoViewIfNeeded();
        await screenshot(
            targetPage,
            '03a-supporting-documents',
            'browser/screenshots/03a-supporting-documents.png',
        );

        await targetPage.setViewportSize({ width: 390, height: 844 });
        const mobileEstablishmentProfileVisible = await establishmentProfile
            .isVisible()
            .catch(() => false);
        const mobileSupportingDocumentVisible = await supportingDocument
            .isVisible()
            .catch(() => false);
        const mobileSupportingDocumentsOverflow = await targetPage.evaluate(
            () => document.documentElement.scrollWidth > window.innerWidth,
        );
        checks.push(
            check(
                'mobile-permit-establishment-profile-visible',
                'Mobile permit detail keeps the establishment profile visible',
                true,
                mobileEstablishmentProfileVisible,
            ),
        );
        checks.push(
            check(
                'mobile-permit-supporting-document-visible',
                'Mobile permit detail keeps the exact supporting document visible',
                true,
                mobileSupportingDocumentVisible,
            ),
        );
        checks.push(
            check(
                'mobile-permit-supporting-documents-no-overflow',
                'Mobile supporting-document surface has no horizontal overflow',
                false,
                mobileSupportingDocumentsOverflow,
            ),
        );
        supportingDocumentEvidence.mobile_visible =
            mobileSupportingDocumentVisible;
        supportingDocumentEvidence.mobile_horizontal_overflow =
            mobileSupportingDocumentsOverflow;
        establishmentProfileEvidence.mobile_visible =
            mobileEstablishmentProfileVisible;
        await supportingDocuments.scrollIntoViewIfNeeded();
        await screenshot(
            targetPage,
            '03b-mobile-supporting-documents',
            'browser/screenshots/03b-mobile-supporting-documents.png',
        );
        await targetPage.setViewportSize({ width: 1440, height: 900 });
    }

    if (timelineVisible) {
        await timeline.scrollIntoViewIfNeeded();
        await screenshot(
            targetPage,
            '03a-permit-timeline',
            'browser/screenshots/03a-permit-timeline.png',
        );
    }

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
    const verificationViewUrl = `${targetBaseUrl}${
        manifest.resources.permit_verification_view_url ??
        `${manifest.resources.permit_verification_url}/view`
    }`;
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
    const publicPageRouteVisible = await targetPage
        .getByText(manifest.resources.permit_verification_view_url, {
            exact: false,
        })
        .first()
        .isVisible()
        .catch(() => false);
    const verificationApiRouteVisible = await targetPage
        .getByText(verificationUrl, { exact: true })
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
            'permit-verification-public-page-url-visible',
            'Permit detail shows exact public verification page URL',
            true,
            publicPageRouteVisible,
        ),
    );
    checks.push(
        check(
            'permit-verification-api-url-visible',
            'Permit detail identifies the exact verification API URL separately',
            true,
            verificationApiRouteVisible,
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
        ) &&
        applicationFormPdfBody.includes(
            manifest.resources.establishment_building_name,
        ) &&
        applicationFormPdfBody.includes(
            manifest.resources.establishment_property_index_number,
        ) &&
        applicationFormPdfBody.includes(
            `${manifest.resources.establishment_business_area_square_meters} square meters`,
        ) &&
        applicationFormPdfBody.includes(
            `Male ${manifest.resources.establishment_male_employee_count} / Female ${manifest.resources.establishment_female_employee_count}`,
        ) &&
        applicationFormPdfBody.includes(
            manifest.resources.establishment_started_on,
        ) &&
        manifest.resources.business_activities.every(
            (activity) =>
                applicationFormPdfBody.includes(activity.code) &&
                applicationFormPdfBody.includes(activity.name) &&
                applicationFormPdfBody.includes(
                    moneyFromCents(activity.declared_gross_sales_cents),
                ) &&
                applicationFormPdfBody.includes(activity.started_on),
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
        permitPdfBody.includes(verificationViewUrl) &&
        permitPdfBody.includes(verificationUrl) &&
        permitPdfBody.includes('VERIFICATION BOUNDARY') &&
        permitPdfBody.includes('AUTHORITY BOUNDARY') &&
        permitPdfBody.includes(
            'Generated permit artifacts support authority review',
        );
    checks.push(
        check(
            'permit-pdf-verification-reference-visible',
            'Permit PDF contains exact verification reference, public page, and API URLs',
            true,
            permitPdfVisible,
            {
                url: `${targetBaseUrl}${manifest.resources.permit_pdf_url}`,
                status: permitPdfResponse.status(),
                content_type: permitPdfContentType,
                reference,
                public_page_url: verificationViewUrl,
                api_url: verificationUrl,
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

    await targetPage.goto(verificationViewUrl, { waitUntil: 'networkidle' });
    const publicPageReferenceVisible = await targetPage
        .getByText(reference, { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const publicPageArtifactOnlyVisible = await targetPage
        .getByText(/artifact only/i)
        .first()
        .isVisible()
        .catch(() => false);
    const publicPageNoReleaseVisible = await targetPage
        .getByText(/does not confirm permit release or legal effect/i)
        .first()
        .isVisible()
        .catch(() => false);
    const publicPageAuthorityBoundaryVisible = await targetPage
        .getByText('Authority boundary', { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const publicPageVisible =
        publicPageReferenceVisible &&
        publicPageArtifactOnlyVisible &&
        publicPageNoReleaseVisible &&
        publicPageAuthorityBoundaryVisible;
    checks.push(
        check(
            'public-verification-page-visible',
            'Public verification page shows artifact-only authority boundary',
            true,
            publicPageVisible,
            {
                url: verificationViewUrl,
                reference_visible: publicPageReferenceVisible,
                artifact_only_visible: publicPageArtifactOnlyVisible,
                no_release_visible: publicPageNoReleaseVisible,
                authority_boundary_visible: publicPageAuthorityBoundaryVisible,
            },
        ),
    );

    reportVerification(
        reference,
        publicStatus,
        canVerifyRelease,
        released,
        publicPageVisible,
        manifest.resources.permit_verification_url,
        manifest.resources.permit_verification_view_url,
    );
    await screenshot(
        targetPage,
        '04-permit-verification-boundary',
        'browser/screenshots/04-permit-verification-boundary.png',
    );

    await targetPage.setViewportSize({ width: 390, height: 844 });
    await targetPage.goto(verificationViewUrl, { waitUntil: 'networkidle' });
    const publicPageMobileReferenceVisible = await targetPage
        .getByText(reference, { exact: true })
        .first()
        .isVisible()
        .catch(() => false);
    const publicPageMobileNoReleaseVisible = await targetPage
        .getByText(/does not confirm permit release or legal effect/i)
        .first()
        .isVisible()
        .catch(() => false);
    const publicPageMobileNoHorizontalOverflow = await targetPage.evaluate(
        () => document.documentElement.scrollWidth <= window.innerWidth,
    );
    const publicPageMobileVisible =
        publicPageMobileReferenceVisible &&
        publicPageMobileNoReleaseVisible &&
        publicPageMobileNoHorizontalOverflow;
    checks.push(
        check(
            'public-verification-page-mobile-visible',
            'Public verification page keeps essential artifact boundary visible on mobile',
            true,
            publicPageMobileVisible,
            {
                url: verificationViewUrl,
                reference_visible: publicPageMobileReferenceVisible,
                no_release_visible: publicPageMobileNoReleaseVisible,
                no_horizontal_overflow: publicPageMobileNoHorizontalOverflow,
            },
        ),
    );
    verificationEvidence.public_page_mobile_visible = publicPageMobileVisible;
    await screenshot(
        targetPage,
        '04b-mobile-permit-verification-boundary',
        'browser/screenshots/04b-mobile-permit-verification-boundary.png',
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
        passed:
            Object.is(expected, actual) ||
            JSON.stringify(expected) === JSON.stringify(actual),
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
    publicPageVisible = false,
    apiUrl = null,
    publicPageUrl = null,
) {
    verificationEvidence.reference = reference;
    verificationEvidence.api_url = apiUrl;
    verificationEvidence.public_page_url = publicPageUrl;
    verificationEvidence.public_status = publicStatus;
    verificationEvidence.can_verify_release = canVerifyRelease;
    verificationEvidence.released = released;
    verificationEvidence.public_page_visible = publicPageVisible;
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

function reportPaymentPolicyBoundary(
    status,
    installmentVisible,
    dueDateVisible,
    surchargeVisible,
    pilVisible,
) {
    paymentPolicyBoundaryEvidence.status = status;
    paymentPolicyBoundaryEvidence.can_calculate_surcharge = false;
    paymentPolicyBoundaryEvidence.can_calculate_interest = false;
    paymentPolicyBoundaryEvidence.can_validate_pil = false;
    paymentPolicyBoundaryEvidence.can_calculate_deficiency_tax = false;
    paymentPolicyBoundaryEvidence.can_split_installments = false;
    paymentPolicyBoundaryEvidence.can_assign_statutory_due_dates = false;
    paymentPolicyBoundaryEvidence.installment_visible = installmentVisible;
    paymentPolicyBoundaryEvidence.due_date_visible = dueDateVisible;
    paymentPolicyBoundaryEvidence.surcharge_visible = surchargeVisible;
    paymentPolicyBoundaryEvidence.pil_visible = pilVisible;
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

function uiLabel(value) {
    return String(value).replaceAll('_', ' ');
}

function normalizedText(value) {
    return String(value ?? '')
        .replaceAll(/\s+/g, ' ')
        .trim();
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
        boundaryVisible &&
        onlineNoVisible &&
        reconciliationVisible &&
        policyVisible
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
