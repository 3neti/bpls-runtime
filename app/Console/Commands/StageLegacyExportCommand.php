<?php

namespace App\Console\Commands;

use App\Actions\StageLegacyExport;
use App\Enums\LegacyImportBatchStatus;
use App\Models\LegacyImportBatch;
use App\Models\LegacyMigrationException;
use App\Models\MigrationValidationResult;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:stage
    {manifest : Absolute or working-directory-relative path to the versioned staging manifest}
    {--run-id= : Stable operator-provided run reference}
    {--json : Write only structured output}')]
#[Description('Stage a checksum-verified legacy JSONL export without changing BPLS domain records.')]
class StageLegacyExportCommand extends Command
{
    public function handle(StageLegacyExport $stageLegacyExport): int
    {
        if (! app()->environment(['local', 'testing'])) {
            return $this->failCommand('Legacy staging is currently restricted to local and testing environments.');
        }

        $runReference = $this->option('run-id');

        if (! is_string($runReference) || $runReference === '') {
            return $this->failCommand('A stable --run-id is required.');
        }

        try {
            $batch = $stageLegacyExport->handle((string) $this->argument('manifest'), $runReference);
            $artifactPath = $this->writeEvidence($batch);
        } catch (Throwable $exception) {
            return $this->failCommand($exception->getMessage());
        }

        $passed = $batch->status !== LegacyImportBatchStatus::Failed;
        $result = [
            'passed' => $passed,
            'run_id' => $batch->run_reference,
            'batch_id' => $batch->id,
            'source' => $batch->source->key,
            'status' => $batch->status->value,
            'source_records' => $batch->source_record_count,
            'staged_records' => $batch->staged_record_count,
            'exceptions' => $batch->exception_count,
            'mappings' => $batch->mapping_count,
            'domain_writes' => false,
            'artifacts' => Storage::disk('local')->path($artifactPath),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->line('Legacy source: '.$batch->source->key);
            $this->line('Run ID: '.$batch->run_reference);
            $this->line('Batch: '.$batch->id);
            $this->line('Status: '.$batch->status->value);
            $this->line("Records: {$batch->staged_record_count} staged / {$batch->source_record_count} source");
            $this->line('Exceptions: '.$batch->exception_count);
            $this->line('Mappings: 0');
            $this->line('Domain writes: none');
            $this->line('Artifacts: '.Storage::disk('local')->path($artifactPath));
        }

        return $passed ? self::SUCCESS : self::FAILURE;
    }

    private function writeEvidence(LegacyImportBatch $batch): string
    {
        $batch->loadMissing(['source', 'validationResults', 'exceptions']);
        $root = "legacy-migrations/{$batch->source->key}/{$batch->run_reference}";
        $report = [
            'schema_version' => 'bpls.legacy-staging-report.v1',
            'run_id' => $batch->run_reference,
            'batch_id' => $batch->id,
            'source' => [
                'key' => $batch->source->key,
                'title' => $batch->source->title,
                'baseline' => $batch->source->baseline,
                'archive_checksum' => $batch->source->archive_checksum,
            ],
            'manifest' => [
                'schema_version' => $batch->manifest_schema_version,
                'checksum' => $batch->manifest_checksum,
                'filename' => $batch->metadata['manifest_filename'] ?? null,
            ],
            'result' => [
                'status' => $batch->status->value,
                'source_record_count' => $batch->source_record_count,
                'staged_record_count' => $batch->staged_record_count,
                'exception_count' => $batch->exception_count,
                'mapping_count' => $batch->mapping_count,
                'domain_writes' => false,
            ],
            'validations' => $batch->validationResults->map(fn (MigrationValidationResult $validation): array => [
                'dataset_key' => $validation->dataset_key,
                'check_key' => $validation->check_key,
                'status' => $validation->status->value,
                'expected' => $validation->expected,
                'actual' => $validation->actual,
                'details' => $validation->details,
            ])->values()->all(),
            'exceptions' => $batch->exceptions->map(fn (LegacyMigrationException $exception): array => [
                'id' => $exception->id,
                'dataset_key' => $exception->dataset_key,
                'line_number' => $exception->line_number,
                'code' => $exception->code,
                'severity' => $exception->severity->value,
                'status' => $exception->status->value,
                'message' => $exception->message,
                'context' => $this->sanitizedExceptionContext($exception->context),
            ])->values()->all(),
            'safety' => [
                'payloads_in_report' => false,
                'domain_writes' => false,
                'external_integrations' => false,
            ],
            'completed_at' => $batch->completed_at?->toIso8601String(),
        ];

        $reportWritten = Storage::disk('local')->put($root.'/report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        $reviewWritten = Storage::disk('local')->put($root.'/review.md', "# Legacy Migration Staging Review\n\nReviewer status: Pending\nReviewer:\nReviewed at:\nNotes:\n");

        if (! $reportWritten || ! $reviewWritten) {
            throw new RuntimeException('Legacy staging evidence could not be written to private storage.');
        }

        return $root;
    }

    /**
     * @param  array<string, mixed>|null  $context
     * @return array<string, mixed>|null
     */
    private function sanitizedExceptionContext(?array $context): ?array
    {
        if ($context === null) {
            return null;
        }

        return Arr::only($context, [
            'actual',
            'expected',
            'file',
            'first_line_number',
            'identity_field',
            'json_error',
            'legacy_id_sha256',
            'payload_sha256',
            'raw_sha256',
        ]);
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
