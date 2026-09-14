<?php

use App\Actions\BuildCitizenCurrentQrPhAttempt;
use App\Actions\InitiateQrPhPayment;
use App\Actions\ResolveActivePaymentAttempt;
use App\Actions\SimulateAuthorizedQrPhPayment;
use App\Assessment\AssessmentSnapshotFingerprint;
use App\Enums\AssessmentDecisionAction;
use App\Enums\AssessmentStatus;
use App\Enums\FeeRuleCategory;
use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitApplicationStatus;
use App\Enums\StakeholderPreviewPersona;
use App\Enums\UserPermission;
use App\Exceptions\XChangePartnerApiException;
use App\Models\Assessment;
use App\Models\AssessmentDecision;
use App\Models\AssessmentLine;
use App\Models\PaymentSchedule;
use App\Models\PaymentScheduleLine;
use App\Models\PermitApplication;
use App\Models\TreasuryCollection;
use App\Models\User;
use App\Models\XChangePayment;
use App\Models\XChangePaymentAttempt;
use App\StakeholderPreview\StakeholderPreviewSafety;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function handoffFixture(): array
{
    Http::preventStrayRequests();
    $actor = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewPaymentSchedules, UserPermission::RecordCollections]);
    $safety = Mockery::mock(StakeholderPreviewSafety::class)->makePartial();
    $safety->shouldReceive('isEnabled')->andReturn(true);
    $safety->shouldReceive('personaFor')->with($actor)->andReturn(StakeholderPreviewPersona::Cashier);
    app()->instance(StakeholderPreviewSafety::class, $safety);
    config(['app.url' => 'https://bpls-stakeholder-preview-uat-uat-5wn03n.laravel.cloud']);
    $application = PermitApplication::factory()->withStatus(PermitApplicationStatus::PendingPayment)->create([
        'id' => 291, 'status' => PermitApplicationStatus::PendingPayment,
        'metadata' => [],
    ]);
    $assessment = Assessment::factory()->for($application)->create([
        'id' => 215, 'status' => AssessmentStatus::Computed, 'total_amount_cents' => 417500,
    ]);
    $line = AssessmentLine::factory()->for($assessment)->create(['amount_cents' => 417500, 'category' => FeeRuleCategory::Fee]);
    AssessmentDecision::factory()->for($assessment)->create([
        'action' => AssessmentDecisionAction::Approved, 'total_amount_cents' => 417500,
        'assessment_snapshot_hash' => app(AssessmentSnapshotFingerprint::class)->hash($assessment->fresh()),
    ]);
    $schedule = PaymentSchedule::factory()->for($application, 'permitApplication')->for($assessment)->create([
        'id' => 63, 'status' => PaymentScheduleStatus::Pending, 'total_amount_cents' => 417500, 'paid_amount_cents' => 0,
    ]);
    PaymentScheduleLine::factory()->for($schedule)->create([
        'assessment_line_id' => $line->id, 'amount_cents' => 417500, 'paid_amount_cents' => 0, 'category' => FeeRuleCategory::Fee,
    ]);
    $payment = XChangePayment::query()->forceCreate([
        'id' => 5,
        'payment_schedule_id' => $schedule->id, 'assessment_id' => $assessment->id,
        'external_reference' => 'synthetic-handoff', 'issue_idempotency_key' => Str::uuid(),
        'terms_hash' => hash('sha256', 'synthetic'), 'amount_cents' => 417500,
        'currency' => 'PHP', 'binding_secret' => '123456', 'status' => 'awaiting_payment', 'pay_code' => 'TEST',
    ]);

    $application->update(['metadata' => ['nelson_reconciliation_v1' => ['commissioned_path' => true]]]);

    return [$actor, $schedule->fresh(), $payment];
}

function handoffAttempt(XChangePayment $payment, int $seconds = 600, string $status = 'awaiting_payment'): XChangePaymentAttempt
{
    return $payment->attempts()->create([
        'idempotency_key' => Str::uuid(), 'reference' => (string) Str::ulid(),
        'status' => $status, 'provider' => 'synthetic', 'amount_cents' => 417500,
        'expires_at' => now()->addSeconds($seconds),
    ]);
}

test('ordinary Cashier and Citizen share the unique replacement and reads preserve all evidence', function () {
    [$actor, $schedule, $payment] = handoffFixture();
    $old = handoffAttempt($payment, -60);
    $active = handoffAttempt($payment);
    $before = [$schedule->fresh()->getRawOriginal(), $payment->fresh()->getRawOriginal(), $old->fresh()->getRawOriginal(), $active->fresh()->getRawOriginal()];
    for ($i = 0; $i < 2; $i++) {
        $this->actingAs($actor)->get(route('staff.payment-schedules.show', $schedule))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('classicPaymentHandoff.payment_id', $payment->id)
                ->where('classicPaymentHandoff.attempt.id', $active->id)
                ->where('classicPaymentHandoff.amount_cents', 417500)
                ->where('classicPaymentHandoff.resolution', 'active')
                ->where('can.initiate_qr_ph', false)
                ->where('can.simulate_classic_payment', true));
        $citizen = app(BuildCitizenCurrentQrPhAttempt::class)->handle($schedule->fresh());
        expect($citizen['payment_id'])->toBe($payment->id)
            ->and($citizen['attempt_id'])->toBe($active->id)
            ->and($citizen['expires_at'])->toBe($active->expires_at->toIso8601String())
            ->and($citizen['amount_cents'])->toBe(417500);
    }
    expect([$schedule->fresh()->getRawOriginal(), $payment->fresh()->getRawOriginal(), $old->fresh()->getRawOriginal(), $active->fresh()->getRawOriginal()])->toBe($before)
        ->and(XChangePayment::count())->toBe(1)->and($payment->attempts()->count())->toBe(2);
    Http::assertNothingSent();
});

test('expiry is exclusive and replacement history is never actionable', function (int $seconds, string $expected) {
    [, , $payment] = handoffFixture();
    $this->freezeTime();
    handoffAttempt($payment, $seconds);
    expect(app(ResolveActivePaymentAttempt::class)->handle($payment)['state'])->toBe($expected);
})->with([[1, 'active'], [0, 'none'], [-1, 'none']]);

test('ambiguous active attempts fail closed in both projection and confirmation', function () {
    [$actor, $schedule, $payment] = handoffFixture();
    $first = handoffAttempt($payment);
    handoffAttempt($payment);
    $this->actingAs($actor)->get(route('staff.payment-schedules.show', $schedule))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('classicPaymentHandoff.resolution', 'needs_review')
            ->where('can.simulate_classic_payment', false)->where('can.initiate_qr_ph', false));
    expect(app(BuildCitizenCurrentQrPhAttempt::class)->handle($schedule))->toBeNull();
    expect(fn () => app(SimulateAuthorizedQrPhPayment::class)->handle($schedule, $actor, $first->id))->toThrow(LogicException::class);
    expect(TreasuryCollection::count())->toBe(0)->and($payment->attempts()->count())->toBe(2);
});

test('exact active simulation creates one Collection and duplicate returns the same evidence', function () {
    [$actor, $schedule, $payment] = handoffFixture();
    $old = handoffAttempt($payment, -60);
    $attempt = handoffAttempt($payment);
    $hash = app(AssessmentSnapshotFingerprint::class)->hash($schedule->assessment);
    $action = app(SimulateAuthorizedQrPhPayment::class);
    $collection = $action->handle($schedule, $actor, $attempt->id);
    expect($action->handle($schedule, $actor, $attempt->id)->id)->toBe($collection->id)
        ->and(TreasuryCollection::count())->toBe(1)
        ->and($collection->amount_cents)->toBe(417500)
        ->and($schedule->fresh()->paid_amount_cents)->toBe(417500)
        ->and($payment->fresh()->treasury_collection_id)->toBe($collection->id)
        ->and(app(AssessmentSnapshotFingerprint::class)->hash($schedule->assessment->fresh()))->toBe($hash)
        ->and($old->fresh()->status)->toBe('awaiting_payment');
    Http::assertNothingSent();
});

test('expired superseded and mismatched requests cannot collect', function (string $kind) {
    [$actor, $schedule, $payment] = handoffFixture();
    $attempt = handoffAttempt($payment, $kind === 'expired' ? -1 : 600, $kind === 'superseded' ? 'superseded' : 'awaiting_payment');
    if ($kind === 'mismatched') {
        $attempt->update(['amount_cents' => 1]);
    }
    expect(fn () => app(SimulateAuthorizedQrPhPayment::class)->handle($schedule, $actor, $attempt->id))->toThrow(LogicException::class);
    expect(TreasuryCollection::count())->toBe(0);
})->with(['expired', 'superseded', 'mismatched']);

test('stale confirmation does not collect an active replacement', function () {
    [$actor, $schedule, $payment] = handoffFixture();
    $old = handoffAttempt($payment, -1);
    handoffAttempt($payment);
    expect(fn () => app(SimulateAuthorizedQrPhPayment::class)->handle($schedule, $actor, $old->id))->toThrow(LogicException::class);
    expect(TreasuryCollection::count())->toBe(0);
});

test('simulation HTTP boundary requires exact attempt and rejects unauthorized actor', function () {
    [$actor, $schedule, $payment] = handoffFixture();
    handoffAttempt($payment);
    $this->actingAs($actor)->post(route('staff.payment-schedules.classic-payment-simulation.store', $schedule))->assertSessionHasErrors('attempt_id');
    $other = User::factory()->create();
    $other->givePermissionTo([UserPermission::AccessStaff->value, UserPermission::ViewPaymentSchedules->value]);
    $this->actingAs($other)->post(route('staff.payment-schedules.classic-payment-simulation.store', $schedule), ['attempt_id' => 1])->assertForbidden();
    expect(TreasuryCollection::count())->toBe(0);
});

test('ordinary simulation cannot escape the exact commissioned environment', function () {
    [$actor, $schedule, $payment] = handoffFixture();
    $attempt = handoffAttempt($payment);
    config(['app.url' => 'https://production.example.test']);
    $this->actingAs($actor)->post(route('staff.payment-schedules.classic-payment-simulation.store', $schedule), ['attempt_id' => $attempt->id])->assertForbidden();
    expect(TreasuryCollection::count())->toBe(0);
});

test('ambiguity blocks Citizen initiation before another attempt is created', function () {
    [, $schedule, $payment] = handoffFixture();
    handoffAttempt($payment);
    handoffAttempt($payment);
    $terms = hash('sha256', implode('|', [63, 215, app(AssessmentSnapshotFingerprint::class)->hash($schedule->assessment), 417500, 'PHP']));
    $payment->update(['terms_hash' => $terms]);
    expect(fn () => app(InitiateQrPhPayment::class)->handle($schedule))->toThrow(XChangePartnerApiException::class);
    expect($payment->attempts()->count())->toBe(2)->and(XChangePayment::count())->toBe(1);
    Http::assertNothingSent();
});

test('an unfinished request is not a confirmable active QR', function () {
    [, $schedule, $payment] = handoffFixture();
    $attempt = handoffAttempt($payment);
    $attempt->update(['reference' => null, 'expires_at' => null, 'status' => 'requested']);
    expect(app(ResolveActivePaymentAttempt::class)->handle($payment)['state'])->toBe('pending_request')
        ->and(app(BuildCitizenCurrentQrPhAttempt::class)->handle($schedule))->toBeNull();
});

test('Cashier cannot reinitiate an existing payable', function () {
    [$actor, $schedule, $payment] = handoffFixture();
    handoffAttempt($payment);
    $this->actingAs($actor)->postJson(route('staff.payment-schedules.qr-ph.initiate', $schedule))->assertConflict();
    expect($payment->attempts()->count())->toBe(1)->and(XChangePayment::count())->toBe(1);
    Http::assertNothingSent();
});
