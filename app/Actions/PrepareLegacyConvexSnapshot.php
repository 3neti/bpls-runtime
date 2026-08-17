<?php

namespace App\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;
use ZipArchive;

final class PrepareLegacyConvexSnapshot
{
    public const IntakeSchemaVersion = 'bpls.legacy-convex-snapshot-intake.v3';

    public const FileStorageIndexSchemaVersion = 'bpls.legacy-convex-file-storage-index.v1';

    private const MaxArchiveBytes = 2_147_483_648;

    private const MaxEntryCount = 10_000;

    private const MaxUncompressedBytes = 8_589_934_592;

    /**
     * @return array{
     *   artifact_root: string,
     *   archive_path: string,
     *   archive_checksum: string,
     *   manifest_path: string,
     *   report_path: string,
     *   provenance_path: string,
     *   source_key: string,
     *   table_count: int,
     *   record_count: int,
     *   datasets: list<array<string, mixed>>,
     *   storage_file_count: int,
     *   storage_bytes: int,
     *   storage_index_path: string|null
     * }
     */
    public function handle(
        string $archivePath,
        string $runReference,
        string $deployment,
        string $capturedAt,
        string $operator,
        string $tooling,
    ): array {
        $this->assertEnvironment();
        $this->assertRunReference($runReference);
        $this->assertDeployment($deployment);
        $this->assertProvenanceValue($operator, 'operator');
        $this->assertProvenanceValue($tooling, 'tooling');
        $capturedAt = $this->normalizedCapturedAt($capturedAt);
        $absoluteArchivePath = realpath($archivePath);

        if ($absoluteArchivePath === false || ! is_file($absoluteArchivePath)) {
            throw new RuntimeException("Convex snapshot archive [{$archivePath}] does not exist.");
        }

        $archiveSize = filesize($absoluteArchivePath);
        if (! is_int($archiveSize) || $archiveSize < 1 || $archiveSize > self::MaxArchiveBytes) {
            throw new RuntimeException('Convex snapshot archive must be between 1 byte and 2 GiB.');
        }

        $archiveChecksum = hash_file('sha256', $absoluteArchivePath);
        if (! is_string($archiveChecksum)) {
            throw new RuntimeException('Convex snapshot archive could not be checksummed.');
        }

        $artifactRoot = "legacy-migrations/convex-snapshots/{$runReference}";
        $absoluteRoot = Storage::disk('local')->path($artifactRoot);
        File::ensureDirectoryExists($absoluteRoot);
        $binding = [
            'schema_version' => self::IntakeSchemaVersion,
            'run_id' => $runReference,
            'archive_sha256' => $archiveChecksum,
            'archive_bytes' => $archiveSize,
            'deployment_sha256' => hash('sha256', $deployment),
            'operator_sha256' => hash('sha256', $operator),
            'tooling_sha256' => hash('sha256', $tooling),
            'captured_at' => $capturedAt,
        ];

        $this->bindRun($absoluteRoot, $binding);
        $provenancePath = $absoluteRoot.'/provenance.json';
        $this->writeImmutableJson($provenancePath, [
            'schema_version' => self::IntakeSchemaVersion,
            'run_id' => $runReference,
            'deployment' => $deployment,
            'captured_at' => $capturedAt,
            'operator' => $operator,
            'tooling' => $tooling,
        ], 'private provenance');
        $storedArchivePath = $absoluteRoot.'/snapshot.zip';
        $this->preserveArchive($absoluteArchivePath, $storedArchivePath, $archiveChecksum);
        $snapshot = $this->extractSnapshot($storedArchivePath, $absoluteRoot, $archiveChecksum);
        $datasets = $snapshot['datasets'];
        $fileStorage = $snapshot['file_storage'];
        $sourceKey = 'IPIL-CONVEX-SNAPSHOT-'.strtoupper(substr($archiveChecksum, 0, 16));
        $manifest = $this->manifest($sourceKey, $archiveChecksum, $deployment, $capturedAt, $datasets, $fileStorage);
        $manifestPath = $absoluteRoot.'/manifest.json';
        $this->writeImmutableJson($manifestPath, $manifest, 'manifest');
        $recordCount = array_sum(array_column($datasets, 'record_count'));
        $report = [
            'schema_version' => self::IntakeSchemaVersion,
            'run_id' => $runReference,
            'source_key' => $sourceKey,
            'archive' => [
                'filename' => 'snapshot.zip',
                'sha256' => $archiveChecksum,
                'bytes' => $archiveSize,
            ],
            'capture' => [
                'deployment_sha256' => hash('sha256', $deployment),
                'operator_sha256' => hash('sha256', $operator),
                'tooling' => $tooling,
                'captured_at' => $capturedAt,
                'legacy_source_baseline' => 'b5a66a6a8b3828ebae9916f4bde1da729b1b9154',
            ],
            'result' => [
                'table_count' => count($datasets),
                'record_count' => $recordCount,
                'file_storage_included' => $fileStorage['included'],
                'storage_file_count' => $fileStorage['file_count'],
                'storage_bytes' => $fileStorage['total_bytes'],
                'storage_index_sha256' => $fileStorage['index_sha256'],
                'manifest_sha256' => hash_file('sha256', $manifestPath),
                'ready_for_checksum_staging' => true,
                'staged' => false,
            ],
            'datasets' => array_map(fn (array $dataset): array => [
                'key' => $dataset['key'],
                'entity_type' => $dataset['entity_type'],
                'sha256' => $dataset['sha256'],
                'record_count' => $dataset['record_count'],
                'reference_count' => count($dataset['references']),
            ], $datasets),
            'safety' => [
                'production_data_present' => true,
                'payloads_in_report' => false,
                'credentials_in_report' => false,
                'domain_writes' => false,
                'migration_executed' => false,
                'external_integrations' => false,
                'cutover_authorized' => false,
                'storage_identifiers_in_report' => false,
                'private_storage_index_contains_source_identifiers' => $fileStorage['included'],
            ],
            'completed_at' => $capturedAt,
        ];
        $reportPath = $absoluteRoot.'/intake.json';
        $this->writeImmutableJson($reportPath, $report, 'intake report');
        $reviewPath = $absoluteRoot.'/review.md';
        $this->writeImmutableText($reviewPath, "# Legacy Convex Snapshot Intake Review\n\nReviewer status: Pending\nReviewer:\nReviewed at:\nNotes:\n", 'review');

        return [
            'artifact_root' => $artifactRoot,
            'archive_path' => $storedArchivePath,
            'archive_checksum' => $archiveChecksum,
            'manifest_path' => $manifestPath,
            'report_path' => $reportPath,
            'provenance_path' => $provenancePath,
            'source_key' => $sourceKey,
            'table_count' => count($datasets),
            'record_count' => $recordCount,
            'datasets' => $datasets,
            'storage_file_count' => $fileStorage['file_count'],
            'storage_bytes' => $fileStorage['total_bytes'],
            'storage_index_path' => $fileStorage['index_path'],
        ];
    }

    /** @param array<string, mixed> $binding */
    private function bindRun(string $root, array $binding): void
    {
        $path = $root.'/binding.json';

        if (File::exists($path)) {
            $existing = json_decode((string) File::get($path), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($existing) || $existing !== $binding) {
                throw new RuntimeException('Convex snapshot intake run is already bound to different source evidence.');
            }

            return;
        }

        File::put($path, $this->json($binding)."\n");
    }

    private function preserveArchive(string $source, string $destination, string $expectedChecksum): void
    {
        if (File::exists($destination)) {
            $actualChecksum = hash_file('sha256', $destination);
            if (! is_string($actualChecksum) || ! hash_equals($expectedChecksum, $actualChecksum)) {
                throw new RuntimeException('Preserved Convex snapshot does not match the bound archive checksum.');
            }

            return;
        }

        $temporary = $destination.'.part';
        try {
            if (! File::copy($source, $temporary)) {
                throw new RuntimeException('Convex snapshot archive could not be copied into private storage.');
            }

            $copiedChecksum = hash_file('sha256', $temporary);
            if (! is_string($copiedChecksum) || ! hash_equals($expectedChecksum, $copiedChecksum)) {
                throw new RuntimeException('Copied Convex snapshot failed checksum verification.');
            }

            if (! File::move($temporary, $destination)) {
                throw new RuntimeException('Verified Convex snapshot could not be finalized in private storage.');
            }
        } finally {
            File::delete($temporary);
        }
    }

    /**
     * @return array{
     *   datasets: list<array<string, mixed>>,
     *   file_storage: array{included: bool, file_count: int, total_bytes: int, index_path: string|null, index_sha256: string|null}
     * }
     */
    private function extractSnapshot(string $archivePath, string $root, string $archiveChecksum): array
    {
        $zip = new ZipArchive;
        $opened = $zip->open($archivePath, ZipArchive::RDONLY);
        if ($opened !== true) {
            throw new RuntimeException('Convex snapshot archive is not a readable ZIP file.');
        }

        try {
            if ($zip->numFiles < 1 || $zip->numFiles > self::MaxEntryCount) {
                throw new RuntimeException('Convex snapshot archive entry count is outside the safe intake limit.');
            }

            $tableEntries = [];
            $storageNamespaces = [];
            $uncompressedBytes = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                if (! is_array($stat)) {
                    throw new RuntimeException('Convex snapshot contains an unreadable ZIP entry.');
                }

                $entry = $stat['name'];
                $this->assertSafeEntry($zip, $index, $entry);
                $entryBytes = $stat['size'];
                $uncompressedBytes += $entryBytes;
                if ($uncompressedBytes > self::MaxUncompressedBytes) {
                    throw new RuntimeException('Convex snapshot uncompressed size exceeds the 8 GiB intake limit.');
                }

                if (preg_match('#^((?:_components/[A-Za-z0-9_-]+/)*)_storage/documents\.jsonl$#', $entry, $matches) === 1) {
                    $namespace = $matches[1];
                    $storage = $storageNamespaces[$namespace] ?? ['metadata' => null, 'blobs' => []];
                    if ($storage['metadata'] !== null) {
                        throw new RuntimeException('Convex snapshot contains duplicate file-storage metadata.');
                    }
                    $storage['metadata'] = $index;
                    $storageNamespaces[$namespace] = $storage;

                    continue;
                }

                if (preg_match('#^((?:_components/[A-Za-z0-9_-]+/)*)_storage/([^/]+)$#', $entry, $matches) === 1
                    && ! in_array($matches[2], ['documents.jsonl', 'generated_schema.jsonl'], true)) {
                    $namespace = $matches[1];
                    $storage = $storageNamespaces[$namespace] ?? ['metadata' => null, 'blobs' => []];
                    $storage['blobs'][] = $index;
                    $storageNamespaces[$namespace] = $storage;

                    continue;
                }

                if (preg_match('#^([A-Za-z][A-Za-z0-9_-]{0,99})/documents\.jsonl$#', $entry, $matches) !== 1) {
                    continue;
                }

                $table = $matches[1];
                if (isset($tableEntries[$table])) {
                    throw new RuntimeException("Convex snapshot contains duplicate documents for table [{$table}].");
                }
                $tableEntries[$table] = $index;
            }

            if ($tableEntries === []) {
                throw new RuntimeException('Convex snapshot contains no table documents.jsonl entries.');
            }

            ksort($tableEntries);
            $datasetKeys = array_keys($tableEntries);
            $tableRoot = $root.'/tables';
            File::ensureDirectoryExists($tableRoot);
            $datasets = [];
            foreach ($tableEntries as $table => $index) {
                $path = $tableRoot.'/'.$table.'.jsonl';
                $recordCount = $this->extractTable($zip, $index, $path, $table);
                $checksum = hash_file('sha256', $path);
                if (! is_string($checksum)) {
                    throw new RuntimeException("Extracted table [{$table}] could not be checksummed.");
                }
                $datasets[] = [
                    'key' => $table,
                    'entity_type' => $this->entityType($table),
                    'file' => 'tables/'.$table.'.jsonl',
                    'sha256' => $checksum,
                    'record_count' => $recordCount,
                    'identity_field' => '_id',
                    'references' => $this->references($table, $datasetKeys),
                ];
            }

            return [
                'datasets' => $datasets,
                'file_storage' => $this->inventoryFileStorage($zip, $storageNamespaces, $root, $archiveChecksum),
            ];
        } finally {
            $zip->close();
        }
    }

    /**
     * @param  array<string, array{metadata: int|null, blobs: list<int>}>  $namespaces
     * @return array{included: bool, file_count: int, total_bytes: int, index_path: string|null, index_sha256: string|null}
     */
    private function inventoryFileStorage(ZipArchive $zip, array $namespaces, string $root, string $archiveChecksum): array
    {
        if ($namespaces === []) {
            return [
                'included' => false,
                'file_count' => 0,
                'total_bytes' => 0,
                'index_path' => null,
                'index_sha256' => null,
            ];
        }

        ksort($namespaces);
        $entries = [];
        foreach ($namespaces as $namespace => $storage) {
            if ($storage['metadata'] === null) {
                throw new RuntimeException('Convex snapshot file storage contains blobs without documents.jsonl metadata.');
            }

            $metadata = $this->storageMetadata($zip, $storage['metadata']);
            $blobs = $this->storageBlobs($zip, $storage['blobs'], array_keys($metadata));
            foreach ($metadata as $storageId => $item) {
                $blob = $blobs[$storageId] ?? null;
                if (! is_array($blob)) {
                    throw new RuntimeException('Convex snapshot file-storage metadata has no matching blob.');
                }
                if ($item['size_bytes'] !== $blob['size_bytes']) {
                    throw new RuntimeException('Convex snapshot file-storage blob size does not match its metadata.');
                }
                if (! hash_equals($item['sha256_base64'], $blob['sha256_base64'])) {
                    throw new RuntimeException('Convex snapshot file-storage blob checksum does not match its metadata.');
                }

                $entries[] = [
                    'namespace' => $namespace === '' ? 'root' : rtrim($namespace, '/'),
                    'storage_id' => $storageId,
                    'archive_entry' => $blob['archive_entry'],
                    'sha256' => $blob['sha256'],
                    'size_bytes' => $blob['size_bytes'],
                    'content_type' => $item['content_type'],
                ];
            }

            if (count($blobs) !== count($metadata)) {
                throw new RuntimeException('Convex snapshot file storage contains a blob without matching metadata.');
            }
        }

        $index = [
            'schema_version' => self::FileStorageIndexSchemaVersion,
            'archive_sha256' => $archiveChecksum,
            'file_count' => count($entries),
            'total_bytes' => array_sum(array_column($entries, 'size_bytes')),
            'entries' => $entries,
        ];
        $indexPath = $root.'/storage-index.json';
        $this->writeImmutableJson($indexPath, $index, 'file-storage index');
        $indexChecksum = hash_file('sha256', $indexPath);
        if (! is_string($indexChecksum)) {
            throw new RuntimeException('Convex snapshot file-storage index could not be checksummed.');
        }

        return [
            'included' => true,
            'file_count' => $index['file_count'],
            'total_bytes' => $index['total_bytes'],
            'index_path' => $indexPath,
            'index_sha256' => $indexChecksum,
        ];
    }

    /** @return array<string, array{sha256_base64: string, size_bytes: int, content_type: string|null}> */
    private function storageMetadata(ZipArchive $zip, int $index): array
    {
        $entry = $zip->getNameIndex($index);
        $stream = is_string($entry) ? $zip->getStream($entry) : false;
        if (! is_resource($stream)) {
            throw new RuntimeException('Convex snapshot file-storage metadata could not be opened.');
        }

        $metadata = [];
        try {
            while (($line = fgets($stream)) !== false) {
                if (trim($line) === '') {
                    continue;
                }

                try {
                    $item = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                } catch (JsonException $exception) {
                    throw new RuntimeException('Convex snapshot file-storage metadata is not valid JSON.', previous: $exception);
                }
                if (! is_array($item)) {
                    throw new RuntimeException('Convex snapshot file-storage metadata must contain JSON objects.');
                }

                $storageId = $item['_id'] ?? null;
                $sha256 = $item['sha256'] ?? null;
                $size = $item['size'] ?? null;
                $contentType = $item['contentType'] ?? null;
                $binaryChecksum = is_string($sha256) ? base64_decode($sha256, true) : false;
                if (! is_string($storageId) || preg_match('/^[A-Za-z0-9_-]{1,255}$/', $storageId) !== 1
                    || ! is_string($sha256) || ! is_string($binaryChecksum) || strlen($binaryChecksum) !== 32
                    || ! is_int($size) || $size < 0
                    || (! is_null($contentType) && (! is_string($contentType) || strlen($contentType) > 255))) {
                    throw new RuntimeException('Convex snapshot file-storage metadata is incomplete or invalid.');
                }
                if (isset($metadata[$storageId])) {
                    throw new RuntimeException('Convex snapshot file-storage metadata contains a duplicate storage ID.');
                }

                $metadata[$storageId] = [
                    'sha256_base64' => $sha256,
                    'size_bytes' => $size,
                    'content_type' => $contentType,
                ];
            }
        } finally {
            fclose($stream);
        }

        return $metadata;
    }

    /**
     * @param  list<int>  $indexes
     * @param  list<string>  $storageIds
     * @return array<string, array{archive_entry: string, sha256: string, sha256_base64: string, size_bytes: int}>
     */
    private function storageBlobs(ZipArchive $zip, array $indexes, array $storageIds): array
    {
        $blobs = [];
        $storageIdLookup = array_fill_keys($storageIds, true);
        foreach ($indexes as $index) {
            $entry = $zip->getNameIndex($index);
            $name = is_string($entry) ? basename($entry) : '';
            $storageId = explode('.', $name, 2)[0];
            if (! isset($storageIdLookup[$storageId])) {
                throw new RuntimeException('Convex snapshot file-storage blob does not resolve to exactly one metadata record.');
            }

            if (isset($blobs[$storageId])) {
                throw new RuntimeException('Convex snapshot file storage contains duplicate blobs for one storage ID.');
            }

            $stream = is_string($entry) ? $zip->getStream($entry) : false;
            if (! is_resource($stream)) {
                throw new RuntimeException('Convex snapshot file-storage blob could not be opened.');
            }

            try {
                $hash = hash_init('sha256');
                $size = hash_update_stream($hash, $stream);
                $checksum = hash_final($hash);
            } finally {
                fclose($stream);
            }
            if (preg_match('/^[a-f0-9]{64}$/', $checksum) !== 1) {
                throw new RuntimeException('Convex snapshot file-storage blob could not be inspected.');
            }

            $binaryChecksum = hex2bin($checksum);
            if (! is_string($binaryChecksum)) {
                throw new RuntimeException('Convex snapshot file-storage blob checksum could not be encoded.');
            }

            $blobs[$storageId] = [
                'archive_entry' => $entry,
                'sha256' => $checksum,
                'sha256_base64' => base64_encode($binaryChecksum),
                'size_bytes' => $size,
            ];
        }

        return $blobs;
    }

    private function assertSafeEntry(ZipArchive $zip, int $index, string $entry): void
    {
        if ($entry === '' || str_contains($entry, "\0") || str_contains($entry, '\\') || str_starts_with($entry, '/')) {
            throw new RuntimeException('Convex snapshot contains an unsafe ZIP entry path.');
        }

        $segments = explode('/', rtrim($entry, '/'));
        if (in_array('', $segments, true) || in_array('.', $segments, true) || in_array('..', $segments, true)) {
            throw new RuntimeException('Convex snapshot contains an unsafe ZIP entry path.');
        }

        if ($zip->getExternalAttributesIndex($index, $operationsSystem, $attributes)) {
            $fileType = ($attributes >> 16) & 0170000;
            if ($fileType === 0120000) {
                throw new RuntimeException('Convex snapshot symbolic links are not accepted.');
            }
        }
    }

    private function extractTable(ZipArchive $zip, int $index, string $destination, string $table): int
    {
        $entry = $zip->getNameIndex($index);
        $input = is_string($entry) ? $zip->getStream($entry) : false;
        $output = fopen($destination.'.part', 'wb');
        if (! is_resource($input) || ! is_resource($output)) {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }
            throw new RuntimeException("Convex table [{$table}] could not be opened for extraction.");
        }

        $recordCount = 0;
        try {
            while (($line = fgets($input)) !== false) {
                if (trim($line) !== '') {
                    $recordCount++;
                }
                if (fwrite($output, $line) === false) {
                    throw new RuntimeException("Convex table [{$table}] could not be extracted.");
                }
            }
        } finally {
            fclose($input);
            fclose($output);
        }

        $temporary = $destination.'.part';
        if (File::exists($destination)) {
            $existingChecksum = hash_file('sha256', $destination);
            $temporaryChecksum = hash_file('sha256', $temporary);
            File::delete($temporary);
            if (! is_string($existingChecksum) || ! is_string($temporaryChecksum) || ! hash_equals($existingChecksum, $temporaryChecksum)) {
                throw new RuntimeException("Existing extracted Convex table [{$table}] does not match the bound snapshot.");
            }

            return $recordCount;
        }

        if (! File::move($temporary, $destination)) {
            File::delete($destination.'.part');
            throw new RuntimeException("Convex table [{$table}] could not be finalized.");
        }

        return $recordCount;
    }

    /**
     * @param  list<array<string, mixed>>  $datasets
     * @param  array{included: bool, file_count: int, total_bytes: int, index_path: string|null, index_sha256: string|null}  $fileStorage
     * @return array<string, mixed>
     */
    private function manifest(
        string $sourceKey,
        string $archiveChecksum,
        string $deployment,
        string $capturedAt,
        array $datasets,
        array $fileStorage,
    ): array {
        return [
            'schema_version' => StageLegacyExport::SchemaVersion,
            'source' => [
                'key' => $sourceKey,
                'title' => 'Municipality of Ipil production Convex snapshot',
                'source_type' => 'convex_snapshot_export',
                'baseline' => $capturedAt,
                'archive_checksum' => $archiveChecksum,
                'provenance' => [
                    'origin' => 'operator_supplied_authorized_convex_snapshot',
                    'environment' => 'production',
                    'deployment_sha256' => hash('sha256', $deployment),
                    'captured_at' => $capturedAt,
                    'legacy_repository' => 'git@github.com:iCubed-Solutions-Inc/bpls-system.git',
                    'legacy_source_baseline' => 'b5a66a6a8b3828ebae9916f4bde1da729b1b9154',
                    'production_data' => true,
                    'payloads_in_evidence_report' => false,
                    'file_storage_included' => $fileStorage['included'],
                    'file_storage_count' => $fileStorage['file_count'],
                    'file_storage_bytes' => $fileStorage['total_bytes'],
                    'file_storage_index_sha256' => $fileStorage['index_sha256'],
                ],
            ],
            'datasets' => $datasets,
        ];
    }

    /**
     * @param  list<string>  $availableDatasets
     * @return list<array{field: string, target_dataset: string, required: bool, cardinality: string}>
     */
    private function references(string $table, array $availableDatasets): array
    {
        $catalog = [
            'businesses' => [
                ['field' => 'ownerId', 'target_dataset' => 'business_owners', 'required' => true, 'cardinality' => 'one'],
            ],
            'business_permit_applications' => [
                ['field' => 'businessOwnerId', 'target_dataset' => 'business_owners', 'required' => true, 'cardinality' => 'one'],
                ['field' => 'businessId', 'target_dataset' => 'businesses', 'required' => true, 'cardinality' => 'one'],
            ],
            'payment_schedules' => [
                ['field' => 'applicationId', 'target_dataset' => 'business_permit_applications', 'required' => true, 'cardinality' => 'one'],
            ],
            'payments' => [
                ['field' => 'applicationId', 'target_dataset' => 'business_permit_applications', 'required' => true, 'cardinality' => 'one'],
                ['field' => 'scheduleId', 'target_dataset' => 'payment_schedules', 'required' => true, 'cardinality' => 'one'],
            ],
            'permit_clearances' => [
                ['field' => 'applicationId', 'target_dataset' => 'business_permit_applications', 'required' => true, 'cardinality' => 'one'],
                ['field' => 'clearanceTypeId', 'target_dataset' => 'clearance_types', 'required' => true, 'cardinality' => 'one'],
            ],
            'permits' => [
                ['field' => 'ownerId', 'target_dataset' => 'business_owners', 'required' => true, 'cardinality' => 'one'],
                ['field' => 'businessId', 'target_dataset' => 'businesses', 'required' => true, 'cardinality' => 'one'],
                ['field' => 'applicationId', 'target_dataset' => 'business_permit_applications', 'required' => false, 'cardinality' => 'one'],
            ],
        ];

        return array_values(array_filter(
            $catalog[$table] ?? [],
            fn (array $reference): bool => in_array($reference['target_dataset'], $availableDatasets, true),
        ));
    }

    private function entityType(string $table): string
    {
        return match ($table) {
            'business_permit_applications' => 'business_permit_application',
            'payment_schedules' => 'payment_schedule',
            'permit_clearances' => 'permit_clearance',
            default => Str::singular($table),
        };
    }

    /** @param array<string, mixed> $value */
    private function writeImmutableJson(string $path, array $value, string $label): void
    {
        $this->writeImmutableText($path, $this->json($value)."\n", $label);
    }

    private function writeImmutableText(string $path, string $contents, string $label): void
    {
        if (File::exists($path)) {
            $existing = File::get($path);
            if (! hash_equals(hash('sha256', $contents), hash('sha256', $existing))) {
                throw new RuntimeException("Existing Convex snapshot {$label} does not match the bound run.");
            }

            return;
        }

        File::put($path, $contents);
    }

    /** @param array<string, mixed> $value */
    private function json(array $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function normalizedCapturedAt(string $capturedAt): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/', $capturedAt) !== 1) {
            throw new RuntimeException('Snapshot --captured-at must be an ISO-8601 timestamp including timezone.');
        }

        try {
            return CarbonImmutable::parse($capturedAt)->toIso8601String();
        } catch (Throwable $exception) {
            throw new RuntimeException('Snapshot --captured-at is not a valid timestamp.', previous: $exception);
        }
    }

    private function assertEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Legacy Convex snapshot intake is restricted to local and testing environments.');
        }
    }

    private function assertRunReference(string $runReference): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,69}$/', $runReference) !== 1) {
            throw new RuntimeException('Snapshot intake run reference must contain 3-70 safe characters.');
        }
    }

    private function assertDeployment(string $deployment): void
    {
        if (preg_match('/^[a-z][a-z0-9-]{2,99}$/', $deployment) !== 1) {
            throw new RuntimeException('Convex deployment must be a safe deployment name, not a URL or credential.');
        }
    }

    private function assertProvenanceValue(string $value, string $label): void
    {
        if ($value === '' || strlen($value) > 255 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new RuntimeException("Snapshot {$label} must be a non-empty single-line value of at most 255 bytes.");
        }
    }
}
