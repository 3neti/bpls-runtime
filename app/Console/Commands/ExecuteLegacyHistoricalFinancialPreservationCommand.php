<?php

namespace App\Console\Commands;

use App\Actions\ExecuteLegacyHistoricalFinancialPreservation;
use App\Models\LegacyHistoricalFinancialPreservationExecution;
use App\Models\LegacyHistoricalFinancialPreservationPlan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:execute-historical-financial-preservation
    {plan : Exact historical preservation plan ID}
    {--proposal=* : Exact ready complete-application proposal ID}
    {--run-id= : Stable operator-provided execution reference}
    {--execute : Confirm isolated preservation bundles may be written}
    {--confirm-execute : Second explicit confirmation}
    {--json : Write only structured output}')]
#[Description('Preserve selected complete historical application financial histories in local or testing only.')]
class ExecuteLegacyHistoricalFinancialPreservationCommand extends Command
{
    public function handle(ExecuteLegacyHistoricalFinancialPreservation $action): int
    {
        try {
            if (! $this->option('execute') || ! $this->option('confirm-execute')) {
                throw new RuntimeException('Both --execute and --confirm-execute are required for historical preservation writes.');
            }
            $runId = $this->option('run-id');
            if (! is_string($runId) || $runId === '') {
                throw new RuntimeException('A stable --run-id is required.');
            }
            $plan = LegacyHistoricalFinancialPreservationPlan::query()->findOrFail($this->id($this->argument('plan'), 'plan'));
            $ids = array_values(array_map(fn (mixed $id): int => $this->id($id, 'proposal'), $this->option('proposal')));
            $execution = $action->handle($plan, $ids, $runId);
            $execution->loadMissing('preservationPlan.importBatch.source');
            $root = $this->root($execution);
            $result = [
                'schema_version' => 'bpls.historical-financial-preservation-execution.v1',
                'passed' => true,
                'execution_id' => $execution->id,
                'run_id' => $execution->run_reference,
                'status' => $execution->status->value,
                'counts' => ['selected' => $execution->selected_count, 'created' => $execution->created_count, 'reused' => $execution->reused_count],
                'selection_hash' => $execution->selection_hash,
                'safety' => $execution->metadata,
            ];
            $this->put($root.'/execution.json', $result);
            Storage::disk('local')->put($root.'/review.md', "# Historical Financial Preservation Review\n\nReviewer status: Pending\nReviewer:\nReviewed at:\nNotes:\n");
            $result['artifacts'] = Storage::disk('local')->path($root);
        } catch (Throwable $exception) {
            return $this->failure($exception->getMessage());
        }

        $this->line($this->option('json') ? json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : "Historical preservation execution: {$execution->run_reference}\nBundles: {$execution->created_count}\nOperational financial writes: none\nArtifacts: {$result['artifacts']}");

        return self::SUCCESS;
    }

    private function root(LegacyHistoricalFinancialPreservationExecution $execution): string
    {
        $plan = $execution->preservationPlan;

        return "legacy-migrations/{$plan->importBatch->source->key}/{$plan->importBatch->run_reference}/historical-financial-preservation/{$plan->run_reference}/executions/{$execution->run_reference}";
    }

    /** @param array<string, mixed> $value */
    private function put(string $path, array $value): void
    {
        if (! Storage::disk('local')->put($path, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n")) {
            throw new RuntimeException('Historical preservation execution evidence could not be written.');
        }
    }

    private function id(mixed $value, string $name): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! is_int($id)) {
            throw new RuntimeException("The {$name} value must be an exact positive ID.");
        }

        return $id;
    }

    private function failure(string $message): int
    {
        $this->line($this->option('json') ? json_encode(['passed' => false, 'error' => $message], JSON_THROW_ON_ERROR) : $message);

        return self::FAILURE;
    }
}
