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

    expect(RevenueCodeProvision::query()->count())->toBe(39)
        ->and(RevenueCodeProvision::query()->where('section_reference', 'like', 'Section 2A.02%')->count())->toBe(8)
        ->and(RevenueCodeProvision::query()->where('section_reference', 'like', 'Section 2B.%')->count())->toBe(4)
        ->and(RevenueCodeProvision::query()->where('section_reference', 'like', 'Section 2C.%')->count())->toBe(2)
        ->and(RevenueCodeProvision::query()->where('section_reference', 'like', 'Section 2D.%')->count())->toBe(3)
        ->and(RevenueCodeProvision::query()->where('section_reference', 'like', 'Section 3A.%')->count())->toBe(7)
        ->and(RevenueCodeProvision::query()->where('section_reference', 'like', 'Section 3B.%')->count())->toBe(6)
        ->and(RevenueCodeProvision::query()->whereNotNull('fee_rule_id')->count())->toBe(4)
        ->and(RevenueCodeProvision::query()->where('reconciliation_status', RevenueCodeProvisionStatus::ReconciliationRequired)->count())->toBe(38);

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
    expect(RevenueCodeProvisionClause::query()->count())->toBe(254)
        ->and(RevenueCodeProvisionClause::query()
            ->where('reconciliation_status', RevenueCodeProvisionStatus::ReconciliationRequired)
            ->count())->toBe(254);

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
    $manufacturerTaxScope = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2A-02-B-MANUFACTURER-TAX-SCOPE')
        ->sole();
    $contractorMinimum = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2A-02-E-MINIMUM-TAX')
        ->sole();
    $contractorInstallments = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2A-02-E-PROJECT-INSTALLMENTS')
        ->sole();
    $contractorRecomputation = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2A-02-E-COMPLETION-RECOMPUTATION')
        ->sole();
    $serviceEligibility = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2A-02-G-ENUMERATED-BUSINESSES')
        ->sole();
    $serviceMinimum = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2A-02-G-MINIMUM-TAX')
        ->sole();
    $quarterlyInstallments = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2E-03-QUARTERLY-INSTALLMENTS')
        ->sole();
    $lateDeficiency = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2E-04-E-AFTER-MAY-20-SURCHARGE-INTEREST')
        ->sole();
    $groceryPil = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2F-01-PIL-GROCERY')
        ->sole();
    $malformedWholesalePil = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2F-01-PIL-WHOLESALERS-DEALERS-DISTRIBUTORS')
        ->sole();
    $malformedRepairPil = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2F-01-PIL-SMALL-REPAIR-SHOPS')
        ->sole();
    $pilUsage = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2F-01-PIL-VALIDATION-FALLBACK')
        ->sole();
    $receiptCertification = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2E-04-F-PAID-TAX-CERTIFICATION')
        ->sole();
    $locationTransfer = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2E-04-G-PAID-PERIOD-LOCATION-TRANSFER')
        ->sole();
    $retirementFine = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2E-04-RETIREMENT-LATE-FINE')
        ->sole();
    $permitCancellation = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2E-04-RETIREMENT-PERMIT-CANCELLATION')
        ->sole();
    $estateContinuation = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2E-04-DEATH-ESTATE-CONTINUATION')
        ->sole();
    $mobileTraderRate = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2B-02-MOBILE-TRADER-GROSS-RECEIPTS-RATE')
        ->sole();
    $publicUtilityVanRate = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2B-05-VAN')
        ->sole();
    $amusementDailyRate = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2B-07-AMUSEMENT-CONTRIVANCE-DAILY-RATE')
        ->sole();
    $otherBusinessCeiling = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2B-08-NATIONAL-TAX-BUSINESS-CEILING')
        ->sole();
    $petroleumExemption = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2C-01-PETROLEUM-LOCAL-TAX-EXEMPTION')
        ->sole();
    $newBusinessExemption = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2C-02-INITIAL-LOCAL-BUSINESS-TAX-EXEMPTION')
        ->sole();
    $principalOfficeSitus = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2D-01-A-PRINCIPAL-OFFICE')
        ->sole();
    $salesAllocation = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2D-01-B-THIRTY-SEVENTY-ALLOCATION')
        ->sole();
    $portOfLoading = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-2D-01-C-PORT-OF-LOADING')
        ->sole();
    $largeEnterprise = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-3A-02-ENTERPRISE-LARGE')
        ->sole();
    $unlabeledServiceAmount = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-3A-02-A-05-SERVICE-UNLABELED')
        ->sole();
    $missingServiceAmount = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-3A-02-A-05-SERVICE-LARGE')
        ->sole();
    $unlabeledGasolineAmount = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-3A-02-A-10-GASOLINE-UNLABELED')
        ->sole();
    $missingGasolineAmount = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-3A-02-A-10-GASOLINE-LARGE')
        ->sole();
    $newBusinessLargeAmount = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-3A-02-B-NEW-LARGE')
        ->sole();
    $registrationPlateCeiling = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-3A-05-REGISTRATION-PLATE-CEILING')
        ->sole();
    $cooperativeAmount = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-3A-02-A-12-COOPERATIVES')
        ->sole();
    $doeCompliance = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-3A-02-A-10-DOE-COMPLIANCE')
        ->sole();
    $localDerbyDefinition = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-3B-01-LOCAL-DERBY')
        ->sole();
    $missingDerbyAmount = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-3B-02-DERBY-MISSING-AMOUNT')
        ->sole();
    $hackfightAmount = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-3B-02-HACKFIGHT-PER-FIGHT')
        ->sole();
    $cockpitFranchise = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-3B-03-TEN-YEAR-FRANCHISE')
        ->sole();
    $cockpitRegistrationFee = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-3B-05-REGISTRATION-FEE-PAYMENT')
        ->sole();
    $prohibitedCockfightDates = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-3B-07-PROHIBITED-DATES')
        ->sole();
    $otherCockpitOffenderPenalty = RevenueCodeProvisionClause::query()
        ->where('code', 'MRC-3B-08-OTHER-OFFENDER-PENALTY')
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
        ->execution_blocker->toContain('ceiling')
        ->and($manufacturerTaxScope)
        ->clause_type->toBe(RevenueCodeProvisionClauseType::TaxScopeBoundary)
        ->metadata->excluded_when_subject_to_section->toBe('2A.02(a)')
        ->and($contractorMinimum)
        ->clause_type->toBe(RevenueCodeProvisionClauseType::MinimumTax)
        ->amount_cents->toBe(1_447_793)
        ->and($contractorInstallments)
        ->clause_type->toBe(RevenueCodeProvisionClauseType::InstallmentSchedule)
        ->metadata->allocation->toBe('equal')
        ->and($contractorRecomputation)
        ->clause_type->toBe(RevenueCodeProvisionClauseType::CompletionRecomputation)
        ->metadata->candidate_outcomes->toBe(['deficiency_collection', 'excess_refund'])
        ->and($serviceEligibility)
        ->metadata->source_item_numbers->toBe([14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24])
        ->metadata->known_ambiguity->toBe('enumeration_starts_at_14')
        ->and($serviceMinimum)
        ->clause_type->toBe(RevenueCodeProvisionClauseType::MinimumTax)
        ->amount_cents->toBe(1_447_793)
        ->and($quarterlyInstallments)
        ->metadata->candidate_due_dates->toBe(['01-20', '04-20', '07-20', '10-20'])
        ->and($lateDeficiency)
        ->clause_type->toBe(RevenueCodeProvisionClauseType::SurchargeInterest)
        ->metadata->stated_surcharge_percent->toBe('25.00')
        ->and($groceryPil)
        ->clause_type->toBe(RevenueCodeProvisionClauseType::PresumptiveIncomeThreshold)
        ->amount_cents->toBe(616_000_000)
        ->metadata->source_value_text->toBe('6,160,000.00')
        ->and($malformedWholesalePil)
        ->amount_cents->toBeNull()
        ->metadata->source_value_text->toBe('6,1600,000.00')
        ->metadata->normalization_question->toContain('Malformed source value')
        ->and($malformedRepairPil)
        ->amount_cents->toBeNull()
        ->metadata->source_value_text->toBe('100,000.000')
        ->metadata->normalization_question->toContain('Malformed decimal precision')
        ->and($pilUsage)
        ->clause_type->toBe(RevenueCodeProvisionClauseType::ValidationFallback)
        ->metadata->candidate_uses->toBe(['validate_declared_gross_receipts', 'establish_taxable_gross_receipts_when_valid_data_unavailable'])
        ->and($receiptCertification)
        ->clause_type->toBe(RevenueCodeProvisionClauseType::ReceiptCertification)
        ->amount_cents->toBe(10_000)
        ->metadata->candidate_values_are_non_executable->toBeTrue()
        ->and($locationTransfer)
        ->clause_type->toBe(RevenueCodeProvisionClauseType::LocationTransfer)
        ->metadata->candidate_tax_effect->toBe('no_additional_tax_for_paid_period')
        ->and($retirementFine)
        ->clause_type->toBe(RevenueCodeProvisionClauseType::TaxSettlement)
        ->amount_cents->toBe(100_000)
        ->metadata->candidate_values_are_non_executable->toBeTrue()
        ->and($permitCancellation)
        ->clause_type->toBe(RevenueCodeProvisionClauseType::PermitCancellation)
        ->metadata->candidate_actions->toBe(['permit_surrender', 'permit_cancellation', 'cancellation_record'])
        ->and($estateContinuation)
        ->clause_type->toBe(RevenueCodeProvisionClauseType::EstateContinuation)
        ->metadata->candidate_tax_effect->toBe('no_additional_payment_for_paid_term_remainder')
        ->and($mobileTraderRate)
        ->rate_basis_points->toBe('100.0000')
        ->metadata->candidate_values_are_non_executable->toBeTrue()
        ->and($publicUtilityVanRate)
        ->amount_cents->toBe(114_450)
        ->metadata->candidate_unit->toBe('vehicle')
        ->and($amusementDailyRate)
        ->amount_cents->toBe(54_500)
        ->metadata->candidate_unit->toBe('operating_day')
        ->and($otherBusinessCeiling)
        ->rate_basis_points->toBe('200.0000')
        ->is_ceiling->toBeTrue()
        ->and($petroleumExemption)
        ->clause_type->toBe(RevenueCodeProvisionClauseType::Exemption)
        ->metadata->covered_articles->toBe(['A', 'B'])
        ->and($newBusinessExemption)
        ->clause_type->toBe(RevenueCodeProvisionClauseType::Exemption)
        ->metadata->candidate_remaining_charges->toBe(['business_permit', 'regulatory_fees_and_charges'])
        ->and($principalOfficeSitus)
        ->clause_type->toBe(RevenueCodeProvisionClauseType::SitusDefinition)
        ->metadata->source_notice_days->toBe(15)
        ->and($salesAllocation)
        ->clause_type->toBe(RevenueCodeProvisionClauseType::SalesAllocation)
        ->metadata->source_item->toBe('2 [second occurrence]')
        ->metadata->candidate_principal_office_percent->toBe('30.00')
        ->metadata->candidate_operating_facility_percent->toBe('70.00')
        ->and($portOfLoading)
        ->clause_type->toBe(RevenueCodeProvisionClauseType::SalesAllocation)
        ->metadata->known_cross_reference_question->toBeTrue()
        ->and($largeEnterprise)
        ->metadata->source_asset_limit->toBe('Above P 60M')
        ->metadata->source_workforce->toBe('200 more')
        ->metadata->candidate_values_are_non_executable->toBeTrue()
        ->and($unlabeledServiceAmount)
        ->amount_cents->toBe(50_000)
        ->metadata->source_classification->toBeNull()
        ->metadata->source_row_is_unlabeled->toBeTrue()
        ->and($missingServiceAmount)
        ->amount_cents->toBeNull()
        ->metadata->source_amount_is_missing->toBeTrue()
        ->and($unlabeledGasolineAmount)
        ->amount_cents->toBe(500_000)
        ->metadata->source_row_is_unlabeled->toBeTrue()
        ->and($missingGasolineAmount)
        ->amount_cents->toBeNull()
        ->metadata->source_amount_is_missing->toBeTrue()
        ->and($newBusinessLargeAmount)
        ->amount_cents->toBe(200_000)
        ->metadata->candidate_values_are_non_executable->toBeTrue()
        ->and($registrationPlateCeiling)
        ->amount_cents->toBe(30_000)
        ->is_ceiling->toBeTrue()
        ->and($cooperativeAmount)
        ->metadata->source_row_is_unlabeled->toBeFalse()
        ->and($doeCompliance)
        ->clause_type->toBe(RevenueCodeProvisionClauseType::DocumentaryRequirement)
        ->source_text->toContain('No Retail Outlet shall operate until a Certificate of Compliance')
        ->candidate_interpretation->toContain('evidence requirement')
        ->metadata->source_amount_is_missing->toBeFalse()
        ->and($localDerbyDefinition)
        ->clause_type->toBe(RevenueCodeProvisionClauseType::Definition)
        ->source_text->toContain('Local Derby')
        ->and($missingDerbyAmount)
        ->amount_cents->toBeNull()
        ->metadata->source_amount_is_missing->toBeTrue()
        ->and($hackfightAmount)
        ->amount_cents->toBe(10_000)
        ->metadata->source_value_text->toBe('100.00/fight')
        ->and($cockpitFranchise)
        ->metadata->source_term_years->toBe(10)
        ->and($cockpitRegistrationFee)
        ->metadata->known_terminology_conflict->toBe(['annual_cockpit_permit_fee', 'cockpit_registration_fee'])
        ->and($prohibitedCockfightDates)
        ->clause_type->toBe(RevenueCodeProvisionClauseType::OperatingRestriction)
        ->metadata->known_source_defect->toBe('November 30 labeled National Heroes Day')
        ->and($otherCockpitOffenderPenalty)
        ->clause_type->toBe(RevenueCodeProvisionClauseType::Penalty)
        ->is_ceiling->toBeTrue()
        ->metadata->candidate_minimum_fine_cents->toBe(60_000)
        ->metadata->candidate_maximum_fine_cents->toBe(200_000);

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
