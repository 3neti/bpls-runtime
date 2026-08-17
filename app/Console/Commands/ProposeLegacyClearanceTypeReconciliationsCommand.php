<?php

namespace App\Console\Commands;

use App\Actions\ProposeLegacyClearanceTypeReconciliations;
use App\Models\LegacyImportBatch;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:propose-clearance-reconciliations {batch : Exact legacy import batch ID} {--run-id= : Stable proposal reference} {--json : Write only structured output}')]
#[Description('Propose exact clearance-type crosswalk candidates without recording municipal acceptance or migration mappings.')]
class ProposeLegacyClearanceTypeReconciliationsCommand extends Command
{
    public function handle(ProposeLegacyClearanceTypeReconciliations $action): int
    {
        try {
            $runReference = $this->runReference();
            $batch = LegacyImportBatch::query()->with('source')->findOrFail($this->batchId());
            $report = $action->handle($batch, $runReference);
            $root = $this->writeEvidence($batch, $runReference, $report);
        } catch (Throwable $exception) {
            return $this->failCommand($exception->getMessage());
        }

        $result = [
            'passed' => true,
            'run_id' => $runReference,
            'batch_id' => $batch->id,
            'affected_records' => $report['result']['affected_record_count'],
            'missing_source_identifiers' => $report['result']['missing_source_identifier_count'],
            'exact_candidates' => $report['result']['exact_candidate_count'],
            'accepted' => 0,
            'domain_writes' => false,
            'migration_executed' => false,
            'artifacts' => Storage::disk('local')->path($root),
        ];

        $this->outputResult($result);

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $report */
    private function writeEvidence(LegacyImportBatch $batch, string $runReference, array $report): string
    {
        $root = "legacy-migrations/{$batch->source->key}/{$batch->run_reference}/reconciliation/clearance-types/{$runReference}";
        $this->writeImmutable($root.'/proposal.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        $this->writeImmutable($root.'/review.md', "# Clearance-Type Reconciliation Review\n\nReviewer status: Pending\nReviewer:\nAuthority / role:\nReviewed at:\nDecision reference:\nNotes:\n");

        return $root;
    }

    private function writeImmutable(string $path, string $contents): void
    {
        $disk = Storage::disk('local');
        if ($disk->exists($path)) {
            if (! hash_equals(hash('sha256', $contents), hash('sha256', $disk->get($path)))) {
                throw new RuntimeException('Stable clearance reconciliation run is already bound to different evidence.');
            }

            return;
        }
        if (! $disk->put($path, $contents)) {
            throw new RuntimeException('Clearance reconciliation evidence could not be written.');
        }
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

        $this->line('Clearance reconciliation proposal: '.$result['run_id']);
        $this->line("Affected: {$result['affected_records']} records / {$result['missing_source_identifiers']} missing identifiers");
        $this->line("Exact candidates: {$result['exact_candidates']} / accepted: 0");
        $this->line('Domain writes and migration: none');
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
