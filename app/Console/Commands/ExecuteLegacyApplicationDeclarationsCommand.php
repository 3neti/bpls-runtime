<?php

namespace App\Console\Commands;

use App\Actions\ExecuteLegacyApplicationDeclarations;
use App\Models\LegacyDeclarationMappingExecution;
use App\Models\LegacyDeclarationMappingPlan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:execute-declarations
    {plan : Exact declaration mapping plan ID}
    {--proposal=* : Exact ready proposal ID; include the complete set for every selected application}
    {--run-id= : Stable operator-provided execution reference}
    {--execute : Confirm permit application declaration lines may be written}
    {--confirm-execute : Second explicit confirmation of the selected writes}
    {--json : Write only structured output}')]
#[Description('Execute complete ready declaration sets in local or testing environments without calculating assessments.')]
class ExecuteLegacyApplicationDeclarationsCommand extends Command
{
    public function handle(ExecuteLegacyApplicationDeclarations $action): int
    {
        try {
            if (! $this->option('execute') || ! $this->option('confirm-execute')) {
                throw new RuntimeException('Both --execute and --confirm-execute are required for declaration writes.');
            }

            $runReference = $this->option('run-id');

            if (! is_string($runReference) || $runReference === '') {
                throw new RuntimeException('A stable --run-id is required.');
            }

            $planId = $this->positiveInteger($this->argument('plan'), 'plan');
            $proposalIds = array_values(array_map(fn (mixed $value): int => $this->positiveInteger($value, 'proposal'), $this->option('proposal')));
            $plan = LegacyDeclarationMappingPlan::query()->findOrFail($planId);
            $execution = $action->handle($plan, $proposalIds, $runReference);
            $artifactPath = $this->writeEvidence($execution);
        } catch (Throwable $exception) {
            return $this->failCommand($exception->getMessage());
        }

        $result = $this->result($execution, $artifactPath);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->line('Declaration execution: '.$execution->run_reference);
            $this->line('Execution ID: '.$execution->id);
            $this->line('Status: '.$execution->status->value);
            $this->line("Selected: {$execution->selected_count}; created: {$execution->created_count}; reused: {$execution->reused_count}");
            $this->line('Financial calculations: none');
            $this->line('Assessment records created: none');
            $this->line('External calls: none');
            $this->line('Artifacts: '.Storage::disk('local')->path($artifactPath));
        }

        return self::SUCCESS;
    }

    private function writeEvidence(LegacyDeclarationMappingExecution $execution): string
    {
        $execution->loadMissing(['mappingPlan.importBatch.source', 'mappings']);
        $plan = $execution->mappingPlan;
        $root = "legacy-migrations/{$plan->importBatch->source->key}/{$plan->importBatch->run_reference}/declaration-mapping-plans/{$plan->run_reference}/executions/{$execution->run_reference}";
        $report = [
            'schema_version' => 'bpls.declaration-migration-execution.v1',
            'execution_id' => $execution->id,
            'run_id' => $execution->run_reference,
            'plan_id' => $execution->legacy_declaration_mapping_plan_id,
            'selection_hash' => $execution->selection_hash,
            'status' => $execution->status->value,
            'counts' => [
                'selected' => $execution->selected_count,
                'created' => $execution->created_count,
                'reused' => $execution->reused_count,
                'accepted_mappings' => $execution->mapping_count,
            ],
            'mappings' => $execution->mappings->map(fn ($mapping): array => [
                'mapping_id' => $mapping->id,
                'proposal_id' => $mapping->metadata['proposal_id'] ?? null,
                'permit_application_line_id' => $mapping->permit_application_line_id,
                'application_mapping_id' => $mapping->legacy_application_id_mapping_id,
                'reconciliation_id' => $mapping->legacy_line_of_business_reconciliation_id,
                'line_index' => $mapping->line_index,
                'projection_hash' => $mapping->metadata['projection_hash'] ?? null,
                'target_snapshot_hash' => $mapping->metadata['target_snapshot_hash'] ?? null,
            ])->all(),
            'safety' => [
                'environment' => app()->environment(),
                'complete_application_sets_required' => true,
                'explicit_proposal_selection' => true,
                'double_confirmation' => true,
                'financial_calculations' => false,
                'assessment_records_created' => false,
                'external_integrations' => false,
                'notifications' => false,
                'irreversible_actions' => false,
                'raw_legacy_ids_in_report' => false,
                'personal_data_in_report' => false,
            ],
            'completed_at' => $execution->completed_at?->toIso8601String(),
        ];

        if (! Storage::disk('local')->put($root.'/execution.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n")
            || ! Storage::disk('local')->put($root.'/review.md', "# Declaration Migration Execution Review\n\nReviewer status: Pending\nReviewer:\nReviewed at:\nNotes:\n")) {
            throw new RuntimeException('Declaration migration execution evidence could not be written to private storage.');
        }

        return $root;
    }

    /** @return array<string, mixed> */
    private function result(LegacyDeclarationMappingExecution $execution, string $artifactPath): array
    {
        return [
            'passed' => true,
            'run_id' => $execution->run_reference,
            'execution_id' => $execution->id,
            'plan_id' => $execution->legacy_declaration_mapping_plan_id,
            'status' => $execution->status->value,
            'selected' => $execution->selected_count,
            'created' => $execution->created_count,
            'reused' => $execution->reused_count,
            'accepted_mappings' => $execution->mapping_count,
            'financial_calculations' => 0,
            'assessment_records_created' => 0,
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
