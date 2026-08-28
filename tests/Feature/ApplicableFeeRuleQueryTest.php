<?php

use App\Actions\CreateAssessmentForPermitApplication;
use App\Assessment\ApplicableFeeRuleQuery;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleExecutionStatus;
use App\Enums\FeeRuleScope;
use App\Enums\PermitApplicationType;
use App\Exceptions\UnsupportedAssessmentPolicy;
use App\Models\FeeRule;
use App\Models\FeeRuleRange;
use App\Models\FeeRuleReconciliation;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;

it('selects the same fixed range application type line of business and effective rules used by assessment', function () {
    $lineOfBusiness = LineOfBusiness::factory()->create();
    $otherLineOfBusiness = LineOfBusiness::factory()->create();
    $application = PermitApplication::factory()->create([
        'type' => PermitApplicationType::Renewal,
        'application_year' => 2026,
    ]);
    PermitApplicationLine::factory()->for($application)->for($lineOfBusiness)->create([
        'declared_gross_sales_cents' => 500_000,
    ]);

    FeeRule::factory()->create([
        'code' => 'APPLICATION-FIXED',
        'scope' => FeeRuleScope::Application,
        'amount_cents' => 10_000,
        'effective_from' => '2025-01-01',
        'metadata' => ['application_types' => ['renewal']],
    ]);
    $rangeRule = FeeRule::factory()->for($lineOfBusiness)->create([
        'code' => 'LOB-RANGE',
        'scope' => FeeRuleScope::LineOfBusiness,
        'calculation_type' => FeeRuleCalculationType::Range,
        'basis' => 'declared_gross_sales',
        'amount_cents' => 0,
        'effective_from' => '2026-01-01',
        'metadata' => ['application_types' => ['renewal']],
    ]);
    FeeRuleRange::factory()->for($rangeRule)->create([
        'min_basis_cents' => 0,
        'max_basis_cents' => 999_999,
        'amount_cents' => 20_000,
    ]);

    FeeRule::factory()->for($otherLineOfBusiness)->create([
        'code' => 'OTHER-LOB',
        'scope' => FeeRuleScope::LineOfBusiness,
        'effective_from' => '2026-01-01',
    ]);
    FeeRule::factory()->create([
        'code' => 'NEW-ONLY',
        'effective_from' => '2026-01-01',
        'metadata' => ['application_types' => ['new']],
    ]);
    FeeRule::factory()->create(['code' => 'FUTURE', 'effective_from' => '2027-01-01']);
    FeeRule::factory()->create([
        'code' => 'EXPIRED',
        'effective_from' => '2024-01-01',
        'effective_until' => '2025-12-31',
    ]);
    FeeRule::factory()->create([
        'code' => 'INACTIVE',
        'effective_from' => '2026-01-01',
        'is_active' => false,
    ]);
    FeeRule::factory()->create([
        'code' => 'MALFORMED-TYPE-SCOPE',
        'effective_from' => '2026-01-01',
        'metadata' => ['application_types' => 'renewal'],
    ]);

    $selectedRules = app(ApplicableFeeRuleQuery::class)->forPermitApplication($application);

    expect($selectedRules->pluck('code')->all())->toBe(['APPLICATION-FIXED', 'LOB-RANGE']);

    $assessment = app(CreateAssessmentForPermitApplication::class)->handle($application);

    expect($assessment->lines->pluck('code')->all())->toBe(['APPLICATION-FIXED', 'LOB-RANGE'])
        ->and($assessment->lines->pluck('amount_cents')->all())->toBe([10_000, 20_000]);
});

it('preserves assessment refusal for a selected blocked reconciliation rule', function () {
    $application = PermitApplication::factory()->create([
        'type' => PermitApplicationType::New,
        'application_year' => 2026,
    ]);
    $blockedRule = FeeRule::factory()->create([
        'code' => 'BLOCKED-MUNICIPAL-RULE',
        'effective_from' => '2026-01-01',
        'metadata' => [
            'application_types' => ['new'],
            'reconciliation_required' => true,
        ],
    ]);
    FeeRuleReconciliation::factory()->for($blockedRule)->create([
        'execution_status' => FeeRuleExecutionStatus::Blocked,
        'execution_reason' => 'Municipal interpretation remains unresolved.',
    ]);

    expect(app(ApplicableFeeRuleQuery::class)->forPermitApplication($application)->modelKeys())
        ->toBe([$blockedRule->id]);

    expect(fn () => app(CreateAssessmentForPermitApplication::class)->handle($application))
        ->toThrow(UnsupportedAssessmentPolicy::class, 'Municipal interpretation remains unresolved.');
});
