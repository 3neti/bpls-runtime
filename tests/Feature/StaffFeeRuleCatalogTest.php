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
            ->where('summary.blocked_policy_count', 1)
            ->has('feeRules.data', 4)
            ->has('feeRules.data.0', fn (Assert $rule) => $rule
                ->where('code', 'MRC-2A-02-B-RETAIL-BUSINESS-TAX')
                ->where('category', 'tax')
                ->where('scope', 'line_of_business')
                ->where('calculation_type', 'range')
                ->where('range_count', 23)
                ->where('catalog_status', 'partial_executable_extract')
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
            ->where('feeRule.catalog_status', 'partial_executable_extract')
            ->where('feeRule.application_types', ['renewal'])
            ->where('feeRule.policy_boundaries.0', 'new_business_initial_local_business_tax_exemption')
            ->where('feeRule.line_of_business.name', 'Wholesalers, Retailers, Dealers or Distributors')
            ->has('feeRule.ranges', 23)
            ->where('feeRule.ranges.0.min_basis_cents', 0)
            ->where('feeRule.ranges.0.amount_cents', 2266)
            ->where('scopeNote', 'This detail page is read-only evidence. It exposes the persisted catalog rule, legal provenance, applicability, and unresolved policy boundaries without editing rates or inventing assessment behavior.')
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
