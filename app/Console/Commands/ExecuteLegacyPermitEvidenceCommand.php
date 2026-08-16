<?php

namespace App\Console\Commands;

use App\Actions\ExecuteLegacyPermitEvidence;
use App\Models\LegacyPermitEvidenceExecution;
use App\Models\LegacyPermitEvidencePlan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:execute-permit-evidence
    {plan : Exact permit-evidence plan ID}
    {--proposal=* : Exact ready pending-clearance proposal ID; repeat for each approved proposal}
    {--run-id= : Stable operator-provided execution reference}
    {--execute : Confirm that pending clearance records and mappings may be written}
    {--confirm-execute : Second explicit confirmation of the selected writes}
    {--json : Write only structured output}')]
#[Description('Execute explicitly selected ready pending-clearance proposals in local or testing environments without asserting completion or permit authority.')]
class ExecuteLegacyPermitEvidenceCommand extends Command
{
    public function handle(ExecuteLegacyPermitEvidence $action): int
    {
        try {
            if (! $this->option('execute') || ! $this->option('confirm-execute')) {
                throw new RuntimeException('Both --execute and --confirm-execute are required for pending clearance writes.');
            }

            $runReference = $this->option('run-id');
            if (! is_string($runReference) || $runReference === '') {
                throw new RuntimeException('A stable --run-id is required.');
            }

            $planId = $this->positiveInteger($this->argument('plan'), 'plan');
            $proposalIds = array_values(array_map(
                fn (mixed $value): int => $this->positiveInteger($value, 'proposal'),
                $this->option('proposal'),
            ));
            $execution = $action->handle(LegacyPermitEvidencePlan::query()->findOrFail($planId), $proposalIds, $runReference);
            $artifactPath = $this->writeEvidence($execution);
        } catch (Throwable $exception) {
            return $this->failCommand($exception->getMessage());
        }

        $result = $this->result($execution, $artifactPath);
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->line('Permit-evidence execution: '.$execution->run_reference);
            $this->line('Execution ID: '.$execution->id);
            $this->line('Status: '.$execution->status->value);
            $this->line("Selected: {$execution->selected_count}; created: {$execution->created_count}; exact links: {$execution->linked_count}; reused: {$execution->reused_count}");
            $this->line('Completed clearances created: none');
            $this->line('Document objects copied: none');
            $this->line('Permit authority asserted: none');
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
            'schema_version' => 'bpls.permit-evidence-execution.v1',
            'execution_id' => $execution->id,
            'run_id' => $execution->run_reference,
            'plan_id' => $execution->legacy_permit_evidence_plan_id,
            'selection_hash' => $execution->selection_hash,
            'status' => $execution->status->value,
            'counts' => [
                'selected' => $execution->selected_count,
                'created' => $execution->created_count,
                'linked_exact' => $execution->linked_count,
                'reused' => $execution->reused_count,
                'accepted_mappings' => $execution->mapping_count,
            ],
            'mappings' => $execution->mappings->map(fn ($mapping): array => [
                'mapping_id' => $mapping->id,
                'proposal_id' => $mapping->metadata['proposal_id'] ?? null,
                'permit_clearance_id' => $mapping->permit_clearance_id,
                'mapping_basis' => $mapping->mapping_basis,
                'created_by_execution' => $mapping->metadata['created_by_execution'] ?? false,
                'projection_hash' => $mapping->metadata['projection_hash'] ?? null,
                'target_snapshot_hash' => $mapping->metadata['target_snapshot_hash'] ?? null,
                'completion_authority_asserted' => false,
            ])->all(),
            'safety' => [
                'environment' => app()->environment(),
                'explicit_proposal_selection' => true,
                'double_confirmation' => true,
                'pending_clearances_only' => true,
                'completed_clearances_created' => false,
                'document_objects_copied' => false,
                'permit_artifacts_created' => false,
                'issuance_authorized' => false,
                'release_authorized' => false,
                'legal_effect_asserted' => false,
                'external_integrations' => false,
                'notifications' => false,
                'irreversible_actions' => false,
                'raw_legacy_ids_in_report' => false,
                'personal_data_in_report' => false,
            ],
            'completed_at' => $execution->completed_at?->toIso8601String(),
        ];

        if (! Storage::disk('local')->put($root.'/execution.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n")
            || ! Storage::disk('local')->put($root.'/review.md', "# Permit Evidence Migration Execution Review\n\nReviewer status: Pending\nReviewer:\nReviewed at:\nNotes:\n")) {
            throw new RuntimeException('Permit-evidence execution could not be written to private storage.');
        }

        return $root;
    }

    /** @return array<string, mixed> */
    private function result(LegacyPermitEvidenceExecution $execution, string $artifactPath): array
    {
        return [
            'passed' => true,
            'run_id' => $execution->run_reference,
            'execution_id' => $execution->id,
            'plan_id' => $execution->legacy_permit_evidence_plan_id,
            'status' => $execution->status->value,
            'selected' => $execution->selected_count,
            'created' => $execution->created_count,
            'linked_exact' => $execution->linked_count,
            'reused' => $execution->reused_count,
            'accepted_mappings' => $execution->mapping_count,
            'completed_clearances_created' => 0,
            'document_objects_copied' => 0,
            'permit_authority_claims_executed' => 0,
            'external_calls' => 0,
            'artifacts' => Storage::disk('local')->path($artifactPath),
        ];
    }

    private function positiveInteger(mixed $value, string $name): int
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
