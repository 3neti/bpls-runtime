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
            ->where('summary.provisions_recorded', 11)
            ->where('summary.provisions_requiring_reconciliation', 10)
            ->where('summary.provisions_linked_to_rules', 4)
            ->where('summary.policy_boundary_clauses', 19)
            ->where('summary.policy_boundary_clauses_requiring_reconciliation', 19)
            ->has('revenueCodeProvisions', 11)
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
            ->has('revenueCodePolicyBoundaries', 7)
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
