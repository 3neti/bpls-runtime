<?php

use App\Enums\RevenueCodeProvisionClauseType;
use App\Enums\RevenueCodeProvisionStatus;
use App\Models\RevenueCodeProvision;
use App\Models\RevenueCodeProvisionClause;

it('stores a typed non-executable clause beneath its legal provision', function () {
    $provision = RevenueCodeProvision::factory()->create();

    $clause = RevenueCodeProvisionClause::factory()
        ->for($provision, 'provision')
        ->create([
            'clause_type' => RevenueCodeProvisionClauseType::AmountCeiling,
            'amount_cents' => 6_275,
            'is_ceiling' => true,
            'reconciliation_status' => RevenueCodeProvisionStatus::ReconciliationRequired,
        ]);

    expect($clause)
        ->clause_type->toBe(RevenueCodeProvisionClauseType::AmountCeiling)
        ->amount_cents->toBe(6_275)
        ->is_ceiling->toBeTrue()
        ->reconciliation_status->toBe(RevenueCodeProvisionStatus::ReconciliationRequired)
        ->metadata->candidate_values_are_non_executable->toBeTrue()
        ->and($clause->provision->is($provision))->toBeTrue()
        ->and($provision->clauses()->sole()->is($clause))->toBeTrue();
});
