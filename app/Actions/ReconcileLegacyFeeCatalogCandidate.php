<?php

namespace App\Actions;

use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleExecutionStatus;
use App\Enums\FeeRuleScope;
use App\Enums\LegacyFeeRuleReconciliationStatus;
use App\Models\FeeRule;
use App\Models\FeeRuleAuditEvent;
use App\Models\FeeRuleRange;
use App\Models\FeeRuleReconciliation;
use App\Models\LegacyFeeRuleReconciliation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

final class ReconcileLegacyFeeCatalogCandidate
{
    public function handle(LegacyFeeRuleReconciliation $candidate, string $action, ?int $feeRuleId, string $reason, User $actor): LegacyFeeRuleReconciliation
    {
        return DB::transaction(function () use ($candidate, $action, $feeRuleId, $reason, $actor): LegacyFeeRuleReconciliation {
            $candidate = LegacyFeeRuleReconciliation::query()->lockForUpdate()->findOrFail($candidate->id);
            if ($candidate->status !== LegacyFeeRuleReconciliationStatus::Pending) {
                throw new LogicException('A decided legacy fee reconciliation cannot be changed through candidate review.');
            }

            $metadata = $candidate->metadata ?? [];
            $feeRule = match ($action) {
                'map_existing' => $this->existingFeeRule($feeRuleId),
                'create_proposed' => $candidate->fee_rule_id === null
                    ? $this->createProposedFeeRule($candidate, $actor)
                    : FeeRule::query()->findOrFail($candidate->fee_rule_id),
                'quarantine' => null,
                default => throw new LogicException('Unknown legacy fee catalogue review action.'),
            };

            $candidate->fill([
                'fee_rule_id' => $feeRule?->id,
                'metadata' => [
                    ...$metadata,
                    'review_disposition' => $action,
                    'review_reason' => $reason,
                    'reviewed_by_id' => $actor->id,
                    'reviewed_at' => now()->toIso8601String(),
                    'executable' => false,
                ],
            ])->save();

            return $candidate->refresh()->load('feeRule');
        });
    }

    private function existingFeeRule(?int $feeRuleId): FeeRule
    {
        if ($feeRuleId === null) {
            throw new LogicException('Select an existing Price List rule to record a proposed mapping.');
        }

        return FeeRule::query()->findOrFail($feeRuleId);
    }

    private function createProposedFeeRule(LegacyFeeRuleReconciliation $candidate, User $actor): FeeRule
    {
        $candidate->loadMissing('source');
        $metadata = $candidate->metadata ?? [];
        $sourceHash = (string) data_get($metadata, 'source_fee_id_sha256');
        $calculationType = match (data_get($metadata, 'calculation_type')) {
            'constant' => FeeRuleCalculationType::Fixed,
            'range' => FeeRuleCalculationType::Range,
            default => FeeRuleCalculationType::Formula,
        };
        $scope = data_get($metadata, 'applicability_scope') === 'application_wide'
            ? FeeRuleScope::Application
            : FeeRuleScope::LineOfBusiness;
        $basis = match (data_get($metadata, 'range_field')) {
            null => 'none',
            'capitalInvestment' => 'capital_investment',
            'grossSales' => 'declared_gross_sales',
            default => 'legacy_unresolved',
        };
        $sourceBaseline = $candidate->source->baseline;
        $effectiveFrom = is_string($sourceBaseline) && preg_match('/^\d{4}-\d{2}-\d{2}/', $sourceBaseline) === 1
            ? substr($sourceBaseline, 0, 10)
            : now()->toDateString();

        $applicationTypes = data_get($metadata, 'application_types', []);
        if (! is_array($applicationTypes)) {
            $applicationTypes = [];
        }

        $feeRule = FeeRule::query()->create([
            'code' => 'IPIL-LEGACY-'.strtoupper(substr($sourceHash, 0, 16)),
            'name' => (string) data_get($metadata, 'name'),
            'category' => $this->category(data_get($metadata, 'legacy_fee_category')),
            'scope' => $scope,
            'calculation_type' => $calculationType,
            'basis' => $basis,
            'amount_cents' => max(0, (int) data_get($metadata, 'amount_minor', 0)),
            'effective_from' => $effectiveFrom,
            'legal_basis' => 'Observed legacy Ipil production configuration; fiscal acceptance is pending.',
            'is_active' => false,
            'legacy_source_id' => 'sha256:'.$sourceHash,
            'metadata' => [
                'catalog_status' => 'legacy_candidate',
                'reconciliation_required' => true,
                'price_list_source_classification' => 'municipal_confirmation_required',
                'legacy_source_payload_sha256' => data_get($metadata, 'source_payload_sha256'),
                'legacy_applicability_scope' => data_get($metadata, 'applicability_scope'),
                'legacy_division_name' => data_get($metadata, 'division_name'),
                'legacy_group_names' => data_get($metadata, 'applicable_group_names', []),
                'legacy_formula' => data_get($metadata, 'formula'),
                'legacy_overrides' => data_get($metadata, 'overrides', []),
                'application_types' => array_values(array_map(
                    fn (mixed $type): string => strtolower((string) $type),
                    $applicationTypes,
                )),
                'policy_boundaries' => data_get($metadata, 'blockers', []),
            ],
        ]);

        if ($calculationType === FeeRuleCalculationType::Range) {
            foreach (data_get($metadata, 'ranges', []) as $range) {
                if (! is_array($range) || data_get($range, 'amount_minor') === null || data_get($range, 'minimum_basis_minor') === null) {
                    continue;
                }
                FeeRuleRange::query()->create([
                    'fee_rule_id' => $feeRule->id,
                    'min_basis_cents' => (int) data_get($range, 'minimum_basis_minor'),
                    'max_basis_cents' => data_get($range, 'maximum_basis_minor'),
                    'amount_cents' => (int) data_get($range, 'amount_minor'),
                    'rate_basis_points' => null,
                ]);
            }
        }

        FeeRuleReconciliation::query()->create([
            'fee_rule_id' => $feeRule->id,
            'version' => 1,
            'legal_authority' => 'Legacy production configuration evidence only',
            'evidence_reference' => 'sha256:'.data_get($metadata, 'source_payload_sha256'),
            'original_text' => (string) data_get($metadata, 'name'),
            'normalized_interpretation' => null,
            'effective_from' => $effectiveFrom,
            'execution_status' => FeeRuleExecutionStatus::Blocked,
            'execution_reason' => implode('; ', data_get($metadata, 'blockers', ['municipal acceptance required'])),
        ]);
        FeeRuleAuditEvent::query()->create([
            'fee_rule_id' => $feeRule->id,
            'event' => 'legacy_candidate_created',
            'actor_id' => $actor->id,
            'occurred_at' => now(),
            'snapshot' => [
                'source_fee_id_sha256' => $sourceHash,
                'source_payload_sha256' => data_get($metadata, 'source_payload_sha256'),
                'active' => false,
                'historical_financial_records_changed' => false,
            ],
        ]);

        return $feeRule;
    }

    private function category(mixed $legacyCategory): FeeRuleCategory
    {
        $category = strtolower((string) $legacyCategory);

        return match (true) {
            str_contains($category, 'tax') => FeeRuleCategory::Tax,
            str_contains($category, 'clearance'), str_contains($category, 'certificate') => FeeRuleCategory::Clearance,
            str_contains($category, 'fee'), str_contains($category, 'regulatory') => FeeRuleCategory::Fee,
            default => FeeRuleCategory::Other,
        };
    }
}
