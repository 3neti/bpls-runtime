<?php

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\FeeRule;
use Database\Seeders\RevenueCodeFeeCatalogSeeder;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the read-only taxes and fees catalog to authorized staff', function () {
    $this->seed(RevenueCodeFeeCatalogSeeder::class);

    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewFeeRules,
    ], UserRole::Bplo);

    $this->actingAs($user)
        ->get(route('staff.fee-rules.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('fee-rules/Index')
            ->where('summary.total_rules', 4)
            ->where('summary.active_rules', 4)
            ->where('summary.mrc_rules', 4)
            ->where('summary.blocked_policy_count', 3)
            ->where('summary.executable_rule_count', 1)
            ->where('summary.provisions_recorded', 39)
            ->where('summary.provisions_requiring_reconciliation', 38)
            ->where('summary.provisions_linked_to_rules', 4)
            ->where('summary.policy_boundary_clauses', 254)
            ->where('summary.policy_boundary_clauses_requiring_reconciliation', 254)
            ->has('revenueCodeProvisions', 39)
            ->where('revenueCodeProvisions.1.code', 'MRC-2A-02-B-WHOLESALERS')
            ->where('revenueCodeProvisions.1.reconciliation_status', 'reconciliation_required')
            ->where('revenueCodeProvisions.1.fee_rule.code', 'MRC-2A-02-B-RETAIL-BUSINESS-TAX')
            ->where('revenueCodeProvisions.1.fee_rule.execution_status', 'blocked')
            ->has('revenueCodeScheduleMatrices', 4)
            ->where('revenueCodeScheduleMatrices.0.provision.code', 'MRC-2A-02-A-MANUFACTURERS')
            ->where('revenueCodeScheduleMatrices.0.summary.row_count', 20)
            ->where('revenueCodeScheduleMatrices.0.summary.reconciliation_required_count', 2)
            ->where('revenueCodeScheduleMatrices.0.summary.overlap_count', 0)
            ->where('revenueCodeScheduleMatrices.0.summary.execution_ready', false)
            ->has('revenueCodeScheduleMatrices.0.rows', 20)
            ->where('revenueCodeScheduleMatrices.0.rows.17.code', 'MRC-2A-02-A-ROW-18')
            ->where('revenueCodeScheduleMatrices.0.rows.17.normalization_status', 'reconciliation_required')
            ->where('revenueCodeScheduleMatrices.1.provision.code', 'MRC-2A-02-B-WHOLESALERS')
            ->where('revenueCodeScheduleMatrices.1.provision.linked_fee_rule_execution_status', 'blocked')
            ->where('revenueCodeScheduleMatrices.1.summary.row_count', 24)
            ->where('revenueCodeScheduleMatrices.1.summary.reconciliation_required_count', 3)
            ->where('revenueCodeScheduleMatrices.1.summary.overlap_count', 1)
            ->where('revenueCodeScheduleMatrices.1.summary.gap_count', 0)
            ->where('revenueCodeScheduleMatrices.1.summary.ceiling_count', 1)
            ->where('revenueCodeScheduleMatrices.1.summary.execution_ready', false)
            ->has('revenueCodeScheduleMatrices.1.rows', 24)
            ->where('revenueCodeScheduleMatrices.1.rows.7.code', 'MRC-2A-02-B-ROW-08')
            ->where('revenueCodeScheduleMatrices.1.rows.7.issues.0.type', 'overlap')
            ->where('revenueCodeScheduleMatrices.2.provision.code', 'MRC-2A-02-E-CONTRACTORS')
            ->where('revenueCodeScheduleMatrices.2.summary.overlap_count', 1)
            ->where('revenueCodeScheduleMatrices.2.rows.14.issues.0.type', 'overlap')
            ->where('revenueCodeScheduleMatrices.3.provision.code', 'MRC-2A-02-G-ENUMERATED-SERVICES')
            ->where('revenueCodeScheduleMatrices.3.summary.overlap_count', 1)
            ->where('revenueCodeScheduleMatrices.3.rows.18.rate_basis_points', '57.2300')
            ->has('revenueCodePolicyBoundaries', 37)
            ->where('revenueCodePolicyBoundaries.0.provision.code', 'MRC-2A-02-B-WHOLESALERS')
            ->where('revenueCodePolicyBoundaries.0.clauses.0.clause_type', 'tax_scope_boundary')
            ->where('revenueCodePolicyBoundaries.1.provision.code', 'MRC-2A-02-C-EXPORTERS-ESSENTIALS')
            ->has('revenueCodePolicyBoundaries.1.clauses', 3)
            ->where('revenueCodePolicyBoundaries.1.clauses.0.code', 'MRC-2A-02-C-DEPENDENT-HALF-RATE')
            ->where('revenueCodePolicyBoundaries.1.clauses.0.is_ceiling', true)
            ->where('revenueCodePolicyBoundaries.1.clauses.0.candidate_values_are_non_executable', true)
            ->where('revenueCodePolicyBoundaries.2.provision.code', 'MRC-2A-02-D-RETAILERS')
            ->where('revenueCodePolicyBoundaries.2.clauses.1.rate_basis_points', '126.0000')
            ->where('revenueCodePolicyBoundaries.3.provision.code', 'MRC-2A-02-E-CONTRACTORS')
            ->has('revenueCodePolicyBoundaries.3.clauses', 4)
            ->where('revenueCodePolicyBoundaries.3.clauses.2.clause_type', 'installment_schedule')
            ->where('revenueCodePolicyBoundaries.4.provision.code', 'MRC-2A-02-F-FINANCIAL-INSTITUTIONS')
            ->where('revenueCodePolicyBoundaries.4.clauses.1.clause_type', 'taxable_receipt_catalog')
            ->where('revenueCodePolicyBoundaries.5.provision.code', 'MRC-2A-02-G-ENUMERATED-SERVICES')
            ->where('revenueCodePolicyBoundaries.5.clauses.1.amount_cents', 1447793)
            ->where('revenueCodePolicyBoundaries.6.provision.code', 'MRC-2A-02-H-PEDDLERS')
            ->where('revenueCodePolicyBoundaries.6.clauses.0.amount_cents', 6275)
            ->where('revenueCodePolicyBoundaries.7.provision.code', 'MRC-2B-02-04-MOBILE-TRADERS')
            ->where('revenueCodePolicyBoundaries.7.clauses.0.rate_basis_points', '100.0000')
            ->where('revenueCodePolicyBoundaries.8.provision.code', 'MRC-2B-05-06-PUBLIC-UTILITY-VEHICLES')
            ->has('revenueCodePolicyBoundaries.8.clauses', 5)
            ->where('revenueCodePolicyBoundaries.8.clauses.3.amount_cents', 114450)
            ->where('revenueCodePolicyBoundaries.9.provision.code', 'MRC-2B-07-AMUSEMENT-OPERATORS')
            ->has('revenueCodePolicyBoundaries.9.clauses', 3)
            ->where('revenueCodePolicyBoundaries.9.clauses.1.amount_cents', 54500)
            ->where('revenueCodePolicyBoundaries.10.provision.code', 'MRC-2B-08-09-OTHER-BUSINESSES')
            ->where('revenueCodePolicyBoundaries.10.clauses.1.is_ceiling', true)
            ->where('revenueCodePolicyBoundaries.11.provision.code', 'MRC-2C-01-PETROLEUM-EXEMPTION')
            ->where('revenueCodePolicyBoundaries.11.clauses.0.clause_type', 'exemption')
            ->where('revenueCodePolicyBoundaries.12.provision.code', 'MRC-2C-02-NEWLY-STARTED-BUSINESS')
            ->where('revenueCodePolicyBoundaries.12.clauses.1.clause_type', 'initial_tax_basis')
            ->where('revenueCodePolicyBoundaries.13.provision.code', 'MRC-2D-01-SITUS-DEFINITIONS')
            ->has('revenueCodePolicyBoundaries.13.clauses', 5)
            ->where('revenueCodePolicyBoundaries.13.clauses.0.clause_type', 'situs_definition')
            ->where('revenueCodePolicyBoundaries.14.provision.code', 'MRC-2D-01-SALES-ALLOCATION')
            ->has('revenueCodePolicyBoundaries.14.clauses', 10)
            ->where('revenueCodePolicyBoundaries.14.clauses.2.clause_type', 'sales_allocation')
            ->where('revenueCodePolicyBoundaries.15.provision.code', 'MRC-2D-01-PORT-ROUTE-SALES')
            ->has('revenueCodePolicyBoundaries.15.clauses', 3)
            ->where('revenueCodePolicyBoundaries.15.clauses.0.clause_type', 'sales_allocation')
            ->where('revenueCodePolicyBoundaries.16.provision.code', 'MRC-2E-01-BUSINESS-TAX-SCOPE')
            ->where('revenueCodePolicyBoundaries.16.clauses.2.clause_type', 'combined_tax_base')
            ->where('revenueCodePolicyBoundaries.17.provision.code', 'MRC-2E-02-03-ACCRUAL-PAYMENT')
            ->where('revenueCodePolicyBoundaries.17.clauses.2.code', 'MRC-2E-03-QUARTERLY-INSTALLMENTS')
            ->where('revenueCodePolicyBoundaries.18.provision.code', 'MRC-2E-04-A-C-PERMIT-RECEIPT-REQUIREMENTS')
            ->has('revenueCodePolicyBoundaries.18.clauses', 9)
            ->where('revenueCodePolicyBoundaries.18.clauses.7.clause_type', 'record_retention')
            ->where('revenueCodePolicyBoundaries.19.provision.code', 'MRC-2E-04-D-E-DECLARATIONS-DEFICIENCY')
            ->where('revenueCodePolicyBoundaries.19.clauses.5.clause_type', 'surcharge_interest')
            ->where('revenueCodePolicyBoundaries.20.provision.code', 'MRC-2E-04-DEATH-TAX-MAPPING')
            ->where('revenueCodePolicyBoundaries.20.clauses.0.clause_type', 'estate_continuation')
            ->where('revenueCodePolicyBoundaries.21.provision.code', 'MRC-2E-04-F-LOST-RECEIPT-CERTIFICATION')
            ->where('revenueCodePolicyBoundaries.21.clauses.0.amount_cents', 10000)
            ->where('revenueCodePolicyBoundaries.22.provision.code', 'MRC-2E-04-RETIREMENT')
            ->has('revenueCodePolicyBoundaries.22.clauses', 12)
            ->where('revenueCodePolicyBoundaries.22.clauses.10.clause_type', 'permit_cancellation')
            ->where('revenueCodePolicyBoundaries.23.provision.code', 'MRC-2E-04-G-LOCATION-TRANSFER')
            ->where('revenueCodePolicyBoundaries.23.clauses.0.clause_type', 'location_transfer')
            ->where('revenueCodePolicyBoundaries.24.provision.code', 'MRC-2F-01-PIL')
            ->has('revenueCodePolicyBoundaries.24.clauses', 29)
            ->where('revenueCodePolicyBoundaries.24.clauses.2.code', 'MRC-2F-01-PIL-GROCERY')
            ->where('revenueCodePolicyBoundaries.24.clauses.2.amount_cents', 616000000)
            ->where('revenueCodePolicyBoundaries.24.clauses.10.amount_cents', null)
            ->where('revenueCodePolicyBoundaries.24.clauses.28.clause_type', 'validation_fallback')
            ->where('revenueCodePolicyBoundaries.25.provision.code', 'MRC-3A-01-02-PERMIT-SCOPE-ENTERPRISE-SCALE')
            ->has('revenueCodePolicyBoundaries.25.clauses', 7)
            ->where('revenueCodePolicyBoundaries.26.provision.code', 'MRC-3A-02-A-01-06-GENERAL-PERMIT-FEES')
            ->has('revenueCodePolicyBoundaries.26.clauses', 27)
            ->where('revenueCodePolicyBoundaries.26.clauses.17.code', 'MRC-3A-02-A-05-SERVICE-UNLABELED')
            ->where('revenueCodePolicyBoundaries.26.clauses.17.amount_cents', 50000)
            ->where('revenueCodePolicyBoundaries.26.clauses.17.candidate_values_are_non_executable', true)
            ->where('revenueCodePolicyBoundaries.27.provision.code', 'MRC-3A-02-A-07-13-SPECIAL-PERMIT-FEES')
            ->has('revenueCodePolicyBoundaries.27.clauses', 29)
            ->where('revenueCodePolicyBoundaries.27.clauses.12.code', 'MRC-3A-02-A-10-GASOLINE-UNLABELED')
            ->where('revenueCodePolicyBoundaries.28.provision.code', 'MRC-3A-02-B-NEW-MICRO-PERMIT')
            ->has('revenueCodePolicyBoundaries.28.clauses', 5)
            ->where('revenueCodePolicyBoundaries.29.provision.code', 'MRC-3A-03-PAYMENT-PRORATION')
            ->has('revenueCodePolicyBoundaries.29.clauses', 4)
            ->where('revenueCodePolicyBoundaries.30.provision.code', 'MRC-3A-05-REGISTRATION-PLATE')
            ->has('revenueCodePolicyBoundaries.30.clauses', 2)
            ->where('revenueCodePolicyBoundaries.31.provision.code', 'MRC-3B-01-DEFINITIONS')
            ->has('revenueCodePolicyBoundaries.31.clauses', 11)
            ->where('revenueCodePolicyBoundaries.31.clauses.2.clause_type', 'definition')
            ->where('revenueCodePolicyBoundaries.32.provision.code', 'MRC-3B-02-PERMIT-FEES')
            ->has('revenueCodePolicyBoundaries.32.clauses', 13)
            ->where('revenueCodePolicyBoundaries.32.clauses.11.amount_cents', 10000)
            ->where('revenueCodePolicyBoundaries.32.clauses.12.amount_cents', null)
            ->where('revenueCodePolicyBoundaries.33.provision.code', 'MRC-3B-03-04-FRANCHISE-LICENSING-REGISTRATION')
            ->has('revenueCodePolicyBoundaries.33.clauses', 11)
            ->where('revenueCodePolicyBoundaries.34.provision.code', 'MRC-3B-05-06-PAYMENT-APPLICABILITY')
            ->has('revenueCodePolicyBoundaries.34.clauses', 6)
            ->where('revenueCodePolicyBoundaries.35.provision.code', 'MRC-3B-07-OPERATIONS')
            ->has('revenueCodePolicyBoundaries.35.clauses', 15)
            ->where('revenueCodePolicyBoundaries.36.provision.code', 'MRC-3B-08-PENALTIES')
            ->has('revenueCodePolicyBoundaries.36.clauses', 2)
            ->where('revenueCodePolicyBoundaries.36.clauses.1.clause_type', 'penalty')
            ->has('feeRules.data', 4)
            ->has('feeRules.data.0', fn (Assert $rule) => $rule
                ->where('code', 'MRC-2A-02-B-RETAIL-BUSINESS-TAX')
                ->where('category', 'tax')
                ->where('scope', 'line_of_business')
                ->where('calculation_type', 'range')
                ->where('range_count', 23)
                ->where('catalog_status', 'recorded_non_executable')
                ->where('current_reconciliation.execution_status', 'blocked')
                ->where('application_types', ['renewal'])
                ->where('policy_boundaries.0', 'new_business_initial_local_business_tax_exemption')
                ->etc()
            )
            ->has('categories')
            ->has('scopes')
            ->has('calculationTypes')
        );
});

it('shows fee rule detail with ranges and policy boundaries to authorized staff', function () {
    $this->seed(RevenueCodeFeeCatalogSeeder::class);

    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewFeeRules,
    ], UserRole::Bplo);
    $feeRule = FeeRule::query()
        ->where('code', 'MRC-2A-02-B-RETAIL-BUSINESS-TAX')
        ->sole();

    $this->actingAs($user)
        ->get(route('staff.fee-rules.show', $feeRule))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('fee-rules/Show')
            ->where('feeRule.code', 'MRC-2A-02-B-RETAIL-BUSINESS-TAX')
            ->where('feeRule.name', 'Business Tax - Wholesalers/Retailers/Dealers/Distributors')
            ->where('feeRule.category', 'tax')
            ->where('feeRule.scope', 'line_of_business')
            ->where('feeRule.calculation_type', 'range')
            ->where('feeRule.range_count', 23)
            ->where('feeRule.catalog_status', 'recorded_non_executable')
            ->where('feeRule.reconciliation_required', true)
            ->where('feeRule.current_reconciliation.execution_status', 'blocked')
            ->where('feeRule.current_reconciliation.legal_authority', 'Municipality of Ipil Ordinance No. 08-656-2023')
            ->where('feeRule.current_reconciliation.decision_authority', 'Municipality of Ipil - decision pending')
            ->where('feeRule.current_reconciliation.decision_reference', 'Engineering Program Review #005 Board Decision (software execution refusal)')
            ->where('feeRule.current_reconciliation.execution_reason', 'The wholesale/dealer schedule contains overlapping and malformed brackets that require an accepted municipal reconciliation.')
            ->where('feeRule.application_types', ['renewal'])
            ->where('feeRule.policy_boundaries.0', 'new_business_initial_local_business_tax_exemption')
            ->where('feeRule.line_of_business.name', 'Wholesalers, Retailers, Dealers or Distributors')
            ->has('feeRule.ranges', 23)
            ->where('feeRule.ranges.0.min_basis_cents', 0)
            ->where('feeRule.ranges.0.amount_cents', 2266)
            ->where('scopeNote', 'This detail page is read-only evidence. A recorded ordinance extract is executable only when its current reconciliation explicitly authorizes deterministic execution.')
        );
});

it('shows exact ordinance execution authority separately from recorded evidence', function () {
    $this->seed(RevenueCodeFeeCatalogSeeder::class);

    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewFeeRules,
    ], UserRole::Bplo);
    $feeRule = FeeRule::query()
        ->where('code', 'MRC-3A-04-BUSINESS-INSPECTION')
        ->sole();

    $this->actingAs($user)
        ->get(route('staff.fee-rules.show', $feeRule))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('feeRule.current_reconciliation.execution_status', 'executable')
            ->where('feeRule.current_reconciliation.normalized_interpretation', 'Charge one annual PHP 350.00 business inspection fee per permit application.')
            ->where('feeRule.current_reconciliation.decision_authority', 'Municipality of Ipil Sangguniang Bayan')
            ->where('feeRule.current_reconciliation.decision_reference', 'Ordinance No. 08-656-2023 Section 3A.04')
            ->where('feeRule.amount_cents', 35000)
            ->etc()
        );
});

it('filters fee rules by category and search text', function () {
    $this->seed(RevenueCodeFeeCatalogSeeder::class);

    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewFeeRules,
    ], UserRole::Bplo);

    $this->actingAs($user)
        ->get(route('staff.fee-rules.index', [
            'category' => 'fee',
            'q' => 'inspection',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('fee-rules/Index')
            ->where('filters.category', 'fee')
            ->where('filters.q', 'inspection')
            ->has('feeRules.data', 1)
            ->where('feeRules.data.0.code', 'MRC-3A-04-BUSINESS-INSPECTION')
        );
});

it('blocks staff without fee-rule view permission', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
    ], UserRole::Bplo);

    $this->actingAs($user)
        ->get(route('staff.fee-rules.index'))
        ->assertForbidden();
});

it('blocks fee rule detail from staff without fee-rule view permission', function () {
    $this->seed(RevenueCodeFeeCatalogSeeder::class);

    $user = userWithPermissions([
        UserPermission::AccessStaff,
    ], UserRole::Bplo);
    $feeRule = FeeRule::query()
        ->where('code', 'MRC-2A-02-B-RETAIL-BUSINESS-TAX')
        ->sole();

    $this->actingAs($user)
        ->get(route('staff.fee-rules.show', $feeRule))
        ->assertForbidden();
});
