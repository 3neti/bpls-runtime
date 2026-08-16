<?php

namespace App\Console\Commands;

use App\Actions\PlanLegacyPermitApplications;
use App\Enums\LegacyMappingPlanStatus;
use App\Models\LegacyApplicationMappingPlan;
use App\Models\LegacyApplicationMappingProposal;
use App\Models\LegacyImportBatch;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:plan-applications
    {batch : Exact legacy import batch ID}
    {--run-id= : Stable operator-provided application-plan reference}
    {--json : Write only structured output}')]
#[Description('Plan legacy permit applications against accepted registry mappings without creating runtime applications.')]
class PlanLegacyPermitApplicationsCommand extends Command
{
    public function handle(PlanLegacyPermitApplications $action): int
    {
        try {
            $runReference = $this->option('run-id');

            if (! is_string($runReference) || $runReference === '') {
                throw new RuntimeException('A stable --run-id is required.');
            }

            $batchId = filter_var($this->argument('batch'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            if (! is_int($batchId)) {
                throw new RuntimeException('The batch argument must be an exact positive legacy import batch ID.');
            }

            $plan = $action->handle(LegacyImportBatch::query()->findOrFail($batchId), $runReference);
            $artifactPath = $this->writeEvidence($plan);
        } catch (Throwable $exception) {
            return $this->failCommand($exception->getMessage());
        }

        $result = [
            'passed' => $plan->status !== LegacyMappingPlanStatus::Failed,
            'run_id' => $plan->run_reference,
            'plan_id' => $plan->id,
            'batch_id' => $plan->legacy_import_batch_id,
            'status' => $plan->status->value,
            'proposals' => $plan->proposal_count,
            'ready' => $plan->ready_count,
            'review_required' => $plan->review_count,
            'blocked' => $plan->blocked_count,
            'exact_links' => $plan->exact_link_count,
            'accepted_id_mappings' => false,
            'permit_application_writes' => false,
            'artifacts' => Storage::disk('local')->path($artifactPath),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->line('Application mapping plan: '.$plan->run_reference);
            $this->line('Plan ID: '.$plan->id);
            $this->line('Import batch: '.$plan->legacy_import_batch_id);
            $this->line('Status: '.$plan->status->value);
            $this->line("Disposition: {$plan->ready_count} ready / {$plan->review_count} review / {$plan->blocked_count} blocked");
            $this->line('Official application numbers assigned: none');
            $this->line('Permit application writes: none');
            $this->line('Artifacts: '.Storage::disk('local')->path($artifactPath));
        }

        return self::SUCCESS;
    }

    private function writeEvidence(LegacyApplicationMappingPlan $plan): string
    {
        $plan->loadMissing(['importBatch.source', 'proposals']);
        $root = "legacy-migrations/{$plan->importBatch->source->key}/{$plan->importBatch->run_reference}/application-mapping-plans/{$plan->run_reference}";
        $report = [
            'schema_version' => 'bpls.application-mapping-plan-report.v1',
            'run_id' => $plan->run_reference,
            'plan_id' => $plan->id,
            'planner_version' => $plan->planner_version,
            'dependency_snapshot_hash' => $plan->dependency_snapshot_hash,
            'source' => [
                'key' => $plan->importBatch->source->key,
                'baseline' => $plan->importBatch->source->baseline,
                'archive_checksum' => $plan->importBatch->source->archive_checksum,
                'batch_id' => $plan->legacy_import_batch_id,
                'batch_run_id' => $plan->importBatch->run_reference,
                'manifest_checksum' => $plan->importBatch->manifest_checksum,
                'application_dataset_key' => $plan->metadata['application_dataset_key'] ?? null,
            ],
            'result' => [
                'status' => $plan->status->value,
                'proposal_count' => $plan->proposal_count,
                'ready_count' => $plan->ready_count,
                'review_count' => $plan->review_count,
                'blocked_count' => $plan->blocked_count,
                'exact_link_count' => $plan->exact_link_count,
                'accepted_id_mappings' => false,
                'permit_application_writes' => false,
            ],
            'proposals' => $plan->proposals->sortBy('id')->map(fn (LegacyApplicationMappingProposal $proposal): array => [
                'proposal_id' => $proposal->id,
                'source_record_id' => $proposal->legacy_record_id,
                'owner_mapping_id' => $proposal->owner_mapping_id,
                'business_mapping_id' => $proposal->business_mapping_id,
                'target_id' => $proposal->target_id,
                'proposed_action' => $proposal->proposed_action->value,
                'status' => $proposal->status->value,
                'identity_fingerprint' => $proposal->identity_fingerprint,
                'projection_hash' => $proposal->projection_hash,
                'collision_fingerprints' => $proposal->collision_fingerprints ?? [],
                'reasons' => $proposal->reasons ?? [],
                'metadata' => $proposal->metadata,
            ])->values()->all(),
            'policy_boundaries' => [
                'official_application_number_authority' => 'unresolved',
                'legacy_draft_submission_semantics' => 'requires_reconciliation',
                'released_status_legal_effect' => 'unresolved',
                'financial_state_migrated_by_this_plan' => false,
                'line_of_business_state_migrated_by_this_plan' => false,
            ],
            'safety' => [
                'payloads_in_report' => false,
                'personal_data_in_report' => false,
                'raw_legacy_ids_in_report' => false,
                'accepted_id_mappings' => false,
                'permit_application_writes' => false,
                'external_integrations' => false,
            ],
            'completed_at' => $plan->completed_at?->toIso8601String(),
        ];

        if (! Storage::disk('local')->put($root.'/application-plan.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n")
            || ! Storage::disk('local')->put($root.'/review.md', "# Legacy Permit Application Mapping Plan Review\n\nReviewer status: Pending\nReviewer:\nReviewed at:\nNotes:\n")) {
            throw new RuntimeException('Legacy permit application mapping evidence could not be written to private storage.');
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
