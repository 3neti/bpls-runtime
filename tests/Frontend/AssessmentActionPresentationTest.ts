import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { assessmentActionPresentation } from '../../resources/js/lib/assessmentActionPresentation.ts';
import type { EvaluationLatestAssessment } from '../../resources/js/types/business-permit-evaluation.ts';

const currentAssessment: EvaluationLatestAssessment = {
    id: 216,
    sequence: 1,
    total_amount_cents: 417_500,
    superseded: false,
    decision: null,
    evaluation_version_id: 1108,
    evaluation_fingerprint: 'current-fingerprint',
    consumes_current_evaluation: true,
};

test('current Assessment replaces stale pending state and prevents duplicate preparation', () => {
    assert.deepEqual(
        assessmentActionPresentation(currentAssessment, true, 999_999),
        {
            currentAssessmentId: 216,
            heading: 'Assessment #1',
            ownerLabel: 'Next actor — Treasury',
            statusLabel: 'Prepared',
            totalLabel: 'Assessment total',
            totalAmountCents: 417_500,
            description:
                'The immutable Assessment is prepared from this exact Evaluation. Treasury counter-check is next.',
            canPrepare: false,
        },
    );
});

test('genuinely pending state permits preparation only when canonical readiness is true', () => {
    const pending = assessmentActionPresentation(null, true, 417_500);

    assert.equal(pending.currentAssessmentId, null);
    assert.equal(pending.heading, 'Prepare Assessment');
    assert.equal(pending.statusLabel, 'Pending');
    assert.equal(pending.totalLabel, 'Current total');
    assert.equal(pending.totalAmountCents, 417_500);
    assert.equal(pending.canPrepare, true);
    assert.equal(
        assessmentActionPresentation(null, false, 417_500).canPrepare,
        false,
    );
});

test('an incomplete canonical amount remains unknown rather than becoming a zero assessment total', () => {
    const pending = assessmentActionPresentation(null, false, null);
    assert.equal(pending.totalAmountCents, null);
    assert.equal(pending.canPrepare, false);
    assert.equal(
        assessmentActionPresentation(currentAssessment, false, null)
            .totalAmountCents,
        417_500,
    );
});

test('superseded or stale-version Assessment never suppresses current preparation', () => {
    assert.equal(
        assessmentActionPresentation(
            { ...currentAssessment, superseded: true },
            true,
            417_500,
        ).canPrepare,
        true,
    );
    assert.equal(
        assessmentActionPresentation(
            { ...currentAssessment, consumes_current_evaluation: false },
            true,
            417_500,
        ).canPrepare,
        true,
    );
});

test('prepared Assessment advances its next-action copy with canonical counter-check and decision facts', () => {
    const counterChecked = assessmentActionPresentation(
        currentAssessment,
        true,
        417_500,
        true,
    );
    assert.equal(counterChecked.ownerLabel, 'Next actor — Municipal Treasurer');
    assert.match(counterChecked.description, /counter-check is complete/);

    const decided = assessmentActionPresentation(
        { ...currentAssessment, decision: 'approved' },
        true,
        417_500,
        true,
    );
    assert.equal(decided.ownerLabel, 'Assessment status');
    assert.match(decided.description, /decision is recorded/);
    assert.doesNotMatch(decided.description, /counter-check is next/);
});

test('evaluation action rail renders View Assessment instead of duplicate preparation for current state', () => {
    const source = readFileSync(
        'resources/js/pages/business-permit-evaluations/Show.vue',
        'utf8',
    );

    assert.match(source, /v-if="assessmentAction\.canPrepare"/);
    assert.match(
        source,
        /v-else-if="[\s\S]*?assessmentAction\.currentAssessmentId[\s\S]*?View Assessment/,
    );
    assert.match(source, /\{\{ assessmentAction\.statusLabel \}\}/);
    assert.doesNotMatch(source, /<dd class="mt-1 font-semibold">Pending<\/dd>/);
});
