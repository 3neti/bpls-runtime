<?php

namespace App\Console\Commands;

use App\Actions\BuildLegacyFinancialFormulaReconciliation;
use App\Models\LegacyImportBatch;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:build-financial-reconciliation {batch : Exact legacy import batch ID} {--run-id= : Stable reconciliation reference} {--legacy-archive=docs/sources/legacy/bpls-system-main.zip : Authoritative legacy source archive} {--json : Write only structured output}')]
#[Description('Build private production financial formula reconciliation evidence without evaluating formulas or recalculating liability.')]
class BuildLegacyFinancialFormulaReconciliationCommand extends Command
{
    public function handle(BuildLegacyFinancialFormulaReconciliation $action): int
    {
        try {
            $runReference = $this->runReference();
            $batch = LegacyImportBatch::query()->with('source')->findOrFail($this->batchId());
            $report = $action->handle($batch, $runReference, $this->archivePath());
            $root = $this->writeEvidence($batch, $runReference, $report);
        } catch (Throwable $exception) {
            return $this->failCommand($exception->getMessage());
        }

        $summary = $report['summary'];
        $result = [
            'passed' => true,
            'run_id' => $runReference,
            'batch_id' => $batch->id,
            'fee_definitions' => $summary['fee_definition_count'],
            'formula_bearing_fees' => $summary['formula_bearing_fee_count'],
            'range_based_fees' => $summary['range_based_fee_count'],
            'fee_overrides' => $summary['fee_override_count'],
            'payment_schedules' => $summary['payment_schedule_count'],
            'payments' => $summary['payment_count'],
            'historical_formula_attribution' => $summary['historical_formula_attribution_status'],
            'accepted_interpretations' => 0,
            'formulas_evaluated' => false,
            'historical_liability_recalculated' => false,
            'financial_domain_writes' => false,
            'migration_executed' => false,
            'artifacts' => Storage::disk('local')->path($root),
        ];

        $this->outputResult($result);

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $report */
    private function writeEvidence(LegacyImportBatch $batch, string $runReference, array $report): string
    {
        $root = "legacy-migrations/{$batch->source->key}/{$batch->run_reference}/reconciliation/financial-formulas/{$runReference}";
        $this->writeImmutable($root.'/financial-reconciliation.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        $this->writeImmutable($root.'/review.md', "# Production Financial Formula Reconciliation Review\n\nReviewer status: Pending\nReviewer:\nMunicipal authority / role:\nReviewed at:\nDecision reference:\nNotes:\n");

        return $root;
    }

    private function writeImmutable(string $path, string $contents): void
    {
        $disk = Storage::disk('local');
        if ($disk->exists($path)) {
            if (! hash_equals(hash('sha256', $contents), hash('sha256', $disk->get($path)))) {
                throw new RuntimeException('Stable financial reconciliation run is already bound to different evidence.');
            }

            return;
        }
        if (! $disk->put($path, $contents)) {
            throw new RuntimeException('Financial reconciliation evidence could not be written.');
        }
    }

    private function archivePath(): string
    {
        $path = $this->option('legacy-archive');
        if (! is_string($path) || trim($path) === '') {
            throw new RuntimeException('The --legacy-archive path is required.');
        }
        $path = trim($path);

        return str_starts_with($path, '/') ? $path : base_path($path);
    }

    private function runReference(): string
    {
        $runReference = $this->option('run-id');
        if (! is_string($runReference) || trim($runReference) === '') {
            throw new RuntimeException('A stable --run-id is required.');
        }

        return trim($runReference);
    }

    private function batchId(): int
    {
        $batchId = filter_var($this->argument('batch'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! is_int($batchId)) {
            throw new RuntimeException('The batch argument must be an exact positive legacy import batch ID.');
        }

        return $batchId;
    }

    /** @param array<string, mixed> $result */
    private function outputResult(array $result): void
    {
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return;
        }

        $this->line('Financial reconciliation: '.$result['run_id']);
        $this->line("Configuration: {$result['fee_definitions']} fees / {$result['formula_bearing_fees']} formulas / {$result['range_based_fees']} range based / {$result['fee_overrides']} overrides");
        $this->line("Historical evidence: {$result['payment_schedules']} schedules / {$result['payments']} payments");
        $this->line('Formula attribution: '.$result['historical_formula_attribution']);
        $this->line('Formula evaluation, liability recalculation, and financial writes: none');
        $this->line('Artifacts: '.$result['artifacts']);
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
