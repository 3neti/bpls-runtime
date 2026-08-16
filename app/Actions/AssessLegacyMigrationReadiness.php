<?php

namespace App\Actions;

use App\Enums\LegacyImportBatchStatus;
use App\Enums\LegacyMappingExecutionStatus;
use App\Enums\LegacyMappingPlanStatus;
use App\Enums\LegacyMigrationReadinessStatus;
use App\Enums\MigrationExceptionSeverity;
use App\Enums\MigrationExceptionStatus;
use App\Enums\MigrationValidationStatus;
use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyApplicationMappingExecution;
use App\Models\LegacyDeclarationLineMapping;
use App\Models\LegacyDeclarationMappingExecution;
use App\Models\LegacyImportBatch;
use App\Models\LegacyMappingExecution;
use App\Models\LegacyMappingPlan;
use App\Models\LegacyMigrationReadinessAssessment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AssessLegacyMigrationReadiness
{
    public const AssessorVersion = 'bpls.legacy-migration-readiness.v2';

    public function handle(LegacyImportBatch $batch, string $runReference): LegacyMigrationReadinessAssessment
    {
        $this->assertAllowed($batch, $runReference);
        $snapshot = $this->snapshotHash($batch);
        $assessment = $this->resolveAssessment($batch, $runReference, $snapshot);

        if ($assessment->completed_at !== null) {
            return $assessment->refresh();
        }

        $checks = $this->checks($batch);
        $rehearsalReady = collect($checks)->where('scope', 'rehearsal')->every(fn (array $check): bool => $check['passed']);
        $cutoverReady = $rehearsalReady && collect($checks)->where('scope', 'cutover')->every(fn (array $check): bool => $check['passed']);
        $passed = collect($checks)->where('passed', true)->count();

        $assessment->update([
            'status' => $rehearsalReady ? LegacyMigrationReadinessStatus::RehearsalReady : LegacyMigrationReadinessStatus::Blocked,
            'rehearsal_ready' => $rehearsalReady,
            'cutover_ready' => $cutoverReady,
            'check_count' => count($checks),
            'passed_count' => $passed,
            'blocked_count' => count($checks) - $passed,
            'checks' => $checks,
            'completed_at' => now(),
        ]);

        return $assessment->refresh();
    }

    /** @return list<array{key: string, scope: string, passed: bool, actual: array<string, mixed>, reason: string}> */
    private function checks(LegacyImportBatch $batch): array
    {
        $datasetCounts = $batch->records()->selectRaw('dataset_key, count(*) as aggregate')->groupBy('dataset_key')->pluck('aggregate', 'dataset_key');
        $owners = (int) ($datasetCounts['business_owners'] ?? 0);
        $businesses = (int) ($datasetCounts['businesses'] ?? 0);
        $applications = (int) (($datasetCounts['business_permit_applications'] ?? 0) + ($datasetCounts['applications'] ?? 0));
        $missingCore = collect(['business_owners', 'businesses'])->filter(fn (string $key): bool => ! $datasetCounts->has($key))->values()->all();
        if (! $datasetCounts->has('business_permit_applications') && ! $datasetCounts->has('applications')) {
            $missingCore[] = 'business_permit_applications';
        }

        $openErrors = $batch->exceptions()
            ->where('status', MigrationExceptionStatus::Open)
            ->where('severity', MigrationExceptionSeverity::Error)
            ->count();
        $failedValidations = $batch->validationResults()->where('status', MigrationValidationStatus::Failed)->count();
        $lineCount = $this->applicationLineCount($batch);
        $documentCount = $this->businessDocumentCount($batch);

        $checks = [
            $this->check('staging_finished', 'rehearsal', in_array($batch->status, [LegacyImportBatchStatus::Staged, LegacyImportBatchStatus::StagedWithExceptions], true), [
                'status' => $batch->status->value,
            ], 'The checksum-verified staging process must finish before rehearsal.'),
            $this->check('record_inventory_complete', 'rehearsal', $batch->source_record_count === $batch->staged_record_count, [
                'source_records' => $batch->source_record_count,
                'staged_records' => $batch->staged_record_count,
            ], 'Every source row must be staged or consciously reconciled before rehearsal.'),
            $this->check('core_datasets_present', 'rehearsal', $missingCore === [], [
                'missing' => $missingCore,
                'dataset_count' => $datasetCounts->count(),
            ], 'Business owners, businesses, and permit applications are required core rescue datasets.'),
            $this->check('staging_validations_pass', 'rehearsal', $failedValidations === 0, [
                'failed_validations' => $failedValidations,
            ], 'Failed staging validations must be resolved before rehearsal.'),
            $this->check('migration_exceptions_resolved', 'rehearsal', $openErrors === 0, [
                'open_errors' => $openErrors,
            ], 'Open migration errors must be resolved rather than ignored.'),
            $this->planCheck('registry_plan_ready', $batch->mappingPlans()->latest('id')->first(), $owners + $businesses, 'rehearsal'),
            $this->planCheck('application_plan_ready', $batch->applicationMappingPlans()->latest('id')->first(), $applications, 'rehearsal'),
            $lineCount === 0
                ? $this->check('declaration_plan_ready', 'rehearsal', true, ['applicable' => false, 'source_lines' => 0], 'No staged application declarations require planning.')
                : $this->planCheck('declaration_plan_ready', $batch->declarationMappingPlans()->latest('id')->first(), $lineCount, 'rehearsal'),
            $this->planCheck('financial_plan_ready', $batch->financialMappingPlans()->latest('id')->first(), null, 'rehearsal'),
            $this->planCheck('permit_evidence_plan_ready', $batch->permitEvidencePlans()->latest('id')->first(), null, 'rehearsal'),
        ];

        $provenance = $batch->source->provenance;
        $productionEvidence = ($provenance['environment'] ?? null) === 'production'
            && is_string($provenance['captured_at'] ?? null)
            && $batch->source->source_type === 'convex_export'
            && $batch->source->archive_checksum !== null;
        $registryMappings = $batch->idMappings()->where('status', 'mapped')->count();
        $completedRegistryExecution = $batch->mappingPlans()
            ->whereHas('executions', fn ($query) => $query->where('status', LegacyMappingExecutionStatus::Completed))
            ->exists();
        $applicationMappings = LegacyApplicationIdMapping::query()
            ->whereBelongsTo($batch, 'importBatch')
            ->where('status', 'mapped')
            ->count();
        $completedApplicationExecution = $batch->applicationMappingPlans()
            ->whereHas('executions', fn ($query) => $query->where('status', LegacyMappingExecutionStatus::Completed))
            ->exists();
        $applicationExecutionComplete = $completedApplicationExecution && $applicationMappings >= $applications;
        $declarationMappings = LegacyDeclarationLineMapping::query()
            ->whereBelongsTo($batch, 'importBatch')
            ->where('status', 'mapped')
            ->count();
        $completedDeclarationExecution = $batch->declarationMappingPlans()
            ->whereHas('executions', fn ($query) => $query->where('status', LegacyMappingExecutionStatus::Completed))
            ->exists();
        $declarationExecutionComplete = $lineCount === 0 || ($completedDeclarationExecution && $declarationMappings >= $lineCount);

        array_push(
            $checks,
            $this->check('production_export_provenance', 'cutover', $productionEvidence, [
                'source_type' => $batch->source->source_type,
                'production_environment_asserted' => ($provenance['environment'] ?? null) === 'production',
                'capture_timestamp_present' => is_string($provenance['captured_at'] ?? null),
                'archive_checksum_present' => $batch->source->archive_checksum !== null,
            ], 'Cutover requires a checksum-bound production export with capture provenance.'),
            $this->check('registry_execution_complete', 'cutover', $completedRegistryExecution && $registryMappings >= $owners + $businesses, [
                'completed_execution' => $completedRegistryExecution,
                'mapped_records' => $registryMappings,
                'required_records' => $owners + $businesses,
            ], 'Registry proposals must be executed and mapped before cutover.'),
            $this->check('remaining_domain_execution_paths', 'cutover', false, [
                'application_execution' => $applicationExecutionComplete,
                'application_mapped_records' => $applicationMappings,
                'application_required_records' => $applications,
                'declaration_execution' => $declarationExecutionComplete,
                'declaration_mapped_records' => $declarationMappings,
                'declaration_required_records' => $lineCount,
                'financial_execution' => false,
                'permit_evidence_execution' => false,
            ], 'Application and declaration execution are bounded and reversible; financial and permit-evidence migration paths remain required.'),
            $this->check('document_object_transfer_verified', 'cutover', $documentCount === 0, [
                'staged_document_metadata_records' => $documentCount,
                'object_transfer_verified' => false,
            ], $documentCount === 0 ? 'No staged document objects require transfer in this batch.' : 'Every referenced object requires checksum-verified transfer and scope reconciliation.'),
            $this->check('municipal_cutover_authorization', 'cutover', false, [
                'authorization_recorded' => false,
            ], 'Municipal cutover authority and evidence are not yet represented or accepted.'),
        );

        return $checks;
    }

    /** @return array{key: string, scope: string, passed: bool, actual: array<string, mixed>, reason: string} */
    private function planCheck(string $key, ?Model $plan, ?int $expectedProposals, string $scope): array
    {
        $completed = $plan !== null
            && in_array($plan->getAttribute('status'), [LegacyMappingPlanStatus::Planned, LegacyMappingPlanStatus::PlannedWithExceptions], true)
            && $plan->getAttribute('completed_at') !== null;
        $review = (int) ($plan?->getAttribute('review_count') ?? 0);
        $blocked = (int) ($plan?->getAttribute('blocked_count') ?? 0);
        $proposalCount = $plan instanceof LegacyMappingPlan
            ? $plan->owner_proposal_count + $plan->business_proposal_count
            : (int) ($plan?->getAttribute('proposal_count') ?? 0);
        $coverage = $expectedProposals === null || $proposalCount === $expectedProposals;

        return $this->check($key, $scope, $completed && $review === 0 && $blocked === 0 && $coverage, [
            'plan_id' => $plan?->getKey(),
            'completed' => $completed,
            'proposal_count' => $proposalCount,
            'expected_proposals' => $expectedProposals,
            'review_required' => $review,
            'blocked' => $blocked,
        ], 'The latest completed plan must cover its source records with no review or blocked proposals.');
    }

    /** @param array<string, mixed> $actual
     * @return array{key: string, scope: string, passed: bool, actual: array<string, mixed>, reason: string}
     */
    private function check(string $key, string $scope, bool $passed, array $actual, string $reason): array
    {
        return compact('key', 'scope', 'passed', 'actual', 'reason');
    }

    private function applicationLineCount(LegacyImportBatch $batch): int
    {
        $count = 0;
        foreach ($batch->records()->whereIn('dataset_key', ['applications', 'business_permit_applications'])->select('payload')->cursor() as $record) {
            $lines = $record->payload['linesOfBusiness'] ?? [];
            $count += is_array($lines) ? count($lines) : 0;
        }

        return $count;
    }

    private function businessDocumentCount(LegacyImportBatch $batch): int
    {
        $count = 0;
        foreach ($batch->records()->where('dataset_key', 'businesses')->select('payload')->cursor() as $record) {
            $documents = $record->payload['documents'] ?? [];
            $count += is_array($documents) ? count($documents) : 0;
        }

        return $count;
    }

    private function resolveAssessment(LegacyImportBatch $batch, string $runReference, string $snapshot): LegacyMigrationReadinessAssessment
    {
        return DB::transaction(function () use ($batch, $runReference, $snapshot): LegacyMigrationReadinessAssessment {
            $assessment = $batch->readinessAssessments()->where('run_reference', $runReference)->lockForUpdate()->first();
            if ($assessment instanceof LegacyMigrationReadinessAssessment) {
                if (! hash_equals($assessment->dependency_snapshot_hash, $snapshot) || $assessment->assessor_version !== self::AssessorVersion) {
                    throw new RuntimeException("Migration readiness run reference [{$runReference}] is bound to different evidence.");
                }

                return $assessment;
            }

            return $batch->readinessAssessments()->create([
                'run_reference' => $runReference,
                'assessor_version' => self::AssessorVersion,
                'dependency_snapshot_hash' => $snapshot,
                'status' => LegacyMigrationReadinessStatus::Assessing,
                'started_at' => now(),
                'metadata' => [
                    'assessment_only' => true,
                    'migration_execution' => false,
                    'cutover_authorized' => false,
                    'domain_writes' => false,
                ],
            ]);
        });
    }

    private function snapshotHash(LegacyImportBatch $batch): string
    {
        $parts = [[
            'batch', $batch->id, $batch->status->value, $batch->manifest_checksum,
            $batch->source_record_count, $batch->staged_record_count, $batch->exception_count,
            $batch->source->source_type, $batch->source->archive_checksum,
            hash('sha256', json_encode($batch->source->provenance, JSON_THROW_ON_ERROR)),
        ]];
        foreach ($batch->records()->select(['id', 'dataset_key', 'payload_hash'])->orderBy('id')->cursor() as $record) {
            $parts[] = ['record', $record->id, $record->dataset_key, $record->payload_hash];
        }
        foreach ($batch->validationResults()->select(['id', 'check_key', 'status', 'updated_at'])->orderBy('id')->cursor() as $result) {
            $parts[] = ['validation', $result->id, $result->check_key, $result->status->value, $result->updated_at?->toIso8601String()];
        }
        foreach ($batch->exceptions()->select(['id', 'code', 'severity', 'status', 'updated_at'])->orderBy('id')->cursor() as $exception) {
            $parts[] = ['exception', $exception->id, $exception->code, $exception->severity->value, $exception->status->value, $exception->updated_at?->toIso8601String()];
        }
        foreach ([$batch->mappingPlans(), $batch->applicationMappingPlans(), $batch->declarationMappingPlans(), $batch->financialMappingPlans(), $batch->permitEvidencePlans()] as $relation) {
            foreach ($relation->orderBy('id')->get() as $plan) {
                $parts[] = ['plan', $plan->getTable(), $plan->getKey(), $plan->getAttribute('status')->value, $plan->getAttribute('updated_at')?->toIso8601String()];
            }
        }
        foreach ($batch->idMappings()->select(['id', 'status', 'updated_at'])->orderBy('id')->cursor() as $mapping) {
            $parts[] = ['mapping', $mapping->id, $mapping->status, $mapping->updated_at?->toIso8601String()];
        }
        foreach (LegacyMappingExecution::query()->whereHas('mappingPlan', fn ($query) => $query->whereBelongsTo($batch, 'importBatch'))->orderBy('id')->get() as $execution) {
            $parts[] = ['execution', $execution->id, $execution->status->value, $execution->selection_hash, $execution->updated_at?->toIso8601String()];
        }
        foreach (LegacyApplicationMappingExecution::query()->whereHas('mappingPlan', fn ($query) => $query->whereBelongsTo($batch, 'importBatch'))->orderBy('id')->get() as $execution) {
            $parts[] = ['application_execution', $execution->id, $execution->status->value, $execution->selection_hash, $execution->updated_at?->toIso8601String()];
        }
        foreach (LegacyApplicationIdMapping::query()->whereBelongsTo($batch, 'importBatch')->select(['id', 'status', 'updated_at'])->orderBy('id')->cursor() as $mapping) {
            $parts[] = ['application_mapping', $mapping->id, $mapping->status, $mapping->updated_at?->toIso8601String()];
        }
        foreach (LegacyDeclarationMappingExecution::query()->whereHas('mappingPlan', fn ($query) => $query->whereBelongsTo($batch, 'importBatch'))->orderBy('id')->get() as $execution) {
            $parts[] = ['declaration_execution', $execution->id, $execution->status->value, $execution->selection_hash, $execution->updated_at?->toIso8601String()];
        }
        foreach (LegacyDeclarationLineMapping::query()->whereBelongsTo($batch, 'importBatch')->select(['id', 'status', 'updated_at'])->orderBy('id')->cursor() as $mapping) {
            $parts[] = ['declaration_mapping', $mapping->id, $mapping->status, $mapping->updated_at?->toIso8601String()];
        }

        return hash('sha256', json_encode($parts, JSON_THROW_ON_ERROR));
    }

    private function assertAllowed(LegacyImportBatch $batch, string $runReference): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Legacy migration readiness assessment is restricted to local and testing environments.');
        }
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{2,99}\z/', $runReference) !== 1) {
            throw new RuntimeException('Readiness run reference must contain 3-100 safe characters.');
        }
    }
}
