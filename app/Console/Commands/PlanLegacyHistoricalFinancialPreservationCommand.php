<?php

namespace App\Console\Commands;

use App\Actions\PlanLegacyHistoricalFinancialPreservation;
use App\Models\LegacyFinancialMappingPlan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:plan-historical-financial-preservation
    {financial-plan : Exact completed financial mapping plan ID}
    {--run-id= : Stable operator-provided planning reference}
    {--json : Write only structured output}')]
#[Description('Plan complete legacy application financial histories for isolated preservation.')]
class PlanLegacyHistoricalFinancialPreservationCommand extends Command
{
    public function handle(PlanLegacyHistoricalFinancialPreservation $action): int
    {
        try {
            $runId = $this->requiredRunId();
            $plan = $action->handle(LegacyFinancialMappingPlan::query()->findOrFail($this->positiveInteger($this->argument('financial-plan'))), $runId);
            $root = "legacy-migrations/{$plan->importBatch->source->key}/{$plan->importBatch->run_reference}/historical-financial-preservation/{$plan->run_reference}";
            $result = [
                'schema_version' => 'bpls.historical-financial-preservation-plan.v1',
                'passed' => true,
                'plan_id' => $plan->id,
                'run_id' => $plan->run_reference,
                'status' => $plan->status->value,
                'counts' => ['proposals' => $plan->proposal_count, 'ready' => $plan->ready_count, 'blocked' => $plan->blocked_count],
                'dependency_snapshot_hash' => $plan->dependency_snapshot_hash,
                'safety' => $plan->metadata,
            ];
            $this->put($root.'/plan.json', $result);
            $result['artifacts'] = Storage::disk('local')->path($root);
        } catch (Throwable $exception) {
            return $this->failure($exception->getMessage());
        }

        return $this->success($result, "Historical preservation plan: {$plan->run_reference}");
    }

    private function requiredRunId(): string
    {
        $value = $this->option('run-id');
        if (! is_string($value) || $value === '') {
            throw new RuntimeException('A stable --run-id is required.');
        }

        return $value;
    }

    private function positiveInteger(mixed $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! is_int($id)) {
            throw new RuntimeException('The financial-plan value must be an exact positive ID.');
        }

        return $id;
    }

    /** @param array<string, mixed> $value */
    private function put(string $path, array $value): void
    {
        if (! Storage::disk('local')->put($path, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n")) {
            throw new RuntimeException('Historical preservation planning evidence could not be written.');
        }
    }

    /** @param array<string, mixed> $result */
    private function success(array $result, string $label): int
    {
        $this->line($this->option('json') ? json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : $label."\nArtifacts: ".$result['artifacts']);

        return self::SUCCESS;
    }

    private function failure(string $message): int
    {
        $this->line($this->option('json') ? json_encode(['passed' => false, 'error' => $message], JSON_THROW_ON_ERROR) : $message);

        return self::FAILURE;
    }
}
