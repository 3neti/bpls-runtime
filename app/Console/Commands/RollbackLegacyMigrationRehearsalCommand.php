<?php

namespace App\Console\Commands;

use App\Actions\RollbackLegacyMigrationRehearsal;
use App\Models\LegacyMigrationRehearsal;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:rollback-migration-rehearsal
    {rehearsal : Exact verified migration rehearsal ID}
    {--rollback : Confirm dependency-reverse rollback through the existing domain rollback actions}
    {--confirm-rollback : Second explicit rollback confirmation}
    {--json : Write only structured output}')]
#[Description('Rollback a verified local migration rehearsal through the existing domain rollback actions.')]
class RollbackLegacyMigrationRehearsalCommand extends Command
{
    public function handle(RollbackLegacyMigrationRehearsal $action): int
    {
        try {
            if (! $this->option('rollback') || ! $this->option('confirm-rollback')) {
                throw new RuntimeException('Both --rollback and --confirm-rollback are required.');
            }
            $id = filter_var($this->argument('rehearsal'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (! is_int($id)) {
                throw new RuntimeException('The rehearsal argument must be an exact positive ID.');
            }

            $rehearsal = $action->handle(LegacyMigrationRehearsal::query()->findOrFail($id));
            $artifactPath = $this->writeEvidence($rehearsal);
        } catch (Throwable $exception) {
            return $this->failCommand($exception->getMessage());
        }

        $result = [
            'passed' => true,
            'run_id' => $rehearsal->run_reference,
            'rehearsal_id' => $rehearsal->id,
            'status' => $rehearsal->status->value,
            'completed_phases' => $rehearsal->metadata['rollback_completed_phases'] ?? [],
            'pre_existing_targets_deleted' => false,
            'artifacts' => Storage::disk('local')->path($artifactPath),
        ];
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->line('Migration rehearsal: '.$rehearsal->run_reference);
            $this->line('Status: '.$rehearsal->status->value);
            $this->line('Rollback phases: '.implode(', ', $rehearsal->metadata['rollback_completed_phases'] ?? []));
            $this->line('Pre-existing targets deleted: no');
            $this->line('Artifacts: '.Storage::disk('local')->path($artifactPath));
        }

        return self::SUCCESS;
    }

    private function writeEvidence(LegacyMigrationRehearsal $rehearsal): string
    {
        $rehearsal->loadMissing('importBatch.source');
        $root = "legacy-migrations/{$rehearsal->importBatch->source->key}/{$rehearsal->importBatch->run_reference}/rehearsals/{$rehearsal->run_reference}";
        $report = [
            'schema_version' => 'bpls.legacy-migration-rehearsal-rollback.v1',
            'run_id' => $rehearsal->run_reference,
            'rehearsal_id' => $rehearsal->id,
            'status' => $rehearsal->status->value,
            'completed_phases' => $rehearsal->metadata['rollback_completed_phases'] ?? [],
            'rollback_complete' => $rehearsal->metadata['rollback_complete'] ?? false,
            'pre_existing_targets_deleted' => false,
            'external_integrations' => false,
            'irreversible_actions' => false,
            'personal_data_in_report' => false,
            'rolled_back_at' => $rehearsal->rolled_back_at?->toIso8601String(),
        ];
        if (! Storage::disk('local')->put($root.'/rollback.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n")) {
            throw new RuntimeException('Migration rehearsal rollback evidence could not be written to private storage.');
        }

        return $root;
    }

    private function failCommand(string $message): int
    {
        if ($this->option('json')) {
            $this->line(json_encode(['passed' => false, 'error' => $message], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->error($message);
        }

        return self::FAILURE;
    }
}
