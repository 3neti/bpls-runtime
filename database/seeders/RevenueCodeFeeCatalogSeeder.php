<?php

namespace Database\Seeders;

use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleExecutionStatus;
use App\Enums\FeeRuleScope;
use App\Enums\RevenueCodeProvisionRowStatus;
use App\Enums\RevenueCodeProvisionStatus;
use App\Enums\RevenueCodeProvisionType;
use App\Models\FeeRule;
use App\Models\FeeRuleRange;
use App\Models\FeeRuleReconciliation;
use App\Models\LineOfBusiness;
use App\Models\RevenueCodeProvision;
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
