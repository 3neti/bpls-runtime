<?php

namespace App\Console\Commands;

use App\Actions\RollbackLegacyHistoricalFinancialPreservation;
use App\Models\LegacyHistoricalFinancialPreservationExecution;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

#[Signature('legacy:rollback-historical-financial-preservation
    {execution : Exact execution ID}
    {--rollback : Confirm preserved bundles may be deleted}
    {--confirm-rollback : Second explicit confirmation}
    {--json : Write only structured output}')]
#[Description('Rollback unchanged and unreferenced historical preservation bundles in local or testing only.')]
class RollbackLegacyHistoricalFinancialPreservationCommand extends Command
{
    public function handle(RollbackLegacyHistoricalFinancialPreservation $action): int
    {
        try {
            if (! $this->option('rollback') || ! $this->option('confirm-rollback')) {
                throw new RuntimeException('Both --rollback and --confirm-rollback are required.');
            }
            $id = filter_var($this->argument('execution'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (! is_int($id)) {
                throw new RuntimeException('The execution value must be an exact positive ID.');
            }
            $execution = $action->handle(LegacyHistoricalFinancialPreservationExecution::query()->findOrFail($id));
            $result = ['passed' => true, 'execution_id' => $execution->id, 'run_id' => $execution->run_reference, 'status' => $execution->status->value, 'remaining_bundles' => $execution->bundles->count()];
        } catch (Throwable $exception) {
            $this->line($this->option('json') ? json_encode(['passed' => false, 'error' => $exception->getMessage()], JSON_THROW_ON_ERROR) : $exception->getMessage());

            return self::FAILURE;
        }

        $this->line($this->option('json') ? json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : "Historical preservation rollback: {$execution->status->value}\nRemaining bundles: {$result['remaining_bundles']}");

        return self::SUCCESS;
    }
}
