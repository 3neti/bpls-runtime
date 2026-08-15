<?php

use App\Actions\CreateAssessmentForPermitApplication;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleExecutionStatus;
use App\Enums\FeeRuleScope;
use App\Enums\PermitApplicationType;
use App\Enums\RevenueCodeProvisionClauseType;
use App\Enums\RevenueCodeProvisionStatus;
use App\Exceptions\UnsupportedAssessmentPolicy;
use App\Models\FeeRule;
use App\Models\FeeRuleRange;
use App\Models\FeeRuleReconciliation;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use App\Models\RevenueCodeProvision;
use App\Models\RevenueCodeProvisionClause;
use App\Models\RevenueCodeProvisionRow;
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

    expect(RevenueCodeProvision::query()->count())->toBe(11)
        ->and(RevenueCodeProvision::query()->where('section_reference', 'like', 'Section 2A.02%')->count())->toBe(8)
        ->and(RevenueCodeProvision::query()->whereNotNull('fee_rule_id')->count())->toBe(4)
        ->and(RevenueCodeProvision::query()->where('reconciliation_status', RevenueCodeProvisionStatus::ReconciliationRequired)->count())->toBe(10);

    $wholesaleProvision = RevenueCodeProvision::query()
        ->with('feeRule.currentReconciliation')
        ->where('code', 'MRC-2A-02-B-WHOLESALERS')
        ->sole();

    expect($wholesaleProvision)
        ->section_reference->toBe('Section 2A.02(b)')
        ->reconciliation_status->toBe(RevenueCodeProvisionStatus::ReconciliationRequired)
        ->reconciliation_notes->toContain('overlapping')
        ->feeRule->code->toBe('MRC-2A-02-B-RETAIL-BUSINESS-TAX')
        ->feeRule->currentReconciliation->execution_status->toBe(FeeRuleExecutionStatus::Blocked);

    $inspectionProvision = RevenueCodeProvision::query()
        ->with('feeRule.currentReconciliation')
        ->where('code', 'MRC-3A-04-INSPECTION')
        ->sole();

    expect($inspectionProvision)
        ->reconciliation_status->toBe(RevenueCodeProvisionStatus::Reconciled)
        ->feeRule->currentReconciliation->execution_status->toBe(FeeRuleExecutionStatus::Executable);

    expect(RevenueCodeProvisionRow::query()->count())->toBe(82);
    expect(RevenueCodeProvisionClause::query()->count())->toBe(12)
        ->and(RevenueCodeProvisionClause::query()
            ->where('reconciliation_status', RevenueCodeProvisionStatus::ReconciliationRequired)
            ->count())->toBe(12);

    $dependentRate = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2A-02-C-DEPENDENT-HALF-RATE')
        ->sole();
    $retailExcessRate = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2A-02-D-EXCESS-RETAIL-BAND')
        ->sole();
    $financialReceipts = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2A-02-F-TAXABLE-RECEIPTS')
        ->sole();
    $peddlerCeiling = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2A-02-H-ANNUAL-CEILING')
        ->sole();

    expect($dependentRate)
        ->clause_type->toBe(RevenueCodeProvisionClauseType::DependentRate)
        ->is_ceiling->toBeTrue()
        ->metadata->dependent_sections->toBe(['2A.02(a)', '2A.02(b)', '2A.02(d)'])
        ->metadata->candidate_values_are_non_executable->toBeTrue()
        ->and($retailExcessRate)
        ->rate_basis_points->toBe('126.0000')
        ->candidate_interpretation->toContain('first PHP 400,000.00')
        ->and($financialReceipts)
        ->clause_type->toBe(RevenueCodeProvisionClauseType::TaxableReceiptCatalog)
        ->metadata->bank_receipt_categories->toHaveCount(13)
        ->and($peddlerCeiling)
        ->amount_cents->toBe(6_275)
        ->is_ceiling->toBeTrue()
        ->execution_blocker->toContain('ceiling');

    $overlappingRow = RevenueCodeProvisionRow::query()
        ->where('code', 'MRC-2A-02-B-ROW-08')
        ->sole();
    $malformedRow = RevenueCodeProvisionRow::query()
        ->where('code', 'MRC-2A-02-B-ROW-18')
        ->sole();
    $ceilingRow = RevenueCodeProvisionRow::query()
        ->where('code', 'MRC-2A-02-B-ROW-24')
        ->sole();

    expect($overlappingRow)
        ->source_basis_text->toBe('7,000.00 or more but less than 8,000.00')
        ->basis_from_cents->toBe(700_000)
        ->basis_below_cents->toBe(800_000)
        ->and($malformedRow)
        ->source_basis_text->toStartWith('150,0000.00')
        ->normalization_notes->toContain('malformed source value')
        ->and($ceilingRow)
        ->source_value_text->toContain('not exceeding')
        ->rate_basis_points->toBe('62.9500')
        ->is_ceiling->toBeTrue();

    $manufacturerMalformedRow = RevenueCodeProvisionRow::query()
        ->where('code', 'MRC-2A-02-A-ROW-18')
        ->sole();
    $manufacturerCeilingRow = RevenueCodeProvisionRow::query()
        ->where('code', 'MRC-2A-02-A-ROW-20')
        ->sole();
    $contractorOverlapRow = RevenueCodeProvisionRow::query()
        ->where('code', 'MRC-2A-02-E-ROW-15')
        ->sole();
    $serviceCeilingRow = RevenueCodeProvisionRow::query()
        ->where('code', 'MRC-2A-02-G-ROW-19')
        ->sole();

    expect($manufacturerMalformedRow)
        ->source_basis_text->toStartWith('4,000,0000.00')
        ->normalization_notes->toContain('malformed source value')
        ->and($manufacturerCeilingRow)
        ->rate_basis_points->toBe('47.2100')
        ->is_ceiling->toBeTrue()
        ->and($contractorOverlapRow)
        ->source_basis_text->toStartWith('400,000.00')
        ->basis_from_cents->toBe(40_000_000)
        ->and($serviceCeilingRow)
        ->rate_basis_points->toBe('57.2300')
        ->is_ceiling->toBeTrue();

    $enumeratedServiceProvision = RevenueCodeProvision::query()
        ->where('code', 'MRC-2A-02-G-ENUMERATED-SERVICES')
        ->sole();

    expect($enumeratedServiceProvision->metadata['known_ambiguities'])
        ->toContain('overlapping_ranges')
        ->toContain('statutory_ceiling')
        ->toContain('minimum_tax_floor')
        ->not->toContain('source_layout_corruption');
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
