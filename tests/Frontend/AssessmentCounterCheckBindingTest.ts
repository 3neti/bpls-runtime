import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

test('Assessment action and next actor consume canonical counter-check state', () => {
    const source = readFileSync('resources/js/pages/permit-applications/Assessments/Show.vue', 'utf8');
    assert.ok(source.includes("props.assessment.counter_check_state === 'awaiting_counter_check'"));
    assert.ok(source.includes("props.assessment.counter_check_state === 'incomplete'"));
    assert.ok(source.includes('Blocked · Evaluation binding unavailable'));
    assert.ok(source.includes('Open Treasury counter-check'));
});

test('visible counter-check form submits the displayed Assessment and Evaluation identities', () => {
    const source = readFileSync('resources/js/pages/business-permit-evaluations/Show.vue', 'utf8');
    const start = source.indexOf("runOnce('counter-check'");
    const form = source.slice(start, source.indexOf('function submitRefresh', start));
    assert.ok(form.includes('assessment_id: latestAssessment.value?.id'));
    assert.ok(form.includes('expected_version_sequence: props.evaluation!.version.sequence'));
    assert.ok(form.includes('expected_fingerprint: props.evaluation!.version.fingerprint'));
    assert.ok(form.includes('.post(counterCheck(props.application.id).url'));
});
