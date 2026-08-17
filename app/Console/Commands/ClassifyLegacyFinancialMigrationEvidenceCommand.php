<?php

namespace App\Console\Commands;

use App\Actions\ClassifyLegacyFinancialMigrationEvidence;
use App\Models\LegacyFinancialMappingPlan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:classify-financial-migration {plan : Exact completed financial mapping plan ID} {--run-id= : Stable classification reference} {--json : Write only structured output}')]
#[Description('Classify legacy financial proposals for rehearsal, reconciliation, quarantine, or authority review without executing migration.')]
class ClassifyLegacyFinancialMigrationEvidenceCommand extends Command
{
    public function handle(ClassifyLegacyFinancialMigrationEvidence $action): int
    {
        try {
            $runReference = $this->runReference();
            $plan = LegacyFinancialMappingPlan::query()->with('importBatch.source')->findOrFail($this->planId());
            $report = $action->handle($plan);
            $root = $this->writeEvidence($plan, $runReference, $report);
        } catch (Throwable $exception) {
            return $this->failCommand($exception->getMessage());
        }

        $counts = $report['summary']['disposition_counts'];
        $result = [
            'passed' => true,
            'run_id' => $runReference,
            'plan_id' => $plan->id,
            'classified' => $report['summary']['classified_count'],
            'dispositions' => $counts,
            'migration_executed' => false,
            'cutover_authorized' => false,
            'artifacts' => Storage::disk('local')->path($root),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->line('Financial migration classification: '.$runReference);
            $this->line('Classified proposals: '.$result['classified']);
            foreach ($counts as $disposition => $count) {
                $this->line("{$disposition}: {$count}");
            }
            $this->line('Migration executed: no');
            $this->line('Cutover authorized: no');
            $this->line('Artifacts: '.$result['artifacts']);
        }

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $report */
    private function writeEvidence(LegacyFinancialMappingPlan $plan, string $runReference, array $report): string
    {
        $root = "legacy-migrations/{$plan->importBatch->source->key}/{$plan->importBatch->run_reference}/reconciliation/financial-migration-classification/{$runReference}";
        $this->writeImmutable($root.'/classification.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        $this->writeImmutable($root.'/review.md', "# Historical Financial Migration Classification Review\n\nReviewer status: Pending\nReviewer:\nMunicipal authority / role:\nReviewed at:\nDecision reference:\nNotes:\n");

        return $root;
    }

    private function writeImmutable(string $path, string $contents): void
    {
        $disk = Storage::disk('local');
        if ($disk->exists($path)) {
            if (! hash_equals(hash('sha256', $contents), hash('sha256', $disk->get($path)))) {
                throw new RuntimeException('Stable financial migration classification run is already bound to different evidence.');
            }

            return;
        }
        if (! $disk->put($path, $contents)) {
            throw new RuntimeException('Financial migration classification evidence could not be written.');
        }
    }

    private function runReference(): string
    {
        $runReference = $this->option('run-id');
        if (! is_string($runReference) || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,99}$/', $runReference) !== 1) {
            throw new RuntimeException('A stable filesystem-safe --run-id is required.');
        }

        return $runReference;
    }

    private function planId(): int
    {
        $planId = filter_var($this->argument('plan'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! is_int($planId)) {
            throw new RuntimeException('The plan argument must be an exact positive financial mapping plan ID.');
        }

        return $planId;
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
