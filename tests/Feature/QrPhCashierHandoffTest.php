<?php

use App\Actions\AuthorizeUatQrPhSimulation;
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
use App\Enums\UserPermission;
use App\Exceptions\XChangePartnerApiException;
use App\Models\Assessment;
use App\Models\AssessmentDecision;
use App\Models\AssessmentLine;
use App\Models\InstitutionalPosition;
use App\Models\InstitutionalPositionAssignment;
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
    $cashierRole = $actor->roles()->sole();
    $cashierRole->update(['code' => 'cashier', 'name' => 'cashier']);
    $position = InstitutionalPosition::factory()->for($cashierRole, 'capabilityRole')->create();
    InstitutionalPositionAssignment::query()->create([
        'user_id' => $actor->id, 'institutional_position_id' => $position->id,
        'status' => 'active', 'assigned_at' => now(), 'reason' => 'Synthetic Cashier authority fixture',
    ]);
    config([
        'app.url' => 'https://bpls-stakeholder-preview-uat-uat-5wn03n.laravel.cloud',
        'payment_simulation.commissioned' => true,
        'payment_simulation.context' => 'workflow_uat',
        'stakeholder_preview.mode' => true,
        'stakeholder_preview.production_migration_enabled' => false,
        'stakeholder_preview.production_integrations' => 'disabled',
    ]);
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

test('simulation uses municipal Cashier authority rather than a preview persona', function () {
    [$actor, $schedule, $payment] = handoffFixture();
    handoffAttempt($payment);
    expect(app(StakeholderPreviewSafety::class)->personaFor($actor))->toBeNull()
        ->and(app(AuthorizeUatQrPhSimulation::class)->available($schedule, $actor))->toBeTrue();
});

test('non Cashier positions cannot simulate even with collection permission', function (string $role) {
    [$actor, $schedule, $payment] = handoffFixture();
    $attempt = handoffAttempt($payment);
    $actor->roles()->sole()->update(['code' => $role, 'name' => $role]);
    $this->actingAs($actor)->get(route('staff.payment-schedules.show', $schedule))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.simulate_classic_payment', false)->where('classicPaymentSimulationUrl', null));
    $this->post(route('staff.payment-schedules.classic-payment-simulation.store', $schedule), ['attempt_id' => $attempt->id])->assertForbidden();
    expect(TreasuryCollection::count())->toBe(0);
})->with(['citizen', 'bplo', 'assessor', 'engineering', 'health', 'menro', 'treasury', 'municipal_treasurer', 'admin']);

test('simulation environment is explicit and fail closed', function (string $environment, string $url, bool $enabled, bool $migration, string $integrations, bool $allowed) {
    [$actor, $schedule, $payment] = handoffFixture();
    $attempt = handoffAttempt($payment);
    app()->detectEnvironment(fn () => $environment);
    config(['app.url' => $url, 'payment_simulation.commissioned' => $allowed,
        'payment_simulation.context' => $environment === 'local' ? 'gate10_local' : ($environment === 'staging' && $allowed ? 'workflow_uat' : 'unadmitted'), 'stakeholder_preview.mode' => $enabled,
        'stakeholder_preview.production_migration_enabled' => $migration,
        'stakeholder_preview.production_integrations' => $integrations]);
    $this->actingAs($actor)->get(route('staff.payment-schedules.show', $schedule))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.simulate_classic_payment', $allowed));
    if (! $allowed) {
        $this->withSession(['_token' => 'synthetic-csrf'])->post(route('staff.payment-schedules.classic-payment-simulation.store', $schedule), [
            'attempt_id' => $attempt->id, '_token' => 'synthetic-csrf',
        ])->assertForbidden();
    }
    expect(TreasuryCollection::count())->toBe(0);
})->with([
    'production' => ['production', 'https://bpls-stakeholder-preview-uat-uat-5wn03n.laravel.cloud', true, false, 'disabled', false],
    'historical UAT' => ['staging', 'https://historical-uat.example.test', true, false, 'disabled', false],
    'unapproved target' => ['staging', 'https://ordinary.example.test', true, false, 'disabled', false],
    'disabled preview' => ['staging', 'https://bpls-stakeholder-preview-uat-uat-5wn03n.laravel.cloud', false, false, 'disabled', false],
    'production migration enabled' => ['staging', 'https://bpls-stakeholder-preview-uat-uat-5wn03n.laravel.cloud', true, true, 'disabled', false],
    'production integration enabled' => ['staging', 'https://bpls-stakeholder-preview-uat-uat-5wn03n.laravel.cloud', true, false, 'enabled', false],
    'local configured' => ['local', 'http://localhost', true, false, 'disabled', true],
    'local isolated runtime' => ['local', 'http://bpls-runtime-integration.test', true, false, 'disabled', true],
    'local target runtime' => ['local', 'http://bpls-runtime.test', true, false, 'disabled', true],
    'arbitrary local test host' => ['local', 'http://ordinary.example.test', true, false, 'disabled', false],
    'local unconfigured' => ['local', 'http://localhost', false, false, 'disabled', false],
    'workflow UAT' => ['staging', 'https://bpls-stakeholder-preview-uat-uat-5wn03n.laravel.cloud', true, false, 'disabled', true],
]);

test('revoked Cashier position fails both presentation and execution', function () {
    [$actor, $schedule, $payment] = handoffFixture();
    $attempt = handoffAttempt($payment);
    InstitutionalPositionAssignment::where('user_id', $actor->id)->update(['ended_at' => now()]);
    expect(app(AuthorizeUatQrPhSimulation::class)->available($schedule, $actor))->toBeFalse();
    $this->actingAs($actor)->post(route('staff.payment-schedules.classic-payment-simulation.store', $schedule), ['attempt_id' => $attempt->id])->assertForbidden();
});

test('anonymous users cannot simulate', function () {
    [, $schedule, $payment] = handoffFixture();
    $attempt = handoffAttempt($payment);
    expect(app(AuthorizeUatQrPhSimulation::class)->available($schedule, null))->toBeFalse();
    $this->postJson(route('staff.payment-schedules.classic-payment-simulation.store', $schedule), ['attempt_id' => $attempt->id])->assertUnauthorized();
});

test('visible simulation fails cleanly when request expires before submission', function () {
    [$actor, $schedule, $payment] = handoffFixture();
    $attempt = handoffAttempt($payment);
    expect(app(AuthorizeUatQrPhSimulation::class)->available($schedule, $actor))->toBeTrue();
    $this->travel(11)->minutes();
    $this->actingAs($actor)->post(route('staff.payment-schedules.classic-payment-simulation.store', $schedule), ['attempt_id' => $attempt->id])->assertSessionHasErrors('payment');
    expect(TreasuryCollection::count())->toBe(0);
});

test('HTTP simulation is idempotent and refresh hides the settled action with provenance intact', function () {
    [$actor, $schedule, $payment] = handoffFixture();
    $attempt = handoffAttempt($payment);
    foreach ([1, 2] as $retry) {
        $this->actingAs($actor)->post(route('staff.payment-schedules.classic-payment-simulation.store', $schedule), ['attempt_id' => $attempt->id])->assertRedirect(route('staff.payment-schedules.show', $schedule));
    }
    $collection = TreasuryCollection::sole();
    expect($collection->amount_cents)->toBe(417500)
        ->and(data_get($collection->source_snapshot, 'integration_evidence.x_change_payment_id'))->toBe(5)
        ->and(data_get($collection->source_snapshot, 'integration_evidence.attempt_id'))->toBe($attempt->id)
        ->and(data_get($collection->source_snapshot, 'integration_evidence.attempt_reference'))->toBe($attempt->reference)
        ->and(data_get($collection->source_snapshot, 'integration_evidence.attempt_provider'))->toBe('synthetic')
        ->and(data_get($collection->source_snapshot, 'integration_evidence.real_funds_moved'))->toBeFalse()
        ->and(XChangePayment::count())->toBe(1)->and($payment->attempts()->count())->toBe(1);
    $this->get(route('staff.payment-schedules.show', $schedule))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.simulate_classic_payment', false)->where('classicPaymentSimulationUrl', null));
});

test('no active or non payable payment never advertises simulation', function (string $state) {
    [$actor, $schedule, $payment] = handoffFixture();
    if ($state !== 'no_attempt') {
        handoffAttempt($payment, $state === 'expired' ? -1 : 600);
    }
    if ($state === 'failed_payment') {
        $payment->update(['status' => 'failed']);
    }
    expect(app(AuthorizeUatQrPhSimulation::class)->available($schedule->fresh(), $actor))->toBeFalse();
})->with(['no_attempt', 'expired', 'failed_payment']);
