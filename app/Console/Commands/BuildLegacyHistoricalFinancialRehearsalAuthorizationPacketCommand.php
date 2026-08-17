<?php

namespace App\Console\Commands;

use App\Actions\BuildLegacyHistoricalFinancialRehearsalAuthorizationPacket;
use App\Models\LegacyHistoricalFinancialMappingSet;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:build-five-record-historical-preservation-authorization-packet
    {mapping-set : Exact frozen accepted mapping-set ID}
    {--run-id= : Stable preservation planning reference}
    {--json : Write only structured output}')]
#[Description('Regenerate and freeze the payload-safe five-record historical preservation rehearsal authorization packet without executing it.')]
class BuildLegacyHistoricalFinancialRehearsalAuthorizationPacketCommand extends Command
{
    public function handle(BuildLegacyHistoricalFinancialRehearsalAuthorizationPacket $action): int
    {
        try {
            $runId = $this->runId();
            $mappingSet = LegacyHistoricalFinancialMappingSet::query()->findOrFail($this->positiveId($this->argument('mapping-set')));
            $result = $action->handle($mappingSet, $runId);
            $mappingSet->loadMissing('financialMappingPlan.importBatch.source');
            $batch = $mappingSet->financialMappingPlan->importBatch;
            $root = "legacy-migrations/{$batch->source->key}/{$batch->run_reference}/historical-financial-preservation-authorization/{$runId}";
            $this->writeImmutable($root.'/authorization-packet.json', json_encode($result['report'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
            $this->writeImmutable($root.'/authorization-packet.md', $this->markdown($result['report']));
            $report = $result['report'];
            $report['artifacts'] = Storage::disk('local')->path($root);
        } catch (Throwable $exception) {
            $this->line($this->option('json') ? json_encode(['passed' => false, 'error' => $exception->getMessage()], JSON_THROW_ON_ERROR) : $exception->getMessage());

            return self::FAILURE;
        }

        $this->line($this->option('json')
            ? json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            : "{$report['recommendation']}\nCohort: {$report['frozen_cohort_sha256']}\nMapping set: {$report['frozen_accepted_mapping_set_sha256']}\nArtifacts: {$report['artifacts']}");

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $report */
    private function markdown(array $report): string
    {
        $totals = $report['expected_totals'];
        $commands = $report['proposed_commands_not_executed'];

        return "# Five-Record Historical Preservation Rehearsal Authorization Packet\n\n"
            ."Status: **{$report['recommendation']}**\n\n"
            ."This packet requests authorization only. No production-derived historical preservation rehearsal was executed.\n\n"
            ."## Fingerprints\n\n"
            ."- Frozen cohort SHA-256: `{$report['frozen_cohort_sha256']}`\n"
            ."- Accepted mapping-set SHA-256: `{$report['frozen_accepted_mapping_set_sha256']}`\n"
            ."- Proposal package SHA-256: `{$report['proposal_package_sha256']}`\n"
            ."- Preservation dependency SHA-256: `{$report['preservation_dependency_snapshot_sha256']}`\n\n"
            ."## Exact Expected Totals\n\n"
            ."- Historical bundles: {$totals['historical_bundle_count']}\n"
            ."- Schedules: {$totals['schedule_count']}\n"
            ."- Fee lines: {$totals['fee_line_count']}\n"
            ."- Completed payments: {$totals['completed_payment_count']}\n"
            ."- Unpaid schedules: {$totals['unpaid_schedule_count']}\n"
            ."- Scheduled centavos: {$totals['scheduled_amount_cents']}\n"
            ."- Fee centavos: {$totals['fee_amount_cents']}\n"
            ."- Paid centavos: {$totals['paid_amount_cents']}\n"
            ."- Payment centavos: {$totals['payment_amount_cents']}\n\n"
            ."## Proposed Commands (Not Executed)\n\n```bash\n{$commands['execute']}\n{$commands['audit']}\n{$commands['rollback']}\n{$commands['restoration_audit']}\n```\n\n"
            ."The JSON packet in this directory is the authoritative assertion and fail-closed checklist.\n";
    }

    private function writeImmutable(string $path, string $contents): void
    {
        $disk = Storage::disk('local');
        if ($disk->exists($path)) {
            if (! hash_equals(hash('sha256', $contents), hash('sha256', $disk->get($path)))) {
                throw new RuntimeException('Stable authorization-packet run is already bound to different evidence.');
            }

            return;
        }
        if (! $disk->put($path, $contents)) {
            throw new RuntimeException('Authorization packet could not be written.');
        }
    }

    private function positiveId(mixed $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! is_int($id)) {
            throw new RuntimeException('Mapping-set argument must be an exact positive ID.');
        }

        return $id;
    }

    private function runId(): string
    {
        $value = $this->option('run-id');
        if (! is_string($value) || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,99}$/', $value) !== 1) {
            throw new RuntimeException('A stable filesystem-safe --run-id is required.');
        }

        return $value;
    }
}
