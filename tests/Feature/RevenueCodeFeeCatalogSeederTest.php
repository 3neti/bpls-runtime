<?php

use App\Actions\CreateAssessmentForPermitApplication;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleScope;
use App\Enums\PermitApplicationType;
use App\Models\FeeRule;
use App\Models\FeeRuleRange;
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
        ->metadata->catalog_status->toBe('executable_foundation');

    $retailTax = FeeRule::query()->where('code', 'MRC-2A-02-B-RETAIL-BUSINESS-TAX')->sole();

    expect($retailTax)
        ->category->toBe(FeeRuleCategory::Tax)
        ->scope->toBe(FeeRuleScope::LineOfBusiness)
        ->calculation_type->toBe(FeeRuleCalculationType::Range)
        ->basis->toBe('declared_gross_sales')
        ->metadata->application_types->toBe(['renewal'])
        ->metadata->catalog_status->toBe('partial_executable_extract')
        ->metadata->policy_boundaries->toContain('rate_based_brackets')
        ->metadata->policy_boundaries->toContain('pil_validation');

    expect(FeeRuleRange::query()->whereBelongsTo($retailTax)->count())->toBe(23);
});

it('uses seeded rules without assessing initial local business tax on a new business', function () {
    $this->seed(RevenueCodeFeeCatalogSeeder::class);

    $application = PermitApplication::factory()->create([
        'application_year' => 2026,
        'type' => PermitApplicationType::New,
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

    $assessment = app(CreateAssessmentForPermitApplication::class)->handle($application);

    expect($assessment->lines)->toHaveCount(3);
    expect($assessment->total_amount_cents)->toBe(85_000);

    expect($assessment->lines->pluck('code')->all())
        ->toContain('MRC-3A-02-NEW-MAYORS-PERMIT-MICRO')
        ->toContain('MRC-3A-04-BUSINESS-INSPECTION')
        ->toContain('MRC-3A-05-BUSINESS-REGISTRATION-PLATE')
        ->not->toContain('MRC-2A-02-B-RETAIL-BUSINESS-TAX');
});

it('uses seeded renewal business tax ranges to create an explainable assessment snapshot', function () {
    $this->seed(RevenueCodeFeeCatalogSeeder::class);

    $application = PermitApplication::factory()->create([
        'application_year' => 2026,
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

    $assessment = app(CreateAssessmentForPermitApplication::class)->handle($application);

    expect($assessment->lines)->toHaveCount(2);
    expect($assessment->total_amount_cents)->toBe(270_424);

    $retailTax = $assessment->lines->firstWhere('code', 'MRC-2A-02-B-RETAIL-BUSINESS-TAX');
    $inspectionFee = $assessment->lines->firstWhere('code', 'MRC-3A-04-BUSINESS-INSPECTION');

    expect($retailTax)
        ->amount_cents->toBe(235_424)
        ->basis_amount_cents->toBe(12_500_000)
        ->legal_basis->toContain('LEGAL-MRC-001 Section 2A.02(b)')
        ->rule_snapshot->legacy_source_id->toBe('LEGAL-MRC-001:SECTION-2A.02-B')
        ->rule_snapshot->range->min_basis_cents->toBe(10_000_000)
        ->rule_snapshot->range->max_basis_cents->toBe(14_999_999);

    expect($inspectionFee)->amount_cents->toBe(35_000);
});
