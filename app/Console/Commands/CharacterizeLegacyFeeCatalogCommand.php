<?php

namespace App\Console\Commands;

use App\Actions\CharacterizeLegacyFeeCatalog;
use App\Models\LegacyImportBatch;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Signature('legacy:characterize-fee-catalog
    {batch : Staged legacy import batch ID}
    {--run-id= : Stable operator-provided characterization reference}
    {--json : Write only structured output}')]
#[Description('Prepare reviewable legacy Price List candidates without activating fee policy or changing financial records.')]
final class CharacterizeLegacyFeeCatalogCommand extends Command
{
    public function handle(CharacterizeLegacyFeeCatalog $characterize): int
    {
        $runReference = $this->option('run-id');
        if (! is_string($runReference) || trim($runReference) === '') {
            return $this->failCommand('A stable --run-id is required.');
        }

        try {
            $batch = LegacyImportBatch::query()->with('source')->findOrFail((int) $this->argument('batch'));
            $report = $characterize->handle($batch);
            $root = "legacy-migrations/{$batch->source->key}/{$batch->run_reference}/reconciliation/fee-catalog/{$runReference}";
            Storage::disk('local')->put($root.'/summary.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
            Storage::disk('local')->put($root.'/review.md', "# Legacy Price List Review\n\nStatus: Pending municipal fiscal acceptance\n\nCharacterization does not activate fees or alter historical financial records.\n");
        } catch (Throwable $exception) {
            return $this->failCommand($exception->getMessage());
        }

        $result = [
            'passed' => true,
            'run_id' => $runReference,
            'batch_id' => $batch->id,
            'summary' => $report['summary'],
            'safety' => $report['safety'],
            'artifacts' => Storage::disk('local')->path($root),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->info('Legacy Price List candidates prepared.');
            $this->line('Fee definitions: '.$report['summary']['fee_definition_count']);
            $this->line('Range bands: '.$report['summary']['range_band_count']);
            $this->line('Overrides: '.$report['summary']['override_count']);
            $this->line('Active fee rules created: 0');
            $this->line('Historical financial records changed: 0');
            $this->line('Artifacts: '.Storage::disk('local')->path($root));
        }

        return self::SUCCESS;
    }

    private function failCommand(string $message): int
    {
        if ($this->option('json')) {
            $this->line(json_encode(['passed' => false, 'error' => $message], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->error($message);
        }

        return self::FAILURE;
    }
}
