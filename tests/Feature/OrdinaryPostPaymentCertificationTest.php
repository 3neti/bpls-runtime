<?php

use App\Actions\AuthorizePostPaymentCertification;
use App\Actions\BuildMunicipalWorkInbox;
use App\Actions\CommissionPostPaymentOfficeCertifications;
use App\Actions\IssueManualCollectionReceipt;
use App\Actions\ProjectPermitReadiness;
use App\Assessment\AssessmentSnapshotFingerprint;
use App\Models\InstitutionalPositionAssignment;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../Support/OrdinaryCertificationFixture.php';

test('final reconciled receipt commissions exactly the actual routed office tasks without a Laboratory', function () {
    [$a, $c, $s, $assessment] = ordinaryCertificationFixture(false);
    expect($a->postPaymentOfficeCertifications()->count())->toBe(0);
    app(IssueManualCollectionReceipt::class)->handle($c, ['receipt_group_key' => 'treasury:application', 'receipt_number' => '7900006', 'numbering_authority' => 'manual']);
    $hash = app(AssessmentSnapshotFingerprint::class)->hash($assessment);
    $financialBefore = [$c->fresh()->getRawOriginal(), $s->fresh()->getRawOriginal(), $c->receipts()->orderBy('id')->get()->map->getRawOriginal()->all(), $c->allocations()->orderBy('id')->get()->map->getRawOriginal()->all()];
    foreach (['assessor', 'engineering', 'health', 'menro'] as $office) {
        $actor = certificationOfficer($office);
        $cert = $a->postPaymentOfficeCertifications()->where('office_code', $office)->sole();
        expect($cert->receipt->receipt_group_key)->toBe('office:'.$office)
            ->and(app(BuildMunicipalWorkInbox::class)->handle($actor)['items']->where('task_type', 'post_payment_certification'))->toHaveCount(1);
        $this->actingAs($actor)->get(route('staff.post-payment-certifications.show', $cert))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('post-payment-certifications/Show')->where('certification.receipt_number', $cert->receipt->receipt_number));
        $this->post(route('staff.post-payment-certifications.store', $cert), ['result' => 'certified', 'remarks' => 'Reviewed synthetic bound OR.'])->assertRedirect();
        $before = $cert->fresh()->getRawOriginal();
        $this->post(route('staff.post-payment-certifications.store', $cert), ['result' => 'certified'])->assertRedirect();
        expect($cert->fresh()->getRawOriginal())->toBe($before)
            ->and(app(BuildMunicipalWorkInbox::class)->handle($actor)['items']->where('task_type', 'post_payment_certification'))->toHaveCount(0);
    }
    app(CommissionPostPaymentOfficeCertifications::class)->handle($a);
    $readiness = app(ProjectPermitReadiness::class)->handle($a->fresh());
    expect($a->postPaymentOfficeCertifications()->count())->toBe(4)
        ->and($readiness['prerequisites']['all_required_post_payment_certifications'])->toBeTrue()
        ->and($readiness['ready'])->toBeFalse()
        ->and($readiness['blocked_by'])->toContain('synthetic_issuance_authority_available')
        ->and(app(AssessmentSnapshotFingerprint::class)->hash($assessment->fresh()))->toBe($hash)
        ->and($c->fresh()->amount_cents)->toBe(417500)
        ->and($c->receipts()->sum('amount_cents'))->toBe(417500);
    expect([$c->fresh()->getRawOriginal(), $s->fresh()->getRawOriginal(), $c->receipts()->orderBy('id')->get()->map->getRawOriginal()->all(), $c->allocations()->orderBy('id')->get()->map->getRawOriginal()->all()])->toBe($financialBefore);
});

test('wrong office and unrouted actor cannot certify and obtain no task', function () {
    [$a] = ordinaryCertificationFixture();
    $cert = $a->postPaymentOfficeCertifications()->where('office_code', 'assessor')->sole();
    foreach (['engineering', 'mpdo', 'admin'] as $office) {
        $actor = certificationOfficer($office);
        $this->actingAs($actor)->get(route('staff.post-payment-certifications.show', $cert))->assertForbidden();
        $this->post(route('staff.post-payment-certifications.store', $cert), ['result' => 'certified'])->assertForbidden();
        if ($office !== 'engineering') {
            expect(app(BuildMunicipalWorkInbox::class)->handle($actor)['items']->where('task_type', 'post_payment_certification'))->toHaveCount(0);
        }
    }
    expect($cert->fresh()->status)->toBe('pending');
});

test('incomplete receipt coverage cannot commission', function () {
    [$a] = ordinaryCertificationFixture(false);
    expect(fn () => app(CommissionPostPaymentOfficeCertifications::class)->handle($a))->toThrow(LogicException::class);
    expect($a->postPaymentOfficeCertifications()->count())->toBe(0);
});

test('execution rechecks office receipt routing and payment evidence', function (string $damage) {
    [$a, $c, $s] = ordinaryCertificationFixture();
    $actor = certificationOfficer('assessor');
    $cert = $a->postPaymentOfficeCertifications()->where('office_code', 'assessor')->sole();
    expect(app(AuthorizePostPaymentCertification::class)->allows($cert, $actor))->toBeTrue();
    match ($damage) {
        'wrong_or' => $cert->update(['receipt_id' => $c->receipts()->where('receipt_group_key', 'office:health')->sole()->id]),
        'unpaid' => $s->update(['paid_amount_cents' => 0]),
        'unbound_allocation' => $c->allocations()->first()->update(['receipt_id' => null]),
        'unrouted' => $a->bploRoutingDetermination->works()->where('office_code', 'assessor')->update(['office_code' => 'other']),
        'revoked_actor' => InstitutionalPositionAssignment::where('user_id', $actor->id)->update(['status' => 'ended', 'ended_at' => now()]),
        'disabled_uat' => config(['stakeholder_preview.mode' => false]),
        'wrong_work_binding' => $cert->update(['routing_work_ids' => [999999]]),
        'historical_target' => config(['app.url' => 'https://historical-uat.laravel.cloud']),
        'production_integrations' => config(['stakeholder_preview.production_integrations' => 'enabled']),
        'voided_collection' => $c->update(['status' => 'voided']),
        'voided_schedule' => $s->update(['status' => 'voided']),
    };
    $this->actingAs($actor)->post(route('staff.post-payment-certifications.store', $cert), ['result' => 'certified'])->assertForbidden();
    expect($cert->fresh()->status)->toBe('pending');
})->with(['wrong_or', 'unpaid', 'unbound_allocation', 'unrouted', 'revoked_actor', 'disabled_uat', 'wrong_work_binding', 'historical_target', 'production_integrations', 'voided_collection', 'voided_schedule']);
