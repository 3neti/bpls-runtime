<?php

namespace App\Console\Commands;

use App\Actions\PlanLegacyRegistryMigration;
use App\Enums\LegacyMappingPlanStatus;
use App\Models\LegacyImportBatch;
use App\Models\LegacyMappingPlan;
use App\Models\LegacyMappingProposal;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:plan-registry
    {batch : Exact legacy import batch ID}
    {--run-id= : Stable operator-provided mapping-plan reference}
    {--json : Write only structured output}')]
#[Description('Plan owner and business registry mappings without changing domain records or accepting ID mappings.')]
class PlanLegacyRegistryMigrationCommand extends Command
{
    public function handle(PlanLegacyRegistryMigration $planLegacyRegistryMigration): int
    {
        if (! app()->environment(['local', 'testing'])) {
            return $this->failCommand('Legacy registry planning is currently restricted to local and testing environments.');
        }

        $runReference = $this->option('run-id');

        if (! is_string($runReference) || $runReference === '') {
            return $this->failCommand('A stable --run-id is required.');
        }

        try {
            $batchId = filter_var($this->argument('batch'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            if (! is_int($batchId)) {
                throw new RuntimeException('The batch argument must be an exact positive legacy import batch ID.');
            }

            $batch = LegacyImportBatch::query()->with('source')->findOrFail($batchId);
            $plan = $planLegacyRegistryMigration->handle($batch, $runReference);
            $artifactPath = $this->writeEvidence($plan);
        } catch (Throwable $exception) {
            return $this->failCommand($exception->getMessage());
        }

        $passed = $plan->status !== LegacyMappingPlanStatus::Failed;
        $result = [
            'passed' => $passed,
            'run_id' => $plan->run_reference,
            'plan_id' => $plan->id,
            'batch_id' => $plan->legacy_import_batch_id,
            'status' => $plan->status->value,
            'owner_proposals' => $plan->owner_proposal_count,
            'business_proposals' => $plan->business_proposal_count,
            'ready' => $plan->ready_count,
            'review_required' => $plan->review_count,
            'blocked' => $plan->blocked_count,
            'exact_links' => $plan->exact_link_count,
            'accepted_id_mappings' => false,
            'domain_writes' => false,
            'artifacts' => Storage::disk('local')->path($artifactPath),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->line('Mapping plan: '.$plan->run_reference);
            $this->line('Plan ID: '.$plan->id);
            $this->line('Import batch: '.$plan->legacy_import_batch_id);
            $this->line('Status: '.$plan->status->value);
            $this->line("Proposals: {$plan->owner_proposal_count} owners / {$plan->business_proposal_count} businesses");
            $this->line("Disposition: {$plan->ready_count} ready / {$plan->review_count} review / {$plan->blocked_count} blocked");
            $this->line('Exact legacy links: '.$plan->exact_link_count);
            $this->line('Accepted ID mappings: none');
            $this->line('Domain writes: none');
            $this->line('Artifacts: '.Storage::disk('local')->path($artifactPath));
        }

        return $passed ? self::SUCCESS : self::FAILURE;
    }

    private function writeEvidence(LegacyMappingPlan $plan): string
    {
        $plan->loadMissing(['importBatch.source', 'proposals']);
        $root = "legacy-migrations/{$plan->importBatch->source->key}/{$plan->importBatch->run_reference}/mapping-plans/{$plan->run_reference}";
        $report = [
            'schema_version' => 'bpls.registry-mapping-plan-report.v1',
            'run_id' => $plan->run_reference,
            'plan_id' => $plan->id,
            'planner_version' => $plan->planner_version,
            'registry_snapshot_hash' => $plan->registry_snapshot_hash,
            'source' => [
                'key' => $plan->importBatch->source->key,
                'baseline' => $plan->importBatch->source->baseline,
                'archive_checksum' => $plan->importBatch->source->archive_checksum,
                'batch_id' => $plan->legacy_import_batch_id,
                'batch_run_id' => $plan->importBatch->run_reference,
                'manifest_checksum' => $plan->importBatch->manifest_checksum,
            ],
            'result' => [
                'status' => $plan->status->value,
                'owner_proposal_count' => $plan->owner_proposal_count,
                'business_proposal_count' => $plan->business_proposal_count,
                'ready_count' => $plan->ready_count,
                'review_count' => $plan->review_count,
                'blocked_count' => $plan->blocked_count,
                'exact_link_count' => $plan->exact_link_count,
                'accepted_id_mappings' => false,
                'domain_writes' => false,
            ],
            'proposals' => $plan->proposals
                ->sortBy('id')
                ->map(fn (LegacyMappingProposal $proposal): array => [
                    'proposal_id' => $proposal->id,
                    'source_record_id' => $proposal->legacy_record_id,
                    'parent_source_record_id' => $proposal->parent_legacy_record_id,
                    'dataset_key' => $proposal->dataset_key,
                    'entity_type' => $proposal->entity_type,
                    'target_type' => $proposal->target_type,
                    'target_id' => $proposal->target_id,
                    'proposed_action' => $proposal->proposed_action->value,
                    'status' => $proposal->status->value,
                    'identity_fingerprint' => $proposal->identity_fingerprint,
                    'projection_hash' => $proposal->projection_hash,
                    'collision_fingerprints' => $proposal->collision_fingerprints ?? [],
                    'reasons' => $proposal->reasons ?? [],
                    'metadata' => $proposal->metadata,
                ])
                ->values()
                ->all(),
            'safety' => [
                'payloads_in_report' => false,
                'identity_similarity_is_authority' => false,
                'accepted_id_mappings' => false,
                'domain_writes' => false,
                'external_integrations' => false,
            ],
            'completed_at' => $plan->completed_at?->toIso8601String(),
        ];
        $reportWritten = Storage::disk('local')->put($root.'/registry-plan.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        $reviewWritten = Storage::disk('local')->put($root.'/review.md', "# Legacy Registry Mapping Plan Review\n\nReviewer status: Pending\nReviewer:\nReviewed at:\nNotes:\n");

        if (! $reportWritten || ! $reviewWritten) {
            throw new RuntimeException('Legacy registry mapping evidence could not be written to private storage.');
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
