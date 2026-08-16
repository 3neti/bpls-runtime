<?php

namespace Database\Factories;

use App\Enums\BillingGroupEvidenceType;
use App\Enums\BillingGroupReconciliationStatus;
use App\Models\BillingGroup;
use App\Models\BillingGroupReconciliation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BillingGroupReconciliation> */
class BillingGroupReconciliationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'billing_group_id' => BillingGroup::factory(),
            'recorded_by_id' => User::factory(),
            'version' => 1,
            'evidence_type' => BillingGroupEvidenceType::LegacyConfiguration,
            'evidence_reference' => 'Legacy billing-group configuration snapshot',
            'source_excerpt' => 'A configurable billing group was present in the legacy application.',
            'operational_interpretation' => null,
            'unresolved_questions' => ['Municipal acceptance remains required.'],
            'reconciliation_status' => BillingGroupReconciliationStatus::PendingMunicipalDecision,
            'execution_status' => 'blocked',
            'execution_reason' => 'Evidence recording does not authorize financial execution.',
            'definition_snapshot' => ['acceptance_status' => 'provisional', 'fields' => []],
            'metadata' => null,
        ];
    }
}
