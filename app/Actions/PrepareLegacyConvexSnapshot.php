<?php

namespace App\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

final class PrepareLegacyConvexSnapshot
{
    public const IntakeSchemaVersion = 'bpls.legacy-convex-snapshot-intake.v1';

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
     *   source_key: string,
     *   table_count: int,
     *   record_count: int,
     *   datasets: list<array<string, mixed>>
     * }
     */
    public function handle(
        string $archivePath,
        string $runReference,
        string $deployment,
        string $capturedAt,
    ): array {
        $this->assertEnvironment();
        $this->assertRunReference($runReference);
        $this->assertDeployment($deployment);
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
            'captured_at' => $capturedAt,
        ];

        $this->bindRun($absoluteRoot, $binding);
        $storedArchivePath = $absoluteRoot.'/snapshot.zip';
        $this->preserveArchive($absoluteArchivePath, $storedArchivePath, $archiveChecksum);
        $datasets = $this->extractDatasets($storedArchivePath, $absoluteRoot);
        $sourceKey = 'IPIL-CONVEX-SNAPSHOT-'.strtoupper(substr($archiveChecksum, 0, 16));
        $manifest = $this->manifest($sourceKey, $archiveChecksum, $deployment, $capturedAt, $datasets);
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
                'captured_at' => $capturedAt,
                'legacy_source_baseline' => 'b5a66a6a8b3828ebae9916f4bde1da729b1b9154',
            ],
            'result' => [
                'table_count' => count($datasets),
                'record_count' => $recordCount,
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
            'source_key' => $sourceKey,
            'table_count' => count($datasets),
            'record_count' => $recordCount,
            'datasets' => $datasets,
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

    /** @return list<array<string, mixed>> */
    private function extractDatasets(string $archivePath, string $root): array
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

            return $datasets;
        } finally {
            $zip->close();
        }
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
     * @return array<string, mixed>
     */
    private function manifest(string $sourceKey, string $archiveChecksum, string $deployment, string $capturedAt, array $datasets): array
    {
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
}
