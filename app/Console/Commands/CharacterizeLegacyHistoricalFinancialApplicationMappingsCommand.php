<?php

namespace App\Console\Commands;

use App\Actions\CharacterizeLegacyHistoricalFinancialApplicationMappings;
use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyMappingPlan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:characterize-historical-financial-application-mappings
    {financial-plan : Exact completed financial mapping plan ID}
    {registry-plan : Exact completed registry mapping plan ID}
    {--run-id= : Stable characterization reference}
    {--json : Write only structured output}')]
#[Description('Characterize exact application-mapping readiness for strict historical-financial preservation candidates without accepting mappings or executing migration.')]
class CharacterizeLegacyHistoricalFinancialApplicationMappingsCommand extends Command
{
    public function handle(CharacterizeLegacyHistoricalFinancialApplicationMappings $action): int
    {
        try {
            $runId = $this->runId();
            $financialPlan = LegacyFinancialMappingPlan::query()->with('importBatch.source')->findOrFail($this->positiveInteger($this->argument('financial-plan')));
            $registryPlan = LegacyMappingPlan::query()->with('importBatch.source')->findOrFail($this->positiveInteger($this->argument('registry-plan')));
            $result = $action->handle($financialPlan, $registryPlan);
            $root = $this->writeEvidence($financialPlan, $runId, $result);
            $summary = [
                'passed' => true,
                'run_id' => $runId,
                'financial_plan_id' => $financialPlan->id,
                'registry_plan_id' => $registryPlan->id,
                ...$result['report']['summary'],
                'artifacts' => Storage::disk('local')->path($root),
            ];
        } catch (Throwable $exception) {
            return $this->failure($exception->getMessage());
        }

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->line('Historical financial application-mapping readiness: '.$runId);
            $this->line('Strict candidates: '.$summary['strict_preservation_candidate_count']);
            $this->line('Deterministic identity chains: '.$summary['deterministic_identity_chain_count']);
            foreach ($summary['classification_counts'] as $classification => $count) {
                $this->line("{$classification}: {$count}");
            }
            $this->line('Accepted mappings created: none');
            $this->line('Production rehearsal authorized: no');
            $this->line('Artifacts: '.$summary['artifacts']);
        }

        return self::SUCCESS;
    }

    /** @param array{report: array<string, mixed>, candidates: list<array<string, mixed>>, cohort: list<array<string, mixed>>} $result */
    private function writeEvidence(LegacyFinancialMappingPlan $plan, string $runId, array $result): string
    {
        $root = "legacy-migrations/{$plan->importBatch->source->key}/{$plan->importBatch->run_reference}/reconciliation/historical-financial-application-mapping-readiness/{$runId}";
        $candidateLines = implode('', array_map(
            fn (array $candidate): string => json_encode($candidate, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n",
            $result['candidates'],
        ));
        $this->writeImmutable($root.'/summary.json', json_encode($result['report'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        $this->writeImmutable($root.'/proposed-mappings.jsonl', $candidateLines);
        $this->writeImmutable($root.'/recommended-first-cohort.json', json_encode([
            'schema_version' => CharacterizeLegacyHistoricalFinancialApplicationMappings::SchemaVersion,
            'selection_rule' => 'First five preservation-compatible deterministic identity chains requiring at most accepted reference-data crosswalks, ordered by candidate fingerprint.',
            'acceptance_status' => 'pending',
            'production_rehearsal_authorized' => false,
            'candidates' => $result['cohort'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        $this->writeImmutable($root.'/review.md', "# Historical Financial Application Mapping Readiness Review\n\nReviewer status: Pending\nReviewer:\nMunicipal authority / role:\nReviewed at:\nDecision reference:\nNotes:\n");

        return $root;
    }

    private function writeImmutable(string $path, string $contents): void
    {
        $disk = Storage::disk('local');
        if ($disk->exists($path)) {
            if (! hash_equals(hash('sha256', $contents), hash('sha256', $disk->get($path)))) {
                throw new RuntimeException('Stable mapping-readiness run is already bound to different evidence.');
            }

            return;
        }
        if (! $disk->put($path, $contents)) {
            throw new RuntimeException('Mapping-readiness evidence could not be written.');
        }
    }

    private function runId(): string
    {
        $runId = $this->option('run-id');
        if (! is_string($runId) || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,99}$/', $runId) !== 1) {
            throw new RuntimeException('A stable filesystem-safe --run-id is required.');
        }

        return $runId;
    }

    private function positiveInteger(mixed $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! is_int($id)) {
            throw new RuntimeException('Plan arguments must be exact positive IDs.');
        }

        return $id;
    }

    private function failure(string $message): int
    {
        $this->line($this->option('json') ? json_encode(['passed' => false, 'error' => $message], JSON_THROW_ON_ERROR) : $message);

        return self::FAILURE;
    }
}
