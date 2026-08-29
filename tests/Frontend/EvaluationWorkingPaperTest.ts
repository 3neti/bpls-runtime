import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    componentReconciliation,
    componentToneClasses,
    financialComponents,
    latestChange,
    money,
} from '../../resources/js/lib/evaluationPresentation.ts';
import type {
    WorkingPaperCharge,
    WorkingPaperEvaluation,
    WorkingPaperItem,
    WorkingPaperRevision,
} from '../../resources/js/lib/evaluationPresentation.ts';

/** The governed municipal charge the deterministic fixture always supplies. */
function governedCharge(
    overrides: Partial<WorkingPaperCharge> = {},
): WorkingPaperCharge {
    return {
        key: 'rule.1.line.none',
        fee_rule_id: 1,
        code: 'EVAL-UAT-BASE',
        name: 'Evaluator UAT base proposal',
        amount_cents: 10_000,
        basis: 'none',
        basis_amount_cents: 0,
        legal_basis: null,
        source_classification: 'governed_rule',
        ...overrides,
    };
}

function revision(
    overrides: Partial<WorkingPaperRevision> = {},
): WorkingPaperRevision {
    return {
        version_sequence: 1,
        action: 'proposal',
        applicability: 'applicable',
        value: { amount_cents: 12_500 },
        source_classification: 'governed_office_procedure',
        actor_name: 'Preview Assessment Officer',
        reason: 'System proposal for the office.',
        occurred_at: '2026-08-29T10:00:00+08:00',
        ...overrides,
    };
}

/** An office charge item: Engineering, proposed ₱125.00. */
function officeChargeItem(
    overrides: Partial<WorkingPaperItem> = {},
): WorkingPaperItem {
    return {
        id: 7,
        key: 'engineering.charge',
        label: 'Engineering evaluation charge',
        item_type: 'charge',
        responsible_party: 'engineering',
        is_required: true,
        is_mine: false,
        applicability: 'applicable',
        resolution: 'awaiting_responsible_confirmation',
        default_value: { amount_cents: 12_500 },
        default_source_classification: 'governed_office_procedure',
        resolved_value: { amount_cents: 12_500 },
        source_classification: 'governed_office_procedure',
        reason: null,
        occurred_at: '2026-08-29T10:00:00+08:00',
        inspection_required: true,
        history: [revision()],
        ...overrides,
    };
}

function evaluation(
    overrides: Partial<WorkingPaperEvaluation> = {},
): WorkingPaperEvaluation {
    return {
        current_evaluated_amount_cents: 10_000,
        projected_charges: [governedCharge()],
        items: [officeChargeItem()],
        ...overrides,
    };
}

test('the build-up lists governed pricing first, then office charges', () => {
    const components = financialComponents(evaluation());

    assert.equal(components.length, 2);
    assert.equal(components[0].origin, 'governed');
    assert.equal(components[0].label, 'Evaluator UAT base proposal');
    assert.equal(components[0].reference, 'EVAL-UAT-BASE');
    assert.equal(components[0].owner, 'Municipal system');
    assert.equal(components[1].origin, 'office');
    assert.equal(components[1].label, 'Engineering evaluation charge');
    assert.equal(components[1].owner, 'Engineering');
});

test('a governed charge is priced by the municipality and always counted', () => {
    const [governed] = financialComponents(evaluation());

    assert.equal(governed.proposalCents, 10_000);
    assert.equal(governed.resolvedCents, 10_000);
    assert.equal(governed.includedInTotal, true);
    assert.equal(governed.status.key, 'in_total');
    assert.equal(governed.whyItApplies, 'No configured pricing basis');
    assert.equal(governed.sourceLabel, 'Municipal fee rule');
});

test('an unconfirmed office charge keeps its proposal out of the total', () => {
    const [, office] = financialComponents(evaluation());

    assert.equal(office.proposalCents, 12_500);
    assert.equal(office.includedInTotal, false);
    assert.equal(office.status.key, 'awaiting_office');
    assert.equal(office.status.label, 'Awaiting Engineering');
});

test('a confirmed office charge joins the total and reconciles it', () => {
    const confirmed = evaluation({
        current_evaluated_amount_cents: 22_500,
        items: [
            officeChargeItem({
                resolution: 'resolved',
                history: [
                    revision(),
                    revision({ version_sequence: 2, action: 'confirmation' }),
                ],
            }),
        ],
    });
    const reconciliation = componentReconciliation(confirmed);

    assert.equal(reconciliation.included.length, 2);
    assert.equal(reconciliation.pending.length, 0);
    assert.equal(reconciliation.includedTotalCents, 22_500);
    assert.equal(reconciliation.canonicalTotalCents, 22_500);
    assert.equal(reconciliation.reconciled, true);
});

test('an overridden office charge shows the amount that was replaced and why', () => {
    const overridden = officeChargeItem({
        resolution: 'resolved',
        resolved_value: { amount_cents: 15_000 },
        history: [
            revision(),
            revision({
                version_sequence: 2,
                action: 'correction',
                value: { amount_cents: 15_000 },
                reason: 'Synthetic UAT conditions require a different office-resolved amount.',
                actor_name: 'Preview Engineering Officer',
            }),
        ],
    });
    const change = latestChange(overridden);

    assert.notEqual(change, null);
    assert.equal(change?.fromCents, 12_500);
    assert.equal(change?.toCents, 15_000);
    assert.equal(change?.actorName, 'Preview Engineering Officer');
    assert.equal(
        change?.reason,
        'Synthetic UAT conditions require a different office-resolved amount.',
    );

    const reconciliation = componentReconciliation(
        evaluation({
            current_evaluated_amount_cents: 25_000,
            items: [overridden],
        }),
    );

    assert.equal(reconciliation.includedTotalCents, 25_000);
    assert.equal(reconciliation.reconciled, true);
    assert.equal(reconciliation.components[1].change?.toCents, 15_000);
});

test('confirming the proposal unchanged is not reported as a change', () => {
    const confirmed = officeChargeItem({
        resolution: 'resolved',
        history: [
            revision(),
            revision({ version_sequence: 2, action: 'confirmation' }),
        ],
    });

    assert.equal(latestChange(confirmed), null);
});

test('a not-applicable office charge carries no amount and no pending work', () => {
    const notApplicable = evaluation({
        items: [
            officeChargeItem({
                applicability: 'not_applicable',
                resolution: 'resolved',
                resolved_value: null,
            }),
        ],
    });
    const reconciliation = componentReconciliation(notApplicable);
    const [, office] = reconciliation.components;

    assert.equal(office.resolvedCents, null);
    assert.equal(office.includedInTotal, false);
    assert.equal(office.status.key, 'not_applicable');
    assert.equal(reconciliation.pending.length, 0);
    assert.equal(reconciliation.includedTotalCents, 10_000);
    assert.equal(reconciliation.reconciled, true);
});

test('a superseded office charge asks to be recorded again', () => {
    const superseded = financialComponents(
        evaluation({
            items: [officeChargeItem({ resolution: 'superseded' })],
        }),
    );

    assert.equal(superseded[1].status.key, 'superseded');
    assert.equal(superseded[1].includedInTotal, false);
});

test('a build-up that cannot explain the canonical total says so', () => {
    const drifted = componentReconciliation(
        evaluation({ current_evaluated_amount_cents: 99_999 }),
    );

    assert.equal(drifted.reconciled, false);
    assert.equal(drifted.canonicalTotalCents, 99_999);
    assert.equal(drifted.includedTotalCents, 10_000);
});

test('non-charge responsibilities never enter the financial build-up', () => {
    const withDetermination = financialComponents(
        evaluation({
            items: [
                officeChargeItem(),
                officeChargeItem({
                    id: 9,
                    key: 'health.determination',
                    label: 'Health applicability and clearance determination',
                    item_type: 'determination',
                    responsible_party: 'health',
                }),
            ],
        }),
    );

    assert.equal(withDetermination.length, 2);
    assert.equal(withDetermination[1].label, 'Engineering evaluation charge');
});

test('an office charge with no proposal is shown as unproposed, not as zero', () => {
    const [, office] = financialComponents(
        evaluation({
            items: [officeChargeItem({ default_value: null })],
        }),
    );

    assert.equal(office.proposalCents, null);
});

test('the viewer own-work flag comes from the contract, not from the office name', () => {
    const [, notMine] = financialComponents(evaluation());
    const [, mine] = financialComponents(
        evaluation({ items: [officeChargeItem({ is_mine: true })] }),
    );

    assert.equal(notMine.isMine, false);
    assert.equal(mine.isMine, true);
});

test('amounts render as municipal pesos and tones stay distinguishable', () => {
    assert.equal(money(22_500), '₱225.00');
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
