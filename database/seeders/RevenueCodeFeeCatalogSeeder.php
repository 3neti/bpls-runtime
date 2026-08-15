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
                type: RevenueCodeProvisionType::FixedFee,
                excerpt: 'Graduated annual business-tax schedule for manufacturers and related producers, ending with a percentage rate for gross sales of PHP 6,500,000.00 or more.',
                notes: 'The schedule contains the malformed value "4,000,0000.00" and an upper percentage expressed as "not exceeding"; municipal reconciliation and production configuration are required.',
                metadata: ['chapter' => 2, 'article' => 'A', 'schedule_row_count' => 20, 'known_ambiguities' => ['malformed_numeric_value', 'statutory_ceiling']],
            ),
            $this->provision(
                code: 'MRC-2A-02-B-WHOLESALERS',
                section: 'Section 2A.02(b)',
                title: 'Wholesalers, distributors, and dealers',
                type: RevenueCodeProvisionType::FixedFee,
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
                code: 'MRC-2B-02-04-MOBILE-TRADERS',
                section: 'Section 2B.02-2B.04',
                title: 'Tax on mobile traders',
                type: RevenueCodeProvisionType::PercentageRate,
                excerpt: 'Mobile traders are subject to a stated annual percentage of gross receipts, payable upon Mayor’s Permit issuance, with taxable receipts determined through the Presumptive Income Level technique.',
                notes: 'Mobile-trader classification, gross-receipts evidence, PIL mapping, permit-issuance payment point, rate arithmetic, and rounding require municipal reconciliation.',
                metadata: ['chapter' => 2, 'article' => 'B', 'known_ambiguities' => ['mobile_trader_classification', 'pil_classification_mapping', 'permit_issuance_payment_point', 'rounding_policy']],
            ),
            $this->provision(
                code: 'MRC-2B-05-06-PUBLIC-UTILITY-VEHICLES',
                section: 'Section 2B.05-2B.06',
                title: 'Tax on public utility vehicle operators',
                type: RevenueCodeProvisionType::TaxSchedule,
                excerpt: 'Operators maintaining a booking office, terminal, or waiting station for covered passenger vehicles are assigned annual per-unit amounts.',
                notes: 'Operator, franchise, terminal, vehicle-class, unit-count, proration, evidence, and payment-date rules require accepted municipal procedure.',
                metadata: ['chapter' => 2, 'article' => 'B', 'known_ambiguities' => ['operator_and_franchise_eligibility', 'municipal_terminal_nexus', 'vehicle_classification', 'unit_count_and_proration', 'payment_timing']],
            ),
            $this->provision(
                code: 'MRC-2B-07-AMUSEMENT-OPERATORS',
                section: 'Section 2B.07',
                title: 'Tax on ambulant and itinerant amusement operators',
                type: RevenueCodeProvisionType::TaxSchedule,
                excerpt: 'Ambulant and itinerant amusement operators during fiestas and fairs are assigned stated daily rates by activity.',
                notes: 'Fiesta or fair designation, ambulant or itinerant classification, activity mapping, operating-day count, combinations, collection point, and rounding require reconciliation.',
                metadata: ['chapter' => 2, 'article' => 'B', 'known_ambiguities' => ['event_and_operator_eligibility', 'activity_classification', 'operating_day_count', 'combined_activities', 'collection_procedure']],
            ),
            $this->provision(
                code: 'MRC-2B-08-09-OTHER-BUSINESSES',
                section: 'Section 2B.08-2B.09',
                title: 'Tax on other businesses designated by the Sanggunian',
                type: RevenueCodeProvisionType::PercentageRate,
                excerpt: 'Businesses not otherwise specified may be taxed when the Sanggunian deems proper, subject to a stated ceiling for businesses covered by national excise, value-added, or percentage tax.',
                notes: 'The covered-business designation, Sanggunian authority record, national-tax classification, exact operational rate, tax base, preceding-year evidence, and payment trigger require reconciliation.',
                metadata: ['chapter' => 2, 'article' => 'B', 'known_ambiguities' => ['sanggunian_designation_authority', 'national_tax_classification', 'statutory_ceiling', 'tax_base_and_evidence', 'payment_trigger']],
            ),
            $this->provision(
                code: 'MRC-2C-01-PETROLEUM-EXEMPTION',
                section: 'Section 2C.01',
                title: 'Petroleum-business local-tax exemption',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'Businesses engaged in producing, manufacturing, refining, distributing, or selling oil, gasoline, and other petroleum products are stated as exempt from local tax under Articles A and B.',
                notes: 'Product, activity, mixed-business, establishment, national-law, and fee-versus-tax boundaries require municipal and legal reconciliation.',
                metadata: ['chapter' => 2, 'article' => 'C', 'known_ambiguities' => ['petroleum_product_classification', 'covered_activities', 'mixed_business_allocation', 'tax_versus_regulatory_fee_scope']],
            ),
            $this->provision(
                code: 'MRC-2C-02-NEWLY-STARTED-BUSINESS',
                section: 'Section 2C.02',
                title: 'Newly started business tax treatment',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'Newly started businesses are stated as not liable for initial local business tax but remain subject to permit and regulatory charges; the succeeding year uses preceding-year gross receipts or a fraction thereof.',
                notes: 'New-business identity, initial-period boundary, permit and regulatory charge applicability, succeeding-year basis, fraction-of-year treatment, evidence, and interaction with other provisions require accepted policy.',
                metadata: ['chapter' => 2, 'article' => 'C', 'known_ambiguities' => ['new_business_identity', 'initial_tax_period', 'permit_and_regulatory_charge_catalog', 'succeeding_year_fractional_basis', 'evidence_and_rounding']],
            ),
            $this->provision(
                code: 'MRC-2D-01-SITUS-DEFINITIONS',
                section: 'Section 2D.01(a)',
                title: 'Business-tax situs definitions',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance defines principal office, branch or sales office, warehouse, plantation, and experimental farm for business-tax situs.',
                notes: 'Registration-document authority, location evidence, operational facts, mixed-use facilities, notice, and classification decisions require municipal procedure.',
                metadata: ['chapter' => 2, 'article' => 'D', 'known_ambiguities' => ['registration_document_authority', 'facility_classification', 'mixed_use_facilities', 'relocation_notice_procedure', 'experimental_farm_sales']],
            ),
            $this->provision(
                code: 'MRC-2D-01-SALES-ALLOCATION',
                section: 'Section 2D.01(b)',
                title: 'Multi-locality sales allocation',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance assigns sales and tax among principal offices, branches, warehouses, factories, project offices, plants, and plantations using location, delivery, and stated allocation percentages.',
                notes: 'The source duplicates item number 2 and contains textual defects; transaction situs, facility identity, production volume or project cost, multi-locality allocation, evidence, rounding, and remittance require reconciliation.',
                metadata: ['chapter' => 2, 'article' => 'D', 'known_ambiguities' => ['duplicate_source_item_2', 'transaction_situs_and_delivery', 'thirty_seventy_allocation', 'factory_plantation_split', 'production_volume_or_project_cost', 'allocation_rounding_and_remittance']],
            ),
            $this->provision(
                code: 'MRC-2D-01-PORT-ROUTE-SALES',
                section: 'Section 2D.01(c)-(d)',
                title: 'Port-of-loading and route-sales situs',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'Port location alone does not establish taxing authority absent a covered business facility, while route sales are assigned according to the relevant branch, sales office, or warehouse.',
                notes: 'Exporter and facility identity, route inventory origin, sale and delivery evidence, inter-LGU allocation, Article-reference defect, and remittance procedure require reconciliation.',
                metadata: ['chapter' => 2, 'article' => 'D', 'known_ambiguities' => ['source_article_reference_defect', 'exporter_facility_nexus', 'route_inventory_origin', 'sale_and_delivery_situs', 'inter_lgu_allocation']],
            ),
            $this->provision(
                code: 'MRC-3A-01-02-PERMIT-SCOPE-ENTERPRISE-SCALE',
                section: 'Section 3A.01-3A.02',
                title: "Mayor's Permit scope and enterprise-scale definitions",
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: "The ordinance requires a Mayor's Permit for covered businesses and adopts five enterprise scales using stated asset-limit and workforce descriptions.",
                notes: 'Asset-boundary overlap, the relationship between asset and workforce criteria, source wording defects, business/activity scope, and separate-establishment identity require municipal reconciliation.',
                metadata: ['chapter' => 3, 'article' => 'A', 'known_ambiguities' => ['covered_business_and_activity_scope', 'separate_establishment_identity', 'asset_boundary_overlap', 'asset_and_workforce_relationship', 'workforce_wording_defects']],
            ),
            $this->provision(
                code: 'MRC-3A-02-A-01-06-GENERAL-PERMIT-FEES',
                section: 'Section 3A.02(a), items 1-6',
                title: "Annual Mayor's Permit fees for general business categories",
                type: RevenueCodeProvisionType::TaxSchedule,
                excerpt: 'The ordinance states annual permit amounts for manufacturers, banks, financial institutions, contractors, service establishments, and wholesalers or retailers.',
                notes: 'Business-category and enterprise-scale mapping require accepted policy. The service-establishment table contains an unlabeled PHP 500.00 row and a large-scale row with no aligned amount; those defects are preserved and not normalized.',
                metadata: ['chapter' => 3, 'article' => 'A', 'schedule_clause_count' => 27, 'known_ambiguities' => ['business_category_mapping', 'enterprise_scale_eligibility', 'service_schedule_unlabeled_amount', 'service_schedule_missing_large_amount']],
            ),
            $this->provision(
                code: 'MRC-3A-02-A-07-13-SPECIAL-PERMIT-FEES',
                section: 'Section 3A.02(a), items 7-13',
                title: "Annual Mayor's Permit fees for regulated and special business categories",
                type: RevenueCodeProvisionType::TaxSchedule,
                excerpt: 'The ordinance states annual permit amounts for liquor and tobacco businesses, trans-loading, other businesses, fuel dealers, trucking or hauling, cooperatives, educational institutions, and unlisted businesses.',
                notes: 'Category, scale, nozzle, unit-count, and residual-business mapping require accepted policy. The gasoline-station table contains an unlabeled PHP 5,000.00 row and a large row with no aligned amount; DOE compliance is an external authority boundary.',
                metadata: ['chapter' => 3, 'article' => 'A', 'schedule_clause_count' => 29, 'known_ambiguities' => ['business_category_mapping', 'enterprise_scale_eligibility', 'gasoline_schedule_unlabeled_amount', 'gasoline_schedule_missing_large_amount', 'doe_compliance_authority', 'residual_business_scope']],
            ),
            $this->provision(
                code: 'MRC-3A-02-B-NEW-MICRO-PERMIT',
                section: 'Section 3A.02(b)',
                title: "New-business Mayor's Permit fee schedule",
                type: RevenueCodeProvisionType::FixedFee,
                excerpt: 'For new business, the ordinance states permit amounts from PHP 200.00 for micro-industry through PHP 2,000.00 for large-scale industries.',
                notes: 'Municipal enterprise-scale eligibility, business identity, separate-establishment scope, and operational schedule acceptance remain unresolved. The stable provision code is retained from the first extracted micro row.',
                metadata: ['chapter' => 3, 'article' => 'A', 'schedule_clause_count' => 5, 'known_ambiguities' => ['enterprise_scale_eligibility', 'new_business_identity', 'separate_establishment_identity']],
                feeRule: $newBusinessPermitFee,
            ),
            $this->provision(
                code: 'MRC-3A-03-PAYMENT-PRORATION',
                section: 'Section 3A.03',
                title: "Mayor's Permit payment, proration, and abandonment",
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance places payment with the Municipal Treasurer upon application, states a January renewal period, reckons newly started businesses from the beginning of a calendar quarter, and limits abandoned-business fees without refunding unexpired quarters.',
                notes: 'Payment-versus-issuance ordering, renewal due-date procedure, quarter reckoning, abandonment evidence, fee allocation, and the duplicated abandonment wording require operational reconciliation.',
                metadata: ['chapter' => 3, 'article' => 'A', 'known_ambiguities' => ['payment_and_issuance_order', 'renewal_due_date_procedure', 'calendar_quarter_reckoning', 'abandonment_evidence', 'no_refund_scope', 'duplicated_source_wording']],
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
            $this->provision(
                code: 'MRC-3B-01-DEFINITIONS',
                section: 'Section 3B.01',
                title: 'Cockpit, cockfighting, derby, personnel, and participant definitions',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance defines cockpit, cockfighting, local and international derby, bet taker or promoter, gaffer, referee, bettor, matchmaker, cashier, and medical aid.',
                notes: 'Several definitions contain source wording defects, role overlap, and terminology that require legal and operational reconciliation before classification or authorization behavior is implemented.',
                metadata: ['chapter' => 3, 'article' => 'B', 'known_ambiguities' => ['role_overlap', 'local_and_international_derby_eligibility', 'source_wording_defects', 'personnel_identity_and_credentials']],
            ),
            $this->provision(
                code: 'MRC-3B-02-PERMIT-FEES',
                section: 'Section 3B.02',
                title: 'Cockpit owner, operator, licensee, personnel, hackfight, and derby fees',
                type: RevenueCodeProvisionType::FixedFee,
                excerpt: 'The ordinance states owner/operator/licensee fees, nine cockpit-personnel permit amounts, a PHP 100.00 per-fight hackfight amount, and a Derby row with no printed amount.',
                notes: 'Fee scope, annual-versus-event treatment, role mapping, duplicate roles, hackfight event counting, and the blank Derby amount require municipal reconciliation. No listed amount is executable.',
                metadata: ['chapter' => 3, 'article' => 'B', 'schedule_clause_count' => 13, 'known_ambiguities' => ['annual_fee_scope', 'role_mapping', 'hackfight_counting', 'blank_derby_amount', 'personnel_permit_identity']],
            ),
            $this->provision(
                code: 'MRC-3B-03-04-FRANCHISE-LICENSING-REGISTRATION',
                section: 'Section 3B.03-3B.04',
                title: 'Cockpit franchise, licensing, documentary requirements, and registration',
                type: RevenueCodeProvisionType::EvidenceRequirement,
                excerpt: 'The ordinance requires a ten-year Sangguniang Bayan franchise, an authorizing ordinance, stated new-license and renewal evidence, municipal authority to operate, and registration with the Municipal Mayor.',
                notes: 'Franchise grant, license issuance, documentary sufficiency, responsible-office authority, renewal sequence, registration identity, and national-law references require accepted procedure and current legal validation.',
                metadata: ['chapter' => 3, 'article' => 'B', 'known_ambiguities' => ['franchise_and_license_authority', 'ten_year_term_effect', 'documentary_sufficiency', 'city_or_municipal_wording', 'registration_certificate_identity', 'national_law_currency']],
            ),
            $this->provision(
                code: 'MRC-3B-05-06-PAYMENT-APPLICABILITY',
                section: 'Section 3B.05-3B.06',
                title: 'Cockpit fee payment timing and national-law applicability',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance states payment points for filing, cockpit registration, and personnel permits and applies Presidential Decrees 449 and 1802 plus other pertinent laws.',
                notes: 'The source fee table names an annual cockpit permit fee while the payment section names a cockpit registration fee. Amount identity, January and birth-month renewal procedure, participation control, and current external-law applicability require reconciliation.',
                metadata: ['chapter' => 3, 'article' => 'B', 'known_ambiguities' => ['permit_versus_registration_fee_terminology', 'january_renewal_procedure', 'birth_month_renewal_procedure', 'participation_control', 'external_law_currency_and_priority']],
            ),
            $this->provision(
                code: 'MRC-3B-07-OPERATIONS',
                section: 'Section 3B.07',
                title: 'Cockpit ownership, siting, scheduling, special-purpose fights, and prohibited games',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance restricts ownership and operation, assigns cockpit-number and siting authority, limits ordinary and special-purpose cockfighting dates, and prohibits other gambling on the premises.',
                notes: 'Citizenship eligibility, zoning and proximity standards, obsolete transition timing, allowed dates, fair and fiesta limits, malformed special-purpose wording, resolution/Mayor authority, and enforcement require current legal and municipal policy.',
                metadata: ['chapter' => 3, 'article' => 'B', 'known_ambiguities' => ['citizenship_eligibility', 'cockpit_number_authority', 'zoning_and_proximity_standard', 'obsolete_transition_period', 'allowed_and_prohibited_dates', 'fair_and_fiesta_frequency', 'special_purpose_authority', 'malformed_source_wording', 'other_gambling_enforcement']],
            ),
            $this->provision(
                code: 'MRC-3B-08-PENALTIES',
                section: 'Section 3B.08',
                title: 'Cockpit violation penalties',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance states imprisonment and fine consequences for enumerated cockpit actors and a separate PHP 600.00 to PHP 2,000.00 range for other offenders.',
                notes: 'The source contains grammatical defects and references court discretion, subsidiary imprisonment, Chief of Police regulations, and Section 49. Penal enforcement is not application-calculation behavior and requires current legal authority.',
                metadata: ['chapter' => 3, 'article' => 'B', 'known_ambiguities' => ['penal_authority_and_currency', 'offender_classification', 'court_discretion', 'subsidiary_imprisonment', 'section_49_reference', 'source_wording_defects']],
            ),
            $this->provision(
                code: 'MRC-3C-01-SPECIAL-DERBY-FEES',
                section: 'Section 3C.01',
                title: 'Special cockfighting permit fees',
                type: RevenueCodeProvisionType::FixedFee,
                excerpt: 'The ordinance prints a PHP 2,000.00 per-day fee for National/Local Derby and a PHP 4,000.00 per-day fee for International Derby.',
                notes: 'Special-event classification, the undefined National Derby term, event-day counting, permit identity, and the direct conflict with the Section 3C.02 international-Derby exclusion require municipal reconciliation. Neither amount is executable.',
                metadata: ['chapter' => 3, 'article' => 'C', 'schedule_clause_count' => 2, 'known_ambiguities' => ['national_derby_definition', 'special_event_classification', 'event_day_counting', 'permit_identity', 'international_derby_exclusion_conflict']],
            ),
            $this->provision(
                code: 'MRC-3C-02-EXCLUSIONS',
                section: 'Section 3C.02',
                title: 'Special cockfighting fee exclusions',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance excludes regular cockfights held during Sundays, legal holidays, and local fiestas, and also states that international derbies are excluded from the Article C fees.',
                notes: 'The international-Derby exclusion directly contradicts the preceding PHP 4,000.00/day fee row. Regular-versus-special classification, calendar authority, and the scope of each exclusion require authoritative reconciliation.',
                metadata: ['chapter' => 3, 'article' => 'C', 'known_ambiguities' => ['regular_cockfight_definition', 'calendar_authority', 'local_fiesta_scope', 'international_derby_fee_conflict']],
            ),
            $this->provision(
                code: 'MRC-3C-03-PAYMENT-TIMING',
                section: 'Section 3C.03',
                title: 'Special cockfighting fee payment timing',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance states that the fees are payable to the City/Municipal Treasurer before special cockfights and derbies can lawfully be held.',
                notes: 'The City/Municipal wording, collector identity, payment and permit sequence, event authorization, receipt evidence, and treatment of cancelled or shortened events require operational reconciliation.',
                metadata: ['chapter' => 3, 'article' => 'C', 'known_ambiguities' => ['city_or_municipal_wording', 'collector_authority', 'payment_and_permit_sequence', 'event_authorization', 'cancelled_or_shortened_event_treatment']],
            ),
            $this->provision(
                code: 'MRC-3C-04-APPLICABILITY',
                section: 'Section 3C.04',
                title: 'Special cockfighting external-law applicability',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance applies Presidential Decrees 449 and 1802 and other pertinent laws to cockpit operation and cockfight holding.',
                notes: 'Current legal force, amendments, institutional succession, precedence, incorporated requirements, and enforcement authority require legal validation.',
                metadata: ['chapter' => 3, 'article' => 'C', 'known_ambiguities' => ['external_law_currency', 'institutional_succession', 'law_precedence', 'incorporated_requirements', 'enforcement_authority']],
            ),
            $this->provision(
                code: 'MRC-2E-01-BUSINESS-TAX-SCOPE',
                section: 'Section 2E.01',
                title: 'Payment scope for multiple establishments and businesses',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'Business taxes are assigned by establishment, taxpayer, and line of business, with combined or independent gross-receipts treatment depending on whether rates are the same.',
                notes: 'Establishment identity, related-business classification, rate equivalence, and gross-receipts allocation require accepted municipal policy and production-data mapping.',
                metadata: ['chapter' => 2, 'article' => 'E', 'known_ambiguities' => ['separate_establishment_identity', 'related_business_classification', 'combined_or_independent_tax_base']],
            ),
            $this->provision(
                code: 'MRC-2E-02-03-ACCRUAL-PAYMENT',
                section: 'Section 2E.02-2E.03',
                title: 'Business-tax accrual, payment dates, and extension authority',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'Business taxes accrue on January 1 and may be paid annually or quarterly on stated dates, subject to a bounded Sangguniang Bayan extension authority.',
                notes: 'Payment election, installment allocation, due-date behavior, extension approval, and effects on surcharge or penalty require operational reconciliation.',
                metadata: ['chapter' => 2, 'article' => 'E', 'known_ambiguities' => ['annual_or_quarterly_election', 'installment_allocation', 'extension_authority_and_evidence']],
            ),
            $this->provision(
                code: 'MRC-2E-04-D-E-DECLARATIONS-DEFICIENCY',
                section: 'Section 2E.04(d)-(e)',
                title: 'Sworn declarations, evidence, and deficiency tax',
                type: RevenueCodeProvisionType::EvidenceRequirement,
                excerpt: 'Taxpayers submit sworn capital and gross-receipts declarations plus certified income-tax-return evidence; the Treasurer may use best available evidence and collect stated deficiency, surcharge, and interest amounts.',
                notes: 'Form, documentary sufficiency, verification authority, discrepancy basis, interest arithmetic, trigger dates, rounding, and collection procedure require municipal reconciliation.',
                metadata: ['chapter' => 2, 'article' => 'E', 'known_ambiguities' => ['declaration_form_and_sufficiency', 'best_available_evidence_method', 'deficiency_tax_basis', 'interest_and_surcharge_arithmetic']],
            ),
            $this->provision(
                code: 'MRC-2E-04-A-C-PERMIT-RECEIPT-REQUIREMENTS',
                section: 'Section 2E.04(a)-(c)',
                title: 'Permit, official-receipt, and invoice requirements',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'Business operators must obtain stated clearances and permits, register for a permit plate, retain and display official receipts, and issue and preserve qualifying invoices or receipts.',
                notes: 'Document applicability, sufficiency, plate exemptions, receipt-numbering authority, inspection enforcement, and the relationship to national BIR records require operational reconciliation.',
                metadata: ['chapter' => 2, 'article' => 'E', 'known_ambiguities' => ['barangay_document_applicability', 'permit_plate_exemptions', 'official_receipt_numbering_authority', 'invoice_enforcement_and_bir_equivalence']],
            ),
            $this->provision(
                code: 'MRC-2E-04-F-LOST-RECEIPT-CERTIFICATION',
                section: 'Section 2E.04(f) [certification]',
                title: 'Certification of paid business tax when original receipt is unavailable',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The Municipal Treasurer may certify that business tax was paid when satisfactory proof shows the original receipt was lost, stolen, or destroyed, for a stated fee.',
                notes: 'Proof, request procedure, official receipt identity, certification format, numbering authority, and accepted operational fee require reconciliation.',
                metadata: ['chapter' => 2, 'article' => 'E', 'known_ambiguities' => ['duplicate_source_subsection_f', 'satisfactory_proof', 'certification_format_and_numbering', 'operational_fee_acceptance']],
            ),
            $this->provision(
                code: 'MRC-2E-04-G-LOCATION-TRANSFER',
                section: 'Section 2E.04(g)',
                title: 'Transfer of a taxed business to another municipal location',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'A business whose municipal business tax has been paid may transfer and continue at another location within the Municipality without additional tax for the paid period.',
                notes: 'Territorial validation, proof of tax payment, paid-period identity, permit amendment or replacement, clearances, inspection, and effective date require accepted municipal procedure.',
                metadata: ['chapter' => 2, 'article' => 'E', 'known_ambiguities' => ['location_transfer_procedure', 'paid_period_identity', 'permit_and_clearance_effect', 'effective_date']],
            ),
            $this->provision(
                code: 'MRC-2E-04-RETIREMENT',
                section: 'Section 2E.04(f) [retirement] and procedures',
                title: 'Business retirement, verification, final liability, and permit cancellation',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'Retirement requires timely sworn closure evidence, verification that operations truly ceased, settlement of final liabilities, and surrender and cancellation of the permit before official termination.',
                notes: 'The source duplicates subsection (f) and nests a second procedural sequence; closure evidence, inspection, tax recomputation, disapproval, cancellation authority, and legal effect require municipal reconciliation.',
                metadata: ['chapter' => 2, 'article' => 'E', 'known_ambiguities' => ['duplicate_source_subsection_f', 'nested_procedure_lettering', 'closure_effective_date', 'final_tax_recomputation', 'inspection_and_disapproval_authority', 'permit_cancellation_and_legal_effect']],
            ),
            $this->provision(
                code: 'MRC-2E-04-DEATH-TAX-MAPPING',
                section: 'Section 2E.04(d)-(e) [post-retirement provisions]',
                title: 'Continuation after death and tax-mapping evidence',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance addresses paid-term continuation by a person interested in a deceased licensee\'s estate and assigns tax-mapping sticker responsibility to the Municipal Treasurer.',
                notes: 'The source reuses subsection letters after the retirement procedures; estate authority, successor identity, remaining term, inspection criteria, and sticker evidentiary meaning require reconciliation.',
                metadata: ['chapter' => 2, 'article' => 'E', 'known_ambiguities' => ['reused_source_subsection_letters', 'estate_continuation_authority', 'remaining_paid_term', 'tax_mapping_sticker_meaning']],
            ),
            $this->provision(
                code: 'MRC-2F-01-PIL',
                section: 'Section 2F.01',
                title: 'Presumptive Income Level schedule and use',
                type: RevenueCodeProvisionType::PresumptiveIncomeSchedule,
                excerpt: 'A stratified schedule of minimum gross sales or receipts supports PIL validation and may establish taxable gross receipts where valid data is unavailable.',
                notes: 'The source contains duplicate item numbering and malformed amounts; classification mapping, evidence precedence, review authority, and taxpayer challenge procedure require municipal reconciliation.',
                metadata: ['chapter' => 2, 'article' => 'F', 'schedule_row_count' => 28, 'known_ambiguities' => ['duplicate_item_number_5', 'malformed_numeric_values', 'classification_mapping', 'validation_vs_substitution_authority']],
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

        $this->persistPolicyBoundaryClauses('MRC-2B-02-04-MOBILE-TRADERS', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-2B-02-MOBILE-TRADER-GROSS-RECEIPTS-RATE',
                type: RevenueCodeProvisionClauseType::RateBand,
                sourceText: 'There is hereby imposed an annual tax at the time of one percent (1%) on the gross receipts of Mobile Traders.',
                candidateInterpretation: 'Candidate annual rate: 100 basis points applies to accepted gross receipts of a qualifying mobile trader.',
                executionBlocker: 'Mobile-trader classification, taxable-receipt period and evidence, exact source wording, operational rate authority, and rounding require reconciliation.',
                metadata: ['source_expression' => 'one percent (1%)', 'candidate_basis' => 'gross_receipts', 'candidate_frequency' => 'annual'],
                rateBasisPoints: '100.0000',
            ),
            $this->policyBoundaryClause(
                sequence: 2,
                code: 'MRC-2B-03-MOBILE-TRADER-PAYMENT-TIMING',
                type: RevenueCodeProvisionClauseType::PaymentTiming,
                sourceText: 'The tax shall be paid upon the issuance of Mayor’s Permit to do business in the municipality.',
                candidateInterpretation: 'Candidate payment point: the mobile-trader tax is paid when the Mayor’s Permit is issued.',
                executionBlocker: 'Permit issuance authority, payment-before-or-after sequencing, renewal treatment, failed issuance, and receipt evidence remain unresolved.',
                metadata: ['candidate_payment_event' => 'mayors_permit_issuance'],
            ),
            $this->policyBoundaryClause(
                sequence: 3,
                code: 'MRC-2B-04-MOBILE-TRADER-PIL-ASSESSMENT',
                type: RevenueCodeProvisionClauseType::AuthorityBoundary,
                sourceText: 'The Municipal Treasurer shall determine the taxable gross receipts by applying the Presumptive Income Level Technique in this code, and thereafter assess and collect the tax due.',
                candidateInterpretation: 'Candidate authority: the Treasurer determines taxable mobile-trader receipts using an accepted PIL classification before assessment and collection.',
                executionBlocker: 'PIL classification mapping, observation period, evidence hierarchy, taxpayer notice and challenge, approval, calculation, and audit procedure require municipal acceptance.',
                metadata: ['decision_authority' => 'Municipal Treasurer', 'candidate_method' => 'presumptive_income_level'],
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-2B-05-06-PUBLIC-UTILITY-VEHICLES', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-2B-05-AIR-CONDITIONED-BUS',
                type: RevenueCodeProvisionClauseType::DependentRate,
                sourceText: 'Air Conditioned Buses — Php 5,722.50 per unit.',
                candidateInterpretation: 'Candidate annual amount: PHP 5,722.50 per qualifying air-conditioned bus.',
                executionBlocker: 'Operator and franchise eligibility, municipal booking-office or terminal nexus, vehicle classification, unit count, effective version, and proration require reconciliation.',
                metadata: ['vehicle_class' => 'air_conditioned_bus', 'candidate_unit' => 'vehicle'],
                amountCents: 572_250,
            ),
            $this->policyBoundaryClause(
                sequence: 2,
                code: 'MRC-2B-05-NON-AIR-CONDITIONED-BUS',
                type: RevenueCodeProvisionClauseType::DependentRate,
                sourceText: 'Buses without air conditioning — Php 4,578.00 per unit.',
                candidateInterpretation: 'Candidate annual amount: PHP 4,578.00 per qualifying bus without air conditioning.',
                executionBlocker: 'Operator and franchise eligibility, municipal booking-office or terminal nexus, vehicle classification, unit count, effective version, and proration require reconciliation.',
                metadata: ['vehicle_class' => 'non_air_conditioned_bus', 'candidate_unit' => 'vehicle'],
                amountCents: 457_800,
            ),
            $this->policyBoundaryClause(
                sequence: 3,
                code: 'MRC-2B-05-JEEPNEY-FIERA-TAMARAW-MULTICAB',
                type: RevenueCodeProvisionClauseType::DependentRate,
                sourceText: 'Jeepneys/Fieras/Tamaraws/multicabs — Php 572.25 per unit.',
                candidateInterpretation: 'Candidate annual amount: PHP 572.25 per qualifying vehicle in the stated combined class.',
                executionBlocker: 'Operator and franchise eligibility, municipal booking-office or terminal nexus, combined vehicle classification, unit count, effective version, and proration require reconciliation.',
                metadata: ['vehicle_classes' => ['jeepney', 'fiera', 'tamaraw', 'multicab'], 'candidate_unit' => 'vehicle'],
                amountCents: 57_225,
            ),
            $this->policyBoundaryClause(
                sequence: 4,
                code: 'MRC-2B-05-VAN',
                type: RevenueCodeProvisionClauseType::DependentRate,
                sourceText: 'Vans — Php 1,144.50 per unit.',
                candidateInterpretation: 'Candidate annual amount: PHP 1,144.50 per qualifying van.',
                executionBlocker: 'Operator and franchise eligibility, municipal booking-office or terminal nexus, vehicle classification, unit count, effective version, and proration require reconciliation.',
                metadata: ['vehicle_class' => 'van', 'candidate_unit' => 'vehicle'],
                amountCents: 114_450,
            ),
            $this->policyBoundaryClause(
                sequence: 5,
                code: 'MRC-2B-06-PUV-PAYMENT-TIMING',
                type: RevenueCodeProvisionClauseType::PaymentTiming,
                sourceText: 'The tax shall be paid within the first twenty (20) days of January of each year.',
                candidateInterpretation: 'Candidate annual due date: qualifying operators pay by January 20.',
                executionBlocker: 'Accrual, new entrants, late payment, unit additions or removals, proration, due-date extension, and collection procedure require reconciliation.',
                metadata: ['candidate_due_month' => 1, 'candidate_due_day' => 20],
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-2B-07-AMUSEMENT-OPERATORS', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-2B-07-CIRCUS-CARNIVAL-DAILY-RATE',
                type: RevenueCodeProvisionClauseType::DependentRate,
                sourceText: 'Circus, carnivals, or the like — P572.25 Rate PerDay.',
                candidateInterpretation: 'Candidate amount: PHP 572.25 for each accepted operating day of a qualifying circus, carnival, or similar activity.',
                executionBlocker: 'Fiesta or fair designation, operator and activity classification, operating-day count, similar-activity determination, collection point, and effective rate require reconciliation.',
                metadata: ['candidate_activity_class' => 'circus_carnival_or_similar', 'candidate_unit' => 'operating_day'],
                amountCents: 57_225,
            ),
            $this->policyBoundaryClause(
                sequence: 2,
                code: 'MRC-2B-07-AMUSEMENT-CONTRIVANCE-DAILY-RATE',
                type: RevenueCodeProvisionClauseType::DependentRate,
                sourceText: 'Merry-Go-Round, roller coaster, ferris wheel, swing, shooting gallery and other similar contrivances — P545.00 Rate PerDay.',
                candidateInterpretation: 'Candidate amount: PHP 545.00 for each accepted operating day of a qualifying amusement contrivance.',
                executionBlocker: 'Event, operator, device and similar-contrivance classification, combined-device treatment, operating-day count, collection point, and effective rate require reconciliation.',
                metadata: ['candidate_activity_classes' => ['merry_go_round', 'roller_coaster', 'ferris_wheel', 'swing', 'shooting_gallery', 'similar_contrivance'], 'candidate_unit' => 'operating_day'],
                amountCents: 54_500,
            ),
            $this->policyBoundaryClause(
                sequence: 3,
                code: 'MRC-2B-07-SPORT-CONTEST-EXHIBITION-DAILY-RATE',
                type: RevenueCodeProvisionClauseType::DependentRate,
                sourceText: 'Sports/Contest/Exhibitions — P545.00 Rate PerDay.',
                candidateInterpretation: 'Candidate amount: PHP 545.00 for each accepted operating day of a qualifying sport, contest, or exhibition.',
                executionBlocker: 'Event and operator eligibility, activity classification, operating-day count, multiple events, collection point, and effective rate require reconciliation.',
                metadata: ['candidate_activity_classes' => ['sport', 'contest', 'exhibition'], 'candidate_unit' => 'operating_day'],
                amountCents: 54_500,
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-2B-08-09-OTHER-BUSINESSES', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-2B-08-SANGGUNIAN-DESIGNATION',
                type: RevenueCodeProvisionClauseType::AuthorityBoundary,
                sourceText: 'On any business, not otherwise specified in the preceding paragraphs, which the Sanggunian concerned may deem proper to tax.',
                candidateInterpretation: 'Candidate scope: a residual business becomes taxable only through a traceable Sanggunian designation and only when it is not otherwise specified.',
                executionBlocker: 'No accepted designation catalog, ordinance or resolution reference, classification procedure, overlap check, effective date, or taxpayer notice currently exists.',
                metadata: ['decision_authority' => 'Sanggunian', 'candidate_scope' => 'otherwise_unspecified_business'],
            ),
            $this->policyBoundaryClause(
                sequence: 2,
                code: 'MRC-2B-08-NATIONAL-TAX-BUSINESS-CEILING',
                type: RevenueCodeProvisionClauseType::AmountCeiling,
                sourceText: 'Provided, That on any business subject to the excise, value-added or percentage tax under the National Internal Revenue Code, as amended, the rate of tax shall not exceed two percent (2%) of gross sales or receipts of the preceding calendar year.',
                candidateInterpretation: 'Candidate ceiling: an accepted rate for a qualifying nationally taxed business may not exceed 200 basis points of preceding-year gross sales or receipts.',
                executionBlocker: 'National-tax classification, accepted exact local rate, gross-sales-versus-receipts basis, period evidence, amendments to national law, rounding, and municipal authority require reconciliation.',
                metadata: ['candidate_national_tax_classes' => ['excise_tax', 'value_added_tax', 'percentage_tax'], 'candidate_basis_period' => 'preceding_calendar_year'],
                rateBasisPoints: '200.0000',
                isCeiling: true,
            ),
            $this->policyBoundaryClause(
                sequence: 3,
                code: 'MRC-2B-09-OTHER-BUSINESS-PAYMENT-TIMING',
                type: RevenueCodeProvisionClauseType::PaymentTiming,
                sourceText: 'The tax herein imposed shall be payable before engaging in such activity.',
                candidateInterpretation: 'Candidate payment point: a properly designated residual-business tax is paid before the taxed activity begins.',
                executionBlocker: 'The provision to which this timing applies, activity start evidence, designation authority, collection workflow, renewals, and late commencement require reconciliation.',
                metadata: ['candidate_payment_event' => 'before_activity_begins'],
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-2C-01-PETROLEUM-EXEMPTION', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-2C-01-PETROLEUM-LOCAL-TAX-EXEMPTION',
                type: RevenueCodeProvisionClauseType::Exemption,
                sourceText: 'Businesses engaged in the production, manufacture, refining, distribution or sale of oil, gasoline and other petroleum products shall not be subject to any local tax imposed under Article A and Article B.',
                candidateInterpretation: 'Candidate exemption: qualifying petroleum-product activities are outside the local taxes imposed under Chapter II Articles A and B.',
                executionBlocker: 'Petroleum-product and activity classification, mixed businesses, establishment allocation, documentary proof, superseding national law, and the boundary between local tax and regulatory charges require reconciliation.',
                metadata: ['covered_articles' => ['A', 'B'], 'candidate_activities' => ['production', 'manufacture', 'refining', 'distribution', 'sale'], 'candidate_products' => ['oil', 'gasoline', 'other_petroleum_products']],
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-2C-02-NEWLY-STARTED-BUSINESS', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-2C-02-INITIAL-LOCAL-BUSINESS-TAX-EXEMPTION',
                type: RevenueCodeProvisionClauseType::Exemption,
                sourceText: 'Newly started business entities shall not be subject to and/or liable to the payment of initial local business tax and shall only be subject to the payment of Business Permit and other regulatory fees and charges.',
                candidateInterpretation: 'Candidate initial treatment: a qualifying newly started business does not pay initial local business tax but remains subject to accepted permit and regulatory charges.',
                executionBlocker: 'New-business identity, initial-period boundary, successor or transferred business treatment, permit and regulatory charge catalog, eligibility evidence, and interaction with other provisions require municipal acceptance.',
                metadata: ['candidate_exemption' => 'initial_local_business_tax', 'candidate_remaining_charges' => ['business_permit', 'regulatory_fees_and_charges']],
            ),
            $this->policyBoundaryClause(
                sequence: 2,
                code: 'MRC-2C-02-SUCCEEDING-YEAR-GROSS-RECEIPTS-BASIS',
                type: RevenueCodeProvisionClauseType::InitialTaxBasis,
                sourceText: 'In the succeeding calendar year, regardless of when the business started to operate, the tax shall be based on the gross receipts for the preceding calendar year or any fraction thereof, as provided on the pertinent schedules in this Article.',
                candidateInterpretation: 'Candidate succeeding-year basis: use accepted gross receipts from the preceding calendar year or qualifying fraction and the pertinent reconciled schedule.',
                executionBlocker: 'Tax-year transition, fraction-of-year meaning, gross-receipts evidence, schedule selection, annualization or non-annualization, rounding, and closed or transferred business treatment require reconciliation.',
                metadata: ['candidate_basis' => 'preceding_calendar_year_gross_receipts_or_fraction', 'candidate_application_period' => 'succeeding_calendar_year'],
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-2D-01-SITUS-DEFINITIONS', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-2D-01-A-PRINCIPAL-OFFICE',
                type: RevenueCodeProvisionClauseType::SitusDefinition,
                sourceText: 'Principal Office -the head or main office of the businesses appearing in the pertinent documents submitted to the Securities and Exchange Commission, or the Department of Trade and Industry, or other appropriate agencies as the case may be. The city or municipality specifically mentioned in the articles of the incorporation or official registration papers as being the official address or said principal office shall be considered as the situs thereof. In case there is a transfer or relocation of the principal office to another city or municipality, it shall be the duty of the owner, operator or manager of the business to give due notice of such transfer or relocation to the local chief executives of the cities or municipalities concerned within fifteen (15) days after such transfer or relocation effected.',
                candidateInterpretation: 'Candidate principal-office situs: use the accepted official address in authoritative registration records and record relocation notice to affected LGUs within the stated 15-day period.',
                executionBlocker: 'Authoritative document hierarchy, conflicting addresses, effective relocation date, notice form and recipient, proof of delivery, late handling, and legal effect require reconciliation.',
                metadata: ['candidate_registration_authorities' => ['SEC', 'DTI', 'other_appropriate_agency'], 'source_notice_days' => 15],
            ),
            $this->policyBoundaryClause(
                sequence: 2,
                code: 'MRC-2D-01-A-BRANCH-SALES-OFFICE',
                type: RevenueCodeProvisionClauseType::SitusDefinition,
                sourceText: 'Branch or Sales Office- a fixed place in a locality which conducts operations of the businesses as an extension of the principal office. However, offices used only as display areas of the products where no stocks or items are stored for sale, although orders for the products may be received thereat, are not branch or sales offices as herein contemplated. A warehouse which accepts orders and/or issue sales invoices independent of a branch with sales office shall be considered as a sales office.',
                candidateInterpretation: 'Candidate classification: a fixed operating extension is a branch or sales office; display-only locations are excluded, while an order-taking or independently invoicing warehouse is included.',
                executionBlocker: 'Fixed-place, operational, stock, order, invoicing, independent-operation, mixed-use, inspection, and evidence criteria require municipal acceptance.',
                metadata: ['candidate_inclusions' => ['fixed_operating_extension', 'order_taking_or_independently_invoicing_warehouse'], 'candidate_exclusion' => 'display_only_without_stock_for_sale'],
            ),
            $this->policyBoundaryClause(
                sequence: 3,
                code: 'MRC-2D-01-A-WAREHOUSE',
                type: RevenueCodeProvisionClauseType::SitusDefinition,
                sourceText: 'Warehouse- a building utilized for the storage of products for sale and from which goods or merchandise are withdrawn for delivery to customers or dealers, or by persons acting on behalf of the business. A warehouse that does not accept orders and /or issue sales invoices as aforementioned shall not be considered a branch or sales office.',
                candidateInterpretation: 'Candidate warehouse classification separates storage and delivery activity from order-taking or invoicing facts that may make the facility a sales office.',
                executionBlocker: 'Inventory ownership, withdrawal and delivery records, order and invoice behavior, mixed use, third-party operation, inspection, and evidence criteria require reconciliation.',
                metadata: ['candidate_primary_use' => 'storage_for_sale_and_delivery', 'branch_sales_office_indicator' => 'accepts_orders_or_issues_sales_invoices'],
            ),
            $this->policyBoundaryClause(
                sequence: 4,
                code: 'MRC-2D-01-A-PLANTATION',
                type: RevenueCodeProvisionClauseType::SitusDefinition,
                sourceText: 'Plantation- a tract of agricultural land planted to trees or seedlings whether fruit bearing or not, uniformly spaced or seeded by broadcast methods or normally arranged to allow highest production. For purpose for this Article, inland fishing ground shall be considered as plantation.',
                candidateInterpretation: 'Candidate situs classification includes qualifying planted agricultural land and treats an inland fishing ground as a plantation for Article D.',
                executionBlocker: 'Land and activity evidence, production use, boundaries, multi-parcel treatment, inland-fishing classification, inspection, and effective-period criteria require reconciliation.',
                metadata: ['candidate_inclusion' => 'inland_fishing_ground'],
            ),
            $this->policyBoundaryClause(
                sequence: 5,
                code: 'MRC-2D-01-A-EXPERIMENTAL-FARM',
                type: RevenueCodeProvisionClauseType::SitusDefinition,
                sourceText: 'Experimental Farms- agricultural lands utilized by a business or corporation to conduct studies, tests, researches or experiments involving agricultural, agri-business, marine or aquatic livestock, poultry, dairy and other similar products for the purpose of improving the quality and quantity of goods and products. However, on-site sales of commercial quantity made in experimental farms shall be similarly imposed the corresponding tax under paragraph (b), Section 8 of this Ordinance.',
                candidateInterpretation: 'Candidate classification distinguishes experimental use from commercial-quantity on-site sales that may create tax liability under the referenced provision.',
                executionBlocker: 'Research-purpose evidence, commercial-quantity threshold, on-site sale records, referenced Section 8 identity, tax mapping, mixed use, inspection, and authority require reconciliation.',
                metadata: ['source_cross_reference' => 'paragraph (b), Section 8', 'known_cross_reference_question' => true],
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-2D-01-SALES-ALLOCATION', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-2D-01-B-LOCAL-BRANCH-SALES',
                type: RevenueCodeProvisionClauseType::SalesAllocation,
                sourceText: 'All sales made in a locality where there is branch or sales office or warehouse shall be recorded in said branch or sales office or warehouse and the tax shall be payable to the city or municipality where the same is located.',
                candidateInterpretation: 'Candidate allocation: sales made where a qualifying branch, sales office, or warehouse exists are recorded there and taxed by that locality.',
                executionBlocker: 'Sale location, qualifying facility, recording system, transaction attribution, evidence, inter-LGU reconciliation, and remittance procedure require acceptance.',
                metadata: ['candidate_allocation_basis' => 'sale_locality_with_qualifying_facility'],
            ),
            $this->policyBoundaryClause(
                sequence: 2,
                code: 'MRC-2D-01-B-IPIL-SALE-WITHOUT-LOCAL-BRANCH',
                type: RevenueCodeProvisionClauseType::SalesAllocation,
                sourceText: 'If the business concerned has no branch office or sales outlet in the municipality of Ipil, the sale or transaction may be recorded in the place where the principal office of the said business is located. The taxes, however, shall accrue and be paid to municipality of Ipil where the sale or transaction was made or consummated, associated with the delivery of the articles, commodities or things which are the subject matter of the contract of sale.',
                candidateInterpretation: 'Candidate allocation: an Ipil-consummated sale associated with delivery in Ipil accrues to Ipil even when recorded at a principal office outside Ipil.',
                executionBlocker: 'Sale consummation, delivery nexus, contract evidence, no-local-facility fact, transaction recording, inter-LGU reconciliation, and remittance require accepted procedure.',
                metadata: ['candidate_taxing_locality' => 'Municipality of Ipil', 'source_item' => '2 [first occurrence]'],
            ),
            $this->policyBoundaryClause(
                sequence: 3,
                code: 'MRC-2D-01-B-THIRTY-SEVENTY-ALLOCATION',
                type: RevenueCodeProvisionClauseType::SalesAllocation,
                sourceText: 'In cases where there is a factory, project office, plant or plantation in pursuit of business, thirty percent (30%) if all sales recorded in the principal office shall be taxable by the city or municipality where the principal office is located and seventy percent (70%) of all sales recorded in the principal office shall be taxable by the city or municipality where the factory, project office, plant or plantation is located.',
                candidateInterpretation: 'Candidate allocation: 30 percent of qualifying principal-office sales remains with the principal-office locality and 70 percent is assigned to the qualifying operating-facility locality.',
                executionBlocker: 'The source wording “if all sales,” facility and pursuit-of-business eligibility, sales population, period, multi-facility allocation, evidence, rounding, and remittance require reconciliation.',
                metadata: ['source_item' => '2 [second occurrence]', 'candidate_principal_office_percent' => '30.00', 'candidate_operating_facility_percent' => '70.00', 'candidate_facility_types' => ['factory', 'project_office', 'plant', 'plantation']],
            ),
            $this->policyBoundaryClause(
                sequence: 4,
                code: 'MRC-2D-01-B-EXPERIMENTAL-FARM-EXCLUSION',
                type: RevenueCodeProvisionClauseType::Exemption,
                sourceText: 'The sales allocation in (a) and (b) above shall not apply to experimental farms. LGU’s where only experimental farms are located shall not be entitled to the sales allocation herein provided for.',
                candidateInterpretation: 'Candidate exclusion: an LGU containing only an experimental farm does not receive the stated sales allocation.',
                executionBlocker: 'Experimental-farm classification, “only” condition, mixed facilities, commercial on-site sales, allocation period, evidence, and authority require reconciliation.',
                metadata: ['candidate_excluded_facility' => 'experimental_farm'],
            ),
            $this->policyBoundaryClause(
                sequence: 5,
                code: 'MRC-2D-01-B-FACTORY-PLANTATION-SPLIT',
                type: RevenueCodeProvisionClauseType::SalesAllocation,
                sourceText: 'In case of a plantation located in a locality other than that where the factory is located, said seventy percent (70%) sales allocation shall be divided as follows. Sixty percent (60) to the city or municipality where the factory is located; and Forty percent (40) to the city or municipality where the plantation is located.',
                candidateInterpretation: 'Candidate sub-allocation: divide the 70-percent operating-facility share 60/40 between factory and plantation localities when they differ.',
                executionBlocker: 'Whether 60 and 40 are percentages of the 70-percent share, qualifying facility identity, source omission of percent signs, sales population, evidence, rounding, and remittance require reconciliation.',
                metadata: ['candidate_parent_share_percent' => '70.00', 'candidate_factory_share_of_parent_percent' => '60.00', 'candidate_plantation_share_of_parent_percent' => '40.00'],
            ),
            $this->policyBoundaryClause(
                sequence: 6,
                code: 'MRC-2D-01-B-MULTI-FACILITY-PRORATION',
                type: RevenueCodeProvisionClauseType::SalesAllocation,
                sourceText: 'In cases where there are two (2) or more factories, project offices, plants or plantations located in different localities, the seventy percent (70%) sales allocation shall be pro-rated among the localities where such factories, project offices, plants and plantations are located in proportion to their respective volumes of production during the period for which the tax is due.',
                candidateInterpretation: 'Candidate allocation: prorate the 70-percent operating-facility share among qualifying localities by accepted production volume for the tax period.',
                executionBlocker: 'Facility identity, production-volume definition and evidence, tax period, zero or missing production, additions and closures, precision, remainder allocation, approval, and remittance require reconciliation.',
                metadata: ['candidate_parent_share_percent' => '70.00', 'candidate_proration_basis' => 'production_volume'],
            ),
            $this->policyBoundaryClause(
                sequence: 7,
                code: 'MRC-2D-01-B-PROJECT-OFFICE-PRODUCTION-BASIS',
                type: RevenueCodeProvisionClauseType::TaxBase,
                sourceText: 'In the case of project offices of services and other independent contractors, the term production shall refer to the costs of projects actually undertaken during the tax period.',
                candidateInterpretation: 'Candidate proration basis: project costs actually undertaken during the tax period stand in for production volume for qualifying service project offices and independent contractors.',
                executionBlocker: 'Contractor and project-office eligibility, qualifying cost catalog, cost recognition period, shared costs, evidence, audit, and allocation precision require reconciliation.',
                metadata: ['candidate_production_proxy' => 'costs_of_projects_actually_undertaken'],
            ),
            $this->policyBoundaryClause(
                sequence: 8,
                code: 'MRC-2D-01-B-ALLOCATION-IRRESPECTIVE-OF-LOCAL-SALES',
                type: RevenueCodeProvisionClauseType::SalesAllocation,
                sourceText: 'The foregoing sales allocation under paragraph (3) hereof shall be applied irrespective of whether or not sales are made in the locality where the factory, project office, plant or plantation is located. In case of sales made by the factory, project office, plant or plantation, the sale shall be covered by paragraph (1) or (2) above.',
                candidateInterpretation: 'Candidate boundary: operating-facility allocation does not depend on local sales, while direct facility sales return to the applicable transaction-location rule.',
                executionBlocker: 'The source paragraph cross-references, direct-sale classification, double-count prevention, facility and transaction evidence, allocation ordering, and remittance require reconciliation.',
                metadata: ['source_cross_references' => ['paragraph (3)', 'paragraph (1)', 'paragraph (2)']],
            ),
            $this->policyBoundaryClause(
                sequence: 9,
                code: 'MRC-2D-01-B-CONTRACTOR-FACILITY-ATTRIBUTION',
                type: RevenueCodeProvisionClauseType::SalesAllocation,
                sourceText: 'In the case of manufacturers or producers which engage the services of an independent contractor to produce or manufacture some of their products, the rules on situs of taxation provided in this article as clarified in the paragraphs above shall apply except that the factory or plant and warehouse of the contractor utilized for the production or storage of the manufacturer’s products shall be considered as the factory or plant and warehouse of the manufacturer.',
                candidateInterpretation: 'Candidate attribution: qualifying contractor facilities used for a manufacturer’s products are treated as that manufacturer’s facilities for situs allocation.',
                executionBlocker: 'Manufacturer, contractor, product, facility-use and storage evidence, shared facilities, attribution period, double counting, contractual authority, and allocation procedure require reconciliation.',
                metadata: ['candidate_attributed_facilities' => ['factory', 'plant', 'warehouse']],
            ),
            $this->policyBoundaryClause(
                sequence: 10,
                code: 'MRC-2D-01-B-IPIL-FACTORY-SALES-RECORDING',
                type: RevenueCodeProvisionClauseType::SalesAllocation,
                sourceText: 'All sales made by the factory, project office, plant or plantation located in this municipality shall be recorded in the branch or sales office which is similarly located herein, and shall be taxable by this municipality. In case there is no branch or sales office or warehouse in this municipality, but the principal office is located therein, the sales made in the dais factory shall be taxable by this municipality along with the sales made in the principal office.',
                candidateInterpretation: 'Candidate Ipil allocation: sales by an Ipil operating facility are recorded at the local branch or sales office and taxed by Ipil; a stated no-local-branch case also assigns qualifying sales to Ipil when the principal office is local.',
                executionBlocker: 'The source phrase “dais factory,” facility and sale identity, recording hierarchy, no-local-facility condition, principal-office evidence, double counting, and remittance require reconciliation.',
                metadata: ['candidate_taxing_locality' => 'Municipality of Ipil', 'source_wording_question' => 'dais_factory'],
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-2D-01-PORT-ROUTE-SALES', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-2D-01-C-PORT-OF-LOADING',
                type: RevenueCodeProvisionClauseType::SalesAllocation,
                sourceText: 'Port of Loading- the city or municipality where the port of loading is located shall not levy and collect the tax imposable under Article 1, Chapter II of this ordinance unless the exporter maintain in said city or municipality its principal office, a branch, sales office, warehouse, factory, plant or plantation in which case the foregoing rule on the mater shall apply accordingly.',
                candidateInterpretation: 'Candidate boundary: port location alone does not create taxing authority; a qualifying exporter facility in the port locality invokes the applicable situs rules.',
                executionBlocker: 'The source reference to Article 1, exporter and facility identity, port and transaction evidence, applicable allocation rule, inter-LGU reconciliation, and remittance require acceptance.',
                metadata: ['source_cross_reference' => 'Article 1, Chapter II', 'known_cross_reference_question' => true, 'candidate_facility_types' => ['principal_office', 'branch', 'sales_office', 'warehouse', 'factory', 'plant', 'plantation']],
            ),
            $this->policyBoundaryClause(
                sequence: 2,
                code: 'MRC-2D-01-D-ROUTE-SALES-RECORDING',
                type: RevenueCodeProvisionClauseType::SalesAllocation,
                sourceText: 'Route Sales- sales made by route trucks, vans or vehicles in this municipality which a manufacturer, producer, wholesaler, maintains a branch or sales office or warehouse shall be recorded in the branch or sales office or warehouse and shall be taxed herein.',
                candidateInterpretation: 'Candidate route-sales allocation: qualifying sales made in Ipil by route vehicles are recorded at and taxed through the operator’s qualifying local facility.',
                executionBlocker: 'The source grammar, operator and local-facility identity, route inventory, sale and delivery location, vehicle and transaction evidence, returns, and remittance require reconciliation.',
                metadata: ['candidate_vehicle_types' => ['route_truck', 'van', 'vehicle'], 'candidate_taxing_locality' => 'Municipality of Ipil'],
            ),
            $this->policyBoundaryClause(
                sequence: 3,
                code: 'MRC-2D-01-D-OUTBOUND-ROUTE-WITHDRAWALS',
                type: RevenueCodeProvisionClauseType::SalesAllocation,
                sourceText: 'This municipality shall tax the sales of the products withdrawn by route trucks from the branch, sales office or warehouse located herein but sold in another locality.',
                candidateInterpretation: 'Candidate outbound-route allocation: Ipil taxes qualifying products withdrawn from an Ipil facility by route truck even when sold in another locality.',
                executionBlocker: 'Inventory withdrawal, source facility, product and route identity, sale and delivery evidence, other-LGU claims, returns, allocation authority, and remittance require reconciliation.',
                metadata: ['candidate_allocation_basis' => 'withdrawal_from_ipil_facility', 'candidate_taxing_locality' => 'Municipality of Ipil'],
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-2E-01-BUSINESS-TAX-SCOPE', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-2E-01-SEPARATE-ESTABLISHMENT',
                type: RevenueCodeProvisionClauseType::SeparateEstablishment,
                sourceText: 'The taxes imposed under Section 2A.02 and 2B.01, Chapter II of this Ordinance shall be payable for every separate or distinct establishment or place where the business subject to the tax is conducted and one line of business does not become exempt by being conducted with some other businesses for which such tax has been paid. The tax on a business must be paid by the person conducting the same.',
                candidateInterpretation: 'Candidate scope: tax liability is determined per distinct establishment and line of business and belongs to the person conducting the business.',
                executionBlocker: 'Distinct-establishment identity, shared premises, taxpayer identity, and multi-line allocation require accepted municipal rules and production-data mapping.',
                metadata: ['applies_to_sections' => ['2A.02', '2B.01'], 'candidate_scope' => 'distinct_establishment_and_line_of_business'],
            ),
            $this->policyBoundaryClause(
                sequence: 2,
                code: 'MRC-2E-01-SEPARATE-PERMITS',
                type: RevenueCodeProvisionClauseType::PermitRequirement,
                sourceText: 'The conduct or operation of two or more related businesses provided for under Section 2A.02, Chapter II of this Ordinance any one person, natural or juridical, shall require the issuance of a separate permit or license to each business.',
                candidateInterpretation: 'Candidate permit boundary: each related business operated by one natural or juridical person requires a separate permit or license.',
                executionBlocker: 'The meaning of related business, business-versus-line identity, and legacy permit grouping require municipal acceptance before permit creation can enforce this rule.',
                metadata: ['candidate_permit_scope' => 'each_related_business', 'actor_types' => ['natural_person', 'juridical_person']],
            ),
            $this->policyBoundaryClause(
                sequence: 3,
                code: 'MRC-2E-01-SAME-RATE-COMBINED-BASE',
                type: RevenueCodeProvisionClauseType::CombinedTaxBase,
                sourceText: 'In case where a person conducts or operates two (2) or more of the businesses mentioned in Section 2A.02, Chapter II of this Ordinance which are subject to the same rate of imposition, the tax shall be computed on the combined total gross sales or receipts of the said two (2) or more related businesses.',
                candidateInterpretation: 'Candidate basis: combine gross sales or receipts of related businesses when their accepted tax rate is the same.',
                executionBlocker: 'Related-business identity, rate equivalence, period alignment, and gross-receipts allocation are not operationally reconciled.',
                metadata: ['combination_condition' => 'same_rate', 'candidate_basis' => 'combined_gross_sales_or_receipts'],
            ),
            $this->policyBoundaryClause(
                sequence: 4,
                code: 'MRC-2E-01-DIFFERENT-RATE-INDEPENDENT-BASE',
                type: RevenueCodeProvisionClauseType::TaxBase,
                sourceText: 'In cases where a person conducts or operates two (2) or more businesses mentioned in Section 2A.02, Chapter II of this Ordinance which are subject to different rates of imposition, the taxable gross sales or receipts of each business shall be reported independently and tax thereon shall be computed on the basis of the pertinent schedule.',
                candidateInterpretation: 'Candidate basis: report and assess each business independently when accepted rates differ.',
                executionBlocker: 'Business-line allocation, rate determination, shared receipts, and pertinent-schedule selection require accepted municipal policy.',
                metadata: ['separation_condition' => 'different_rates', 'candidate_basis' => 'independent_gross_sales_or_receipts'],
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-2E-02-03-ACCRUAL-PAYMENT', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-2E-02-JANUARY-ACCRUAL',
                type: RevenueCodeProvisionClauseType::PaymentTiming,
                sourceText: 'Unless specifically provided in this Article, the taxes imposed herein shall accrue on the first day of January of each year.',
                candidateInterpretation: 'Candidate accrual date: January 1 of each tax year unless a specific provision controls.',
                executionBlocker: 'Exceptions, timezone, newly started businesses, closure, and the legal consequence of accrual require operational reconciliation.',
                metadata: ['candidate_accrual_month' => 1, 'candidate_accrual_day' => 1],
            ),
            $this->policyBoundaryClause(
                sequence: 2,
                code: 'MRC-2E-03-ANNUAL-PAYMENT',
                type: RevenueCodeProvisionClauseType::PaymentTiming,
                sourceText: 'The tax shall be paid once within the first twenty (20) days of January.',
                candidateInterpretation: 'Candidate annual due date: January 20.',
                executionBlocker: 'Payment election, non-business days, newly started businesses, extension records, and delinquency trigger semantics require municipal policy.',
                metadata: ['candidate_due_month' => 1, 'candidate_due_day' => 20, 'frequency' => 'annual'],
            ),
            $this->policyBoundaryClause(
                sequence: 3,
                code: 'MRC-2E-03-QUARTERLY-INSTALLMENTS',
                type: RevenueCodeProvisionClauseType::InstallmentSchedule,
                sourceText: 'The tax shall be paid once within the first twenty (20) days of January or in quarterly installments within the first twenty (20) days of January, April, July and October of each year.',
                candidateInterpretation: 'Candidate quarterly due dates: January 20, April 20, July 20, and October 20.',
                executionBlocker: 'Election timing, installment allocation, rounding remainders, partial payments, and delinquency per installment require accepted policy.',
                metadata: ['frequency' => 'quarterly', 'candidate_due_dates' => ['01-20', '04-20', '07-20', '10-20']],
            ),
            $this->policyBoundaryClause(
                sequence: 4,
                code: 'MRC-2E-03-EXTENSION-AUTHORITY',
                type: RevenueCodeProvisionClauseType::AuthorityBoundary,
                sourceText: 'The Sangguniang Bayan may, for a justifiable reason or cause, extend the time for payment of such taxes without surcharges or penalties, but only for the period not exceeding six (6) months.',
                candidateInterpretation: 'Candidate authority boundary: the Sangguniang Bayan may approve a justified extension of no more than six months without surcharge or penalty.',
                executionBlocker: 'Approval evidence, resolution identity, covered taxpayers, extension start and end dates, and interaction with installment deadlines require municipal authority.',
                metadata: ['decision_authority' => 'Sangguniang Bayan', 'maximum_extension_months' => 6, 'waives_during_extension' => ['surcharge', 'penalty']],
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-2E-04-D-E-DECLARATIONS-DEFICIENCY', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-2E-04-D-CAPITAL-STATEMENT',
                type: RevenueCodeProvisionClauseType::DocumentaryRequirement,
                sourceText: 'Operators of business subject to the taxes on business shall submit a sworn statement of the capital investment before the start of their business operations and upon application for a Mayor’s permit to operate the business.',
                candidateInterpretation: 'Candidate evidence: a sworn capital-investment statement accompanies pre-operation and Mayor’s Permit application activity.',
                executionBlocker: 'Form, oath authority, documentary sufficiency, amendment handling, and applicability by business type require municipal acceptance.',
                metadata: ['declared_measure' => 'capital_investment', 'candidate_submission_points' => ['before_operations', 'mayors_permit_application']],
            ),
            $this->policyBoundaryClause(
                sequence: 2,
                code: 'MRC-2E-04-D-GROSS-RECEIPTS-STATEMENT',
                type: RevenueCodeProvisionClauseType::DocumentaryRequirement,
                sourceText: 'Upon payment of the tax levied in this Chapter, any person engaged in business subject to the business tax paid based on gross sales and/or receipts shall submit a sworn statement of his gross sales/receipts for the preceding calendar year or quarter in such manner and form as may be prescribed by the Municipal Treasurer.',
                candidateInterpretation: 'Candidate evidence: gross-sales-based taxpayers submit a sworn statement for the preceding year or quarter in the Treasurer-prescribed form.',
                executionBlocker: 'Annual-versus-quarterly applicability, prescribed form, amendment handling, reviewer authority, and documentary sufficiency remain unresolved.',
                metadata: ['declared_measure' => 'gross_sales_or_receipts', 'candidate_periods' => ['preceding_calendar_year', 'preceding_quarter'], 'form_authority' => 'Municipal Treasurer'],
            ),
            $this->policyBoundaryClause(
                sequence: 3,
                code: 'MRC-2E-04-D-BEST-AVAILABLE-EVIDENCE',
                type: RevenueCodeProvisionClauseType::BestAvailableEvidence,
                sourceText: 'Should the taxpayer fail to submit a sworn statement of gross sales or receipts, due among others to his failure to have a book of accounts, records or subsidiaries for his business, the Municipal Treasurer or his authorized representatives may verify or assess the gross sales or receipts of the taxpayer under the best available evidence upon which the tax may be based.',
                candidateInterpretation: 'Candidate authority boundary: the Treasurer or authorized representative may establish gross receipts from best available evidence when the sworn statement or supporting records are unavailable.',
                executionBlocker: 'Evidence hierarchy, estimation method, authorization, notice, taxpayer challenge, approval, and audit requirements are not defined operationally.',
                metadata: ['decision_authority' => ['Municipal Treasurer', 'authorized_representative'], 'trigger' => 'sworn_statement_or_records_unavailable'],
            ),
            $this->policyBoundaryClause(
                sequence: 4,
                code: 'MRC-2E-04-E-ITR-DEADLINE',
                type: RevenueCodeProvisionClauseType::DocumentaryRequirement,
                sourceText: 'All persons who are granted a permit to conduct an activity or business and who are liable to pay the business tax provided in this Code shall submit a certified photocopy of their income tax returns (ITR) on or before April 30 of each year to the Municipal Treasurer.',
                candidateInterpretation: 'Candidate evidence deadline: a certified ITR copy is due to the Municipal Treasurer by April 30 for permitted business-tax payers.',
                executionBlocker: 'Certification standard, covered return period, extensions, non-calendar fiscal years, exemptions, and documentary-sufficiency review require municipal policy.',
                metadata: ['candidate_due_month' => 4, 'candidate_due_day' => 30, 'recipient' => 'Municipal Treasurer'],
            ),
            $this->policyBoundaryClause(
                sequence: 5,
                code: 'MRC-2E-04-E-DEFICIENCY-BY-MAY-20',
                type: RevenueCodeProvisionClauseType::DeficiencyTax,
                sourceText: 'The deficiency in the business tax arising out of the difference in gross receipts or sales declared in the application for Mayor’s Permit/Declaration of gross sales or receipts and the gross receipts or sales declared in the ITR shall be payable on or before May 20 of the same year with interest at the rate of ten percent (10%) corresponding to the two percent (2%) per month from January to May.',
                candidateInterpretation: 'Candidate deficiency basis: compare permit/declaration gross receipts with ITR gross receipts; payment by May 20 carries the stated 10 percent interest described as 2 percent per month from January to May.',
                executionBlocker: 'The taxable difference, tax recomputation basis, interest base, inclusive month counting, discrepancy review, notice, rounding, and collection authority require reconciliation.',
                metadata: ['candidate_due_month' => 5, 'candidate_due_day' => 20, 'stated_interest_percent' => '10.00', 'stated_monthly_interest_percent' => '2.00', 'stated_interest_period' => 'January_to_May'],
            ),
            $this->policyBoundaryClause(
                sequence: 6,
                code: 'MRC-2E-04-E-AFTER-MAY-20-SURCHARGE-INTEREST',
                type: RevenueCodeProvisionClauseType::SurchargeInterest,
                sourceText: 'Payments of the deficiency tax made after May 20 shall be subject to the twenty-five percent (25%) surcharge and two Percent (2%) interest for every month counted from January up to the month payment is made.',
                candidateInterpretation: 'Candidate late-deficiency treatment: add a 25 percent surcharge and 2 percent interest for each month from January through the payment month.',
                executionBlocker: 'Surcharge and interest bases, inclusive counting, partial months, compounding, caps, rounding, extensions, payment allocation, and notice require accepted municipal policy.',
                metadata: ['trigger' => 'payment_after_May_20', 'stated_surcharge_percent' => '25.00', 'stated_monthly_interest_percent' => '2.00', 'stated_interest_start_month' => 1],
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-2E-04-A-C-PERMIT-RECEIPT-REQUIREMENTS', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-2E-04-A-BARANGAY-DOCUMENTS',
                type: RevenueCodeProvisionClauseType::DocumentaryRequirement,
                sourceText: 'New applicant to operate any kind of businesses shall secure Barangay Residence Certification and Barangay Business Clearance in his/her Barangay where he or she is residing or business is located.',
                candidateInterpretation: 'Candidate intake evidence: a new applicant secures the stated barangay residence certification and business clearance from the relevant residence or business barangay.',
                executionBlocker: 'Applicant type, residence-versus-business jurisdiction, document format, validity period, electronic verification, and sufficiency review require municipal acceptance.',
                metadata: ['application_type' => 'new', 'candidate_documents' => ['barangay_residence_certification', 'barangay_business_clearance']],
            ),
            $this->policyBoundaryClause(
                sequence: 2,
                code: 'MRC-2E-04-A-MAYORS-PERMIT-TAX',
                type: RevenueCodeProvisionClauseType::PermitRequirement,
                sourceText: 'Any person who shall establish, operate or conduct any business, trade or activity mentioned in this Chapter in this municipality shall first obtain a Mayor’s Permit and pay the fee therefore, and the business tax imposed under the pertinent Article.',
                candidateInterpretation: 'Candidate prerequisite: obtain the Mayor’s Permit and settle the applicable permit fee and business tax before operating a covered activity.',
                executionBlocker: 'Issuance authority, payment completion semantics, applicable fee and tax selection, exemptions, and legal-effective date remain unresolved.',
                metadata: ['candidate_prerequisites' => ['mayors_permit', 'permit_fee', 'business_tax']],
            ),
            $this->policyBoundaryClause(
                sequence: 3,
                code: 'MRC-2E-04-A-REGISTRATION-PLATE',
                type: RevenueCodeProvisionClauseType::PermitRequirement,
                sourceText: 'All business shall register to the Licensing Office and shall secure Business Registration Permit Plate with a corresponding fee to cover the cost of the business plate except for the tudlos-tudlos vendors and peddlers selling goods door to door.',
                candidateInterpretation: 'Candidate registration evidence: covered businesses register with Licensing and obtain a permit plate, while the two stated vendor categories are excluded.',
                executionBlocker: 'Licensing registration identity, plate issuance and replacement, accepted amount, vendor classification, exemption approval, and enforcement require municipal procedure.',
                metadata: ['candidate_exemptions' => ['tudlos_tudlos_vendors', 'door_to_door_peddlers']],
            ),
            $this->policyBoundaryClause(
                sequence: 4,
                code: 'MRC-2E-04-B-OFFICIAL-RECEIPT-ISSUANCE',
                type: RevenueCodeProvisionClauseType::ReceiptRequirement,
                sourceText: 'The Municipal Treasurer shall issue an official receipt upon payment of the business tax. Issuance of the said official receipt shall not relieve the taxpayer of any requirement imposed by the different departments of this municipality.',
                candidateInterpretation: 'Candidate Treasury evidence: business-tax payment results in an official receipt but does not establish satisfaction of other departmental requirements.',
                executionBlocker: 'Official receipt numbering authority, issuance event, payment finality, departmental requirement catalog, and cross-office evidence remain unresolved.',
                metadata: ['issuer' => 'Municipal Treasurer', 'candidate_trigger' => 'business_tax_payment', 'does_not_prove' => 'other_departmental_requirements_satisfied'],
            ),
            $this->policyBoundaryClause(
                sequence: 5,
                code: 'MRC-2E-04-B-RECEIPT-DISPLAY-DEMAND',
                type: RevenueCodeProvisionClauseType::ReceiptRequirement,
                sourceText: 'Every person issued an official receipt for the conduct of a business or undertaking shall keep the same conspicuously posted in plain view at the place of business or undertaking. If individual has no fixed place of business or office, he shall keep the official receipt in his person. The receipt shall be produced upon demand by the Municipal Mayor, Municipal Treasurer, or their duly authorized representatives.',
                candidateInterpretation: 'Candidate compliance evidence: display the official receipt at a fixed business, carry it when no fixed place exists, and produce it to an authorized official on demand.',
                executionBlocker: 'Digital receipt equivalence, fixed-place classification, inspection procedure, representative authorization, notice, and violation handling require municipal policy.',
                metadata: ['candidate_modes' => ['display_at_fixed_place', 'carry_without_fixed_place'], 'demand_authorities' => ['Municipal Mayor', 'Municipal Treasurer', 'authorized_representative']],
            ),
            $this->policyBoundaryClause(
                sequence: 6,
                code: 'MRC-2E-04-C-INVOICE-THRESHOLD',
                type: RevenueCodeProvisionClauseType::ReceiptRequirement,
                sourceText: 'All persons subject to the taxes on business shall, for each sell or transfer of merchandise or goods, or for services rendered, valued at Twenty-Five Pesos (P25.00) or more at any one time, prepare and issue sales or commercial invoices and receipts serially numbered in duplicate, showing among others, their names or styles, if any, and business address.',
                candidateInterpretation: 'Candidate invoice threshold: issue a serially numbered duplicate invoice or receipt for a covered sale, transfer, or service valued at PHP 25.00 or more.',
                executionBlocker: 'Inflation or superseding-law effect, transaction aggregation, invoice numbering authority, form, electronic records, and enforcement require reconciliation.',
                metadata: ['source_threshold_pesos' => '25.00', 'candidate_threshold_cents' => 2_500, 'candidate_copy_count' => 2],
                amountCents: 2_500,
            ),
            $this->policyBoundaryClause(
                sequence: 7,
                code: 'MRC-2E-04-C-INVOICE-COPIES',
                type: RevenueCodeProvisionClauseType::ReceiptRequirement,
                sourceText: 'The original of each sales invoice or receipts shall be issued to the purchaser or customer and the duplicate to be kept and preserved by the person subject to the said tax, in his place of business for a period of Five (5) years.',
                candidateInterpretation: 'Candidate record handling: provide the original to the customer and preserve the duplicate at the business for five years.',
                executionBlocker: 'Electronic-copy equivalence, retention start date, storage location, privacy, inspection access, and superseding national requirements require policy.',
                metadata: ['original_recipient' => 'purchaser_or_customer', 'duplicate_custodian' => 'taxpayer', 'retention_years' => 5],
            ),
            $this->policyBoundaryClause(
                sequence: 8,
                code: 'MRC-2E-04-C-FIVE-YEAR-RETENTION',
                type: RevenueCodeProvisionClauseType::RecordRetention,
                sourceText: 'The duplicate to be kept and preserved by the person subject to the said tax, in his place of business for a period of Five (5) years.',
                candidateInterpretation: 'Candidate retention boundary: preserve the duplicate invoice or receipt for five years at the place of business.',
                executionBlocker: 'Retention commencement, electronic archives, closed or transferred businesses, off-site storage, disposal, and access authority require accepted records policy.',
                metadata: ['candidate_retention_years' => 5, 'candidate_storage_location' => 'place_of_business'],
            ),
            $this->policyBoundaryClause(
                sequence: 9,
                code: 'MRC-2E-04-C-BIR-RECEIPT-SUFFICIENCY',
                type: RevenueCodeProvisionClauseType::DocumentaryRequirement,
                sourceText: 'The receipts or invoices issued pursuant to the requirement of the Bureau of Internal Revenue for determination of national internal revenue taxes shall be sufficient for purposes of this Code.',
                candidateInterpretation: 'Candidate equivalence: a qualifying BIR-required receipt or invoice satisfies the local invoice requirement.',
                executionBlocker: 'Qualifying BIR document types, current national rules, electronic invoice treatment, authenticity checks, and local review procedure require reconciliation.',
                metadata: ['candidate_equivalent_authority' => 'Bureau of Internal Revenue'],
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-2E-04-F-LOST-RECEIPT-CERTIFICATION', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-2E-04-F-PAID-TAX-CERTIFICATION',
                type: RevenueCodeProvisionClauseType::ReceiptCertification,
                sourceText: 'The Municipal Treasurer may, upon presentation or satisfactory proof that the original receipt has been lost, stolen or destroyed, issue a certification to the effect that the business tax has been paid, indicating therein, the number of official receipt issued, upon payment of a fee of One Hundred Pesos (P100.00).',
                candidateInterpretation: 'Candidate certification: after satisfactory proof of an unavailable original receipt, the Treasurer may issue a paid-tax certification identifying the official receipt for a stated PHP 100.00 fee.',
                executionBlocker: 'Proof standard, requester authority, receipt lookup, certification format and numbering, approval, and operational fee acceptance require reconciliation.',
                metadata: ['decision_authority' => 'Municipal Treasurer', 'source_fee_text' => 'One Hundred Pesos (P100.00)', 'candidate_fee_cents' => 10_000],
                amountCents: 10_000,
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-2E-04-G-LOCATION-TRANSFER', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-2E-04-G-PAID-PERIOD-LOCATION-TRANSFER',
                type: RevenueCodeProvisionClauseType::LocationTransfer,
                sourceText: 'Any business for which a municipal business tax has been paid by the person conducting it may be transferred and continued in any other place within the territorial limits of this municipal without payment of additional tax during the period for which the payment of the tax was made.',
                candidateInterpretation: 'Candidate location-transfer treatment: a tax-paid business may continue at another municipal location without additional business tax for the same paid period.',
                executionBlocker: 'Proof of payment, same-business identity, territorial validation, paid-period scope, permit amendment or replacement, clearances, inspection, and effective date require municipal acceptance.',
                metadata: ['candidate_transfer_type' => 'location_within_municipality', 'candidate_tax_effect' => 'no_additional_tax_for_paid_period'],
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-2E-04-RETIREMENT', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-2E-04-RETIREMENT-THIRTY-DAY-STATEMENT',
                type: RevenueCodeProvisionClauseType::RetirementRequirement,
                sourceText: 'Any person natural or juridical, subject to the tax on business under Article A, Chapter II of this Ordinance shall, upon termination of the business, submit a sworn statement of the gross sales or receipts for the current calendar year within thirty (30) days following the closure.',
                candidateInterpretation: 'Candidate retirement evidence: a covered taxpayer submits a sworn current-year gross-sales or receipts statement within 30 days after closure.',
                executionBlocker: 'Closure date authority, covered taxpayers, form, oath, submission channel, late handling, and documentary sufficiency require municipal policy.',
                metadata: ['candidate_deadline_days_after_closure' => 30, 'declared_period' => 'current_calendar_year'],
            ),
            $this->policyBoundaryClause(
                sequence: 2,
                code: 'MRC-2E-04-RETIREMENT-TAX-BEFORE-TERMINATION',
                type: RevenueCodeProvisionClauseType::TaxSettlement,
                sourceText: 'Any tax due shall first be paid before any business or undertaking is fully terminated.',
                candidateInterpretation: 'Candidate authority boundary: all tax due must be settled before official termination.',
                executionBlocker: 'Final-liability calculation, surcharge, interest, deficiency, payment finality, reconciliation, waiver, and approval authority remain unresolved.',
                metadata: ['candidate_precondition' => 'all_tax_due_paid', 'candidate_effect' => 'official_termination'],
            ),
            $this->policyBoundaryClause(
                sequence: 3,
                code: 'MRC-2E-04-RETIREMENT-LATE-FINE',
                type: RevenueCodeProvisionClauseType::TaxSettlement,
                sourceText: 'Failure on the part of the permittee to retire the business within the period stated above shall be fined in the amount of One Thousand Pesos (P1,000.00).',
                candidateInterpretation: 'Candidate late-retirement fine: PHP 1,000.00 after the stated retirement period is missed.',
                executionBlocker: 'Trigger date, due process, notice, assessment authority, waiver, appeal, collection, and superseding ordinance effect require municipal reconciliation.',
                metadata: ['source_fine_text' => 'One Thousand Pesos (P1,000.00)', 'candidate_fine_cents' => 100_000],
                amountCents: 100_000,
            ),
            $this->policyBoundaryClause(
                sequence: 4,
                code: 'MRC-2E-04-RETIREMENT-TERMINATION-MEANING',
                type: RevenueCodeProvisionClauseType::RetirementRequirement,
                sourceText: 'For the purposes hereof, termination shall mean that business operations are stopped completely. Any change in ownership, management and/or name of the business shall constitute termination as herein contemplated.',
                candidateInterpretation: 'Candidate legal meaning: complete cessation is termination, and ownership, management, or business-name change is also treated as termination.',
                executionBlocker: 'Relationship to amendment and transfer intake, effective date, successor identity, partial closure, and prior-permit legal effect require municipal acceptance.',
                metadata: ['candidate_termination_events' => ['complete_cessation', 'ownership_change', 'management_change', 'business_name_change']],
            ),
            $this->policyBoundaryClause(
                sequence: 5,
                code: 'MRC-2E-04-RETIREMENT-SUCCESSOR-RENEWAL-RECORD',
                type: RevenueCodeProvisionClauseType::RetirementRequirement,
                sourceText: 'Unless stated otherwise, assumption of the business by any new owner or manager or registration of the same business under a new name will only be considered by the LGU concerned for record purposes in the course of the renewal of the permit or license to operate the business.',
                candidateInterpretation: 'Candidate record treatment: successor or renamed-business facts are considered during permit renewal unless another provision controls.',
                executionBlocker: 'The relationship among termination, new permit, transfer, amendment, and renewal appears internally tensioned and requires municipal interpretation.',
                metadata: ['candidate_recording_point' => 'permit_renewal', 'source_tension' => 'termination_and_new_owner_permit_requirements'],
            ),
            $this->policyBoundaryClause(
                sequence: 6,
                code: 'MRC-2E-04-RETIREMENT-INSPECTION',
                type: RevenueCodeProvisionClauseType::AuthorityBoundary,
                sourceText: 'The Municipal Treasurer shall assign every application for the termination or retirement of business to an inspector in his office who shall go to address of the business on record to verify if it is really not operating.',
                candidateInterpretation: 'Candidate verification step: the Treasurer assigns an inspector to verify non-operation at the recorded business address.',
                executionBlocker: 'Inspector assignment, scheduling, evidence standard, failed access, remote inspection, findings, audit record, and review authority require operational procedure.',
                metadata: ['assignment_authority' => 'Municipal Treasurer', 'candidate_verification' => 'not_operating_at_recorded_address'],
            ),
            $this->policyBoundaryClause(
                sequence: 7,
                code: 'MRC-2E-04-RETIREMENT-DISAPPROVAL',
                type: RevenueCodeProvisionClauseType::AuthorityBoundary,
                sourceText: 'If the inspector finds that the business is simply placed under a new name, manager and/or new owner, the Municipal Treasurer shall recommend to the Municipal Mayor the disapproval of the application of the termination or retirement of said business.',
                candidateInterpretation: 'Candidate authority path: the Treasurer recommends disapproval to the Mayor when inspection identifies continuation under a new name, manager, or owner.',
                executionBlocker: 'Finding standard, recommendation evidence, Mayor decision procedure, notice, response, appeal, and resulting application status require municipal policy.',
                metadata: ['recommending_authority' => 'Municipal Treasurer', 'decision_authority' => 'Municipal Mayor', 'candidate_outcome' => 'retirement_disapproval'],
            ),
            $this->policyBoundaryClause(
                sequence: 8,
                code: 'MRC-2E-04-RETIREMENT-CONTINUING-LIABILITY',
                type: RevenueCodeProvisionClauseType::TaxSettlement,
                sourceText: 'Accordingly, the business continues to become liable for the payment for all taxes, fees, and charges imposed thereon under existing local tax ordinance.',
                candidateInterpretation: 'Candidate consequence: a business not legitimately retired remains liable for taxes, fees, and charges.',
                executionBlocker: 'Decision finality, liability period, applicable catalog version, surcharge and interest, responsible taxpayer, and collection procedure require reconciliation.',
                metadata: ['candidate_liability' => ['taxes', 'fees', 'charges'], 'candidate_condition' => 'retirement_not_accepted'],
            ),
            $this->policyBoundaryClause(
                sequence: 9,
                code: 'MRC-2E-04-RETIREMENT-NEW-OWNER-PERMIT',
                type: RevenueCodeProvisionClauseType::PermitRequirement,
                sourceText: 'In addition, in the case of a new owner to whom the business was transferred by sale or other form of conveyance, said new owner shall be liable to pay the tax or fee for the business and shall secure a new Mayor’s permit therefore.',
                candidateInterpretation: 'Candidate ownership-transfer treatment: the new owner owes the applicable tax or fee and obtains a new Mayor’s Permit.',
                executionBlocker: 'Conveyance evidence, predecessor and successor identities, prior liability, application type, tax basis, permit supersession, and effective date require municipal acceptance.',
                metadata: ['candidate_transfer_type' => 'ownership_by_sale_or_conveyance', 'candidate_requirements' => ['tax_or_fee', 'new_mayors_permit']],
            ),
            $this->policyBoundaryClause(
                sequence: 10,
                code: 'MRC-2E-04-RETIREMENT-FINAL-DEFICIENCY',
                type: RevenueCodeProvisionClauseType::DeficiencyTax,
                sourceText: 'In case it is found that the retirement or termination of the business is legitimate and the tax paid during the current year be less than the tax due for the current year based on the gross sales or receipts, the difference in the amount of the tax shall be paid before the business is considered officially retired or terminated.',
                candidateInterpretation: 'Candidate final settlement: after legitimate retirement is verified, pay any current-year business-tax deficiency based on gross sales or receipts before official retirement.',
                executionBlocker: 'Gross-receipts evidence, current-year tax recomputation, PIL, surcharge, interest, rounding, payment allocation, and approval remain unresolved.',
                metadata: ['candidate_basis' => 'current_year_gross_sales_or_receipts', 'candidate_precondition' => 'retirement_verified_legitimate'],
            ),
            $this->policyBoundaryClause(
                sequence: 11,
                code: 'MRC-2E-04-RETIREMENT-PERMIT-CANCELLATION',
                type: RevenueCodeProvisionClauseType::PermitCancellation,
                sourceText: 'The permit issued to a business retiring or terminating its operation shall be surrendered to the Local Treasurer who shall forthwith cancel the same and record such cancellation in his books.',
                candidateInterpretation: 'Candidate authority boundary: surrender the permit to the Treasurer, who cancels it and records the cancellation.',
                executionBlocker: 'Physical or digital surrender, Treasurer authority delegation, cancellation numbering, timestamp, public status, artifact invalidation, QR meaning, and legal effect require policy.',
                metadata: ['candidate_custodian' => 'Local Treasurer', 'candidate_actions' => ['permit_surrender', 'permit_cancellation', 'cancellation_record']],
            ),
            $this->policyBoundaryClause(
                sequence: 12,
                code: 'MRC-2E-04-RETIREMENT-TWO-PERIOD-TAX-BASES',
                type: RevenueCodeProvisionClauseType::TaxSettlement,
                sourceText: 'A company who has retired its business must pay business tax on its gross sales of the preceding year within the first 20 days of January of the current year and on its gross sales from January 1 of the current year up to the date business actually ceased operations.',
                candidateInterpretation: 'Candidate final tax periods: settle tax based on preceding-year gross sales and current-year gross sales through the actual cessation date.',
                executionBlocker: 'Possible double-counting, annual versus quarterly payments, cessation date evidence, proration, rate version, PIL, deficiency, surcharge, interest, and rounding require reconciliation.',
                metadata: ['candidate_tax_periods' => ['preceding_year', 'current_year_through_actual_cessation'], 'preceding_year_due_day' => 20, 'preceding_year_due_month' => 1],
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-2E-04-DEATH-TAX-MAPPING', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-2E-04-DEATH-ESTATE-CONTINUATION',
                type: RevenueCodeProvisionClauseType::EstateContinuation,
                sourceText: 'When any individual paying a business tax dies, and the business is continued by a person interested in his estate, no additional payment shall be required for the residue of the term for which the tax was paid.',
                candidateInterpretation: 'Candidate estate continuation: an interested person continuing the deceased taxpayer’s business owes no additional payment for the remainder of the already paid term.',
                executionBlocker: 'Death evidence, estate interest and authority, same-business identity, remaining term, permit holder, succession, new-period obligations, and legal effect require municipal policy.',
                metadata: ['candidate_tax_effect' => 'no_additional_payment_for_paid_term_remainder', 'candidate_successor' => 'person_interested_in_estate'],
            ),
            $this->policyBoundaryClause(
                sequence: 2,
                code: 'MRC-2E-04-TAX-MAPPED-STICKER',
                type: RevenueCodeProvisionClauseType::TaxMapping,
                sourceText: 'The Office of the Municipal Treasurer shall be responsible in placing stickers marked “TAX MAPPED” for all business establishments that have been officially tax inspected and assessed.',
                candidateInterpretation: 'Candidate inspection evidence: the Treasurer’s Office places a TAX MAPPED sticker after official tax inspection and assessment.',
                executionBlocker: 'Sticker identity, issuance and replacement, inspection and assessment completion criteria, validity period, public meaning, and audit record require operational policy.',
                metadata: ['responsible_office' => 'Office of the Municipal Treasurer', 'candidate_prerequisites' => ['official_tax_inspection', 'assessment']],
            ),
        ]);

        $this->seedMayorsPermitArticleAClauses();
        $this->seedCockpitArticleBClauses();
        $this->seedSpecialCockfightingArticleCClauses();

        $this->persistPolicyBoundaryClauses('MRC-2F-01-PIL', [
            $this->pilThresholdClause(1, 'SARI-SARI', '1', 'Sari-Sari Stores', '61,600.00', 6_160_000),
            $this->pilThresholdClause(2, 'SARI-SARI-LIQUOR-CIGARETTES', '1', 'Sari-Sari Stores with Liquors & Cigarettes', '308,000.00', 30_800_000),
            $this->pilThresholdClause(3, 'GROCERY', '1', 'Grocery Stores', '6,160,000.00', 616_000_000),
            $this->pilThresholdClause(4, 'SUPERMARKET', '1', 'Supermarket', '15,400,000.00', 1_540_000_000),
            $this->pilThresholdClause(5, 'RETAILERS', '2', 'Retailers', '616,000.00', 61_600_000),
            $this->pilThresholdClause(6, 'EATERY-CARENDERIA', '3', 'Eatery/Carenderia', '924,000.00', 92_400_000),
            $this->pilThresholdClause(7, 'RESTAURANTS', '3', 'Restaurants', '3,080,000.00', 308_000_000),
            $this->pilThresholdClause(8, 'FASTFOODS', '3', 'Fastfoods', '9,240,000.00', 924_000_000),
            $this->pilThresholdClause(9, 'MANUFACTURERS', '4', 'Manufacturers', '3,080,000.00', 308_000_000),
            $this->pilThresholdClause(10, 'REFILLING-STATIONS', '5', 'Refilling Stations', '770,000.00', 77_000_000),
            $this->pilThresholdClause(11, 'WHOLESALERS-DEALERS-DISTRIBUTORS', '5', 'Wholesalers/Dealers/Distributors', '6,1600,000.00', null, 'Malformed source value; no numeric candidate is authorized.'),
            $this->pilThresholdClause(12, 'CONTRACTORS', '6', 'Contractors', '5,000,000.00', 500_000_000),
            $this->pilThresholdClause(13, 'PAWNSHOPS-LENDING', '7', 'Pawnshops/Lending Institutions', '2,000,000.00', 200_000_000),
            $this->pilThresholdClause(14, 'BEAUTY-PARLOR', '8', 'Beauty Parlor', '616,000.00', 61_600_000),
            $this->pilThresholdClause(15, 'BEER-HOUSE-GARDENS', '9', 'Beer House/Beer Gardens', '3,080,000.00', 308_000_000),
            $this->pilThresholdClause(16, 'BARBER-SHOPS', '10', 'Barber Shops', '308,000.00', 30_800_000),
            $this->pilThresholdClause(17, 'SMALL-REPAIR-SHOPS', '11', 'Small Scale Repair Shops and the like', '100,000.000', null, 'Malformed decimal precision; no numeric candidate is authorized.'),
            $this->pilThresholdClause(18, 'REFRESHMENT-COCKTAIL', '12', 'Refreshment Parlor/Cocktail Lounge', '1,540,000.00', 154_000_000),
            $this->pilThresholdClause(19, 'BAKERY', '13', 'Bakery (Wholesale and Retail)', '1,000,000.00', 100_000_000),
            $this->pilThresholdClause(20, 'TAILORING-DRESS', '14', 'Tailoring/Dress Shop', '770,000.00', 77_000_000),
            $this->pilThresholdClause(21, 'BANKS', '15', 'Banks', '5,000,000.00', 500_000_000),
            $this->pilThresholdClause(22, 'LODGING-PENSION', '16', 'Lodging/Pension House', '547,500.00', 54_750_000),
            $this->pilThresholdClause(23, 'BOARDING-HOUSE', '16', 'Boarding House', '120,000.00', 12_000_000),
            $this->pilThresholdClause(24, 'HOTEL', '16', 'Hotel', '4,927,500.00', 492_750_000),
            $this->pilThresholdClause(25, 'REAL-ESTATE-LESSOR', '17', 'Real Estate Lessor', '420,000.00', 42_000_000),
            $this->pilThresholdClause(26, 'MASSAGE-BEAUTY-SPA', '18', 'Massage Parlor/Beauty Spa', '924,000.00', 92_400_000),
            $this->pilThresholdClause(27, 'INTERNET-CAFE', '19', 'Internet Cafe', '246,400.00', 24_640_000),
            $this->pilThresholdClause(28, 'RICE-CORN-MILL', '20', 'Rice/Corn Mill', '2,772,000.00', 277_200_000),
            $this->policyBoundaryClause(
                sequence: 29,
                code: 'MRC-2F-01-PIL-VALIDATION-FALLBACK',
                type: RevenueCodeProvisionClauseType::ValidationFallback,
                sourceText: 'The Presumptive Income Level (PIL) of gross receipts shall be used to validate the gross receipts declared by taxpayers and/or for establishing the taxable gross receipts where no valid data is otherwise available.',
                candidateInterpretation: 'Candidate two-part use: compare declared receipts with PIL for validation, or establish taxable receipts from PIL only where valid data is unavailable.',
                executionBlocker: 'Classification mapping, valid-data criteria, discrepancy handling, substitution authority, taxpayer notice and challenge, approval, effective version, and audit requirements are unresolved.',
                metadata: ['candidate_uses' => ['validate_declared_gross_receipts', 'establish_taxable_gross_receipts_when_valid_data_unavailable']],
            ),
        ]);
    }

    private function seedMayorsPermitArticleAClauses(): void
    {
        $this->persistPolicyBoundaryClauses('MRC-3A-01-02-PERMIT-SCOPE-ENTERPRISE-SCALE', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-3A-01-MAYORS-PERMIT-REQUIREMENT',
                type: RevenueCodeProvisionClauseType::PermitRequirement,
                sourceText: 'It shall be unlawful for any person or entity to conduct or engaged in any business, trade or occupation within the territorial jurisdiction of the municipality of Ipil for which a permit is required for the proper supervision and enforcement of existing laws and ordinances governing the sanitation, security and welfare of the public and the health of the employees engaged in business, trade or occupation specified in this Code and other ordinances that may hereafter be enacted, without first having secured a permit therefore from the Municipal Mayor.',
                candidateInterpretation: "Candidate scope: covered businesses, trades, occupations, and activities operating in Ipil require a Mayor's Permit before operation.",
                executionBlocker: 'The covered activity catalog, exemptions, territorial facts, prerequisite evidence, and authority procedure require accepted municipal policy.',
                metadata: ['candidate_authority' => 'Municipal Mayor', 'candidate_timing' => 'before_operation'],
            ),
            $this->policyBoundaryClause(
                sequence: 2,
                code: 'MRC-3A-02-SEPARATE-BUSINESS-PLACE-FEE',
                type: RevenueCodeProvisionClauseType::SeparateEstablishment,
                sourceText: 'The permit fee is payable for every distinct or separate business or place where the business or trade is conducted. One line of business or trade does not become exempt by being conducted with some other business or trade for which the permit fee has been obtained and the corresponding fee paid for.',
                candidateInterpretation: 'Candidate scope: assess each distinct business, place, and non-exempt line of business separately.',
                executionBlocker: 'Business, establishment, branch, place, co-located line, and fee-combination identity require accepted policy and production-data mapping.',
                metadata: ['candidate_dimensions' => ['business', 'place', 'line_of_business']],
            ),
            $this->enterpriseScaleClause(3, 'MICRO', 'Micro-Industry', 'P 150,000 and below', 'No specific', 0, 15_000_000),
            $this->enterpriseScaleClause(4, 'COTTAGE', 'Cottage Industries', 'Above P 150,000 to P 1.5M', 'Less than 10', 15_000_000, 150_000_000),
            $this->enterpriseScaleClause(5, 'SMALL', 'Small-scale Industries', 'P 1.5M to P 15M', '10-99', 150_000_000, 1_500_000_000),
            $this->enterpriseScaleClause(6, 'MEDIUM', 'Medium-scale Industries', 'P 15M to P 60M', '100-199', 1_500_000_000, 6_000_000_000),
            $this->enterpriseScaleClause(7, 'LARGE', 'Large-scale Industries', 'Above P 60M', '200 more', 6_000_000_000, null),
        ]);

        $this->persistFixedFeeScheduleClauses('MRC-3A-02-A-01-06-GENERAL-PERMIT-FEES', [
            ['MRC-3A-02-A-01-MANUFACTURER-MICRO', 'Manufacturers/Importers/Producers', 'Micro-Industry', '300.00', 30_000],
            ['MRC-3A-02-A-01-MANUFACTURER-COTTAGE', 'Manufacturers/Importers/Producers', 'Cottage Industries', '500.00', 50_000],
            ['MRC-3A-02-A-01-MANUFACTURER-SMALL', 'Manufacturers/Importers/Producers', 'Small-scale Industries', '1,000.00', 100_000],
            ['MRC-3A-02-A-01-MANUFACTURER-MEDIUM', 'Manufacturers/Importers/Producers', 'Medium-Scale Industries', '1,500.00', 150_000],
            ['MRC-3A-02-A-01-MANUFACTURER-LARGE', 'Manufacturers/Importers/Producers', 'Large-Scale Industries', '2,000.00', 200_000],
            ['MRC-3A-02-A-02-BANK-RURAL', 'Banks', 'Rural, Thrift and Savings Banks', '5,000.00', 500_000],
            ['MRC-3A-02-A-02-BANK-COMMERCIAL', 'Banks', 'Commercial, Industrial and Development Banks', '15,000.00', 1_500_000],
            ['MRC-3A-02-A-02-BANK-UNIVERSAL', 'Banks', 'Universal Banks', '20,000.00', 2_000_000],
            ['MRC-3A-02-A-03-FINANCIAL-SMALL', 'Other Financial Institutions', 'Small', '3,000.00', 300_000],
            ['MRC-3A-02-A-03-FINANCIAL-MEDIUM', 'Other Financial Institutions', 'Medium', '5,000.00', 500_000],
            ['MRC-3A-02-A-03-FINANCIAL-LARGE', 'Other Financial Institutions', 'Large', '15,000.00', 1_500_000],
            ['MRC-3A-02-A-04-CONTRACTOR-MICRO', 'Contractors', 'Micro-Industry', '1,000.00', 100_000],
            ['MRC-3A-02-A-04-CONTRACTOR-COTTAGE', 'Contractors', 'Cottage Industries', '2,000.00', 200_000],
            ['MRC-3A-02-A-04-CONTRACTOR-SMALL', 'Contractors', 'Small-scale Industries', '3,000.00', 300_000],
            ['MRC-3A-02-A-04-CONTRACTOR-MEDIUM', 'Contractors', 'Medium-Scale Industries', '4,000.00', 400_000],
            ['MRC-3A-02-A-04-CONTRACTOR-LARGE', 'Contractors', 'Large-Scale Industries', '5,000.00', 500_000],
            ['MRC-3A-02-A-05-SERVICE-MICRO', 'Service Establishments', 'Micro-Industry', '300.00', 30_000],
            ['MRC-3A-02-A-05-SERVICE-UNLABELED', 'Service Establishments', null, '500.00', 50_000, 'The source table contains an unlabeled PHP 500.00 row; no enterprise-scale assignment is authorized.'],
            ['MRC-3A-02-A-05-SERVICE-COTTAGE', 'Service Establishments', 'Cottage Industries', '1,000.00', 100_000],
            ['MRC-3A-02-A-05-SERVICE-SMALL', 'Service Establishments', 'Small-scale Industries', '1,500.00', 150_000],
            ['MRC-3A-02-A-05-SERVICE-MEDIUM', 'Service Establishments', 'Medium-Scale Industries', '2,000.00', 200_000],
            ['MRC-3A-02-A-05-SERVICE-LARGE', 'Service Establishments', 'Large-Scale Industries', null, null, 'The source table prints Large-Scale Industries without an aligned amount; no value is inferred from neighboring rows.'],
            ['MRC-3A-02-A-06-WHOLESALE-MICRO', 'Wholesalers/Retailers/Dealers or Distributors', 'Micro-Industry', '300.00', 30_000],
            ['MRC-3A-02-A-06-WHOLESALE-COTTAGE', 'Wholesalers/Retailers/Dealers or Distributors', 'Cottage Industries', '500.00', 50_000],
            ['MRC-3A-02-A-06-WHOLESALE-SMALL', 'Wholesalers/Retailers/Dealers or Distributors', 'Small-scale Industries', '1,000.00', 100_000],
            ['MRC-3A-02-A-06-WHOLESALE-MEDIUM', 'Wholesalers/Retailers/Dealers or Distributors', 'Medium-Scale Industries', '1,500.00', 150_000],
            ['MRC-3A-02-A-06-WHOLESALE-LARGE', 'Wholesalers/Retailers/Dealers or Distributors', 'Large-Scale Industries', '2,000.00', 200_000],
        ]);

        $this->persistFixedFeeScheduleClauses('MRC-3A-02-A-07-13-SPECIAL-PERMIT-FEES', [
            ['MRC-3A-02-A-07-LIQUOR-MICRO', 'Liquors, Distilled Spirits and Manufactured Tobacco', 'Micro-Industry', '1,000.00', 100_000],
            ['MRC-3A-02-A-07-LIQUOR-COTTAGE', 'Liquors, Distilled Spirits and Manufactured Tobacco', 'Cottage Industries', '2,000.00', 200_000],
            ['MRC-3A-02-A-07-LIQUOR-SMALL', 'Liquors, Distilled Spirits and Manufactured Tobacco', 'Small-scale Industries', '3,000.00', 300_000],
            ['MRC-3A-02-A-07-LIQUOR-MEDIUM', 'Liquors, Distilled Spirits and Manufactured Tobacco', 'Medium-Scale Industries', '4,000.00', 400_000],
            ['MRC-3A-02-A-07-LIQUOR-LARGE', 'Liquors, Distilled Spirits and Manufactured Tobacco', 'Large-Scale Industries', '5,000.00', 500_000],
            ['MRC-3A-02-A-08-TRANSLOADING-MEDIUM', 'Trans-loading Operations', 'Medium', '2,000.00', 200_000],
            ['MRC-3A-02-A-08-TRANSLOADING-LARGE', 'Trans-loading Operations', 'Large', '4,000.00', 400_000],
            ['MRC-3A-02-A-09-OTHER-MICRO', 'Other Businesses', 'Micro-Industry', '300.00', 30_000],
            ['MRC-3A-02-A-09-OTHER-COTTAGE', 'Other Businesses', 'Cottage Industries', '500.00', 50_000],
            ['MRC-3A-02-A-09-OTHER-SMALL', 'Other Businesses', 'Small-scale Industries', '1,000.00', 100_000],
            ['MRC-3A-02-A-09-OTHER-MEDIUM', 'Other Businesses', 'Medium-Scale Industries', '1,500.00', 150_000],
            ['MRC-3A-02-A-09-OTHER-LARGE', 'Other Businesses', 'Large-Scale Industries', '2,000.00', 200_000],
            ['MRC-3A-02-A-10-GASOLINE-UNLABELED', 'Gasoline Station', null, '5,000.00', 500_000, 'The source table aligns PHP 5,000.00 with the category heading rather than a nozzle classification; no classification assignment is authorized.'],
            ['MRC-3A-02-A-10-GASOLINE-SMALL', 'Gasoline Station', 'Small (1-5 nozzles)', '10,000.00', 1_000_000],
            ['MRC-3A-02-A-10-GASOLINE-MEDIUM', 'Gasoline Station', 'Medium (6-10 nozzles)', '15,000.00', 1_500_000],
            ['MRC-3A-02-A-10-GASOLINE-LARGE', 'Gasoline Station', 'Large (11 nozzles and up)', null, null, 'The source table prints the large nozzle classification without an aligned amount; no value is inferred from neighboring rows.'],
            ['MRC-3A-02-A-10-LPG-COTTAGE', 'LPG Dealers', 'Cottage', '3,000.00', 300_000],
            ['MRC-3A-02-A-10-LPG-SMALL', 'LPG Dealers', 'Small', '5,000.00', 500_000],
            ['MRC-3A-02-A-10-LPG-MEDIUM', 'LPG Dealers', 'Medium', '10,000.00', 1_000_000],
            ['MRC-3A-02-A-10-LPG-LARGE', 'LPG Dealers', 'Large', '20,000.00', 2_000_000],
            ['MRC-3A-02-A-10-DOE-COMPLIANCE', 'Gasoline Station / LPG Dealers', 'DOE Certificate of Compliance', null, null, 'DOE certificate issuance, applicability, verification, continuing validity, and enforcement remain external-authority and municipal-policy questions.', RevenueCodeProvisionClauseType::DocumentaryRequirement, 'Sec. 9 of DOE DC No. 2003-011-10: CERTIFICATE OF COMPLIANCE - The DOE, through the OIMB, shall issue a Certificate of Compliance upon the complete submission of and full compliance by the Retail Outlet owner and/or operator with the requirements provided in the foregoing Sections of this Rule. No Retail Outlet shall operate until a Certificate of Compliance is so secured from the DOE. The owner and/or operator shall be deemed to be engaged in the ILLEGAL TRADING of Liquid Petroleum Products if he/she operates without the Certificate of Compliance and/or violates any of the foregoing Sections.'],
            ['MRC-3A-02-A-11-TRUCKING-BELOW-5', 'Trucking/Hauling', 'Below 5 units', '1,000.00', 100_000],
            ['MRC-3A-02-A-11-TRUCKING-5-10', 'Trucking/Hauling', '5 to 10 units', '3,000.00', 300_000],
            ['MRC-3A-02-A-11-TRUCKING-11-UP', 'Trucking/Hauling', '11 units and above', '5,000.00', 500_000],
            ['MRC-3A-02-A-12-COOPERATIVES', 'Cooperatives', null, '1,000.00', 100_000],
            ['MRC-3A-02-A-13-EDUCATION-COTTAGE', 'Educational Institutions and any other business not mention in the chapter', 'Cottage', '1,000.00', 100_000],
            ['MRC-3A-02-A-13-EDUCATION-SMALL', 'Educational Institutions and any other business not mention in the chapter', 'Small', '5,000.00', 500_000],
            ['MRC-3A-02-A-13-EDUCATION-MEDIUM', 'Educational Institutions and any other business not mention in the chapter', 'Medium', '10,000.00', 1_000_000],
            ['MRC-3A-02-A-13-EDUCATION-LARGE', 'Educational Institutions and any other business not mention in the chapter', 'Large', '20,000.00', 2_000_000],
        ]);

        $this->persistFixedFeeScheduleClauses('MRC-3A-02-B-NEW-MICRO-PERMIT', [
            ['MRC-3A-02-B-NEW-MICRO', 'New Business', 'Micro-Industry', '200.00', 20_000],
            ['MRC-3A-02-B-NEW-COTTAGE', 'New Business', 'Cottage Industries', '500.00', 50_000],
            ['MRC-3A-02-B-NEW-SMALL', 'New Business', 'Small-scale Industries', '1,000.00', 100_000],
            ['MRC-3A-02-B-NEW-MEDIUM', 'New Business', 'Medium-Scale Industries', '1,500.00', 150_000],
            ['MRC-3A-02-B-NEW-LARGE', 'New Business', 'Large-Scale Industries', '2,000.00', 200_000],
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3A-03-PAYMENT-PRORATION', [
            $this->policyBoundaryClause(1, 'MRC-3A-03-PAYMENT-UPON-APPLICATION', RevenueCodeProvisionClauseType::PaymentTiming, "The fee for the issuance of the Mayor's Permit shall be paid to the Municipal Treasurer upon application before any business or undertaking can be lawfully begun or pursued.", 'Candidate timing: collect the permit fee upon application and before lawful operation.', 'Payment, receipt, issuance, and authority sequencing require accepted municipal procedure.', ['candidate_collector' => 'Municipal Treasurer', 'candidate_timing' => 'upon_application_before_operation']),
            $this->policyBoundaryClause(2, 'MRC-3A-03-RENEWAL-JANUARY-20', RevenueCodeProvisionClauseType::PaymentTiming, 'Within the first twenty (20) days of January of each year in case of renewal thereof.', 'Candidate renewal deadline: within the first 20 days of January.', 'Calendar, non-business-day, extension, late-payment, surcharge, and receipt rules require accepted policy.', ['candidate_month' => 1, 'candidate_day' => 20]),
            $this->policyBoundaryClause(3, 'MRC-3A-03-NEW-BUSINESS-QUARTER-RECKONING', RevenueCodeProvisionClauseType::PaymentTiming, 'For a newly-started business or activity that starts to operate after January 20, the fee shall be reckoned from the beginning of the calendar quarter.', 'Candidate proration boundary: reckon the fee from the beginning of the calendar quarter for a newly started business operating after January 20.', 'The calculation method, quarter boundaries, start-date evidence, annual-versus-quarter allocation, and rounding are not stated sufficiently for execution.', ['candidate_trigger' => 'operation_after_january_20', 'candidate_period' => 'calendar_quarter']),
            $this->policyBoundaryClause(4, 'MRC-3A-03-ABANDONMENT-NO-REFUND', RevenueCodeProvisionClauseType::TaxSettlement, 'When the business or activity is abandoned, the fee shall not be exacted for a period longer than the current quarter and the business activity is abandoned, no refund of the fee corresponding to the unexpired quarter or quarters shall be made.', 'Candidate abandonment treatment: stop exacting beyond the current quarter and do not refund fees for unexpired quarters.', 'The source repeats the abandonment phrase; abandonment evidence, effective date, fee allocation, already-paid treatment, and interaction with retirement policy require accepted procedure.', ['candidate_tax_effects' => ['no_fee_beyond_current_quarter', 'no_refund_for_unexpired_quarters'], 'known_source_defect' => 'duplicated_abandonment_phrase']),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3A-05-REGISTRATION-PLATE', [
            $this->policyBoundaryClause(1, 'MRC-3A-05-REGISTRATION-PLATE-CEILING', RevenueCodeProvisionClauseType::AmountCeiling, 'Applicants shall pay an amount not to exceed Three Hundred Pesos (P300.00) for the Business Permit Registration Plate and handling.', 'Candidate ceiling: the registration plate and handling charge may not exceed PHP 300.00.', 'The ordinance states a ceiling rather than the Municipality-confirmed exact operational amount.', ['candidate_charge' => 'registration_plate_and_handling'], 30_000, isCeiling: true),
            $this->policyBoundaryClause(2, 'MRC-3A-05-PLATE-STICKER-COVERAGE', RevenueCodeProvisionClauseType::PermitRequirement, 'The payment shall include the new plate and the corresponding annual or quarterly renewal sticker.', 'Candidate coverage: the charge includes a new plate and the corresponding annual or quarterly renewal sticker.', 'Plate eligibility, annual-versus-quarterly sticker issue, replacement, validity, custody, and audit procedure require accepted policy.', ['candidate_inclusions' => ['new_plate', 'annual_or_quarterly_renewal_sticker']]),
        ]);
    }

    private function seedCockpitArticleBClauses(): void
    {
        $this->persistPolicyBoundaryClauses('MRC-3B-01-DEFINITIONS', [
            $this->definitionClause(1, 'COCKPIT', 'Cockpit includes any place, compound, building or portion thereof, where cockfights are held, whether or not money bets are made on the results of such cockfights.', 'Candidate meaning: a cockpit is a place or part of a place where cockfights are held, regardless of whether money bets are made.'),
            $this->definitionClause(2, 'COCKFIGHTING', 'Cockfighting shall embrace and mean the commonly known game or term “cockfighting derby, pintakasi or tubada” or each equivalent term in different Philippine localities.', 'Candidate meaning: cockfighting includes derby, pintakasi, tubada, and equivalent local terms.'),
            $this->definitionClause(3, 'LOCAL-DERBY', 'Local Derby is an invitational cockfight participated in by gamecockers or cockfighting “afficionados” of the Philippines with “pot money” awarded to the proclaimed winning entry.', 'Candidate meaning: a local derby is an invitational event for Philippine participants with pot money awarded to the winning entry.'),
            $this->definitionClause(4, 'INTERNATIONAL-DERBY', 'International Derby refers to an invitational cockfight participated in by local and foreign gamecockers or cockfighting “aficionados” with “pot money” awarded to the proclaimed winning entry.', 'Candidate meaning: an international derby is an invitational event involving local and foreign participants with pot money awarded to the winning entry.'),
            $this->definitionClause(5, 'BET-TAKER-PROMOTER', 'Bet taker or promoter refers to a person who alone or with another initiates a cockfight and/or calls and take care of bets from the owners of both gamecocks and those of other bettors before he orders commencement of the cockfight thereafter distributes won bets to the winners after deducting a certain commission, or both.', 'Candidate meaning: a bet taker or promoter initiates a fight and/or handles, distributes, and deducts commission from bets.'),
            $this->definitionClause(6, 'GAFFER', 'Gaffer (taga-tari) refers to a person knowledgeable in the art of arming fighting cocks with gaffs on one or both legs.', 'Candidate meaning: a gaffer arms fighting cocks with gaffs.'),
            $this->definitionClause(7, 'REFEREE', 'Referee (Sentenciador) refers who a person who watches and oversees the proper gaffing of fighting cocks; determine the physical condition of gamecocks while cockfighting is in progress, the injuries sustained by the cocks and their capability to continue fighting, and decides and makes known his decision either by word or gesture the result of the cockfighting by announcing the winner or deciding a tie in a contest game.', 'Candidate meaning: a referee oversees gaffing, evaluates fighting condition, and declares a winner or tie.'),
            $this->definitionClause(8, 'BETTOR', 'Bettor a person who participates in cockfights and with the use of money or other things of value, bets with other bettors or through the bet taker or promoter and win or lose his bet depending upon the result of the cockfight as announced by the referee or sentenciador. He may be the owner of fighting cock.', 'Candidate meaning: a bettor wagers money or value directly or through a bet taker and may own a fighting cock.'),
            $this->definitionClause(9, 'MATCHMAKER', 'Matchmaker one who arranges cockfights.', 'Candidate meaning: a matchmaker arranges cockfights.'),
            $this->definitionClause(10, 'CASHIER', 'Cashier a person in charge of the cash transactions of a cockpit.', 'Candidate meaning: a cashier handles cockpit cash transactions.'),
            $this->definitionClause(11, 'MEDICAL-AID', 'Medical Aid a person in charge in treating wounded cockpit', 'Candidate meaning: medical aid is a person responsible for treatment described by the source, whose object is grammatically incomplete.'),
        ]);

        $this->persistFixedFeeScheduleClauses('MRC-3B-02-PERMIT-FEES', [
            ['MRC-3B-02-OWNER-APPLICATION-FILING', 'Owner/Operator/Licensee of the Cockpit', 'Application Filling Fee', '20,000.00', 2_000_000],
            ['MRC-3B-02-OWNER-ANNUAL-PERMIT', 'Owner/Operator/Licensee of the Cockpit', 'Annual Cockpit Permit Fee', '10,000.00', 1_000_000],
            ['MRC-3B-02-PERSONNEL-PROMOTER-HOST', 'Cockpit Personnel', 'Promoter/Host', '2,000.00', 200_000],
            ['MRC-3B-02-PERSONNEL-MANAGER', 'Cockpit Personnel', 'Cockpit Manager', '1,000.00', 100_000],
            ['MRC-3B-02-PERSONNEL-REFEREE', 'Cockpit Personnel', 'Referee', '200.00', 20_000],
            ['MRC-3B-02-PERSONNEL-BET-TAKER', 'Cockpit Personnel', 'Bet Taker “Kristo” (Inside/Outside of the Rueda”', '300.00', 30_000],
            ['MRC-3B-02-PERSONNEL-BET-MANAGER', 'Cockpit Personnel', 'Bet Manager “Maciador/ Kasador”', '500.00', 50_000],
            ['MRC-3B-02-PERSONNEL-GAFFER', 'Cockpit Personnel', 'Gaffer “ Mananari”', '200.00', 20_000],
            ['MRC-3B-02-PERSONNEL-CASHIER', 'Cockpit Personnel', 'Cashier', '200.00', 20_000],
            ['MRC-3B-02-PERSONNEL-DERBY-MATCHMAKER', 'Cockpit Personnel', 'Derby (Matchmaker)', '1,000.00', 100_000],
            ['MRC-3B-02-PERSONNEL-MEDICAL-AID', 'Cockpit Personnel', 'Medical Aid', '200.00', 20_000],
            ['MRC-3B-02-HACKFIGHT-PER-FIGHT', 'Cockfight Event', 'Hackfight', '100.00/fight', 10_000],
            ['MRC-3B-02-DERBY-MISSING-AMOUNT', 'Cockfight Event', 'Derby', null, null, 'The source table prints a Derby row with no amount; no value is inferred from Article C or neighboring rows.'],
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3B-03-04-FRANCHISE-LICENSING-REGISTRATION', [
            $this->policyBoundaryClause(1, 'MRC-3B-03-TEN-YEAR-FRANCHISE', RevenueCodeProvisionClauseType::PermitRequirement, 'No cockpit shall be established, operated and maintained without first securing a franchise from the Sangguniang Bayan and which shall be for a period of ten (10) years.', 'Candidate authority boundary: secure a ten-year Sangguniang Bayan franchise before establishing, operating, or maintaining a cockpit.', 'Franchise application, grant, effective date, renewal, revocation, evidence, and current legal authority require accepted procedure.', ['candidate_authority' => 'Sangguniang Bayan', 'source_term_years' => 10]),
            $this->policyBoundaryClause(2, 'MRC-3B-03-LICENSE-ORDINANCE-AUTHORITY', RevenueCodeProvisionClauseType::AuthorityBoundary, 'Subject to the provisions of Book II of Republic Act 7160, the Sangguniang Bayan shall enact an Ordinance authorizing the issuance of license to operate a cockpit in the Municipality of Ipil pursuant to the provisions of Article 99 Section (a) Subsection (3) Paragraph (v) of the rules and Regulations Implementing the Local Government Code of 1991.', 'Candidate authority chain: a Sangguniang Bayan ordinance authorizes cockpit license issuance subject to the cited national law and implementing rules.', 'The authorizing ordinance, current national-law reference, issuing office, effective version, and relationship to the franchise require legal and municipal confirmation.', ['candidate_authority' => 'Sangguniang Bayan', 'external_authority' => 'Republic Act 7160 and implementing rules']),
            $this->policyBoundaryClause(3, 'MRC-3B-03-NEW-ZONING-CLEARANCE', RevenueCodeProvisionClauseType::DocumentaryRequirement, 'New License: Zoning/Locational Clearance issued by the Zoning Administrator.', 'Candidate new-license evidence: zoning or locational clearance issued by the Zoning Administrator.', 'Applicability, document identity, validity, verification, and sufficiency require accepted licensing procedure.', ['application_type' => 'new', 'candidate_issuer' => 'Zoning Administrator']),
            $this->policyBoundaryClause(4, 'MRC-3B-03-NEW-BUILDING-PLAN', RevenueCodeProvisionClauseType::DocumentaryRequirement, 'New License: Building Plan and Design duly approved by the Municipal Engineer.', 'Candidate new-license evidence: building plan and design approved by the Municipal Engineer.', 'Approval identity, version, site linkage, verification, and sufficiency require accepted licensing procedure.', ['application_type' => 'new', 'candidate_issuer' => 'Municipal Engineer']),
            $this->policyBoundaryClause(5, 'MRC-3B-03-NEW-SANITARY-CLEARANCE', RevenueCodeProvisionClauseType::DocumentaryRequirement, 'New License: Sanitary Permit/Clearance issued by the Municipal Health Office.', 'Candidate new-license evidence: sanitary permit or clearance issued by the Municipal Health Office.', 'Document identity, validity, verification, and sufficiency require accepted licensing procedure.', ['application_type' => 'new', 'candidate_issuer' => 'Municipal Health Office']),
            $this->policyBoundaryClause(6, 'MRC-3B-03-NEW-TAX-FEE-PAYMENT', RevenueCodeProvisionClauseType::DocumentaryRequirement, 'New License: Payments of the required city/municipal taxes and fees.', 'Candidate new-license evidence: payment of required city or municipal taxes and fees.', 'The source says city/municipal; jurisdiction, charge catalog, payment sufficiency, receipt evidence, and sequence require reconciliation.', ['application_type' => 'new', 'known_source_wording' => 'city/municipal']),
            $this->policyBoundaryClause(7, 'MRC-3B-03-RENEWAL-ENGINEER-CERTIFICATION', RevenueCodeProvisionClauseType::DocumentaryRequirement, 'Annual Renewal: Certification from the Municipal Engineer to the effect that such cockpit is free from material, structural or other physical hazards.', 'Candidate annual-renewal evidence: Municipal Engineer certification that the cockpit is free from material, structural, or other physical hazards.', 'Inspection method, certification identity, validity, verification, and sufficiency require accepted renewal procedure.', ['application_type' => 'renewal', 'candidate_issuer' => 'Municipal Engineer']),
            $this->policyBoundaryClause(8, 'MRC-3B-03-RENEWAL-SANITARY-CLEARANCE', RevenueCodeProvisionClauseType::DocumentaryRequirement, 'Annual Renewal: Sanitary Permit/Clearance issued by the Municipal Health Office.', 'Candidate annual-renewal evidence: sanitary permit or clearance issued by the Municipal Health Office.', 'Document identity, validity, verification, and sufficiency require accepted renewal procedure.', ['application_type' => 'renewal', 'candidate_issuer' => 'Municipal Health Office']),
            $this->policyBoundaryClause(9, 'MRC-3B-03-RENEWAL-TAX-FEE-PAYMENT', RevenueCodeProvisionClauseType::DocumentaryRequirement, 'Annual Renewal: Payments of the required city/municipal taxes and fees.', 'Candidate annual-renewal evidence: payment of required city or municipal taxes and fees.', 'The source says city/municipal; jurisdiction, charge catalog, payment sufficiency, receipt evidence, and sequence require reconciliation.', ['application_type' => 'renewal', 'known_source_wording' => 'city/municipal']),
            $this->policyBoundaryClause(10, 'MRC-3B-04-MAYOR-REGISTRATION', RevenueCodeProvisionClauseType::AuthorityBoundary, 'Cockpit in the Municipality of Ipil after Having been granted authority to operate by the Sangguniang Bayan shall register at the Office of the Municipal Mayor.', 'Candidate sequence: after Sangguniang Bayan operating authority, register the cockpit with the Office of the Municipal Mayor.', 'Authority evidence, registration workflow, responsible actor, record identity, and relationship to franchise and license require accepted procedure.', ['candidate_prerequisite_authority' => 'Sangguniang Bayan', 'candidate_registration_office' => 'Office of the Municipal Mayor']),
            $this->policyBoundaryClause(11, 'MRC-3B-04-REGISTRATION-CERTIFICATE', RevenueCodeProvisionClauseType::PermitRequirement, 'No cockpits shall be allowed to operate without the proper registration certificate.', 'Candidate operating prerequisite: a proper cockpit registration certificate.', 'Certificate identity, issuer, validity, public meaning, replacement, suspension, and enforcement require accepted policy.', ['candidate_timing' => 'before_operation']),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3B-05-06-PAYMENT-APPLICABILITY', [
            $this->policyBoundaryClause(1, 'MRC-3B-05-FILING-FEE-UPON-APPLICATION', RevenueCodeProvisionClauseType::PaymentTiming, 'The application filling fee shall be payable to the Municipal Treasurer upon application for a permit or license to operate and maintain cockpits.', 'Candidate payment timing: pay the application filing fee to the Municipal Treasurer upon permit or license application.', 'Filing-versus-filling terminology, payment initiation, non-refundable treatment, receipt evidence, and franchise/license sequence require accepted policy.', ['candidate_collector' => 'Municipal Treasurer', 'candidate_timing' => 'upon_application']),
            $this->policyBoundaryClause(2, 'MRC-3B-05-REGISTRATION-FEE-PAYMENT', RevenueCodeProvisionClauseType::PaymentTiming, 'The cockpit registration fee shall be also payable upon application for a permit before a cockpit can operate and shall be secured within the first twenty days of January of each year in case of renewal;', 'Candidate payment timing: pay the named cockpit registration fee upon application before operation and within the first 20 days of January for renewal.', 'Section 3B.02 names an annual cockpit permit fee rather than a registration fee; amount identity, first-year application, renewal calendar, extensions, late consequences, and receipt evidence require reconciliation.', ['candidate_timing' => ['upon_application_before_operation', 'renewal_first_20_days_of_january'], 'known_terminology_conflict' => ['annual_cockpit_permit_fee', 'cockpit_registration_fee']]),
            $this->policyBoundaryClause(3, 'MRC-3B-05-PERSONNEL-FEE-PAYMENT', RevenueCodeProvisionClauseType::PaymentTiming, 'The permit fees on cockpit personnel shall be paid before they participate in a cockfight and shall be paid annually upon renewal of the permit on the birth month of permittee.', 'Candidate personnel timing: pay before participation and renew annually during the permittee birth month.', 'Personnel identity, permit scope, participation control, birth-month due date, grace periods, late consequences, and receipt evidence require accepted policy.', ['candidate_timing' => ['before_participation', 'annual_birth_month_renewal']]),
            $this->policyBoundaryClause(4, 'MRC-3B-06-PD-449-APPLICABILITY', RevenueCodeProvisionClauseType::AuthorityBoundary, 'The provision of PD 449, otherwise known as the Cockfighting Law of 1974 ... shall apply to all matters regarding the operation of cockpits and the holding of cockfights in this Municipality.', 'Candidate external authority: Presidential Decree 449 applies to cockpit operation and cockfight holding.', 'Current legal force, amendments, precedence, incorporated requirements, and enforcement authority require legal validation.', ['external_authority' => 'Presidential Decree 449']),
            $this->policyBoundaryClause(5, 'MRC-3B-06-PD-1802-APPLICABILITY', RevenueCodeProvisionClauseType::AuthorityBoundary, 'PD 1802 (Creating the Philippine Game Fowl Commission) ... shall apply to all matters regarding the operation of cockpits and the holding of cockfights in this Municipality.', 'Candidate external authority: Presidential Decree 1802 applies to cockpit operation and cockfight holding.', 'Current legal force, institutional succession, amendments, incorporated requirements, and enforcement authority require legal validation.', ['external_authority' => 'Presidential Decree 1802']),
            $this->policyBoundaryClause(6, 'MRC-3B-06-OTHER-LAWS-APPLICABILITY', RevenueCodeProvisionClauseType::AuthorityBoundary, 'Such other pertinent laws shall apply to all matters regarding the operation of cockpits and the holding of cockfights in this Municipality.', 'Candidate external authority boundary: other pertinent laws may govern cockpit operation and cockfight holding.', 'The applicable-law catalog, precedence, effective versions, and operational consequences are not enumerated and require legal validation.', ['external_authority' => 'other pertinent laws']),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3B-07-OPERATIONS', [
            $this->policyBoundaryClause(1, 'MRC-3B-07-FILIPINO-OWNERSHIP', RevenueCodeProvisionClauseType::Eligibility, 'Only Filipino citizens not otherwise inhibited by existing laws shall be allowed to own, manage and operate cockpits.', 'Candidate eligibility: only Filipino citizens not otherwise prohibited by law may own, manage, or operate cockpits.', 'Natural-person versus entity ownership, citizenship evidence, prohibited-person rules, beneficial ownership, role scope, and current law require validation.', ['candidate_roles' => ['owner', 'manager', 'operator']]),
            $this->policyBoundaryClause(2, 'MRC-3B-07-COOPERATIVE-CAPITALIZATION', RevenueCodeProvisionClauseType::Eligibility, 'Cooperative capitalization is encouraged.', 'Candidate policy observation: cooperative capitalization is encouraged but not stated as a mandatory eligibility condition.', 'Legal effect, cooperative identity, capitalization evidence, incentives, and whether the sentence is merely aspirational require municipal confirmation.', ['candidate_requirement_level' => 'encouraged']),
            $this->policyBoundaryClause(3, 'MRC-3B-07-COCKPIT-NUMBER-AUTHORITY', RevenueCodeProvisionClauseType::AuthorityBoundary, 'The Sangguniang Bayan shall determine the number of cockpits to be allowed on this municipality.', 'Candidate authority: the Sangguniang Bayan determines the number of allowed cockpits.', 'Current authorized count, resolution or ordinance evidence, allocation procedure, vacancies, transfers, and enforcement require municipal policy.', ['candidate_authority' => 'Sangguniang Bayan']),
            $this->policyBoundaryClause(4, 'MRC-3B-07-ZONING-SITE', RevenueCodeProvisionClauseType::OperatingRestriction, 'Cockpits shall be constructed and operated within the appropriate areas as prescribed in the Zoning Ordinance of the municipality of Ipil.', 'Candidate siting restriction: construction and operation only in areas allowed by the Ipil Zoning Ordinance.', 'Current zoning version, parcel mapping, locational evidence, nonconforming use, variance, and enforcement require accepted procedure.', ['external_authority' => 'Municipality of Ipil Zoning Ordinance']),
            $this->policyBoundaryClause(5, 'MRC-3B-07-PROXIMITY-RESTRICTION', RevenueCodeProvisionClauseType::OperatingRestriction, 'The Municipal Mayor shall see to it that no cockpits are constructed within or near existing residential or commercial areas, hospitals, school, churches or other public building.', 'Candidate siting restriction: exclude locations within or near the listed sensitive places under Municipal Mayor oversight.', 'The source includes commercial areas and does not define within or near; distance, measurement, land-use evidence, exceptions, and enforcement require policy.', ['candidate_authority' => 'Municipal Mayor', 'source_sensitive_places' => ['residential areas', 'commercial areas', 'hospitals', 'school', 'churches', 'other public building']]),
            $this->policyBoundaryClause(6, 'MRC-3B-07-PD449-TRANSITION', RevenueCodeProvisionClauseType::OperatingRestriction, 'Owners, lessees, or operators of cockpits which are now in existence and do not conform to this requirements are given three (3) years from date of effectivity of Presidential Degree No. 449 to comply herewith.', 'Candidate historical transition: nonconforming existing cockpits received three years from the effectivity of Presidential Decree 449 to comply.', 'The transition is historical and likely elapsed; current legal effect, grandfathered records, compliance evidence, and source Degree/Decree wording require legal validation.', ['source_term_years' => 3, 'external_authority' => 'Presidential Decree 449', 'candidate_historical_rule' => true]),
            $this->policyBoundaryClause(7, 'MRC-3B-07-BUILDING-PERMIT-AUTHORITY', RevenueCodeProvisionClauseType::AuthorityBoundary, 'Approval or issuance of building permits for the construction of cockpits shall be made by the Municipal Engineer in accordance with the Building Ordinance of municipality of Ipil, or engineering laws and practices.', 'Candidate authority: the Municipal Engineer approves or issues cockpit building permits under local building and engineering authorities.', 'Current issuing authority, code version, plan-review procedure, evidence, inspections, and relationship to national building law require validation.', ['candidate_authority' => 'Municipal Engineer']),
            $this->policyBoundaryClause(8, 'MRC-3B-07-LICENSED-COCKPIT-ONLY', RevenueCodeProvisionClauseType::OperatingRestriction, 'Except as provided in this Ordinance, cock fighting shall be allowed only in licensed cockpits.', 'Candidate restriction: cockfighting occurs only in licensed cockpits except for stated ordinance exceptions.', 'License validity, exception catalog, event-location evidence, enforcement, and relationship to registration and franchise require accepted policy.', ['candidate_location_requirement' => 'licensed_cockpit']),
            $this->policyBoundaryClause(9, 'MRC-3B-07-SUNDAY-HOLIDAY-SCHEDULE', RevenueCodeProvisionClauseType::OperatingRestriction, 'Cock fighting shall be allowed only in licensed cockpits during Sundays and legal holidays.', 'Candidate ordinary schedule: Sundays and legal holidays in licensed cockpits, subject to other prohibitions.', 'Holiday authority, conflicts with prohibited dates, hours, event identity, and permit requirements require accepted scheduling policy.', ['candidate_days' => ['sundays', 'legal_holidays']]),
            $this->policyBoundaryClause(10, 'MRC-3B-07-LOCAL-FIESTA-THREE-DAYS', RevenueCodeProvisionClauseType::OperatingRestriction, 'Cock fighting shall be allowed ... during local fiestas for not more than three (3) days.', 'Candidate fiesta schedule: no more than three days during a local fiesta.', 'Fiesta designation, start/end dates, consecutive-day treatment, event identity, hours, and authorization evidence require accepted policy.', ['candidate_maximum_days' => 3]),
            $this->policyBoundaryClause(11, 'MRC-3B-07-FAIR-CARNIVAL-EXPOSITION', RevenueCodeProvisionClauseType::OperatingRestriction, 'It may be held during municipal, agricultural, commercial or industrial fair, carnival or exposition for similar period of three (3) days upon resolution of the municipality where such fair, carnival or exposition shall be allowed within the month of a local fiesta or more than two more occasions a year in the same city.', 'Candidate special schedule: up to three days for listed events upon municipal resolution, subject to a malformed source condition concerning the local-fiesta month and two additional occasions.', 'The final condition is grammatically defective and says city; event eligibility, resolution authority, three-day period, fiesta-month relationship, annual frequency, and jurisdiction require reconciliation.', ['candidate_events' => ['municipal fair', 'agricultural fair', 'commercial fair', 'industrial fair', 'carnival', 'exposition'], 'candidate_maximum_days' => 3, 'known_source_wording' => ['more than two more occasions', 'same city']]),
            $this->policyBoundaryClause(12, 'MRC-3B-07-PROHIBITED-DATES', RevenueCodeProvisionClauseType::OperatingRestriction, 'No cock fighting shall be held on December (30) Rizal Day), December 25 (Christmas Day), November 30 (National Heroes Day),June 12 (Philippine Independence Day), Holy Thursday, Good Friday, Election or Referendum Day and during Registration Day for such Election or Referendum Day.', 'Candidate prohibited schedule: no cockfighting on the listed fixed and movable dates or election-related days.', 'The source mislabels November 30, contains punctuation defects, and may conflict with legal holidays; authoritative calendars, election notices, date precedence, and current law require validation.', ['source_dates' => ['December 30', 'December 25', 'November 30', 'June 12', 'Holy Thursday', 'Good Friday', 'Election or Referendum Day', 'Registration Day for Election or Referendum'], 'known_source_defect' => 'November 30 labeled National Heroes Day']),
            $this->policyBoundaryClause(13, 'MRC-3B-07-SPECIAL-PURPOSE-AUTHORITY', RevenueCodeProvisionClauseType::AuthorityBoundary, 'Subject to the preceding subsection hereof, the Municipal Mayor or his authorized representative may also allow the holding of cock fighting for the entertainment of foreign dignitaries or for tourist, or for returning Filipinos, commonly known as “Balikbayan”, or for the support of national fund raising campaign for charitable purposes as may be authorized by the Office of the Municipal Mayor upon resolution of the Sangguniang Bayan, in licensed cockpits or in playgrounds or parks.', 'Candidate special-purpose authority: Mayor or authorized representative approval plus a Sangguniang Bayan resolution for listed tourism, returning-Filipino, or national charitable purposes at stated venues.', 'Actor authority, resolution prerequisite, eligible purpose/person, venue, event permit, evidence, national campaign authorization, and relationship to the preceding restriction require accepted policy.', ['candidate_authorities' => ['Municipal Mayor or authorized representative', 'Sangguniang Bayan'], 'candidate_venues' => ['licensed cockpits', 'playgrounds', 'parks']]),
            $this->policyBoundaryClause(14, 'MRC-3B-07-SPECIAL-PURPOSE-FREQUENCY', RevenueCodeProvisionClauseType::OperatingRestriction, 'Provided that this privilege shall be extended for only on time, for a period not exceeding three, within a year.', 'Candidate boundary: the source appears to limit the privilege to one time and a period not exceeding three within a year, but omits the unit and contains a wording defect.', '“Only on time” and “not exceeding three” are incomplete; occurrence count, day unit, rolling/calendar year, event scope, and renewal require authoritative interpretation.', ['known_source_wording' => ['only on time', 'period not exceeding three'], 'missing_unit' => true]),
            $this->policyBoundaryClause(15, 'MRC-3B-07-OTHER-GAMES-PROHIBITED', RevenueCodeProvisionClauseType::OperatingRestriction, 'No gambling of any kind shall be permitted on the premises of the cockpit or place of cock fighting during cockpits. The owner, manager or lessee of such cockpit and the violators of this injunction shall be criminally liable under the provisions of Section 49 hereof.', 'Candidate restriction: prohibit other gambling on cockpit or cockfight premises during the stated event, with liability assigned to listed actors and violators.', '“During cockpits” is defective wording; prohibited activity classification, premises/time scope, actor responsibility, Section 49 reference, reporting, and enforcement require legal validation.', ['source_cross_reference' => 'Section 49', 'candidate_responsible_roles' => ['owner', 'manager', 'lessee', 'violator']]),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3B-08-PENALTIES', [
            $this->policyBoundaryClause(1, 'MRC-3B-08-ENUMERATED-ACTOR-PENALTY', RevenueCodeProvisionClauseType::Penalty, 'By prison correctional in its maximum period and a fine of TWO THOUSAND PESOS (Php2,000.00), with subsidiary imprisonment in case of insolvency, when the offender if the financier, owner, manager or operator of a cockpit, or the gaffer, referee or bet taker in cockfights, or the offender is guilty of allowing, promoting or participating in any other kind of gambling in the premises of cockpits during cockfights.', 'Candidate source penalty: PHP 2,000.00 plus stated imprisonment consequences for enumerated actors or other-gambling conduct.', 'Penal execution belongs to lawful investigation and court authority, not assessment calculation; current legal force, offense elements, actor classification, procedure, and sentencing require legal validation.', ['source_offender_roles' => ['financier', 'owner', 'manager', 'operator', 'gaffer', 'referee', 'bet taker'], 'candidate_imprisonment' => 'prison correctional maximum period with subsidiary imprisonment in case of insolvency'], 200_000),
            $this->policyBoundaryClause(2, 'MRC-3B-08-OTHER-OFFENDER-PENALTY', RevenueCodeProvisionClauseType::Penalty, 'By prison correctional or fine or not less than SIX HUNDRED PESOS (Php600.00) or more than TWO THOUSAND PESOS (Php2,000.00) or both, such imprisonment and fine at the discretion of the court, with subsidiary imprisonment in case of insolvency, in case of any other offender.', 'Candidate source range: PHP 600.00 minimum and PHP 2,000.00 maximum, with stated imprisonment or both at court discretion for other offenders.', 'Penal execution belongs to court authority, not assessment calculation; the source grammar, current legal force, offense elements, procedure, and sentencing require legal validation.', ['candidate_minimum_fine_cents' => 60_000, 'candidate_maximum_fine_cents' => 200_000, 'candidate_authority' => 'court discretion', 'candidate_imprisonment' => 'prison correctional with subsidiary imprisonment in case of insolvency'], 200_000, isCeiling: true),
        ]);
    }

    private function seedSpecialCockfightingArticleCClauses(): void
    {
        $this->persistFixedFeeScheduleClauses('MRC-3C-01-SPECIAL-DERBY-FEES', [
            [
                'MRC-3C-01-NATIONAL-LOCAL-DERBY',
                'Special Cockfighting',
                'National/Local Derby',
                '2,000.00/day',
                200_000,
                'National Derby is not defined in Article B; National/Local classification, special-event eligibility, permit scope, event-day counting, and operational acceptance require municipal policy.',
                RevenueCodeProvisionClauseType::DependentRate,
                'National/Local Derby - P 2,000.00/day.',
                ['candidate_unit' => 'event_day', 'candidate_event_types' => ['national_derby', 'local_derby']],
            ],
            [
                'MRC-3C-01-INTERNATIONAL-DERBY',
                'Special Cockfighting',
                'International Derby',
                '4,000.00/day',
                400_000,
                'Section 3C.02 expressly excludes international derbies from these fees; the direct contradiction must be resolved by authorized municipal interpretation before execution.',
                RevenueCodeProvisionClauseType::DependentRate,
                'International Derby - P 4,000.00/day.',
                ['candidate_unit' => 'event_day', 'candidate_event_types' => ['international_derby'], 'contradicted_by_clause' => 'MRC-3C-02-INTERNATIONAL-DERBY-EXCLUSION'],
            ],
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3C-02-EXCLUSIONS', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-3C-02-REGULAR-COCKFIGHT-EXCLUSION',
                type: RevenueCodeProvisionClauseType::Exemption,
                sourceText: 'Regular cockfights i.e., those held during Sunday, legal holidays and local fiestas ... shall be excluded from the payment of fees herein imposed.',
                candidateInterpretation: 'Candidate exclusion: regular cockfights held on Sundays, legal holidays, or during local fiestas are outside the Article C special-permit fees.',
                executionBlocker: 'Regular-versus-special event classification, authoritative calendars, local-fiesta dates, overlap with Article B schedules, and permit evidence require accepted policy.',
                metadata: ['candidate_excluded_schedules' => ['sunday', 'legal_holiday', 'local_fiesta']],
            ),
            $this->policyBoundaryClause(
                sequence: 2,
                code: 'MRC-3C-02-INTERNATIONAL-DERBY-EXCLUSION',
                type: RevenueCodeProvisionClauseType::Exemption,
                sourceText: 'International derbies shall be excluded from the payment of fees herein imposed.',
                candidateInterpretation: 'Candidate exclusion: international derbies are outside the Article C special-permit fees.',
                executionBlocker: 'This sentence directly contradicts the PHP 4,000.00/day International Derby row in Section 3C.01; no financial interpretation is authorized until the Municipality resolves the conflict.',
                metadata: ['contradicts_clause' => 'MRC-3C-01-INTERNATIONAL-DERBY', 'known_direct_source_contradiction' => true],
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3C-03-PAYMENT-TIMING', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-3C-03-PAY-BEFORE-EVENT',
                type: RevenueCodeProvisionClauseType::PaymentTiming,
                sourceText: 'The fees herein imposed shall be payable to the City/Municipal Treasurer before the special cockfights and derbies can lawfully held.',
                candidateInterpretation: 'Candidate timing: pay the applicable special-cockfighting fee before the event or derby may lawfully be held.',
                executionBlocker: 'The source says City/Municipal Treasurer and omits “be” in the final phrase; collector authority, payment and permit sequence, event identity, receipt evidence, cancellation, and refund treatment require accepted procedure.',
                metadata: ['source_collector' => 'City/Municipal Treasurer', 'candidate_timing' => 'before_event', 'known_source_wording' => 'can lawfully held'],
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3C-04-APPLICABILITY', [
            $this->policyBoundaryClause(1, 'MRC-3C-04-PD-449-APPLICABILITY', RevenueCodeProvisionClauseType::AuthorityBoundary, 'The provision of PD 449, otherwise known as the Cockfighting Law of 1974 ... shall apply to all matters regarding the operation of cockpits and the holding of cockfights in this municipality.', 'Candidate external authority: Presidential Decree 449 applies to cockpit operation and cockfight holding.', 'Current legal force, amendments, precedence, incorporated requirements, and enforcement authority require legal validation.', ['external_authority' => 'Presidential Decree 449']),
            $this->policyBoundaryClause(2, 'MRC-3C-04-PD-1802-APPLICABILITY', RevenueCodeProvisionClauseType::AuthorityBoundary, 'PD 1802 (Creating the Philippine Game fowl Commission) ... shall apply to all matters regarding the operation of cockpits and the holding of cockfights in this municipality.', 'Candidate external authority: Presidential Decree 1802 applies to cockpit operation and cockfight holding.', 'Current legal force, institutional succession, amendments, incorporated requirements, and enforcement authority require legal validation.', ['external_authority' => 'Presidential Decree 1802', 'source_institution' => 'Philippine Game fowl Commission']),
            $this->policyBoundaryClause(3, 'MRC-3C-04-OTHER-LAWS-APPLICABILITY', RevenueCodeProvisionClauseType::AuthorityBoundary, 'Such other pertinent laws shall apply to all matters regarding the operation of cockpits and the holding of cockfights in this municipality.', 'Candidate external authority boundary: other pertinent laws may govern cockpit operation and cockfight holding.', 'The applicable-law catalog, precedence, effective versions, and operational consequences are not enumerated and require legal validation.', ['external_authority' => 'other pertinent laws']),
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

    /** @return array<string, mixed> */
    private function enterpriseScaleClause(
        int $sequence,
        string $codeSuffix,
        string $scale,
        string $sourceAssetLimit,
        string $sourceWorkforce,
        ?int $candidateAssetFromCents,
        ?int $candidateAssetToCents,
    ): array {
        return $this->policyBoundaryClause(
            sequence: $sequence,
            code: 'MRC-3A-02-ENTERPRISE-'.$codeSuffix,
            type: RevenueCodeProvisionClauseType::Eligibility,
            sourceText: $scale.' — Asset Limit: '.$sourceAssetLimit.'; Workforce: '.$sourceWorkforce.'.',
            candidateInterpretation: 'Candidate enterprise scale: '.$scale.' using the source asset-limit and workforce descriptions.',
            executionBlocker: 'Asset boundary inclusivity overlaps at stated thresholds, the relationship between asset and workforce criteria is not defined, and source workforce wording requires municipal reconciliation.',
            metadata: [
                'enterprise_scale' => $scale,
                'source_asset_limit' => $sourceAssetLimit,
                'source_workforce' => $sourceWorkforce,
                'candidate_asset_from_cents' => $candidateAssetFromCents,
                'candidate_asset_to_cents' => $candidateAssetToCents,
            ],
        );
    }

    /**
     * @param  list<array{0: string, 1: string, 2: ?string, 3: ?string, 4: ?int, 5?: string, 6?: RevenueCodeProvisionClauseType, 7?: string, 8?: array<string, mixed>}>  $rows
     */
    private function persistFixedFeeScheduleClauses(string $provisionCode, array $rows): void
    {
        $clauses = [];

        foreach ($rows as $index => $row) {
            [$code, $category, $classification, $sourceValueText, $amountCents] = $row;
            $executionBlocker = $row[5] ?? 'Business-category, enterprise-scale, application-type, establishment, and operational schedule mapping require accepted municipal policy.';
            $type = $row[6] ?? RevenueCodeProvisionClauseType::DependentRate;
            $sourceRowIsUnlabeled = str_ends_with($code, '-UNLABELED');
            $sourceClassification = match (true) {
                $classification !== null => ' — '.$classification,
                $sourceRowIsUnlabeled => ' — [unlabeled source row]',
                default => '',
            };
            $sourceValue = $sourceValueText === null ? '[no aligned amount in source]' : 'P '.$sourceValueText;
            $sourceText = $row[7] ?? $category.$sourceClassification.' — '.$sourceValue.'.';
            $candidateInterpretation = match (true) {
                $type === RevenueCodeProvisionClauseType::DocumentaryRequirement => 'Candidate evidence requirement: a covered retail outlet holds a DOE Certificate of Compliance before operation.',
                $amountCents === null => 'No executable numeric interpretation is recorded for this source row.',
                default => 'Candidate source amount: PHP '.number_format($amountCents / 100, 2, '.', ',').'.',
            };

            $clauses[] = $this->policyBoundaryClause(
                sequence: $index + 1,
                code: $code,
                type: $type,
                sourceText: $sourceText,
                candidateInterpretation: $candidateInterpretation,
                executionBlocker: $executionBlocker,
                metadata: array_merge([
                    'business_category' => $category,
                    'source_classification' => $classification,
                    'source_value_text' => $sourceValueText,
                    'source_row_is_unlabeled' => $sourceRowIsUnlabeled,
                    'source_amount_is_missing' => $sourceValueText === null && $type === RevenueCodeProvisionClauseType::DependentRate,
                ], $row[8] ?? []),
                amountCents: $amountCents,
            );
        }

        $this->persistPolicyBoundaryClauses($provisionCode, $clauses);
    }

    /** @return array<string, mixed> */
    private function definitionClause(int $sequence, string $codeSuffix, string $sourceText, string $candidateInterpretation): array
    {
        return $this->policyBoundaryClause(
            sequence: $sequence,
            code: 'MRC-3B-01-'.$codeSuffix,
            type: RevenueCodeProvisionClauseType::Definition,
            sourceText: $sourceText,
            candidateInterpretation: $candidateInterpretation,
            executionBlocker: 'The definition remains non-executable until terminology, role identity, evidence, and current governing-law alignment are accepted.',
            metadata: ['definition_code' => strtolower(str_replace('-', '_', $codeSuffix))],
        );
    }

    /** @return array<string, mixed> */
    private function pilThresholdClause(
        int $sequence,
        string $codeSuffix,
        string $sourceItem,
        string $classification,
        string $sourceValueText,
        ?int $amountCents,
        ?string $normalizationQuestion = null,
    ): array {
        return $this->policyBoundaryClause(
            sequence: $sequence,
            code: 'MRC-2F-01-PIL-'.$codeSuffix,
            type: RevenueCodeProvisionClauseType::PresumptiveIncomeThreshold,
            sourceText: $classification.' — '.$sourceValueText,
            candidateInterpretation: $amountCents === null
                ? 'No numeric candidate is recorded from the malformed source value.'
                : 'Candidate minimum gross sales or receipts: PHP '.number_format($amountCents / 100, 2, '.', ',').'.',
            executionBlocker: $normalizationQuestion ?? 'The amount remains non-executable until classification mapping, PIL use, effective version, and municipal acceptance are established.',
            metadata: [
                'source_item' => $sourceItem,
                'classification' => $classification,
                'source_value_text' => $sourceValueText,
                'normalization_question' => $normalizationQuestion,
            ],
            amountCents: $amountCents,
        );
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
