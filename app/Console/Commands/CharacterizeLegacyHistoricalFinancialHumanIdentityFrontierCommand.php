<?php

namespace App\Console\Commands;

use App\Actions\CharacterizeLegacyHistoricalFinancialHumanIdentityFrontier;
use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyMappingPlan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:characterize-historical-financial-human-identities
    {financial-plan : Exact completed financial mapping plan ID}
    {registry-plan : Exact completed registry mapping plan ID}
    {--run-id= : Stable characterization reference}
    {--json : Write only structured output}')]
#[Description('Classify human-identity migration cases without accepting mappings or using similarity as identity authority.')]
class CharacterizeLegacyHistoricalFinancialHumanIdentityFrontierCommand extends Command
{
    public function handle(CharacterizeLegacyHistoricalFinancialHumanIdentityFrontier $action): int
    {
        try {
            $runId = $this->requiredOption('run-id');
            $financialPlan = LegacyFinancialMappingPlan::query()->with('importBatch.source')->findOrFail($this->positiveInteger($this->argument('financial-plan')));
            $registryPlan = LegacyMappingPlan::query()->with('importBatch.source')->findOrFail($this->positiveInteger($this->argument('registry-plan')));
            $result = $action->handle($financialPlan, $registryPlan);
            $root = "legacy-migrations/{$financialPlan->importBatch->source->key}/{$financialPlan->importBatch->run_reference}/reconciliation/historical-financial-human-identity-frontier/{$runId}";
            $this->writeImmutable($root.'/summary.json', json_encode($result['report'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
            $this->writeImmutable($root.'/classes.json', json_encode([
                'schema_version' => CharacterizeLegacyHistoricalFinancialHumanIdentityFrontier::SchemaVersion,
                'acceptance_status' => 'not_accepted',
                'classes' => $result['classes'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
            $this->writeImmutable($root.'/candidate-membership.jsonl', implode('', array_map(
                fn (array $candidate): string => json_encode($candidate, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n",
                $result['candidates'],
            )));
            $this->writeImmutable($root.'/review.md', "# Human Identity Frontier Review\n\nReviewer status: Pending\nReviewer:\nReviewed at:\nDecision reference:\nNotes:\n");
            $summary = [
                'passed' => true,
                'run_id' => $runId,
                ...$result['report']['summary'],
                'fingerprints' => $result['report']['fingerprints'],
                'artifacts' => Storage::disk('local')->path($root),
            ];
        } catch (Throwable $exception) {
            $this->line(json_encode(['passed' => false, 'error' => $exception->getMessage()], JSON_THROW_ON_ERROR));

            return self::FAILURE;
        }

        $this->line($this->option('json')
            ? json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            : "Human identity cases: {$summary['human_identity_application_count']}\nAccepted mappings: none\nArtifacts: {$summary['artifacts']}");

        return self::SUCCESS;
    }

    private function writeImmutable(string $path, string $contents): void
    {
        $disk = Storage::disk('local');
        if ($disk->exists($path)) {
            if (! hash_equals(hash('sha256', $contents), hash('sha256', $disk->get($path)))) {
                throw new RuntimeException('Stable human-identity frontier path is already bound to different evidence.');
            }

            return;
        }
        if (! $disk->put($path, $contents)) {
            throw new RuntimeException('Human-identity frontier evidence could not be written.');
        }
    }

    private function positiveInteger(mixed $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! is_int($id)) {
            throw new RuntimeException('Plan arguments must be exact positive IDs.');
        }

        return $id;
    }

    private function requiredOption(string $option): string
    {
        $value = $this->option($option);
        if (! is_string($value) || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,99}$/', $value) !== 1) {
            throw new RuntimeException("A stable filesystem-safe --{$option} is required.");
        }

        return $value;
    }
}
