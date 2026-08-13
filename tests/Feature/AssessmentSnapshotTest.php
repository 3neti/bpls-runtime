<?php

use App\Actions\CreateAssessmentForPermitApplication;
use App\Enums\AssessmentStatus;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleScope;
use App\Enums\PermitApplicationStatus;
use App\Exceptions\UnsupportedAssessmentPolicy;
use App\Models\Assessment;
use App\Models\FeeRule;
use App\Models\FeeRuleRange;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use App\Models\User;

it('creates an explainable assessment snapshot from fixed and bracketed fee rules', function () {
    $application = PermitApplication::factory()->create([
        'application_year' => 2026,
    ]);

    $lineOfBusiness = LineOfBusiness::factory()->create();

    PermitApplicationLine::factory()
        ->for($application)
        ->for($lineOfBusiness)
        ->create([
            'declared_gross_sales_cents' => 750_000,
            'capital_investment_cents' => 100_000,
        ]);

    FeeRule::factory()->create([
        'code' => 'MAYORS-PERMIT',
        'name' => "Mayor's Permit Fee",
        'category' => FeeRuleCategory::Fee,
        'scope' => FeeRuleScope::Application,
        'calculation_type' => FeeRuleCalculationType::Fixed,
        'amount_cents' => 50_000,
        'effective_from' => '2026-01-01',
        'legal_basis' => 'TOR treasury and permitting capability',
    ]);

    $grossSalesRule = FeeRule::factory()
        ->for($lineOfBusiness)
        ->create([
            'code' => 'BUSINESS-TAX',
            'name' => 'Business Tax',
            'category' => FeeRuleCategory::Tax,
            'scope' => FeeRuleScope::LineOfBusiness,
            'calculation_type' => FeeRuleCalculationType::Range,
            'basis' => 'declared_gross_sales',
            'amount_cents' => 0,
            'effective_from' => '2026-01-01',
            'legal_basis' => 'Revenue code tax schedule evidence pending rule-by-rule reconciliation',
        ]);

    FeeRuleRange::factory()->for($grossSalesRule)->create([
        'min_basis_cents' => 0,
        'max_basis_cents' => 500_000,
        'amount_cents' => 10_000,
    ]);

    FeeRuleRange::factory()->for($grossSalesRule)->create([
        'min_basis_cents' => 500_001,
        'max_basis_cents' => 1_000_000,
        'amount_cents' => 20_000,
    ]);

    $assessor = User::factory()->create();

    $assessment = app(CreateAssessmentForPermitApplication::class)->handle($application, $assessor);

    expect($assessment)
        ->toBeInstanceOf(Assessment::class)
        ->status->toBe(AssessmentStatus::Computed)
        ->total_amount_cents->toBe(70_000);

    expect($assessment->lines)->toHaveCount(2);

    $permitFee = $assessment->lines->firstWhere('code', 'MAYORS-PERMIT');
    $businessTax = $assessment->lines->firstWhere('code', 'BUSINESS-TAX');

    expect($permitFee)
        ->amount_cents->toBe(50_000)
        ->basis_amount_cents->toBe(0)
        ->rule_snapshot->code->toBe('MAYORS-PERMIT');

    expect($businessTax)
        ->amount_cents->toBe(20_000)
        ->basis_amount_cents->toBe(750_000)
        ->rule_snapshot->range->amount_cents->toBe(20_000);

    expect($application->refresh())
        ->status->toBe(PermitApplicationStatus::Assessment)
        ->assessed_at->not->toBeNull();
});

it('keeps historical assessment lines unchanged when fee rules change later', function () {
    $application = PermitApplication::factory()->create([
        'application_year' => 2026,
    ]);

    $lineOfBusiness = LineOfBusiness::factory()->create();

    PermitApplicationLine::factory()
        ->for($application)
        ->for($lineOfBusiness)
        ->create([
            'declared_gross_sales_cents' => 500_000,
        ]);

    $feeRule = FeeRule::factory()->create([
        'code' => 'STATIC-FEE',
        'scope' => FeeRuleScope::Application,
        'calculation_type' => FeeRuleCalculationType::Fixed,
        'amount_cents' => 15_000,
        'effective_from' => '2026-01-01',
    ]);

    $assessment = app(CreateAssessmentForPermitApplication::class)->handle($application);
    $assessmentLine = $assessment->lines->first();

    $feeRule->update([
        'amount_cents' => 99_000,
        'name' => 'Changed Fee Name',
    ]);

    expect($assessmentLine->refresh())
        ->amount_cents->toBe(15_000)
        ->name->not->toBe('Changed Fee Name')
        ->rule_snapshot->amount_cents->toBe(15_000);
});

it('supersedes an existing assessment when a new assessment snapshot is created', function () {
    $application = PermitApplication::factory()->create([
        'application_year' => 2026,
    ]);

    FeeRule::factory()->create([
        'code' => 'APPLICATION-FEE',
        'scope' => FeeRuleScope::Application,
        'calculation_type' => FeeRuleCalculationType::Fixed,
        'amount_cents' => 10_000,
        'effective_from' => '2026-01-01',
    ]);

    $firstAssessment = app(CreateAssessmentForPermitApplication::class)->handle($application);
    $secondAssessment = app(CreateAssessmentForPermitApplication::class)->handle($application->refresh());

    $firstAssessment->refresh();

    expect($firstAssessment->sequence)->toBe(1);
    expect($firstAssessment->superseded_at)->not->toBeNull();
    expect($secondAssessment->sequence)->toBe(2);
    expect($secondAssessment->superseded_at)->toBeNull();
});

it('does not invent formula assessment behavior before policy is confirmed', function () {
    $application = PermitApplication::factory()->create([
        'application_year' => 2026,
    ]);

    FeeRule::factory()->create([
        'code' => 'FORMULA-FEE',
        'scope' => FeeRuleScope::Application,
        'calculation_type' => FeeRuleCalculationType::Formula,
        'effective_from' => '2026-01-01',
    ]);

    app(CreateAssessmentForPermitApplication::class)->handle($application);
})->throws(UnsupportedAssessmentPolicy::class);

it('does not invent rate rounding behavior before policy is confirmed', function () {
    $application = PermitApplication::factory()->create([
        'application_year' => 2026,
    ]);

    $lineOfBusiness = LineOfBusiness::factory()->create();

    PermitApplicationLine::factory()
        ->for($application)
        ->for($lineOfBusiness)
        ->create([
            'declared_gross_sales_cents' => 1_000_000,
        ]);

    $feeRule = FeeRule::factory()
        ->for($lineOfBusiness)
        ->create([
            'code' => 'RATE-TAX',
            'scope' => FeeRuleScope::LineOfBusiness,
            'calculation_type' => FeeRuleCalculationType::Range,
            'basis' => 'declared_gross_sales',
            'effective_from' => '2026-01-01',
        ]);

    FeeRuleRange::factory()->for($feeRule)->create([
        'min_basis_cents' => 0,
        'max_basis_cents' => null,
        'amount_cents' => 0,
        'rate_basis_points' => 125,
    ]);

    app(CreateAssessmentForPermitApplication::class)->handle($application);
})->throws(UnsupportedAssessmentPolicy::class);
