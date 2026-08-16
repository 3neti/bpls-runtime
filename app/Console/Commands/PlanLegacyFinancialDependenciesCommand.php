<?php

namespace App\Console\Commands;

use App\Actions\PlanLegacyFinancialDependencies;
use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyFinancialMappingProposal;
use App\Models\LegacyImportBatch;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:plan-financial-dependencies {batch : Exact legacy import batch ID} {--run-id= : Stable financial-plan reference} {--json : Write only structured output}')]
#[Description('Plan legacy fee exceptions, schedules, payments, and receipt claims without financial domain writes.')]
class PlanLegacyFinancialDependenciesCommand extends Command
{
    public function handle(PlanLegacyFinancialDependencies $action): int
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
            'liability_calculations' => false,
            'financial_domain_writes' => false,
            'artifacts' => Storage::disk('local')->path($path),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->line('Financial dependency plan: '.$plan->run_reference);
            $this->line("Disposition: {$plan->ready_count} ready / {$plan->review_count} review / {$plan->blocked_count} blocked");
            $this->line('Liability calculations: none');
            $this->line('Financial domain writes: none');
            $this->line('Artifacts: '.Storage::disk('local')->path($path));
        }

        return self::SUCCESS;
    }

    private function writeEvidence(LegacyFinancialMappingPlan $plan): string
    {
        $plan->loadMissing(['importBatch.source', 'proposals']);
        $root = "legacy-migrations/{$plan->importBatch->source->key}/{$plan->importBatch->run_reference}/financial-mapping-plans/{$plan->run_reference}";
        $report = [
            'schema_version' => 'bpls.financial-mapping-plan-report.v1',
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
                'liability_calculations' => false,
                'financial_domain_writes' => false,
            ],
            'proposals' => $plan->proposals->sortBy('id')->map(fn (LegacyFinancialMappingProposal $proposal): array => [
                'proposal_id' => $proposal->id,
                'source_record_id' => $proposal->legacy_record_id,
                'source_dataset' => $proposal->source_dataset,
                'kind' => $proposal->kind,
                'item_key' => $proposal->item_key,
                'fee_reconciliation_id' => $proposal->legacy_fee_rule_reconciliation_id,
                'fee_rule_id' => $proposal->fee_rule_id,
                'status' => $proposal->status->value,
                'projection_hash' => $proposal->projection_hash,
                'reasons' => $proposal->reasons ?? [],
                'metadata' => $proposal->metadata,
            ])->values()->all(),
            'safety' => [
                'payloads_in_report' => false,
                'raw_transaction_references_in_report' => false,
                'raw_receipt_numbers_in_report' => false,
                'fee_name_matching' => false,
                'execution_authorized' => false,
                'liability_calculations' => false,
                'collection_writes' => false,
                'receipt_writes' => false,
                'financial_domain_writes' => false,
            ],
            'completed_at' => $plan->completed_at?->toIso8601String(),
        ];

        if (! Storage::disk('local')->put($root.'/financial-plan.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n")
            || ! Storage::disk('local')->put($root.'/review.md', "# Legacy Financial Dependency Plan Review\n\nReviewer status: Pending\nReviewer:\nReviewed at:\nNotes:\n")) {
            throw new RuntimeException('Financial mapping evidence could not be written.');
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
