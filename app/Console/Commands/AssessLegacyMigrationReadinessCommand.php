<?php

namespace App\Console\Commands;

use App\Actions\AssessLegacyMigrationReadiness;
use App\Models\LegacyImportBatch;
use App\Models\LegacyMigrationReadinessAssessment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:assess-readiness {batch : Exact legacy import batch ID} {--run-id= : Stable readiness assessment reference} {--gate=rehearsal : Required gate: rehearsal or cutover} {--json : Write only structured output}')]
#[Description('Assess legacy migration rehearsal and cutover readiness without executing migration or granting authority.')]
class AssessLegacyMigrationReadinessCommand extends Command
{
    public function handle(AssessLegacyMigrationReadiness $action): int
    {
        try {
            $run = $this->option('run-id');
            if (! is_string($run) || $run === '') {
                throw new RuntimeException('A stable --run-id is required.');
            }
            $gate = $this->option('gate');
            if (! is_string($gate) || ! in_array($gate, ['rehearsal', 'cutover'], true)) {
                throw new RuntimeException('The --gate option must be rehearsal or cutover.');
            }
            $batchId = filter_var($this->argument('batch'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (! is_int($batchId)) {
                throw new RuntimeException('The batch argument must be an exact positive legacy import batch ID.');
            }
            $assessment = $action->handle(LegacyImportBatch::query()->findOrFail($batchId), $run);
            $path = $this->writeEvidence($assessment);
        } catch (Throwable $exception) {
            return $this->failCommand($exception->getMessage());
        }

        $gatePassed = $gate === 'rehearsal' ? $assessment->rehearsal_ready : $assessment->cutover_ready;
        $result = [
            'passed' => $gatePassed,
            'requested_gate' => $gate,
            'run_id' => $assessment->run_reference,
            'assessment_id' => $assessment->id,
            'status' => $assessment->status->value,
            'rehearsal_ready' => $assessment->rehearsal_ready,
            'cutover_ready' => $assessment->cutover_ready,
            'checks' => $assessment->check_count,
            'passed_checks' => $assessment->passed_count,
            'blocked_checks' => $assessment->blocked_count,
            'migration_executed' => false,
            'cutover_authorized' => false,
            'artifacts' => Storage::disk('local')->path($path),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->line('Migration readiness assessment: '.$assessment->run_reference);
            $this->line('Rehearsal ready: '.($assessment->rehearsal_ready ? 'yes' : 'no'));
            $this->line('Cutover ready: '.($assessment->cutover_ready ? 'yes' : 'no'));
            $this->line("Checks: {$assessment->passed_count} passed / {$assessment->blocked_count} blocked");
            $this->line('Migration executed: no');
            $this->line('Cutover authorized: no');
            $this->line('Artifacts: '.Storage::disk('local')->path($path));
        }

        return $gatePassed ? self::SUCCESS : self::FAILURE;
    }

    private function writeEvidence(LegacyMigrationReadinessAssessment $assessment): string
    {
        $assessment->loadMissing('importBatch.source');
        $root = "legacy-migrations/{$assessment->importBatch->source->key}/{$assessment->importBatch->run_reference}/readiness-assessments/{$assessment->run_reference}";
        $report = [
            'schema_version' => 'bpls.legacy-migration-readiness-report.v1',
            'run_id' => $assessment->run_reference,
            'assessment_id' => $assessment->id,
            'dependency_snapshot_hash' => $assessment->dependency_snapshot_hash,
            'result' => [
                'status' => $assessment->status->value,
                'rehearsal_ready' => $assessment->rehearsal_ready,
                'cutover_ready' => $assessment->cutover_ready,
                'check_count' => $assessment->check_count,
                'passed_count' => $assessment->passed_count,
                'blocked_count' => $assessment->blocked_count,
            ],
            'checks' => $assessment->checks ?? [],
            'safety' => [
                'assessment_only' => true,
                'migration_executed' => false,
                'external_calls' => false,
                'production_mutation' => false,
                'cutover_authorized' => false,
                'domain_writes' => false,
            ],
            'completed_at' => $assessment->completed_at?->toIso8601String(),
        ];

        if (! Storage::disk('local')->put($root.'/readiness-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n")
            || ! Storage::disk('local')->put($root.'/review.md', "# Legacy Migration Readiness Review\n\nReviewer status: Pending\nReviewer:\nReviewed at:\nNotes:\n")) {
            throw new RuntimeException('Migration readiness evidence could not be written.');
        }

        return $root;
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
