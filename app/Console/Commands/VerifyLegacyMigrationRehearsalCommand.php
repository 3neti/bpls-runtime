<?php

namespace App\Console\Commands;

use App\Actions\VerifyLegacyMigrationRehearsal;
use App\Enums\LegacyMigrationRehearsalStatus;
use App\Models\LegacyApplicationMappingExecution;
use App\Models\LegacyDeclarationMappingExecution;
use App\Models\LegacyFinancialMappingExecution;
use App\Models\LegacyImportBatch;
use App\Models\LegacyMappingExecution;
use App\Models\LegacyMigrationRehearsal;
use App\Models\LegacyPermitEvidenceExecution;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:verify-migration-rehearsal
    {batch : Exact staged legacy import batch ID}
    {--registry-execution= : Exact completed registry execution ID}
    {--application-execution= : Exact completed application execution ID}
    {--declaration-execution= : Exact completed declaration execution ID when applicable}
    {--financial-execution= : Exact completed financial execution ID when applicable}
    {--permit-evidence-execution= : Exact completed permit-evidence execution ID when applicable}
    {--run-id= : Stable operator-provided rehearsal reference}
    {--verify : Confirm creation of an immutable cross-domain rehearsal verification}
    {--confirm-verify : Second explicit verification confirmation}
    {--json : Write only structured output}')]
#[Description('Verify exact completed legacy migration executions as one bounded, reversible, redacted local rehearsal.')]
class VerifyLegacyMigrationRehearsalCommand extends Command
{
    public function handle(VerifyLegacyMigrationRehearsal $action): int
    {
        try {
            if (! $this->option('verify') || ! $this->option('confirm-verify')) {
                throw new RuntimeException('Both --verify and --confirm-verify are required.');
            }
            $runReference = $this->option('run-id');
            if (! is_string($runReference) || $runReference === '') {
                throw new RuntimeException('A stable --run-id is required.');
            }
            $declarationId = $this->optionalId($this->option('declaration-execution'), 'declaration-execution');
            $financialId = $this->optionalId($this->option('financial-execution'), 'financial-execution');
            $permitEvidenceId = $this->optionalId($this->option('permit-evidence-execution'), 'permit-evidence-execution');

            $rehearsal = $action->handle(
                LegacyImportBatch::query()->findOrFail($this->requiredId($this->argument('batch'), 'batch')),
                LegacyMappingExecution::query()->findOrFail($this->requiredId($this->option('registry-execution'), 'registry-execution')),
                LegacyApplicationMappingExecution::query()->findOrFail($this->requiredId($this->option('application-execution'), 'application-execution')),
                $declarationId === null ? null : LegacyDeclarationMappingExecution::query()->findOrFail($declarationId),
                $financialId === null ? null : LegacyFinancialMappingExecution::query()->findOrFail($financialId),
                $permitEvidenceId === null ? null : LegacyPermitEvidenceExecution::query()->findOrFail($permitEvidenceId),
                $runReference,
            );
            $artifactPath = $this->writeEvidence($rehearsal);
        } catch (Throwable $exception) {
            return $this->failCommand($exception->getMessage());
        }

        $passed = $rehearsal->status === LegacyMigrationRehearsalStatus::Verified;
        $result = [
            'passed' => $passed,
            'run_id' => $rehearsal->run_reference,
            'rehearsal_id' => $rehearsal->id,
            'status' => $rehearsal->status->value,
            'checks' => $rehearsal->check_count,
            'blocked' => $rehearsal->blocked_count,
            'cutover_authorized' => $rehearsal->metadata['cutover_authorized'] ?? false,
            'domain_writes' => 0,
            'external_calls' => 0,
            'artifacts' => Storage::disk('local')->path($artifactPath),
        ];
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->line('Migration rehearsal: '.$rehearsal->run_reference);
            $this->line('Status: '.$rehearsal->status->value);
            $this->line("Checks: {$rehearsal->passed_count}/{$rehearsal->check_count}; blocked: {$rehearsal->blocked_count}");
            $this->line('Cutover authorized: '.(($rehearsal->metadata['cutover_authorized'] ?? false) ? 'yes' : 'no'));
            $this->line('Domain records written by verifier: none');
            $this->line('Artifacts: '.Storage::disk('local')->path($artifactPath));
        }

        return $passed ? self::SUCCESS : self::FAILURE;
    }

    private function writeEvidence(LegacyMigrationRehearsal $rehearsal): string
    {
        $rehearsal->loadMissing(['importBatch.source', 'registryExecution.mappingPlan', 'applicationExecution.mappingPlan', 'declarationExecution.mappingPlan', 'financialExecution.mappingPlan', 'permitEvidenceExecution.mappingPlan', 'readinessAssessment']);
        $root = "legacy-migrations/{$rehearsal->importBatch->source->key}/{$rehearsal->importBatch->run_reference}/rehearsals/{$rehearsal->run_reference}";
        $report = [
            'schema_version' => 'bpls.legacy-migration-rehearsal-report.v1',
            'run_id' => $rehearsal->run_reference,
            'rehearsal_id' => $rehearsal->id,
            'status' => $rehearsal->status->value,
            'selection_hash' => $rehearsal->selection_hash,
            'dependency_snapshot_hash' => $rehearsal->dependency_snapshot_hash,
            'executions' => [
                'registry' => $this->executionEvidence($rehearsal->registryExecution),
                'applications' => $this->executionEvidence($rehearsal->applicationExecution),
                'declarations' => $this->executionEvidence($rehearsal->declarationExecution),
                'financial' => $this->executionEvidence($rehearsal->financialExecution),
                'permit_evidence' => $this->executionEvidence($rehearsal->permitEvidenceExecution),
            ],
            'readiness' => [
                'assessment_id' => $rehearsal->legacy_migration_readiness_assessment_id,
                'rehearsal_ready' => $rehearsal->readinessAssessment?->rehearsal_ready,
                'cutover_ready' => $rehearsal->readinessAssessment?->cutover_ready,
            ],
            'checks' => $rehearsal->checks,
            'safety' => [
                'verification_only' => true,
                'domain_logic_duplicated' => false,
                'domain_writes' => false,
                'external_integrations' => false,
                'notifications' => false,
                'irreversible_actions' => false,
                'cutover_authority_inferred' => false,
                'raw_legacy_ids_in_report' => false,
                'personal_data_in_report' => false,
            ],
            'completed_at' => $rehearsal->completed_at?->toIso8601String(),
        ];

        if (! Storage::disk('local')->put($root.'/rehearsal.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n")
            || ! Storage::disk('local')->put($root.'/review.md', "# Legacy Migration Rehearsal Review\n\nReviewer status: Pending\nReviewer:\nReviewed at:\nNotes:\n")) {
            throw new RuntimeException('Migration rehearsal evidence could not be written to private storage.');
        }

        return $root;
    }

    /** @return array<string, mixed>|null */
    private function executionEvidence(?Model $execution): ?array
    {
        if ($execution === null) {
            return null;
        }

        return [
            'execution_id' => $execution->getKey(),
            'plan_id' => $execution->getRelationValue('mappingPlan')?->getKey(),
            'status' => $execution->getAttribute('status')->value,
            'selection_hash' => $execution->getAttribute('selection_hash'),
            'selected' => $execution->getAttribute('selected_count'),
            'created' => $execution->getAttribute('created_count'),
            'reused' => $execution->getAttribute('reused_count'),
            'mappings' => $execution->getAttribute('mapping_count'),
        ];
    }

    private function optionalId(mixed $value, string $name): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->requiredId($value, $name);
    }

    private function requiredId(mixed $value, string $name): int
    {
        $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! is_int($validated)) {
            throw new RuntimeException("The {$name} value must be an exact positive ID.");
        }

        return $validated;
    }

    private function failCommand(string $message): int
    {
        if ($this->option('json')) {
            $this->line(json_encode(['passed' => false, 'error' => $message], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->error($message);
        }

        return self::FAILURE;
    }
}
