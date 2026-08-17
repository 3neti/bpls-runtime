<?php

namespace App\Console\Commands;

use App\Actions\AuditLegacyHistoricalFinancialPreservationRestoration;
use App\Models\LegacyHistoricalFinancialMappingSet;
use App\Models\LegacyHistoricalFinancialPreservationExecution;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:audit-historical-financial-preservation-restoration
    {execution : Exact rolled-back preservation execution ID}
    {--mapping-set= : Exact frozen accepted mapping-set ID}
    {--json : Write only structured output}')]
#[Description('Verify exact restoration after a historical financial preservation rollback.')]
class AuditLegacyHistoricalFinancialPreservationRestorationCommand extends Command
{
    public function handle(AuditLegacyHistoricalFinancialPreservationRestoration $action): int
    {
        try {
            $execution = LegacyHistoricalFinancialPreservationExecution::query()->findOrFail($this->positiveId($this->argument('execution'), 'execution'));
            $mappingSet = LegacyHistoricalFinancialMappingSet::query()->findOrFail($this->positiveId($this->option('mapping-set'), 'mapping-set'));
            $report = $action->handle($execution, $mappingSet);
            $execution->loadMissing('preservationPlan.importBatch.source');
            $plan = $execution->preservationPlan;
            $root = "legacy-migrations/{$plan->importBatch->source->key}/{$plan->importBatch->run_reference}/historical-financial-preservation/{$plan->run_reference}/executions/{$execution->run_reference}";
            $contents = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
            if (! Storage::disk('local')->put($root.'/restoration-audit.json', $contents)) {
                throw new RuntimeException('Restoration audit evidence could not be written.');
            }
            $report['artifacts'] = Storage::disk('local')->path($root);
        } catch (Throwable $exception) {
            $this->line($this->option('json') ? json_encode(['passed' => false, 'error' => $exception->getMessage()], JSON_THROW_ON_ERROR) : $exception->getMessage());

            return self::FAILURE;
        }

        $this->line($this->option('json') ? json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : 'Historical preservation restoration audit: '.($report['passed'] ? 'PASS' : 'FAIL')."\nArtifacts: {$report['artifacts']}");

        return $report['passed'] ? self::SUCCESS : self::FAILURE;
    }

    private function positiveId(mixed $value, string $label): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! is_int($id)) {
            throw new RuntimeException("The {$label} value must be an exact positive ID.");
        }

        return $id;
    }
}
