<?php

namespace Database\Seeders;

use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleScope;
use App\Models\FeeRule;
use App\Models\FeeRuleRange;
use App\Models\LineOfBusiness;
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

        $this->fixedRule(
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
                'catalog_status' => 'executable_foundation',
            ],
        );

        $this->fixedRule(
            code: 'MRC-3A-04-BUSINESS-INSPECTION',
            name: 'Business Inspection Fee',
            amountCents: 35_000,
            legalBasis: 'LEGAL-MRC-001 Section 3A.04: uniform annual business inspection fee.',
            metadata: [
                'source_id' => 'LEGAL-MRC-001',
                'source_section' => 'Section 3A.04',
                'catalog_status' => 'executable_foundation',
            ],
        );

        $this->fixedRule(
            code: 'MRC-3A-05-BUSINESS-REGISTRATION-PLATE',
            name: 'Business Permit Registration Plate',
            amountCents: 30_000,
            legalBasis: 'LEGAL-MRC-001 Section 3A.05: business permit registration plate amount not to exceed PHP 300.00.',
            metadata: [
                'source_id' => 'LEGAL-MRC-001',
                'source_section' => 'Section 3A.05',
                'application_types' => ['new'],
                'catalog_status' => 'executable_foundation',
                'policy_note' => 'Ordinance states not to exceed PHP 300.00; production configuration must confirm the exact charged amount.',
            ],
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
                    'catalog_status' => 'partial_executable_extract',
                    'extraction_scope' => 'Fixed-amount brackets only; percentage/rate continuation above PHP 2,000,000 remains blocked by rounding and policy confirmation.',
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
}
