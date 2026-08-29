import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    applicabilityLabel,
    applicationTypeLabel,
    inspectionModeLabels,
    itemTypeLabel,
    officeLabel,
    plainSentence,
    pricingBasisSummary,
    readinessBlocker,
    readinessBlockers,
    resolutionLabel,
    revisionActionLabel,
    sourceLabel,
} from '../../resources/js/lib/evaluationPresentation.ts';

const items = [
    {
        key: 'applicant.lines_of_business',
        label: 'Line(s) of Business',
        responsible_party: 'applicant',
    },
    {
        key: 'health.determination',
        label: 'Health applicability and clearance determination',
        responsible_party: 'health',
    },
    {
        key: 'engineering.charge',
        label: 'Engineering evaluation charge',
        responsible_party: 'engineering',
    },
];

const charges = [
    {
        code: 'EVAL-UAT-BASE-1',
        name: 'Evaluator UAT base proposal',
        basis: 'none',
        basis_amount_cents: 0,
    },
];

/**
 * Every sentence `App\Evaluation\BusinessPermitEvaluationReadiness` can
 * emit. These are canonical backend values; the presentation layer must
 * reword them without leaking their internal notation.
 */
const canonicalIssues = [
    'Evaluation has no current version.',
    'Required applicant facts are not lodged because the permit application is not submitted.',
    'At least one valid resolved Line of Business is required.',
    'Required applicant financial declarations are incomplete or invalid.',
    'Required applicability is undetermined for [health.determination].',
    'Required item [health.determination] is unresolved.',
    'Required item [engineering.charge] is awaiting_responsible_confirmation.',
    'Required item [engineering.charge] is superseded.',
    'Required inspection/review is incomplete for [engineering.charge].',
    'Targeted return remains unresolved for [health.determination].',
    'Applicable charge [engineering.charge] has no resolved non-negative amount; undefined is not zero.',
    'Charge [engineering.charge] duplicates the canonical FeeRule path.',
    'Applicable charge [engineering.charge] has no accepted commissioned source or procedure.',
    'Applicable charge [engineering.charge] is provisional_uat and cannot establish production liability.',
    'Projected charge [EVAL-UAT-BASE-1] is provisional_uat and cannot establish production liability.',
    'Selected pricing rule is blocked or ambiguous: Fee rule [EVAL-UAT-BASE-1] requires basis [declared_gross_sales] but no application line was supplied.',
    'Evaluation fingerprint is stale and must be refreshed before assessment.',
    'Required Treasury counter-check is not complete for the current Evaluation version.',
];

test('no canonical readiness issue leaks internal notation to a stakeholder', () => {
    for (const blocker of readinessBlockers(canonicalIssues, items, charges)) {
        assert.equal(
            /[[\]]/.test(blocker),
            false,
            `bracketed notation survived: ${blocker}`,
        );
        assert.equal(
            /[a-z0-9]_[a-z0-9]/.test(blocker),
            false,
            `snake_case token survived: ${blocker}`,
        );
        assert.equal(
            blocker.includes('provisional_uat'),
            false,
            `raw source token survived: ${blocker}`,
        );
        assert.equal(blocker.trim().length > 0, true);
    }
});

test('readiness copy stays 1:1 with the canonical issues', () => {
    const blockers = readinessBlockers(canonicalIssues, items, charges);

    assert.equal(blockers.length, canonicalIssues.length);
    assert.equal(readinessBlockers([], items, charges).length, 0);
});

test('an outstanding responsibility names the office and the item', () => {
    assert.equal(
        readinessBlocker(
            'Required item [health.determination] is unresolved.',
            items,
            charges,
        ),
        'Awaiting Health: Health applicability and clearance determination.',
    );
    assert.equal(
        readinessBlocker(
            'Required item [engineering.charge] is awaiting_responsible_confirmation.',
            items,
            charges,
        ),
        'Awaiting Engineering confirmation: Engineering evaluation charge.',
    );
    assert.equal(
        readinessBlocker(
            'Required applicability is undetermined for [health.determination].',
            items,
            charges,
        ),
        'Health has not yet decided whether Health applicability and clearance determination applies.',
    );
});

test('a superseded responsibility reads as work to record again', () => {
    assert.equal(
        readinessBlocker(
            'Required item [engineering.charge] is superseded.',
            items,
            charges,
        ),
        'Engineering evaluation charge must be recorded again by Engineering because the Evaluation changed.',
    );
});

test('an unrecorded charge amount is never presented as zero', () => {
    const blocker = readinessBlocker(
        'Applicable charge [engineering.charge] has no resolved non-negative amount; undefined is not zero.',
        items,
        charges,
    );

    assert.equal(
        blocker,
        'Engineering has not recorded an amount for Engineering evaluation charge. An unrecorded amount is not ₱0.00.',
    );
});

test('provisional UAT evidence is named in municipal language', () => {
    assert.equal(
        readinessBlocker(
            'Projected charge [EVAL-UAT-BASE-1] is provisional_uat and cannot establish production liability.',
            items,
            charges,
        ),
        'Evaluator UAT base proposal rests on provisional UAT evidence, which cannot create a real amount due.',
    );
});

test('internal fingerprint and counter-check wording becomes municipal', () => {
    assert.equal(
        readinessBlocker(
            'Evaluation fingerprint is stale and must be refreshed before assessment.',
            items,
            charges,
        ),
        'The Evaluation dependencies changed. Refresh the Evaluation before preparing an Assessment.',
    );
    assert.equal(
        readinessBlocker(
            'Required Treasury counter-check is not complete for the current Evaluation version.',
            items,
            charges,
        ),
        'Treasury has not completed the counter-check for this Evaluation version.',
    );
});

test('a blocked pricing rule keeps its detail without machine notation', () => {
    const blocker = readinessBlocker(
        'Selected pricing rule is blocked or ambiguous: Fee rule [EVAL-UAT-BASE-1] requires basis [declared_gross_sales] but no application line was supplied.',
        items,
        charges,
    );

    assert.equal(
        blocker,
        'Municipal pricing needs review: Fee rule EVAL-UAT-BASE-1 requires basis Declared gross sales but no application line was supplied.',
    );
});

test('an unrecognized issue is reworded rather than dropped', () => {
    const blocker = readinessBlocker(
        'Some future check failed for [health.determination] with state awaiting_responsible_confirmation.',
        items,
        charges,
    );

    assert.equal(/[[\]]/.test(blocker), false);
    assert.equal(/[a-z0-9]_[a-z0-9]/.test(blocker), false);
    assert.equal(
        blocker.includes('Health applicability and clearance determination'),
        true,
    );
    assert.equal(blocker.includes('Awaiting office confirmation'), true);
});

test('plain sentences humanize unknown identifiers without inventing meaning', () => {
    assert.equal(
        plainSentence('Item [some.future_item] is pending.'),
        'Item Some future item is pending.',
    );
    assert.equal(
        plainSentence('Rule [EVAL-UAT-1] failed.'),
        'Rule EVAL-UAT-1 failed.',
    );
});

test('a fee rule with no configured basis shows no basis amount', () => {
    const summary = pricingBasisSummary({
        code: 'EVAL-UAT-BASE-1',
        name: 'Evaluator UAT base proposal',
        basis: 'none',
        basis_amount_cents: 0,
    });

    assert.equal(summary.label, 'No configured pricing basis');
    assert.equal(summary.amountCents, null);
});

test('a configured basis keeps its label and its real amount, including zero', () => {
    assert.deepEqual(
        pricingBasisSummary({
            code: 'BUS-TAX',
            name: 'Business tax',
            basis: 'declared_gross_sales',
            basis_amount_cents: 750_000,
        }),
        { label: 'Declared gross sales', amountCents: 750_000 },
    );
    assert.deepEqual(
        pricingBasisSummary({
            code: 'BUS-TAX',
            name: 'Business tax',
            basis: 'capital_investment',
            basis_amount_cents: 0,
        }),
        { label: 'Capital investment', amountCents: 0 },
    );
});

test('office labels use municipal names, not role codes', () => {
    assert.equal(officeLabel('health'), 'Health');
    assert.equal(officeLabel('engineering'), 'Engineering');
    assert.equal(officeLabel('bplo'), 'BPLO');
    assert.equal(officeLabel('menro'), 'MENRO');
    assert.equal(officeLabel('mayor_office'), "Mayor's Office");
    assert.equal(officeLabel('applicant'), 'Applicant');
    assert.equal(officeLabel(null), 'Concerned office');
});

test('canonical enum values read as municipal vocabulary', () => {
    assert.equal(sourceLabel('provisional_uat'), 'Provisional UAT evidence');
    assert.equal(sourceLabel('governed_rule'), 'Municipal fee rule');
    assert.equal(
        sourceLabel('board_operational_recollection'),
        'Board operational recollection',
    );
    assert.equal(sourceLabel(null), 'Not recorded');
    assert.equal(
        revisionActionLabel('authorized_determination'),
        'Authorized determination',
    );
    assert.equal(revisionActionLabel('proposal'), 'System proposal');
    assert.equal(applicabilityLabel('not_applicable'), 'Not applicable');
    assert.equal(applicabilityLabel('undetermined'), 'Not yet determined');
    assert.equal(itemTypeLabel('determination'), 'Municipal determination');
    assert.equal(
        resolutionLabel('awaiting_responsible_confirmation'),
        'Awaiting office confirmation',
    );
    assert.equal(applicationTypeLabel('new'), 'New');
    assert.deepEqual(Object.keys(inspectionModeLabels), [
        'physical',
        'virtual',
        'document_review',
    ]);
});

test('an unmapped enum value still cannot surface as a raw token', () => {
    assert.equal(
        sourceLabel('future_municipal_source'),
        'Future municipal source',
    );
    assert.equal(officeLabel('future_office'), 'Future office');
});
