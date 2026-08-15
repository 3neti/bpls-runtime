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
use Illuminate\Support\Str;

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
                code: 'MRC-3D-01-DEFINITIONS',
                section: 'Section 3D.01',
                title: 'Astray-animal, place, and large-cattle definitions',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance defines astray animal, public place, private place, and large cattle for the impounding article.',
                notes: 'Animal classification, control and possession evidence, public/private place boundaries, property-owner identity, and the relationship to other animal laws require operational and legal reconciliation.',
                metadata: ['chapter' => 3, 'article' => 'D', 'known_ambiguities' => ['animal_classification', 'control_and_possession_evidence', 'place_boundary', 'property_owner_identity', 'other_animal_law_alignment']],
            ),
            $this->provision(
                code: 'MRC-3D-02-IMPOUNDING-EXPENSES',
                section: 'Section 3D.02',
                title: 'Astray-animal impounding expense charge',
                type: RevenueCodeProvisionType::FixedFee,
                excerpt: 'The ordinance imposes actual incurred expenses during impounding for each day or fraction thereof on each head of large cattle and other animals found astray.',
                notes: 'The source provides no fixed amount or expense catalog. Eligible costs, evidence, daily/fraction allocation, per-head treatment, approval, accounting, and rounding require municipal reconciliation; no numeric charge is executable.',
                metadata: ['chapter' => 3, 'article' => 'D', 'known_ambiguities' => ['eligible_expense_catalog', 'expense_evidence', 'day_fraction_allocation', 'per_head_allocation', 'cost_approval', 'accounting_and_rounding']],
            ),
            $this->provision(
                code: 'MRC-3D-03-RELEASE-PAYMENT',
                section: 'Section 3D.03',
                title: 'Impounding-fee payment before release',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance requires payment of the impounding fee to the Municipal Treasurer before release of the animal to its owner.',
                notes: 'Ownership proof, charge finalization, receipt evidence, release authority, disputes, third-party claimants, and terminology alignment with poundage fees require accepted procedure.',
                metadata: ['chapter' => 3, 'article' => 'D', 'known_ambiguities' => ['ownership_proof', 'charge_finalization', 'receipt_evidence', 'release_authority', 'dispute_and_claimant_procedure', 'impounding_or_poundage_terminology']],
            ),
            $this->provision(
                code: 'MRC-3D-04-CUSTODY-NOTICE',
                section: 'Section 3D.04(a)',
                title: 'Astray-animal apprehension, custody, notice, and claim',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance authorizes Barangay Tanods to apprehend and impound astray animals, requires a three-day Municipal Hall notice starting one day after impounding, and requires notice to the Municipal Mayor and Treasurer.',
                notes: 'Authorized personnel, designated facilities, humane custody, record identity, notice timing and content, ownership proof, notifications, and audit evidence require accepted operational procedure.',
                metadata: ['chapter' => 3, 'article' => 'D', 'known_ambiguities' => ['authorized_personnel', 'designated_facility', 'humane_custody', 'animal_record_identity', 'notice_timing_and_content', 'ownership_proof', 'official_notification_and_audit']],
            ),
            $this->provision(
                code: 'MRC-3D-04-AUCTION-DISPOSITION',
                section: 'Section 3D.04(b)',
                title: 'Unclaimed-animal auction, redemption, proceeds, and municipal disposition',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance sends animals unclaimed after five days to public auction, prescribes notice and reporting, permits owner redemption before or during sale, allocates proceeds and surplus, and deems an unsold animal sold to the Municipality after ten days from auction notice.',
                notes: 'Interacting deadlines, auction authority, publication places, valuation, bidding, owner redemption, cost proof, surplus treatment, municipal acquisition, custody after disposition, and legal due process require reconciliation.',
                metadata: ['chapter' => 3, 'article' => 'D', 'known_ambiguities' => ['interacting_notice_deadlines', 'auction_authority_and_places', 'valuation_and_bidding', 'owner_redemption', 'cost_evidence', 'surplus_to_general_fund', 'municipal_acquisition', 'post_disposition_custody', 'due_process']],
            ),
            $this->provision(
                code: 'MRC-3D-05-PENALTIES-DAMAGES',
                section: 'Section 3D.05',
                title: 'Astray-animal property-damage fines and reimbursement',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance prints escalating PHP 100.00, PHP 200.00, and PHP 300.00 fines for animals caught astray and causing plant or property damage, plus payment of actual damage to the property owner.',
                notes: 'Damage trigger and proof, offense counting, owner identity, enforcement authority, due process, collection and receipt treatment, actual-damage valuation, and payment to the property owner require legal and operational reconciliation.',
                metadata: ['chapter' => 3, 'article' => 'D', 'known_ambiguities' => ['damage_trigger_and_proof', 'offense_counting', 'animal_owner_identity', 'enforcement_authority', 'due_process', 'collection_and_receipt', 'damage_valuation', 'property_owner_payment']],
            ),
            $this->provision(
                code: 'MRC-3E-01-DAILY-PERMIT-FEE',
                section: 'Section 3E.01',
                title: 'Circus and parade daily Mayor permit fee',
                type: RevenueCodeProvisionType::FixedFee,
                excerpt: 'The ordinance prints a PHP 500.00 per-day Mayor permit fee for every circus and other parade using banners, floats, or musical instruments in the Municipality.',
                notes: 'Circus and parade classification, whether the listed equipment is conjunctive or illustrative, event identity, event-day counting, partial days, route scope, cancellations, and operational fee acceptance require municipal reconciliation. The printed amount is not executable.',
                metadata: ['chapter' => 3, 'article' => 'E', 'known_ambiguities' => ['circus_or_parade_classification', 'banner_float_instrument_scope', 'event_identity', 'event_day_counting', 'partial_day_treatment', 'route_scope', 'cancellation_and_refund', 'operational_fee_acceptance']],
            ),
            $this->provision(
                code: 'MRC-3E-02-PAYMENT-TIMING',
                section: 'Section 3E.02',
                title: 'Circus and parade application and payment timing',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance makes the fee payable to the Municipal Treasurer upon permit application to the Municipal Mayor at least three days before the scheduled circus or parade, followed by grammatically defective activity-timing text.',
                notes: 'The final source phrase is malformed. Filing and payment sequence, deadline counting, collector and application receiver, late applications, event changes, receipt evidence, permit issuance, and whether payment must precede activity require accepted procedure.',
                metadata: ['chapter' => 3, 'article' => 'E', 'known_ambiguities' => ['malformed_activity_timing_text', 'application_and_payment_sequence', 'three_day_deadline_counting', 'late_application', 'event_change', 'receipt_evidence', 'permit_issuance_sequence']],
            ),
            $this->provision(
                code: 'MRC-3E-03-EXEMPTIONS',
                section: 'Section 3E.03',
                title: 'Circus and parade permit-fee exemptions',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance exempts civic and military parades and religious processions from the Article E permit fee.',
                notes: 'Event classification, mixed-purpose events, organizer and sponsorship evidence, exemption approval, permit-versus-fee treatment, and current legal authority require accepted municipal policy.',
                metadata: ['chapter' => 3, 'article' => 'E', 'known_ambiguities' => ['event_classification', 'mixed_purpose_event', 'organizer_and_sponsorship_evidence', 'exemption_approval', 'permit_or_fee_exemption_scope', 'current_legal_authority']],
            ),
            $this->provision(
                code: 'MRC-3E-04-ADMINISTRATION',
                section: 'Section 3E.04',
                title: 'Parade permit application and public-order administration',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance requires a Municipal Mayor permit and prescribed written application before a parade, and assigns public-order rules and lawful-activity boundaries to the Philippine National Police Station Commander.',
                notes: 'Applicant identity, prescribed form, required information, route and place detail, issuing authority, review and approval, current PNP title and authority, rule publication, boundary definition, inter-office coordination, and enforcement require accepted procedure.',
                metadata: ['chapter' => 3, 'article' => 'E', 'known_ambiguities' => ['applicant_identity', 'prescribed_form', 'required_information', 'route_and_place_detail', 'mayor_permit_authority', 'review_and_approval', 'current_pnp_title_and_authority', 'rule_publication', 'lawful_boundary_definition', 'inter_office_coordination']],
            ),
            $this->provision(
                code: 'MRC-3F-01-DEFINITION',
                section: 'Section 3F.01',
                title: 'Article F large-cattle definition',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'For Article F, the ordinance defines large cattle using a two-year age qualifier and a list of horses, mule or ass, carabao, cow, and other domesticated bovine-family members.',
                notes: 'The source punctuation is defective and its age qualifier differs from the Article D impounding definition. Species, age evidence, article scope, and alignment with external livestock law require accepted interpretation.',
                metadata: ['chapter' => 3, 'article' => 'F', 'known_ambiguities' => ['two_year_age_qualifier_scope', 'mule_ass_punctuation', 'species_classification', 'age_evidence', 'article_d_definition_difference', 'external_livestock_law_alignment']],
            ),
            $this->provision(
                code: 'MRC-3F-02-FEES',
                section: 'Section 3F.02',
                title: 'Large-cattle registration, transfer, and private-brand service fees',
                type: RevenueCodeProvisionType::FixedFee,
                excerpt: 'The ordinance prints service fees of PHP 100.00 for a certificate of ownership, PHP 100.00 for a certificate of transfer, and PHP 200.00 for registration of a private brand, with a once-per-day transfer-fee limitation.',
                notes: 'Fee identity, certificate and brand eligibility, cattle identity, ownership proof, transfer chain, same-day transfer counting, payer, receipt and certificate sequence, numbering, and operational amounts require municipal reconciliation. None of the values is executable.',
                metadata: ['chapter' => 3, 'article' => 'F', 'schedule_clause_count' => 4, 'known_ambiguities' => ['fee_identity', 'certificate_eligibility', 'private_brand_registration', 'cattle_identity', 'ownership_and_transfer_evidence', 'same_day_transfer_counting', 'payer_and_collector', 'receipt_certificate_sequence', 'certificate_numbering', 'operational_amount_acceptance']],
            ),
            $this->provision(
                code: 'MRC-3F-03-PAYMENT',
                section: 'Section 3F.03',
                title: 'Large-cattle registration-fee payment timing',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance requires payment of the registration fee to the Municipal Treasurer upon registration or transfer of ownership of large cattle.',
                notes: 'The section says registration fee while Section 3F.02 lists ownership-certificate, transfer-certificate, and private-brand service fees. Charge identity, event and payment sequence, payer, collector, receipt, and certificate release require accepted procedure.',
                metadata: ['chapter' => 3, 'article' => 'F', 'known_ambiguities' => ['registration_or_service_fee_terminology', 'charge_identity', 'registration_or_transfer_event', 'payment_sequence', 'payer', 'collector', 'receipt_evidence', 'certificate_release']],
            ),
            $this->provision(
                code: 'MRC-3F-04-REGISTRY',
                section: 'Section 3F.04',
                title: 'Large-cattle ownership and transfer registry administration',
                type: RevenueCodeProvisionType::EvidenceRequirement,
                excerpt: 'The ordinance requires registration at age two, registration of ownership and transfers, detailed ownership and transfer registry entries, certificate data, and original title documents before transfer entry or certificate issuance.',
                notes: 'Registry identity, cattle identification, age and ownership proof, record and certificate fields, municipality references, original-document handling, transfer chain, numbering, corrections, duplicates, retention, access, and migration require accepted procedure.',
                metadata: ['chapter' => 3, 'article' => 'F', 'known_ambiguities' => ['registry_identity', 'cattle_identification', 'age_and_ownership_proof', 'record_and_certificate_fields', 'municipality_reference', 'original_document_handling', 'transfer_chain', 'numbering_authority', 'corrections_and_duplicates', 'retention_and_access', 'legacy_registry_migration']],
            ),
            $this->provision(
                code: 'MRC-3F-05-APPLICABILITY',
                section: 'Section 3F.05',
                title: 'Large-cattle registration external-law applicability',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance assigns other large-cattle registration matters to the Revised Administrative Code and other applicable laws, ordinances, rules, and regulations.',
                notes: 'The exact code provisions, current legal force, amendments, precedence, incorporated requirements, institutional authority, and enforcement require legal validation.',
                metadata: ['chapter' => 3, 'article' => 'F', 'known_ambiguities' => ['revised_administrative_code_provisions', 'external_law_currency', 'amendments_and_precedence', 'incorporated_requirements', 'institutional_authority', 'enforcement']],
            ),
            $this->provision(
                code: 'MRC-3G-01-EXCAVATION-FEES',
                section: 'Section 3G.01',
                title: 'Street-excavation permit fees and restoration condition',
                type: RevenueCodeProvisionType::FixedFee,
                excerpt: 'The ordinance prints minimum, per-linear-meter, and per-delay-day excavation charges for concrete, asphalt, gravel, curbs, and gutters, plus a restoration condition.',
                notes: 'The table contains “concentrate pavement,” a malformed `2.00x600m,12sq.m.` measurement, crossing-versus-parallel inconsistencies, and a non-monetary condition in the amount column. A legacy mock Engineering Department fee library separately lists an Excavation Permit at PHP 900.00, but it is not an ordinance schedule implementation or accepted authority. All values remain non-executable.',
                metadata: ['chapter' => 3, 'article' => 'G', 'schedule_clause_count' => 9, 'known_ambiguities' => ['concentrate_or_concrete_wording', 'malformed_minimum_area', 'minimum_fee_scope', 'crossing_or_parallel_measurement', 'width_and_length_measurement', 'curb_gutter_damage_trigger', 'delay_period_and_day_counting', 'restoration_standard', 'legacy_mock_amount_conflict'], 'legacy_mock_evidence' => ['source' => 'apps/admin/lib/data/department-fees.ts:363-370', 'id' => 'eng-007', 'name' => 'Excavation Permit', 'amount_cents' => 90_000, 'department' => 'Engineering Department', 'status' => 'mock_data_not_operational_authority']],
            ),
            $this->provision(
                code: 'MRC-3G-02-PAYMENT-DEPOSIT-FORFEITURE',
                section: 'Section 3G.02',
                title: 'Excavation payment, cash deposit, and restoration forfeiture',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance requires fee payment before excavation, a same-time cash deposit equal to the fee, and forfeiture to the Municipal Government when restoration is not completed within seven days after the excavation purpose is accomplished.',
                notes: 'Fee finalization, application and payment sequence, deposit base, custody and accounting, receipt, restoration completion and acceptance, seven-day trigger, forfeiture authority, notice and due process, partial restoration, refund or release, and dispute handling require accepted municipal policy.',
                metadata: ['chapter' => 3, 'article' => 'G', 'known_ambiguities' => ['fee_finalization', 'application_payment_sequence', 'deposit_base', 'deposit_custody_and_accounting', 'restoration_completion_and_acceptance', 'seven_day_trigger', 'forfeiture_authority_and_due_process', 'partial_restoration', 'deposit_refund_or_release', 'dispute_handling']],
            ),
            $this->provision(
                code: 'MRC-3G-03-ADMINISTRATION',
                section: 'Section 3G.03',
                title: 'Excavation permit, engineering supervision, delay reporting, and public safety',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance requires a Mayor permit stating excavation duration, assigns supervision and width determination to the Municipal Engineer or Building Official, requires delay notice to the Treasurer, and requires public-safety signs.',
                notes: 'Street and excavation scope, permit identity and authority, duration approval, engineer/building-official responsibility, measurement and inspection evidence, delay determination, Treasurer notice, sign standards and placement, the source word “arena,” enforcement, and audit records require accepted procedure.',
                metadata: ['chapter' => 3, 'article' => 'G', 'known_ambiguities' => ['street_and_excavation_scope', 'permit_identity_and_authority', 'duration_approval', 'engineer_or_building_official_responsibility', 'measurement_and_inspection_evidence', 'delay_determination', 'treasurer_notification', 'safety_sign_standard', 'arena_or_area_wording', 'enforcement_and_audit']],
            ),
            $this->provision(
                code: 'MRC-3H-01-IMPLEMENTING-AGENCY',
                section: 'Section 3H.01',
                title: 'Weights-and-measures implementing authority',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance assigns strict enforcement of weights-and-measures practices under Chapter II of Republic Act No. 7394 to the Municipal Treasurer.',
                notes: 'The incorporated national-law provisions, current amendments, division of municipal and national authority, delegation, enforcement procedure, and evidence require legal and operational validation. No operational implementation was found in the studied legacy archive.',
                metadata: ['chapter' => 3, 'article' => 'H', 'known_ambiguities' => ['incorporated_consumer_act_provisions', 'external_law_currency', 'municipal_and_national_authority', 'delegation', 'enforcement_procedure', 'legacy_implementation_not_found']],
            ),
            $this->provision(
                code: 'MRC-3H-02-SEALING-TESTING',
                section: 'Section 3H.02',
                title: 'Testing, calibration, sealing, and continuing inspection',
                type: RevenueCodeProvisionType::EvidenceRequirement,
                excerpt: 'Consumer-transaction instruments are to be tested, calibrated, and sealed every six months by the Municipal Treasurer or an authorized representative, continuously inspected, and marked using an official LGU sticker or sealing wax.',
                notes: 'Instrument scope, consumer-related transaction scope, six-month cycle, annual-license relationship, testing standards, calibration tolerance, official-sealer delegation, payment prerequisite, inspection cadence, sticker control and identity, and evidence retention require accepted policy.',
                metadata: ['chapter' => 3, 'article' => 'H', 'known_ambiguities' => ['covered_instrument_scope', 'consumer_related_transaction_scope', 'six_month_cycle', 'annual_license_cadence_difference', 'testing_and_calibration_standard', 'official_sealer_delegation', 'payment_prerequisite', 'continuous_inspection_cadence', 'sticker_or_wax_control', 'evidence_retention']],
            ),
            $this->provision(
                code: 'MRC-3H-03-FEES',
                section: 'Section 3H.03',
                title: 'Weights-and-measures sealing and licensing fees',
                type: RevenueCodeProvisionType::FixedFee,
                excerpt: 'The ordinance prints annual sealing and licensing amounts for linear measures, four classes of weighing instruments by capacity, bronze wire seals, and requested off-site retesting and resealing.',
                notes: 'Instrument classification, capacity boundary units and inclusivity, per-instrument identity, sealing-versus-licensing fee identity, annual and six-month cadence, bronze-wire-seal applicability, office-versus-field service, gasoline-pump overlap with Article I, payer, and accepted operational amounts require reconciliation. All values remain non-executable.',
                metadata: ['chapter' => 3, 'article' => 'H', 'schedule_clause_count' => 17, 'known_ambiguities' => ['instrument_classification', 'capacity_units_and_boundaries', 'per_instrument_identity', 'sealing_or_licensing_fee_identity', 'annual_or_six_month_cadence', 'bronze_wire_seal_applicability', 'office_or_field_service', 'gasoline_pump_article_i_overlap', 'payer_and_collector', 'operational_amount_acceptance']],
            ),
            $this->provision(
                code: 'MRC-3H-04-PAYMENT-SURCHARGE',
                section: 'Section 3H.04',
                title: 'Payment timing, receipt-license validity, and late retesting surcharge',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'Fees are collected by the Treasurer at sealing before use and by each anniversary; the receipt serves as a one-year license unless the instrument becomes defective, while late retesting incurs a printed 500% surcharge without interest.',
                notes: 'Sealing, payment and use sequence, anniversary computation, six-month testing relationship, receipt and license identity, defect event, retest trigger, 500% arithmetic and base, grace or notice, collection authority, no-interest treatment, and operational enforcement require accepted policy.',
                metadata: ['chapter' => 3, 'article' => 'H', 'known_ambiguities' => ['sealing_payment_use_sequence', 'anniversary_date', 'six_month_testing_relationship', 'receipt_license_identity', 'defect_event', 'retest_trigger', 'five_hundred_percent_surcharge_base', 'grace_and_notice', 'no_interest_treatment', 'enforcement']],
            ),
            $this->provision(
                code: 'MRC-3H-05-PLACE-OF-PAYMENT',
                section: 'Section 3H.05',
                title: 'Weights-and-measures payment situs',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'Business users pay where the business is conducted, while a peddler or itinerant vendor using one instrument pays where the vendor resides.',
                notes: 'Business situs, municipality and residence evidence, peddler or itinerant-vendor identity, one-instrument condition, multiple municipalities, mobile use, duplicate payment prevention, and migration require accepted procedure.',
                metadata: ['chapter' => 3, 'article' => 'H', 'known_ambiguities' => ['business_situs', 'municipality_and_residence_evidence', 'peddler_or_itinerant_vendor_identity', 'one_instrument_condition', 'multi_municipality_use', 'mobile_use', 'duplicate_payment_prevention']],
            ),
            $this->provision(
                code: 'MRC-3H-06-EXEMPTIONS',
                section: 'Section 3H.06',
                title: 'Weights-and-measures fee exemptions',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'Government-work and public-use instruments are tested and sealed free, and dealers holding weights-and-measures instruments intended for sale are listed as exempt.',
                notes: 'Government instrumentality and public-use scope, fee-versus-procedure exemption, dealer identity, sale inventory versus demonstration or use, mixed use, proof, approval, and audit require accepted policy.',
                metadata: ['chapter' => 3, 'article' => 'H', 'known_ambiguities' => ['government_instrumentality_scope', 'public_use_scope', 'fee_or_procedure_exemption', 'dealer_identity', 'inventory_or_operational_use', 'mixed_use', 'proof_and_approval']],
            ),
            $this->provision(
                code: 'MRC-3H-07-ADMINISTRATION',
                section: 'Section 3H.07',
                title: 'License evidence, standards, inspection, confiscation, and destruction',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance governs receipt-license custody and exhibition, annual DOST comparison of secondary standards, periodic inspection, and confiscation and witnessed destruction of irreparable instruments.',
                notes: 'License identity and display, secondary-standard custody and certification, DOST authority and current process, acceptable variation, destruction criteria, periodic inspection cadence, defect and repairability determination, confiscation authority and due process, Provincial Auditor participation, disposition records, and appeals require accepted procedure.',
                metadata: ['chapter' => 3, 'article' => 'H', 'known_ambiguities' => ['license_identity_and_display', 'secondary_standard_custody', 'dost_comparison_and_certificate', 'acceptable_variation', 'standard_destruction_criteria', 'inspection_cadence', 'defect_and_repairability', 'confiscation_authority_and_due_process', 'provincial_auditor_participation', 'disposition_and_appeal']],
            ),
            $this->provision(
                code: 'MRC-3H-08-PROHIBITED-PRACTICES',
                section: 'Section 3H.08',
                title: 'Prohibited weights-and-measures practices and resealing surcharge',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance lists twelve prohibited practices involving unauthorized or counterfeit seals, altered evidence or instruments, expired licenses, false or short measures, misrepresentation, and procuring offenses; it then prints a resealing condition and two-times surcharge.',
                notes: 'Offense elements, intent standards, actor and instrument identity, evidence, authorized-sealer boundary, counterfeit and alteration definitions, license status, prosecution referral, the placement of the resealing rule under paragraph (l), “without penalty except” wording, two-times surcharge base, and relationship to Section 3H.04 require legal and municipal interpretation.',
                metadata: ['chapter' => 3, 'article' => 'H', 'known_ambiguities' => ['offense_elements_and_intent', 'actor_and_instrument_identity', 'authorized_sealer_boundary', 'counterfeit_and_alteration_evidence', 'license_status', 'prosecution_referral', 'resealing_rule_paragraph_placement', 'without_penalty_except_wording', 'two_times_surcharge_base', 'section_3h04_surcharge_relationship']],
            ),
            $this->provision(
                code: 'MRC-3H-09-PENALTIES',
                section: 'Section 3H.09',
                title: 'Weights-and-measures judicial penalties',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance prints separate fine and imprisonment treatments for Section 3H.08 paragraphs (a)-(f), first violation of paragraph (g), and paragraphs (h)-(l).',
                notes: 'The first imprisonment range says not more than one month and not more than six months; paragraph (g) prints a five-year maximum; fine maxima are absent in two bands. Offense counting, conviction authority, current statutory ceilings, judicial discretion, imprisonment wording, and legal validity require counsel and municipal confirmation.',
                metadata: ['chapter' => 3, 'article' => 'H', 'known_ambiguities' => ['contradictory_first_imprisonment_maximum', 'paragraph_g_five_year_maximum', 'missing_fine_maxima', 'first_offense_counting', 'conviction_authority', 'current_statutory_ceiling', 'judicial_discretion', 'legal_validity']],
            ),
            $this->provision(
                code: 'MRC-3H-10-COMPROMISE',
                section: 'Section 3H.10',
                title: 'Treasurer compromise authority for non-fraud violations',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'After apprehension and before court filing, the ordinance authorizes the Municipal Treasurer to impose at least PHP 500.00 as a compromise penalty for Article H violations not involving fraud.',
                notes: 'Apprehension event, fraud exclusion, complaint-filing boundary, Treasurer discretion, minimum with no printed maximum, offense eligibility, taxpayer consent, settlement effect, receipt and accounting, referral, audit, and current legal authority require accepted procedure.',
                metadata: ['chapter' => 3, 'article' => 'H', 'known_ambiguities' => ['apprehension_event', 'fraud_exclusion', 'complaint_filing_boundary', 'treasurer_discretion', 'minimum_without_maximum', 'offense_eligibility', 'taxpayer_consent', 'settlement_effect', 'receipt_and_accounting', 'current_legal_authority']],
            ),
            $this->provision(
                code: 'MRC-3I-01-CALIBRATION-SEALING',
                section: 'Section 3I.01',
                title: 'Dispensing-pump calibration, sealing, outage control, and records',
                type: RevenueCodeProvisionType::EvidenceRequirement,
                excerpt: 'The ordinance requires Ipil liquid-petroleum retail dispensing pumps to be calibrated twice yearly, sealed immediately by the Treasurer or an authorized representative, disabled and marked when uncalibrated, and supported by signed outlet records.',
                notes: 'Pump, nozzle, retail-outlet and brand identity; twice-yearly cadence; calibration standard; sealing authority; off-calibration determination; out-of-order sign and padlock controls; mechanic and countersignatory authority; record format and retention; independent-brand responsibility; and the source reference to “Section 4” require accepted procedure. No operational implementation was found in the studied legacy archive.',
                metadata: ['chapter' => 3, 'article' => 'I', 'known_ambiguities' => ['pump_nozzle_outlet_brand_identity', 'twice_yearly_cadence', 'calibration_standard', 'sealing_authority', 'off_calibration_determination', 'out_of_order_sign_and_padlock', 'mechanic_and_countersignatory_authority', 'record_format_and_retention', 'independent_brand_responsibility', 'section_4_reference', 'legacy_implementation_not_found']],
            ),
            $this->provision(
                code: 'MRC-3I-02-UNDERDELIVERY',
                section: 'Section 3I.02',
                title: 'Dispensing-pump underdelivery tolerance, test protocol, and presumptions',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance prints a tolerance of fifty “millimeters” per ten liters using a DOST-ITDI certified bucket, a three-flow-rate test and average, an underdelivery definition, and actual-use and prima-facie presumptions.',
                notes: 'The source unit “millimeters” is dimensionally inconsistent with liquid quantity and is not normalized to milliliters. Tolerance direction and arithmetic, certified bucket identity and validity, three-run averaging, flow-rate definitions, rounding, test authority, sign-versus-padlock logic, actual-use presumption, broken-seal evidence, rebuttal, chain of custody, and due process require legal, technical, and municipal reconciliation.',
                metadata: ['chapter' => 3, 'article' => 'I', 'known_ambiguities' => ['millimeters_or_milliliters', 'tolerance_direction_and_arithmetic', 'dost_itdi_bucket_identity', 'three_run_average', 'flow_rate_definition', 'rounding', 'test_authority', 'sign_or_padlock_logic', 'actual_use_presumption', 'broken_seal_prima_facie_evidence', 'rebuttal_and_due_process']],
            ),
            $this->provision(
                code: 'MRC-3I-03-SANCTIONS',
                section: 'Section 3I.03',
                title: 'Illegal-trading and underdelivery sanctions',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance prints a PHP 5,000.00 first-offense fine and, for second and succeeding offenses, the same fine plus permit revocation and permanent business closure.',
                notes: 'Illegal trading is not defined in Article I. Actor and outlet liability, underdelivery finding, offense counting, notice, hearing, fine authority and collection, permit identity, revocation authority, closure order, appeal, correction and reopening, interaction with Article H penalties, and current statutory limits require legal and municipal acceptance. No sanction is executable.',
                metadata: ['chapter' => 3, 'article' => 'I', 'known_ambiguities' => ['illegal_trading_definition', 'actor_and_outlet_liability', 'underdelivery_finding', 'offense_counting', 'notice_and_hearing', 'fine_authority_and_collection', 'permit_identity', 'revocation_authority', 'permanent_closure_authority', 'appeal_and_reopening', 'article_h_penalty_overlap', 'current_statutory_limits']],
            ),
            $this->provision(
                code: 'MRC-3I-04-FEES',
                section: 'Section 3I.04',
                title: 'Dispensing-pump registration, sealing, tagging, and calibration fees',
                type: RevenueCodeProvisionType::FixedFee,
                excerpt: 'The ordinance prints PHP 75.00 per nozzle for registration and PHP 125.00 per nozzle for sealing and tagging, under a lead sentence that also names calibration.',
                notes: 'Pump-versus-nozzle identity, registration event and validity, sealing and tagging identity, calibration charge without an aligned amount, whether printed fees are separate or cumulative, cadence, payer, collector, receipt and license evidence, exemptions, Article H off-site retesting overlap, and accepted operational amounts require reconciliation. Both printed values remain non-executable.',
                metadata: ['chapter' => 3, 'article' => 'I', 'schedule_clause_count' => 3, 'known_ambiguities' => ['pump_or_nozzle_identity', 'registration_event_and_validity', 'sealing_and_tagging_identity', 'calibration_amount_missing', 'separate_or_cumulative_fees', 'fee_cadence', 'payer_and_collector', 'receipt_and_license_evidence', 'exemptions', 'article_h_offsite_retesting_overlap', 'operational_amount_acceptance']],
            ),
            $this->provision(
                code: 'MRC-3J-01-FILMING-FEES',
                section: 'Section 3J.01',
                title: 'Location-filming and video-coverage permit fees',
                type: RevenueCodeProvisionType::FixedFee,
                excerpt: 'The ordinance prints permit fees for commercial movies, commercial advertisements, documentary films, and video coverage conducted on location within Ipil, with additional payment required before an extension of filming time.',
                notes: 'Person and responsible production identity, location-filming and territorial-jurisdiction scope, activity classification, film-versus-coverage unit, project and location count, extension duration and additional amount, permit identity, exemptions, cancellation and refund, payer and collector, and accepted operational amounts require reconciliation. No operational implementation was found in the studied legacy archive.',
                metadata: ['chapter' => 3, 'article' => 'J', 'schedule_clause_count' => 6, 'known_ambiguities' => ['person_and_production_identity', 'location_filming_scope', 'territorial_jurisdiction_evidence', 'commercial_movie_advertisement_documentary_classification', 'film_or_coverage_unit', 'project_location_and_day_count', 'extension_duration_and_amount', 'separate_or_cumulative_fees', 'permit_identity', 'exemptions', 'cancellation_and_refund', 'payer_and_collector', 'operational_amount_acceptance', 'legacy_implementation_not_found']],
            ),
            $this->provision(
                code: 'MRC-3J-02-PAYMENT-TIMING',
                section: 'Section 3J.02',
                title: 'Location-filming permit payment timing and Treasurer authority',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance requires payment to the Municipal Treasurer upon application for the Mayor’s Permit seven days before location-filming commences.',
                notes: 'The relationship between the Article J filming permit and a Mayor’s Permit, application and payment sequence, calendar-versus-business days, commencement evidence, late applications, changed dates, extensions, collector delegation, receipt, cancellation, refund, and enforcement require accepted municipal procedure.',
                metadata: ['chapter' => 3, 'article' => 'J', 'known_ambiguities' => ['filming_permit_and_mayors_permit_relationship', 'application_and_payment_sequence', 'calendar_or_business_days', 'commencement_timestamp', 'late_application', 'changed_schedule', 'extension_relationship', 'treasurer_delegation', 'receipt_evidence', 'cancellation_and_refund', 'enforcement']],
            ),
            $this->provision(
                code: 'MRC-3K-01-ANNUAL-EQUIPMENT-FEES',
                section: 'Section 3K.01',
                title: 'Agricultural machinery and heavy-equipment annual permit fees',
                type: RevenueCodeProvisionType::FixedFee,
                excerpt: 'The ordinance prints annual permit fees for agricultural machinery and heavy equipment associated with non-resident operators or equipment rented within Ipil.',
                notes: 'The “non-resident operators ... or equipment renting out” grammar leaves the eligibility logic unclear. Operator, owner, lessor, lessee and equipment identity; residence; rental and municipal-use evidence; annual term; equipment classification; the unpriced other-equipment parent; fleet and duplicate-unit treatment; permit identity; exemptions; payer; collector; and accepted operational amounts require reconciliation. No operational implementation was found in the studied legacy archive.',
                metadata: ['chapter' => 3, 'article' => 'K', 'schedule_clause_count' => 23, 'known_ambiguities' => ['nonresident_or_rental_eligibility_logic', 'operator_owner_lessor_lessee_identity', 'residence_evidence', 'rental_and_municipal_use_evidence', 'annual_term_and_proration', 'equipment_classification', 'singular_plural_source_labels', 'unpriced_other_equipment_parent', 'fleet_and_duplicate_unit_treatment', 'permit_identity', 'exemptions', 'payer_and_collector', 'operational_amount_acceptance', 'legacy_implementation_not_found']],
            ),
            $this->provision(
                code: 'MRC-3K-02-PAYMENT-TIMING',
                section: 'Section 3K.02',
                title: 'Equipment permit payment before rental',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance makes the fee payable before rental of the equipment upon application for a Mayor’s permit.',
                notes: 'Whether payment is triggered by rental, permit application, or both; owner, operator, lessor, lessee and payer identity; rental date; annual permit validity; late or ongoing rentals; equipment substitution; Mayor’s-permit identity; collector; receipt; cancellation; refund; and enforcement require accepted municipal procedure.',
                metadata: ['chapter' => 3, 'article' => 'K', 'known_ambiguities' => ['rental_or_application_trigger', 'actor_and_payer_identity', 'rental_commencement', 'annual_permit_validity', 'late_or_ongoing_rental', 'equipment_substitution', 'mayors_permit_identity', 'collector_and_receipt', 'cancellation_and_refund', 'enforcement']],
            ),
            $this->provision(
                code: 'MRC-3K-03-EQUIPMENT-REGISTRY',
                section: 'Section 3K.03',
                title: 'Treasurer agricultural machinery and heavy-equipment registry',
                type: RevenueCodeProvisionType::EvidenceRequirement,
                excerpt: 'The ordinance requires the Municipal Treasurer to keep a registry of all heavy equipment and agricultural machinery with equipment make and brand plus owner name and address.',
                notes: 'Registry scope uses “all” although the fee scope refers to non-resident operators or rented equipment. Equipment and owner identity, serial and plate identifiers absent from the printed fields, operator and rental linkage, required fields, registration trigger, permit relationship, numbering, corrections, transfers, retirement, retention, privacy, access, and legacy migration require accepted procedure.',
                metadata: ['chapter' => 3, 'article' => 'K', 'known_ambiguities' => ['all_equipment_versus_fee_scope', 'equipment_and_owner_identity', 'missing_serial_plate_identifiers', 'operator_and_rental_linkage', 'registration_trigger', 'permit_relationship', 'numbering', 'corrections_and_transfers', 'retirement_and_disposition', 'retention_privacy_access', 'legacy_registry_migration']],
            ),
            $this->provision(
                code: 'MRC-3K-04-PENALTY',
                section: 'Section 3K.04',
                title: 'Agricultural machinery and heavy-equipment judicial penalty',
                type: RevenueCodeProvisionType::AdministrativeRule,
                excerpt: 'The ordinance prints a PHP 500 to PHP 1,000 fine, one to six months imprisonment, or both, for any Article K violation at the court’s discretion.',
                notes: 'Violation elements, responsible actor, equipment and permit identity, enforcement and referral, conviction, offense counting, fine and imprisonment authority, municipal penalty ceilings, court discretion, notice, hearing, appeal, and current legal validity require counsel and municipal acceptance. No penalty is executable.',
                metadata: ['chapter' => 3, 'article' => 'K', 'known_ambiguities' => ['violation_elements', 'responsible_actor', 'equipment_and_permit_identity', 'enforcement_and_referral', 'conviction_boundary', 'offense_counting', 'fine_and_imprisonment_authority', 'municipal_penalty_ceiling', 'court_discretion', 'notice_hearing_appeal', 'current_legal_validity']],
            ),
            ...$this->articleLProvisions(),
            ...$this->articleMProvisions(),
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

    /** @return array<int, array<string, mixed>> */
    private function articleLProvisions(): array
    {
        $definitions = [
            ['01-SCOPE', 'Section 3L.01', 'MTOP territorial scope', RevenueCodeProvisionType::AdministrativeRule, 'Motorized tricycles for hire within Ipil are within Article L.', ['vehicle_and_for_hire_classification', 'territorial_nexus']],
            ['02-DEFINITIONS', 'Section 3L.02', 'MTOP regulatory definitions', RevenueCodeProvisionType::EvidenceRequirement, 'The ordinance defines colorum operation, franchise, tricycle for hire, MTOP, RSU, road worthiness, tariff, and traffic citation.', ['natural_or_juridical_operator', 'private_to_for_hire_status', 'roadworthiness_standard', 'tariff_and_citation_authority']],
            ['03-REGULATORY-BOARD', 'Section 3L.03', 'Motorized Tricycle Regulatory Board authority', RevenueCodeProvisionType::AdministrativeRule, 'The ordinance identifies MTRB membership and powers over permits, zones, service conditions, recommendations, and a task force.', ['board_membership_and_quorum', 'delegation', 'sangguniang_bayan_coordination', 'task_force_authority']],
            ['04-GRANTING-MTOP', 'Section 3L.04', 'MTOP application, renewal, and eligibility', RevenueCodeProvisionType::AdministrativeRule, 'Operators and drivers routing within Ipil must secure and annually renew an MTOP subject to family, document, seminar, and association requirements.', ['operator_or_driver_applicant', 'one_franchise_per_family', 'renewal_deadline', 'documentary_sufficiency', 'automatic_issuance_authority']],
            ['05-FEES', 'Section 3L.05', 'MTOP fees and rates', RevenueCodeProvisionType::FixedFee, 'The ordinance prints separate MTOP, inspection, parking, ID, lamination, total, and monthly garage-franchising amounts.', ['vehicle_configuration', 'separate_or_bundled_charges', 'printed_total_reconciliation', 'garage_identity_and_monthly_term', 'payer_collector_receipt']],
            ['06-APPROVAL-RENEWAL', 'Section 3L.06', 'MTOP approval and LTO conversion', RevenueCodeProvisionType::AdministrativeRule, 'Within 30 days after approval, a new motorcycle unit must be converted from private to for-hire registration to secure a number plate.', ['approval_timestamp', 'calendar_or_business_days', 'lto_rsu_evidence', 'plate_and_operating_authority']],
            ['07-MARKINGS', 'Section 3L.07', 'MTOP body number, color, and markings', RevenueCodeProvisionType::EvidenceRequirement, 'Covered tricycles must display an assigned body number, franchise-holder name, and day-off marking in stated locations and dimensions.', ['body_number_authority', 'marking_measurement', 'vehicle_identity', 'inspection_and_correction']],
            ['08-SUSPENSION-STOPPAGE', 'Section 3L.08', 'MTOP service suspension or stoppage notice', RevenueCodeProvisionType::AdministrativeRule, 'An operator stopping completely or suspending service for more than one month must report in writing to the named regulatory office.', ['notice_form_and_recipient', 'effective_date', 'one_month_boundary', 'permit_and_fee_effect']],
            ['09-NONTRANSFERABILITY', 'Section 3L.09', 'MTOP non-transferability and death exception', RevenueCodeProvisionType::AdministrativeRule, 'MTOP is personal, non-transferable, and non-negotiable, with a stated direct-family exception after death.', ['personal_qualification', 'death_evidence', 'spouse_or_child_eligibility', 'single_and_legal_age_requirements', 'succession_process']],
            ['10-DAY-OFF', 'Section 3L.10', 'MTOP number coding and weekly day off', RevenueCodeProvisionType::AdministrativeRule, 'A weekly day-off schedule is assigned from the last digit of the MTRB case or body number.', ['case_or_body_number_authority', 'last_digit_10_conflict', 'holidays_and_exceptions', 'enforcement']],
            ['11-FARE', 'Section 3L.11', 'Tricycle fare, baggage, and passenger discounts', RevenueCodeProvisionType::FixedFee, 'The ordinance prints a first-kilometer fare, succeeding-kilometer increment, baggage equivalents, discounts, and a child exemption.', ['multiple_passenger_activation', 'distance_measurement', 'fractional_distance', 'fare_rounding', 'discount_stacking', 'passenger_evidence']],
            ['12-ROTUNDA-RESTRICTION', 'Section 3L.12', 'Rotunda operating restriction', RevenueCodeProvisionType::AdministrativeRule, 'Tricycles are prohibited along Rotunda from the stated effective date.', ['route_geometry', 'exceptions', 'current_route_authority', 'enforcement']],
            ['13-ROUTES', 'Section 3L.13', 'MTOP operating routes', RevenueCodeProvisionType::AdministrativeRule, 'The ordinance names four route boundaries for tricycles and pedicabs.', ['route_geometry_and_direction', 'assignment_authority', 'pedicab_scope', 'current_route_validity']],
            ['14-HIGHWAY-LANE', 'Section 3L.14', 'National Highway lane restriction', RevenueCodeProvisionType::AdministrativeRule, 'Motorized tricycles must use the outermost right lane of the National Highway.', ['road_segment_scope', 'overtaking_and_turning_exceptions', 'traffic_authority', 'enforcement']],
            ['15-ASSOCIATION', 'Section 3L.15', 'Recognized tricycle association', RevenueCodeProvisionType::AdministrativeRule, 'ITDOA is identified as the sole MTRB-recognized association and its president may relay association actions.', ['exclusive_recognition_authority', 'membership_effect', 'officer_identity', 'report_evidentiary_effect']],
            ['16-PROHIBITED-ACTS', 'Section 3L.16', 'Prohibited tricycle operating conduct', RevenueCodeProvisionType::AdministrativeRule, 'The ordinance prohibits refusal without emergency cause, attire and ID violations, overloading, overcharging, misconduct, intoxication, gambling, and smoking.', ['actor_and_vehicle_identity', 'complaint_and_investigation', 'emergency_defense', 'evidence_standard', 'violation_mapping']],
            ['17-APPREHENDING-OFFICERS', 'Section 3L.17', 'Authorized MTOP apprehending officers', RevenueCodeProvisionType::AdministrativeRule, 'MTRB Task Force, PNP, MITSOM, and other deputized traffic enforcers are named as apprehending officers.', ['appointment_and_deputation', 'jurisdiction', 'citation_authority', 'chain_of_custody']],
            ['18-PENALTIES', 'Section 3L.18', 'Escalating MTOP administrative penalties', RevenueCodeProvisionType::AdministrativeRule, 'The ordinance prints first, second, and third-offense fines, with denial of renewal and impoundment at the third offense.', ['offense_identity_and_counting', 'finality_and_lookback', 'fine_authority', 'denial_and_impoundment_due_process', 'appeal']],
            ['19-FINE-DISPOSITION', 'Section 3L.19', 'Treasury collection and General Fund disposition', RevenueCodeProvisionType::AdministrativeRule, 'Article L fines and forfeitures are paid through the Municipal Treasurer and deposited to the General Fund.', ['assessed_fine_identity', 'collector_and_receipt', 'fund_account_mapping', 'forfeiture_scope', 'reconciliation']],
            ['20-REPEALING', 'Section 3L.20', 'Article L repealing clause', RevenueCodeProvisionType::AdministrativeRule, 'Inconsistent ordinances, rules, and regulations are repealed or amended accordingly.', ['conflicting_instrument_inventory', 'legal_priority', 'effective_date']],
            ['21-SEPARABILITY', 'Section 3L.21', 'Article L separability clause', RevenueCodeProvisionType::AdministrativeRule, 'Unaffected Article L provisions continue if another part is held unconstitutional or invalid.', ['invalidity_decision_authority', 'affected_provision_mapping', 'effective_date']],
        ];

        return array_map(fn (array $definition): array => $this->provision(
            code: 'MRC-3L-'.$definition[0],
            section: $definition[1],
            title: $definition[2],
            type: $definition[3],
            excerpt: $definition[4],
            notes: 'Operational execution is blocked pending accepted municipal procedure for: '.implode(', ', $definition[5]).'. No Article L implementation was found in the studied legacy archive.',
            metadata: ['chapter' => 3, 'article' => 'L', 'known_ambiguities' => $definition[5], 'legacy_implementation_not_found' => true],
        ), $definitions);
    }

    /** @return array<int, array<string, mixed>> */
    private function articleMProvisions(): array
    {
        $definitions = [
            ['01-OCCUPATIONAL-FEES', 'Section 3M.01', 'Individual occupational calling permit fees', RevenueCodeProvisionType::FixedFee, 'The ordinance prints a uniform PHP 100.00 annual Mayor’s Permit fee for 69 named or catch-all occupations and callings not requiring government examination.', ['person_and_occupation_identity', 'government_examination_boundary', 'occupation_classification', 'multiple_occupation_treatment', 'current_operational_amount']],
            ['02-EXEMPTIONS', 'Section 3M.02', 'Occupational calling fee exemptions', RevenueCodeProvisionType::AdministrativeRule, 'Professionals subject to provincial professional tax and government employees are exempted from the Article M fee.', ['professional_tax_liability_evidence', 'government_employee_scope', 'permit_versus_fee_exemption', 'mixed_occupation']],
            ['03-PERSONS-GOVERNED', 'Section 3M.03', 'Workers requiring individual Mayor’s Permit and Calling ID', RevenueCodeProvisionType::AdministrativeRule, 'Temporary and permanent workers in stated dangerous, public-facing, food, night, and other occupations must secure an individual Mayor’s Permit and LGU-Ipil Calling ID.', ['worker_and_employer_identity', 'establishment_classification', 'temporary_worker_scope', 'public_health_and_safety_basis', 'age_and_documentary_requirements']],
            ['04-PAYMENT-TIMING', 'Section 3M.04', 'Occupational permit payment, employer advance, and Calling ID', RevenueCodeProvisionType::AdministrativeRule, 'Article M fees are payable upon first application and annually by January 20; each distinct occupation is separately payable, employers advance employee fees, and a one-year Calling ID has a printed PHP 25.00 amount.', ['application_and_approval_sequence', 'annual_period', 'distinct_occupation_identity', 'employer_advance_and_recovery', 'calling_id_issuance_and_validity', 'payer_collector_receipt']],
            ['05-LATE-CHANGES-RENEWAL', 'Section 3M.05', 'Late charges, ownership or location change, new hires, and renewal', RevenueCodeProvisionType::AdministrativeRule, 'The ordinance prints a 25-percent surcharge and two-percent monthly penalty capped at 36 months, requires new permits after ownership or inter-municipal location changes, and addresses new hires and birth-month renewal.', ['surcharge_and_interest_arithmetic', 'monthly_accrual_and_cap', 'ownership_and_location_change_scope', 'new_hire_start_boundary', 'birth_month_renewal_conflict']],
            ['06-ADMINISTRATION', 'Section 3M.06', 'BPLO occupational registry and permit cancellation', RevenueCodeProvisionType::EvidenceRequirement, 'BPLO must keep occupational permit and payment records; retirement or cessation requires surrender of permit and receipt for cancellation by stated authorities.', ['registry_fields_and_privacy', 'payment_and_receipt_linkage', 'retirement_or_cessation_evidence', 'surrender_and_cancellation_authority', 'retention_and_migration']],
        ];

        return array_map(fn (array $definition): array => $this->provision(
            code: 'MRC-3M-'.$definition[0],
            section: $definition[1],
            title: $definition[2],
            type: $definition[3],
            excerpt: $definition[4],
            notes: 'Operational execution is blocked pending accepted municipal procedure for: '.implode(', ', $definition[5]).'. The studied legacy archive contains only a static “OCCUPATIONAL CALLING” assessment label, not an Article M workflow or rule implementation.',
            metadata: ['chapter' => 3, 'article' => 'M', 'known_ambiguities' => $definition[5], 'legacy_evidence' => 'static_business_permit_form_assessment_label_only'],
        ), $definitions);
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
        $this->seedAstrayAnimalArticleDClauses();
        $this->seedCircusParadeArticleEClauses();
        $this->seedLargeCattleArticleFClauses();
        $this->seedExcavationArticleGClauses();
        $this->seedWeightsMeasuresArticleHClauses();
        $this->seedDispensingPumpsArticleIClauses();
        $this->seedFilmingArticleJClauses();
        $this->seedEquipmentArticleKClauses();

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

    private function seedAstrayAnimalArticleDClauses(): void
    {
        $this->persistPolicyBoundaryClauses('MRC-3D-01-DEFINITIONS', [
            $this->policyBoundaryClause(1, 'MRC-3D-01-ASTRAY-ANIMAL', RevenueCodeProvisionClauseType::Definition, 'Astray Animal means an animal which is set loose unrestrained, and not under the complete control of its owner, or the charge or in possession thereof, found roaming at-large in public or private places whether fettered or not.', 'Candidate meaning: an astray animal is unrestrained or not under complete control and found at large in a public or private place, whether fettered or not.', 'Control, possession, restraint, at-large status, observation evidence, animal identity, responsible person, and current animal law require accepted interpretation.', ['definition_code' => 'astray_animal']),
            $this->policyBoundaryClause(2, 'MRC-3D-01-PUBLIC-PLACE', RevenueCodeProvisionClauseType::Definition, 'Public Place includes national, provincial, municipal, or barangay streets, parks, places, and such other places open to the public.', 'Candidate meaning: public place includes the listed government streets and parks plus other places open to the public.', 'Ownership, public-access status, temporary closure, jurisdiction, boundary evidence, and other-place classification require accepted interpretation.', ['definition_code' => 'public_place']),
            $this->policyBoundaryClause(3, 'MRC-3D-01-PRIVATE-PLACE', RevenueCodeProvisionClauseType::Definition, 'Private Place includes privately-owned streets or yards, rice fields or farmlands, or lots owned by an individual other than the owner of the animal.', 'Candidate meaning: private place includes listed privately owned locations belonging to someone other than the animal owner.', 'Property ownership, occupier authority, animal-owner identity, common areas, boundary evidence, and complainant standing require accepted interpretation.', ['definition_code' => 'private_place']),
            $this->policyBoundaryClause(4, 'MRC-3D-01-LARGE-CATTLE', RevenueCodeProvisionClauseType::Definition, 'Large Cattle includes horses, mules, asses, carabaos, cows, and other domestic members of the bovine family.', 'Candidate meaning: large cattle includes the listed animals and other domestic bovine-family members.', 'Species classification, age, ownership marks, cross-reference to Article F, and current livestock law require accepted interpretation.', ['definition_code' => 'large_cattle', 'source_animals' => ['horses', 'mules', 'asses', 'carabaos', 'cows', 'other domestic bovine family members']]),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3D-02-IMPOUNDING-EXPENSES', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-3D-02-ACTUAL-IMPOUNDING-EXPENSES',
                type: RevenueCodeProvisionClauseType::ActualCost,
                sourceText: 'For each day or fraction thereof on each head of astray animal ... Large Cattle and other animals - Actual Incurred expenses during impounding.',
                candidateInterpretation: 'Candidate amount basis: recover actual incurred impounding expenses for each animal head for each day or fraction of a day.',
                executionBlocker: 'The source states no numeric amount or eligible-cost catalog; expense evidence, approval, allocation by animal and day/fraction, overhead, accounting, disputes, and rounding require accepted policy.',
                metadata: ['source_amount_basis' => 'actual_incurred_expenses', 'candidate_units' => ['animal_head', 'day_or_fraction'], 'numeric_candidate_is_missing' => true],
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3D-03-RELEASE-PAYMENT', [
            $this->policyBoundaryClause(1, 'MRC-3D-03-PAY-BEFORE-RELEASE', RevenueCodeProvisionClauseType::PaymentTiming, 'The impounding fee shall be paid to the Municipal Treasurer prior to the release of the impounded animal to its owner.', 'Candidate timing: pay the impounding charge to the Municipal Treasurer before releasing the animal to its owner.', 'Ownership proof, final charge and expense evidence, official receipt, authorized release, disputes, third-party claims, and impounding-versus-poundage terminology require accepted procedure.', ['candidate_collector' => 'Municipal Treasurer', 'candidate_timing' => 'before_release', 'candidate_release_to' => 'animal_owner']),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3D-04-CUSTODY-NOTICE', [
            $this->policyBoundaryClause(1, 'MRC-3D-04-BARANGAY-TANOD-APPREHENSION', RevenueCodeProvisionClauseType::AuthorityBoundary, 'The Barangay Tanods of the Municipality are hereby authorized to apprehend and impound astray animals in the municipal corral or a place duly designated for such purpose.', 'Candidate authority: municipal Barangay Tanods apprehend and impound astray animals in the municipal corral or a duly designated place.', 'Current authority, territorial assignment, apprehension evidence, animal welfare, transport, designated-place approval, custody handoff, and incident records require accepted procedure.', ['candidate_actor' => 'Barangay Tanods of the Municipality', 'candidate_facilities' => ['municipal_corral', 'duly_designated_place']]),
            $this->policyBoundaryClause(2, 'MRC-3D-04-MUNICIPAL-HALL-NOTICE-CLAIM', RevenueCodeProvisionClauseType::CustodyProcedure, 'He shall also cause the posting of notice of the impounded astray animal in the Municipal Hall for three [3] consecutive days, starting one day after the animal is impounded, within which the owner is required to claim and establish ownership of the impounded animal.', 'Candidate notice and claim period: post at Municipal Hall for three consecutive days beginning one day after impounding, during which the owner claims and establishes ownership.', 'The responsible “He” is ambiguous; posting time, day counting, notice content and proof, owner notification, ownership evidence, late claims, and relationship to the five-day auction trigger require accepted procedure.', ['source_notice_days' => 3, 'candidate_notice_start' => 'one_day_after_impounding', 'candidate_notice_place' => 'Municipal Hall', 'known_actor_ambiguity' => 'He']),
            $this->policyBoundaryClause(3, 'MRC-3D-04-MAYOR-TREASURER-INFORMED', RevenueCodeProvisionClauseType::CustodyProcedure, 'The Municipal Mayor and Municipal Treasurer shall be informed of the impounding.', 'Candidate notification: inform the Municipal Mayor and Municipal Treasurer of each impounding.', 'Responsible sender, timing, method, required data, acknowledgement, delegation, and retained audit evidence require accepted procedure.', ['candidate_recipients' => ['Municipal Mayor', 'Municipal Treasurer']]),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3D-04-AUCTION-DISPOSITION', [
            $this->policyBoundaryClause(1, 'MRC-3D-04-UNCLAIMED-FIVE-DAY-AUCTION', RevenueCodeProvisionClauseType::DispositionProcedure, 'Impounded animals not claimed within five (5) days after the date of impounding shall be sold at public auction under the following procedures.', 'Candidate disposition trigger: send an animal unclaimed for five days after impounding to public auction.', 'Calendar and cutoff, valid claim attempts, notice sufficiency, ownership disputes, welfare status, auction authorization, and relationship to the three-day posting period require accepted procedure.', ['source_unclaimed_days' => 5, 'candidate_disposition' => 'public_auction']),
            $this->policyBoundaryClause(2, 'MRC-3D-04-AUCTION-NOTICE-SALE-REPORT', RevenueCodeProvisionClauseType::DispositionProcedure, 'The Municipal Treasurer shall post notice for three (3) days in three (3) conspicuous places including the main door of the Municipal Hall and the public markets. The animal shall be sold to the highest bidder. Within three (3) days after the auction sale, the Municipal Treasurer shall make a report of the proceedings in writing to the Municipal Mayor.', 'Candidate auction procedure: Treasurer posts three-day notice in three conspicuous places, sells to the highest bidder, and reports the proceedings in writing to the Mayor within three days after sale.', 'Notice-place selection, multiple public markets, posting proof, bid rules, reserve and valuation, bidder eligibility, sale record, payment, report content, and deadline counting require accepted policy.', ['candidate_actor' => 'Municipal Treasurer', 'source_notice_days' => 3, 'source_notice_place_count' => 3, 'source_included_places' => ['main door of Municipal Hall', 'public markets'], 'candidate_award' => 'highest_bidder', 'source_report_days_after_sale' => 3, 'candidate_report_recipient' => 'Municipal Mayor']),
            $this->policyBoundaryClause(3, 'MRC-3D-04-OWNER-STOPS-SALE', RevenueCodeProvisionClauseType::PaymentTiming, 'The owner may stop the sale by paying at any time before or during the auction sale, the impounding fees due and the cost of the advertisement and conduct of sale to the Municipal Treasurer, otherwise, the sale shall proceed.', 'Candidate redemption: before or during auction, the owner may stop sale by paying impounding charges plus advertisement and sale-conduct costs to the Treasurer.', 'Ownership proof, exact cutoff during auction, eligible and evidenced costs, final charge, receipt, competing bidder rights, animal release, and dispute handling require accepted procedure.', ['candidate_collector' => 'Municipal Treasurer', 'candidate_redemption_window' => 'before_or_during_auction', 'candidate_redemption_costs' => ['impounding_fees_due', 'advertisement_cost', 'conduct_of_sale_cost']]),
            $this->policyBoundaryClause(4, 'MRC-3D-04-SALE-PROCEEDS-ALLOCATION', RevenueCodeProvisionClauseType::DispositionProcedure, 'The proceeds of the sale shall be applied to satisfy the cost of impounding, advertisement and conduct of sale. The residue over the cost shall accrue to the General Fund of the Municipality.', 'Candidate allocation: auction proceeds first satisfy stated impounding and sale costs, with the residue accruing to the Municipal General Fund.', 'Cost priority, eligible expenses, shortfall treatment, accounting codes, remittance, receipt and liquidation, owner claim to surplus, and legal authority for General Fund treatment require reconciliation.', ['candidate_cost_priority' => ['impounding', 'advertisement', 'conduct_of_sale'], 'candidate_residue_destination' => 'Municipality General Fund']),
            $this->policyBoundaryClause(5, 'MRC-3D-04-DEEMED-MUNICIPAL-SALE', RevenueCodeProvisionClauseType::DispositionProcedure, 'In case the impounded animal is not disposed of within ten (10) days from the date of notice of public auction, the same shall be considered sold to the Municipal Government for the amount equivalent to the poundage fees due.', 'Candidate terminal disposition: after ten days from auction notice without disposal, deem the animal sold to the Municipal Government for the amount of poundage charges due.', 'Which notice date starts the period, failed-sale evidence, valuation, impounding-versus-poundage terminology, title transfer, accounting entry, authority, animal custody/use/disposal, owner rights, and due process require legal and operational reconciliation.', ['source_days_from_auction_notice' => 10, 'candidate_buyer' => 'Municipal Government', 'candidate_consideration' => 'poundage_fees_due', 'known_terminology_conflict' => ['impounding_fee', 'poundage_fees']]),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3D-05-PENALTIES-DAMAGES', [
            $this->policyBoundaryClause(1, 'MRC-3D-05-FIRST-OFFENSE-FINE', RevenueCodeProvisionClauseType::Penalty, 'Owners whose animals are caught astray and incurring damages to plants and properties shall pay: First offense - P 100.00.', 'Candidate source fine: PHP 100.00 for a first damage-causing astray-animal offense.', 'Damage and causation proof, offense identity and history, owner responsibility, notice and hearing, imposing authority, collection, receipt, appeal, and current legal authority require reconciliation.', ['candidate_offense_number' => 1, 'candidate_damage_required' => true], 10_000),
            $this->policyBoundaryClause(2, 'MRC-3D-05-SECOND-OFFENSE-FINE', RevenueCodeProvisionClauseType::Penalty, 'Owners whose animals are caught astray and incurring damages to plants and properties shall pay: Second offense - P 200.00.', 'Candidate source fine: PHP 200.00 for a second damage-causing astray-animal offense.', 'Damage and causation proof, offense counting period and linkage, owner responsibility, notice and hearing, imposing authority, collection, receipt, appeal, and current legal authority require reconciliation.', ['candidate_offense_number' => 2, 'candidate_damage_required' => true], 20_000),
            $this->policyBoundaryClause(3, 'MRC-3D-05-THIRD-SUBSEQUENT-FINE', RevenueCodeProvisionClauseType::Penalty, 'Owners whose animals are caught astray and incurring damages to plants and properties shall pay: For the third offense and each subsequent offense - P 300.00.', 'Candidate source fine: PHP 300.00 for the third and each subsequent damage-causing astray-animal offense.', 'Damage and causation proof, offense counting period and linkage, subsequent-offense treatment, owner responsibility, notice and hearing, imposing authority, collection, receipt, appeal, and current legal authority require reconciliation.', ['candidate_offense_from' => 3, 'candidate_damage_required' => true], 30_000),
            $this->policyBoundaryClause(4, 'MRC-3D-05-ACTUAL-PROPERTY-DAMAGE', RevenueCodeProvisionClauseType::ActualCost, 'In addition to the fine, the owners shall pay the amount of damage incurred, if any, to the property owner.', 'Candidate compensation: in addition to the fine, the animal owner pays actual property damage to the property owner.', 'Damage valuation, causation and ownership proof, agreement or adjudication authority, payer and payee identity, direct-versus-municipal collection, receipt, disputes, partial payment, and relationship to civil remedies require accepted legal procedure.', ['source_amount_basis' => 'actual_property_damage', 'candidate_payer' => 'animal_owner', 'candidate_payee' => 'property_owner', 'numeric_candidate_is_missing' => true]),
        ]);
    }

    private function seedCircusParadeArticleEClauses(): void
    {
        $this->persistPolicyBoundaryClauses('MRC-3E-01-DAILY-PERMIT-FEE', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-3E-01-CIRCUS-PARADE-DAILY-FEE',
                type: RevenueCodeProvisionClauseType::PermitRequirement,
                sourceText: 'There shall be collected a Mayor’s Permit Fee of Five Hundred (P500.00) pesos per day on every circus and other parades using banners, floats or musical instruments carried on in this municipality.',
                candidateInterpretation: 'Candidate source fee: PHP 500.00 for each day of a qualifying circus or other parade using banners, floats, or musical instruments in the Municipality.',
                executionBlocker: 'Event classification, the relationship among circus, parade, banners, floats, and instruments, event and permit identity, day and partial-day counting, route scope, cancellation, refund, and operational acceptance require municipal policy.',
                metadata: ['candidate_unit' => 'event_day', 'candidate_activity_types' => ['circus', 'other_parade'], 'source_activity_features' => ['banners', 'floats', 'musical_instruments']],
                amountCents: 50_000,
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3E-02-PAYMENT-TIMING', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-3E-02-APPLY-PAY-THREE-DAYS-BEFORE',
                type: RevenueCodeProvisionClauseType::PaymentTiming,
                sourceText: 'The fee imposed herein shall be due and payable to the Municipal Treasurer upon application for a permit to the Municipal Mayor at least three (3) days before the scheduled date of the circus or parade and on such activity shall be held.',
                candidateInterpretation: 'Candidate timing: apply to the Municipal Mayor and pay the Municipal Treasurer at least three days before the scheduled circus or parade.',
                executionBlocker: 'The final phrase “and on such activity shall be held” is grammatically defective; filing/payment sequence, deadline counting, late applications, event rescheduling, receipt evidence, permit issuance, and activity authorization require accepted procedure.',
                metadata: ['candidate_application_receiver' => 'Municipal Mayor', 'candidate_collector' => 'Municipal Treasurer', 'source_advance_days' => 3, 'known_source_wording' => 'and on such activity shall be held'],
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3E-03-EXEMPTIONS', [
            $this->policyBoundaryClause(1, 'MRC-3E-03-CIVIC-PARADE-EXEMPTION', RevenueCodeProvisionClauseType::Exemption, 'Civic ... parades ... shall not be required to pay the permit fee imposed in this Article.', 'Candidate exemption: civic parades do not pay the Article E permit fee.', 'Civic-event classification, organizer and sponsorship evidence, mixed-purpose events, approval authority, and whether only payment or also permit requirements are affected require accepted policy.', ['candidate_exempt_activity' => 'civic_parade']),
            $this->policyBoundaryClause(2, 'MRC-3E-03-MILITARY-PARADE-EXEMPTION', RevenueCodeProvisionClauseType::Exemption, 'Military parades ... shall not be required to pay the permit fee imposed in this Article.', 'Candidate exemption: military parades do not pay the Article E permit fee.', 'Military-event and organizer identity, joint or ceremonial events, evidence, approval authority, and whether only payment or also permit requirements are affected require accepted policy.', ['candidate_exempt_activity' => 'military_parade']),
            $this->policyBoundaryClause(3, 'MRC-3E-03-RELIGIOUS-PROCESSION-EXEMPTION', RevenueCodeProvisionClauseType::Exemption, 'Religious processions shall not be required to pay the permit fee imposed in this Article.', 'Candidate exemption: religious processions do not pay the Article E permit fee.', 'Religious-procession and organizer identity, mixed religious/commercial events, evidence, approval authority, constitutional treatment, and whether only payment or also permit requirements are affected require legal and municipal policy.', ['candidate_exempt_activity' => 'religious_procession']),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3E-04-ADMINISTRATION', [
            $this->policyBoundaryClause(1, 'MRC-3E-04-MAYOR-PERMIT-BEFORE-PARADE', RevenueCodeProvisionClauseType::PermitRequirement, 'Any persons who shall hold a parade within this municipality shall first obtain from the Municipal Mayor before undertaking the activity.', 'Candidate prerequisite: obtain a permit from the Municipal Mayor before holding a parade in the Municipality.', 'The source omits the object after “obtain”; applicant and responsible-person identity, permit identity, issuing authority, approval criteria, exemptions, validity, event changes, and enforcement require accepted procedure.', ['candidate_authority' => 'Municipal Mayor', 'candidate_timing' => 'before_activity', 'known_source_omission' => 'object after obtain']),
            $this->policyBoundaryClause(2, 'MRC-3E-04-WRITTEN-APPLICATION-CONTENTS', RevenueCodeProvisionClauseType::DocumentaryRequirement, 'A written application in a prescribed form shall indicate the name, address of the applicant, the description of the activity, the place or places where the same will be conducted and such other pertinent information as may be required.', 'Candidate application evidence: prescribed written form containing applicant name and address, activity description, activity places, and other required pertinent information.', 'The prescribed form, applicant identity, address standard, route and place detail, additional-information authority, filing channel, signature, attachments, retention, and sufficiency require accepted procedure.', ['candidate_fields' => ['applicant_name', 'applicant_address', 'activity_description', 'activity_places', 'other_pertinent_information']]),
            $this->policyBoundaryClause(3, 'MRC-3E-04-PNP-ORDER-RULES', RevenueCodeProvisionClauseType::AuthorityBoundary, 'The Station Commander of the Philippine National Police shall promulgate the necessary rules and regulations to maintain an orderly and peaceful conduct of the activities mentioned in this Article.', 'Candidate authority: the Philippine National Police Station Commander establishes rules for orderly and peaceful conduct of Article E activities.', 'Current office title and jurisdiction, delegation, rule identity and publication, consistency with other law, permit conditions, coordination, enforcement, and audit evidence require legal and operational validation.', ['candidate_authority' => 'Station Commander of the Philippine National Police', 'candidate_purpose' => 'orderly_and_peaceful_conduct']),
            $this->policyBoundaryClause(4, 'MRC-3E-04-PNP-LAWFUL-BOUNDARY', RevenueCodeProvisionClauseType::OperatingRestriction, 'He shall also define the boundary within which such activities may be lawfully conducted.', 'Candidate operating boundary: the PNP Station Commander defines where the covered activities may lawfully occur.', 'The pronoun refers to the preceding Station Commander, but current authority, route and geographic representation, issuance and publication, conflict with Mayor permit places, changes, enforcement, and evidence require accepted procedure.', ['candidate_authority' => 'Station Commander of the Philippine National Police', 'candidate_boundary_type' => 'lawful_activity_area', 'source_actor_pronoun' => 'He']),
        ]);
    }

    private function seedLargeCattleArticleFClauses(): void
    {
        $this->persistPolicyBoundaryClauses('MRC-3F-01-DEFINITION', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-3F-01-TWO-YEAR-LARGE-CATTLE',
                type: RevenueCodeProvisionClauseType::Definition,
                sourceText: 'For purposes of this Article, “large cattle” includes a two-year old horse, mule ass, carabao, cow or other domesticated member of the bovine family.',
                candidateInterpretation: 'Candidate Article F meaning: large cattle includes the listed domesticated animals once they reach two years of age.',
                executionBlocker: 'The “mule ass” punctuation is defective; species treatment, the two-year qualifier, age evidence, Article F-only scope, and the difference from Article D’s unqualified definition require accepted legal interpretation.',
                metadata: ['candidate_minimum_age_years' => 2, 'candidate_animals' => ['horse', 'mule', 'ass', 'carabao', 'cow', 'other domesticated bovine family member'], 'scope_contrast_clause' => 'MRC-3D-01-LARGE-CATTLE', 'known_source_wording' => 'mule ass'],
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3F-02-FEES', [
            $this->policyBoundaryClause(1, 'MRC-3F-02-CERTIFICATE-OWNERSHIP-FEE', RevenueCodeProvisionClauseType::PermitRequirement, 'For certificate of Ownership - Service Fee 100.00.', 'Candidate source fee: PHP 100.00 for a certificate of ownership.', 'Certificate eligibility and meaning, cattle and owner identity, initial versus replacement issuance, ownership evidence, numbering, payer, collection, receipt, and operational amount acceptance require municipal policy.', ['candidate_service' => 'certificate_of_ownership'], 10_000),
            $this->policyBoundaryClause(2, 'MRC-3F-02-CERTIFICATE-TRANSFER-FEE', RevenueCodeProvisionClauseType::PermitRequirement, 'For certificate of transfer - Service Fee 100.00.', 'Candidate source fee: PHP 100.00 for a certificate of transfer.', 'Transfer eligibility and meaning, cattle identity, ownership chain, original documents, numbering, payer, collection, receipt, same-day limitation, and operational amount acceptance require municipal policy.', ['candidate_service' => 'certificate_of_transfer'], 10_000),
            $this->policyBoundaryClause(3, 'MRC-3F-02-PRIVATE-BRAND-REGISTRATION-FEE', RevenueCodeProvisionClauseType::PermitRequirement, 'For registration of Private Brand - Service Fee 200.00.', 'Candidate source fee: PHP 200.00 for registration of a private brand.', 'Brand owner and mark identity, uniqueness, territorial scope, approval and conflict checks, cattle linkage, validity, renewal, numbering, collection, receipt, and operational amount acceptance require municipal policy.', ['candidate_service' => 'private_brand_registration'], 20_000),
            $this->policyBoundaryClause(4, 'MRC-3F-02-TRANSFER-FEE-ONCE-PER-DAY', RevenueCodeProvisionClauseType::PaymentTiming, 'The transfer fee shall be collected only once if a large cattle is transferred more than once in a day.', 'Candidate frequency: collect the transfer fee only once for the same animal when it is transferred multiple times on one day.', 'Animal identity, calendar day and timezone, transfer chain, payer, first-versus-later transaction, reversals, corrections, cross-municipality transfers, and certificate issuance for each transfer require accepted policy.', ['candidate_frequency' => 'once_per_animal_per_day', 'candidate_trigger' => 'multiple_transfers_same_day']),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3F-03-PAYMENT', [
            $this->policyBoundaryClause(
                sequence: 1,
                code: 'MRC-3F-03-PAY-UPON-REGISTRATION-TRANSFER',
                type: RevenueCodeProvisionClauseType::PaymentTiming,
                sourceText: 'The registration fee shall be paid to the Municipal Treasurer upon registration or transfer of ownership of the large cattle.',
                candidateInterpretation: 'Candidate timing: pay the applicable charge to the Municipal Treasurer when registering cattle or transferring its ownership.',
                executionBlocker: 'Section 3F.02 labels three service fees while this sentence says registration fee; charge mapping, payer, event sequence, receipt, certificate release, failed registration, and refund treatment require accepted procedure.',
                metadata: ['candidate_collector' => 'Municipal Treasurer', 'candidate_timing' => 'upon_registration_or_transfer', 'known_terminology_conflict' => ['registration_fee', 'service_fee']],
            ),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3F-04-REGISTRY', [
            $this->policyBoundaryClause(1, 'MRC-3F-04-REGISTER-AT-TWO-YEARS', RevenueCodeProvisionClauseType::PermitRequirement, 'Large cattle shall be registered with the Municipal Treasurer upon reaching the age of two (2) years.', 'Candidate registration trigger: register large cattle with the Municipal Treasurer when it reaches two years of age.', 'Birth and age evidence, exact due date, cattle identity, owner responsibility, late registration, animals already older than two, imported animals, and enforcement require accepted procedure.', ['candidate_registration_age_years' => 2, 'candidate_registry_authority' => 'Municipal Treasurer']),
            $this->policyBoundaryClause(2, 'MRC-3F-04-REGISTER-OWNERSHIP-SALE-TRANSFER', RevenueCodeProvisionClauseType::PermitRequirement, 'The ownership of a large cattle or its sale or transfer of ownership to another person shall be registered with the Municipal Treasurer.', 'Candidate registrable events: ownership, sale, and other transfer of ownership must be registered with the Municipal Treasurer.', 'Initial ownership, sale-versus-other-transfer classification, effective date, parties, cattle identity, title evidence, gifts, inheritance, cross-municipality events, and duplicate records require accepted procedure.', ['candidate_registry_authority' => 'Municipal Treasurer', 'candidate_events' => ['ownership', 'sale', 'other_transfer_of_ownership']]),
            $this->policyBoundaryClause(3, 'MRC-3F-04-OWNERSHIP-REGISTRY-CERTIFICATE-DATA', RevenueCodeProvisionClauseType::DocumentaryRequirement, 'All branded and counter-branded large cattle presented to the Municipal Treasurer shall be registered in a book showing among others, the name and residence of the owner, the consideration or purchase price of the animal in cases of sale or transfer, and the class, color, sex, brands and other identification marks of the cattle. These data shall also be stated in the certificate of ownership issued to the owner of the large cattle.', 'Candidate ownership record: registry book and ownership certificate carry the stated owner, transaction, classification, appearance, brand, and identification facts.', 'Branded/counter-branded scope, record identity, prescribed fields, owner and address standards, price evidence, classification vocabularies, mark representation, certificate format and numbering, corrections, privacy, and retention require accepted procedure.', ['candidate_registry_fields' => ['owner_name', 'owner_residence', 'consideration_or_purchase_price', 'cattle_class', 'color', 'sex', 'brands', 'other_identification_marks'], 'candidate_certificate' => 'certificate_of_ownership']),
            $this->policyBoundaryClause(4, 'MRC-3F-04-TRANSFER-REGISTRY-DATA', RevenueCodeProvisionClauseType::DocumentaryRequirement, 'The transfer of the large cattle, regardless of its age, shall be entered in the registry book setting forth, among others, the names and the residence of the owners and the purchaser; the consideration or purchase price of the animal for sale or transfer, class, sex, brands and other identifying marks of the animals; and a reference by number to the original certificate of ownership with the name of the municipality issued to it.', 'Candidate transfer record: every transfer regardless of cattle age records the parties, transaction value, cattle facts, marks, original ownership-certificate number, and issuing municipality.', 'The age-independent transfer rule must be reconciled with initial registration at age two; seller/owner plurality, purchaser identity, fields, price evidence, omitted color, certificate reference and municipality authority, cross-municipality verification, corrections, and chain integrity require accepted procedure.', ['candidate_applies_regardless_of_age' => true, 'candidate_registry_fields' => ['owner_names_and_residences', 'purchaser_name_and_residence', 'consideration_or_purchase_price', 'cattle_class', 'sex', 'brands', 'other_identifying_marks', 'original_certificate_number', 'issuing_municipality'], 'known_field_difference' => 'color appears in ownership record but not transfer list']),
            $this->policyBoundaryClause(5, 'MRC-3F-04-ORIGINAL-TITLE-DOCUMENTS', RevenueCodeProvisionClauseType::DocumentaryRequirement, 'No entries of transfer shall be made or certificate of transfer shall be issued by the Municipal Treasurer except upon the production of the original certificate of ownership and certificate of transfer and such other documents that show title of the owner.', 'Candidate transfer prerequisite: produce the original ownership certificate, transfer certificate, and other title documents before registry entry or transfer-certificate issuance.', 'Why an original transfer certificate is required before issuing a transfer certificate is unclear; document chain, original handling, prior transfers, loss or destruction, fraud checks, title sufficiency, issuing municipality verification, exceptions, and retention require accepted policy.', ['candidate_required_documents' => ['original_certificate_of_ownership', 'certificate_of_transfer', 'other_documents_showing_owner_title'], 'candidate_blocks' => ['transfer_registry_entry', 'certificate_of_transfer_issuance'], 'known_sequence_ambiguity' => 'certificate of transfer required before certificate of transfer issuance']),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3F-05-APPLICABILITY', [
            $this->policyBoundaryClause(1, 'MRC-3F-05-REVISED-ADMINISTRATIVE-CODE', RevenueCodeProvisionClauseType::AuthorityBoundary, 'All other matters relating to the registration of large cattle shall be governed by the pertinent provisions of the Revised Administrative Code.', 'Candidate external authority: pertinent Revised Administrative Code provisions govern other large-cattle registration matters.', 'The source does not identify the code version or provisions; current legal force, amendments, superseding livestock law, incorporated procedure, institutional authority, and precedence require legal validation.', ['external_authority' => 'Revised Administrative Code']),
            $this->policyBoundaryClause(2, 'MRC-3F-05-OTHER-AUTHORITIES', RevenueCodeProvisionClauseType::AuthorityBoundary, 'All other matters relating to the registration of large cattle shall be governed by ... other applicable laws, ordinances and rules and regulations.', 'Candidate external authority boundary: other applicable laws, ordinances, rules, and regulations govern matters not specified in Article F.', 'The authority catalog, versions, applicability, precedence, incorporated requirements, issuing institutions, and enforcement are not enumerated and require legal validation.', ['external_authority' => 'other applicable laws, ordinances, rules and regulations']),
        ]);
    }

    private function seedExcavationArticleGClauses(): void
    {
        $this->persistPolicyBoundaryClauses('MRC-3G-01-EXCAVATION-FEES', [
            $this->policyBoundaryClause(1, 'MRC-3G-01-CONCRETE-MINIMUM', RevenueCodeProvisionClauseType::PermitRequirement, 'For crossing streets with concentrate pavement: For crossing concentrate pavement (minimum area 2.00x600m,12sq.m.) - P 2,500.00.', 'Candidate source fee: PHP 2,500.00 minimum for a qualifying street crossing involving the pavement type and area printed in the source.', 'The source repeatedly says “concentrate” and prints `2.00x600m,12sq.m.`, whose literal dimensions do not produce 12 square meters; pavement classification, intended dimensions, minimum application, measurement, engineer acceptance, and operational amount require reconciliation.', ['candidate_surface' => 'concrete_or_source_concentrate', 'candidate_charge_kind' => 'minimum', 'source_measurement_text' => '2.00x600m,12sq.m.', 'known_mathematical_conflict' => true], 250_000),
            $this->policyBoundaryClause(2, 'MRC-3G-01-CONCRETE-BORING-PER-METER', RevenueCodeProvisionClauseType::RateBand, 'For crossing across base of streets with concentrate pavement, per linear meter (boring method) - 100.00.', 'Candidate source rate: PHP 100.00 per linear meter for boring across the base of the stated pavement type.', 'Concentrate/concrete wording, base and boring-method definition, eligible alignment, measured length, partial-meter treatment, minimum interaction, engineer certification, and operational rate require reconciliation.', ['candidate_surface' => 'concrete_or_source_concentrate', 'candidate_method' => 'boring', 'candidate_unit' => 'linear_meter'], 10_000),
            $this->policyBoundaryClause(3, 'MRC-3G-01-ASPHALT-MINIMUM', RevenueCodeProvisionClauseType::PermitRequirement, 'For crossing streets with asphalt pavement: Minimum - P 400.00.', 'Candidate source fee: PHP 400.00 minimum for a qualifying asphalt-pavement street crossing.', 'The covered excavation quantity, minimum threshold, relationship to the additional linear-meter charge, measurement and classification, engineer acceptance, and operational amount require reconciliation.', ['candidate_surface' => 'asphalt', 'candidate_charge_kind' => 'minimum'], 40_000),
            $this->policyBoundaryClause(4, 'MRC-3G-01-ASPHALT-PER-METER', RevenueCodeProvisionClauseType::RateBand, 'Additional fee for each linear meter crossing the streets (minimum width of excavation, 0.80m) - 100.00.', 'Candidate source rate: PHP 100.00 for each linear meter of an asphalt street crossing with the printed minimum excavation width.', 'Whether the rate starts after a minimum quantity or applies to every meter, length and 0.80-meter width measurement, partial meters, multiple cuts, minimum-fee interaction, engineer certification, and operational rate require reconciliation.', ['candidate_surface' => 'asphalt', 'candidate_unit' => 'linear_meter', 'source_minimum_width_meters' => '0.80'], 10_000),
            $this->policyBoundaryClause(5, 'MRC-3G-01-GRAVEL-MINIMUM', RevenueCodeProvisionClauseType::PermitRequirement, 'For crossing the streets with gravel pavement: Minimum Fee - P 500.00.', 'Candidate source fee: PHP 500.00 minimum for a qualifying gravel-pavement street crossing.', 'The covered excavation quantity, minimum threshold, relationship to the additional linear-meter charge, measurement and classification, engineer acceptance, and operational amount require reconciliation.', ['candidate_surface' => 'gravel', 'candidate_charge_kind' => 'minimum'], 50_000),
            $this->policyBoundaryClause(6, 'MRC-3G-01-GRAVEL-PER-METER', RevenueCodeProvisionClauseType::RateBand, 'Additional fee for each linear meter parallel to streets (minimum width of excavation, 0.3 meters) - 200.00.', 'Candidate source rate: PHP 200.00 for each linear meter parallel to a gravel street with the printed minimum excavation width.', 'The Article row begins with street crossing but this line says parallel; alignment scope, length and 0.3-meter width measurement, partial meters, multiple cuts, minimum-fee interaction, engineer certification, and operational rate require reconciliation.', ['candidate_surface' => 'gravel', 'candidate_unit' => 'linear_meter', 'candidate_alignment' => 'parallel', 'source_minimum_width_meters' => '0.3', 'known_scope_difference' => ['crossing', 'parallel']], 20_000),
            $this->policyBoundaryClause(7, 'MRC-3G-01-CURB-GUTTER-DAMAGE-PER-METER', RevenueCodeProvisionClauseType::RateBand, 'For crossing existing curbs and gutters resulting in the damage per linear meter - P 200.00.', 'Candidate source rate: PHP 200.00 per linear meter for crossing and damaging existing curbs or gutters.', 'Damage and causation evidence, curb/gutter scope, measured damaged length, partial meters, overlap with restoration and deposit obligations, engineer certification, actual repair costs, and operational rate require reconciliation.', ['candidate_features' => ['curbs', 'gutters'], 'candidate_unit' => 'damaged_linear_meter', 'candidate_damage_required' => true], 20_000),
            $this->policyBoundaryClause(8, 'MRC-3G-01-DELAY-PER-DAY', RevenueCodeProvisionClauseType::RateBand, 'Additional fee for each day of delay in excess of excavation period provided Mayor’s Permit - P 200.00.', 'Candidate source rate: PHP 200.00 for each day beyond the excavation period stated in the Mayor permit.', 'Permit duration, approved extensions, completion standard, delay start and end, calendar-day treatment, partial days, responsible official notice, overlap with restoration deadline, collection sequence, and operational rate require reconciliation.', ['candidate_unit' => 'delay_day', 'candidate_basis' => 'excavation_period_in_mayor_permit'], 20_000),
            $this->policyBoundaryClause(9, 'MRC-3G-01-RESTORE-ORIGINAL-FORM', RevenueCodeProvisionClauseType::RestorationRequirement, 'Provided: That all excavation resulting to damage shall be restored to its original form and shape.', 'Candidate restoration obligation: restore damage caused by excavation to its original form and shape.', 'The standard of original form and shape, responsible party, covered damage, materials and quality, deadline, inspection and acceptance authority, partial completion, warranty, actual costs, deposit consequences, and enforcement require accepted procedure.', ['candidate_standard' => 'original_form_and_shape', 'candidate_trigger' => 'excavation_resulting_in_damage']),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3G-02-PAYMENT-DEPOSIT-FORFEITURE', [
            $this->policyBoundaryClause(1, 'MRC-3G-02-PAY-UPON-APPLICATION-BEFORE-EXCAVATION', RevenueCodeProvisionClauseType::PaymentTiming, 'The fee imposed herein shall be paid to the Municipal Treasurer by every person who shall make any excavation or cause any excavation to be made upon application for Mayor’s Permit, but in all cases, prior to the excavation.', 'Candidate timing: the responsible person pays the Municipal Treasurer upon Mayor permit application and always before excavation.', 'Responsible-person identity, fee finalization before measurement, application and payment sequence, late or emergency work, collector, receipt, permit issuance, cancellations, and refunds require accepted procedure.', ['candidate_collector' => 'Municipal Treasurer', 'candidate_timing' => ['upon_mayor_permit_application', 'before_excavation']]),
            $this->policyBoundaryClause(2, 'MRC-3G-02-CASH-DEPOSIT-EQUAL-FEE', RevenueCodeProvisionClauseType::SecurityDeposit, 'A cash deposit in an amount equal to the fee imposed shall be deposited with the Municipal Treasurer at the same time the permit is paid.', 'Candidate security deposit: deposit cash equal to the applicable excavation fee with the Municipal Treasurer when paying for the permit.', 'Which fee components form the deposit base, estimate versus final measurement, deposit payer, separate receipt and accounting, custody, use restrictions, additional charges, interest, refund/release, and unclaimed deposits require accepted Treasury policy.', ['candidate_collector' => 'Municipal Treasurer', 'candidate_amount_basis' => 'equal_to_fee_imposed', 'candidate_timing' => 'same_time_permit_is_paid']),
            $this->policyBoundaryClause(3, 'MRC-3G-02-DEPOSIT-FORFEITURE-SEVEN-DAYS', RevenueCodeProvisionClauseType::Forfeiture, 'The cash deposited shall be forfeited in favor of the Municipal Government in case the restoration to its original form of the street excavated is not made within seven (7) days after the purpose of the excavation is accomplished.', 'Candidate forfeiture: forfeit the cash deposit to the Municipal Government if the street is not restored within seven days after the excavation purpose is accomplished.', 'Purpose-accomplished event and evidence, deadline start and day counting, restoration standard and inspection acceptance, responsible authority, notice and opportunity to cure, partial restoration, force majeure, forfeiture decision and accounting, actual repair, appeal, and surplus or shortfall require legal and Treasury policy.', ['candidate_beneficiary' => 'Municipal Government', 'source_restoration_days' => 7, 'candidate_deadline_start' => 'after_excavation_purpose_accomplished']),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3G-03-ADMINISTRATION', [
            $this->policyBoundaryClause(1, 'MRC-3G-03-MAYOR-PERMIT-WITH-DURATION', RevenueCodeProvisionClauseType::PermitRequirement, 'No person shall undertake or cause to undertake any digging or excavation, of any part or portion of the municipal streets of Ipil, Zamboanga Sibugay unless a permit shall have been first secured from the Office of the Municipal Mayor specifying the duration of the excavation.', 'Candidate prerequisite: secure a Municipal Mayor permit stating excavation duration before digging any part of an Ipil municipal street.', 'Public-versus-private street scope differs from Section 3G.01; municipal-street identity, applicant and responsible person, permit authority and numbering, duration basis, emergency work, extensions, start notice, validity, and enforcement require accepted procedure.', ['candidate_authority' => 'Office of the Municipal Mayor', 'candidate_timing' => 'before_excavation', 'candidate_required_permit_fact' => 'excavation_duration', 'known_scope_difference' => ['public_or_private_streets', 'municipal_streets']]),
            $this->policyBoundaryClause(2, 'MRC-3G-03-ENGINEER-SUPERVISION-WIDTH', RevenueCodeProvisionClauseType::AuthorityBoundary, 'The Municipal Engineer/ Municipal Building Official shall supervise the digging and excavation and shall determine the necessary width of the streets to be dug or excavated.', 'Candidate authority: the Municipal Engineer or Municipal Building Official supervises excavation and determines the necessary street width to be excavated.', 'Whether the source names alternatives, joint authority, or institutional succession; assignment, site inspection, approved dimensions, measurement record, safety and restoration standards, changes, and sign-off require accepted procedure.', ['candidate_authorities' => ['Municipal Engineer', 'Municipal Building Official'], 'candidate_responsibilities' => ['supervise_excavation', 'determine_necessary_width']]),
            $this->policyBoundaryClause(3, 'MRC-3G-03-DELAY-NOTICE-TO-TREASURER', RevenueCodeProvisionClauseType::AuthorityBoundary, 'Said official shall likewise inform the Municipal Treasurer of any delay in the completion of the excavation work for purposes of collection of the additional fee.', 'Candidate handoff: the responsible engineering/building official informs the Municipal Treasurer of completion delay for additional-fee collection.', 'Responsible official, completion and delay determination, approved extensions, notice content and timing, measured delay days, evidence, acknowledgement, assessment authority, disputes, and audit correlation require accepted procedure.', ['candidate_recipient' => 'Municipal Treasurer', 'candidate_purpose' => 'additional_delay_fee_collection']),
            $this->policyBoundaryClause(4, 'MRC-3G-03-PUBLIC-SAFETY-SIGNS', RevenueCodeProvisionClauseType::OperatingRestriction, 'In order to protect the public from any danger, appropriate signs must be placed in the arena where work is being done.', 'Candidate safety condition: place appropriate warning signs where excavation work is underway to protect the public.', 'The source says arena, likely rather than area; sign type, number, placement, visibility, lighting, barriers, responsible party, timing, inspection, other safety law, and enforcement require accepted procedure.', ['candidate_purpose' => 'public_safety', 'known_source_wording' => 'arena where work is being done']),
        ]);
    }

    private function seedWeightsMeasuresArticleHClauses(): void
    {
        $this->persistPolicyBoundaryClauses('MRC-3H-01-IMPLEMENTING-AGENCY', [
            $this->policyBoundaryClause(1, 'MRC-3H-01-TREASURER-CONSUMER-ACT-ENFORCEMENT', RevenueCodeProvisionClauseType::AuthorityBoundary, 'The Municipal Treasurer shall strictly enforce the provisions of the Regulation of Practices Relative to Weights and Measures, as provided in Chapter II of the Consumer Act, Republic Act No. 7394.', 'Candidate authority: the Municipal Treasurer enforces the incorporated weights-and-measures provisions of Republic Act No. 7394.', 'The incorporated provisions, current amendments, national and local jurisdiction, delegation, investigation, enforcement action, referral, due process, and audit evidence require legal and municipal validation.', ['candidate_authority' => 'Municipal Treasurer', 'external_authority' => 'Republic Act No. 7394, Chapter II']),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3H-02-SEALING-TESTING', [
            $this->policyBoundaryClause(1, 'MRC-3H-02-CONSUMER-TRANSACTION-INSTRUMENTS', RevenueCodeProvisionClauseType::Eligibility, 'All instruments for determining weights and measures in all consumer and consumer related transactions shall be tested, calibrated and sealed.', 'Candidate scope: instruments determining weights or measures in consumer and consumer-related transactions.', 'Instrument taxonomy, transaction scope, excluded scientific or industrial instruments, location, owner, operator, and shared-instrument treatment require accepted policy.', ['candidate_instrument_scope' => 'consumer_and_consumer_related_transactions']),
            $this->policyBoundaryClause(2, 'MRC-3H-02-SIX-MONTH-TEST-CALIBRATE-SEAL', RevenueCodeProvisionClauseType::CalibrationRequirement, 'All covered instruments shall be tested, calibrated and sealed every six (6) months.', 'Candidate cadence: test, calibrate, and seal every six months.', 'Cycle start, calendar computation, grace periods, defect-triggered retesting, annual-license relationship, calibration standard, tolerance, pass or fail criteria, and evidence require accepted procedure.', ['source_interval_months' => 6, 'candidate_actions' => ['test', 'calibrate', 'seal']]),
            $this->policyBoundaryClause(3, 'MRC-3H-02-OFFICIAL-SEALER', RevenueCodeProvisionClauseType::AuthorityBoundary, 'The official sealer shall be the Municipal Treasurer or his duly authorized representative upon payment of fees required under this Article.', 'Candidate authority: the Municipal Treasurer or a duly authorized representative seals covered instruments after required payment.', 'Delegation instrument, representative identity, segregation of duties, fee finalization, payment evidence, failed calibration, seal inventory, and audit require accepted procedure.', ['candidate_authorities' => ['Municipal Treasurer', 'duly authorized representative'], 'candidate_payment_prerequisite' => true]),
            $this->policyBoundaryClause(4, 'MRC-3H-02-CONTINUOUS-INSPECTION', RevenueCodeProvisionClauseType::InspectionRequirement, 'All instruments of weights and measures shall continuously be inspected for compliance with the provisions of this Article.', 'Candidate control: covered instruments remain subject to continuing compliance inspection.', 'The meaning and cadence of continuous inspection, risk selection, inspection authority, entry and access, evidence, defects, notices, corrective action, seizure, and appeal require accepted procedure.', ['candidate_inspection_cadence' => 'continuous']),
            $this->policyBoundaryClause(5, 'MRC-3H-02-IPIL-STICKER-IN-LIEU-OF-WAX', RevenueCodeProvisionClauseType::DocumentaryRequirement, 'Sticker officially printed for LGU of Ipil may be used in sealing weight and measure in lieu of sealing wax.', 'Candidate seal evidence: an officially printed LGU of Ipil sticker may replace sealing wax.', 'Sticker design, printer and custody authority, serial identity, issuance, placement, tamper evidence, replacement, cancellation, inventory reconciliation, counterfeit controls, and relationship to receipt-license evidence require accepted policy.', ['candidate_marker_options' => ['official_ipil_sticker', 'sealing_wax']]),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3H-03-FEES', [
            $this->policyBoundaryClause(1, 'MRC-3H-03-SEAL-LICENSE-ANNUALLY-BEFORE-USE', RevenueCodeProvisionClauseType::LicenseRequirement, 'Every person before using instruments of weights and measures within this municipality shall first have them sealed and licensed annually and pay therefore to the Municipal Treasurer.', 'Candidate prerequisite: before municipal use, seal and license each covered instrument annually and pay the Municipal Treasurer.', 'Person and instrument identity, municipal-use boundary, annual-cycle start, six-month testing relationship, fee identity, collector, receipt-license, failed calibration, and use prohibition require accepted procedure.', ['candidate_timing' => 'before_use', 'candidate_license_interval_months' => 12, 'candidate_collector' => 'Municipal Treasurer']),
            $this->weightsMeasureFeeClause(2, 'LINEAR-METRIC-MEASURES', 'For sealing linear metric measures for meter sticks chains, and tapes - P 25.00.', 'linear_metric_measure', 2_500, ['candidate_examples' => ['meter_sticks', 'chains', 'tapes']]),
            $this->weightsMeasureFeeClause(3, 'APOTHECARY-UP-TO-1000G', 'Apothecary Balances of Precision: 1,000 grams or less - P 50.00.', 'apothecary_balance_of_precision', 5_000, ['candidate_capacity_max_grams' => 1_000, 'candidate_max_inclusive' => true]),
            $this->weightsMeasureFeeClause(4, 'APOTHECARY-OVER-1000G', 'Apothecary Balances of Precision: Over 1,000 grams - P 100.00.', 'apothecary_balance_of_precision', 10_000, ['candidate_capacity_min_grams' => 1_000, 'candidate_min_inclusive' => false]),
            $this->weightsMeasureFeeClause(5, 'PLATFORM-UP-TO-25KG', 'Platform Scales: 25 kgs or less - P 50.00.', 'platform_scale', 5_000, ['candidate_capacity_max_grams' => 25_000, 'candidate_max_inclusive' => true]),
            $this->weightsMeasureFeeClause(6, 'PLATFORM-OVER-25-TO-100KG', 'Platform Scales: over 25 up to 100 kgs - P 100.00.', 'platform_scale', 10_000, ['candidate_capacity_min_grams' => 25_000, 'candidate_min_inclusive' => false, 'candidate_capacity_max_grams' => 100_000, 'candidate_max_inclusive' => true]),
            $this->weightsMeasureFeeClause(7, 'PLATFORM-OVER-100-TO-500KG', 'Platform Scales: over 100 up to 500 kgs - P 150.00.', 'platform_scale', 15_000, ['candidate_capacity_min_grams' => 100_000, 'candidate_min_inclusive' => false, 'candidate_capacity_max_grams' => 500_000, 'candidate_max_inclusive' => true]),
            $this->weightsMeasureFeeClause(8, 'PLATFORM-OVER-500-TO-2000KG', 'Platform Scales: over 500 up to 2,000 kgs - P 500.00.', 'platform_scale', 50_000, ['candidate_capacity_min_grams' => 500_000, 'candidate_min_inclusive' => false, 'candidate_capacity_max_grams' => 2_000_000, 'candidate_max_inclusive' => true]),
            $this->weightsMeasureFeeClause(9, 'PLATFORM-OVER-2000KG', 'Platform Scales: over 2,000 kgs - P 1,000.00.', 'platform_scale', 100_000, ['candidate_capacity_min_grams' => 2_000_000, 'candidate_min_inclusive' => false]),
            $this->weightsMeasureFeeClause(10, 'STEELYARD-UP-TO-25KG', 'Steelyards or Espada Type Scales: 25 kgs or less - P 25.00.', 'steelyard_or_espada_scale', 2_500, ['candidate_capacity_max_grams' => 25_000, 'candidate_max_inclusive' => true]),
            $this->weightsMeasureFeeClause(11, 'STEELYARD-OVER-25-TO-100KG', 'Steelyards or Espada Type Scales: over 25 up to 100 kgs - P 50.00.', 'steelyard_or_espada_scale', 5_000, ['candidate_capacity_min_grams' => 25_000, 'candidate_min_inclusive' => false, 'candidate_capacity_max_grams' => 100_000, 'candidate_max_inclusive' => true]),
            $this->weightsMeasureFeeClause(12, 'STEELYARD-OVER-100KG', 'Steelyards or Espada Type Scales: over 100 kgs - P 100.00.', 'steelyard_or_espada_scale', 10_000, ['candidate_capacity_min_grams' => 100_000, 'candidate_min_inclusive' => false]),
            $this->weightsMeasureFeeClause(13, 'CLOCK-UP-TO-5KG', 'Clock Type Scales: 5 kgs or less - P 40.00.', 'clock_type_scale', 4_000, ['candidate_capacity_max_grams' => 5_000, 'candidate_max_inclusive' => true]),
            $this->weightsMeasureFeeClause(14, 'CLOCK-OVER-5-TO-10KG', 'Clock Type Scales: over 5 up to 10 kgs - P 60.00.', 'clock_type_scale', 6_000, ['candidate_capacity_min_grams' => 5_000, 'candidate_min_inclusive' => false, 'candidate_capacity_max_grams' => 10_000, 'candidate_max_inclusive' => true]),
            $this->weightsMeasureFeeClause(15, 'CLOCK-ABOVE-10KG', 'Clock Type Scales: Above 10 kgs - P 100.00.', 'clock_type_scale', 10_000, ['candidate_capacity_min_grams' => 10_000, 'candidate_min_inclusive' => false]),
            $this->weightsMeasureFeeClause(16, 'BRONZE-WIRE-SEAL', 'Bronze Wire Seal - P 100.00.', 'bronze_wire_seal', 10_000, ['candidate_charge_object' => 'seal']),
            $this->policyBoundaryClause(17, 'MRC-3H-03-OFFSITE-RETEST-RESEAL-SERVICE', RevenueCodeProvisionClauseType::RateBand, 'For each and every re-testing and re-sealing of weights measures instruments including gasoline pumps outside the office upon request of the owner or operator, an additional service charge of fifty (50.00) pesos for each instrument shall be collected.', 'Candidate service charge: PHP 50.00 for each owner-requested instrument retested and resealed outside the office, including a gasoline pump.', 'Request authority, outside-office boundary, instrument identity and count, failed test, travel and repeat visits, base-fee interaction, gasoline-pump overlap with Article I, payer, collector, receipt, and operational amount require reconciliation.', ['candidate_service' => ['retest', 'reseal'], 'candidate_location' => 'outside_office', 'candidate_unit' => 'instrument', 'candidate_includes' => 'gasoline_pumps'], 5_000),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3H-04-PAYMENT-SURCHARGE', [
            $this->policyBoundaryClause(1, 'MRC-3H-04-PAY-AT-SEALING-BEFORE-USE-ANNIVERSARY', RevenueCodeProvisionClauseType::PaymentTiming, 'The fees herein imposed shall be paid and collected by the Municipal Treasurer when the weights or measuring instruments are sealed, before their use and thereafter, on or before the anniversary date thereof.', 'Candidate timing: the Treasurer collects at sealing before first use and by each anniversary thereafter.', 'Sealing and payment sequence, first-use evidence, anniversary computation, six-month test events, early or late processing, failed calibration, collector assignment, receipt, and renewal require accepted procedure.', ['candidate_collector' => 'Municipal Treasurer', 'candidate_timing' => ['at_sealing', 'before_first_use', 'on_or_before_anniversary']]),
            $this->policyBoundaryClause(2, 'MRC-3H-04-RECEIPT-LICENSE-ONE-YEAR-UNLESS-DEFECTIVE', RevenueCodeProvisionClauseType::LicenseRequirement, 'The official receipt serving as license to use the instrument is valid for one (1) year from the date of sealing unless such instrument becomes defective before the expiration period.', 'Candidate license evidence: the sealing-fee official receipt authorizes use for one year unless the instrument becomes defective earlier.', 'Receipt identity and numbering, instrument linkage, date of sealing, defect determination, suspension and reactivation, replacement evidence, transfer, display, and audit require accepted procedure.', ['candidate_license_evidence' => 'official_receipt', 'source_validity_years' => 1, 'candidate_early_expiry_trigger' => 'instrument_defective']),
            $this->policyBoundaryClause(3, 'MRC-3H-04-LATE-RETEST-FIVE-HUNDRED-PERCENT', RevenueCodeProvisionClauseType::SurchargeInterest, 'Failure to have the instrument re-tested and the corresponding fees therefore paid within the prescribed period shall subject the owner or user to a surcharge of five hundred percent (500%) of the prescribed fees.', 'Candidate late-treatment: charge 500 percent of prescribed fees when retesting and payment do not occur within the prescribed period.', 'Trigger date, owner or user liability, prescribed-fee base, meaning of 500 percent as surcharge versus total, six-month or annual period, grace, notices, partial compliance, multiple instruments, rounding, collection, and relationship to Section 3H.08 require legal and Treasury reconciliation.', ['stated_surcharge_percent' => '500.00', 'candidate_trigger' => 'retest_and_payment_not_completed_within_prescribed_period']),
            $this->policyBoundaryClause(4, 'MRC-3H-04-SURCHARGE-NO-INTEREST', RevenueCodeProvisionClauseType::SurchargeInterest, 'The five hundred percent surcharge shall no longer be subject to interest.', 'Candidate exclusion: do not add interest to the stated Article H late-retesting surcharge.', 'Whether only the surcharge or the underlying fee is interest-free, other Code interest provisions, payment allocation, partial payment, judgment obligations, and current legal authority require accepted policy.', ['candidate_interest_exclusion' => 'five_hundred_percent_surcharge']),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3H-05-PLACE-OF-PAYMENT', [
            $this->policyBoundaryClause(1, 'MRC-3H-05-BUSINESS-MUNICIPALITY', RevenueCodeProvisionClauseType::SitusDefinition, 'The fees herein levied shall be paid in the municipality where the business is conducted by persons conducting their business therein.', 'Candidate situs: a business user pays in the municipality where the business is conducted.', 'Business and establishment identity, instrument location and mobility, branch operations, temporary use, multiple municipalities, proof of prior payment, and inter-LGU recognition require accepted policy.', ['candidate_situs' => 'municipality_where_business_is_conducted']),
            $this->policyBoundaryClause(2, 'MRC-3H-05-ITINERANT-ONE-INSTRUMENT-RESIDENCE', RevenueCodeProvisionClauseType::SitusDefinition, 'A peddler or itinerant vendor using only one (1) instrument of weight or measure shall pay the fee in the municipality where he maintains his residence.', 'Candidate special situs: a peddler or itinerant vendor using one instrument pays in the municipality of residence.', 'Vendor classification, residence evidence, one-instrument counting, replacement instruments, business entities, changing residence, route municipalities, proof of payment, and more-than-one-instrument treatment require accepted policy.', ['candidate_actor' => 'peddler_or_itinerant_vendor', 'candidate_maximum_instruments' => 1, 'candidate_situs' => 'municipality_of_residence']),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3H-06-EXEMPTIONS', [
            $this->policyBoundaryClause(1, 'MRC-3H-06-GOVERNMENT-PUBLIC-USE-FREE', RevenueCodeProvisionClauseType::Exemption, 'All instruments for weights and measures used in government work or maintained for public use by any instrumentality of the government shall be tested and sealed free.', 'Candidate fee exemption: test and seal qualifying government-work or government-instrumentality public-use instruments without charge.', 'Government instrumentality, ownership, government-work and public-use definitions, mixed use, contractor instruments, proof, approval, whether licensing remains required, and audit require accepted policy.', ['candidate_fee_amount_cents' => 0, 'candidate_uses' => ['government_work', 'government_instrumentality_public_use']]),
            $this->policyBoundaryClause(2, 'MRC-3H-06-DEALER-INSTRUMENTS-FOR-SALE', RevenueCodeProvisionClauseType::Exemption, 'Dealers of weights and measures instruments intended for sale.', 'Candidate exemption: qualifying dealers are exempt for instruments held as inventory for sale.', 'The source is a sentence fragment; dealer identity, fee-versus-test exemption, inventory evidence, demonstration use, rental, consignment, repair, used instruments, conversion to operational use, and audit require accepted interpretation.', ['candidate_actor' => 'weights_and_measures_instrument_dealer', 'candidate_instrument_purpose' => 'intended_for_sale', 'known_source_fragment' => true]),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3H-07-ADMINISTRATION', [
            $this->policyBoundaryClause(1, 'MRC-3H-07-RECEIPT-LICENSE-EXPIRY', RevenueCodeProvisionClauseType::LicenseRequirement, 'The official receipt for the fee issued for the sealing of a weight or measure shall serves as a license to use such instrument for one year from the date of sealing, unless deterioration or damage renders the weight or measure inaccurate within that period. The license shall expire on the day and the month of the year following its original issuance.', 'Candidate license rule: the sealing receipt licenses one instrument until the corresponding day and month in the following year unless deterioration or damage causes inaccuracy earlier.', 'Receipt and instrument identity, grammatical defect, anniversary behavior, leap day, deterioration or damage finding, accuracy threshold, suspension, retest, and replacement evidence require accepted procedure.', ['candidate_license_evidence' => 'official_receipt', 'source_validity_years' => 1, 'candidate_early_expiry_triggers' => ['deterioration', 'damage_causing_inaccuracy']]),
            $this->policyBoundaryClause(2, 'MRC-3H-07-PRESERVE-EXHIBIT-LICENSE-INSTRUMENT', RevenueCodeProvisionClauseType::DocumentaryRequirement, 'Such license shall be preserved by the owner and together with the weight or measure covered by the license, shall be exhibited on demand by the Municipal Treasurer or his deputies.', 'Candidate evidence duty: preserve the receipt-license and exhibit it with its instrument on authorized demand.', 'Owner and custodian identity, physical or electronic evidence, instrument linkage, demand authority, inspection location, lost or damaged evidence, replacement, privacy, and enforcement require accepted procedure.', ['candidate_demand_authorities' => ['Municipal Treasurer', 'deputies']]),
            $this->policyBoundaryClause(3, 'MRC-3H-07-SECONDARY-STANDARDS-DOST-COMPARISON', RevenueCodeProvisionClauseType::CustodyProcedure, 'The Municipal Treasurer is hereby required to keep full sets of secondary standards, which shall be compared with the fundamental standards in the Department of Science and Technology annually.', 'Candidate standards control: the Treasurer keeps complete secondary standards and compares them annually with DOST fundamental standards.', 'Required set composition, custody, calibration chain, current DOST office and process, annual due date, transport, environmental conditions, traceability, cost, and continuity require accepted procedure.', ['candidate_custodian' => 'Municipal Treasurer', 'candidate_comparison_authority' => 'Department of Science and Technology', 'source_interval_months' => 12]),
            $this->policyBoundaryClause(4, 'MRC-3H-07-STANDARD-LABEL-CERTIFICATE-DESTRUCTION', RevenueCodeProvisionClauseType::DispositionProcedure, 'Sufficiently accurate secondary standards shall be distinguished by label, tag or seal and accompanied by a certificate showing variation from fundamental standards. If variation impairs utility, the instrument shall be destroyed at the Department of Science and Technology.', 'Candidate disposition: label and certify usable secondary standards with their variation; destroy a standard at DOST when variation impairs utility.', 'Accuracy tolerance, certificate format, identifier, authorized signatory, impairment threshold, quarantine, destruction authority and witness, replacement, disposal record, and audit require accepted procedure.', ['candidate_usable_markers' => ['label', 'tag', 'seal'], 'candidate_certificate_fact' => 'variation_from_fundamental_standard', 'candidate_destruction_location' => 'Department of Science and Technology']),
            $this->policyBoundaryClause(5, 'MRC-3H-07-PERIODIC-PHYSICAL-INSPECTION-TEST', RevenueCodeProvisionClauseType::InspectionRequirement, 'The Municipal Treasurer or his deputies shall conduct periodic physical inspection and test weight and measure instruments within the locality.', 'Candidate inspection authority: the Treasurer or deputies periodically inspect and test local instruments.', 'Deputy authorization, inspection cadence and selection, location access, notice, instrument registry, test standard, evidence, findings, corrective action, seizure, and appeal require accepted procedure.', ['candidate_authorities' => ['Municipal Treasurer', 'deputies'], 'candidate_actions' => ['physical_inspection', 'test']]),
            $this->policyBoundaryClause(6, 'MRC-3H-07-IRREPARABLE-CONFISCATE-DESTROY', RevenueCodeProvisionClauseType::DispositionProcedure, 'Instruments of weight and measures found to be defective and such defect is beyond repair shall be confiscated in favor of the government and shall be destroyed by the Municipal Treasurer in the presence of the Provincial Auditor or his representative.', 'Candidate disposition: confiscate an instrument determined defective beyond repair and destroy it with the stated officials present.', 'Defect and repairability standard, qualified determination, owner notice, hearing, custody, confiscation authority, appeal, government title, destruction method, Provincial Auditor role, evidence, and replacement require legal and municipal procedure.', ['candidate_trigger' => 'defective_beyond_repair', 'candidate_beneficiary' => 'government', 'candidate_destruction_authority' => 'Municipal Treasurer', 'candidate_witnesses' => ['Provincial Auditor', 'Provincial Auditor representative']]),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3H-08-PROHIBITED-PRACTICES', [
            $this->weightsMeasureProhibitedPracticeClause(1, 'UNAUTHORIZED-OFFICIAL-MARK', 'For any person other than the official sealer or his duly authorized representative to place an official tag, seal, sticker, mark, stamp, brand or other characteristic sign used to indicate official testing, calibration, sealing or inspection.', 'placing an official testing or sealing mark without official-sealer authority'),
            $this->weightsMeasureProhibitedPracticeClause(2, 'IMITATE-OFFICIAL-MARK', 'For any person to imitate any seal, sticker, mark stamp, brand, tag or other characteristic design used to indicate official testing, calibration, sealing or inspection.', 'imitating an official testing or sealing mark'),
            $this->weightsMeasureProhibitedPracticeClause(3, 'UNAUTHORIZED-ALTER-CERTIFICATE-RECEIPT', 'For any person other than the official sealer or his duly authorized representative to alter in any way the certificate or receipt given as acknowledgement that the instrument has been fully rested, calibrated, sealed or inspected.', 'altering official certificate or receipt evidence without official-sealer authority', ['known_source_wording' => 'fully rested, calibrated, sealed or inspected']),
            $this->weightsMeasureProhibitedPracticeClause(4, 'COUNTERFEIT-SEAL-CERTIFICATE-LICENSE', 'For any person to make or knowingly sell or use any false or counterfeit seal, sticker, brand, stamp, tag, certificate or license or any dye for printing or making the same.', 'making, knowingly selling, or using counterfeit sealing evidence or production tools'),
            $this->weightsMeasureProhibitedPracticeClause(5, 'UNAUTHORIZED-ALTER-OFFICIAL-FIGURES', 'For any person other than the official sealer or his duly authorized representative to alter the written or printed figures, letters or symbols on an official seal, sticker, receipt, stamp, tag, certificate or license.', 'altering figures, letters, or symbols on official evidence without official-sealer authority'),
            $this->weightsMeasureProhibitedPracticeClause(6, 'USE-RESTORED-ALTERED-EXPIRED-DAMAGED-EVIDENCE', 'For any person to use or reuse any restored, altered, expired, damaged stamp, damaged certificate or license to make it appear that the instrument has been tested, calibrated, sealed or inspected.', 'using or reusing compromised or expired evidence to represent official compliance'),
            $this->weightsMeasureProhibitedPracticeClause(7, 'USE-UNSEALED-OR-EXPIRED-INSTRUMENT', 'For a person buying or selling consumer products or furnishing services valued by weight or measure to possess, use or maintain with intention to use any scale, balance, weight or measure that has not been sealed or whose license has expired and was not renewed in due time.', 'commercial possession, use, or intended use of an unsealed or expired instrument'),
            $this->weightsMeasureProhibitedPracticeClause(8, 'ALTER-INSTRUMENT-AFTER-SEALING', 'For any person to fraudulently alter any scale, balance, weight or measure after it is officially sealed.', 'fraudulently altering an instrument after official sealing'),
            $this->weightsMeasureProhibitedPracticeClause(9, 'KNOWINGLY-USE-FALSE-INSTRUMENT', 'For any person to knowingly use any false scale, balance, weight or measure, whether sealed or not.', 'knowingly using a false instrument regardless of sealing'),
            $this->weightsMeasureProhibitedPracticeClause(10, 'GIVE-SHORT-WEIGHT-MEASURE', 'For any person to fraudulently give short weight or measure in the making of a scale.', 'fraudulently giving short weight or measure', ['known_source_wording' => 'in the making of a scale']),
            $this->weightsMeasureProhibitedPracticeClause(11, 'MISREPRESENT-WEIGHT-MEASURE', 'For any person, assuming to determine truly the weight or measure of an article bought or sold by weight or measure, to fraudulently misrepresent its weight or measure.', 'fraudulently misrepresenting an article weight or measure'),
            $this->weightsMeasureProhibitedPracticeClause(12, 'PROCURE-OFFENSE', 'For any person to procure the commission of any such offense above mentioned by another.', 'procuring another person to commit an Article H prohibited practice'),
            $this->policyBoundaryClause(13, 'MRC-3H-08-INTACT-ACCURATE-PROMPT-RESEAL', RevenueCodeProvisionClauseType::CalibrationRequirement, 'Instruments officially sealed at some previous time which have remained unaltered and accurate and whose seal or tag remains intact and in the same position and condition shall, if presented for sealing, be sealed promptly on demand by the official sealer or authorized representative.', 'Candidate resealing treatment: promptly reseal a previously sealed instrument that remains accurate, unaltered, and bears an intact unchanged official marker.', 'Accuracy evidence, unchanged condition, seal identity, presentation timing, demand handling, test requirement, official-sealer availability, record linkage, and relationship to six-month and annual cycles require accepted procedure.', ['candidate_conditions' => ['previously_officially_sealed', 'unaltered', 'accurate', 'seal_or_tag_intact_and_unchanged']]),
            $this->policyBoundaryClause(14, 'MRC-3H-08-TWO-TIMES-RESEAL-SURCHARGE', RevenueCodeProvisionClauseType::SurchargeInterest, 'Such an instrument shall be sealed promptly without penalty except a surcharge equal to two (2) times the regular fee fixed by law for sealing an instrument of its class, collected and accounted for by the Municipal Treasurer as regular sealing fees.', 'Candidate surcharge: collect two times the regular class sealing fee for the stated resealing condition.', 'The source says without penalty except a surcharge; trigger, regular-fee version and class, whether two times is additional or total, relationship to the 500% Section 3H.04 surcharge, payment timing, accounting, rounding, waiver, and legal authority require reconciliation.', ['stated_surcharge_multiple' => '2', 'candidate_base' => 'regular_sealing_fee_for_instrument_class', 'candidate_collector' => 'Municipal Treasurer', 'known_wording_conflict' => 'without_penalty_except_surcharge']),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3H-09-PENALTIES', [
            $this->policyBoundaryClause(1, 'MRC-3H-09-A-F-PENALTY', RevenueCodeProvisionClauseType::Penalty, 'A violation of paragraphs (a) to (f) of Section 3H.08 shall, upon conviction, be subject to a fine of not less than P500.00 but not more than P1,000.00 or imprisonment of not more than one (1) month, but not more than six (6) months.', 'Candidate judicial band: for paragraphs (a)-(f), fine PHP 500.00 to PHP 1,000.00 or imprisonment under the malformed printed duration.', 'The imprisonment text has two different maxima; offense classification, conviction, fine boundary inclusivity, imprisonment range, election or combination, court discretion, current national law, municipal penalty ceiling, and legal validity require counsel confirmation.', ['candidate_paragraphs' => ['a', 'b', 'c', 'd', 'e', 'f'], 'candidate_minimum_fine_cents' => 50_000, 'candidate_maximum_fine_cents' => 100_000, 'source_imprisonment_text' => 'not more than one (1) month, but not more than six (6) months', 'known_duration_conflict' => true]),
            $this->policyBoundaryClause(2, 'MRC-3H-09-G-FIRST-OFFENSE-PENALTY', RevenueCodeProvisionClauseType::Penalty, 'A first violation of paragraph (g) of Section 3H.08 shall be subject to a fine of not less than P500.00 or imprisonment of not less than one (1) month but not more than five (5) years, or both, upon discretion of the court.', 'Candidate judicial band: first paragraph (g) offense carries at least PHP 500.00 fine, imprisonment from one month to five years, or both.', 'No maximum fine is printed; first-offense identity, conviction, imprisonment scale and statutory authority, court discretion, municipal penalty ceiling, successor offenses, and legal validity require counsel confirmation.', ['candidate_paragraphs' => ['g'], 'candidate_offense_ordinal' => 1, 'candidate_minimum_fine_cents' => 50_000, 'candidate_maximum_fine_cents' => null, 'source_imprisonment_minimum_months' => 1, 'source_imprisonment_maximum_months' => 60]),
            $this->policyBoundaryClause(3, 'MRC-3H-09-H-L-PENALTY', RevenueCodeProvisionClauseType::Penalty, 'The owner-possessor or user of an instrument enumerated in paragraphs (h) to (l) of Section 3H.08 shall, upon conviction, be subject to a fine of not less than P500.00 or imprisonment not exceeding one (1) year, or both, upon discretion of the court.', 'Candidate judicial band: a covered owner, possessor, or user convicted under paragraphs (h)-(l) faces at least PHP 500.00 fine, up to one year imprisonment, or both.', 'The actor wording may not fit every paragraph and no maximum fine is printed; offense and actor mapping, conviction, court discretion, municipal penalty ceiling, imprisonment authority, and legal validity require counsel confirmation.', ['candidate_paragraphs' => ['h', 'i', 'j', 'k', 'l'], 'candidate_actors' => ['owner', 'possessor', 'user'], 'candidate_minimum_fine_cents' => 50_000, 'candidate_maximum_fine_cents' => null, 'source_imprisonment_maximum_months' => 12]),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3H-10-COMPROMISE', [
            $this->policyBoundaryClause(1, 'MRC-3H-10-NON-FRAUD-COMPROMISE-MINIMUM', RevenueCodeProvisionClauseType::CompromisePenalty, 'At any time after apprehension of a taxpayer for violation of this article not involving fraud, before complaints are filed in the proper court, the Municipal Treasurer is authorized to impose a compromise penalty of not less than P500.00.', 'Candidate compromise boundary: after apprehension and before court filing, the Treasurer may impose at least PHP 500.00 for an eligible non-fraud Article H violation.', 'Apprehension and complaint-filing timestamps, taxpayer and offense identity, fraud determination, eligible violations, Treasurer discretion, absent maximum, taxpayer consent, settlement effect, repeat offenses, receipt and fund accounting, audit, referral, and current legal authority require accepted policy.', ['candidate_authority' => 'Municipal Treasurer', 'candidate_minimum_amount_cents' => 50_000, 'candidate_maximum_amount_cents' => null, 'candidate_window' => ['after_apprehension', 'before_court_complaint'], 'candidate_exclusion' => 'fraud']),
        ]);
    }

    private function seedDispensingPumpsArticleIClauses(): void
    {
        $this->persistPolicyBoundaryClauses('MRC-3I-01-CALIBRATION-SEALING', [
            $this->policyBoundaryClause(1, 'MRC-3I-01-IPIL-LIQUID-PETROLEUM-RETAIL-PUMPS', RevenueCodeProvisionClauseType::Eligibility, 'All dispensing pumps used in Retail Outlets of Liquid Petroleum Products within the Municipality of Ipil must comply with this section.', 'Candidate scope: dispensing pumps used by liquid-petroleum-product retail outlets in Ipil.', 'Retail outlet, liquid petroleum product, dispensing pump, nozzle, owner, operator, brand, outlet location, temporary or mobile equipment, and inactive-pump identity require accepted policy and registry evidence.', ['candidate_location' => 'Municipality of Ipil', 'candidate_outlet_type' => 'Retail Outlet of Liquid Petroleum Products']),
            $this->policyBoundaryClause(2, 'MRC-3I-01-CALIBRATE-TWICE-YEARLY', RevenueCodeProvisionClauseType::CalibrationRequirement, 'All covered dispensing pumps must be properly calibrated twice a year.', 'Candidate cadence: properly calibrate each covered dispensing pump twice yearly.', 'Cycle start, meaning of twice a year, due dates, grace, new or replaced pumps, repair-triggered calibration, technical standard, tolerance, qualified mechanic, pass or fail result, and evidence require accepted procedure.', ['source_frequency_per_year' => 2, 'candidate_action' => 'calibrate']),
            $this->policyBoundaryClause(3, 'MRC-3I-01-TREASURER-SEAL-AFTER-CALIBRATION', RevenueCodeProvisionClauseType::AuthorityBoundary, 'Covered pumps must be sealed by the Treasurer or his/her authorized representative immediately after calibration.', 'Candidate authority: the Treasurer or an authorized representative seals a pump immediately after calibration.', 'Treasurer identity, delegation, successful-calibration prerequisite, meaning of immediately, mechanic and sealer segregation, seal identity and inventory, pump or nozzle placement, failed calibration, payment, and audit require accepted procedure.', ['candidate_authorities' => ['Treasurer', 'authorized representative'], 'candidate_timing' => 'immediately_after_calibration']),
            $this->policyBoundaryClause(4, 'MRC-3I-01-OUT-OF-ORDER-NO-USE-UNTIL-RESEALED', RevenueCodeProvisionClauseType::OperatingRestriction, 'A dispensing pump that is not calibrated and sealed or goes off-calibration shall be marked with an “OUT OF ORDER” sign and shall not be used until the pump is calibrated and resealed.', 'Candidate safety and trading control: visibly mark and stop use of an uncalibrated, unsealed, or off-calibration pump until successful calibration and resealing.', 'Off-calibration finding, actor authorized to mark or disable, sign standard and placement, padlock relationship, stop-use time, notice, repair, retest, reseal, release to service, emergency action, enforcement, and audit require accepted procedure.', ['candidate_triggers' => ['not_calibrated_and_sealed', 'off_calibration'], 'candidate_marker' => 'OUT OF ORDER', 'candidate_release_conditions' => ['calibrated', 'resealed']]),
            $this->policyBoundaryClause(5, 'MRC-3I-01-CALIBRATION-DOCUMENT-SIGNATURES', RevenueCodeProvisionClauseType::DocumentaryRequirement, 'All calibration shall be dully documented and signed by the mechanic performing the calibration and countersigned by the Retail Outlet owner and or/operator of the shift supervisor of the Retail Outlet.', 'Candidate calibration evidence: a calibration record is signed by the performing mechanic and countersigned by a stated outlet representative.', 'The source says dully and has malformed owner/operator/shift-supervisor alternatives. Required fields, mechanic qualification and identity, authorized countersignatory, electronic signatures, timestamp, pump and nozzle linkage, results, corrections, and authenticity require accepted procedure.', ['known_source_wording' => ['dully documented', 'owner and or/operator of the shift supervisor'], 'candidate_signer' => 'performing_mechanic', 'candidate_countersignatories' => ['retail_outlet_owner', 'operator', 'shift_supervisor']]),
            $this->policyBoundaryClause(6, 'MRC-3I-01-OUTLET-COPY-RETENTION', RevenueCodeProvisionClauseType::RecordRetention, 'A copy of these calibration documents shall kept on file at the Retail Outlet.', 'Candidate custody: retain a copy of calibration records at the retail outlet.', 'The source omits “be”; record format, original-versus-copy authority, retention period, physical or electronic location, availability during inspection, loss or replacement, privacy, migration, and disposition require accepted procedure.', ['candidate_custodian' => 'Retail Outlet', 'candidate_record' => 'calibration_document', 'source_retention_period' => null, 'known_source_grammar_defect' => true]),
            $this->policyBoundaryClause(7, 'MRC-3I-01-INDEPENDENT-BRAND-SECTION-4-RESPONSIBILITY', RevenueCodeProvisionClauseType::AuthorityBoundary, 'For independently owned Retail Outlet with its own liquid petroleum Product Brand Name, the Owner and/or operator of the said Retail Outlet shall be responsible for complying with Section 4.', 'Candidate responsibility: an independently owned own-brand outlet places the referenced compliance duty on its owner or operator.', 'Section 4 likely refers to Section 3I.04 but is not normalized; independently owned and own-brand eligibility, owner-versus-operator responsibility, corporate identity, franchise relationships, joint liability, exact duty, and evidence require legal and municipal confirmation.', ['candidate_actors' => ['owner', 'operator'], 'candidate_outlet_condition' => ['independently_owned', 'own_liquid_petroleum_product_brand'], 'source_cross_reference' => 'Section 4', 'candidate_cross_reference' => null]),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3I-02-UNDERDELIVERY', [
            $this->policyBoundaryClause(1, 'MRC-3I-02-FIFTY-MILLIMETERS-PER-TEN-LITERS', RevenueCodeProvisionClauseType::CalibrationRequirement, 'The quantity of Liquid Petroleum Products delivered by the dispensing pump meter shall not be less than the actual quantity by more than fifty (50) millimeters for every ten (10) liters.', 'Candidate source tolerance: no more than the printed fifty “millimeters” difference for each ten liters.', 'Millimeters measure length rather than liquid volume and are not silently normalized to milliliters. Direction of comparison, tolerance arithmetic, absolute versus relative error, decimal precision, rounding, temperature effects, product type, and legal metrology standard require technical and legal reconciliation.', ['source_tolerance_value' => 50, 'source_tolerance_unit' => 'millimeters', 'source_reference_quantity_liters' => 10, 'known_dimensional_conflict' => true]),
            $this->policyBoundaryClause(2, 'MRC-3I-02-DOST-ITDI-CERTIFIED-BUCKET', RevenueCodeProvisionClauseType::DocumentaryRequirement, 'The comparison shall be measured by a calibrating bucket certified and sealed by the DOST-ITDI.', 'Candidate test evidence: use a calibrating bucket bearing current DOST-ITDI certification and seal.', 'Current agency identity, bucket capacity and serial identity, certification and seal validity, calibration uncertainty, custody, inspection, damage, traceability, replacement, and evidence retention require accepted procedure.', ['candidate_authority' => 'DOST-ITDI', 'candidate_reference_instrument' => 'calibrating_bucket']),
            $this->policyBoundaryClause(3, 'MRC-3I-02-THREE-FLOW-RATE-AVERAGE', RevenueCodeProvisionClauseType::InspectionRequirement, 'The calibrating bucket shall be filled to the ten (10) liter mark three (3) times at low, medium and fast flow rates and the average quantity as measured with the actual quantity of ten (10) liters.', 'Candidate test protocol: perform one ten-liter run at each low, medium, and fast flow rate and compare the average measured quantity with ten liters.', 'Whether three total runs or three runs per flow rate are required, flow-rate definitions and controls, run order, stabilization, bucket reading, average formula, rejected runs, rounding, environmental correction, witness, and record fields require accepted technical procedure.', ['source_reference_quantity_liters' => 10, 'source_run_count' => 3, 'source_flow_rates' => ['low', 'medium', 'fast'], 'candidate_aggregate' => 'average']),
            $this->policyBoundaryClause(4, 'MRC-3I-02-UNDERDELIVERING-DEFINITION', RevenueCodeProvisionClauseType::Definition, 'Dispensing pumps delivering less than the tolerable minimum quantity shall be deemed to be UNDERDELIVERING.', 'Candidate classification: a pump below the accepted minimum delivery tolerance is underdelivering.', 'The minimum cannot be calculated until the malformed tolerance unit, direction, test protocol, averaging, rounding, and technical standard are accepted; finding authority, retest, uncertainty, notice, and rebuttal also require procedure.', ['candidate_term' => 'UNDERDELIVERING', 'candidate_trigger' => 'delivery_below_tolerable_minimum']),
            $this->policyBoundaryClause(5, 'MRC-3I-02-ACTUAL-USE-PRESUMPTION', RevenueCodeProvisionClauseType::EvidentiaryPresumption, 'The absence of an “OUT OF ORDER” sign or padlock locking the dispensing pump shall be deemed an actual use of the pump for the conduct of retailing.', 'Candidate evidentiary presumption: absence of the stated sign or locking padlock is treated as retail use.', 'The source uses “or,” making it unclear whether either or both controls must be present. Inspection timing, sign and padlock standards, operability, power state, maintenance, evidence, rebuttal, burden of proof, responsible actor, and due process require legal and municipal policy.', ['candidate_presumed_fact' => 'actual_retail_use', 'source_control_logic' => 'absence_of_out_of_order_sign_or_padlock', 'known_boolean_ambiguity' => true]),
            $this->policyBoundaryClause(6, 'MRC-3I-02-BROKEN-NO-SEAL-PRIMA-FACIE', RevenueCodeProvisionClauseType::EvidentiaryPresumption, 'A dispensing pump found with a broken or no seal shall constitute a prima facie evidence of UNDERDELIVERING.', 'Candidate evidentiary presumption: a broken or absent pump seal is prima facie evidence of underdelivery.', 'Seal identity, authorized placement, breakage cause, accidental damage, missing-versus-never-issued status, inspection and photograph evidence, chain of custody, relation to actual calibration, rebuttal standard, burden of proof, notice, and due process require legal and municipal procedure.', ['candidate_evidence' => ['broken_seal', 'no_seal'], 'candidate_presumed_fact' => 'UNDERDELIVERING', 'candidate_evidence_standard' => 'prima_facie']),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3I-03-SANCTIONS', [
            $this->policyBoundaryClause(1, 'MRC-3I-03-ILLEGAL-TRADING-OR-UNDERDELIVERY-SCOPE', RevenueCodeProvisionClauseType::Eligibility, 'Any person engaged in the business of Retailing Liquid Petroleum Products who commits illegal Trading and/or Underdelivering shall be subject to the following penalties.', 'Candidate sanction scope: a liquid-petroleum retailer found to have committed illegal trading, underdelivery, or both.', 'Illegal trading is undefined; person, business, outlet, pump, and responsible-officer identity; finding authority; evidentiary standard; relationship between conduct types; corporate liability; notice; hearing; and conviction or administrative-order requirements require legal interpretation.', ['candidate_actor' => 'liquid_petroleum_product_retailer', 'candidate_conduct' => ['illegal_trading', 'underdelivering'], 'known_undefined_term' => 'illegal Trading']),
            $this->policyBoundaryClause(2, 'MRC-3I-03-FIRST-OFFENSE-FINE', RevenueCodeProvisionClauseType::Penalty, 'FIRST OFFENSE - fine of Five Thousand Pesos (P5,000.00).', 'Candidate first-offense sanction: PHP 5,000.00 fine.', 'Offense identity and counting unit, prior-final-order requirement, actor and outlet scope, issuing authority, notice and hearing, statutory ceiling, collection and receipt, appeal, and relationship to Article H or other penalties require legal and municipal acceptance.', ['candidate_offense_ordinal' => 1, 'candidate_fine_cents' => 500_000]),
            $this->policyBoundaryClause(3, 'MRC-3I-03-SECOND-SUCCEEDING-FINE-REVOCATION-CLOSURE', RevenueCodeProvisionClauseType::Penalty, 'SECOND OFFENSE & SUCCEEDING OFFENSES - fine of Five Thousand Pesos (5,000.00) and revocation of permit to operate business and permanent closure of business.', 'Candidate repeat-offense sanctions: PHP 5,000.00 fine, permit revocation, and permanent business closure.', 'Offense counting and finality, same outlet or operator, fine authority, permit identity, revocation and permanent-closure authority, proportionality, notice and hearing, closure execution, inventory and consumer safety, appeal, successor ownership, correction, and reopening require legal and municipal policy.', ['candidate_offense_ordinal_minimum' => 2, 'candidate_fine_cents' => 500_000, 'candidate_additional_sanctions' => ['permit_revocation', 'permanent_business_closure']]),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3I-04-FEES', [
            $this->policyBoundaryClause(1, 'MRC-3I-04-REGISTRATION-SEALING-CALIBRATION-SCOPE', RevenueCodeProvisionClauseType::AuthorityBoundary, 'The following are hereby levied for the registration sealing and calibrating of every dispensing pump.', 'Candidate fee scope: Article I contemplates registration, sealing, and calibration charges for every dispensing pump.', 'Only registration and sealing/tagging rows have aligned amounts; no separate calibration amount is printed. Pump-versus-nozzle identity, covered events, separate or bundled services, cadence, Article H overlap, payer, collector, and operational schedule require reconciliation.', ['candidate_services' => ['registration', 'sealing', 'calibration'], 'source_services_with_amounts' => ['registration', 'sealing_and_tagging'], 'source_services_without_amounts' => ['calibration']]),
            $this->policyBoundaryClause(2, 'MRC-3I-04-REGISTRATION-PER-NOZZLE', RevenueCodeProvisionClauseType::RateBand, 'Registration - P75.00/nozzle.', 'Candidate source amount: PHP 75.00 for registration of each nozzle.', 'Registration event, pump and nozzle identity, outlet and owner linkage, initial versus renewal cadence, replacements, inactive nozzles, payer, collector, receipt or certificate, exemptions, and accepted operational amount require reconciliation.', ['candidate_service' => 'registration', 'candidate_unit' => 'nozzle'], 7_500),
            $this->policyBoundaryClause(3, 'MRC-3I-04-SEALING-TAGGING-PER-NOZZLE', RevenueCodeProvisionClauseType::RateBand, 'Sealing and Tagging - P125.00/nozzle.', 'Candidate source amount: PHP 125.00 for sealing and tagging each nozzle.', 'Successful calibration prerequisite, sealing and tagging identity, seal or tag inventory, pump and nozzle linkage, initial or repeat cadence, replacements, payer, collector, receipt, Article H retesting charge overlap, and accepted operational amount require reconciliation.', ['candidate_service' => ['sealing', 'tagging'], 'candidate_unit' => 'nozzle'], 12_500),
        ]);
    }

    private function seedFilmingArticleJClauses(): void
    {
        $this->persistPolicyBoundaryClauses('MRC-3J-01-FILMING-FEES', [
            $this->policyBoundaryClause(1, 'MRC-3J-01-LOCATION-FILMING-PERMIT-SCOPE', RevenueCodeProvisionClauseType::PermitRequirement, 'There shall be collected the following permit fee from any person who shall go on location-filming within the territorial jurisdiction of this municipality.', 'Candidate permit scope: charge a covered person conducting location-filming within Ipil.', 'Person, producer, client, crew and responsible payer identity; location-filming definition; still photography, news, private event, livestream and incidental recording treatment; municipal-boundary evidence; project, location and day count; permit identity; exemptions; and authorization require accepted policy.', ['candidate_activity' => 'location_filming', 'candidate_location' => 'territorial_jurisdiction_of_municipality', 'candidate_charge' => 'permit_fee']),
            $this->policyBoundaryClause(2, 'MRC-3J-01-COMMERCIAL-MOVIE-PER-FILM', RevenueCodeProvisionClauseType::RateBand, 'Commercial movies - P500.00/film.', 'Candidate source amount: PHP 500.00 for each commercial movie film.', 'Commercial-movie classification, film identity, project and episode treatment, location and shooting-day count, extensions, payer, collector, exemptions, cancellation and refund, and accepted operational amount require reconciliation.', ['candidate_activity_class' => 'commercial_movie', 'candidate_unit' => 'film'], 50_000),
            $this->policyBoundaryClause(3, 'MRC-3J-01-COMMERCIAL-ADVERTISEMENT-PER-FILM', RevenueCodeProvisionClauseType::RateBand, 'Commercial/ Advertisements - P300.00/film.', 'Candidate source amount: PHP 300.00 for each commercial or advertisement film.', 'The source slash and plural wording leave category boundaries unclear. Commercial, advertisement, promotional, social-media and sponsored-content classification; film identity; location and day count; extensions; payer; exemptions; and accepted operational amount require reconciliation.', ['source_activity_label' => 'Commercial/ Advertisements', 'candidate_activity_class' => 'commercial_or_advertisement', 'candidate_unit' => 'film', 'known_category_wording_ambiguity' => true], 30_000),
            $this->policyBoundaryClause(4, 'MRC-3J-01-DOCUMENTARY-PER-FILM', RevenueCodeProvisionClauseType::RateBand, 'Documentary film - P200.00/film.', 'Candidate source amount: PHP 200.00 for each documentary film.', 'Documentary classification, public-interest and educational productions, news or government coverage, film identity, location and shooting-day count, extensions, exemptions, payer, and accepted operational amount require reconciliation.', ['candidate_activity_class' => 'documentary_film', 'candidate_unit' => 'film'], 20_000),
            $this->policyBoundaryClause(5, 'MRC-3J-01-VIDEO-PER-COVERAGE', RevenueCodeProvisionClauseType::RateBand, 'Video coverage - P150.00/coverage.', 'Candidate source amount: PHP 150.00 for each video coverage.', 'Coverage-event identity, private and public events, news, livestream, multiple cameras or days, locations, relationship to the Article title’s “Video Tape Coverage,” extensions, exemptions, payer, and accepted operational amount require reconciliation.', ['source_article_term' => 'Video Tape Coverage', 'candidate_activity_class' => 'video_coverage', 'candidate_unit' => 'coverage'], 15_000),
            $this->policyBoundaryClause(6, 'MRC-3J-01-EXTENSION-ADDITIONAL-PREPAYMENT', RevenueCodeProvisionClauseType::PaymentTiming, 'In case of extension of filming time, the additional amount required must be paid prior to extension to filming time.', 'Candidate extension boundary: collect the required additional amount before an approved filming-time extension begins.', 'The source does not state the extension time unit or additional-amount formula. Original permit identity, request and approval authority, new end time, category and rate reuse, partial periods, number of locations, payment deadline, receipt, denial, overrun, enforcement, cancellation and refund require accepted procedure.', ['candidate_trigger' => 'filming_time_extension', 'candidate_payment_timing' => 'before_extension_begins', 'source_extension_unit' => null, 'source_additional_amount_formula' => null]),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3J-02-PAYMENT-TIMING', [
            $this->policyBoundaryClause(1, 'MRC-3J-02-TREASURER-SEVEN-DAYS-BEFORE', RevenueCodeProvisionClauseType::PaymentTiming, 'The fee imposed herein shall be paid to the Municipal Treasurer upon application for the Mayor’s Permit seven (7) days before location-filming is commenced.', 'Candidate payment boundary: pay the Municipal Treasurer when applying for the stated permit at least seven days before location-filming starts.', 'Whether the seven days govern application, payment, or both; calendar-versus-business-day counting; commencement timestamp and timezone; Mayor’s Permit versus filming-permit identity; Treasurer delegation; rescheduling; late applications; payment channel; receipt; rejection; cancellation; refund; and enforcement require accepted municipal procedure.', ['candidate_payee' => 'Municipal Treasurer', 'source_permit_term' => 'Mayor’s Permit', 'source_lead_days' => 7, 'candidate_event' => 'location_filming_commencement', 'known_timing_attachment_ambiguity' => true]),
        ]);
    }

    private function seedEquipmentArticleKClauses(): void
    {
        $this->persistPolicyBoundaryClauses('MRC-3K-01-ANNUAL-EQUIPMENT-FEES', [
            $this->policyBoundaryClause(1, 'MRC-3K-01-NONRESIDENT-OR-RENTED-EQUIPMENT-SCOPE', RevenueCodeProvisionClauseType::Eligibility, 'There shall be collected an annual permit fee at the following rates for every agricultural machinery or heavy equipment from non-resident operators of said machinery, or equipment renting out said machinery/equipment in this municipality.', 'Candidate fee scope: annual equipment permit charges apply under the source’s non-resident-operator or municipal-rental conditions.', 'The sentence does not clearly identify the second actor or whether “or” creates two independent eligibility branches. Operator, owner, lessor, lessee, payer, residence, equipment, rental transaction and municipal-use identity; annual term; permit scope; exemptions; and evidence require accepted policy.', ['source_actor_condition' => 'non-resident operators', 'source_alternative_condition' => 'equipment renting out said machinery/equipment in this municipality', 'source_boolean_operator' => 'or', 'known_grammar_ambiguity' => true, 'candidate_frequency' => 'annual']),
            $this->equipmentPermitFeeClause(2, 'HANDTRACTORS', 'Handtractors - 250.00 per annum.', 'handtractors', 25_000),
            $this->equipmentPermitFeeClause(3, 'LIGHT-TRACTOR', 'Light Tractor - 300.00 per annum.', 'light_tractor', 30_000),
            $this->equipmentPermitFeeClause(4, 'HEAVY-TRACTOR', 'Heavy Tractor - 350.00 per annum.', 'heavy_tractor', 35_000),
            $this->equipmentPermitFeeClause(5, 'BULLDOZERS', 'Bulldozers - 1,500.00 per annum.', 'bulldozers', 150_000),
            $this->equipmentPermitFeeClause(6, 'FORKLIFT', 'Forklift - 350.00 per annum.', 'forklift', 35_000),
            $this->equipmentPermitFeeClause(7, 'HEAVY-GRADER', 'Heavy Grader - 600.00 per annum.', 'heavy_grader', 60_000),
            $this->equipmentPermitFeeClause(8, 'LIGHT-GRADER', 'Light Grader - 600.00 per annum.', 'light_grader', 60_000),
            $this->equipmentPermitFeeClause(9, 'MECHANIZED-TRESHERS', 'Mechanized Treshers - 350.00 per annum.', 'mechanized_treshers', 35_000, ['known_source_spelling' => 'Treshers']),
            $this->equipmentPermitFeeClause(10, 'MANUAL-TRESHERS', 'Manual Treshers - 150.00 per annum.', 'manual_treshers', 15_000, ['known_source_spelling' => 'Treshers']),
            $this->equipmentPermitFeeClause(11, 'CARGO-TRUCK', 'Cargo Truck - 350.00 per annum.', 'cargo_truck', 35_000),
            $this->equipmentPermitFeeClause(12, 'DUMP-TRUCK', 'Dump Truck - 350.00 per annum.', 'dump_truck', 35_000),
            $this->equipmentPermitFeeClause(13, 'ROAD-ROLLERS', 'Road Rollers - 350.00 per annum.', 'road_rollers', 35_000),
            $this->equipmentPermitFeeClause(14, 'PAYLOADER', 'Payloader - 350.00 per annum.', 'payloader', 35_000),
            $this->equipmentPermitFeeClause(15, 'PRIMEMOVERS-FLATBEDS', 'Primemovers/Flatbeds - 350.00 per annum.', 'primemovers_or_flatbeds', 35_000),
            $this->equipmentPermitFeeClause(16, 'BACKHOE', 'Backhoe - 600.00 per annum.', 'backhoe', 60_000),
            $this->equipmentPermitFeeClause(17, 'ROCKCRUSHER', 'Rockcrusher - 600.00 per annum.', 'rockcrusher', 60_000),
            $this->equipmentPermitFeeClause(18, 'BATCHING-PLANT', 'Batching Plant - 500.00 per annum.', 'batching_plant', 50_000),
            $this->equipmentPermitFeeClause(19, 'TRANSIT-MIXER-TRUCK', 'Transit/Mixer Truck - 600.00 per annum.', 'transit_or_mixer_truck', 60_000),
            $this->equipmentPermitFeeClause(20, 'CRANE', 'Crane - 350.00 per annum.', 'crane', 35_000),
            $this->policyBoundaryClause(21, 'MRC-3K-01-OTHER-UNENUMERATED-NO-AMOUNT', RevenueCodeProvisionClauseType::Eligibility, 'Other agricultural machinery or heavy equipment not enumerated above.', 'Candidate catch-all category: other unenumerated agricultural machinery or heavy equipment is contemplated by the schedule.', 'No amount is aligned with the parent catch-all. Whether it is merely a heading for t.1 and t.2 or covers all other equipment, classification authority, comparable-rate treatment, prohibition on collection without an amount, additions, and operational handling require municipal and legal reconciliation.', ['candidate_equipment_class' => 'other_unenumerated_agricultural_machinery_or_heavy_equipment', 'source_amount_cents' => null, 'known_missing_parent_amount' => true]),
            $this->equipmentPermitFeeClause(22, 'FOUR-WHEEL-DRIVE-RICE-FARM-TRACTOR', 'Four-wheel Drive Rice farm Tractor - 500.00 per annum.', 'four_wheel_drive_rice_farm_tractor', 50_000, ['source_item' => 't.1']),
            $this->equipmentPermitFeeClause(23, 'COMBINE-RICE-HARVESTER', 'Combine Rice harvester - 500.00 per annum.', 'combine_rice_harvester', 50_000, ['source_item' => 't.2']),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3K-02-PAYMENT-TIMING', [
            $this->policyBoundaryClause(1, 'MRC-3K-02-PAY-BEFORE-RENTAL-UPON-APPLICATION', RevenueCodeProvisionClauseType::PaymentTiming, 'The fee imposed herein shall be payable prior to the rental of the equipment upon application for a Mayors permit.', 'Candidate payment boundary: pay the equipment permit fee when applying for the stated permit and before the equipment rental.', 'Whether rental or application is the operative trigger, rental commencement and contract evidence, permit applicant and payer, owner/operator/lessor/lessee responsibility, annual validity, late or ongoing rentals, equipment changes, source “Mayors permit” identity, collector, receipt, rejection, cancellation, refund, and enforcement require accepted procedure.', ['candidate_payment_timing' => ['upon_permit_application', 'before_equipment_rental'], 'source_permit_term' => 'Mayors permit', 'known_trigger_attachment_ambiguity' => true]),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3K-03-EQUIPMENT-REGISTRY', [
            $this->policyBoundaryClause(1, 'MRC-3K-03-TREASURER-EQUIPMENT-REGISTRY', RevenueCodeProvisionClauseType::DocumentaryRequirement, 'The Municipal Treasurer shall keep a registry of all heavy equipment and agricultural machinery, which shall include the make and brand of the heavy equipment and agricultural machinery and name and address of the owner.', 'Candidate registry obligation: the Municipal Treasurer records all covered equipment with make, brand, owner name, and owner address.', '“All” may exceed Section 3K.01 fee eligibility. Treasurer delegation, equipment and owner identity, serial/VIN/engine/plate identifiers omitted from the source, operator and rental relationships, required make/brand vocabularies, registration trigger, permit linkage, numbering, duplicate detection, corrections, ownership transfer, retirement, retention, privacy, access, and migration require accepted procedure.', ['candidate_registry_authority' => 'Municipal Treasurer', 'source_registry_scope' => 'all_heavy_equipment_and_agricultural_machinery', 'candidate_registry_fields' => ['equipment_make', 'equipment_brand', 'owner_name', 'owner_address'], 'known_missing_identity_fields' => ['serial_number', 'vehicle_identification_number', 'engine_number', 'plate_number']]),
        ]);

        $this->persistPolicyBoundaryClauses('MRC-3K-04-PENALTY', [
            $this->policyBoundaryClause(1, 'MRC-3K-04-JUDICIAL-FINE-IMPRISONMENT', RevenueCodeProvisionClauseType::Penalty, 'Any violation of the provisions of this article shall be punished by a fine of not less than Five Hundred Pesos (500.00) but not exceeding One Thousand Peso (1,000.00) or imprisonment of not less than one (1) month but not exceeding six months, or both, at the discretion of the court.', 'Candidate judicial penalty band: an Article K violation carries a PHP 500.00 to PHP 1,000.00 fine, one to six months imprisonment, or both at court discretion.', 'Violation and responsible-actor mapping, equipment and permit identity, investigation, notice, hearing, referral, conviction, offense counting, municipal penalty ceiling, fine and imprisonment authority, court discretion, appeal, and current legal validity require counsel and municipal acceptance.', ['candidate_minimum_fine_cents' => 50_000, 'candidate_maximum_fine_cents' => 100_000, 'source_imprisonment_minimum_months' => 1, 'source_imprisonment_maximum_months' => 6, 'candidate_authority' => 'court']),
        ]);

        $this->seedArticleLPolicyBoundaryClauses();
        $this->seedArticleMPolicyBoundaryClauses();
    }

    private function seedArticleLPolicyBoundaryClauses(): void
    {
        $this->seedMtopFoundationClauses();
        $this->seedTricycleOperatingPolicyClauses();
        $this->seedTricycleEnforcementClauses();
    }

    private function seedMtopFoundationClauses(): void
    {
        $this->persistArticleLClauses('MRC-3L-01-SCOPE', [
            ['SCOPE', RevenueCodeProvisionClauseType::Eligibility, 'This article shall apply to all motorized tricycle for hire within the territorial limits or jurisdiction of the municipality of Ipil, Zamboanga Sibugay.', 'Candidate scope: motorized tricycles for hire operating within Ipil.', 'Vehicle classification, for-hire use, operator identity, territorial nexus, mixed routes, and current legal coverage require acceptance.', ['candidate_jurisdiction' => 'Municipality of Ipil']],
        ]);
        $this->persistArticleLClauses('MRC-3L-02-DEFINITIONS', [
            ['COLORUM', RevenueCodeProvisionClauseType::Definition, 'Colorum- refers to Tricycle Operators/Drivers who operate clandestinely without permit within the municipality and/or private vehicle operated for public utility purposes without the benefit of a valid and existing special permit, provisional authority or franchise.', 'Candidate definition preserves both unpermitted clandestine operation and private-vehicle public utility operation.', 'The and/or grammar, permit and authority identity, validity period, knowledge, vehicle, operator, and driver evidence require legal acceptance.', ['candidate_term' => 'colorum', 'known_boolean_ambiguity' => true]],
            ['FRANCHISE', RevenueCodeProvisionClauseType::Definition, 'Franchise- refers to a special privilege conferred by the Local Government to a qualified individual or corporation to provide motorized tricycle transport service to the public for monetary consideration.', 'Candidate definition: a local-government privilege to provide compensated tricycle transport.', 'Granting authority, natural or juridical holder, qualification, contractual effect, amendment, duration, and relationship to MTOP require reconciliation.', ['candidate_term' => 'franchise']],
            ['TRICYCLE-FOR-HIRE', RevenueCodeProvisionClauseType::Definition, 'Motorized Tricycle for hire- a motor vehicle composed of a motorcycle fitted to a single-wheeled sidecar, or with a center cab operated to render transport services to the general public for fee.', 'Candidate definition covers single-wheeled sidecar and center-cab configurations used for paid public transport.', 'Vehicle configuration, two-wheeled-cab schedule wording, registration class, conversion, and mixed private use require acceptance.', ['candidate_term' => 'motorized_tricycle_for_hire', 'candidate_vehicle_configurations' => ['single_wheeled_sidecar', 'center_cab']]],
            ['MTOP', RevenueCodeProvisionClauseType::Definition, 'Motorized Tricycle Operators Permit (MTOP)- the document granting permit to operate issued to a person, natural or juridical, allowing such person to operate motorized tricycle for hire within the Municipality of Ipil.', 'Candidate definition: MTOP is the municipal document authorizing the holder to operate a tricycle for hire in Ipil.', 'Permit holder, vehicle count, franchise relationship, issuance, numbering, validity, conditions, suspension, cancellation, and legal effect require acceptance.', ['candidate_term' => 'mtop']],
            ['RSU', RevenueCodeProvisionClauseType::Definition, 'Request for System Update (RSU)- is a document form the Land Transportation Office (LTO) showing that the applicant has applied for a change of status of his Tricycle unit from “PRIVATE” to “FOR HIRE”.', 'Candidate definition: LTO evidence that private-to-for-hire registration conversion was requested.', 'Document authenticity, pending versus approved conversion, applicant and vehicle matching, validity, and effect on operating authority require acceptance.', ['candidate_term' => 'request_for_system_update', 'candidate_external_authority' => 'LTO']],
            ['ROAD-WORTHINESS', RevenueCodeProvisionClauseType::Definition, 'Road Worthiness- the quality of being fit to drive on the open road.', 'Candidate definition: fitness of a vehicle for road operation.', 'Inspection standard, inspector authority, evidence, validity, defects, correction, and appeal require accepted technical procedure.', ['candidate_term' => 'road_worthiness']],
            ['TARIFF', RevenueCodeProvisionClauseType::Definition, 'Tariff- the schedule of fare rate issued by the Local Motorized Tricycle Regulatory Board (MTRB) as approved by the Sangguniang Bayan.', 'Candidate definition: an MTRB fare schedule becomes authoritative after Sangguniang Bayan approval.', 'Issuance and approval records, effective dates, zones, amendments, publication, and conflict with printed Section 3L.11 fares require reconciliation.', ['candidate_term' => 'tariff', 'candidate_approval_authority' => 'Sangguniang Bayan']],
            ['TRAFFIC-CITATION', RevenueCodeProvisionClauseType::Definition, 'Traffic Citation- is a summon or citation in writing issued to a person violating any of the provision herein or traffic ordinances and regulations of the municipality.', 'Candidate definition: written notice of an alleged Article L or municipal traffic violation.', 'Citation authority, form, numbering, alleged violation, service, response, contest, finality, and evidentiary effect require accepted procedure.', ['candidate_term' => 'traffic_citation']],
        ]);
        $this->persistArticleLClauses('MRC-3L-03-REGULATORY-BOARD', [
            ['COMPOSITION', RevenueCodeProvisionClauseType::AuthorityBoundary, 'MTRB composition: Municipal Mayor as Chairman; Committee on Public Utilities and Transportation chair as Vice Chairman; Ways and Means chair, Public Safety chair, Municipal Licensing Officer, ABC Federated President, Licensing Office Records Officer, Head of MITSOM, and PNP Chief as members.', 'Candidate authority roster preserves the nine named offices.', 'Current office names, incumbency, alternates, vacancies, quorum, voting, recusal, records, and delegation require municipal confirmation.', ['candidate_member_count' => 9]],
            ['ISSUE-AMEND-SUSPEND-CANCEL', RevenueCodeProvisionClauseType::AuthorityBoundary, 'Issue, amend, revise, suspend or cancel Motorized Tricycle Operators Permit (MTOP) and prescribe the appropriate terms and conditions thereof.', 'Candidate MTRB authority covers the stated MTOP lifecycle actions and conditions.', 'Decision thresholds, due process, effective dates, delegated preparation, notice, appeal, and authority records require acceptance.', ['candidate_actions' => ['issue', 'amend', 'revise', 'suspend', 'cancel']]],
            ['ZONES', RevenueCodeProvisionClauseType::AuthorityBoundary, 'Prescribed and regulate zones or service areas in coordination with the Sangguniang Bayan of the areas affected.', 'Candidate MTRB power covers zones and service areas with Sangguniang Bayan coordination.', 'The source grammar, affected-area approval, geographic representation, effective dates, and publication require acceptance.', ['candidate_subject' => 'zones_and_service_areas']],
            ['SERVICE-CONDITIONS', RevenueCodeProvisionClauseType::AuthorityBoundary, 'Establish and prescribe the conditions and qualification of service.', 'Candidate MTRB power covers operating conditions and service qualifications.', 'The qualification catalog, evidence, inspection, renewals, changes, enforcement, and appeal require accepted policy.', ['candidate_subject' => 'service_conditions_and_qualifications']],
            ['RECOMMEND-FARES-FEES', RevenueCodeProvisionClauseType::AuthorityBoundary, 'Recommend to the Sangguniang Bayan the readjustment of fares or rates for different zones, as well as fees and related regulatory charges.', 'Candidate boundary: MTRB recommends while Sangguniang Bayan decides fare and fee adjustments.', 'Recommendation, enactment, publication, effective date, zone mapping, and conflict with ordinance values require reconciliation.', ['candidate_recommender' => 'MTRB', 'candidate_decision_authority' => 'Sangguniang Bayan']],
            ['OTHER-DELEGATED-POWERS', RevenueCodeProvisionClauseType::AuthorityBoundary, 'Perform such other powers and authority that the Sangguniang Bayan may grant unto it.', 'Candidate MTRB authority may expand through a separate Sangguniang Bayan grant.', 'The granting instrument, scope, effective date, expiration, delegation, and audit evidence must be explicit.', ['candidate_granting_authority' => 'Sangguniang Bayan']],
            ['TASK-FORCE', RevenueCodeProvisionClauseType::AuthorityBoundary, 'Creation of Task Force and guidelines.', 'Candidate MTRB power includes task-force and guideline creation.', 'The terse source does not identify creator, composition, appointment, powers, jurisdiction, duration, procedures, or oversight.', ['known_incomplete_source_clause' => true]],
        ]);
        $this->persistArticleLClauses('MRC-3L-04-GRANTING-MTOP', [
            ['PERMIT-ANNUAL-RENEWAL', RevenueCodeProvisionClauseType::PermitRequirement, 'All tricycle operators/drivers routing within Ipil are required to secure an MTOP at the Municipal Licensing Office. This permit must be renewed annually on or before January 20.', 'Candidate requirement: covered operators or drivers obtain an MTOP and renew by January 20 each year.', 'Operator-versus-driver holder, routing nexus, permit identity, first-year timing, late renewal, expiry, collector, and current office authority require acceptance.', ['candidate_due_month' => 1, 'candidate_due_day' => 20, 'candidate_frequency' => 'annual']],
            ['ONE-FRANCHISE-PER-FAMILY', RevenueCodeProvisionClauseType::Eligibility, 'Only one applicant/franchise per family. Effective January 2022, all old multiple franchise shall be surrendered at the Municipal Licensing Office.', 'Candidate eligibility restricts a family to one applicant or franchise and requires surrender of older multiples.', 'Family definition, household and legal relationships, corporate applicants, ownership, duplicate detection, surrender procedure, vested rights, and effective-date authority require legal reconciliation.', ['source_effective_period' => '2022-01', 'candidate_limit' => 1]],
            ['DOCUMENTS', RevenueCodeProvisionClauseType::DocumentaryRequirement, 'Applicants should present a Community Tax Certificate, Ipil Voter’s ID, and LTO Certificate of Registration with latest LTO Official Receipt in the applicant’s name.', 'Candidate document set preserves the three stated applicant and vehicle records.', '“Should” versus mandatory effect, natural/juridical applicants, voter requirement validity, document currency, name matching, authenticity, substitutions, and sufficiency require acceptance.', ['candidate_documents' => ['community_tax_certificate', 'ipil_voters_id', 'lto_certificate_of_registration_and_latest_official_receipt']]],
            ['ORIENTATION', RevenueCodeProvisionClauseType::DocumentaryRequirement, 'All operators and drivers applying for new or renewed MTOP are required to attend an orientation seminar scheduled by the MTRB with the Municipal Licensing Section.', 'Candidate requirement: both new and renewal participants complete the scheduled orientation.', 'Participant identity, attendance evidence, frequency, expiry, missed sessions, equivalence, organizer, and sufficiency require accepted procedure.', ['candidate_application_types' => ['new', 'renewal']]],
            ['ITDOA-MEMBERSHIP', RevenueCodeProvisionClauseType::Eligibility, 'Membership from the Ipil Tricycle Operators and Drivers Association.', 'Candidate requirement: ITDOA membership supports MTOP eligibility.', 'The fragment does not identify member type, proof, standing, dues, expiry, refusal, exceptions, legal authority, or relationship to Section 3L.15.', ['candidate_association' => 'ITDOA', 'known_incomplete_source_clause' => true]],
            ['AUTOMATIC-ISSUANCE', RevenueCodeProvisionClauseType::AuthorityBoundary, 'The operator’s permit shall be issued/renewed automatically, provided that he has completed the requirements.', 'Candidate boundary: completion of accepted requirements leads to issuance or renewal.', 'Requirement completeness, verification, roadworthiness, fee payment, board discretion, authority record, numbering, effectivity, conditions, and contradiction with MTRB approval powers require reconciliation.', ['candidate_trigger' => 'completed_requirements', 'known_authority_tension' => true]],
        ]);
        $this->persistPolicyBoundaryClauses('MRC-3L-05-FEES', [
            $this->mtopFeeClause(1, 'SINGLE-MTOP', 'MTOP Fee - 620.00 for motorcycle fitted to a single wheeled car.', 'single_wheeled_sidecar', 'mtop_fee', 62_000),
            $this->mtopFeeClause(2, 'CAB-MTOP', 'MTOP Fee - 920.00 for motorcycle with two wheeled cab.', 'two_wheeled_cab', 'mtop_fee', 92_000),
            $this->mtopFeeClause(3, 'SINGLE-INSPECTION', 'Supervision/Inspection fee - 150.00 for motorcycle fitted to a single wheeled car.', 'single_wheeled_sidecar', 'supervision_inspection_fee', 15_000),
            $this->mtopFeeClause(4, 'CAB-INSPECTION', 'Supervision/Inspection fee - 150.00 for motorcycle with two wheeled cab.', 'two_wheeled_cab', 'supervision_inspection_fee', 15_000),
            $this->mtopFeeClause(5, 'SINGLE-PARKING', 'Parking Fee - 600.00 for motorcycle fitted to a single wheeled car.', 'single_wheeled_sidecar', 'parking_fee', 60_000),
            $this->mtopFeeClause(6, 'CAB-PARKING', 'Parking Fee - 600.00 for motorcycle with two wheeled cab.', 'two_wheeled_cab', 'parking_fee', 60_000),
            $this->mtopFeeClause(7, 'SINGLE-OCCUPATIONAL-ID', 'Occupational Calling ID - 75.00 for motorcycle fitted to a single wheeled car.', 'single_wheeled_sidecar', 'occupational_calling_id', 7_500),
            $this->mtopFeeClause(8, 'CAB-OCCUPATIONAL-ID', 'Occupational Calling ID - 75.00 for motorcycle with two wheeled cab.', 'two_wheeled_cab', 'occupational_calling_id', 7_500),
            $this->mtopFeeClause(9, 'SINGLE-ID-LAMINATION', 'ID Lamination Fee - 25.00 for motorcycle fitted to a single wheeled car.', 'single_wheeled_sidecar', 'id_lamination_fee', 2_500),
            $this->mtopFeeClause(10, 'CAB-ID-LAMINATION', 'ID Lamination Fee - 25.00 for motorcycle with two wheeled cab.', 'two_wheeled_cab', 'id_lamination_fee', 2_500),
            $this->mtopFeeClause(11, 'SINGLE-MTOP-LAMINATION', 'MTOP Lamination Fee - 30.00 for motorcycle fitted to a single wheeled car.', 'single_wheeled_sidecar', 'mtop_lamination_fee', 3_000),
            $this->mtopFeeClause(12, 'CAB-MTOP-LAMINATION', 'MTOP Lamination Fee - 30.00 for motorcycle with two wheeled cab.', 'two_wheeled_cab', 'mtop_lamination_fee', 3_000),
            $this->mtopFeeClause(13, 'SINGLE-TOTAL', 'Total Amount - P1,500.00 for motorcycle fitted to a single wheeled car.', 'single_wheeled_sidecar', 'printed_total', 150_000),
            $this->mtopFeeClause(14, 'CAB-TOTAL', 'Total Amount - P1,800.00 for motorcycle with two wheeled cab.', 'two_wheeled_cab', 'printed_total', 180_000),
            $this->mtopFeeClause(15, 'GARAGE-FRANCHISING', 'Garage Franchising – P100.00/month.', 'garage', 'garage_franchising', 10_000, ['candidate_frequency' => 'monthly']),
        ]);
        $this->persistArticleLClauses('MRC-3L-06-APPROVAL-RENEWAL', [
            ['PRIVATE-TO-FOR-HIRE', RevenueCodeProvisionClauseType::DocumentaryRequirement, 'Within 30 days from approval, the franchise must convert the new motorcycle unit registration from PRIVATE to FOR HIRE to secure the appropriate Number Plate for operation.', 'Candidate post-approval condition: complete LTO conversion within 30 days and secure the operating plate.', 'Approval timestamp, day counting, franchise and vehicle identity, RSU versus completed conversion, plate authority, interim operation, noncompliance, extension, and permit effect require acceptance.', ['source_completion_days' => 30, 'candidate_status_transition' => ['private', 'for_hire']]],
        ]);
    }

    private function seedTricycleOperatingPolicyClauses(): void
    {
        $this->persistArticleLClauses('MRC-3L-07-MARKINGS', [
            ['BODY-NUMBER', RevenueCodeProvisionClauseType::InspectionRequirement, 'Assigned MTOP body number, painted boldly at the front, rear view and inside the tricycle in dark blue (5x3 inches) and white background (7x16 inches).', 'Candidate marking: assigned body number appears in three locations using the stated color and dimensions.', 'Body-number assignment, uniqueness, case-number relationship, exact dimension target, paint specification, inspection, replacement, and correction require acceptance.', ['candidate_locations' => ['front', 'rear', 'inside'], 'source_foreground_color' => 'dark blue', 'source_foreground_inches' => '5x3', 'source_background_color' => 'white', 'source_background_inches' => '7x16']],
            ['HOLDER-NAME', RevenueCodeProvisionClauseType::InspectionRequirement, 'Name of franchise holder/operator, printed in full under the body number front and rear side of the bicycle for hire (3 inches in bold letters).', 'Candidate marking: full holder or operator name appears under the front and rear body numbers.', 'Franchise-holder versus operator identity, source “bicycle” wording, name format, privacy, letter measurement, changes, and inspection require acceptance.', ['candidate_locations' => ['front', 'rear'], 'source_letter_height_inches' => 3, 'known_source_wording' => 'bicycle_for_hire']],
            ['DAY-OFF', RevenueCodeProvisionClauseType::InspectionRequirement, 'Schedule of the Day-Off shall be printed boldly above the body.', 'Candidate marking: the assigned day-off is displayed above the body.', 'Assigned schedule source, wording, location, visibility, changes, exemptions, and inspection require acceptance.', ['candidate_marking' => 'day_off_schedule']],
        ]);
        $this->persistArticleLClauses('MRC-3L-08-SUSPENSION-STOPPAGE', [
            ['WRITTEN-NOTICE', RevenueCodeProvisionClauseType::DocumentaryRequirement, 'An operator who wishes to stop service completely or suspend service for more than one month should report in writing such termination or suspension to the Municipal Tricycle Franchising and Regulatory Board.', 'Candidate notice: complete stoppage or suspension exceeding one month is reported in writing.', 'The source says “should”; notice timing, recipient identity versus MTRB, effective date, reason, duration, return to service, fees, and permit effect require acceptance.', ['candidate_trigger' => ['complete_stoppage', 'suspension_more_than_one_month']]],
        ]);
        $this->persistArticleLClauses('MRC-3L-09-NONTRANSFERABILITY', [
            ['PERSONAL-NONTRANSFERABLE', RevenueCodeProvisionClauseType::OperatingRestriction, 'The MTOP is issued on the basis of the personal character and qualification of the applicant. Hence, the MTOP is non-transferable and non-negotiable.', 'Candidate restriction: an MTOP remains with the personally qualified holder and cannot be transferred or negotiated.', 'Natural or juridical holder treatment, control changes, lease, unit substitution, enforcement, and effect of an attempted transfer require acceptance.', ['candidate_restrictions' => ['non_transferable', 'non_negotiable']]],
            ['DEATH-FAMILY-EXCEPTION', RevenueCodeProvisionClauseType::Eligibility, 'In case of death of the owner, transfer to direct family is allowed (spouse or children with single status of legal age).', 'Candidate exception: death may permit succession to a spouse or a single, legal-age child.', 'Death and relationship evidence, multiple eligible successors, juridical owners, child legitimacy wording, marital status, age, qualification, fees, board approval, and transfer effect require legal reconciliation.', ['candidate_successors' => ['spouse', 'single_child_of_legal_age']]],
        ]);
        $this->persistArticleLClauses('MRC-3L-10-DAY-OFF', [
            ['SCHEME', RevenueCodeProvisionClauseType::OperatingRestriction, 'A number coding scheme or day off of one day every week shall be based on the last digit of the MTRB case or body number.', 'Candidate restriction: each covered tricycle has one weekly non-operating day derived from its assigned number.', 'Case-versus-body number, assignment, effective week, substitutions, holidays, emergency operation, exemptions, and enforcement require acceptance.', ['candidate_frequency' => 'weekly', 'candidate_days_off_per_week' => 1]],
            ['DIGIT-DAY-MATRIX', RevenueCodeProvisionClauseType::OperatingRestriction, 'Body number last digit: 1 and 2 Monday; 3 and 4 Tuesday; 5 and 6 Wednesday; 7 and 8 Thursday; 9 and 10 Friday.', 'Candidate matrix preserves the printed digit-to-day mapping.', 'A decimal last digit cannot be 10. Whether the source means 0, body numbers 9 and 10, or another scheme requires authoritative correction before execution.', ['source_mapping' => ['1_2' => 'Monday', '3_4' => 'Tuesday', '5_6' => 'Wednesday', '7_8' => 'Thursday', '9_10' => 'Friday'], 'known_last_digit_conflict' => true]],
        ]);
        $this->persistArticleLClauses('MRC-3L-11-FARE', [
            ['BASE-DISTANCE-FARE', RevenueCodeProvisionClauseType::RateBand, 'Ten Pesos (P10.00) per passenger for the first kilometer and an additional Two Pesos (P2.00) in every succeeding kilometer, implemented right after multiple passenger is allowed.', 'Candidate fare: PHP 10.00 first kilometer plus PHP 2.00 each succeeding kilometer per passenger.', 'The activation condition, tariff approval, distance source, partial kilometers, route, multiple passengers, shared rides, fare rounding, and current amount require reconciliation.', ['candidate_first_kilometer_cents' => 1_000, 'candidate_succeeding_kilometer_cents' => 200, 'candidate_unit' => 'passenger'], 1_000],
            ['BAGGAGE', RevenueCodeProvisionClauseType::RateBand, 'Baggage of every 25 kilograms shall be equivalent to half fare and 50 kilograms equivalent to one passenger fare.', 'Candidate baggage treatment: each stated weight maps to a passenger-fare fraction.', 'Weight measurement, intervals, rounding, cumulative pieces, passenger allowance, maximum load, and interaction with discounts require acceptance.', ['source_weight_fare_mapping' => ['25kg' => 'half_fare', '50kg' => 'one_passenger_fare']]],
            ['DISCOUNT', RevenueCodeProvisionClauseType::Exemption, 'Senior citizens, persons with disability (PWD) and students with valid ID shall be entitled to 20 percent discount.', 'Candidate discount: qualifying senior, PWD, and student passengers receive 20 percent.', 'Eligibility evidence, companion and baggage treatment, stacking, rounding, reimbursement, current national law, and fare-table application require reconciliation.', ['candidate_discount_percent' => '20.00', 'candidate_groups' => ['senior_citizen', 'person_with_disability', 'student_with_valid_id']]],
            ['CHILD-FREE', RevenueCodeProvisionClauseType::Exemption, 'Children below 7 years old shall be free of charge.', 'Candidate exemption: passengers younger than seven travel without fare.', 'Age evidence, accompanied travel, seat occupancy, baggage, safety restrictions, and interaction with other passengers require accepted policy.', ['candidate_age_under' => 7]],
        ]);
        $this->persistArticleLClauses('MRC-3L-12-ROTUNDA-RESTRICTION', [
            ['ROTUNDA-PROHIBITION', RevenueCodeProvisionClauseType::OperatingRestriction, 'All tricycles in Ipil are strictly prohibited along Rotunda. Effective February 1, 2021.', 'Candidate restriction: covered tricycles may not operate along the identified Rotunda area.', 'Geospatial boundary, through-crossing, pickup, drop-off, emergencies, current traffic orders, and enforcement require acceptance.', ['source_effective_date' => '2021-02-01']],
        ]);
        $this->persistArticleLClauses('MRC-3L-13-ROUTES', [
            ['ROUTE-A', RevenueCodeProvisionClauseType::OperatingRestriction, 'Route A – within boundaries of Pagadian to Magdaup Road.', 'Candidate route A preserves the printed road endpoints.', 'Geometry, direction, stops, overlaps, assignment, changes, and current validity require accepted route evidence.', ['candidate_route' => 'A', 'source_endpoints' => ['Pagadian', 'Magdaup Road']]],
            ['ROUTE-B', RevenueCodeProvisionClauseType::OperatingRestriction, 'Route B – within boundaries of Magdaup to Zamboanga Road.', 'Candidate route B preserves the printed road endpoints.', 'Geometry, direction, stops, overlaps, assignment, changes, and current validity require accepted route evidence.', ['candidate_route' => 'B', 'source_endpoints' => ['Magdaup', 'Zamboanga Road']]],
            ['ROUTE-C', RevenueCodeProvisionClauseType::OperatingRestriction, 'Route C – within boundaries of Zamboanga to Dipolog Road.', 'Candidate route C preserves the printed road endpoints.', 'Geometry, direction, stops, overlaps, assignment, changes, and current validity require accepted route evidence.', ['candidate_route' => 'C', 'source_endpoints' => ['Zamboanga', 'Dipolog Road']]],
            ['ROUTE-D', RevenueCodeProvisionClauseType::OperatingRestriction, 'Route D – within boundaries of Dipolog to Pagadian Road.', 'Candidate route D preserves the printed road endpoints.', 'Geometry, direction, stops, overlaps, assignment, changes, and current validity require accepted route evidence.', ['candidate_route' => 'D', 'source_endpoints' => ['Dipolog', 'Pagadian Road']]],
        ]);
        $this->persistArticleLClauses('MRC-3L-14-HIGHWAY-LANE', [
            ['OUTERMOST-RIGHT-LANE', RevenueCodeProvisionClauseType::OperatingRestriction, 'Motorized Tricycle shall use the outmost right lane of the National Highway.', 'Candidate restriction: covered tricycles use the outermost right lane.', 'Road scope, lane availability, overtaking, turning, obstruction, emergency, national traffic authority, and enforcement require acceptance.', ['known_source_wording' => 'outmost_right_lane']],
        ]);
        $this->persistArticleLClauses('MRC-3L-15-ASSOCIATION', [
            ['SOLE-RECOGNIZED-ITDOA', RevenueCodeProvisionClauseType::AuthorityBoundary, 'There shall only be one Tricycle Drivers and Operators Association recognized by MTRB: the Ipil Tricycle Drivers and Operators Association (ITDOA).', 'Candidate recognition: ITDOA is the sole association recognized for Article L.', 'Legal basis for exclusivity, association identity and standing, recognition period, replacement, membership effect, and appeal require acceptance.', ['candidate_association' => 'ITDOA', 'candidate_recognized_count' => 1]],
            ['PRESIDENT-REPORTS', RevenueCodeProvisionClauseType::DocumentaryRequirement, 'Association officers represented by the president are authorized to relay or report association actions to the MTRB.', 'Candidate evidence boundary: the association president communicates association actions to MTRB.', 'Officer authority, report format, authentication, receipt, evidentiary weight, actions covered, retention, and effect require accepted procedure.', ['candidate_reporter_role' => 'association_president']],
        ]);
    }

    private function seedTricycleEnforcementClauses(): void
    {
        $this->persistArticleLClauses('MRC-3L-16-PROHIBITED-ACTS', [
            ['REFUSAL-TO-CONVEY', RevenueCodeProvisionClauseType::ProhibitedPractice, 'A driver who refuses or selectively denies a passenger conveyance without justifiable cause is subject to summary MTRB investigation upon passenger complaint; justifiable cause means emergency cases only.', 'Candidate violation: unjustified refusal or selective loading after a passenger complaint.', 'Request and destination evidence, driver and vehicle identity, emergency defense, complaint standing, investigation, burden of proof, decision, and penalty mapping require due-process policy.', ['candidate_defense' => 'emergency_only']],
            ['PRIVATE-USE-SIGN', RevenueCodeProvisionClauseType::OperatingRestriction, 'A driver or operator not wishing to convey passengers during a period of the day must place “PRIVATE USE” in the front windshield.', 'Candidate condition: a visible private-use sign distinguishes a non-service period.', 'Whether an MTOP vehicle may be used privately, sign format, timing, duration, route restrictions, insurance, evidence, and interaction with refusal rules require acceptance.', ['candidate_sign_text' => 'PRIVATE USE']],
            ['ATTIRE-HYGIENE', RevenueCodeProvisionClauseType::ProhibitedPractice, 'Drivers shall wear t-shirt, long pants and shoes; sleeveless clothing and slippers are prohibited; haircut and body odor hygiene shall be observed.', 'Candidate conduct standard preserves the printed attire and hygiene requirements.', 'Gender-neutral application, objective standards, disability and safety accommodations, inspection, evidence, warning, due process, and legal validity require acceptance.', ['candidate_required_attire' => ['t_shirt', 'long_pants', 'shoes'], 'candidate_prohibited_attire' => ['sleeveless', 'slippers']]],
            ['DISPLAY-ID-FRANCHISE', RevenueCodeProvisionClauseType::InspectionRequirement, 'All tricycles for hire shall display the driver’s ID and laminated franchise inside the vehicle visible to passengers; operators shall inform MTRB of an authorized-driver change.', 'Candidate display and change-notice requirement links a visible driver identity and franchise to the operating vehicle.', 'ID and franchise formats, privacy, placement, authorized-driver registry, change timing, substitute drivers, verification, and enforcement require acceptance.', ['candidate_display_documents' => ['driver_id', 'laminated_franchise']]],
            ['LOAD-LIMIT', RevenueCodeProvisionClauseType::OperatingRestriction, 'Motorized Tricycle/Pedicab Load Limit – maximum of six passengers including the driver.', 'Candidate capacity limit: six persons including the driver.', 'The source calls the driver a passenger, vehicle configuration, children, baggage, seating, registration capacity, and enforcement require reconciliation.', ['candidate_maximum_persons_including_driver' => 6]],
            ['OVERCHARGING-MISCONDUCT', RevenueCodeProvisionClauseType::ProhibitedPractice, 'Over charging of Fare rate. Misconduct towards passenger.', 'Candidate violations include charging above the accepted fare and passenger misconduct.', 'The operative tariff, fare computation, payment evidence, misconduct definition, intent, complainant evidence, investigation, and penalty mapping require acceptance.', ['candidate_practices' => ['fare_overcharging', 'passenger_misconduct']]],
            ['INTOXICATION-GAMBLING', RevenueCodeProvisionClauseType::ProhibitedPractice, 'Driving under the influence of illegal drugs and liquor, gambling of any form whether games of chance or skills while parking.', 'Candidate violations cover impaired driving and gambling while parked.', 'Substance and impairment standards, testing authority, refusal, chain of custody, illegal-drug finding, parking status, gambling definition, evidence, and criminal referral require legal procedure.', ['candidate_practices' => ['driving_under_influence_illegal_drugs', 'driving_under_influence_liquor', 'gambling_while_parking']]],
            ['SMOKING', RevenueCodeProvisionClauseType::ProhibitedPractice, 'Smoking.', 'Candidate prohibited practice: smoking in the Article L operating context.', 'The one-word source does not identify actor, place, vehicle state, passengers, tobacco or vaping scope, exceptions, evidence, or relationship to other ordinances.', ['known_incomplete_source_clause' => true]],
        ]);
        $this->persistArticleLClauses('MRC-3L-17-APPREHENDING-OFFICERS', [
            ['AUTHORIZED-OFFICERS', RevenueCodeProvisionClauseType::AuthorityBoundary, 'Authorized apprehending officers: MTRB Task Force, PNP, MITSOM and other deputized traffic enforcers.', 'Candidate authority roster preserves the four printed officer classes.', 'Appointment, deputation instrument, identity, training, territorial and subject jurisdiction, citation and seizure powers, records, supervision, and complaints require acceptance.', ['candidate_officer_classes' => ['mtrb_task_force', 'pnp', 'mitsom', 'other_deputized_traffic_enforcers']]],
        ]);
        $this->persistPolicyBoundaryClauses('MRC-3L-18-PENALTIES', [
            $this->policyBoundaryClause(1, 'MRC-3L-18-FIRST-OFFENSE', RevenueCodeProvisionClauseType::Penalty, 'First Offense - P1,000.00.', 'Candidate first-offense administrative fine: PHP 1,000.00.', 'Violation-to-penalty mapping, responsible party, offense identity and finality, lookback, notice, hearing, payment, receipt, appeal, and current legal authority require reconciliation.', ['candidate_offense_ordinal' => 1], 100_000),
            $this->policyBoundaryClause(2, 'MRC-3L-18-SECOND-OFFENSE', RevenueCodeProvisionClauseType::Penalty, 'Second Offense - P1,500.00.', 'Candidate second-offense administrative fine: PHP 1,500.00.', 'Violation-to-penalty mapping, same-versus-any violation counting, finality, lookback, notice, hearing, payment, receipt, appeal, and current legal authority require reconciliation.', ['candidate_offense_ordinal' => 2], 150_000),
            $this->policyBoundaryClause(3, 'MRC-3L-18-THIRD-OFFENSE', RevenueCodeProvisionClauseType::Penalty, 'Third Offense - P2,500.00 Denial for renewal of application And Impoundment.', 'Candidate third-offense consequences: PHP 2,500.00, renewal denial, and impoundment.', 'Fine-versus-combined-sanction grammar, same-versus-any violation counting, denial duration and permit identity, impoundment authority and custody, notice, hearing, appeal, release, storage costs, and current legal validity require reconciliation.', ['candidate_offense_ordinal' => 3, 'candidate_additional_sanctions' => ['renewal_denial', 'impoundment']], 250_000),
        ]);
        $this->persistArticleLClauses('MRC-3L-19-FINE-DISPOSITION', [
            ['TREASURER-GENERAL-FUND', RevenueCodeProvisionClauseType::DispositionProcedure, 'All fines/forfeitures collected for Article L violations shall be paid to the Municipality through the Municipal Treasurer and deposited under the General Fund.', 'Candidate disposition: finalized Article L fines and forfeitures are receipted by Treasury and deposited to the General Fund.', 'Fine assessment authority, finality, forfeiture property or money scope, collector delegation, receipt numbering, account code, deposit, allocation, refund, reversal, reconciliation, and audit require accepted Treasury policy.', ['candidate_collector' => 'Municipal Treasurer', 'candidate_fund' => 'General Fund']],
        ]);
        $this->persistArticleLClauses('MRC-3L-20-REPEALING', [
            ['INCONSISTENT-INSTRUMENTS', RevenueCodeProvisionClauseType::AuthorityBoundary, 'All ordinances and pertinent rules and regulations inconsistent with Article L are repealed or amended accordingly.', 'Candidate legal boundary: inconsistent prior instruments yield to Article L.', 'The affected-instrument inventory, degree of inconsistency, amendment text, effective date, later enactments, and legal authority require counsel confirmation.', ['candidate_legal_effect' => 'repeal_or_amend_inconsistent_instruments']],
        ]);
        $this->persistArticleLClauses('MRC-3L-21-SEPARABILITY', [
            ['UNAFFECTED-PROVISIONS', RevenueCodeProvisionClauseType::AuthorityBoundary, 'If any Article L part or provision is held unconstitutional or invalid, unaffected parts continue in full force and effect.', 'Candidate legal boundary: an authoritative invalidity decision does not automatically disable unaffected provisions.', 'Decision authority, finality, affected-clause mapping, dependency analysis, effective date, notice, and operational suspension require legal procedure.', ['candidate_legal_effect' => 'separability']],
        ]);
    }

    private function seedArticleMPolicyBoundaryClauses(): void
    {
        $occupations = [
            'Actuary',
            'All Vendors',
            'Animal Trainer',
            'Bandsaw/Chainsaw Operator',
            'Bar Tender',
            'Bar/Club/Disco/Voke/ Manager/Supervisor',
            'Barber/Hairstylist/Beautician',
            'Basketball/Volleyball/Boxing/and Other Sports Referee/Official',
            'Bingo Caller',
            'Butcher',
            'Call Center Agent',
            'Carpenter/Mason/Painter',
            'Chiropodist',
            'Cinema Projector Operator',
            'Commercial Steward/Stewardess',
            'Construction Foreman/Supervisor',
            'Cook/Baker',
            'Couturier',
            'Dance/Gym/Sports Instructor',
            'Disc Jockey',
            'Dispatcher/Porter',
            'Dressmaker/Tailor',
            'Driver/Inspector/Conductor of Passenger and Cargo Vehicles',
            'Driving/Diving/Swimming Instructor',
            'Electrician (Non PRC)',
            'Electronic Technician',
            'Embalmer',
            'GRO/Hospitality Girl/Hostess/Club Dancer',
            'Handyman',
            'Hollow Block Maker',
            'Host/Hostess',
            'Insurance Agents and Sub Agent',
            'Janitor/Janitress',
            'Jewelry Appraiser',
            'Laborer',
            'Lathe Machine Operator',
            'Marine Surveyor',
            'Massage Attendant/Masseur',
            'Mechanic',
            'Medical/Dental Aid/Attendant',
            'Medical/Dental Sales Representative',
            'Merchandiser/Promo girl',
            'Non-PRC Passer Teacher/Instructor',
            'Packer',
            'Photographer',
            'Pilot',
            'Plumber',
            'Professional Artist',
            'Professional Boxer/Ring Announcer',
            'Radio/Telecom Operator',
            'Real Estate Broker',
            'Receptionist',
            'Reflexologist',
            'Salesgirl/salesboy',
            'Security Guard/Watchman',
            'Shoe Shine Boy',
            'Shoe/Bag Repairman',
            'Singer/Band Member',
            'Sports Promoter',
            'Statistician',
            'Stevedoring Worker',
            'Stock Broker',
            'Sugar Technologist',
            'Tattooer',
            'Waiter/Waitress',
            'Watch/Jewelry Repairman',
            'Welder/Body Builder',
            'Personnel Under Recruitment Agencies',
            'Others not specified above',
        ];

        $this->persistPolicyBoundaryClauses('MRC-3M-01-OCCUPATIONAL-FEES', [
            $this->policyBoundaryClause(1, 'MRC-3M-01-ANNUAL-PERSON-OCCUPATION-SCOPE', RevenueCodeProvisionClauseType::PermitRequirement, 'There shall be collected as annual fee at the rate prescribed for issuance of Mayor’s Permit to every person engaged in an occupation or calling not requiring government examination within the municipality.', 'Candidate scope: each person practicing a covered non-examined occupation in Ipil obtains an annual individual Mayor’s Permit.', 'Person, occupation, practice, territorial nexus, government-examination boundary, permit identity, annual period, multiple occupations, exemptions, payer, collector, and current authority require reconciliation.', ['candidate_frequency' => 'annual', 'candidate_permit' => 'individual_mayors_permit']),
            ...array_map(fn (string $occupation, int $index): array => $this->occupationalCallingFeeClause($index + 2, $occupation), $occupations, array_keys($occupations)),
        ]);

        $this->persistArticleMClauses('MRC-3M-02-EXEMPTIONS', [
            ['PROVINCIAL-PROFESSIONAL-TAX', RevenueCodeProvisionClauseType::Exemption, 'All professionals who are subject to the Provincial Tax imposition pursuant to Section 139 of the Local Government Code are exempted from payment of this fee.', 'Candidate exemption: a professional subject to the referenced provincial tax does not pay the Article M fee.', 'Profession, PRC or examination status, actual tax liability versus payment, province, period, proof, mixed occupations, permit-versus-fee effect, and current national law require legal reconciliation.', ['external_legal_reference' => 'Local Government Code Section 139']],
            ['GOVERNMENT-EMPLOYEES', RevenueCodeProvisionClauseType::Exemption, 'Government employees are exempted from payment of this fee.', 'Candidate exemption: government employees do not pay the Article M fee.', 'National or local employer scope, permanent or temporary status, job-order and contractor treatment, outside occupations, proof, period, and permit-versus-fee effect require acceptance.', ['candidate_exempt_group' => 'government_employees']],
        ]);

        $this->persistArticleMClauses('MRC-3M-03-PERSONS-GOVERNED', [
            ['TEMPORARY-PERMANENT-PERMIT-ID', RevenueCodeProvisionClauseType::PermitRequirement, 'The following workers or employees whether working on temporary or permanent basis shall secure the individual Mayor’s Permit and LGU-Ipil Calling ID.', 'Candidate requirement applies to both temporary and permanent workers in the listed groups.', 'Worker, employer, engagement, start and end dates, temporary status, permit and ID identity, duplication, and enforcement require acceptance.', ['candidate_employment_statuses' => ['temporary', 'permanent']]],
            ['INDUSTRIAL-MANUFACTURING', RevenueCodeProvisionClauseType::Eligibility, 'Employees or workers in industrial or manufacturing establishments including the source-enumerated factories, plants, construction jobs, shops, laboratories, mills, and repair establishments.', 'Candidate covered group preserves the ordinance’s industrial and manufacturing establishment catalog.', 'Establishment and activity classification, exposure, construction period, mixed use, worker role, inspection, and source catalog maintenance require municipal mapping.', ['candidate_group' => 'industrial_and_manufacturing_workers']],
            ['COMMERCIAL-DANGEROUS', RevenueCodeProvisionClauseType::Eligibility, 'Employees and workers in commercial establishments including film storage, cold storage, delivery services, funeral parlors, janitorial services, junk shops, hardware, pest control, printing, service stations, slaughterhouses, textile stores, warehouses, and parking lots.', 'Candidate covered group preserves the stated commercial establishment catalog.', 'Business classification, worker exposure, establishment status, mixed activity, role, and current public-health or safety basis require acceptance.', ['candidate_group' => 'commercial_offensive_or_dangerous_workers']],
            ['ENVIRONMENTAL-EXPOSURE', RevenueCodeProvisionClauseType::Eligibility, 'Employees and workers in other industrial, manufacturing, or commercial establishments normally exposed to excessive heat, light, noise, cold, and other environmental factors endangering physical and health well-being.', 'Candidate catch-all uses occupational environmental exposure as eligibility.', 'Exposure thresholds, measurement, normality, duration, hazard determination, inspector authority, evidence, protective controls, and appeal require accepted technical policy.', ['candidate_hazards' => ['excessive_heat', 'excessive_light', 'excessive_noise', 'excessive_cold', 'other_environmental_factors']]],
            ['PUBLIC-DAILY-NEEDS', RevenueCodeProvisionClauseType::Eligibility, 'Employees and workers in establishments attending to the daily needs of the public, including drugstores, department stores, groceries, supermarkets, beauty salons, tailor and dress shops, bank tellers, receptionists, and receiving clerks in utility payment outlets except transportation companies.', 'Candidate covered group uses direct daily public-service contact and the printed examples.', 'The source numbering splits this sentence between items 4 and 5. Public-contact threshold, listed classes, transportation exclusion, role identity, and mixed duties require reconciliation.', ['candidate_group' => 'daily_public_needs_workers', 'known_source_numbering_split' => true]],
            ['FOOD-ESTABLISHMENTS', RevenueCodeProvisionClauseType::Eligibility, 'Employees and workers in canteens, carinderias, catering services, bakeries, ice cream or ice milk factories, refreshment parlors, restaurants, sari-sari stores, and soda fountains.', 'Candidate covered group preserves food and eatery establishment workers.', 'Food establishment and worker identity, handling versus non-handling roles, temporary work, health-certificate relationship, and current policy require acceptance.', ['candidate_group' => 'food_and_eatery_workers']],
            ['MARKET-STALLHOLDERS-WORKERS', RevenueCodeProvisionClauseType::Eligibility, 'Stallholders, employees and workers in public markets.', 'Candidate covered group includes public-market stallholders and workers.', 'Public-market identity, stallholder versus worker, helper and substitute roles, operating days, and relationship to vendor permits require acceptance.', ['candidate_group' => 'public_market_stallholders_and_workers']],
            ['FOOD-PEDDLERS', RevenueCodeProvisionClauseType::Eligibility, 'Peddlers of cooked or uncooked foods and all other food peddlers, including peddlers of seasonal merchandise.', 'Candidate covered group preserves cooked, uncooked, other food, and seasonal-merchandise peddlers.', 'Peddler identity, food-versus-seasonal merchandise grammar, location, mobility, activity period, overlap with vendor permits, and evidence require reconciliation.', ['candidate_group' => 'food_and_seasonal_merchandise_peddlers']],
            ['NIGHT-ESTABLISHMENTS', RevenueCodeProvisionClauseType::Eligibility, 'Workers or employees in the source-enumerated night or night-and-day establishments, including bars, entertainment venues, clubs, massage clinics, hotels, motels, and security agencies.', 'Candidate covered group uses employment in businesses whose activities occur or are consumed at night.', 'Nighttime boundary, establishment classification, worker role, mixed schedules, temporary events, security personnel, and current policy require acceptance.', ['candidate_group' => 'night_and_night_day_establishment_workers']],
            ['MINIMUM-AGE', RevenueCodeProvisionClauseType::OperatingRestriction, 'Night and day clubs, night clubs, day clubs, cocktail lounges, bars, cabarets, sauna bath houses and similar places shall under no circumstances allow hostesses, waitresses, waiters, entertainers or hospitality girls below 18 years of age to work as such.', 'Candidate restriction: the stated establishments and roles have a minimum working age of 18.', 'Role and establishment mapping, age timing, employment relationship, labor-law authority, inspection, evidence, employer liability, due process, and current legal validity require reconciliation.', ['candidate_minimum_age' => 18]],
            ['EIGHTEENTH-YEAR-CERTIFICATE', RevenueCodeProvisionClauseType::DocumentaryRequirement, 'A person securing the individual Mayor’s Permit during the 18th birth year shall present a baptismal or birth certificate issued by the local civil registrar concerned.', 'Candidate age evidence: a permittee in the 18th birth year provides one of the stated certificates.', 'Why only the 18th birth year is covered, baptismal certificate authority, civil-registry issuance wording, authenticity, alternatives, retention, privacy, and sufficiency require acceptance.', ['candidate_documents' => ['baptismal_certificate', 'birth_certificate']]],
            ['OTHER-OCCUPATIONS', RevenueCodeProvisionClauseType::Eligibility, 'All other employees and persons who exercise their profession, occupation or calling within the Municipality aside from those specifically mentioned in Section 108.', 'Candidate catch-all covers other workers and practitioners within Ipil.', 'The source cross-reference to Section 108 is unresolved. Profession versus occupation, examination and exemption boundaries, territorial nexus, overlap, and authority require legal reconciliation.', ['source_cross_reference' => 'Section 108', 'known_cross_reference_question' => true]],
        ]);

        $this->persistArticleMClauses('MRC-3M-04-PAYMENT-TIMING', [
            ['FIRST-APPLICATION-ANNUAL-JANUARY-20', RevenueCodeProvisionClauseType::PaymentTiming, 'Fees shall be paid to the Municipal Treasurer upon filing the first application and annually thereafter within the first twenty days of January.', 'Candidate timing: first application payment occurs on filing; subsequent annual payment is due by January 20.', 'Application filing timestamp, first-versus-renewal identity, calendar, late filing, operating-year coverage, collector delegation, receipt, and conflict with birth-month renewal require reconciliation.', ['candidate_annual_due_month' => 1, 'candidate_annual_due_day' => 20]],
            ['EACH-DISTINCT-OCCUPATION', RevenueCodeProvisionClauseType::SeparateEstablishment, 'The permit fee is payable for every separate or distinct occupation or calling engaged in.', 'Candidate unit: one fee for each distinct occupation or calling practiced by a person.', 'Occupation equivalence, combined labels, concurrent or sequential work, employer count, period, additions, removals, and duplicate charging require classification policy.', ['candidate_charge_unit' => 'person_distinct_occupation']],
            ['EMPLOYER-ADVANCE', RevenueCodeProvisionClauseType::PaymentTiming, 'Employer shall advance the fees to the Municipality for its employees.', 'Candidate payer boundary: the employer advances employee Article M fees to the Municipality.', 'Legal liability, payroll recovery, consent, multiple employers, direct employee payment, new hires, refunds, termination, receipt ownership, and accounting require accepted labor and Treasury procedure.', ['candidate_advancing_party' => 'employer']],
            ['CALLING-ID', RevenueCodeProvisionClauseType::RateBand, 'LGU Calling ID shall be secured from the Licensing Office after approval of the Mayor’s Permit for Twenty-Five Pesos (25.00), valid for one year, paid at the Municipal Treasurer.', 'Candidate Calling ID: after permit approval, collect PHP 25.00 through Treasury for a one-year licensing-office ID.', 'Permit approval and ID issuance sequence, person and occupation identity, one ID per permit or person, validity start, replacement, lamination overlap, payer, receipt, renewal, and accepted amount require reconciliation.', ['candidate_issuing_office' => 'Licensing Office', 'candidate_collector' => 'Municipal Treasurer', 'candidate_validity_years' => 1], 2_500],
        ]);

        $this->persistArticleMClauses('MRC-3M-05-LATE-CHANGES-RENEWAL', [
            ['LATE-SURCHARGE', RevenueCodeProvisionClauseType::SurchargeInterest, 'Failure to pay within the prescribed time shall subject a taxpayer to a surcharge of Twenty-five percent (25%) of the original amount of late payment.', 'Candidate late surcharge: 25 percent of the original late amount.', 'Taxpayer versus permittee or employer liability, original amount, trigger date, partial payment, rounding, waiver, correction, and authority require financial reconciliation.', ['candidate_surcharge_percent' => '25.00']],
            ['MONTHLY-PENALTY', RevenueCodeProvisionClauseType::SurchargeInterest, 'Failure to pay within the prescribed time shall subject a taxpayer to a penalty of 2% monthly but not exceed 36 months.', 'Candidate monthly addition: two percent per month capped at 36 months.', 'The source calls this a penalty, not interest. Accrual start, full or partial month, compounding, base amount, cap application, surcharge interaction, payment allocation, rounding, waiver, and current authority require reconciliation.', ['candidate_monthly_percent' => '2.00', 'candidate_maximum_months' => 36, 'source_term' => 'penalty']],
            ['OWNERSHIP-LOCATION-NEW-PERMIT', RevenueCodeProvisionClauseType::LocationTransfer, 'On change of business ownership or location from one municipality to another, the new owner, agent or manager shall secure a new permit and pay as though it were new business.', 'Candidate boundary: stated ownership or inter-municipal location change requires a new Article M permit and fee.', 'Article M person permit versus business ownership grammar, intramunicipal change, employee continuity, new owner or agent liability, effective date, cancellation, and duplicate payment require reconciliation.', ['candidate_triggers' => ['business_ownership_change', 'inter_municipal_location_change']]],
            ['NEW-HIRE-IMMEDIATE-PERMIT', RevenueCodeProvisionClauseType::PermitRequirement, 'Newly hired workers and employees shall secure their individual Mayor’s Permit from the moment they are actually accepted by management to start working.', 'Candidate timing: a newly accepted worker must obtain the permit at the employment-start boundary.', 'Offer acceptance versus first work, probation, pre-employment, temporary and agency staff, employer responsibility, grace period, evidence, and enforcement require policy.', ['candidate_trigger' => 'accepted_by_management_to_start_work']],
            ['BIRTH-MONTH-RENEWAL', RevenueCodeProvisionClauseType::PaymentTiming, 'The individual Mayor’s Permit shall be renewed during the permittee’s birth month next following calendar.', 'Candidate renewal timing: renewal occurs in the permittee’s next birth month.', 'This conflicts with the January 20 annual deadline. Initial permit date, calendar-year meaning, birth month, leap dates, validity, proration, late charge, and transition require authoritative reconciliation.', ['candidate_renewal_basis' => 'birth_month', 'known_timing_conflict' => 'section_3m_04_january_20']],
        ]);

        $this->persistArticleMClauses('MRC-3M-06-ADMINISTRATION', [
            ['BPLO-REGISTER', RevenueCodeProvisionClauseType::RecordRetention, 'The Business Permit and Licensing Office shall keep a record of persons engaged in occupations or callings not requiring government examination and corresponding payment of fees under personal data for reference.', 'Candidate registry: BPLO links person, occupation or calling, permit, and fee-payment evidence.', 'Required personal fields, lawful purpose, occupation history, employer, permit and receipt identity, corrections, duplicate detection, access, privacy, retention, reporting, and migration require accepted procedure.', ['candidate_registry_authority' => 'BPLO', 'candidate_registry_subject' => 'occupational_calling_permittees']],
            ['RETIREMENT-SURRENDER-CANCEL', RevenueCodeProvisionClauseType::PermitCancellation, 'On retirement or cessation, a person with a valid Mayor’s Permit shall surrender the permit and corresponding Official Receipt to the Municipal Treasurer and Municipal Mayor respectively for cancellation.', 'Candidate closure boundary: retirement or cessation requires surrender of the permit and receipt to the stated authorities for cancellation.', 'The “respectively” mapping is unclear. Retirement and cessation evidence, effective date, surrender when lost, Treasurer-versus-Mayor custody, cancellation authority, fee and refund effect, record retention, and re-entry require reconciliation.', ['candidate_trigger' => ['retirement', 'cessation'], 'known_respectively_mapping_ambiguity' => true]],
        ]);
    }

    /**
     * @param  array<int, array{0: string, 1: RevenueCodeProvisionClauseType, 2: string, 3: string, 4: string, 5: array<string, mixed>, 6?: int}>  $clauses
     */
    private function persistArticleMClauses(string $provisionCode, array $clauses): void
    {
        $this->persistPolicyBoundaryClauses($provisionCode, array_map(
            fn (array $clause, int $index): array => $this->policyBoundaryClause(
                sequence: $index + 1,
                code: $provisionCode.'-'.$clause[0],
                type: $clause[1],
                sourceText: $clause[2],
                candidateInterpretation: $clause[3],
                executionBlocker: $clause[4],
                metadata: $clause[5],
                amountCents: $clause[6] ?? null,
            ),
            $clauses,
            array_keys($clauses),
        ));
    }

    /** @return array<string, mixed> */
    private function occupationalCallingFeeClause(int $sequence, string $occupation): array
    {
        $code = Str::of($occupation)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '-')
            ->trim('-')
            ->toString();

        return $this->policyBoundaryClause(
            sequence: $sequence,
            code: 'MRC-3M-01-'.$code,
            type: RevenueCodeProvisionClauseType::RateBand,
            sourceText: $occupation.' - 100.00.',
            candidateInterpretation: 'Candidate source amount: PHP 100.00 annually for a person classified under “'.$occupation.'”.',
            executionBlocker: 'Person, occupation, government-examination boundary, territorial practice, employment and employer identity, annual period, multiple occupations, exemptions, permit and ID relationship, payer, collector, receipt, and accepted operational amount require reconciliation.',
            metadata: ['candidate_occupation' => $occupation, 'candidate_frequency' => 'annual', 'candidate_charge_unit' => 'person_occupation'],
            amountCents: 10_000,
        );
    }

    /**
     * @param  array<int, array{0: string, 1: RevenueCodeProvisionClauseType, 2: string, 3: string, 4: string, 5: array<string, mixed>, 6?: int}>  $clauses
     */
    private function persistArticleLClauses(string $provisionCode, array $clauses): void
    {
        $this->persistPolicyBoundaryClauses($provisionCode, array_map(
            fn (array $clause, int $index): array => $this->policyBoundaryClause(
                sequence: $index + 1,
                code: $provisionCode.'-'.$clause[0],
                type: $clause[1],
                sourceText: $clause[2],
                candidateInterpretation: $clause[3],
                executionBlocker: $clause[4],
                metadata: $clause[5],
                amountCents: $clause[6] ?? null,
            ),
            $clauses,
            array_keys($clauses),
        ));
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function mtopFeeClause(int $sequence, string $codeSuffix, string $sourceText, string $vehicleClass, string $charge, int $amountCents, array $metadata = []): array
    {
        return $this->policyBoundaryClause(
            sequence: $sequence,
            code: 'MRC-3L-05-'.$codeSuffix,
            type: RevenueCodeProvisionClauseType::RateBand,
            sourceText: $sourceText,
            candidateInterpretation: 'Candidate source amount: PHP '.number_format($amountCents / 100, 2, '.', ',').' for '.$charge.' under the stated vehicle class.',
            executionBlocker: 'Vehicle, operator, franchise and application identity; charge unit and cadence; cumulative-versus-alternative treatment; exemptions; payer; collector; receipt; refund; and accepted operational amount require municipal and Treasury reconciliation.',
            metadata: ['candidate_vehicle_class' => $vehicleClass, 'candidate_charge' => $charge, ...$metadata],
            amountCents: $amountCents,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function equipmentPermitFeeClause(int $sequence, string $codeSuffix, string $sourceText, string $equipmentClass, int $amountCents, array $metadata = []): array
    {
        return $this->policyBoundaryClause(
            sequence: $sequence,
            code: 'MRC-3K-01-'.$codeSuffix,
            type: RevenueCodeProvisionClauseType::RateBand,
            sourceText: $sourceText,
            candidateInterpretation: 'Candidate source amount: PHP '.number_format($amountCents / 100, 2, '.', ',').' annually for each equipment item in the stated class.',
            executionBlocker: 'Equipment classification and identity, operator/owner/lessor/lessee eligibility, residence and rental evidence, annual term and proration, unit count, permit identity, payer, collector, exemptions, replacement, cancellation, refund, and accepted operational amount require municipal reconciliation.',
            metadata: [
                'candidate_equipment_class' => $equipmentClass,
                'candidate_unit' => 'equipment_item_per_annum',
                ...$metadata,
            ],
            amountCents: $amountCents,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function weightsMeasureFeeClause(
        int $sequence,
        string $codeSuffix,
        string $sourceText,
        string $instrumentClass,
        int $amountCents,
        array $metadata,
    ): array {
        return $this->policyBoundaryClause(
            sequence: $sequence,
            code: 'MRC-3H-03-'.$codeSuffix,
            type: RevenueCodeProvisionClauseType::RateBand,
            sourceText: $sourceText,
            candidateInterpretation: 'Candidate source amount: PHP '.number_format($amountCents / 100, 2, '.', ',').' for the stated instrument class or item.',
            executionBlocker: 'Instrument classification, technical capacity evidence, boundary eligibility, instrument count and identity, sealing and licensing cadence, payer, collector, failed calibration, exemptions, and accepted operational amount require municipal reconciliation.',
            metadata: [
                'candidate_instrument_class' => $instrumentClass,
                ...$metadata,
            ],
            amountCents: $amountCents,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function weightsMeasureProhibitedPracticeClause(
        int $sequence,
        string $codeSuffix,
        string $sourceText,
        string $candidatePractice,
        array $metadata = [],
    ): array {
        return $this->policyBoundaryClause(
            sequence: $sequence,
            code: 'MRC-3H-08-'.$codeSuffix,
            type: RevenueCodeProvisionClauseType::ProhibitedPractice,
            sourceText: $sourceText,
            candidateInterpretation: 'Candidate prohibited practice: '.$candidatePractice.'.',
            executionBlocker: 'Actor and instrument identity, prohibited act, intent or knowledge standard, official authority, evidence, apprehension, due process, enforcement action, prosecution referral, and penalty mapping require accepted legal and municipal procedure.',
            metadata: $metadata,
        );
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
