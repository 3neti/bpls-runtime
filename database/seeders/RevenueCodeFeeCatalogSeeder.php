<?php

namespace Database\Seeders;

use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleExecutionStatus;
use App\Enums\FeeRuleScope;
use App\Enums\RevenueCodeProvisionClauseType;
use App\Enums\RevenueCodeProvisionRowStatus;
use App\Enums\RevenueCodeProvisionStatus;
use App\Enums\RevenueCodeProvisionType;
use App\Models\FeeRule;
use App\Models\FeeRuleRange;
use App\Models\FeeRuleReconciliation;
use App\Models\LineOfBusiness;
use App\Models\RevenueCodeProvision;
use App\Models\RevenueCodeProvisionClause;
use App\Models\RevenueCodeProvisionRow;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RevenueCodeFeeCatalogSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $retailLine = LineOfBusiness::query()->updateOrCreate(
            ['code' => 'MRC-2A-02-B-WHOLESALE-RETAIL'],
            [
                'name' => 'Wholesalers, Retailers, Dealers or Distributors',
                'major_category' => 'Retail',
                'is_active' => true,
                'legacy_source_id' => 'LEGAL-MRC-001:SECTION-2A.02-B',
                'metadata' => [
                    'source_id' => 'LEGAL-MRC-001',
                    'source_section' => 'Section 2A.02(b)',
                    'catalog_status' => 'foundation_extract',
                    'notes' => 'Representative line of business seeded from the Revenue Code business-tax schedule. Full major/division/group catalog extraction remains pending.',
                ],
            ],
        );

        $newBusinessPermitFee = $this->fixedRule(
            code: 'MRC-3A-02-NEW-MAYORS-PERMIT-MICRO',
            name: "Mayor's Permit Fee - New Business Micro-Industry",
            amountCents: 20_000,
            legalBasis: 'LEGAL-MRC-001 Section 3A.02(b): new business micro-industry Mayor\'s Permit fee.',
            metadata: [
                'source_id' => 'LEGAL-MRC-001',
                'source_section' => 'Section 3A.02(b)',
                'application_type' => 'new',
                'application_types' => ['new'],
                'enterprise_scale' => 'micro_industry',
                'catalog_status' => 'recorded_non_executable',
                'reconciliation_required' => true,
            ],
        );
        $this->reconcile(
            feeRule: $newBusinessPermitFee,
            originalText: 'Section 3A.02(b): For new business, Micro-Industry - P 200.00.',
            normalizedInterpretation: 'Charge PHP 200.00 to every new permit application.',
            executionStatus: FeeRuleExecutionStatus::Blocked,
            executionReason: 'Municipal enterprise-scale eligibility is unresolved; the micro-industry amount cannot be applied to every new business.',
        );

        $inspectionFee = $this->fixedRule(
            code: 'MRC-3A-04-BUSINESS-INSPECTION',
            name: 'Business Inspection Fee',
            amountCents: 35_000,
            legalBasis: 'LEGAL-MRC-001 Section 3A.04: uniform annual business inspection fee.',
            metadata: [
                'source_id' => 'LEGAL-MRC-001',
                'source_section' => 'Section 3A.04',
                'catalog_status' => 'executable_reconciled',
                'reconciliation_required' => true,
            ],
        );
        $this->reconcile(
            feeRule: $inspectionFee,
            originalText: 'Section 3A.04: Any business operation in the municipality should be charged an inspection fee of P350.00, uniform to all business establishments and payable per annum.',
            normalizedInterpretation: 'Charge one annual PHP 350.00 business inspection fee per permit application.',
            executionStatus: FeeRuleExecutionStatus::Executable,
            executionReason: 'The ordinance states an exact, uniform annual amount with deterministic application scope.',
            decisionAuthority: 'Municipality of Ipil Sangguniang Bayan',
            decisionReference: 'Ordinance No. 08-656-2023 Section 3A.04',
        );

        $registrationPlateFee = $this->fixedRule(
            code: 'MRC-3A-05-BUSINESS-REGISTRATION-PLATE',
            name: 'Business Permit Registration Plate',
            amountCents: 30_000,
            legalBasis: 'LEGAL-MRC-001 Section 3A.05: business permit registration plate amount not to exceed PHP 300.00.',
            metadata: [
                'source_id' => 'LEGAL-MRC-001',
                'source_section' => 'Section 3A.05',
                'application_types' => ['new'],
                'catalog_status' => 'recorded_non_executable',
                'reconciliation_required' => true,
                'policy_note' => 'Ordinance states not to exceed PHP 300.00; production configuration must confirm the exact charged amount.',
            ],
        );
        $this->reconcile(
            feeRule: $registrationPlateFee,
            originalText: 'Section 3A.05: Applicants shall pay an amount not to exceed Three Hundred Pesos (P300.00) for the Business Permit Registration Plate and handling.',
            normalizedInterpretation: 'Charge the statutory ceiling of PHP 300.00 as the exact registration-plate amount.',
            executionStatus: FeeRuleExecutionStatus::Blocked,
            executionReason: 'The ordinance provides a ceiling, not the Municipality-confirmed exact operational charge.',
        );

        $retailTax = FeeRule::query()->updateOrCreate(
            ['code' => 'MRC-2A-02-B-RETAIL-BUSINESS-TAX'],
            [
                'line_of_business_id' => $retailLine->id,
                'name' => 'Business Tax - Wholesalers/Retailers/Dealers/Distributors',
                'category' => FeeRuleCategory::Tax,
                'scope' => FeeRuleScope::LineOfBusiness,
                'calculation_type' => FeeRuleCalculationType::Range,
                'basis' => 'declared_gross_sales',
                'amount_cents' => 0,
                'rate_basis_points' => null,
                'effective_from' => '2023-01-01',
                'effective_until' => null,
                'legal_basis' => 'LEGAL-MRC-001 Section 2A.02(b): graduated business tax for wholesalers, retailers, dealers, or distributors.',
                'is_active' => true,
                'legacy_source_id' => 'LEGAL-MRC-001:SECTION-2A.02-B',
                'metadata' => [
                    'source_id' => 'LEGAL-MRC-001',
                    'source_section' => 'Section 2A.02(b)',
                    'application_types' => ['renewal'],
                    'catalog_status' => 'recorded_non_executable',
                    'reconciliation_required' => true,
                    'extraction_scope' => 'The fixed-amount schedule remains recorded but non-executable pending municipal resolution of malformed and overlapping ordinance rows.',
                    'policy_boundaries' => [
                        'new_business_initial_local_business_tax_exemption',
                        'renewal_prior_year_gross_receipts_basis',
                        'rate_based_brackets',
                        'rounding_policy',
                        'pil_validation',
                        'deficiency_tax',
                        'surcharge_interest',
                    ],
                ],
            ],
        );

        collect([
            [0, 99_999, 2_266],
            [100_000, 199_999, 4_155],
            [200_000, 299_999, 6_295],
            [300_000, 399_999, 9_064],
            [400_000, 499_999, 12_590],
            [500_000, 599_999, 15_234],
            [600_000, 749_999, 18_004],
            [750_000, 799_999, 23_291],
            [800_000, 999_999, 23_543],
            [1_000_000, 1_499_999, 27_697],
            [1_500_000, 1_999_999, 34_622],
            [2_000_000, 2_999_999, 41_545],
            [3_000_000, 3_999_999, 55_394],
            [4_000_000, 4_999_999, 83_091],
            [5_000_000, 7_499_999, 124_636],
            [7_500_000, 9_999_999, 166_181],
            [10_000_000, 14_999_999, 235_424],
            [15_000_000, 19_999_999, 304_666],
            [20_000_000, 29_999_999, 415_454],
            [30_000_000, 49_999_999, 553_938],
            [50_000_000, 74_999_999, 830_907],
            [75_000_000, 99_999_999, 1_107_876],
            [100_000_000, 199_999_999, 1_258_950],
        ])->each(function (array $range) use ($retailTax): void {
            FeeRuleRange::query()->updateOrCreate(
                [
                    'fee_rule_id' => $retailTax->id,
                    'min_basis_cents' => $range[0],
                    'max_basis_cents' => $range[1],
                ],
                [
                    'amount_cents' => $range[2],
                    'rate_basis_points' => null,
                ],
            );
        });

        $this->reconcile(
            feeRule: $retailTax,
            originalText: 'Section 2A.02(b) includes "6,000.00 or more but less than 7,500.00", followed by "7,000.00 or more but less than 8,000.00", and malformed values including "150,0000.00" and "5000,000.00".',
            normalizedInterpretation: 'The current catalog transcription starts the second disputed interval at PHP 7,500.00 and interprets the malformed values as PHP 150,000.00 and PHP 500,000.00.',
            executionStatus: FeeRuleExecutionStatus::Blocked,
            executionReason: 'The wholesale/dealer schedule contains overlapping and malformed brackets that require an accepted municipal reconciliation.',
        );

        $this->seedProvisionRegister(
            newBusinessPermitFee: $newBusinessPermitFee,
            inspectionFee: $inspectionFee,
            registrationPlateFee: $registrationPlateFee,
            retailTax: $retailTax,
        );
        $this->seedTaxScheduleRows();
        $this->seedPolicyBoundaryClauses();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function fixedRule(string $code, string $name, int $amountCents, string $legalBasis, array $metadata): FeeRule
    {
        return FeeRule::query()->updateOrCreate(
            ['code' => $code],
            [
                'line_of_business_id' => null,
                'name' => $name,
                'category' => FeeRuleCategory::Fee,
                'scope' => FeeRuleScope::Application,
                'calculation_type' => FeeRuleCalculationType::Fixed,
                'basis' => 'none',
                'amount_cents' => $amountCents,
                'rate_basis_points' => null,
                'effective_from' => '2023-01-01',
                'effective_until' => null,
                'legal_basis' => $legalBasis,
                'is_active' => true,
                'legacy_source_id' => $metadata['source_id'].':'.$metadata['source_section'],
                'metadata' => $metadata,
            ],
        );
    }

    private function reconcile(
        FeeRule $feeRule,
        string $originalText,
        ?string $normalizedInterpretation,
        FeeRuleExecutionStatus $executionStatus,
        string $executionReason,
        string $decisionAuthority = 'Municipality of Ipil - decision pending',
        string $decisionReference = 'Engineering Program Review #005 Board Decision (software execution refusal)',
    ): FeeRuleReconciliation {
        return FeeRuleReconciliation::query()->updateOrCreate(
            [
                'fee_rule_id' => $feeRule->id,
                'version' => 1,
            ],
            [
                'legal_authority' => 'Municipality of Ipil Ordinance No. 08-656-2023',
                'evidence_reference' => $feeRule->legacy_source_id ?? 'LEGAL-MRC-001',
                'original_text' => $originalText,
                'normalized_interpretation' => $normalizedInterpretation,
                'decision_authority' => $decisionAuthority,
                'decision_reference' => $decisionReference,
                'effective_from' => '2023-01-01',
                'effective_until' => null,
                'execution_status' => $executionStatus,
                'execution_reason' => $executionReason,
                'decided_at' => '2026-08-15 00:00:00',
            ],
        );
    }

    private function seedProvisionRegister(
        FeeRule $newBusinessPermitFee,
        FeeRule $inspectionFee,
        FeeRule $registrationPlateFee,
        FeeRule $retailTax,
    ): void {
        $provisions = [
            $this->provision(
                code: 'MRC-2A-02-A-MANUFACTURERS',
                section: 'Section 2A.02(a)',
                title: 'Manufacturers, producers, assemblers, and processors',
                type: RevenueCodeProvisionType::TaxSchedule,
                excerpt: 'Graduated annual business-tax schedule for manufacturers and related producers, ending with a percentage rate for gross sales of PHP 6,500,000.00 or more.',
                notes: 'The schedule contains the malformed value "4,000,0000.00" and an upper percentage expressed as "not exceeding"; municipal reconciliation and production configuration are required.',
                metadata: ['chapter' => 2, 'article' => 'A', 'schedule_row_count' => 20, 'known_ambiguities' => ['malformed_numeric_value', 'statutory_ceiling']],
            ),
            $this->provision(
                code: 'MRC-2A-02-B-WHOLESALERS',
                section: 'Section 2A.02(b)',
                title: 'Wholesalers, distributors, and dealers',
                type: RevenueCodeProvisionType::TaxSchedule,
                excerpt: 'Graduated annual business-tax schedule for wholesalers, distributors, or dealers, ending with a percentage rate for gross sales of PHP 2,000,000.00 or more.',
                notes: 'The source has overlapping PHP 6,000.00-7,500.00 and PHP 7,000.00-8,000.00 rows plus malformed values including "150,0000.00" and "5000,000.00".',
                metadata: ['chapter' => 2, 'article' => 'A', 'schedule_row_count' => 24, 'known_ambiguities' => ['overlapping_ranges', 'malformed_numeric_values', 'statutory_ceiling']],
                feeRule: $retailTax,
            ),
            $this->provision(
                code: 'MRC-2A-02-C-EXPORTERS-ESSENTIALS',
                section: 'Section 2A.02(c)',
                title: 'Exporters and essential commodities',
                type: RevenueCodeProvisionType::PercentageRate,
                excerpt: 'Exporters and listed essential commodities are subject to a rate not exceeding one-half of the rates prescribed under subsections (a), (b), and (d), with export sales excluded from total sales.',
                notes: 'Execution depends on accepted classification of exporters and essential commodities and on reconciled base schedules under subsections (a), (b), and (d).',
                metadata: ['chapter' => 2, 'article' => 'A', 'dependent_sections' => ['2A.02(a)', '2A.02(b)', '2A.02(d)'], 'known_ambiguities' => ['eligibility_classification', 'dependent_unreconciled_schedules']],
            ),
            $this->provision(
                code: 'MRC-2A-02-D-RETAILERS',
                section: 'Section 2A.02(d)',
                title: 'Retailers',
                type: RevenueCodeProvisionType::PercentageRate,
                excerpt: 'Retail gross sales up to PHP 400,000.00 are stated at 2.52 percent, with 1.26 percent on sales in excess of the first PHP 400,000.00 and a barangay threshold stated separately.',
                notes: 'The operational treatment of the two rates, barangay taxing threshold, eligibility, and rounding requires municipal confirmation.',
                metadata: ['chapter' => 2, 'article' => 'A', 'known_ambiguities' => ['compound_rate_application', 'barangay_tax_boundary', 'rounding_policy']],
            ),
            $this->provision(
                code: 'MRC-2A-02-E-CONTRACTORS',
                section: 'Section 2A.02(e)',
                title: 'Contractors and independent contractors',
                type: RevenueCodeProvisionType::TaxSchedule,
                excerpt: 'Graduated annual business-tax schedule for contractors and independent contractors, including project-term installments and completion recomputation.',
                notes: 'The schedule contains overlapping PHP 300,000.00-500,000.00 and PHP 400,000.00-500,000.00 rows; installment, recomputation, deficiency, and refund behavior also require accepted policy.',
                metadata: ['chapter' => 2, 'article' => 'A', 'schedule_row_count' => 19, 'known_ambiguities' => ['overlapping_ranges', 'statutory_ceiling', 'minimum_tax_floor', 'project_term_installments', 'deficiency_or_refund']],
            ),
            $this->provision(
                code: 'MRC-2A-02-F-FINANCIAL-INSTITUTIONS',
                section: 'Section 2A.02(f)',
                title: 'Banks and other financial institutions',
                type: RevenueCodeProvisionType::PercentageRate,
                excerpt: 'Banks and other financial institutions are stated at 57.23 percent of one percent of enumerated gross receipts from the preceding calendar year.',
                notes: 'Taxable receipt classification, evidence requirements, production configuration, and rounding require municipal reconciliation before execution.',
                metadata: ['chapter' => 2, 'article' => 'A', 'known_ambiguities' => ['taxable_receipt_classification', 'documentary_basis', 'rounding_policy']],
            ),
            $this->provision(
                code: 'MRC-2A-02-G-ENUMERATED-SERVICES',
                section: 'Section 2A.02(g)',
                title: 'Enumerated service and amusement businesses',
                type: RevenueCodeProvisionType::TaxSchedule,
                excerpt: 'Enumerated service, amusement, real-estate, lodging, medical, cable, and computer establishments are assigned a graduated annual business-tax schedule.',
                notes: 'The schedule repeats overlapping PHP 300,000.00-500,000.00 and PHP 400,000.00-500,000.00 rows, states a maximum percentage for the final row, and specifies a minimum tax that requires accepted operational treatment.',
                metadata: ['chapter' => 2, 'article' => 'A', 'schedule_row_count' => 19, 'known_ambiguities' => ['overlapping_ranges', 'statutory_ceiling', 'minimum_tax_floor']],
            ),
            $this->provision(
                code: 'MRC-2A-02-H-PEDDLERS',
                section: 'Section 2A.02(h)',
                title: 'Peddlers',
                type: RevenueCodeProvisionType::FixedFee,
                excerpt: 'Peddlers are subject to an annual tax at a rate not exceeding PHP 62.75, with specified delivery vehicles exempted.',
                notes: 'The ordinance states a ceiling rather than an exact operational amount and includes eligibility and vehicle exemptions that require accepted configuration.',
                metadata: ['chapter' => 2, 'article' => 'A', 'known_ambiguities' => ['statutory_ceiling', 'eligibility_and_exemptions']],
            ),
            $this->provision(
                code: 'MRC-3A-02-B-NEW-MICRO-PERMIT',
                section: 'Section 3A.02(b)',
                title: "New-business micro-industry Mayor's Permit fee",
                type: RevenueCodeProvisionType::FixedFee,
                excerpt: 'For new business, Micro-Industry - P 200.00.',
                notes: 'Municipal enterprise-scale eligibility remains unresolved.',
                metadata: ['chapter' => 3, 'article' => 'A', 'known_ambiguities' => ['enterprise_scale_eligibility']],
                feeRule: $newBusinessPermitFee,
            ),
            $this->provision(
                code: 'MRC-3A-04-INSPECTION',
                section: 'Section 3A.04',
                title: 'Business inspection fee',
                type: RevenueCodeProvisionType::FixedFee,
                excerpt: 'Any business operation in the municipality should be charged an inspection fee of P350.00, uniform to all business establishments and payable per annum.',
                notes: 'Exact uniform annual amount is linked to the accepted executable reconciliation.',
                metadata: ['chapter' => 3, 'article' => 'A', 'known_ambiguities' => []],
                feeRule: $inspectionFee,
                status: RevenueCodeProvisionStatus::Reconciled,
            ),
            $this->provision(
                code: 'MRC-3A-05-REGISTRATION-PLATE',
                section: 'Section 3A.05',
                title: 'Business permit registration plate',
                type: RevenueCodeProvisionType::FixedFee,
                excerpt: 'Applicants shall pay an amount not to exceed Three Hundred Pesos (P300.00) for the Business Permit Registration Plate and handling.',
                notes: 'The ordinance supplies a ceiling, not the Municipality-confirmed exact operational amount.',
                metadata: ['chapter' => 3, 'article' => 'A', 'known_ambiguities' => ['statutory_ceiling']],
                feeRule: $registrationPlateFee,
            ),
        ];

        foreach ($provisions as $provision) {
            RevenueCodeProvision::query()->updateOrCreate(['code' => $provision['code']], $provision);
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function provision(
        string $code,
        string $section,
        string $title,
        RevenueCodeProvisionType $type,
        string $excerpt,
        string $notes,
        array $metadata,
        ?FeeRule $feeRule = null,
        RevenueCodeProvisionStatus $status = RevenueCodeProvisionStatus::ReconciliationRequired,
    ): array {
        return [
            'code' => $code,
            'fee_rule_id' => $feeRule?->id,
            'source_id' => 'LEGAL-MRC-001',
            'section_reference' => $section,
            'title' => $title,
            'provision_type' => $type,
            'evidence_summary' => $excerpt,
            'reconciliation_status' => $status,
            'reconciliation_notes' => $notes,
            'effective_from' => '2023-01-01',
            'metadata' => $metadata,
        ];
    }

    private function seedTaxScheduleRows(): void
    {
        $this->seedManufacturerScheduleRows();
        $this->seedWholesaleScheduleRows();
        $this->seedContractorScheduleRows();
        $this->seedEnumeratedServiceScheduleRows();
    }

    private function seedPolicyBoundaryClauses(): void
    {
        $this->persistPolicyBoundaryClauses('MRC-2A-02-B-WHOLESALERS', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-2A-02-B-MANUFACTURER-TAX-SCOPE',
                type: RevenueCodeProvisionClauseType::TaxScopeBoundary,
                sourceText: 'The Businesses enumerated in paragraph (a) above shall no longer be subject to the tax on wholesalers, distributors, or dealers herein provided for.',
                candidateInterpretation: 'Candidate tax-scope boundary: a business taxed under Section 2A.02(a) is excluded from a second wholesale, distributor, or dealer tax under Section 2A.02(b).',
                executionBlocker: 'The application needs an accepted activity-classification and multi-line allocation policy before this exclusion can be applied deterministically.',
                metadata: ['excluded_when_subject_to_section' => '2A.02(a)', 'excluded_tax_section' => '2A.02(b)'],
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-2A-02-C-EXPORTERS-ESSENTIALS', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-2A-02-C-DEPENDENT-HALF-RATE',
                type: RevenueCodeProvisionClauseType::DependentRate,
                sourceText: 'On Exporters, and on manufacturers, millers, producers, wholesalers, distributors, dealers or retailers of essential commodities enumerated hereunder at a rate not exceeding one-half (1/2) of the rates prescribed under subsections (a), (b), and (d) of this article.',
                candidateInterpretation: 'Candidate relationship: an eligible activity is bounded by one-half of the applicable accepted rate under Section 2A.02(a), (b), or (d).',
                executionBlocker: 'The source rates are not fully reconciled and the ordinance supplies a ceiling, not an accepted operational rate.',
                metadata: ['dependent_sections' => ['2A.02(a)', '2A.02(b)', '2A.02(d)'], 'rate_multiplier' => '0.5'],
                isCeiling: true,
            ),
            $this->policyBoundaryClause(
                sequence: 2,
                code: 'MRC-2A-02-C-ESSENTIAL-COMMODITIES',
                type: RevenueCodeProvisionClauseType::Eligibility,
                sourceText: '(1.) Rice and Corn; (2.) Wheat or cassava flour, meat dairy products, locally manufactured, processed or preserved food, sugar, salt and agricultural marine, and fresh water products, whether in their original state or not; (3.) Cooking oil and cooking gas; (4.) Laundry soap, detergents, and medicine; (5.) Agricultural implements, equipment and post-harvest facilities, fertilizers, pesticides, insecticides, herbicides and other farm inputs; (6.) Poultry feeds and other animal feeds; (7.) School Supplies and; (8.) Cement.',
                candidateInterpretation: 'Candidate eligibility catalog: eight source categories are preserved for municipal classification and product mapping.',
                executionBlocker: 'No accepted municipal commodity catalog or classification procedure currently maps a declared line of business to these legal categories.',
                metadata: ['category_count' => 8, 'categories' => ['rice_and_corn', 'flour_meat_dairy_food_sugar_salt_agricultural_and_aquatic_products', 'cooking_oil_and_gas', 'soap_detergents_and_medicine', 'agricultural_inputs_and_equipment', 'animal_feeds', 'school_supplies', 'cement']],
            ),
            $this->policyBoundaryClause(
                sequence: 3,
                code: 'MRC-2A-02-C-EXPORT-SALES-EXCLUSION',
                type: RevenueCodeProvisionClauseType::TaxBase,
                sourceText: 'For purposes of this provision, the term exporters shall refer to those who are principally engaged in the business of exporting goods and merchandise, as well as manufacturers and producers whose goods or products are both sold domestically and abroad. The amount of export sales shall be excluded from the total sales and shall be subject to the rates not exceeding one half (1/2) of the rates prescribed under paragraph (a), (b), and (d) of this article.',
                candidateInterpretation: 'Candidate tax-base split: export sales are separately identified from domestic sales before applying an accepted exporter rate.',
                executionBlocker: 'Exporter eligibility, the source wording around excluded sales, evidence requirements, and the accepted rate remain unresolved.',
                metadata: ['requires_separate_export_sales' => true, 'requires_separate_domestic_sales' => true],
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-2A-02-D-RETAILERS', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-2A-02-D-FIRST-RETAIL-BAND',
                type: RevenueCodeProvisionClauseType::RateBand,
                sourceText: 'Gross Sales/Receipts for the Preceding year: 400,000 or less. Rate of Tax per annum: 2.52%.',
                candidateInterpretation: 'Candidate first band: 252 basis points applies to preceding-year retail gross sales or receipts not exceeding PHP 400,000.00.',
                executionBlocker: 'The applicable population, rounding, and accepted operational configuration have not been reconciled.',
                metadata: ['basis_below_or_equal_cents' => 40_000_000],
                rateBasisPoints: '252.0000',
            ),
            $this->policyBoundaryClause(
                sequence: 2,
                code: 'MRC-2A-02-D-EXCESS-RETAIL-BAND',
                type: RevenueCodeProvisionClauseType::RateBand,
                sourceText: 'Gross Sales/Receipts for the Preceding year: More than 400,000.00. Rate of Tax per annum: 1.26%. The rate of two and fifty two percent (2.52%) per annum shall be imposed on sales not exceeding Four Hundred Thousand Pesos (P400,000.00) while the rate of one and twenty six percent (1.26 %) per annum shall be imposed on sales in excess of the first Four Hundred Thousand Pesos (P400,000.00).',
                candidateInterpretation: 'Candidate compound treatment: 252 basis points applies to the first PHP 400,000.00 and 126 basis points applies only to the excess.',
                executionBlocker: 'Compound-band application and monetary rounding require accepted municipal policy and production configuration.',
                metadata: ['threshold_cents' => 40_000_000, 'first_band_rate_basis_points' => '252.0000'],
                rateBasisPoints: '126.0000',
            ),
            $this->policyBoundaryClause(
                sequence: 3,
                code: 'MRC-2A-02-D-BARANGAY-AUTHORITY',
                type: RevenueCodeProvisionClauseType::AuthorityBoundary,
                sourceText: 'However, Barangays shall have the exclusive power to levy taxes on stores whose gross sales or receipts of the preceding calendar year does not exceed Thirty Thousand Pesos (P30,000.00) subject to existing laws and regulations.',
                candidateInterpretation: 'Candidate taxing-authority boundary: qualifying fixed retailers at or below PHP 30,000.00 fall under barangay taxing authority.',
                executionBlocker: 'Municipal intake needs an accepted rule for identifying the correct barangay authority and excluding or routing the municipal tax.',
                metadata: ['gross_sales_ceiling_cents' => 3_000_000, 'authority' => 'barangay'],
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-2A-02-E-CONTRACTORS', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-2A-02-E-MINIMUM-TAX',
                type: RevenueCodeProvisionClauseType::MinimumTax,
                sourceText: 'Provided, that in no case shall the tax on gross sales of P2,000,000.00 or more be less than P14,477.93.',
                candidateInterpretation: 'Candidate minimum: contractor tax for gross sales of PHP 2,000,000.00 or more has a PHP 14,477.93 floor.',
                executionBlocker: 'The source schedule has an overlapping bracket and a ceiling-only percentage; the floor cannot execute until the underlying schedule and rounding are reconciled.',
                metadata: ['gross_sales_from_cents' => 200_000_000],
                amountCents: 1_447_793,
            ),
            $this->policyBoundaryClause(
                sequence: 2,
                code: 'MRC-2A-02-E-INITIAL-CONTRACT-BASIS',
                type: RevenueCodeProvisionClauseType::InitialTaxBasis,
                sourceText: 'For the purposes of this section, the tax of general engineering, general building and specialty contractors shall initially be based on the total contract price.',
                candidateInterpretation: 'Candidate initial basis: qualifying contractor tax starts from total contract price rather than preceding-year gross receipts.',
                executionBlocker: 'Contract type eligibility, contract amendments, project scope, and the accepted conversion from contract price to annual liability remain unresolved.',
                metadata: ['candidate_basis' => 'total_contract_price', 'contractor_classes' => ['general_engineering', 'general_building', 'specialty']],
            ),
            $this->policyBoundaryClause(
                sequence: 3,
                code: 'MRC-2A-02-E-PROJECT-INSTALLMENTS',
                type: RevenueCodeProvisionClauseType::InstallmentSchedule,
                sourceText: 'For the purposes of this section, the tax of general engineering, general building and specialty contractors shall initially be based on the total contract price, payable in equal annual installments within the project term.',
                candidateInterpretation: 'Candidate schedule: the initial contractor tax is divided into equal annual installments across the project term.',
                executionBlocker: 'Installment count, partial years, due dates, project extensions, cancellations, and remainder allocation require accepted municipal policy.',
                metadata: ['frequency' => 'annual', 'allocation' => 'equal', 'term_basis' => 'project_term'],
            ),
            $this->policyBoundaryClause(
                sequence: 4,
                code: 'MRC-2A-02-E-COMPLETION-RECOMPUTATION',
                type: RevenueCodeProvisionClauseType::CompletionRecomputation,
                sourceText: 'Upon completion of the project, the taxes shall be recomputed on the basis of the gross receipts for the proceeding calendar years and the deficiency tax, if there be any, shall be collected provided in this code or the excess tax payment shall be refunded.',
                candidateInterpretation: 'Candidate completion treatment: recompute from project-period gross receipts, collect a deficiency, or refund an excess payment.',
                executionBlocker: 'Completion authority, gross-receipts period, deficiency procedures, refund authority, audit evidence, and the source wording “proceeding calendar years” require municipal reconciliation.',
                metadata: ['candidate_completion_basis' => 'project_period_gross_receipts', 'candidate_outcomes' => ['deficiency_collection', 'excess_refund'], 'source_wording_question' => 'proceeding_calendar_years'],
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-2A-02-F-FINANCIAL-INSTITUTIONS', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-2A-02-F-GROSS-RECEIPTS-RATE',
                type: RevenueCodeProvisionClauseType::RateBand,
                sourceText: 'On banks and other financial institutions, at the rate of fifty-seven and twenty-three percent of one percent (57.23% of 1%) of the gross receipts of the preceding calendar year derived from the enumerated sources.',
                candidateInterpretation: 'Candidate rate: 57.23 percent of one percent is represented as 57.23 basis points against accepted taxable gross receipts.',
                executionBlocker: 'The taxable-receipt classifications, operational rate authority, source evidence, and rounding remain unreconciled.',
                metadata: ['source_expression' => '57.23% of 1%'],
                rateBasisPoints: '57.2300',
            ),
            $this->policyBoundaryClause(
                sequence: 2,
                code: 'MRC-2A-02-F-TAXABLE-RECEIPTS',
                type: RevenueCodeProvisionClauseType::TaxableReceiptCatalog,
                sourceText: 'Banks and Banking Institutions whether these transactions are recorded in the regional and principal office: (1) Interest from loans and discounts; (2) Interest earned and actually collected on interbank loans; (3) Rental of property; (4) Income earned and actually collected from acquired assets; (5) Income from sales or exchange of assets and property; (6) Cash dividends earned and received on equity investments; (7) Bank Commissions from lending activities; (8) Income component of rentals from financial leasing; (9) Interest Income from unpaid amount due from delinquent cardholders and “Financial Charges”; (10) Merchant’s Discount; (11) Income from Automated Teller Machine (ATM); (12) General consultancy services; (13) All other similar activities consisting essentially of the sales of services for a fee. Other Financial Institutions whether these transactions are recorded in the regional and principal office: (1) Gross receipts derived from interest, commissions and discounts from lending activities; (2) Income from financial leasing, dividends and rentals on property; (3) Profit from exchange or sale of property, insurance premium.',
                candidateInterpretation: 'Candidate receipt catalog: source categories are preserved separately for banks and other financial institutions before any ledger or account mapping.',
                executionBlocker: 'No accepted operational mapping currently determines which production receipt accounts enter or leave the taxable base.',
                metadata: ['bank_receipt_categories' => ['loan_interest_and_discounts', 'interbank_loans', 'property_and_equipment_rentals', 'acquired_assets', 'asset_sales_or_exchanges', 'cash_dividends', 'bank_commissions', 'financial_leasing', 'credit_card_charges', 'merchant_discount', 'atm_income', 'consultancy', 'similar_fee_based_services'], 'other_financial_institution_categories' => ['lending_interest_commissions_and_discounts', 'financial_leasing_dividends_and_rentals', 'property_exchange_or_sale_and_insurance_premiums']],
            ),
            $this->policyBoundaryClause(
                sequence: 3,
                code: 'MRC-2A-02-F-JOINT-STATEMENT',
                type: RevenueCodeProvisionClauseType::DocumentaryRequirement,
                sourceText: 'At the time of the annual payment of the tax due, the Head Office or branch of a bank shall submit to the Municipal Treasurer a notarized Joint Statement of Annual Income (Schedule of Annual Income) for the preceding calendar year which shall be signed by a designated Officer of the Head Office and by the Branch Manager.',
                candidateInterpretation: 'Candidate documentary requirement: annual financial-institution tax evidence includes a notarized joint annual-income statement with two authorized signatories.',
                executionBlocker: 'Document format, signer authority, review responsibility, and documentary-sufficiency policy are not yet accepted.',
                metadata: ['notarization_required' => true, 'required_signatory_roles' => ['designated_head_office_officer', 'branch_manager']],
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-2A-02-G-ENUMERATED-SERVICES', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-2A-02-G-ENUMERATED-BUSINESSES',
                type: RevenueCodeProvisionClauseType::Eligibility,
                sourceText: 'On the businesses hereunder enumerated: (14) Cafes, cafeterias, ice cream and other refreshment parlors, restaurants, soda fountain bars, carinderias or food caterers; (15) Amusement places, including places wherein customers thereof actively participate without making bets or wagers, including but not limited to night clubs, or day clubs, cocktail lounges, cabarets or dance halls, karaoke bars, skating rinks, bath houses, swimming pools, exclusive clubs such as country and sports clubs, resorts and other similar places, billiard and pool tables, bowling alleys, circuses, carnivals, merry go-rounds, roller coasters, ferries wheels, swings, shooting galleries, and other similar contrivances, theaters and cinema houses, boxing stadia, race tracks, cockpits and other similar establishments; (16) Commission agents; (17) Lessors, dealers, brokers of real estate; (18) On travel agencies and travel agents; (19) On boarding houses, pension houses, motels, apartments, apartelles, and condominiums; (20) Subdivision owners/ Private cemeteries and Memorial Parks; (21) Privately-owned markets; (22) Hospitals, medical clinics, dental clinics, therapeutic clinics, medical laboratories, dental, laboratories; (23) Operators of Cable Network System; (24) Operators of computer services establishment.',
                candidateInterpretation: 'Candidate eligibility catalog: the source enumerates eleven service-business groups numbered 14 through 24.',
                executionBlocker: 'Operational business classifications and the unexplained source numbering that begins at 14 require accepted municipal mapping before schedule execution.',
                metadata: ['category_count' => 11, 'source_item_numbers' => [14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24], 'known_ambiguity' => 'enumeration_starts_at_14', 'categories' => ['food_and_refreshment_establishments', 'amusement_places', 'commission_agents', 'real_estate_lessors_dealers_and_brokers', 'travel_agencies_and_agents', 'lodging_and_residential_lessors', 'subdivision_cemetery_and_memorial_park_owners', 'private_markets', 'medical_and_dental_establishments', 'cable_network_operators', 'computer_service_establishments']],
            ),
            $this->policyBoundaryClause(
                sequence: 2,
                code: 'MRC-2A-02-G-MINIMUM-TAX',
                type: RevenueCodeProvisionClauseType::MinimumTax,
                sourceText: 'Provided that in no case shall the tax on gross sales of P2,000,000.00 or more be less than P14,477.93.',
                candidateInterpretation: 'Candidate minimum: enumerated-service tax for gross sales of PHP 2,000,000.00 or more has a PHP 14,477.93 floor.',
                executionBlocker: 'The source schedule has an overlapping bracket and a ceiling-only percentage; the floor cannot execute until the schedule, eligibility, and rounding are reconciled.',
                metadata: ['gross_sales_from_cents' => 200_000_000],
                amountCents: 1_447_793,
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-2A-02-H-PEDDLERS', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-2A-02-H-ANNUAL-CEILING',
                type: RevenueCodeProvisionClauseType::AmountCeiling,
                sourceText: 'On Peddlers engaged in the sale of any merchandise or article of commerce, at the rate not exceeding P62.75 per peddler annually.',
                candidateInterpretation: 'Candidate maximum annual amount: PHP 62.75.',
                executionBlocker: 'The ordinance supplies a ceiling rather than the Municipality-confirmed exact operational amount.',
                metadata: ['period' => 'annual'],
                amountCents: 6_275,
                isCeiling: true,
            ),
            $this->policyBoundaryClause(
                sequence: 2,
                code: 'MRC-2A-02-H-DELIVERY-VEHICLE-EXEMPTION',
                type: RevenueCodeProvisionClauseType::Exemption,
                sourceText: 'Delivery trucks, vans or vehicles used by manufacturers, producers, wholesalers, dealers, or retailers, enumerated under Section 141 of R.A. 7160 shall be exempt from the peddler taxes herein imposed.',
                candidateInterpretation: 'Candidate exemption: qualifying delivery vehicles used by the referenced business classes are outside the peddlers-tax population.',
                executionBlocker: 'Vehicle use, business classification, and Section 141 eligibility require accepted evidence and municipal review rules.',
                metadata: ['external_legal_reference' => 'Republic Act No. 7160 Section 141', 'vehicle_types' => ['delivery_truck', 'van', 'vehicle']],
            ),
            $this->policyBoundaryClause(
                sequence: 3,
                code: 'MRC-2A-02-H-PAYMENT-TIMING',
                type: RevenueCodeProvisionClauseType::PaymentTiming,
                sourceText: 'The tax herein imposed shall be payable within the first twenty (20) days of January. An individual who will start to peddle merchandise or articles of commerce after January 20 shall pay the full amount of the tax before engaging in such activity.',
                candidateInterpretation: 'Candidate timing: existing peddlers pay by January 20; a new entrant after that date pays the full annual amount before operating.',
                executionBlocker: 'The exact amount, start-date evidence, collection workflow, and treatment of late or renewed activity remain unreconciled.',
                metadata: ['annual_due_month' => 1, 'annual_due_day' => 20, 'new_entrant_proration' => false],
            ),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $clauses
     */
    private function persistPolicyBoundaryClauses(string $provisionCode, array $clauses): void
    {
        $provision = RevenueCodeProvision::query()->where('code', $provisionCode)->sole();

        $provision->clauses()->whereNotIn('sequence', array_column($clauses, 'sequence'))->delete();

        foreach ($clauses as $clause) {
            RevenueCodeProvisionClause::query()->updateOrCreate(
                [
                    'revenue_code_provision_id' => $provision->id,
                    'sequence' => $clause['sequence'],
                ],
                $clause,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function policyBoundaryClause(
        int $sequence,
        string $code,
        RevenueCodeProvisionClauseType $type,
        string $sourceText,
        string $candidateInterpretation,
        string $executionBlocker,
        array $metadata,
        ?int $amountCents = null,
        ?string $rateBasisPoints = null,
        bool $isCeiling = false,
    ): array {
        return [
            'sequence' => $sequence,
            'code' => $code,
            'clause_type' => $type,
            'source_text' => $sourceText,
            'candidate_interpretation' => $candidateInterpretation,
            'amount_cents' => $amountCents,
            'rate_basis_points' => $rateBasisPoints,
            'is_ceiling' => $isCeiling,
            'reconciliation_status' => RevenueCodeProvisionStatus::ReconciliationRequired,
            'execution_blocker' => $executionBlocker,
            'metadata' => [
                ...$metadata,
                'source_id' => 'LEGAL-MRC-001',
                'candidate_values_are_non_executable' => true,
            ],
        ];
    }

    private function seedManufacturerScheduleRows(): void
    {
        $rows = [
            $this->scheduleRow(1, 'Less than Php10,000.00', '207.73', 0, 1_000_000, 20_773, sectionCode: 'A'),
            $this->scheduleRow(2, '10,000.00 or more but less than 15,000.00', '276.96', 1_000_000, 1_500_000, 27_696, sectionCode: 'A'),
            $this->scheduleRow(3, '15,000.00 or more but less than 20,000.00', '380.20', 1_500_000, 2_000_000, 38_020, sectionCode: 'A'),
            $this->scheduleRow(4, '20,000.00 or more but less than 30,000.00', '553.94', 2_000_000, 3_000_000, 55_394, sectionCode: 'A'),
            $this->scheduleRow(5, '30,000.00 or more but less than 40,000.00', '830.91', 3_000_000, 4_000_000, 83_091, sectionCode: 'A'),
            $this->scheduleRow(6, '40,000.00 or more but less than 50,000.00', '1,038.64', 4_000_000, 5_000_000, 103_864, sectionCode: 'A'),
            $this->scheduleRow(7, '50,000.00 or more but less than 75,000.00', '1,661.81', 5_000_000, 7_500_000, 166_181, sectionCode: 'A'),
            $this->scheduleRow(8, '75,000.00 or more but less than 100,000.00', '2,077.27', 7_500_000, 10_000_000, 207_727, sectionCode: 'A'),
            $this->scheduleRow(9, '100,000.00 or more but less than 150,000.00', '2,769.69', 10_000_000, 15_000_000, 276_969, sectionCode: 'A'),
            $this->scheduleRow(10, '150,000.00 or more but less than 200,000.00', '3,462.11', 15_000_000, 20_000_000, 346_211, sectionCode: 'A'),
            $this->scheduleRow(11, '200,000.00 or more but less than 300,000.00', '4,846.96', 20_000_000, 30_000_000, 484_696, sectionCode: 'A'),
            $this->scheduleRow(12, '300,000.00 or more but less than 500,000.00', '6,924.23', 30_000_000, 50_000_000, 692_423, sectionCode: 'A'),
            $this->scheduleRow(13, '500,000.00 or more but less than 750,000.00', '10,071.60', 50_000_000, 75_000_000, 1_007_160, sectionCode: 'A'),
            $this->scheduleRow(14, '750,000.00 or more but less than 1,000,000.00', '12,589.50', 75_000_000, 100_000_000, 1_258_950, sectionCode: 'A'),
            $this->scheduleRow(15, '1,000,000.00 or more but less than 2,000,000.00', '17,310.56', 100_000_000, 200_000_000, 1_731_056, sectionCode: 'A'),
            $this->scheduleRow(16, '2,000,000.00 or more but less than 3,000,000.00', '20,772.68', 200_000_000, 300_000_000, 2_077_268, sectionCode: 'A'),
            $this->scheduleRow(17, '3,000,000.00 or more but less than 4,000,000.00', '24,927.21', 300_000_000, 400_000_000, 2_492_721, sectionCode: 'A'),
            $this->scheduleRow(18, '4,000,0000.00 or more but less than 5,000,000.00', '29,081.74', 400_000_000, 500_000_000, 2_908_174, RevenueCodeProvisionRowStatus::ReconciliationRequired, 'Candidate lower bound assumes the malformed source value "4,000,0000.00" means PHP 4,000,000.00.', sectionCode: 'A'),
            $this->scheduleRow(19, '5,000,000.00 or more but less than 6,500,000.00', '30,686.91', 500_000_000, 650_000_000, 3_068_691, sectionCode: 'A'),
            $this->scheduleRow(20, '6,500,000.00 or more', 'at rate not exceeding forty-seven and twenty-one percent (47.21%) of one percent (1%)', 650_000_000, null, null, RevenueCodeProvisionRowStatus::ReconciliationRequired, 'The ordinance provides a maximum percentage, not an exact accepted operational rate.', '47.2100', true, 'A'),
        ];

        $this->persistScheduleRows('MRC-2A-02-A-MANUFACTURERS', $rows);
    }

    private function seedWholesaleScheduleRows(): void
    {
        $rows = [
            $this->scheduleRow(1, 'Less than Php1,000.00', '22.66', 0, 100_000, 2_266),
            $this->scheduleRow(2, '1,000.00 or more but less than 2,000.00', '41.55', 100_000, 200_000, 4_155),
            $this->scheduleRow(3, '2,000.00 or more but less than 3,000.00', '62.95', 200_000, 300_000, 6_295),
            $this->scheduleRow(4, '3,000.00 or more but less than 4,000.00', '90.64', 300_000, 400_000, 9_064),
            $this->scheduleRow(5, '4,000.00 or more but less than 5,000.00', '125.90', 400_000, 500_000, 12_590),
            $this->scheduleRow(6, '5,000.00 or more but less than 6,000.00', '152.34', 500_000, 600_000, 15_234),
            $this->scheduleRow(7, '6,000.00 or more but less than 7,500.00', '180.04', 600_000, 750_000, 18_004),
            $this->scheduleRow(8, '7,000.00 or more but less than 8,000.00', '232.91', 700_000, 800_000, 23_291),
            $this->scheduleRow(9, '8,000.00 or more but less than 10,000.00', '235.43', 800_000, 1_000_000, 23_543),
            $this->scheduleRow(10, '10,000.00 or more but less than 15,000.00', '276.97', 1_000_000, 1_500_000, 27_697),
            $this->scheduleRow(11, '15,000.00 or more but less than 20,000.00', '346.22', 1_500_000, 2_000_000, 34_622),
            $this->scheduleRow(12, '20,000.00 or more but less than 30,000.00', '415.45', 2_000_000, 3_000_000, 41_545),
            $this->scheduleRow(13, '30,000.00 or more but less than 40,000.00', '553.94', 3_000_000, 4_000_000, 55_394),
            $this->scheduleRow(14, '40,000.00 or more but less than 50,000.00', '830.91', 4_000_000, 5_000_000, 83_091),
            $this->scheduleRow(15, '50,000.00 or more but less than 75,000.00', '1,246.36', 5_000_000, 7_500_000, 124_636),
            $this->scheduleRow(16, '75,000.00 or more but less than 100,000.00', '1,661.81', 7_500_000, 10_000_000, 166_181),
            $this->scheduleRow(17, '100,000.00 or more but less than 150,000.00', '2,354.24', 10_000_000, 15_000_000, 235_424),
            $this->scheduleRow(18, '150,0000.00 or more but less than 200,000.00', '3,046.66', 15_000_000, 20_000_000, 304_666, RevenueCodeProvisionRowStatus::ReconciliationRequired, 'Candidate lower bound assumes the malformed source value "150,0000.00" means PHP 150,000.00.'),
            $this->scheduleRow(19, '200,000.00 or more but less than 300,000.00', '4,154.54', 20_000_000, 30_000_000, 415_454),
            $this->scheduleRow(20, '300,000.00 or more but less than 500,000.00', '5,539.38', 30_000_000, 50_000_000, 553_938),
            $this->scheduleRow(21, '5000,000.00 or more but less than 750,000.00', '8,309.07', 50_000_000, 75_000_000, 830_907, RevenueCodeProvisionRowStatus::ReconciliationRequired, 'Candidate lower bound assumes the malformed source value "5000,000.00" means PHP 500,000.00.'),
            $this->scheduleRow(22, '750,000.00 or more but less than 1,000,000.00', '11,078.76', 75_000_000, 100_000_000, 1_107_876),
            $this->scheduleRow(23, '1,000,000.00 or more but less than 2,000,000.00', '12,589.50', 100_000_000, 200_000_000, 1_258_950),
            $this->scheduleRow(24, '2,000,000.00 or more', 'at rate not exceeding sixty-two and ninety-five percent (62.95%) of one percent (1%)', 200_000_000, null, null, RevenueCodeProvisionRowStatus::ReconciliationRequired, 'The ordinance provides a maximum percentage, not an exact accepted operational rate.', '62.9500', true),
        ];

        $this->persistScheduleRows('MRC-2A-02-B-WHOLESALERS', $rows);
    }

    private function seedContractorScheduleRows(): void
    {
        $this->persistScheduleRows(
            'MRC-2A-02-E-CONTRACTORS',
            $this->contractorStyleScheduleRows('E', 34_621, '62.9500'),
        );
    }

    private function seedEnumeratedServiceScheduleRows(): void
    {
        $this->persistScheduleRows(
            'MRC-2A-02-G-ENUMERATED-SERVICES',
            $this->contractorStyleScheduleRows('G', 34_622, '57.2300'),
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function contractorStyleScheduleRows(string $sectionCode, int $twentyThousandAmountCents, string $ceilingRateBasisPoints): array
    {
        $ceilingText = $sectionCode === 'E'
            ? 'at rate not exceeding sixty-two and ninety-five percent (62.95%) of one percent (1%)'
            : 'at rate not exceeding fifty-seven and twenty-three percent (57.23%) of one percent (1%)';

        return [
            $this->scheduleRow(1, 'Less than Php5,000.00', '34.34', 0, 500_000, 3_434, sectionCode: $sectionCode),
            $this->scheduleRow(2, '5,000.00 or more but less than 10,000.00', '77.26', 500_000, 1_000_000, 7_726, sectionCode: $sectionCode),
            $this->scheduleRow(3, '10,000.00 or more but less than 15,000.00', '131.62', 1_000_000, 1_500_000, 13_162, sectionCode: $sectionCode),
            $this->scheduleRow(4, '15,000.00 or more but less than 20,000.00', '207.73', 1_500_000, 2_000_000, 20_773, sectionCode: $sectionCode),
            $this->scheduleRow(5, '20,000.00 or more but less than 30,000.00', $sectionCode === 'E' ? '346.21' : '346.22', 2_000_000, 3_000_000, $twentyThousandAmountCents, sectionCode: $sectionCode),
            $this->scheduleRow(6, '30,000.00 or more but less than 40,000.00', '484.70', 3_000_000, 4_000_000, 48_470, sectionCode: $sectionCode),
            $this->scheduleRow(7, '40,000.00 or more but less than 50,000.00', '692.42', 4_000_000, 5_000_000, 69_242, sectionCode: $sectionCode),
            $this->scheduleRow(8, '50,000.00 or more but less than 75,000.00', '1,107.88', 5_000_000, 7_500_000, 110_788, sectionCode: $sectionCode),
            $this->scheduleRow(9, '75,000.00 or more but less than 100,000.00', '1,661.81', 7_500_000, 10_000_000, 166_181, sectionCode: $sectionCode),
            $this->scheduleRow(10, '100,000.00 or more but less than 150,000.00', '2,492.72', 10_000_000, 15_000_000, 249_272, sectionCode: $sectionCode),
            $this->scheduleRow(11, '150,000.00 or more but less than 200,000.00', '3,323.63', 15_000_000, 20_000_000, 332_363, sectionCode: $sectionCode),
            $this->scheduleRow(12, '200,000.00 or more but less than 250,000.00', '4,569.99', 20_000_000, 25_000_000, 456_999, sectionCode: $sectionCode),
            $this->scheduleRow(13, '250,000.00 or more but less than 300,000.00', '5,816.35', 25_000_000, 30_000_000, 581_635, sectionCode: $sectionCode),
            $this->scheduleRow(14, '300,000.00 or more but less than 500,000.00', '7,755.13', 30_000_000, 50_000_000, 775_513, sectionCode: $sectionCode),
            $this->scheduleRow(15, '400,000.00 or more but less than 500,000.00', '10,386.34', 40_000_000, 50_000_000, 1_038_634, sectionCode: $sectionCode),
            $this->scheduleRow(16, '500,000.00 or more but less than 750,000.00', '11,645.29', 50_000_000, 75_000_000, 1_164_529, sectionCode: $sectionCode),
            $this->scheduleRow(17, '750,000.00 or more but less than 1,000,000.00', '12,904.24', 75_000_000, 100_000_000, 1_290_424, sectionCode: $sectionCode),
            $this->scheduleRow(18, '1,000,000.00 or more but less than 2,000,000.00', '14,477.93', 100_000_000, 200_000_000, 1_447_793, sectionCode: $sectionCode),
            $this->scheduleRow(19, '2,000,000.00 or more', $ceilingText, 200_000_000, null, null, RevenueCodeProvisionRowStatus::ReconciliationRequired, 'The ordinance provides a maximum percentage, not an exact accepted operational rate; the stated PHP 14,477.93 minimum also requires accepted operational treatment.', $ceilingRateBasisPoints, true, $sectionCode),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function persistScheduleRows(string $provisionCode, array $rows): void
    {
        $provision = RevenueCodeProvision::query()->where('code', $provisionCode)->sole();

        $provision->rows()->whereNotIn('sequence', array_column($rows, 'sequence'))->delete();

        foreach ($rows as $row) {
            RevenueCodeProvisionRow::query()->updateOrCreate(
                [
                    'revenue_code_provision_id' => $provision->id,
                    'sequence' => $row['sequence'],
                ],
                $row,
            );
        }
    }

    /** @return array<string, mixed> */
    private function scheduleRow(
        int $sequence,
        string $sourceBasisText,
        string $sourceValueText,
        ?int $basisFromCents,
        ?int $basisBelowCents,
        ?int $amountCents,
        RevenueCodeProvisionRowStatus $status = RevenueCodeProvisionRowStatus::Exact,
        ?string $normalizationNotes = null,
        ?string $rateBasisPoints = null,
        bool $isCeiling = false,
        string $sectionCode = 'B',
    ): array {
        return [
            'sequence' => $sequence,
            'code' => 'MRC-2A-02-'.$sectionCode.'-ROW-'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
            'source_basis_text' => $sourceBasisText,
            'source_value_text' => $sourceValueText,
            'basis_from_cents' => $basisFromCents,
            'basis_below_cents' => $basisBelowCents,
            'amount_cents' => $amountCents,
            'rate_basis_points' => $rateBasisPoints,
            'is_ceiling' => $isCeiling,
            'normalization_status' => $status,
            'normalization_notes' => $normalizationNotes,
            'metadata' => [
                'candidate_values_are_non_executable' => true,
            ],
        ];
    }
}
