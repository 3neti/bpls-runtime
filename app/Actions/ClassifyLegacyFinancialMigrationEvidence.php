<?php

namespace App\Actions;

use App\Enums\LegacyFinancialMigrationDisposition;
use App\Enums\LegacyMappingPlanStatus;
use App\Enums\LegacyMappingProposalStatus;
use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyFinancialMappingProposal;
use RuntimeException;

class ClassifyLegacyFinancialMigrationEvidence
{
    public const ClassifierVersion = 'bpls.legacy-financial-migration-classification.v1';

    private const HistoricalSnapshotProvenanceReasons = [
        'accepted_fee_rule_reconciliation_missing',
        'aggregated_schedule_fee_identity_requires_reconciliation',
        'fee_rule_reconciliation_not_accepted',
        'legacy_fee_identity_missing',
        'reconciled_fee_rule_inactive',
        'schedule_fee_category_requires_reconciliation',
    ];

    private const QuarantineReasons = [
        'application_payment_mode_invalid',
        'application_total_fees_not_exact',
        'payment_amount_not_exact',
        'payment_schedule_application_mismatch',
        'payment_schedule_reference_unresolved',
        'payment_timestamp_invalid',
        'schedule_amount_not_exact',
        'schedule_application_reference_unresolved',
        'schedule_due_date_invalid',
        'schedule_fee_amount_not_exact',
        'schedule_fees_missing',
        'schedule_paid_amount_conflicts_with_completed_payments',
        'schedule_paid_amount_exceeds_total',
        'schedule_section_number_invalid',
        'schedule_status_conflicts_with_paid_amount',
        'schedule_status_requires_reconciliation',
        'schedule_total_conflicts_with_persisted_components',
    ];

    /** @return array<string, mixed> */
    public function handle(LegacyFinancialMappingPlan $plan): array
    {
        if (! in_array($plan->status, [LegacyMappingPlanStatus::Planned, LegacyMappingPlanStatus::PlannedWithExceptions], true)) {
            throw new RuntimeException("Financial mapping plan [{$plan->id}] must be complete before evidence classification.");
        }

        $counts = [];
        foreach (LegacyFinancialMigrationDisposition::cases() as $disposition) {
            $counts[$disposition->value] = 0;
        }
        $kindCounts = [];
        $reasonCounts = [];

        foreach ($plan->proposals()->select(['id', 'kind', 'status', 'reasons', 'metadata'])->orderBy('id')->cursor() as $proposal) {
            $disposition = $this->classify($proposal);
            $counts[$disposition->value]++;
            $kindCounts[$proposal->kind] ??= [];
            $kindCounts[$proposal->kind][$disposition->value] = ($kindCounts[$proposal->kind][$disposition->value] ?? 0) + 1;

            foreach ($proposal->reasons ?? [] as $reason) {
                $reasonCounts[$reason] = ($reasonCounts[$reason] ?? 0) + 1;
            }
        }

        ksort($kindCounts);
        arsort($reasonCounts);
        $classifiedCount = array_sum($counts);
        if ($classifiedCount !== $plan->proposal_count) {
            throw new RuntimeException("Financial mapping plan [{$plan->id}] declares {$plan->proposal_count} proposals but {$classifiedCount} were available for classification.");
        }

        return [
            'schema_version' => self::ClassifierVersion,
            'plan' => [
                'id' => $plan->id,
                'legacy_import_batch_id' => $plan->legacy_import_batch_id,
                'run_reference' => $plan->run_reference,
                'planner_version' => $plan->planner_version,
                'dependency_snapshot_hash' => $plan->dependency_snapshot_hash,
                'proposal_count' => $plan->proposal_count,
            ],
            'summary' => [
                'classified_count' => $classifiedCount,
                'disposition_counts' => $counts,
                'existing_rehearsal_eligible_count' => $counts[LegacyFinancialMigrationDisposition::DeterministicAndRehearsalEligible->value],
                'historical_snapshot_incomplete_provenance_count' => $counts[LegacyFinancialMigrationDisposition::DeterministicHistoricalSnapshotIncompleteProvenance->value],
                'migration_execution_authorized' => false,
                'cutover_authorized' => false,
            ],
            'by_kind' => $kindCounts,
            'reason_counts' => $reasonCounts,
            'semantics' => [
                LegacyFinancialMigrationDisposition::DeterministicAndRehearsalEligible->value => 'The existing immutable plan marks the proposal Ready. Existing executors must still verify complete-set and environment constraints; this classification grants no execution authority.',
                LegacyFinancialMigrationDisposition::DeterministicHistoricalSnapshotIncompleteProvenance->value => 'Persisted amounts are exact and structurally preservable, but exact fee-policy identity is absent or unaccepted. Preserve as historical evidence; do not calculate liability or infer identity by name.',
                LegacyFinancialMigrationDisposition::ReconciliationRequired->value => 'The source fact is structurally understandable, but deterministic mapping or operational meaning remains unresolved.',
                LegacyFinancialMigrationDisposition::QuarantinedHistoricalEvidence->value => 'A required relationship or structural/financial invariant is absent or contradictory. Preserve evidence outside ordinary operational execution.',
                LegacyFinancialMigrationDisposition::AuthorityBlocked->value => 'The source fact requires explicit municipal, fiscal, receipt, collection, or other authority before operational migration.',
            ],
            'safety' => [
                'classification_only' => true,
                'source_payloads_in_report' => false,
                'liability_calculations' => false,
                'formula_execution' => false,
                'identity_inference' => false,
                'financial_domain_writes' => false,
                'production_mutation' => false,
                'migration_executed' => false,
                'cutover_authorized' => false,
            ],
        ];
    }

    private function classify(LegacyFinancialMappingProposal $proposal): LegacyFinancialMigrationDisposition
    {
        if ($proposal->status === LegacyMappingProposalStatus::Ready) {
            return LegacyFinancialMigrationDisposition::DeterministicAndRehearsalEligible;
        }

        $reasons = $proposal->reasons ?? [];
        if (array_intersect($reasons, self::QuarantineReasons) !== []) {
            return LegacyFinancialMigrationDisposition::QuarantinedHistoricalEvidence;
        }

        if ($this->isExactHistoricalSnapshotWithIncompleteProvenance($proposal, $reasons)) {
            return LegacyFinancialMigrationDisposition::DeterministicHistoricalSnapshotIncompleteProvenance;
        }

        if ($this->requiresAuthority($proposal, $reasons)) {
            return LegacyFinancialMigrationDisposition::AuthorityBlocked;
        }

        return LegacyFinancialMigrationDisposition::ReconciliationRequired;
    }

    /** @param list<string> $reasons */
    private function isExactHistoricalSnapshotWithIncompleteProvenance(LegacyFinancialMappingProposal $proposal, array $reasons): bool
    {
        if ($proposal->kind !== 'payment_schedule_fee' || $reasons === []) {
            return false;
        }

        $metadata = $proposal->metadata ?? [];
        if (! is_int($metadata['original_amount_cents'] ?? null)
            || ! is_int($metadata['section_amount_cents'] ?? null)
            || ($metadata['was_edited'] ?? false) === true) {
            return false;
        }

        return array_diff($reasons, self::HistoricalSnapshotProvenanceReasons) === [];
    }

    /** @param list<string> $reasons */
    private function requiresAuthority(LegacyFinancialMappingProposal $proposal, array $reasons): bool
    {
        if (in_array($proposal->kind, ['application_fee_override', 'line_fee_override', 'line_fee_exclusion', 'payment', 'receipt_claim'], true)) {
            return true;
        }

        foreach ($reasons as $reason) {
            if (str_ends_with($reason, '_requires_acceptance')
                || str_contains($reason, 'authority_required')
                || str_contains($reason, 'policy_required')) {
                return true;
            }
        }

        return false;
    }
}
