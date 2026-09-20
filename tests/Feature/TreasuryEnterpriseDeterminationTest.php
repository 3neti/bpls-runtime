<?php

use App\Actions\AssignTreasuryLinesOfBusiness;
use App\Actions\BuildBploRoutingTask;
use App\Actions\BuildScheduleOfPayment;
use App\Actions\CreateAssessmentForPermitApplication;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\FeeRule;
use App\Models\User;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../Support/TreasuryEnterpriseFixture.php';

test('manual local UAT Mayor fee persists exact officer basis and amount through the canonical evaluation', function (): void {
    [$application, $actor, $selection, $mayor] = enterpriseUatFixture();
    unset($selection['enterprise_classification']);
    $selection['manual_amount_cents'] = 100000;
    $selection['manual_basis'] = 'Owner-authorized disposable UAT test amount; not municipal policy.';
    $declaration = $application->declaration->getRawOriginal();
    $orders = $application->paperlessPaymentOrders()->with('lines')->get()->toJson();
    test()->actingAs($actor)->postJson('/staff/permit-applications/'.$application->id.'/treasury-lines-of-business', ['selections' => [$selection]])->assertRedirect();
    $assignment = $application->treasuryLineOfBusinessAssignments()->with('items')->sole();
    $evidence = $assignment->source_snapshot['enterprise_determination'];
    expect($evidence['determination_source'])->toBe('manual_treasury_test_determination')
        ->and($evidence['classification'])->toBeNull()
        ->and($evidence['basis'])->toBe($selection['manual_basis'])
        ->and($evidence['application_id'])->toBe($application->id)
        ->and($evidence['determined_by_id'])->toBe($actor->id)
        ->and($evidence['determined_at'])->not->toBeEmpty()
        ->and($evidence['production_policy_authority'])->toBeFalse()
        ->and($assignment->items->sum('determined_amount_cents'))->toBe(112500)
        ->and($assignment->items->firstWhere('fee_rule_id', $mayor['fee_rule_id'])->source_snapshot['calculation']['basis'])->toBe('manual_treasury_test_determination')
        ->and($application->declaration->fresh()->getRawOriginal())->toBe($declaration)
        ->and($application->paperlessPaymentOrders()->with('lines')->get()->toJson())->toBe($orders);
    expect(fn () => app(AssignTreasuryLinesOfBusiness::class)->handle($application, [$selection], $actor))->toThrow(LogicException::class, 'already been confirmed');
    $assessmentOfficer = userWithPermissions([UserPermission::AssessPermitApplications], UserRole::Treasury);
    $assessment = app(CreateAssessmentForPermitApplication::class)->handle($application->fresh(), $assessmentOfficer);
    $schedule = app(BuildScheduleOfPayment::class)->handle($assessment, $assessment->price_report_snapshot)->toArray();
    $expectedTotal = (int) $application->paperlessPaymentOrders()->sum('total_amount_cents') + 112500;
    expect($assessment->total_amount_cents)->toBe($expectedTotal)
        ->and(data_get($assessment->price_report_snapshot, 'total.minor'))->toBe($expectedTotal)
        ->and($schedule['grand_total_minor'])->toBe($expectedTotal);
});

test('manual Mayor fee fails closed for invalid or unavailable test authority', function (string $case): void {
    [$application, $actor, $selection, $mayor] = enterpriseUatFixture();
    unset($selection['enterprise_classification']);
    $selection['manual_amount_cents'] = 100000;
    $selection['manual_basis'] = 'Authorized test determination';
    match ($case) {
        'basis' => $selection['manual_basis'] = ' ',
        'zero' => $selection['manual_amount_cents'] = 0,
        'negative' => $selection['manual_amount_cents'] = -1,
        'float' => $selection['manual_amount_cents'] = 100000.5,
        'stale' => $selection['enterprise_schedule_fingerprint'] = str_repeat('0', 64),
        'both' => $selection['enterprise_classification'] = 'Small',
        'tampered' => $selection['manual_amount_cents'] = 200000,
        'omitted' => $selection['items'] = [],
        'disabled' => config(['stakeholder_preview.mode' => false]),
        'production' => app()->instance('env', 'production'),
        'renewal' => $application->update(['type' => 'renewal']),
        'year' => $application->update(['application_year' => 2025]),
        'actor' => $actor = User::factory()->create(),
    };
    try {
        app(AssignTreasuryLinesOfBusiness::class)->handle($application, [$selection], $actor);
        test()->fail('Expected a rejected manual determination.');
    } catch (ValidationException|LogicException $exception) {
        expect($application->treasuryLineOfBusinessAssignments()->count())->toBe(0)
            ->and($application->lines()->count())->toBe(0);
    }
})->with(['basis', 'zero', 'negative', 'float', 'stale', 'both', 'tampered', 'omitted', 'disabled', 'production', 'renewal', 'year', 'actor']);

test('explicit enterprise bands persist deterministic Treasury evidence without rewriting offices or declaration', function (string $classification, int $amount): void {
    [$application, $actor, $selection, $mayor] = enterpriseUatFixture();
    $before = $application->fresh()->getRawOriginal();
    $declaration = $application->declaration->getRawOriginal();
    $orders = $application->paperlessPaymentOrders()->with('lines')->get()->toJson();
    $catalogue = FeeRule::query()->orderBy('id')->get()->toJson();
    expect($mayor['resolution_status'])->toBe('unresolved');
    $selection['enterprise_classification'] = $classification;
    foreach ($selection['items'] as &$item) {
        if ($item['fee_rule_id'] === $mayor['fee_rule_id']) {
            $item['amount_cents'] = $amount;
        }
    }
    unset($item);
    test()->actingAs($actor)->postJson('/staff/permit-applications/'.$application->id.'/treasury-lines-of-business', ['selections' => [$selection]])->assertRedirect();
    $assignment = $application->treasuryLineOfBusinessAssignments()->with('items')->sole();
    $evidence = $assignment->source_snapshot['enterprise_determination'];
    expect($evidence['classification'])->toBe($classification)
        ->and($evidence['determined_by_id'])->toBe($actor->id)
        ->and($evidence['determined_at'])->not->toBeEmpty()
        ->and($evidence['derived_from_applicant_data'])->toBeFalse()
        ->and($evidence['production_policy_authority'])->toBeFalse()
        ->and($evidence['schedule']['version'])->toBe('2026-09-14.v1')
        ->and($assignment->items->sum('determined_amount_cents'))->toBe($amount + 12500)
        ->and($assignment->items->firstWhere('fee_rule_id', $mayor['fee_rule_id'])->variance_cents)->toBe(0)
        ->and($assignment->items->pluck('name')->filter(fn ($name) => str_contains(strtolower($name), 'business tax')))->toBeEmpty()
        ->and($application->fresh()->getRawOriginal())->toBe($before)
        ->and($application->declaration->fresh()->getRawOriginal())->toBe($declaration)
        ->and($application->paperlessPaymentOrders()->with('lines')->get()->toJson())->toBe($orders)
        ->and(FeeRule::query()->orderBy('id')->get()->toJson())->toBe($catalogue);
    $projection = app(BuildBploRoutingTask::class)->handle($application->fresh(), $actor)->financial_editor;
    expect($projection['treasury_assignments'][0]['enterprise_determination']['classification'])->toBe($classification);
    expect(fn () => app(AssignTreasuryLinesOfBusiness::class)->handle($application, [$selection], $actor))->toThrow(LogicException::class, 'already been confirmed');
    expect($application->treasuryLineOfBusinessAssignments()->count())->toBe(1)->and($application->assessments()->count())->toBe(0);
})->with(['Micro' => ['Micro', 20000], 'Cottage' => ['Cottage', 50000], 'Small' => ['Small', 100000], 'Medium' => ['Medium', 150000], 'Large' => ['Large', 200000]]);

test('enterprise confirmation rejects incomplete tampered stale or unavailable authority', function (string $case): void {
    [$application, $actor, $selection, $mayor] = enterpriseUatFixture();
    if ($case === 'missing') {
        unset($selection['enterprise_classification']);
    }
    if ($case === 'invalid') {
        $selection['enterprise_classification'] = 'Unknown';
    }
    if ($case === 'stale') {
        $selection['enterprise_schedule_fingerprint'] = str_repeat('0', 64);
    }
    if ($case === 'amount') {
        foreach ($selection['items'] as &$item) {
            if ($item['fee_rule_id'] === $mayor['fee_rule_id']) {
                $item['amount_cents'] = 1;
            }
        } unset($item);
    }
    if ($case === 'omitted') {
        $selection['items'] = array_values(array_filter($selection['items'], fn ($item) => $item['fee_rule_id'] !== $mayor['fee_rule_id']));
    }
    if ($case === 'schedule') {
        config(['treasury_enterprise.schedule' => null]);
    }
    if ($case === 'other_environment') {
        config(['app.url' => 'https://production.example.test', 'treasury_enterprise.provisional_uat_context' => 'production']);
    }
    if ($case === 'disabled') {
        config(['stakeholder_preview.mode' => false]);
    }
    if ($case === 'unknown_context') {
        config(['treasury_enterprise.provisional_uat_context' => 'unadmitted']);
    }
    $before = $application->fresh()->toJson();
    expect(fn () => app(AssignTreasuryLinesOfBusiness::class)->handle($application, [$selection], $actor))->toThrow(ValidationException::class);
    expect($application->treasuryLineOfBusinessAssignments()->count())->toBe(0)->and($application->lines()->count())->toBe(0)
        ->and($application->fresh()->toJson())->toBe($before);
})->with(['missing', 'invalid', 'stale', 'amount', 'omitted', 'schedule', 'other_environment', 'disabled', 'unknown_context']);

test('enterprise authority does not grant a non Treasury actor permission', function (): void {
    [$application, $actor, $selection] = enterpriseUatFixture();
    $unauthorized = User::factory()->create();
    expect(fn () => app(AssignTreasuryLinesOfBusiness::class)->handle($application, [$selection], $unauthorized))->toThrow(LogicException::class, 'authorized Treasury actor');
});
