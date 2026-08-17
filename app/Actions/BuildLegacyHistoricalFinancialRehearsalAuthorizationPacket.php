<?php

namespace App\Actions;

use App\Enums\LegacyMappingProposalStatus;
use App\Models\LegacyHistoricalFinancialMappingSet;
use App\Models\LegacyHistoricalFinancialPreservationPlan;
use App\Models\LegacyHistoricalFinancialPreservationProposal;
use RuntimeException;

class BuildLegacyHistoricalFinancialRehearsalAuthorizationPacket
{
    public const SchemaVersion = 'bpls.historical-financial-five-record-rehearsal-authorization.v1';

    public function __construct(
        private AcceptLegacyHistoricalFinancialCohortMappings $mappingAcceptance,
        private PlanLegacyHistoricalFinancialPreservation $planPreservation,
    ) {}

    /** @return array{plan: LegacyHistoricalFinancialPreservationPlan, report: array<string, mixed>} */
    public function handle(LegacyHistoricalFinancialMappingSet $mappingSet, string $runReference): array
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,99}$/', $runReference) !== 1) {
            throw new RuntimeException('Authorization planning run reference must be 3-100 safe characters.');
        }
        $this->mappingAcceptance->audit($mappingSet);
        $mappingSet->loadMissing('financialMappingPlan.importBatch.source');
        $applicationRecordIds = collect((array) data_get($mappingSet->manifest, 'application_mappings', []))
            ->pluck('source_record_id')->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();
        $plan = $this->planPreservation->handleSelection($mappingSet->financialMappingPlan, $runReference, array_values($applicationRecordIds));
        $proposals = $plan->proposals()->whereIn('legacy_record_id', $applicationRecordIds)->orderBy('legacy_record_id')->get();

        if (count($applicationRecordIds) !== 5 || $proposals->count() !== 5) {
            throw new RuntimeException('The preservation plan does not contain the exact frozen five-record cohort.');
        }
        if ($proposals->contains(fn (LegacyHistoricalFinancialPreservationProposal $proposal): bool => $proposal->status !== LegacyMappingProposalStatus::Ready)) {
            throw new RuntimeException('At least one frozen cohort preservation proposal is not ready.');
        }
        if ($plan->ready_count !== 5) {
            throw new RuntimeException('The preservation plan contains ready proposals outside the frozen five-record cohort.');
        }

        $totals = $this->emptyTotals();
        $redactedApplications = [];
        foreach ($proposals as $proposal) {
            $projection = data_get($proposal->metadata, 'projection');
            if (! is_array($projection)) {
                throw new RuntimeException("Preservation proposal [{$proposal->id}] has no frozen projection.");
            }
            $history = data_get($projection, 'financial_history');
            $sourceTotals = is_array($history) ? ($history['totals'] ?? null) : null;
            $schedules = is_array($history) ? ($history['schedules'] ?? null) : null;
            if (! is_array($sourceTotals) || ! is_array($schedules)) {
                throw new RuntimeException("Preservation proposal [{$proposal->id}] has incomplete financial history.");
            }
            $totals['historical_bundle_count']++;
            $totals['schedule_count'] += $this->integer($sourceTotals, 'schedule_count');
            $totals['fee_line_count'] += $this->integer($sourceTotals, 'fee_line_count');
            $totals['completed_payment_count'] += $this->integer($sourceTotals, 'payment_count');
            $totals['unpaid_schedule_count'] += collect($schedules)->where('status', 'pending')->count();
            foreach (['scheduled_amount_cents', 'fee_amount_cents', 'paid_amount_cents', 'payment_amount_cents'] as $key) {
                $totals[$key] += $this->integer($sourceTotals, $key);
            }
            $redactedApplications[] = [
                'application_reference' => 'sha256:'.substr(hash('sha256', $proposal->legacyRecord->legacy_id), 0, 16),
                'source_record_id' => $proposal->legacy_record_id,
                'source_payload_hash' => $proposal->legacyRecord->payload_hash,
                'proposal_id' => $proposal->id,
                'projection_hash' => $proposal->projection_hash,
                'target_application_id' => $proposal->applicationMapping->permit_application_id,
            ];
        }

        $proposalIds = $proposals->pluck('id')->sort()->values()->all();
        $executionRun = $runReference.'-execute';
        $proposalOptions = collect($proposalIds)->map(fn (int $id): string => "--proposal={$id}")->implode(' ');
        $commands = [
            'execute' => "php artisan legacy:execute-historical-financial-preservation {$plan->id} {$proposalOptions} --run-id={$executionRun} --execute --confirm-execute --json",
            'audit' => 'php artisan legacy:audit-historical-financial-preservation {execution-id-from-execute} --json',
            'rollback' => 'php artisan legacy:rollback-historical-financial-preservation {execution-id-from-execute} --rollback --confirm-rollback --json',
            'restoration_audit' => "php artisan legacy:audit-historical-financial-preservation-restoration {execution-id-from-execute} --mapping-set={$mappingSet->id} --json",
        ];

        return [
            'plan' => $plan,
            'report' => [
                'schema_version' => self::SchemaVersion,
                'recommendation' => 'READY FOR FIVE-RECORD REHEARSAL AUTHORIZATION',
                'mapping_set_id' => $mappingSet->id,
                'frozen_cohort_sha256' => $mappingSet->cohort_sha256,
                'frozen_accepted_mapping_set_sha256' => $mappingSet->accepted_mapping_set_sha256,
                'proposal_package_sha256' => $mappingSet->proposal_package_sha256,
                'preservation_plan_id' => $plan->id,
                'preservation_dependency_snapshot_sha256' => $plan->dependency_snapshot_hash,
                'applications' => $redactedApplications,
                'expected_totals' => $totals,
                'writes' => [
                    'legacy_historical_financial_preservation_executions',
                    'legacy_historical_financial_preserved_bundles',
                ],
                'operational_tables_required_unchanged' => [
                    'assessments',
                    'assessment_lines',
                    'payment_schedules',
                    'payment_schedule_lines',
                    'treasury_collections',
                    'receipts',
                ],
                'pre_execution_assertions' => [
                    'The immutable source archive, financial plan, cohort, proposal package, and accepted mapping-set fingerprints still match.',
                    'Exactly five accepted application mappings and five ready preservation proposals remain bound to the cohort.',
                    'No cohort application already has a preserved historical bundle.',
                    'The selected proposal IDs belong to the exact preservation plan and no ready proposal exists outside the cohort.',
                    'Production execution remains separately unauthorized until the Board approves this packet.',
                ],
                'post_execution_audit_assertions' => [
                    'Exactly five immutable historical bundles exist.',
                    'Source and target schedule, fee-line, payment, and centavo totals agree exactly.',
                    'Every bundle snapshot and source projection hash agrees.',
                    'Operational financial table counts are unchanged.',
                    'No fee identity, formula, liability, collection, receipt, notification, or external call was created.',
                ],
                'rollback_assertions' => [
                    'Rollback is limited to unchanged bundles with no reviewer disposition or downstream references.',
                    'Exactly five created bundles are removed.',
                    'Source records, accepted mappings, target registry records, and applications remain unchanged.',
                    'The execution becomes rolled_back and cannot be reused.',
                ],
                'restoration_audit_assertions' => [
                    'No preserved bundle remains for the execution.',
                    'Operational financial counts exactly equal the pre-execution counts.',
                    'The accepted mapping-set fingerprint and all target dependencies still pass audit.',
                    'No source record or accepted mapping was deleted.',
                ],
                'fail_closed_conditions' => [
                    'Any immutable source, plan, cohort, proposal-package, mapping-set, target, or projection fingerprint changes.',
                    'Any accepted owner, business, application, location-provenance, or line-of-business dependency changes.',
                    'The ready selection is not exactly five or expands beyond the frozen cohort.',
                    'Any source financial history has unresolved V1 eligibility reasons.',
                    'Any cohort application is already preserved.',
                    'Any operational financial count changes during execute, audit, rollback, or restoration audit.',
                    'Any bundle gains reviewer disposition or downstream references before rollback.',
                    'Either explicit execution or rollback confirmation flag is absent.',
                ],
                'proposed_commands_not_executed' => $commands,
                'safety' => [
                    'production_rehearsal_authorized' => false,
                    'production_execution_performed' => false,
                    'historical_recalculation' => false,
                    'fee_identity_inference' => false,
                    'operational_financial_writes' => false,
                ],
            ],
        ];
    }

    /** @return array<string, int> */
    private function emptyTotals(): array
    {
        return [
            'historical_bundle_count' => 0,
            'schedule_count' => 0,
            'fee_line_count' => 0,
            'completed_payment_count' => 0,
            'unpaid_schedule_count' => 0,
            'scheduled_amount_cents' => 0,
            'fee_amount_cents' => 0,
            'paid_amount_cents' => 0,
            'payment_amount_cents' => 0,
        ];
    }

    /** @param array<string, mixed> $values */
    private function integer(array $values, string $key): int
    {
        $value = $values[$key] ?? null;
        if (! is_int($value)) {
            throw new RuntimeException("Historical total [{$key}] is not an exact integer.");
        }

        return $value;
    }
}
