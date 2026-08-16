<?php

namespace App\Actions;

use App\Enums\LegacyDocumentObjectReconciliationStatus;
use App\Enums\LegacyDocumentObjectStagingStatus;
use App\Enums\LegacyImportBatchStatus;
use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyDocumentObjectReconciliation;
use App\Models\LegacyDocumentObjectStagingRun;
use App\Models\LegacyImportBatch;
use App\Models\LegacyRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use JsonException;
use RuntimeException;
use Throwable;

class StageLegacyDocumentObjects
{
    public const SchemaVersion = 'bpls.legacy-document-objects.v1';

    public function __construct(private LegacyDocumentObjectIntegrity $integrity) {}

    public function handle(LegacyImportBatch $batch, string $manifestPath, string $runReference): LegacyDocumentObjectStagingRun
    {
        $this->assertEnvironment();
        $this->assertBatch($batch);
        $this->assertRunReference($runReference);
        $absoluteManifestPath = realpath($manifestPath);

        if ($absoluteManifestPath === false || ! is_file($absoluteManifestPath)) {
            throw new RuntimeException("Legacy document manifest [{$manifestPath}] does not exist.");
        }

        $contents = file_get_contents($absoluteManifestPath);
        if ($contents === false) {
            throw new RuntimeException("Legacy document manifest [{$manifestPath}] could not be read.");
        }

        $manifest = $this->validatedManifest($contents);
        $this->assertManifestIdentity($batch, $manifest);
        $manifestChecksum = hash('sha256', $contents);
        $run = $this->resolveRun($batch, $runReference, $manifestChecksum);

        if ($run->status === LegacyDocumentObjectStagingStatus::Staged) {
            foreach ($run->reconciliations as $reconciliation) {
                $this->integrity->assertReconciledObject($reconciliation);
            }

            return $run->refresh()->load('reconciliations');
        }

        $createdPaths = [];

        try {
            $entries = [];
            foreach ($manifest['objects'] as $index => $object) {
                $entries[] = $this->validatedEntry($batch, dirname($absoluteManifestPath), $object, $index);
            }

            return DB::transaction(function () use ($run, $entries, &$createdPaths): LegacyDocumentObjectStagingRun {
                $lockedRun = LegacyDocumentObjectStagingRun::query()->lockForUpdate()->findOrFail($run->id);
                $lockedRun->reconciliations()->delete();
                $lockedRun->update([
                    'status' => LegacyDocumentObjectStagingStatus::Staging,
                    'object_count' => count($entries),
                    'staged_count' => 0,
                    'started_at' => now(),
                    'completed_at' => null,
                ]);

                foreach ($entries as $entry) {
                    $existing = LegacyDocumentObjectReconciliation::query()
                        ->where('legacy_record_id', $entry['business_record']->id)
                        ->where('item_key', $entry['item_key'])
                        ->where('legacy_document_object_staging_run_id', '!=', $lockedRun->id)
                        ->first();
                    if ($existing instanceof LegacyDocumentObjectReconciliation) {
                        throw new RuntimeException("Business document [{$entry['item_key']}] already has an accepted reconciliation in another staging run.");
                    }

                    $path = $entry['staged_path'];
                    $disk = Storage::disk('local');
                    $existed = $disk->exists($path);

                    if (! $existed && ! $this->integrity->copyLocalFile($entry['source_path'], 'local', $path)) {
                        throw new RuntimeException('Document object could not be copied to private staging storage.');
                    }
                    if (! $existed) {
                        $createdPaths[] = $path;
                    }

                    $stored = $this->integrity->inspectStoredObject('local', $path);
                    if (! hash_equals($entry['inspection']['checksum'], $stored['checksum'])
                        || $entry['inspection']['size_bytes'] !== $stored['size_bytes']
                        || $entry['inspection']['mime_type'] !== $stored['mime_type']) {
                        throw new RuntimeException('Copied document object does not match its source checksum, size, and MIME type.');
                    }

                    $lockedRun->reconciliations()->create([
                        'legacy_record_id' => $entry['business_record']->id,
                        'legacy_application_id_mapping_id' => $entry['application_mapping']->id,
                        'item_key' => $entry['item_key'],
                        'storage_reference_hash' => hash('sha256', $entry['storage_reference']),
                        'document_type_hash' => hash('sha256', $entry['document_type']),
                        'original_name_hash' => hash('sha256', $entry['original_name']),
                        'object_checksum' => $entry['inspection']['checksum'],
                        'size_bytes' => $entry['inspection']['size_bytes'],
                        'mime_type' => $entry['inspection']['mime_type'],
                        'staged_disk' => 'local',
                        'staged_path' => $path,
                        'status' => LegacyDocumentObjectReconciliationStatus::Accepted,
                        'decision_authority' => $entry['decision_authority'],
                        'evidence_reference' => $entry['evidence_reference'],
                        'decided_at' => now(),
                        'metadata' => [
                            'manifest_object_index' => $entry['manifest_object_index'],
                            'business_legacy_id_sha256' => hash('sha256', $entry['business_record']->legacy_id),
                            'application_legacy_id_sha256' => hash('sha256', $entry['application_legacy_id']),
                            'legacy_document_status_observed' => $entry['legacy_status'],
                            'legacy_document_status_authority_migrated' => false,
                            'documentary_sufficiency_asserted' => false,
                        ],
                    ]);
                }

                $lockedRun->update([
                    'status' => LegacyDocumentObjectStagingStatus::Staged,
                    'staged_count' => count($entries),
                    'completed_at' => now(),
                ]);

                return $lockedRun->fresh(['importBatch.source', 'reconciliations']) ?? $lockedRun;
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($createdPaths);
            $run->update([
                'status' => LegacyDocumentObjectStagingStatus::Failed,
                'staged_count' => 0,
                'completed_at' => now(),
                'metadata' => [
                    ...($run->metadata ?? []),
                    'failure_class' => class_basename($exception),
                    'raw_values_recorded' => false,
                ],
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array{business_legacy_id: string, document_index: int, storage_reference: string, file: string, sha256: string, size_bytes: int, mime_type: string, application_legacy_id: string, decision_authority: string, evidence_reference: string}  $object
     * @return array{business_record: LegacyRecord, application_mapping: LegacyApplicationIdMapping, application_legacy_id: string, item_key: string, storage_reference: string, document_type: string, original_name: string, legacy_status: string|null, source_path: string, staged_path: string, inspection: array{checksum: string, size_bytes: int, mime_type: string, extension: string}, decision_authority: string, evidence_reference: string, manifest_object_index: int}
     */
    private function validatedEntry(LegacyImportBatch $batch, string $manifestDirectory, array $object, int $index): array
    {
        $business = $batch->records()->where('dataset_key', 'businesses')->where('legacy_id', $object['business_legacy_id'])->first();
        if (! $business instanceof LegacyRecord) {
            throw new RuntimeException("Document manifest object [{$index}] does not resolve to one staged business.");
        }

        $documents = $business->payload['documents'] ?? null;
        $document = is_array($documents) ? ($documents[$object['document_index']] ?? null) : null;
        if (! is_array($document)) {
            throw new RuntimeException("Document manifest object [{$index}] does not resolve to the stated business document index.");
        }

        $storageReference = $this->string($document['storageId'] ?? null);
        $documentType = $this->string($document['documentType'] ?? null);
        $originalName = $this->string($document['fileName'] ?? null);
        if ($storageReference === '' || ! hash_equals($storageReference, $object['storage_reference'])) {
            throw new RuntimeException("Document manifest object [{$index}] storage reference does not match staged metadata.");
        }
        if ($documentType === '' || $originalName === '' || basename($originalName) !== $originalName || strlen($originalName) > 255) {
            throw new RuntimeException("Document manifest object [{$index}] has unsafe or incomplete document metadata.");
        }

        $applicationRecord = $batch->records()
            ->whereIn('dataset_key', ['business_permit_applications', 'applications'])
            ->where('legacy_id', $object['application_legacy_id'])
            ->first();
        if (! $applicationRecord instanceof LegacyRecord
            || $this->string($applicationRecord->payload['businessId'] ?? null) !== $business->legacy_id) {
            throw new RuntimeException("Document manifest object [{$index}] application scope does not match the staged business relationship.");
        }

        $applicationMappings = LegacyApplicationIdMapping::query()
            ->where('legacy_source_id', $batch->legacy_source_id)
            ->where('legacy_import_batch_id', $batch->id)
            ->where('dataset_key', $applicationRecord->dataset_key)
            ->where('legacy_id', $applicationRecord->legacy_id)
            ->where('status', 'mapped')
            ->get();
        if ($applicationMappings->count() !== 1 || ! $applicationMappings->sole()->permitApplication()->exists()) {
            throw new RuntimeException("Document manifest object [{$index}] requires one exact accepted application mapping.");
        }

        $sourcePath = $this->resolveObjectPath($manifestDirectory, $object['file']);
        $inspection = $this->integrity->inspectLocalFile($sourcePath);
        if (! hash_equals($object['sha256'], $inspection['checksum'])
            || $object['size_bytes'] !== $inspection['size_bytes']
            || $object['mime_type'] !== $inspection['mime_type']) {
            throw new RuntimeException("Document manifest object [{$index}] checksum, size, or MIME type does not match the source file.");
        }

        $itemKey = 'document:'.$object['document_index'];
        $stagedPath = "legacy-document-staging/{$batch->legacy_source_id}/{$batch->id}/{$business->id}/".hash('sha256', $itemKey.'|'.$storageReference).'/'
            .$inspection['checksum'].'.'.$inspection['extension'];

        return [
            'business_record' => $business,
            'application_mapping' => $applicationMappings->sole(),
            'application_legacy_id' => $applicationRecord->legacy_id,
            'item_key' => $itemKey,
            'storage_reference' => $storageReference,
            'document_type' => $documentType,
            'original_name' => $originalName,
            'legacy_status' => $this->nullableString($document['status'] ?? null),
            'source_path' => $sourcePath,
            'staged_path' => $stagedPath,
            'inspection' => $inspection,
            'decision_authority' => $object['decision_authority'],
            'evidence_reference' => $object['evidence_reference'],
            'manifest_object_index' => $index,
        ];
    }

    /** @param array<string, mixed> $manifest */
    private function assertManifestIdentity(LegacyImportBatch $batch, array $manifest): void
    {
        if ($manifest['legacy_source_key'] !== $batch->source->key
            || $manifest['legacy_import_batch_run_reference'] !== $batch->run_reference
            || ! hash_equals($manifest['legacy_import_manifest_checksum'], $batch->manifest_checksum)) {
            throw new RuntimeException('Legacy document manifest does not match the exact staged source and import batch.');
        }
    }

    private function resolveRun(LegacyImportBatch $batch, string $runReference, string $manifestChecksum): LegacyDocumentObjectStagingRun
    {
        return DB::transaction(function () use ($batch, $runReference, $manifestChecksum): LegacyDocumentObjectStagingRun {
            $run = $batch->documentObjectStagingRuns()->where('run_reference', $runReference)->lockForUpdate()->first();
            if ($run instanceof LegacyDocumentObjectStagingRun) {
                if (! hash_equals($run->manifest_checksum, $manifestChecksum)) {
                    throw new RuntimeException("Document staging run [{$runReference}] is already bound to a different manifest checksum.");
                }

                return $run;
            }

            return $batch->documentObjectStagingRuns()->create([
                'run_reference' => $runReference,
                'manifest_schema_version' => self::SchemaVersion,
                'manifest_checksum' => $manifestChecksum,
                'status' => LegacyDocumentObjectStagingStatus::Staging,
                'started_at' => now(),
                'metadata' => [
                    'private_staging' => true,
                    'domain_writes' => false,
                    'raw_values_in_artifacts' => false,
                ],
            ]);
        });
    }

    /** @return array{schema_version: string, legacy_source_key: string, legacy_import_batch_run_reference: string, legacy_import_manifest_checksum: string, objects: list<array{business_legacy_id: string, document_index: int, storage_reference: string, file: string, sha256: string, size_bytes: int, mime_type: string, application_legacy_id: string, decision_authority: string, evidence_reference: string}>} */
    private function validatedManifest(string $contents): array
    {
        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Legacy document manifest is not valid JSON: '.$exception->getMessage(), previous: $exception);
        }
        if (! is_array($decoded)) {
            throw new RuntimeException('Legacy document manifest must be a JSON object.');
        }

        $validator = Validator::make($decoded, [
            'schema_version' => ['required', 'in:'.self::SchemaVersion],
            'legacy_source_key' => ['required', 'string', 'max:100'],
            'legacy_import_batch_run_reference' => ['required', 'string', 'max:100'],
            'legacy_import_manifest_checksum' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'objects' => ['required', 'array', 'list', 'min:1'],
            'objects.*' => ['required', 'array'],
            'objects.*.business_legacy_id' => ['required', 'string', 'max:255'],
            'objects.*.document_index' => ['required', 'integer', 'min:0'],
            'objects.*.storage_reference' => ['required', 'string', 'max:500'],
            'objects.*.file' => ['required', 'string', 'max:500'],
            'objects.*.sha256' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'objects.*.size_bytes' => ['required', 'integer', 'min:1', 'max:'.LegacyDocumentObjectIntegrity::MaximumSizeBytes],
            'objects.*.mime_type' => ['required', 'in:application/pdf,image/jpeg,image/png'],
            'objects.*.application_legacy_id' => ['required', 'string', 'max:255'],
            'objects.*.decision_authority' => ['required', 'string', 'max:255'],
            'objects.*.evidence_reference' => ['required', 'string', 'max:255'],
        ]);
        if ($validator->fails()) {
            throw new RuntimeException('Legacy document manifest failed validation: '.$validator->errors()->toJson());
        }

        /** @var array{schema_version: string, legacy_source_key: string, legacy_import_batch_run_reference: string, legacy_import_manifest_checksum: string, objects: list<array{business_legacy_id: string, document_index: int, storage_reference: string, file: string, sha256: string, size_bytes: int, mime_type: string, application_legacy_id: string, decision_authority: string, evidence_reference: string}>} $validated */
        $validated = $validator->validated();

        return $validated;
    }

    private function resolveObjectPath(string $manifestDirectory, string $relativePath): string
    {
        if (str_starts_with($relativePath, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $relativePath) === 1) {
            throw new RuntimeException('Document object paths must be relative to the manifest directory.');
        }

        $root = realpath($manifestDirectory);
        $path = realpath($manifestDirectory.DIRECTORY_SEPARATOR.$relativePath);
        if ($root === false || $path === false || ! is_file($path)
            || ($path !== $root && ! str_starts_with($path, $root.DIRECTORY_SEPARATOR))) {
            throw new RuntimeException("Document object [{$relativePath}] is unavailable or resolves outside the manifest directory.");
        }

        return $path;
    }

    private function assertEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Legacy document object staging is restricted to local and testing environments.');
        }
    }

    private function assertBatch(LegacyImportBatch $batch): void
    {
        if (! in_array($batch->status, [LegacyImportBatchStatus::Staged, LegacyImportBatchStatus::StagedWithExceptions], true)) {
            throw new RuntimeException('Legacy import batch must finish staging before document objects can be reconciled.');
        }
    }

    private function assertRunReference(string $runReference): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,99}$/', $runReference) !== 1) {
            throw new RuntimeException('Document staging run reference must be 3-100 safe characters.');
        }
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = $this->string($value);

        return $normalized === '' ? null : $normalized;
    }
}
