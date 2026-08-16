<?php

namespace App\Console\Commands;

use App\Actions\PlanLegacyApplicationDeclarations;
use App\Models\LegacyDeclarationMappingPlan;
use App\Models\LegacyDeclarationMappingProposal;
use App\Models\LegacyImportBatch;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:plan-declarations {batch : Exact legacy import batch ID} {--run-id= : Stable declaration-plan reference} {--json : Write only structured output}')]
#[Description('Plan legacy application line declarations without matching by name or calculating fees.')]
class PlanLegacyApplicationDeclarationsCommand extends Command
{
    public function handle(PlanLegacyApplicationDeclarations $action): int
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

        $result = ['passed' => true, 'run_id' => $plan->run_reference, 'plan_id' => $plan->id, 'status' => $plan->status->value,
            'proposals' => $plan->proposal_count, 'ready' => $plan->ready_count, 'review_required' => $plan->review_count,
            'blocked' => $plan->blocked_count, 'financial_calculations' => false, 'domain_writes' => false,
            'artifacts' => Storage::disk('local')->path($path)];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->line('Declaration mapping plan: '.$plan->run_reference);
            $this->line("Disposition: {$plan->ready_count} ready / {$plan->review_count} review / {$plan->blocked_count} blocked");
            $this->line('Financial calculations: none');
            $this->line('Domain writes: none');
            $this->line('Artifacts: '.Storage::disk('local')->path($path));
        }

        return self::SUCCESS;
    }

    private function writeEvidence(LegacyDeclarationMappingPlan $plan): string
    {
        $plan->loadMissing(['importBatch.source', 'proposals']);
        $root = "legacy-migrations/{$plan->importBatch->source->key}/{$plan->importBatch->run_reference}/declaration-mapping-plans/{$plan->run_reference}";
        $report = ['schema_version' => 'bpls.declaration-mapping-plan-report.v1', 'run_id' => $plan->run_reference, 'plan_id' => $plan->id,
            'dependency_snapshot_hash' => $plan->dependency_snapshot_hash,
            'result' => ['status' => $plan->status->value, 'proposal_count' => $plan->proposal_count, 'ready_count' => $plan->ready_count,
                'review_count' => $plan->review_count, 'blocked_count' => $plan->blocked_count, 'financial_calculations' => false, 'domain_writes' => false],
            'proposals' => $plan->proposals->sortBy('id')->map(fn (LegacyDeclarationMappingProposal $proposal): array => [
                'proposal_id' => $proposal->id, 'source_record_id' => $proposal->legacy_record_id, 'line_index' => $proposal->line_index,
                'reconciliation_id' => $proposal->legacy_line_of_business_reconciliation_id, 'line_of_business_id' => $proposal->line_of_business_id,
                'status' => $proposal->status->value, 'projection_hash' => $proposal->projection_hash, 'reasons' => $proposal->reasons ?? [], 'metadata' => $proposal->metadata,
            ])->values()->all(),
            'safety' => ['payloads_in_report' => false, 'raw_category_values_in_report' => false, 'name_only_matching' => false,
                'range_values_interpreted_as_exact' => false, 'financial_calculations' => false, 'domain_writes' => false],
            'completed_at' => $plan->completed_at?->toIso8601String()];

        if (! Storage::disk('local')->put($root.'/declaration-plan.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n")
            || ! Storage::disk('local')->put($root.'/review.md', "# Legacy Application Declaration Plan Review\n\nReviewer status: Pending\nReviewer:\nReviewed at:\nNotes:\n")) {
            throw new RuntimeException('Declaration mapping evidence could not be written.');
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
