<?php

namespace Database\Factories;

use App\Enums\RevenueCodeProvisionClauseType;
use App\Enums\RevenueCodeProvisionStatus;
use App\Models\RevenueCodeProvision;
use App\Models\RevenueCodeProvisionClause;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RevenueCodeProvisionClause>
 */
class RevenueCodeProvisionClauseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'revenue_code_provision_id' => RevenueCodeProvision::factory(),
            'sequence' => 1,
            'code' => fake()->unique()->bothify('MRC-CLAUSE-####'),
            'clause_type' => RevenueCodeProvisionClauseType::Eligibility,
            'source_text' => fake()->sentence(),
            'candidate_interpretation' => fake()->sentence(),
            'amount_cents' => null,
            'rate_basis_points' => null,
            'is_ceiling' => false,
            'reconciliation_status' => RevenueCodeProvisionStatus::ReconciliationRequired,
            'execution_blocker' => fake()->sentence(),
            'metadata' => ['candidate_values_are_non_executable' => true],
        ];
    }
}
