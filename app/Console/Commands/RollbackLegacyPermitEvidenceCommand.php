<?php

namespace App\Console\Commands;

use App\Actions\RollbackLegacyPermitEvidence;
use App\Models\LegacyPermitEvidenceExecution;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:rollback-permit-evidence
    {execution : Exact permit-evidence execution ID}
    {--rollback : Confirm accepted mappings and unchanged created pending clearances may be removed}
    {--confirm-rollback : Second explicit rollback confirmation}
    {--json : Write only structured output}')]
#[Description('Rollback one completed pending-clearance migration execution without deleting pre-existing or changed clearances.')]
class RollbackLegacyPermitEvidenceCommand extends Command
{
    public function handle(RollbackLegacyPermitEvidence $action): int
    {
        try {
            if (! $this->option('rollback') || ! $this->option('confirm-rollback')) {
                throw new RuntimeException('Both --rollback and --confirm-rollback are required.');
            }
            $executionId = filter_var($this->argument('execution'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (! is_int($executionId)) {
                throw new RuntimeException('The execution argument must be an exact positive permit-evidence execution ID.');
            }

            $execution = $action->handle(LegacyPermitEvidenceExecution::query()->findOrFail($executionId));
            $artifactPath = $this->writeEvidence($execution);
        } catch (Throwable $exception) {
            return $this->failCommand($exception->getMessage());
        }

        $result = [
            'passed' => true,
            'run_id' => $execution->run_reference,
            'execution_id' => $execution->id,
            'status' => $execution->status->value,
            'remaining_execution_mappings' => $execution->mappings->count(),
            'pre_existing_targets_deleted' => false,
            'artifacts' => Storage::disk('local')->path($artifactPath),
        ];
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->line('Permit-evidence execution: '.$execution->run_reference);
            $this->line('Status: '.$execution->status->value);
            $this->line('Pre-existing clearances deleted: no');
            $this->line('Artifacts: '.Storage::disk('local')->path($artifactPath));
        }

        return self::SUCCESS;
    }

    private function writeEvidence(LegacyPermitEvidenceExecution $execution): string
    {
        $execution->loadMissing(['mappingPlan.importBatch.source', 'mappings']);
        $plan = $execution->mappingPlan;
        $root = "legacy-migrations/{$plan->importBatch->source->key}/{$plan->importBatch->run_reference}/permit-evidence-plans/{$plan->run_reference}/executions/{$execution->run_reference}";
        $report = [
            'schema_version' => 'bpls.permit-evidence-rollback.v1',
            'execution_id' => $execution->id,
            'run_id' => $execution->run_reference,
            'status' => $execution->status->value,
            'removed_mapping_count' => $execution->metadata['rollback_mapping_count'] ?? 0,
            'deleted_created_clearance_count' => $execution->metadata['rollback_deleted_created_targets'] ?? 0,
            'remaining_execution_mappings' => $execution->mappings->count(),
            'pre_existing_targets_deleted' => false,
            'personal_data_in_report' => false,
            'rolled_back_at' => $execution->rolled_back_at?->toIso8601String(),
        ];

        if (! Storage::disk('local')->put($root.'/rollback.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n")) {
            throw new RuntimeException('Permit-evidence rollback evidence could not be written to private storage.');
        }

        return $root;
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
