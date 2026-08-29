import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import {
    componentToneClasses,
    latestChange,
    money,
    presentFinancialWorkingPaper,
} from '../../resources/js/lib/evaluationPresentation.ts';
import type {
    EvaluationFinancialWorkingPaper,
    EvaluationItem,
    EvaluationWorkingPaperCharge,
} from '../../resources/js/types/business-permit-evaluation.ts';

function charge(
    overrides: Partial<EvaluationWorkingPaperCharge> = {},
): EvaluationWorkingPaperCharge {
    return {
        identity: 'item:7',
        source_type: 'evaluation_item',
        evaluation_item_id: 7,
        fee_rule_id: null,
        scope: 'line_of_business',
        permit_application_line_id: 101,
        line_of_business_id: 11,
        code: 'HCERT',
        label: 'Health Certificate',
        responsible_party: 'health',
        proposal_amount_cents: 12_500,
        resolved_amount_cents: 15_000,
        applicability: 'applicable',
        resolution: 'resolved',
        source_classification: 'provisional_uat',
        action: 'correction',
        reason: 'Synthetic conditions required a corrected amount.',
        included_in_subtotal: true,
        included_in_grand_total: true,
        ...overrides,
    };
}

function item(overrides: Partial<EvaluationItem> = {}): EvaluationItem {
    return {
        id: 7,
        key: 'health.certificate.charge',
        label: 'Health Certificate',
        item_type: 'charge',
        responsible_party: 'health',
        is_required: true,
        requires_confirmation: true,
        is_mine: true,
        applicability: 'applicable',
        resolution: 'resolved',
        action: 'correction',
        default_value: { amount_cents: 12_500 },
        default_source_classification: 'provisional_uat',
        resolved_value: { amount_cents: 15_000 },
        source_classification: 'provisional_uat',
        reason: 'Synthetic conditions required a corrected amount.',
        occurred_at: '2026-08-30T10:10:00+08:00',
        inspection_required: true,
        history: [
            {
                version_sequence: 1,
                action: 'proposal',
                applicability: 'applicable',
                value: { amount_cents: 12_500 },
                source_classification: 'provisional_uat',
                actor_name: 'Preview Assessment Officer',
                reason: 'Synthetic proposal.',
                occurred_at: '2026-08-30T10:00:00+08:00',
            },
            {
                version_sequence: 2,
                action: 'correction',
                applicability: 'applicable',
                value: { amount_cents: 15_000 },
                source_classification: 'provisional_uat',
                actor_name: 'Preview Health Officer',
                reason: 'Synthetic conditions required a corrected amount.',
                occurred_at: '2026-08-30T10:10:00+08:00',
            },
        ],
        ...overrides,
    };
}

function workingPaper(
    overrides: Partial<EvaluationFinancialWorkingPaper> = {},
): EvaluationFinancialWorkingPaper {
    return {
        line_sections: [
            {
                line_of_business_id: 11,
                permit_application_line_id: 101,
                line_of_business_name: 'Retail trade',
                charges: [
                    charge(),
                    charge({
                        identity: 'rule:3:line:101',
                        source_type: 'fee_rule',
                        evaluation_item_id: null,
                        fee_rule_id: 3,
                        code: 'BUS-TAX',
                        label: 'Business Tax',
                        responsible_party: 'system',
                        proposal_amount_cents: 10_000,
                        resolved_amount_cents: 10_000,
                        source_classification: 'governed_rule',
                        action: 'proposal',
                        reason: null,
                    }),
                ],
                subtotal_amount_cents: 25_000,
            },
            {
                line_of_business_id: 22,
                permit_application_line_id: 102,
                line_of_business_name: 'Repair services',
                charges: [
                    charge({
                        identity: 'item:8',
                        evaluation_item_id: 8,
                        permit_application_line_id: 102,
                        line_of_business_id: 22,
                        code: 'SAN-PERMIT',
                        label: 'Sanitary Permit Fee',
                        responsible_party: 'health',
                        proposal_amount_cents: 8_500,
                        resolved_amount_cents: 8_500,
                        action: 'confirmation',
                        reason: null,
                    }),
                ],
                subtotal_amount_cents: 8_500,
            },
        ],
        application_charges: [
            charge({
                identity: 'item:9',
                evaluation_item_id: 9,
                scope: 'application',
                permit_application_line_id: null,
                line_of_business_id: null,
                code: 'MAYOR-PERMIT',
                label: "Mayor's Permit Fee",
                responsible_party: 'bplo',
                proposal_amount_cents: 10_000,
                resolved_amount_cents: 10_000,
                action: 'confirmation',
                reason: null,
            }),
        ],
        application_subtotal_amount_cents: 10_000,
        required_unresolved_charge_count: 0,
        grand_total_available: true,
        grand_total_amount_cents: 43_500,
        ...overrides,
    };
}

test('the canonical projection becomes LOB sections, charges, subtotals, and a Grand Total', () => {
    const presentation = presentFinancialWorkingPaper(workingPaper(), [item()]);

    assert.equal(presentation.lineSections.length, 2);
    assert.equal(presentation.lineSections[0].label, 'Retail trade');
    assert.equal(presentation.lineSections[0].charges.length, 2);
    assert.equal(presentation.lineSections[0].subtotalCents, 25_000);
    assert.equal(presentation.lineSections[1].label, 'Repair services');
    assert.equal(presentation.lineSections[1].subtotalCents, 8_500);
    assert.equal(presentation.applicationSection?.subtotalCents, 10_000);
    assert.equal(presentation.grandTotalCents, 43_500);
    assert.equal(presentation.grandTotalAvailable, true);
});

test('charge scope and subtotal membership come from the typed projection', () => {
    const presentation = presentFinancialWorkingPaper(workingPaper(), [item()]);
    const lineCharge = presentation.lineSections[0].charges[0];
    const applicationCharge = presentation.applicationSection?.charges[0];

    assert.equal(lineCharge.scope, 'line_of_business');
    assert.equal(lineCharge.includedInSubtotal, true);
    assert.equal(lineCharge.includedInGrandTotal, true);
    assert.equal(applicationCharge?.scope, 'application');
    assert.equal(applicationCharge?.label, "Mayor's Permit Fee");
});

test('an Evaluation Item remains the action and provenance anchor by exact id', () => {
    const presentation = presentFinancialWorkingPaper(workingPaper(), [item()]);
    const healthCharge = presentation.lineSections[0].charges[0];

    assert.equal(healthCharge.itemId, 7);
    assert.equal(healthCharge.isMine, true);
    assert.equal(healthCharge.owner, 'Health');
    assert.equal(healthCharge.proposalCents, 12_500);
    assert.equal(healthCharge.resolvedCents, 15_000);
    assert.equal(healthCharge.history.length, 2);
    assert.equal(healthCharge.change?.fromCents, 12_500);
    assert.equal(healthCharge.change?.toCents, 15_000);
    assert.equal(
        healthCharge.change?.reason,
        'Synthetic conditions required a corrected amount.',
    );
});

test('fee-rule charges use the exact projected identity without inventing an item', () => {
    const presentation = presentFinancialWorkingPaper(workingPaper(), [item()]);
    const governed = presentation.lineSections[0].charges[1];

    assert.equal(governed.key, 'rule:3:line:101');
    assert.equal(governed.sourceType, 'fee_rule');
    assert.equal(governed.feeRuleId, 3);
    assert.equal(governed.itemId, null);
    assert.equal(governed.owner, 'Municipal system');
    assert.equal(governed.sourceLabel, 'Municipal fee rule');
});

test('Not Applicable and unresolved charges keep backend inclusion decisions', () => {
    const notApplicable = charge({
        applicability: 'not_applicable',
        resolved_amount_cents: null,
        included_in_subtotal: false,
        included_in_grand_total: false,
    });
    const presentation = presentFinancialWorkingPaper(
        workingPaper({
            line_sections: [
                {
                    line_of_business_id: 11,
                    permit_application_line_id: 101,
                    line_of_business_name: 'Retail trade',
                    charges: [notApplicable],
                    subtotal_amount_cents: 0,
                },
            ],
            required_unresolved_charge_count: 1,
            grand_total_available: false,
            grand_total_amount_cents: null,
        }),
        [item({ applicability: 'not_applicable', resolved_value: null })],
    );
    const presentedCharge = presentation.lineSections[0].charges[0];

    assert.equal(presentedCharge.status.key, 'not_applicable');
    assert.equal(presentedCharge.includedInSubtotal, false);
    assert.equal(presentedCharge.includedInGrandTotal, false);
    assert.equal(presentation.requiredUnresolvedChargeCount, 1);
    assert.equal(presentation.grandTotalAvailable, false);
    assert.equal(presentation.grandTotalCents, null);
});

test('presentation preserves authoritative totals verbatim instead of summing charge rows', () => {
    const presentation = presentFinancialWorkingPaper(
        workingPaper({
            line_sections: [
                {
                    line_of_business_id: 11,
                    permit_application_line_id: 101,
                    line_of_business_name: 'Retail trade',
                    charges: [charge({ resolved_amount_cents: 1 })],
                    subtotal_amount_cents: 123_456,
                },
            ],
            application_subtotal_amount_cents: 654_321,
            grand_total_amount_cents: 777_777,
        }),
        [item()],
    );

    assert.equal(presentation.lineSections[0].subtotalCents, 123_456);
    assert.equal(presentation.applicationSection?.subtotalCents, 654_321);
    assert.equal(presentation.grandTotalCents, 777_777);
});

test('the Vue working paper renders backend sections and contains no amount reduction', () => {
    const page = readFileSync(
        new URL(
            '../../resources/js/pages/business-permit-evaluations/Show.vue',
            import.meta.url,
        ),
        'utf8',
    );

    assert.equal(page.includes('workingPaper.lineSections'), true);
    assert.equal(page.includes('workingPaper.applicationSection'), true);
    assert.equal(page.includes('LOB Subtotal'), true);
    assert.equal(page.includes('Application-wide subtotal'), true);
    assert.equal(page.includes('.reduce('), false);
});

test('amounts render as municipal pesos and tones stay distinguishable', () => {
    assert.equal(money(43_500), '₱435.00');
    assert.equal(money(0), '₱0.00');
    assert.notEqual(
        componentToneClasses('green'),
        componentToneClasses('amber'),
    );
    assert.notEqual(
        componentToneClasses('slate'),
        componentToneClasses('blue'),
    );
});

test('confirming unchanged remains distinct from a monetary correction', () => {
    const confirmed = item({
        history: [
            item().history[0],
            {
                ...item().history[1],
                action: 'confirmation',
                value: { amount_cents: 12_500 },
                reason: null,
            },
        ],
    });

    assert.equal(latestChange(confirmed), null);
    assert.notEqual(latestChange(item()), null);
});
