<?php

namespace App\Console\Commands;

use App\Actions\CharacterizeLegacyHistoricalFinancialNextScaleReadiness;
use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyHistoricalFinancialMappingSet;
use App\Models\LegacyHistoricalFinancialPreservationExecution;
use App\Models\LegacyMappingPlan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:characterize-historical-financial-next-scale-readiness
    {financial-plan : Exact completed financial mapping plan ID}
    {registry-plan : Exact completed registry mapping plan ID}
    {mapping-set : Exact frozen five-record accepted mapping-set ID}
    {baseline-execution : Exact permanently rolled-back five-record execution ID}
    {--source-sha256= : Expected immutable source snapshot SHA-256}
    {--baseline-cohort-sha256= : Expected proven five-record cohort SHA-256}
    {--baseline-mapping-set-sha256= : Expected accepted mapping-set SHA-256}
    {--baseline-dependency-sha256= : Expected proven preservation dependency SHA-256}
    {--run-id= : Stable next-scale characterization reference}
    {--json : Write only structured output}')]
#[Description('Determine whether a materially larger historical-financial cohort exists without introducing semantics beyond the proven five-record rehearsal.')]
class CharacterizeLegacyHistoricalFinancialNextScaleReadinessCommand extends Command
{
    public function handle(CharacterizeLegacyHistoricalFinancialNextScaleReadiness $action): int
    {
        try {
            $runId = $this->runId();
            $financialPlan = LegacyFinancialMappingPlan::query()->with('importBatch.source')->findOrFail($this->positiveInteger($this->argument('financial-plan')));
            $registryPlan = LegacyMappingPlan::query()->findOrFail($this->positiveInteger($this->argument('registry-plan')));
            $mappingSet = LegacyHistoricalFinancialMappingSet::query()->findOrFail($this->positiveInteger($this->argument('mapping-set')));
            $baselineExecution = LegacyHistoricalFinancialPreservationExecution::query()->with('preservationPlan')->findOrFail($this->positiveInteger($this->argument('baseline-execution')));
            $result = $action->handle(
                $financialPlan,
                $registryPlan,
                $mappingSet,
                $baselineExecution,
                $this->fingerprint('source-sha256'),
                $this->fingerprint('baseline-cohort-sha256'),
                $this->fingerprint('baseline-mapping-set-sha256'),
                $this->fingerprint('baseline-dependency-sha256'),
            );
            $root = $this->writeEvidence($financialPlan, $runId, $result);
            $summary = [
                'passed' => true,
                'run_id' => $runId,
                ...$result['report']['summary'],
                'decision' => $result['report']['decision'],
                'artifacts' => Storage::disk('local')->path($root),
            ];
        } catch (Throwable $exception) {
            return $this->failure($exception->getMessage());
        }

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->line('Historical financial next-scale readiness: '.$runId);
            $this->line('Same-semantic candidates: '.$summary['same_semantic_candidate_count']);
            $this->line('Unused same-semantic candidates: '.$summary['same_semantic_expansion_count']);
            $this->line('Materially larger cohort available: '.($summary['materially_larger_cohort_available'] ? 'yes' : 'no'));
            $this->line('Recommendation: '.$summary['decision']['recommendation']);
            $this->line('Artifacts: '.$summary['artifacts']);
        }

        return self::SUCCESS;
    }

    /** @param array{report: array<string, mixed>, same_semantic_candidates: list<array<string, mixed>>, expansion_candidates: list<array<string, mixed>>} $result */
    private function writeEvidence(LegacyFinancialMappingPlan $plan, string $runId, array $result): string
    {
        $root = "legacy-migrations/{$plan->importBatch->source->key}/{$plan->importBatch->run_reference}/reconciliation/historical-financial-next-scale-readiness/{$runId}";
        $this->writeImmutable($root.'/summary.json', json_encode($result['report'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        $this->writeImmutable($root.'/same-semantic-candidates.jsonl', $this->jsonLines($result['same_semantic_candidates']));
        $this->writeImmutable($root.'/expansion-candidates.jsonl', $this->jsonLines($result['expansion_candidates']));
        $this->writeImmutable($root.'/review.md', "# Next-Scale Historical Preservation Readiness Review\n\nReviewer status: Pending\nReviewer:\nReviewed at:\nDecision reference:\nNotes:\n");

        return $root;
    }

    /** @param list<array<string, mixed>> $items */
    private function jsonLines(array $items): string
    {
        return implode('', array_map(
            fn (array $item): string => json_encode($item, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n",
            $items,
        ));
    }

    private function writeImmutable(string $path, string $contents): void
    {
        $disk = Storage::disk('local');
        if ($disk->exists($path)) {
            if (! hash_equals(hash('sha256', $contents), hash('sha256', $disk->get($path)))) {
                throw new RuntimeException('Stable next-scale readiness run is already bound to different evidence.');
            }

            return;
        }
        if (! $disk->put($path, $contents)) {
            throw new RuntimeException('Next-scale readiness evidence could not be written.');
        }
    }

    private function fingerprint(string $option): string
    {
        $value = $this->option($option);
        if (! is_string($value) || preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            throw new RuntimeException("A lowercase hexadecimal --{$option} is required.");
        }

        return $value;
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
            throw new RuntimeException('Plan and mapping-set arguments must be exact positive IDs.');
        }

        return $id;
    }

    private function failure(string $message): int
    {
        $this->line(json_encode(['passed' => false, 'error' => $message], JSON_THROW_ON_ERROR));

        return self::FAILURE;
    }
}
