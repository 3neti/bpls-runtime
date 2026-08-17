<?php

namespace App\Console\Commands;

use App\Actions\AuditLegacyHistoricalFinancialPreservation;
use App\Models\LegacyHistoricalFinancialPreservationExecution;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:audit-historical-financial-preservation {execution : Exact execution ID} {--json : Write only structured output}')]
#[Description('Audit source-to-preserved counts, centavos, hashes, and operational isolation.')]
class AuditLegacyHistoricalFinancialPreservationCommand extends Command
{
    public function handle(AuditLegacyHistoricalFinancialPreservation $action): int
    {
        try {
            $execution = LegacyHistoricalFinancialPreservationExecution::query()->findOrFail($this->id($this->argument('execution')));
            $report = $action->handle($execution);
            $execution->loadMissing('preservationPlan.importBatch.source');
            $plan = $execution->preservationPlan;
            $root = "legacy-migrations/{$plan->importBatch->source->key}/{$plan->importBatch->run_reference}/historical-financial-preservation/{$plan->run_reference}/executions/{$execution->run_reference}";
            if (! Storage::disk('local')->put($root.'/audit.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n")) {
                throw new RuntimeException('Historical preservation audit evidence could not be written.');
            }
            $report['artifacts'] = Storage::disk('local')->path($root);
        } catch (Throwable $exception) {
            $this->line($this->option('json') ? json_encode(['passed' => false, 'error' => $exception->getMessage()], JSON_THROW_ON_ERROR) : $exception->getMessage());

            return self::FAILURE;
        }

        $this->line($this->option('json') ? json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : 'Historical preservation audit: '.($report['passed'] ? 'PASS' : 'FAIL')."\nArtifacts: {$report['artifacts']}");

        return $report['passed'] ? self::SUCCESS : self::FAILURE;
    }

    private function id(mixed $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! is_int($id)) {
            throw new RuntimeException('The execution value must be an exact positive ID.');
        }

        return $id;
    }
}
