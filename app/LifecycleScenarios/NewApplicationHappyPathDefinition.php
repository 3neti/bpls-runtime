<?php

namespace App\LifecycleScenarios;

final class NewApplicationHappyPathDefinition
{
    public const string Id = 'new-application-happy-path';

    public const string RunId = 'scenario-01-canonical';

    public const string Revision = 'bplo_routing_payment_orders_v1';

    public const int ApplicationYear = 2025;

    public const string EffectiveDate = '2025-01-15';

    public const int ExpectedGrandTotalCents = 122_000;

    public const string EvidenceQuestion = 'Can a brand-new Citizen and Business become an approved Payable?';

    /** @return array<string, mixed> */
    public function describe(): array
    {
        return [
            'id' => self::Id,
            'label' => 'Scenario 01 — New Application Happy Path',
            'business_question' => self::EvidenceQuestion,
            'evidence' => [
                'assessment_grammar' => [
                    'classification' => 'accepted',
                    'sources' => ['OPERATIONAL-IPIL-ASSESSMENT-001', 'OPERATIONAL-IPIL-ASSESSMENT-002'],
                    'meaning' => 'Application, LOB charges and subtotals, application-wide charges, Grand Total, Prepared By, and Approved By.',
                ],
                'treasurer_authority' => [
                    'classification' => 'accepted',
                    'sources' => ['NFI-2026-008', 'OPERATIONAL-NELSON-001'],
                    'meaning' => 'Assessment Officer prepares; Municipal Treasurer approves the exact Assessment before payment.',
                ],
                'synthetic_applicability_and_prices' => [
                    'classification' => 'provisional_uat',
                    'meaning' => 'The six scenario-specific departmental amounts are provisional_uat. The ₱350 Business Inspection Fee is an accepted governed municipal rule and is not provisional_uat.',
                ],
                'external_settlement' => [
                    'classification' => 'blocked',
                    'meaning' => 'Scenario ends at Payable and performs no collection or settlement.',
                ],
            ],
            'application_year' => self::ApplicationYear,
            'effective_date' => self::EffectiveDate,
            'lines_of_business' => $this->linesOfBusiness(),
            'responsibilities' => $this->responsibilities(),
            'application_wide_charges' => [[
                'code' => 'MRC-3A-04-BUSINESS-INSPECTION',
                'label' => 'Business Inspection Fee',
                'amount_cents' => 35_000,
                'classification' => 'accepted',
                'scope' => 'application',
            ]],
            'expected' => [
                'responsibility_count' => 6,
                'assessment_line_count' => 7,
                'application_subtotal_amount_cents' => 35_000,
                'grand_total_amount_cents' => self::ExpectedGrandTotalCents,
                'terminal_application_status' => 'pending_payment',
                'payment_schedule_status' => 'pending',
            ],
        ];
    }

    /**
     * @return list<array{
     *     code: string,
     *     name: string,
     *     major_category: string,
     *     declared_gross_sales_cents: int,
     *     capital_investment_cents: int,
     *     subtotal_amount_cents: int
     * }>
     */
    public function linesOfBusiness(): array
    {
        return [
            [
                'code' => 'PRODUCT-LAB-RETAIL-TRADING',
                'name' => 'Retail Trading',
                'major_category' => 'Retail',
                'declared_gross_sales_cents' => 120_000_000,
                'capital_investment_cents' => 60_000_000,
                'subtotal_amount_cents' => 33_000,
            ],
            [
                'code' => 'PRODUCT-LAB-FOOD-SERVICE',
                'name' => 'Food Service',
                'major_category' => 'Food Service',
                'declared_gross_sales_cents' => 85_000_000,
                'capital_investment_cents' => 45_000_000,
                'subtotal_amount_cents' => 54_000,
            ],
        ];
    }

    /**
     * @return list<array{
     *     key: string,
     *     department: string,
     *     line_of_business_code: string,
     *     code: string,
     *     label: string,
     *     amount_cents: int,
     *     inspection_required: bool,
     *     applicability: string,
     *     classification: string,
     *     reason: string,
     *     provenance: string
     * }>
     */
    public function responsibilities(): array
    {
        return [
            $this->responsibility('retail.business-tax.charge', 'assessor', 'PRODUCT-LAB-RETAIL-TRADING', 'S01-RETAIL-BUSINESS-TAX', 'Business Tax', 24_000, false, 'Assessor reviews the declared gross-receipts basis for the Retail Trading LOB.'),
            $this->responsibility('retail.mayors-permit.charge', 'engineering', 'PRODUCT-LAB-RETAIL-TRADING', 'S01-RETAIL-MAYORS-PERMIT', "Mayor's Permit Fee", 9_000, true, 'Engineering reviews the Retail premises for provisional permit-fee applicability.'),
            $this->responsibility('food.business-tax.charge', 'assessor', 'PRODUCT-LAB-FOOD-SERVICE', 'S01-FOOD-BUSINESS-TAX', 'Business Tax', 31_000, false, 'Assessor reviews the declared gross-receipts basis for the Food Service LOB.'),
            $this->responsibility('food.health-certificate.charge', 'health', 'PRODUCT-LAB-FOOD-SERVICE', 'S01-FOOD-HEALTH-CERTIFICATE', 'Health Certificate', 9_500, true, 'Health reviews Food Service operations for provisional Health Certificate applicability.'),
            $this->responsibility('food.sanitary-permit.charge', 'health', 'PRODUCT-LAB-FOOD-SERVICE', 'S01-FOOD-SANITARY-PERMIT', 'Sanitary Permit Fee', 6_500, true, 'Health reviews Food Service sanitation for provisional Sanitary Permit applicability.'),
            $this->responsibility('food.solid-waste.charge', 'menro', 'PRODUCT-LAB-FOOD-SERVICE', 'S01-FOOD-SOLID-WASTE', 'Solid Waste Management', 7_000, true, 'MENRO reviews the provisionally waste-producing Food Service activity.'),
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     department: string,
     *     line_of_business_code: string,
     *     code: string,
     *     label: string,
     *     amount_cents: int,
     *     inspection_required: bool,
     *     applicability: string,
     *     classification: string,
     *     reason: string,
     *     provenance: string
     * }
     */
    private function responsibility(
        string $key,
        string $department,
        string $lineOfBusinessCode,
        string $code,
        string $label,
        int $amountCents,
        bool $inspectionRequired,
        string $reason,
    ): array {
        return [
            'key' => $key,
            'department' => $department,
            'line_of_business_code' => $lineOfBusinessCode,
            'code' => $code,
            'label' => $label,
            'amount_cents' => $amountCents,
            'inspection_required' => $inspectionRequired,
            'applicability' => 'applicable',
            'classification' => 'provisional_uat',
            'reason' => $reason,
            'provenance' => 'Deterministic Scenario 01 synthetic proposal for product and engine certification only.',
        ];
    }
}
