<?php

namespace Database\Seeders;

use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleExecutionStatus;
use App\Enums\FeeRuleScope;
use App\Enums\RevenueCodeProvisionStatus;
use App\Enums\RevenueCodeProvisionType;
use App\Models\FeeRule;
use App\Models\FeeRuleRange;
use App\Models\FeeRuleReconciliation;
use App\Models\LineOfBusiness;
use App\Models\RevenueCodeProvision;
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
                metadata: ['chapter' => 2, 'article' => 'A', 'schedule_row_count' => 19, 'known_ambiguities' => ['overlapping_ranges', 'project_term_installments', 'deficiency_or_refund']],
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
                notes: 'The extracted table is visually interleaved and repeats overlapping PHP 300,000.00-500,000.00 and PHP 400,000.00-500,000.00 rows; source layout and rates require municipal reconciliation.',
                metadata: ['chapter' => 2, 'article' => 'A', 'schedule_row_count' => 19, 'known_ambiguities' => ['source_layout_corruption', 'overlapping_ranges', 'statutory_ceiling']],
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
}
