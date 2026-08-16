<?php

namespace App\Actions;

use App\Enums\LegacyMappingExecutionStatus;
use App\Enums\LegacyMappingProposalStatus;
use App\Enums\LegacyMigrationRehearsalStatus;
use App\Models\LegacyApplicationMappingExecution;
use App\Models\LegacyDeclarationMappingExecution;
use App\Models\LegacyFinancialMappingExecution;
use App\Models\LegacyImportBatch;
use App\Models\LegacyMappingExecution;
use App\Models\LegacyMigrationRehearsal;
use App\Models\LegacyPermitEvidenceExecution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VerifyLegacyMigrationRehearsal
{
    public const VerifierVersion = 'bpls.legacy-migration-rehearsal.v1';

    public function __construct(private AssessLegacyMigrationReadiness $assessReadiness) {}

    public function handle(
        LegacyImportBatch $batch,
        LegacyMappingExecution $registryExecution,
        LegacyApplicationMappingExecution $applicationExecution,
        ?LegacyDeclarationMappingExecution $declarationExecution,
        ?LegacyFinancialMappingExecution $financialExecution,
        ?LegacyPermitEvidenceExecution $permitEvidenceExecution,
        string $runReference,
    ): LegacyMigrationRehearsal {
        $this->assertEnvironment();
        $this->assertRunReference($runReference);
        $selection = [
            'registry' => $registryExecution->id,
            'applications' => $applicationExecution->id,
            'declarations' => $declarationExecution?->id,
            'financial' => $financialExecution?->id,
            'permit_evidence' => $permitEvidenceExecution?->id,
        ];
        $plans = [
            'registry' => $batch->mappingPlans()->latest('id')->first(),
            'applications' => $batch->applicationMappingPlans()->latest('id')->first(),
            'declarations' => $batch->declarationMappingPlans()->latest('id')->first(),
            'financial' => $batch->financialMappingPlans()->latest('id')->first(),
            'permit_evidence' => $batch->permitEvidencePlans()->latest('id')->first(),
        ];
        $selectionHash = $this->hash($selection);
        $readiness = $this->assessReadiness->handle($batch, $runReference.'-readiness');
        $dependencyHash = $this->hash([
            'batch' => [$batch->id, $batch->manifest_checksum, $batch->updated_at?->toIso8601String()],
            'selection' => $selection,
            'plans' => array_map(fn (?Model $plan): ?array => $this->planSnapshot($plan), $plans),
            'executions' => array_map(
                fn (?Model $execution): ?array => $execution === null ? null : [
                    $execution->getKey(),
                    $execution->getAttribute('status')->value,
                    $execution->getAttribute('selection_hash'),
                    $execution->getAttribute('updated_at')?->toIso8601String(),
                ],
                [$registryExecution, $applicationExecution, $declarationExecution, $financialExecution, $permitEvidenceExecution],
            ),
            'readiness' => [$readiness->id, $readiness->dependency_snapshot_hash, $readiness->updated_at?->toIso8601String()],
        ]);
        $rehearsal = $this->resolveRehearsal(
            $batch,
            $registryExecution,
            $applicationExecution,
            $declarationExecution,
            $financialExecution,
            $permitEvidenceExecution,
            $readiness->id,
            $runReference,
            $selectionHash,
            $dependencyHash,
        );

        if (in_array($rehearsal->status, [LegacyMigrationRehearsalStatus::Verified, LegacyMigrationRehearsalStatus::VerificationFailed, LegacyMigrationRehearsalStatus::RolledBack], true)) {
            return $rehearsal->load($this->relations());
        }

        $checks = [
            $this->executionCheck('registry', $batch, $registryExecution, $plans['registry']),
            $this->executionCheck('applications', $batch, $applicationExecution, $plans['applications']),
            $this->optionalExecutionCheck('declarations', $batch, $declarationExecution, $plans['declarations']),
            $this->optionalExecutionCheck('financial', $batch, $financialExecution, $plans['financial']),
            $this->optionalExecutionCheck('permit_evidence', $batch, $permitEvidenceExecution, $plans['permit_evidence']),
            $this->check('planning_rehearsal_ready', $readiness->rehearsal_ready, [
                'assessment_id' => $readiness->id,
                'status' => $readiness->status->value,
                'passed_checks' => $readiness->passed_count,
                'blocked_checks' => $readiness->blocked_count,
            ], 'The staged batch must pass the immutable planning-readiness gate.'),
            $this->executionResultsCheck($readiness->checks ?? []),
            $this->safetyCheck([$registryExecution, $applicationExecution, $declarationExecution, $financialExecution, $permitEvidenceExecution]),
            $this->cutoverBoundaryCheck($readiness->checks ?? [], $readiness->cutover_ready),
        ];
        $passed = collect($checks)->where('passed', true)->count();
        $blocked = count($checks) - $passed;
        $rehearsal->update([
            'status' => $blocked === 0 ? LegacyMigrationRehearsalStatus::Verified : LegacyMigrationRehearsalStatus::VerificationFailed,
            'check_count' => count($checks),
            'passed_count' => $passed,
            'blocked_count' => $blocked,
            'checks' => $checks,
            'completed_at' => now(),
            'metadata' => [
                'execution_only' => false,
                'verification_only' => true,
                'domain_logic_duplicated' => false,
                'cutover_authorized' => $readiness->cutover_ready,
                'external_integrations' => false,
                'notifications' => false,
                'irreversible_actions' => false,
                'raw_legacy_ids_recorded' => false,
                'personal_data_recorded' => false,
            ],
        ]);

        return $rehearsal->refresh()->load($this->relations());
    }

    /** @return array{key: string, passed: bool, actual: array<string, mixed>, reason: string} */
    private function executionCheck(string $key, LegacyImportBatch $batch, Model $execution, ?Model $latestPlan): array
    {
        return $this->domainExecutionCheck($key, $batch, $execution, $latestPlan, true);
    }

    /** @return array{key: string, passed: bool, actual: array<string, mixed>, reason: string} */
    private function optionalExecutionCheck(string $key, LegacyImportBatch $batch, ?Model $execution, ?Model $latestPlan): array
    {
        $readyIds = $this->readyProposalIds($latestPlan);
        if ($execution === null) {
            return $this->check($key.'_execution', $readyIds === [], [
                'applicable' => $readyIds !== [],
                'latest_plan_id' => $latestPlan?->getKey(),
                'ready_proposals' => count($readyIds),
                'execution_id' => null,
            ], 'An execution is required whenever the latest plan contains ready proposals.');
        }

        return $this->domainExecutionCheck($key, $batch, $execution, $latestPlan, $readyIds !== []);
    }

    /** @return array{key: string, passed: bool, actual: array<string, mixed>, reason: string} */
    private function domainExecutionCheck(string $key, LegacyImportBatch $batch, Model $execution, ?Model $latestPlan, bool $applicable): array
    {
        $executionPlan = $execution->getRelationValue('mappingPlan');
        $readyIds = $this->readyProposalIds($latestPlan);
        $selectedIds = $execution->getAttribute('metadata')['proposal_ids'] ?? [];
        $selectedIds = is_array($selectedIds) ? array_values(array_map('intval', $selectedIds)) : [];
        sort($selectedIds);
        $sameBatch = $executionPlan instanceof Model
            && $executionPlan->getAttribute('legacy_import_batch_id') === $batch->id;
        $latest = $executionPlan instanceof Model
            && $latestPlan instanceof Model
            && $executionPlan->getKey() === $latestPlan->getKey();
        $selectionHash = $this->hash($selectedIds);
        $created = (int) $execution->getAttribute('created_count');
        $linked = (int) $execution->getAttribute('linked_count');
        $reused = (int) $execution->getAttribute('reused_count');
        $selectedCount = (int) $execution->getAttribute('selected_count');
        $mappingCount = (int) $execution->getAttribute('mapping_count');
        $resultCountsConsistent = $selectedCount === $created + $linked + $reused
            && $mappingCount === $created + $linked;
        $passed = $applicable
            && $sameBatch
            && $latest
            && $execution->getAttribute('status') === LegacyMappingExecutionStatus::Completed
            && $selectedIds === $readyIds
            && $execution->getAttribute('selected_count') === count($readyIds)
            && $resultCountsConsistent
            && hash_equals((string) $execution->getAttribute('selection_hash'), $selectionHash);

        return $this->check($key.'_execution', $passed, [
            'applicable' => $applicable,
            'execution_id' => $execution->getKey(),
            'execution_status' => $execution->getAttribute('status')->value,
            'same_batch' => $sameBatch,
            'latest_plan' => $latest,
            'ready_proposals' => count($readyIds),
            'selected_proposals' => count($selectedIds),
            'exact_selection' => $selectedIds === $readyIds,
            'selection_hash_verified' => hash_equals((string) $execution->getAttribute('selection_hash'), $selectionHash),
            'result_counts_consistent' => $resultCountsConsistent,
        ], 'The exact latest-plan ready proposal set must have one completed execution for this batch.');
    }

    /** @return list<int> */
    private function readyProposalIds(?Model $plan): array
    {
        if (! $plan instanceof Model) {
            return [];
        }

        $ids = $plan->getRelationValue('proposals')
            ->where('status', LegacyMappingProposalStatus::Ready)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
        sort($ids);

        return $ids;
    }

    /** @return array<string, mixed>|null */
    private function planSnapshot(?Model $plan): ?array
    {
        if (! $plan instanceof Model) {
            return null;
        }

        return [
            'id' => $plan->getKey(),
            'status' => $plan->getAttribute('status')->value,
            'dependency_snapshot_hash' => $plan->getAttribute('dependency_snapshot_hash'),
            'registry_snapshot_hash' => $plan->getAttribute('registry_snapshot_hash'),
            'proposals' => $plan->getRelationValue('proposals')
                ->sortBy('id')
                ->map(fn (Model $proposal): array => [
                    $proposal->getKey(),
                    $proposal->getAttribute('status')->value,
                    $proposal->getAttribute('projection_hash'),
                    $proposal->getAttribute('updated_at')?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  list<Model|null>  $executions
     * @return array{key: string, passed: bool, actual: array<string, mixed>, reason: string}
     */
    private function safetyCheck(array $executions): array
    {
        $unsafe = collect($executions)->filter()->filter(function (Model $execution): bool {
            $metadata = $execution->getAttribute('metadata') ?? [];

            return ! is_array($metadata)
                || ! array_key_exists('external_integrations', $metadata)
                || ! array_key_exists('notifications', $metadata)
                || ! array_key_exists('irreversible_actions', $metadata)
                || $metadata['external_integrations'] !== false
                || $metadata['notifications'] !== false
                || $metadata['irreversible_actions'] !== false;
        })->count();

        return $this->check('safety_boundaries', $unsafe === 0, [
            'executions_checked' => collect($executions)->filter()->count(),
            'unsafe_executions' => $unsafe,
            'external_integrations' => false,
            'notifications' => false,
            'irreversible_actions' => false,
        ], 'A local rehearsal must not invoke integrations, notifications, or irreversible actions.');
    }

    /**
     * @param  list<array<string, mixed>>  $readinessChecks
     * @return array{key: string, passed: bool, actual: array<string, mixed>, reason: string}
     */
    private function executionResultsCheck(array $readinessChecks): array
    {
        $checks = collect($readinessChecks)->keyBy('key');
        $registry = $checks->get('registry_execution_complete');
        $domains = $checks->get('remaining_domain_execution_paths');
        $documents = $checks->get('document_object_transfer_verified');
        $domainActual = is_array($domains) && is_array($domains['actual'] ?? null) ? $domains['actual'] : [];
        $actual = [
            'registry' => is_array($registry) && ($registry['passed'] ?? false) === true,
            'applications' => ($domainActual['application_execution'] ?? false) === true,
            'declarations' => ($domainActual['declaration_execution'] ?? false) === true,
            'financial' => ($domainActual['financial_execution'] ?? false) === true,
            'permit_evidence' => ($domainActual['permit_evidence_execution'] ?? false) === true,
            'document_objects' => is_array($documents) && ($documents['passed'] ?? false) === true,
        ];

        return $this->check('authoritative_execution_results', ! in_array(false, $actual, true), $actual,
            'Completed execution records must agree with authoritative mapping and checksum-verified object coverage.');
    }

    /**
     * @param  list<array<string, mixed>>  $readinessChecks
     * @return array{key: string, passed: bool, actual: array<string, mixed>, reason: string}
     */
    private function cutoverBoundaryCheck(array $readinessChecks, bool $cutoverReady): array
    {
        $blockedKeys = collect($readinessChecks)
            ->where('scope', 'cutover')
            ->where('passed', false)
            ->pluck('key')
            ->values()
            ->all();

        return $this->check('cutover_boundary_recorded', true, [
            'cutover_ready' => $cutoverReady,
            'blocked_cutover_checks' => $blockedKeys,
            'cutover_authority_inferred' => false,
        ], 'Cutover readiness and unresolved authority remain visible without blocking a bounded local rehearsal.');
    }

    /** @param array<string, mixed> $actual
     * @return array{key: string, passed: bool, actual: array<string, mixed>, reason: string}
     */
    private function check(string $key, bool $passed, array $actual, string $reason): array
    {
        return compact('key', 'passed', 'actual', 'reason');
    }

    private function resolveRehearsal(
        LegacyImportBatch $batch,
        LegacyMappingExecution $registryExecution,
        LegacyApplicationMappingExecution $applicationExecution,
        ?LegacyDeclarationMappingExecution $declarationExecution,
        ?LegacyFinancialMappingExecution $financialExecution,
        ?LegacyPermitEvidenceExecution $permitEvidenceExecution,
        int $readinessAssessmentId,
        string $runReference,
        string $selectionHash,
        string $dependencyHash,
    ): LegacyMigrationRehearsal {
        return DB::transaction(function () use ($batch, $registryExecution, $applicationExecution, $declarationExecution, $financialExecution, $permitEvidenceExecution, $readinessAssessmentId, $runReference, $selectionHash, $dependencyHash): LegacyMigrationRehearsal {
            $existing = $batch->migrationRehearsals()->where('run_reference', $runReference)->lockForUpdate()->first();
            if ($existing instanceof LegacyMigrationRehearsal) {
                if (! hash_equals($existing->selection_hash, $selectionHash)
                    || ! hash_equals($existing->dependency_snapshot_hash, $dependencyHash)
                    || $existing->verifier_version !== self::VerifierVersion) {
                    throw new RuntimeException("Migration rehearsal [{$runReference}] is bound to different execution or dependency evidence.");
                }

                return $existing;
            }

            return $batch->migrationRehearsals()->create([
                'legacy_mapping_execution_id' => $registryExecution->id,
                'legacy_application_mapping_execution_id' => $applicationExecution->id,
                'legacy_declaration_mapping_execution_id' => $declarationExecution?->id,
                'legacy_financial_mapping_execution_id' => $financialExecution?->id,
                'legacy_permit_evidence_execution_id' => $permitEvidenceExecution?->id,
                'legacy_migration_readiness_assessment_id' => $readinessAssessmentId,
                'run_reference' => $runReference,
                'verifier_version' => self::VerifierVersion,
                'selection_hash' => $selectionHash,
                'dependency_snapshot_hash' => $dependencyHash,
                'status' => LegacyMigrationRehearsalStatus::Verifying,
                'started_at' => now(),
                'metadata' => [
                    'verification_only' => true,
                    'domain_writes' => false,
                ],
            ]);
        }, 3);
    }

    /** @return list<string> */
    private function relations(): array
    {
        return [
            'importBatch.source',
            'registryExecution.mappingPlan',
            'applicationExecution.mappingPlan',
            'declarationExecution.mappingPlan',
            'financialExecution.mappingPlan',
            'permitEvidenceExecution.mappingPlan',
            'readinessAssessment',
        ];
    }

    private function assertEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Legacy migration rehearsal verification is restricted to local and testing environments.');
        }
    }

    private function assertRunReference(string $runReference): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,99}$/', $runReference) !== 1) {
            throw new RuntimeException('Migration rehearsal run reference must be 3-100 safe characters.');
        }
    }

    private function hash(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }
}
