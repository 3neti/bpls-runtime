<?php

namespace App\Console\Commands;

use App\Actions\AcceptLegacyHistoricalFinancialCohortMappings;
use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyMappingPlan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:accept-historical-financial-cohort-mappings
    {financial-plan : Exact completed financial mapping plan ID}
    {registry-plan : Exact completed registry mapping plan ID}
    {--cohort-sha256= : Board-accepted frozen cohort SHA-256}
    {--proposal-package-sha256= : Board-accepted prerequisite package SHA-256}
    {--run-id= : Stable acceptance reference}
    {--authority= : Decision authority}
    {--evidence= : Decision evidence reference}
    {--accept : Confirm prerequisite acceptance}
    {--confirm-accept : Second explicit confirmation}
    {--json : Write only structured output}')]
#[Description('Accept and freeze the exact Board-authorized five-record mapping prerequisites without executing historical finance preservation.')]
class AcceptLegacyHistoricalFinancialCohortMappingsCommand extends Command
{
    public function handle(AcceptLegacyHistoricalFinancialCohortMappings $action): int
    {
        try {
            if (! $this->option('accept') || ! $this->option('confirm-accept')) {
                throw new RuntimeException('Both --accept and --confirm-accept are required.');
            }
            $financialPlan = LegacyFinancialMappingPlan::query()->with('importBatch.source')->findOrFail($this->positiveId($this->argument('financial-plan')));
            $registryPlan = LegacyMappingPlan::query()->with('importBatch.source')->findOrFail($this->positiveId($this->argument('registry-plan')));
            $mappingSet = $action->handle(
                $financialPlan,
                $registryPlan,
                $this->fingerprint('cohort-sha256'),
                $this->fingerprint('proposal-package-sha256'),
                $this->requiredOption('run-id'),
                $this->requiredOption('authority'),
                $this->requiredOption('evidence'),
            );
            $root = "legacy-migrations/{$financialPlan->importBatch->source->key}/{$financialPlan->importBatch->run_reference}/reconciliation/historical-financial-application-mapping-acceptance/{$mappingSet->run_reference}";
            $report = [
                'schema_version' => AcceptLegacyHistoricalFinancialCohortMappings::SchemaVersion,
                'passed' => true,
                'mapping_set_id' => $mappingSet->id,
                'run_id' => $mappingSet->run_reference,
                'status' => $mappingSet->status,
                'cohort_size' => $mappingSet->cohort_size,
                'cohort_sha256' => $mappingSet->cohort_sha256,
                'proposal_package_sha256' => $mappingSet->proposal_package_sha256,
                'accepted_mapping_set_sha256' => $mappingSet->accepted_mapping_set_sha256,
                'counts' => [
                    'line_of_business_targets' => count((array) data_get($mappingSet->manifest, 'line_of_business_targets', [])),
                    'registry_mappings' => count((array) data_get($mappingSet->manifest, 'registry_mappings', [])),
                    'application_mappings' => count((array) data_get($mappingSet->manifest, 'application_mappings', [])),
                ],
                'safety' => $mappingSet->metadata,
            ];
            $this->writeImmutable($root.'/accepted-mapping-set.json', $report);
            $report['artifacts'] = Storage::disk('local')->path($root);
        } catch (Throwable $exception) {
            $this->line($this->option('json') ? json_encode(['passed' => false, 'error' => $exception->getMessage()], JSON_THROW_ON_ERROR) : $exception->getMessage());

            return self::FAILURE;
        }

        $this->line($this->option('json')
            ? json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            : "Historical mapping set: {$mappingSet->accepted_mapping_set_sha256}\nApplications: {$mappingSet->cohort_size}\nHistorical preservation executed: no\nArtifacts: {$report['artifacts']}");

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $report */
    private function writeImmutable(string $path, array $report): void
    {
        $contents = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        $disk = Storage::disk('local');
        if ($disk->exists($path)) {
            if (! hash_equals(hash('sha256', $contents), hash('sha256', $disk->get($path)))) {
                throw new RuntimeException('Acceptance evidence path is already bound to different content.');
            }

            return;
        }
        if (! $disk->put($path, $contents)) {
            throw new RuntimeException('Accepted mapping-set evidence could not be written.');
        }
    }

    private function positiveId(mixed $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! is_int($id)) {
            throw new RuntimeException('Plan arguments must be exact positive IDs.');
        }

        return $id;
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
