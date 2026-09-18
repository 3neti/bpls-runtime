import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { permitDetailPresentation } from '../../resources/js/lib/permitDetailPresentation.ts';

const application = {
    verification_boundary: { released: false, status: 'artifact_only' },
    latest_payment_schedule: {
        total_amount_cents: 397500,
        paid_amount_cents: 0,
    },
    has_business_permit_evaluation: true,
    can_continue: true,
};

test('only an unpaid schedule asks staff to receive payment', () => {
    assert.equal(
        permitDetailPresentation(application).nextAction,
        'Receive payment',
    );
    assert.equal(
        permitDetailPresentation({
            ...application,
            latest_payment_schedule: {
                total_amount_cents: 397500,
                paid_amount_cents: 397500,
            },
        }).nextAction,
        'Continue post-payment review',
    );
    assert.equal(
        permitDetailPresentation({
            ...application,
            latest_payment_schedule: null,
        }).nextAction,
        'Continue municipal review',
    );
    assert.equal(
        permitDetailPresentation({ ...application, can_continue: false })
            .nextAction,
        'No further processing',
    );
});

test('issued and released evidence takes precedence over stale application and payment hints without mutation', () => {
    const issued = {
        ...application,
        verification_boundary: {
            released: false,
            status: 'issued_synthetic_identity',
        },
    };
    assert.equal(permitDetailPresentation(issued).nextAction, 'BPLO release');
    assert.match(
        permitDetailPresentation(issued).authorityTitle,
        /release pending/,
    );
    const released = {
        ...issued,
        verification_boundary: {
            released: true,
            status: 'released_synthetic_identity',
        },
    };
    const before = JSON.stringify(released);
    assert.equal(
        permitDetailPresentation(released).nextAction,
        'View released Permit / verify',
    );
    assert.equal(
        permitDetailPresentation(released).authorityTitle,
        'Synthetic Permit released',
    );
    assert.equal(
        permitDetailPresentation(released).authorityStatus,
        'released_synthetic_identity',
    );
    assert.equal(JSON.stringify(released), before);
});

test('staff detail consumes canonical routing certification counts and authority statement rather than generic checklist', () => {
    const source = readFileSync(
        'resources/js/pages/permit-applications/Show.vue',
        'utf8',
    );
    assert.match(
        source,
        /v-if="permitApplication.uses_routing_certifications"/,
    );
    assert.match(source, /release_readiness.clearances_completed/);
    assert.match(source, /release_readiness.clearances_total/);
    assert.match(source, /<section\s+v-else[\s\S]*?Clearance checklist/);
    assert.match(source, /:title="detailPresentation.authorityTitle"/);
    assert.match(source, /authority_boundary\s*\.artifact_statement/);
    assert.doesNotMatch(source, /Ready for municipal review — not released/);
    assert.match(source, /Production municipal release authority/);
});
