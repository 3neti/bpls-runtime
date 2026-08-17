<?php

namespace App\Console\Commands;

use App\Actions\AcceptLegacyHistoricalFinancialCohortMappings;
use App\Actions\PrepareLegacyHistoricalReleaseCohort;
use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyMappingPlan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:prepare-historical-release-cohort
    {financial-plan : Exact completed financial mapping plan ID}
    {registry-plan : Exact completed registry mapping plan ID}
    {--cohort-size=25 : Requested bounded cohort size from the largest coherent financial topology}
    {--source-status=Released : Exact supported legacy source status to preserve as historical evidence}
    {--exclude-accepted-mappings : Select only candidates without an accepted application mapping}
    {--expected-class-sha256= : Expected complete historical Released class SHA-256}
    {--run-id= : Stable evidence reference}
    {--accept : Accept exact registry/application mappings after preparation}
    {--confirm-accept : Second explicit mapping acceptance confirmation}
    {--authority= : Mapping acceptance authority}
    {--evidence= : Mapping acceptance evidence reference}
    {--json : Write only structured output}')]
#[Description('Prepare and optionally accept an exact evidence-preserving historical Released cohort without asserting current release authority.')]
class PrepareLegacyHistoricalReleaseCohortCommand extends Command
{
    public function handle(
        PrepareLegacyHistoricalReleaseCohort $prepare,
        AcceptLegacyHistoricalFinancialCohortMappings $accept,
    ): int {
        try {
            $financialPlan = LegacyFinancialMappingPlan::query()->with('importBatch.source')->findOrFail($this->positiveInteger($this->argument('financial-plan')));
            $registryPlan = LegacyMappingPlan::query()->with('importBatch.source')->findOrFail($this->positiveInteger($this->argument('registry-plan')));
            $runId = $this->requiredOption('run-id');
            $prepared = $prepare->handle(
                $financialPlan,
                $registryPlan,
                $this->positiveInteger($this->option('cohort-size')),
                (bool) $this->option('exclude-accepted-mappings'),
                $this->requiredOption('source-status'),
            );
            $actualClass = (string) data_get($prepared, 'report.fingerprints.historical_release_class_sha256');
            $expectedClass = $this->option('expected-class-sha256');
            if (is_string($expectedClass) && trim($expectedClass) !== '' && ! hash_equals($this->fingerprint('expected-class-sha256'), $actualClass)) {
                throw new RuntimeException('The complete historical Released candidate class fingerprint changed.');
            }

            $root = "legacy-migrations/{$financialPlan->importBatch->source->key}/{$financialPlan->importBatch->run_reference}/reconciliation/historical-release-evidence-cohorts/{$runId}";
            $this->writeImmutable($root.'/preparation.json', $prepared);
            $mappingSet = null;
            if ($this->option('accept') || $this->option('confirm-accept')) {
                if (! $this->option('accept') || ! $this->option('confirm-accept')) {
                    throw new RuntimeException('Both --accept and --confirm-accept are required for mapping writes.');
                }
                if (! is_string($expectedClass) || trim($expectedClass) === '') {
                    throw new RuntimeException('--expected-class-sha256 is required for mapping acceptance.');
                }
                $mappingSet = $accept->handlePreparedEvidence(
                    $financialPlan,
                    $registryPlan,
                    $prepared,
                    (string) data_get($prepared, 'report.fingerprints.selected_cohort_sha256'),
                    (string) data_get($prepared, 'report.fingerprints.prerequisite_proposals_sha256'),
                    $runId.'-mapping-acceptance',
                    $this->requiredOption('authority'),
                    $this->requiredOption('evidence'),
                    'historical_evidence',
                );
                $this->writeImmutable($root.'/accepted-mapping-set.json', [
                    'schema_version' => 'bpls.historical-release-evidence-mapping-acceptance.v1',
                    'mapping_set_id' => $mappingSet->id,
                    'cohort_size' => $mappingSet->cohort_size,
                    'cohort_sha256' => $mappingSet->cohort_sha256,
                    'accepted_mapping_set_sha256' => $mappingSet->accepted_mapping_set_sha256,
                    'application_projection_mode' => data_get($mappingSet->metadata, 'application_projection_mode'),
                    'current_release_authorized' => false,
                    'operational_financial_writes' => false,
                ]);
            }

            $result = [
                'passed' => true,
                'run_id' => $runId,
                ...$prepared['report']['summary'],
                'fingerprints' => $prepared['report']['fingerprints'],
                'mapping_set_id' => $mappingSet?->id,
                'accepted_mapping_set_sha256' => $mappingSet?->accepted_mapping_set_sha256,
                'artifacts' => Storage::disk('local')->path($root),
            ];
        } catch (Throwable $exception) {
            $this->line(json_encode(['passed' => false, 'error' => $exception->getMessage()], JSON_THROW_ON_ERROR));

            return self::FAILURE;
        }

        $this->line($this->option('json')
            ? json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            : "Historical release cohort: {$result['cohort_size']}\nCurrent release authority: none\nArtifacts: {$result['artifacts']}");

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $evidence */
    private function writeImmutable(string $path, array $evidence): void
    {
        $contents = json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        $disk = Storage::disk('local');
        if ($disk->exists($path)) {
            if (! hash_equals(hash('sha256', $contents), hash('sha256', $disk->get($path)))) {
                throw new RuntimeException('Stable historical release evidence path is already bound to different content.');
            }

            return;
        }
        if (! $disk->put($path, $contents)) {
            throw new RuntimeException('Historical release cohort evidence could not be written.');
        }
    }

    private function positiveInteger(mixed $value): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! is_int($integer)) {
            throw new RuntimeException('Plan IDs and cohort size must be positive integers.');
        }

        return $integer;
    }

    private function fingerprint(string $option): string
    {
        $value = $this->requiredOption($option);
        if (preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            throw new RuntimeException("--{$option} must be a lowercase SHA-256 value.");
        }

        return $value;
    }

    private function requiredOption(string $option): string
    {
        $value = $this->option($option);
        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("--{$option} is required.");
        }

        return trim($value);
    }
}
