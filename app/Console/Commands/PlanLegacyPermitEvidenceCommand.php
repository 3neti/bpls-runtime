<?php

namespace App\Console\Commands;

use App\Actions\PlanLegacyPermitEvidence;
use App\Models\LegacyImportBatch;
use App\Models\LegacyPermitEvidencePlan;
use App\Models\LegacyPermitEvidenceProposal;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:plan-permit-evidence {batch : Exact legacy import batch ID} {--run-id= : Stable permit-evidence plan reference} {--json : Write only structured output}')]
#[Description('Plan legacy clearances, supporting documents, and permit authority claims without domain or authority writes.')]
class PlanLegacyPermitEvidenceCommand extends Command
{
    public function handle(PlanLegacyPermitEvidence $action): int
    {
        try {
            $run = $this->option('run-id');
            if (! is_string($run) || $run === '') {
                throw new RuntimeException('A stable --run-id is required.');
            }
            $batchId = filter_var($this->argument('batch'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (! is_int($batchId)) {
                throw new RuntimeException('The batch argument must be an exact positive legacy import batch ID.');
            }
            $plan = $action->handle(LegacyImportBatch::query()->findOrFail($batchId), $run);
            $path = $this->writeEvidence($plan);
        } catch (Throwable $exception) {
            return $this->failCommand($exception->getMessage());
        }

        $result = [
            'passed' => true,
            'run_id' => $plan->run_reference,
            'plan_id' => $plan->id,
            'status' => $plan->status->value,
            'proposals' => $plan->proposal_count,
            'ready' => $plan->ready_count,
            'review_required' => $plan->review_count,
            'blocked' => $plan->blocked_count,
            'execution_authorized' => false,
            'authority_writes' => false,
            'document_objects_copied' => false,
            'artifacts' => Storage::disk('local')->path($path),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->line('Permit evidence plan: '.$plan->run_reference);
            $this->line("Disposition: {$plan->ready_count} ready / {$plan->review_count} review / {$plan->blocked_count} blocked");
            $this->line('Issuance, release, and legal-effect writes: none');
            $this->line('Document objects copied: none');
            $this->line('Artifacts: '.Storage::disk('local')->path($path));
        }

        return self::SUCCESS;
    }

    private function writeEvidence(LegacyPermitEvidencePlan $plan): string
    {
        $plan->loadMissing(['importBatch.source', 'proposals']);
        $root = "legacy-migrations/{$plan->importBatch->source->key}/{$plan->importBatch->run_reference}/permit-evidence-plans/{$plan->run_reference}";
        $report = [
            'schema_version' => 'bpls.permit-evidence-plan-report.v1',
            'run_id' => $plan->run_reference,
            'plan_id' => $plan->id,
            'dependency_snapshot_hash' => $plan->dependency_snapshot_hash,
            'result' => [
                'status' => $plan->status->value,
                'proposal_count' => $plan->proposal_count,
                'ready_count' => $plan->ready_count,
                'review_count' => $plan->review_count,
                'blocked_count' => $plan->blocked_count,
                'execution_authorized' => false,
                'authority_writes' => false,
                'document_objects_copied' => false,
            ],
            'proposals' => $plan->proposals->sortBy('id')->map(fn (LegacyPermitEvidenceProposal $proposal): array => [
                'proposal_id' => $proposal->id,
                'source_record_id' => $proposal->legacy_record_id,
                'source_dataset' => $proposal->source_dataset,
                'kind' => $proposal->kind,
                'item_key' => $proposal->item_key,
                'clearance_reconciliation_id' => $proposal->legacy_clearance_type_reconciliation_id,
                'status' => $proposal->status->value,
                'projection_hash' => $proposal->projection_hash,
                'reasons' => $proposal->reasons ?? [],
                'metadata' => $proposal->metadata,
            ])->values()->all(),
            'safety' => [
                'clearance_name_matching' => false,
                'remote_object_access' => false,
                'permit_artifact_generation' => false,
                'official_number_assignment' => false,
                'qr_publication' => false,
                'issuance_authorized' => false,
                'release_authorized' => false,
                'legal_effect_asserted' => false,
                'domain_writes' => false,
            ],
            'completed_at' => $plan->completed_at?->toIso8601String(),
        ];

        if (! Storage::disk('local')->put($root.'/permit-evidence-plan.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n")
            || ! Storage::disk('local')->put($root.'/review.md', "# Legacy Permit Evidence Plan Review\n\nReviewer status: Pending\nReviewer:\nReviewed at:\nNotes:\n")) {
            throw new RuntimeException('Permit evidence planning artifacts could not be written.');
        }

        return $root;
    }

    private function failCommand(string $message): int
    {
        if ($this->option('json')) {
            $this->line(json_encode(['passed' => false, 'error' => $message], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->error($message);
        }

        return self::FAILURE;
    }
}
