<?php

namespace App\Console\Commands;

use App\Actions\CharacterizeLegacyHistoricalFinancialCohortPrerequisites;
use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyMappingPlan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:characterize-historical-financial-cohort-prerequisites
    {financial-plan : Exact completed financial mapping plan ID}
    {registry-plan : Exact completed registry mapping plan ID}
    {--cohort-sha256= : Expected frozen five-record cohort SHA-256}
    {--run-id= : Stable characterization reference}
    {--json : Write only structured output}')]
#[Description('Prepare source-backed location, line-of-business, and exact application-mapping proposals for the frozen historical-financial cohort without accepting them.')]
class CharacterizeLegacyHistoricalFinancialCohortPrerequisitesCommand extends Command
{
    public function handle(CharacterizeLegacyHistoricalFinancialCohortPrerequisites $action): int
    {
        try {
            $runId = $this->runId();
            $cohortFingerprint = $this->cohortFingerprint();
            $financialPlan = LegacyFinancialMappingPlan::query()->with('importBatch.source')->findOrFail($this->positiveInteger($this->argument('financial-plan')));
            $registryPlan = LegacyMappingPlan::query()->with('importBatch.source')->findOrFail($this->positiveInteger($this->argument('registry-plan')));
            $result = $action->handle($financialPlan, $registryPlan, $cohortFingerprint);
            $root = $this->writeEvidence($financialPlan, $runId, $result);
            $summary = [
                'passed' => true,
                'run_id' => $runId,
                'financial_plan_id' => $financialPlan->id,
                'registry_plan_id' => $registryPlan->id,
                ...$result['report']['summary'],
                ...$result['report']['fingerprints'],
                'artifacts' => Storage::disk('local')->path($root),
            ];
        } catch (Throwable $exception) {
            return $this->failure($exception->getMessage());
        }

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->line('Historical financial cohort prerequisites: '.$runId);
            $this->line('Frozen cohort: '.$summary['cohort_size']);
            $this->line('Exact location hierarchies: '.$summary['exact_location_hierarchy_count']);
            $this->line('Exact line-of-business hierarchies: '.$summary['exact_legacy_group_hierarchy_count']);
            $this->line('Evidence-complete proposals pending acceptance: '.$summary['evidence_complete_acceptance_pending_count']);
            $this->line('Accepted mappings created: none');
            $this->line('Production rehearsal authorized: no');
            $this->line('Artifacts: '.$summary['artifacts']);
        }

        return self::SUCCESS;
    }

    /**
     * @param array{
     *   report: array<string, mixed>,
     *   location_proposals: list<array<string, mixed>>,
     *   line_of_business_proposals: list<array<string, mixed>>,
     *   exact_mapping_proposals: list<array<string, mixed>>
     * } $result
     */
    private function writeEvidence(LegacyFinancialMappingPlan $plan, string $runId, array $result): string
    {
        $root = "legacy-migrations/{$plan->importBatch->source->key}/{$plan->importBatch->run_reference}/reconciliation/historical-financial-application-mapping-prerequisites/{$runId}";
        $this->writeImmutable($root.'/summary.json', $this->json($result['report']));
        $this->writeImmutable($root.'/proposed-location-crosswalks.jsonl', $this->jsonLines($result['location_proposals']));
        $this->writeImmutable($root.'/proposed-line-of-business-targets.jsonl', $this->jsonLines($result['line_of_business_proposals']));
        $this->writeImmutable($root.'/proposed-exact-application-mappings.jsonl', $this->jsonLines($result['exact_mapping_proposals']));
        $this->writeImmutable($root.'/review.md', "# Historical Financial Cohort Prerequisite Review\n\nReviewer status: Pending\nReviewer:\nMunicipal authority / role:\nReviewed at:\nDecision reference:\n\n## Proposed decisions\n\n- Location disposition: Pending\n- Line-of-business target definitions: Pending\n- Line-of-business reconciliations: Pending\n- Exact owner/business/application mappings: Pending\n- Production-derived rehearsal authorization: Not requested\n\nNotes:\n");

        return $root;
    }

    /** @param list<array<string, mixed>> $rows */
    private function jsonLines(array $rows): string
    {
        return implode('', array_map(
            fn (array $row): string => json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n",
            $rows,
        ));
    }

    /** @param array<string, mixed> $value */
    private function json(array $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    }

    private function writeImmutable(string $path, string $contents): void
    {
        $disk = Storage::disk('local');
        if ($disk->exists($path)) {
            if (! hash_equals(hash('sha256', $contents), hash('sha256', $disk->get($path)))) {
                throw new RuntimeException('Stable cohort-prerequisite run is already bound to different evidence.');
            }

            return;
        }
        if (! $disk->put($path, $contents)) {
            throw new RuntimeException('Cohort-prerequisite evidence could not be written.');
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

    private function cohortFingerprint(): string
    {
        $fingerprint = $this->option('cohort-sha256');
        if (! is_string($fingerprint) || preg_match('/^[a-f0-9]{64}$/', $fingerprint) !== 1) {
            throw new RuntimeException('A lowercase hexadecimal --cohort-sha256 is required.');
        }

        return $fingerprint;
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
