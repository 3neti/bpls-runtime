<?php

namespace App\Actions;

use App\Enums\LegacyImportBatchStatus;
use App\Enums\MigrationExceptionSeverity;
use App\Enums\MigrationExceptionStatus;
use App\Enums\MigrationValidationStatus;
use App\Models\LegacyImportBatch;
use App\Models\LegacyMigrationException;
use App\Models\LegacyRecord;
use App\Models\LegacySource;
use App\Models\MigrationValidationResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use JsonException;
use RuntimeException;
use SplFileObject;

class StageLegacyExport
{
    public const SchemaVersion = 'bpls.legacy-staging.v1';

    public function handle(string $manifestPath, string $runReference): LegacyImportBatch
    {
        $this->assertRunReference($runReference);

        $absoluteManifestPath = realpath($manifestPath);

        if ($absoluteManifestPath === false || ! is_file($absoluteManifestPath)) {
            throw new RuntimeException("Legacy staging manifest [{$manifestPath}] does not exist.");
        }

        $manifestContents = file_get_contents($absoluteManifestPath);

        if ($manifestContents === false) {
            throw new RuntimeException("Legacy staging manifest [{$manifestPath}] could not be read.");
        }

        $manifest = $this->validatedManifest($manifestContents);
        $manifestChecksum = hash('sha256', $manifestContents);
        $source = $this->resolveSource($manifest['source']);
        $batch = $this->resolveBatch($source, $runReference, $manifestChecksum, basename($absoluteManifestPath));

        if (in_array($batch->status, [LegacyImportBatchStatus::Staged, LegacyImportBatchStatus::StagedWithExceptions], true)) {
            return $this->withEvidence($batch);
        }

        $this->resetBatch($batch);

        $sourceRecordCount = 0;
        $stagedRecordCount = 0;
        $exceptionCount = 0;
        $hasFatalFailure = false;
        $manifestDirectory = dirname($absoluteManifestPath);

        foreach ($manifest['datasets'] as $dataset) {
            $result = $this->stageDataset($batch, $source, $manifestDirectory, $dataset);
            $sourceRecordCount += $result['source_record_count'];
            $stagedRecordCount += $result['staged_record_count'];
            $exceptionCount += $result['exception_count'];
            $hasFatalFailure = $hasFatalFailure || $result['fatal'];
        }

        $status = match (true) {
            $hasFatalFailure => LegacyImportBatchStatus::Failed,
            $exceptionCount > 0 => LegacyImportBatchStatus::StagedWithExceptions,
            default => LegacyImportBatchStatus::Staged,
        };

        $batch->update([
            'status' => $status,
            'source_record_count' => $sourceRecordCount,
            'staged_record_count' => $stagedRecordCount,
            'exception_count' => $exceptionCount,
            'mapping_count' => 0,
            'completed_at' => now(),
            'metadata' => [
                ...($batch->metadata ?? []),
                'dataset_count' => count($manifest['datasets']),
                'domain_writes' => false,
                'payloads_in_report' => false,
            ],
        ]);

        return $this->withEvidence($batch);
    }

    /**
     * @param  array{key: string, title: string, source_type: string, baseline?: string|null, archive_checksum?: string|null, provenance: array<string, mixed>}  $sourceData
     */
    private function resolveSource(array $sourceData): LegacySource
    {
        return DB::transaction(function () use ($sourceData): LegacySource {
            $source = LegacySource::query()->where('key', $sourceData['key'])->lockForUpdate()->first();

            if ($source instanceof LegacySource) {
                $expected = [
                    'title' => $sourceData['title'],
                    'source_type' => $sourceData['source_type'],
                    'baseline' => $sourceData['baseline'] ?? null,
                    'archive_checksum' => $sourceData['archive_checksum'] ?? null,
                    'provenance' => $sourceData['provenance'],
                ];
                $actual = $source->only(array_keys($expected));

                if ($actual !== $expected) {
                    throw new RuntimeException("Legacy source [{$source->key}] is already registered with different identity or provenance.");
                }

                return $source;
            }

            return LegacySource::query()->create([
                ...$sourceData,
                'status' => 'registered',
            ]);
        });
    }

    private function resolveBatch(LegacySource $source, string $runReference, string $manifestChecksum, string $manifestFilename): LegacyImportBatch
    {
        return DB::transaction(function () use ($source, $runReference, $manifestChecksum, $manifestFilename): LegacyImportBatch {
            $batch = LegacyImportBatch::query()
                ->where('legacy_source_id', $source->id)
                ->where('run_reference', $runReference)
                ->lockForUpdate()
                ->first();

            if ($batch instanceof LegacyImportBatch) {
                if (! hash_equals($batch->manifest_checksum, $manifestChecksum)) {
                    throw new RuntimeException("Run reference [{$runReference}] is already bound to a different manifest checksum.");
                }

                return $batch;
            }

            return $source->importBatches()->create([
                'run_reference' => $runReference,
                'manifest_schema_version' => self::SchemaVersion,
                'manifest_checksum' => $manifestChecksum,
                'status' => LegacyImportBatchStatus::Staging,
                'started_at' => now(),
                'metadata' => [
                    'manifest_filename' => $manifestFilename,
                    'domain_writes' => false,
                ],
            ]);
        });
    }

    private function resetBatch(LegacyImportBatch $batch): void
    {
        DB::transaction(function () use ($batch): void {
            $batch->idMappings()->delete();
            $batch->exceptions()->delete();
            $batch->validationResults()->delete();
            $batch->records()->delete();
            $batch->update([
                'status' => LegacyImportBatchStatus::Staging,
                'source_record_count' => 0,
                'staged_record_count' => 0,
                'exception_count' => 0,
                'mapping_count' => 0,
                'started_at' => now(),
                'completed_at' => null,
            ]);
        });
    }

    /**
     * @param  array{key: string, entity_type: string, file: string, sha256: string, record_count: int, identity_field: string}  $dataset
     * @return array{source_record_count: int, staged_record_count: int, exception_count: int, fatal: bool}
     */
    private function stageDataset(LegacyImportBatch $batch, LegacySource $source, string $manifestDirectory, array $dataset): array
    {
        try {
            $datasetPath = $this->resolveDatasetPath($manifestDirectory, $dataset['file']);
        } catch (RuntimeException $exception) {
            $this->validation($batch, $dataset['key'], 'dataset_file', MigrationValidationStatus::Failed, ['file' => $dataset['file']], ['available' => false], $exception->getMessage());
            $this->exception($batch, $dataset['key'], null, 'dataset_file_unavailable', $exception->getMessage(), ['file' => basename($dataset['file'])]);

            return ['source_record_count' => 0, 'staged_record_count' => 0, 'exception_count' => 1, 'fatal' => true];
        }

        $actualChecksum = hash_file('sha256', $datasetPath);
        $checksumPassed = is_string($actualChecksum) && hash_equals($dataset['sha256'], $actualChecksum);
        $this->validation(
            $batch,
            $dataset['key'],
            'dataset_checksum',
            $checksumPassed ? MigrationValidationStatus::Passed : MigrationValidationStatus::Failed,
            ['sha256' => $dataset['sha256']],
            ['sha256' => $actualChecksum],
            $checksumPassed ? null : 'Dataset staging was refused because its SHA-256 checksum did not match the manifest.',
        );

        if (! $checksumPassed) {
            $this->exception($batch, $dataset['key'], null, 'dataset_checksum_mismatch', 'Dataset staging was refused because its checksum does not match the manifest.', ['file' => basename($datasetPath)]);

            return ['source_record_count' => 0, 'staged_record_count' => 0, 'exception_count' => 1, 'fatal' => true];
        }

        $sourceRecordCount = 0;
        $stagedRecordCount = 0;
        $exceptionCount = 0;
        $seenLegacyIds = [];
        $file = new SplFileObject($datasetPath, 'rb');
        $lineNumber = 0;

        while (! $file->eof()) {
            $rawLine = $file->fgets();
            $lineNumber++;

            if (trim($rawLine) === '') {
                continue;
            }

            $sourceRecordCount++;
            $line = trim($rawLine);

            try {
                $payload = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                $exceptionCount++;
                $this->exception($batch, $dataset['key'], $lineNumber, 'invalid_json', 'The JSONL row is not valid JSON and was not staged.', [
                    'raw_sha256' => hash('sha256', $line),
                    'json_error' => $exception->getMessage(),
                ]);

                continue;
            }

            if (! is_array($payload) || array_is_list($payload)) {
                $exceptionCount++;
                $this->exception($batch, $dataset['key'], $lineNumber, 'invalid_document', 'The JSONL row is not a JSON object and was not staged.', ['raw_sha256' => hash('sha256', $line)]);

                continue;
            }

            $legacyIdValue = data_get($payload, $dataset['identity_field']);
            $legacyId = is_string($legacyIdValue) || is_int($legacyIdValue) ? trim((string) $legacyIdValue) : '';

            if ($legacyId === '') {
                $exceptionCount++;
                $this->exception($batch, $dataset['key'], $lineNumber, 'missing_legacy_id', 'The JSONL document has no stable legacy identifier and was not staged.', [
                    'identity_field' => $dataset['identity_field'],
                    'payload_sha256' => hash('sha256', $line),
                ]);

                continue;
            }

            if (isset($seenLegacyIds[$legacyId])) {
                $exceptionCount++;
                $this->exception($batch, $dataset['key'], $lineNumber, 'duplicate_legacy_id', 'The dataset contains a duplicate legacy identifier and the duplicate row was not staged.', [
                    'legacy_id_sha256' => hash('sha256', $legacyId),
                    'first_line_number' => $seenLegacyIds[$legacyId],
                ]);

                continue;
            }

            $seenLegacyIds[$legacyId] = $lineNumber;
            LegacyRecord::query()->create([
                'legacy_import_batch_id' => $batch->id,
                'legacy_source_id' => $source->id,
                'dataset_key' => $dataset['key'],
                'entity_type' => $dataset['entity_type'],
                'legacy_id' => $legacyId,
                'payload' => $payload,
                'payload_hash' => hash('sha256', $line),
                'status' => 'staged',
                'line_number' => $lineNumber,
            ]);
            $stagedRecordCount++;
        }

        $countPassed = $sourceRecordCount === $dataset['record_count'];
        $this->validation(
            $batch,
            $dataset['key'],
            'source_record_count',
            $countPassed ? MigrationValidationStatus::Passed : MigrationValidationStatus::Failed,
            ['record_count' => $dataset['record_count']],
            ['record_count' => $sourceRecordCount],
            $countPassed ? null : 'Observed non-empty JSONL row count does not match the manifest.',
        );
        $this->validation(
            $batch,
            $dataset['key'],
            'staged_record_count',
            $stagedRecordCount === $sourceRecordCount ? MigrationValidationStatus::Passed : MigrationValidationStatus::Warning,
            ['source_record_count' => $sourceRecordCount],
            ['staged_record_count' => $stagedRecordCount],
            $stagedRecordCount === $sourceRecordCount ? null : 'Some source rows require review and were deliberately not staged as valid records.',
        );

        if (! $countPassed) {
            $exceptionCount++;
            $this->exception($batch, $dataset['key'], null, 'source_record_count_mismatch', 'Dataset row count does not match the manifest.', [
                'expected' => $dataset['record_count'],
                'actual' => $sourceRecordCount,
            ]);
        }

        return [
            'source_record_count' => $sourceRecordCount,
            'staged_record_count' => $stagedRecordCount,
            'exception_count' => $exceptionCount,
            'fatal' => ! $countPassed,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $expected
     * @param  array<string, mixed>|null  $actual
     */
    private function validation(LegacyImportBatch $batch, ?string $datasetKey, string $checkKey, MigrationValidationStatus $status, ?array $expected, ?array $actual, ?string $details): void
    {
        MigrationValidationResult::query()->create([
            'legacy_import_batch_id' => $batch->id,
            'dataset_key' => $datasetKey,
            'check_key' => $checkKey,
            'status' => $status,
            'expected' => $expected,
            'actual' => $actual,
            'details' => $details,
        ]);
    }

    /** @param array<string, mixed> $context */
    private function exception(LegacyImportBatch $batch, ?string $datasetKey, ?int $lineNumber, string $code, string $message, array $context): void
    {
        LegacyMigrationException::query()->create([
            'legacy_import_batch_id' => $batch->id,
            'legacy_record_id' => null,
            'dataset_key' => $datasetKey,
            'line_number' => $lineNumber,
            'code' => $code,
            'severity' => MigrationExceptionSeverity::Error,
            'status' => MigrationExceptionStatus::Open,
            'message' => $message,
            'context' => $context,
        ]);
    }

    private function resolveDatasetPath(string $manifestDirectory, string $relativePath): string
    {
        if (str_starts_with($relativePath, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $relativePath) === 1) {
            throw new RuntimeException('Dataset paths must be relative to the manifest directory.');
        }

        $manifestRoot = realpath($manifestDirectory);
        $datasetPath = realpath($manifestDirectory.DIRECTORY_SEPARATOR.$relativePath);

        if ($manifestRoot === false || $datasetPath === false || ! is_file($datasetPath)) {
            throw new RuntimeException("Dataset [{$relativePath}] does not exist.");
        }

        if ($datasetPath !== $manifestRoot && ! str_starts_with($datasetPath, $manifestRoot.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException("Dataset [{$relativePath}] resolves outside the manifest directory.");
        }

        return $datasetPath;
    }

    /**
     * @return array{
     *   schema_version: string,
     *   source: array{key: string, title: string, source_type: string, baseline?: string|null, archive_checksum?: string|null, provenance: array<string, mixed>},
     *   datasets: list<array{key: string, entity_type: string, file: string, sha256: string, record_count: int, identity_field: string}>
     * }
     */
    private function validatedManifest(string $contents): array
    {
        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Legacy staging manifest is not valid JSON: '.$exception->getMessage(), previous: $exception);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('Legacy staging manifest must be a JSON object.');
        }

        $validator = Validator::make($decoded, [
            'schema_version' => ['required', 'in:'.self::SchemaVersion],
            'source' => ['required', 'array'],
            'source.key' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'source.title' => ['required', 'string', 'max:255'],
            'source.source_type' => ['required', 'string', 'max:100'],
            'source.baseline' => ['nullable', 'string', 'max:255'],
            'source.archive_checksum' => ['nullable', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'source.provenance' => ['required', 'array'],
            'datasets' => ['required', 'array', 'list', 'min:1'],
            'datasets.*' => ['required', 'array'],
            'datasets.*.key' => ['required', 'string', 'max:100', 'distinct:strict', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'datasets.*.entity_type' => ['required', 'string', 'max:100'],
            'datasets.*.file' => ['required', 'string', 'max:500'],
            'datasets.*.sha256' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'datasets.*.record_count' => ['required', 'integer', 'min:0'],
            'datasets.*.identity_field' => ['sometimes', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            throw new RuntimeException('Legacy staging manifest failed validation: '.$validator->errors()->toJson());
        }

        $validated = $validator->validated();
        $validatedSource = $validated['source'];
        $validatedDatasets = $validated['datasets'];

        if (! is_array($validatedSource) || ! is_array($validatedDatasets)) {
            throw new RuntimeException('Legacy staging manifest validation returned an invalid normalized shape.');
        }

        $datasets = [];

        foreach ($validatedDatasets as $dataset) {
            if (! is_array($dataset)) {
                throw new RuntimeException('Legacy staging dataset validation returned an invalid normalized shape.');
            }

            $datasets[] = [
                'key' => (string) $dataset['key'],
                'entity_type' => (string) $dataset['entity_type'],
                'file' => (string) $dataset['file'],
                'sha256' => (string) $dataset['sha256'],
                'record_count' => (int) $dataset['record_count'],
                'identity_field' => isset($dataset['identity_field']) ? (string) $dataset['identity_field'] : '_id',
            ];
        }

        $provenance = $validatedSource['provenance'];

        if (! is_array($provenance)) {
            throw new RuntimeException('Legacy source provenance must be a JSON object.');
        }

        return [
            'schema_version' => (string) $validated['schema_version'],
            'source' => [
                'key' => (string) $validatedSource['key'],
                'title' => (string) $validatedSource['title'],
                'source_type' => (string) $validatedSource['source_type'],
                'baseline' => isset($validatedSource['baseline']) ? (string) $validatedSource['baseline'] : null,
                'archive_checksum' => isset($validatedSource['archive_checksum']) ? (string) $validatedSource['archive_checksum'] : null,
                'provenance' => $provenance,
            ],
            'datasets' => $datasets,
        ];
    }

    private function assertRunReference(string $runReference): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,99}$/', $runReference) !== 1) {
            throw new RuntimeException('Run reference must be 3-100 characters and contain only letters, numbers, dots, underscores, or hyphens.');
        }
    }

    private function withEvidence(LegacyImportBatch $batch): LegacyImportBatch
    {
        return $batch->fresh(['source', 'validationResults', 'exceptions']) ?? $batch;
    }
}
