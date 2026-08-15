<?php

use App\Actions\CreateAssessmentForPermitApplication;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
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
use Database\Seeders\RevenueCodeFeeCatalogSeeder;

it('seeds a deterministic revenue code fee catalog foundation with legal provenance', function () {
    $this->seed(RevenueCodeFeeCatalogSeeder::class);
    $this->seed(RevenueCodeFeeCatalogSeeder::class);

    expect(FeeRule::query()->where('code', 'MRC-3A-02-NEW-MAYORS-PERMIT-MICRO')->count())->toBe(1);
    expect(FeeRule::query()->where('code', 'MRC-3A-04-BUSINESS-INSPECTION')->count())->toBe(1);
    expect(FeeRule::query()->where('code', 'MRC-3A-05-BUSINESS-REGISTRATION-PLATE')->count())->toBe(1);
    expect(FeeRule::query()->where('code', 'MRC-2A-02-B-RETAIL-BUSINESS-TAX')->count())->toBe(1);

    $permitFee = FeeRule::query()->where('code', 'MRC-3A-02-NEW-MAYORS-PERMIT-MICRO')->sole();

    expect($permitFee)
        ->category->toBe(FeeRuleCategory::Fee)
        ->scope->toBe(FeeRuleScope::Application)
        ->calculation_type->toBe(FeeRuleCalculationType::Fixed)
        ->amount_cents->toBe(20_000)
        ->legal_basis->toContain('LEGAL-MRC-001 Section 3A.02(b)')
        ->metadata->source_id->toBe('LEGAL-MRC-001')
        ->metadata->application_types->toBe(['new'])
        ->metadata->catalog_status->toBe('recorded_non_executable')
        ->currentReconciliation->execution_status->toBe(FeeRuleExecutionStatus::Blocked);

    $retailTax = FeeRule::query()->where('code', 'MRC-2A-02-B-RETAIL-BUSINESS-TAX')->sole();

    expect($retailTax)
        ->category->toBe(FeeRuleCategory::Tax)
        ->scope->toBe(FeeRuleScope::LineOfBusiness)
        ->calculation_type->toBe(FeeRuleCalculationType::Range)
        ->basis->toBe('declared_gross_sales')
        ->metadata->application_types->toBe(['renewal'])
        ->metadata->catalog_status->toBe('recorded_non_executable')
        ->metadata->policy_boundaries->toContain('rate_based_brackets')
        ->metadata->policy_boundaries->toContain('pil_validation')
        ->currentReconciliation->execution_status->toBe(FeeRuleExecutionStatus::Blocked);

    expect(FeeRuleRange::query()->whereBelongsTo($retailTax)->count())->toBe(23);
    expect(FeeRuleReconciliation::query()->count())->toBe(4);
});

it('executes the exact annual business inspection fee with reconciliation provenance', function () {
    $this->seed(RevenueCodeFeeCatalogSeeder::class);

    $application = PermitApplication::factory()->create([
        'application_year' => 2023,
        'type' => PermitApplicationType::Additional,
    ]);

    $lineOfBusiness = LineOfBusiness::factory()->create();

    PermitApplicationLine::factory()
        ->for($application)
        ->for($lineOfBusiness)
        ->create([
            'declared_gross_sales_cents' => 100_000,
            'capital_investment_cents' => 100_000,
        ]);

    $assessment = app(CreateAssessmentForPermitApplication::class)->handle($application);

    expect($assessment->lines)->toHaveCount(1);
    expect($assessment->total_amount_cents)->toBe(35_000);

    $inspectionFee = $assessment->lines->sole();

    expect($inspectionFee)
        ->code->toBe('MRC-3A-04-BUSINESS-INSPECTION')
        ->amount_cents->toBe(35_000)
        ->rule_snapshot->reconciliation->execution_status->toBe('executable')
        ->rule_snapshot->reconciliation->legal_authority->toBe('Municipality of Ipil Ordinance No. 08-656-2023')
        ->rule_snapshot->reconciliation->decision_reference->toBe('Ordinance No. 08-656-2023 Section 3A.04');
});

it('refuses unresolved new-business eligibility instead of assessing every new business', function () {
    $this->seed(RevenueCodeFeeCatalogSeeder::class);

    $application = PermitApplication::factory()->create([
        'application_year' => 2023,
        'type' => PermitApplicationType::New,
    ]);

    $lineOfBusiness = LineOfBusiness::factory()->create();

    PermitApplicationLine::factory()
        ->for($application)
        ->for($lineOfBusiness)
        ->create([
            'declared_gross_sales_cents' => 100_000,
            'capital_investment_cents' => 100_000,
        ]);

    expect(fn () => app(CreateAssessmentForPermitApplication::class)->handle($application))
        ->toThrow(UnsupportedAssessmentPolicy::class, 'Municipal enterprise-scale eligibility is unresolved');

    expect($application->assessments()->count())->toBe(0);
});

it('refuses the disputed wholesale schedule instead of executing normalized brackets', function () {
    $this->seed(RevenueCodeFeeCatalogSeeder::class);

    $application = PermitApplication::factory()->create([
        'application_year' => 2023,
        'type' => PermitApplicationType::Renewal,
    ]);

    $lineOfBusiness = LineOfBusiness::query()
        ->where('code', 'MRC-2A-02-B-WHOLESALE-RETAIL')
        ->sole();

    PermitApplicationLine::factory()
        ->for($application)
        ->for($lineOfBusiness)
        ->create([
            'declared_gross_sales_cents' => 12_500_000,
            'capital_investment_cents' => 100_000,
        ]);

    expect(fn () => app(CreateAssessmentForPermitApplication::class)->handle($application))
        ->toThrow(UnsupportedAssessmentPolicy::class, 'overlapping and malformed brackets');

    expect($application->assessments()->count())->toBe(0);
});
