<?php

namespace App\Support\IpilRescue;

use App\Contracts\IpilCullSource;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

final class ConvexExportIpilCullSource implements IpilCullSource
{
    private ?ZipArchive $archive = null;

    /** @var array<string, string> */
    private array $tableEntries = [];

    /** @var array<string, array<string, mixed>>|null */
    private ?array $storageMetadata = null;

    /** @var array<string, resource> */
    private array $tableStreams = [];

    /** @var array<string, int> */
    private array $tablePositions = [];

    public function __construct(
        private readonly Filesystem $files,
        private readonly ConvexAuthenticatedMediaRetriever $mediaRetriever,
    ) {}

    public function __destruct()
    {
        foreach ($this->tableStreams as $stream) {
            fclose($stream);
        }

        $this->archive?->close();
    }

    public function preflight(): array
    {
        $deployment = $this->requiredConfig('source.deployment');
        $convexUrl = $this->requiredConfig('source.convex_url');
        $deployKey = $this->requiredConfig('source.deploy_key');
        $userToken = $this->requiredConfig('source.user_token');
        $cli = $this->requiredConfig('source.convex_cli');
        $project = $this->requiredConfig('source.project_path');
        $schemaPath = $this->requiredConfig('source.schema_path');

        if (config('ipil_rescue.source.read_only_approved') !== true) {
            throw new RuntimeException('The Ipil source profile is not explicitly approved as read-only.');
        }

        if (! str_starts_with($convexUrl, 'https://') || str_contains($convexUrl, '?')) {
            throw new RuntimeException('The Convex source URL must be an HTTPS deployment base URL.');
        }

        if (! is_file($cli) || ! is_executable($cli)) {
            throw new RuntimeException('The pinned Convex CLI executable is unavailable.');
        }

        if (! is_dir($project) || ! is_file($schemaPath)) {
            throw new RuntimeException('The approved legacy project or source schema is unavailable.');
        }

        $this->assertPrivateEvidenceFile($this->requiredConfig('pricing.manifest_path'));
        $this->mediaRetriever->assertAuthenticated($convexUrl, $userToken);

        $versionProcess = new Process([$cli, '--version'], $project);
        $versionProcess->setTimeout(20);
        $versionProcess->run();

        if (! $versionProcess->isSuccessful()) {
            throw new RuntimeException('The pinned Convex CLI could not be executed.');
        }

        $version = trim($versionProcess->getOutput());

        return [
            'source_system' => 'ipil-convex',
            'deployment_identity_sha256' => hash('sha256', $deployment),
            'source_engine' => 'convex',
            'source_engine_version' => $version === '' ? null : $version,
            'source_schema_sha256' => $this->checksum($schemaPath),
            'consistency_method' => 'authenticated Convex export with file storage; immutable ZIP reused on resume',
            'tool' => [
                'name' => 'convex-export',
                'version' => $version === '' ? 'unknown' : $version,
                'sha256' => $this->checksum($cli),
            ],
        ];
    }

    public function begin(string $workingDirectory): void
    {
        foreach ($this->tableStreams as $stream) {
            fclose($stream);
        }

        $this->tableStreams = [];
        $this->tablePositions = [];
        $this->archive?->close();
        $this->archive = null;
        $this->tableEntries = [];
        $this->storageMetadata = null;
        $archivePath = $workingDirectory.'/source-export.zip';

        if (! is_file($archivePath)) {
            $process = new Process([
                $this->requiredConfig('source.convex_cli'),
                'export',
                '--include-file-storage',
                '--path',
                $archivePath,
            ], $this->requiredConfig('source.project_path'), [
                'CONVEX_DEPLOY_KEY' => $this->requiredConfig('source.deploy_key'),
            ]);
            $process->setTimeout(null);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($archivePath)) {
                throw new RuntimeException('The authenticated read-only Convex export failed.');
            }
        }

        $archive = new ZipArchive;

        if ($archive->open($archivePath, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('The Convex export is not a readable ZIP archive.');
        }

        $this->archive = $archive;
        $this->indexArchive();
    }

    public function datasets(): array
    {
        $this->requireArchive();
        $datasets = [];

        foreach ($this->tableEntries as $name => $entry) {
            $generatedSchema = dirname($entry).'/generated_schema.jsonl';
            $schemaBytes = $this->archive?->getFromName($generatedSchema);
            $datasets[] = [
                'name' => $name,
                'identity_field' => '_id',
                'fields_sha256' => hash('sha256', is_string($schemaBytes) ? $schemaBytes : $name),
                'relationships' => $this->relationships($name),
            ];
        }

        return $datasets;
    }

    public function databasePage(string $dataset, ?string $cursor, int $limit): array
    {
        $archive = $this->requireArchive();
        $entry = $this->tableEntries[$dataset] ?? null;

        if ($entry === null || $limit < 1) {
            throw new RuntimeException('An unsupported source dataset page was requested.');
        }

        $expectedPosition = $cursor === null ? 0 : filter_var($cursor, FILTER_VALIDATE_INT);

        if (! is_int($expectedPosition) || $expectedPosition < 0) {
            throw new RuntimeException('The source page cursor is invalid.');
        }

        if (! isset($this->tableStreams[$dataset])) {
            $stream = $archive->getStream($entry);

            if (! is_resource($stream)) {
                throw new RuntimeException("The source dataset [{$dataset}] cannot be opened.");
            }

            $this->tableStreams[$dataset] = $stream;
            $this->tablePositions[$dataset] = 0;
        }

        if ($this->tablePositions[$dataset] !== $expectedPosition) {
            throw new RuntimeException('Source pagination is unstable or was resumed from an invalid cursor.');
        }

        $rows = [];
        $stream = $this->tableStreams[$dataset];

        while (count($rows) < $limit && ($line = fgets($stream)) !== false) {
            if (trim($line) === '') {
                continue;
            }

            $value = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($value) || array_is_list($value)) {
                throw new RuntimeException("The source dataset [{$dataset}] contains a non-object row.");
            }

            $rows[] = ['raw' => rtrim($line, "\r\n"), 'value' => $value];
            $this->tablePositions[$dataset]++;
        }

        $done = feof($stream);

        if ($done) {
            fclose($stream);
            unset($this->tableStreams[$dataset]);
        }

        return [
            'rows' => $rows,
            'next_cursor' => $done ? null : (string) $this->tablePositions[$dataset],
        ];
    }

    public function mediaObjects(): array
    {
        $this->loadStorageInventory();
        $relationships = $this->mediaRelationships();
        $objects = [];

        foreach ($this->storageMetadata ?? [] as $storageId => $metadata) {
            $linked = $relationships[$storageId] ?? [];

            if ($linked === []) {
                $linked[] = [
                    'source_dataset' => 'storage-objects',
                    'source_key' => $storageId,
                    'relationship' => '_storage',
                    'evidence_role' => 'storage-object',
                    'association_state' => 'UNRESOLVED',
                    'document_type' => null,
                    'original_filename' => null,
                ];
            }

            foreach ($linked as $relationship) {
                $objects[] = [...$relationship, ...$metadata, 'storage_id' => $storageId];
            }
        }

        return $objects;
    }

    public function retrieveMedia(array $object, int $maximumAttempts): array
    {
        $storageId = $object['storage_id'] ?? null;

        if (! is_string($storageId)) {
            throw new RuntimeException('A media object has no source storage identity.');
        }

        return $this->mediaRetriever->retrieve(
            $this->requiredConfig('source.convex_url'),
            $this->requiredConfig('source.user_token'),
            $storageId,
            $maximumAttempts,
        );
    }

    public function pricingEvidence(): array
    {
        $sourcePath = $this->requiredConfig('pricing.manifest_path');
        $sourceManifest = $this->jsonObject($sourcePath);
        $datasets = $sourceManifest['datasets'] ?? null;

        if (! is_array($datasets) || ! array_is_list($datasets) || $datasets === []) {
            throw new RuntimeException('The recovered pricing manifest has no dataset inventory.');
        }

        $sourceDatasets = [];
        $records = [];
        $manifestFingerprint = $this->checksum($sourcePath);

        foreach ($datasets as $dataset) {
            if (! is_array($dataset) || ! is_string($dataset['key'] ?? null) || ! is_int($dataset['record_count'] ?? null) || ! is_string($dataset['sha256'] ?? null)) {
                throw new RuntimeException('The recovered pricing dataset inventory is malformed.');
            }

            $sourceDatasets[] = [
                'dataset' => $dataset['key'],
                'row_count' => $dataset['record_count'],
                'sha256' => $dataset['sha256'],
            ];
            $records[] = [
                'schema_version' => RescueCorpusSemantics::PricingRecordVersion,
                'source_dataset' => 'pricing-corpus',
                'source_key_sha256' => hash('sha256', $dataset['key']),
                'knowledge_kind' => 'raw-dataset-evidence',
                'candidate_payload_sha256' => $dataset['sha256'],
                'confidence' => 'established',
                'evidence' => [
                    'dataset' => $dataset['key'],
                    'record_count' => $dataset['record_count'],
                    'source_manifest_sha256' => $manifestFingerprint,
                ],
            ];
        }

        return [
            'manifest' => [
                'schema_version' => RescueCorpusSemantics::PricingManifestVersion,
                'evidence_class' => 'interpreted-pricing-knowledge',
                'raw_database_fingerprint_sha256' => hash('sha256', CanonicalJson::encode(['datasets' => $sourceDatasets])),
                'records_relative_path' => 'source/pricing/records.jsonl',
                'records_sha256' => str_repeat('0', 64),
                'record_count' => count($records),
                'interpreter' => 'ipil-cull-pricing-reference',
                'interpreter_version' => '1.0.0',
                'interpretation_state' => 'candidate-only',
                'source_datasets' => $sourceDatasets,
            ],
            'records' => $records,
        ];
    }

    private function indexArchive(): void
    {
        $archive = $this->requireArchive();
        $tables = [];

        for ($index = 0; $index < $archive->numFiles; $index++) {
            $name = $archive->getNameIndex($index);

            if (! is_string($name) || $name === '' || str_contains($name, '..') || str_contains($name, '\\')) {
                throw new RuntimeException('The Convex export contains an unsafe entry.');
            }

            if (preg_match('#^([A-Za-z][A-Za-z0-9_-]{0,99})/documents\.jsonl$#', $name, $matches) === 1) {
                $tables[$matches[1]] = $name;
            }
        }

        if ($tables === []) {
            throw new RuntimeException('The Convex export contains no source datasets.');
        }

        ksort($tables);
        $required = config('ipil_rescue.required_datasets');

        if (! is_array($required) || ! array_is_list($required)) {
            throw new RuntimeException('The approved Ipil dataset inventory is not configured.');
        }

        $actualNames = array_keys($tables);
        sort($required);
        sort($actualNames);

        if ($actualNames !== $required) {
            throw new RuntimeException('The Convex export dataset inventory differs from the approved 53-table source contract.');
        }

        $this->tableEntries = $tables;
    }

    private function loadStorageInventory(): void
    {
        if ($this->storageMetadata !== null) {
            return;
        }

        $archive = $this->requireArchive();
        $bytes = $archive->getFromName('_storage/documents.jsonl');

        if (! is_string($bytes)) {
            throw new RuntimeException('The Convex export does not include file-storage metadata.');
        }

        $metadata = [];
        $blobs = [];

        foreach (preg_split('/\R/', trim($bytes)) ?: [] as $line) {
            $item = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            $storageId = is_array($item) ? ($item['_id'] ?? null) : null;
            $binaryHash = is_array($item) && is_string($item['sha256'] ?? null) ? base64_decode($item['sha256'], true) : false;

            if (! is_string($storageId) || ! is_string($binaryHash) || strlen($binaryHash) !== 32 || ! is_int($item['size'] ?? null)) {
                throw new RuntimeException('The Convex storage metadata is incomplete.');
            }

            $metadata[$storageId] = [
                'declared_mime' => is_string($item['contentType'] ?? null) ? $item['contentType'] : null,
                'declared_size_bytes' => $item['size'],
                'declared_sha256' => bin2hex($binaryHash),
            ];
        }

        for ($index = 0; $index < $archive->numFiles; $index++) {
            $entry = $archive->getNameIndex($index);

            if (! is_string($entry) || preg_match('#^_storage/([^/]+)$#', $entry, $matches) !== 1 || in_array($matches[1], ['documents.jsonl', 'generated_schema.jsonl'], true)) {
                continue;
            }

            $storageId = explode('.', $matches[1], 2)[0];
            $blobs[$storageId] = $entry;
        }

        if (array_diff_key($metadata, $blobs) !== [] || array_diff_key($blobs, $metadata) !== []) {
            throw new RuntimeException('The Convex storage metadata and blob inventories do not close.');
        }

        $this->storageMetadata = $metadata;
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function mediaRelationships(): array
    {
        $relationships = [];
        $paths = [
            'businesses' => ['documents'],
            'permit_layouts' => ['backgroundImageStorageId'],
            'platform_settings' => ['logoStorageId', 'landingImageStorageId', 'municipalSealStorageId'],
            'report_exports' => ['storageId'],
        ];

        foreach ($paths as $dataset => $fields) {
            $entry = $this->tableEntries[$dataset] ?? null;

            if ($entry === null) {
                continue;
            }

            $stream = $this->requireArchive()->getStream($entry);

            if (! is_resource($stream)) {
                throw new RuntimeException("The media relationship dataset [{$dataset}] cannot be opened.");
            }

            try {
                while (($line = fgets($stream)) !== false) {
                    $row = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

                    if (! is_array($row)) {
                        continue;
                    }

                    foreach ($fields as $field) {
                        $values = $field === 'documents' && is_array($row[$field] ?? null) ? $row[$field] : [$row[$field] ?? null];

                        foreach ($values as $offset => $value) {
                            $document = is_array($value) ? $value : [];
                            $storageId = is_array($value) ? ($value['storageId'] ?? null) : $value;

                            if (! is_string($storageId)) {
                                continue;
                            }

                            $rowId = is_string($row['_id'] ?? null) ? $row['_id'] : hash('sha256', $line);
                            $relationshipPath = $field === 'documents' ? "{$dataset}.documents[{$offset}].storageId" : "{$dataset}.{$field}";
                            $relationships[$storageId][] = [
                                'source_dataset' => $dataset.'-media',
                                'source_key' => $rowId.'|'.$relationshipPath.'|'.$storageId,
                                'relationship' => $relationshipPath,
                                'evidence_role' => 'metadata-relationship',
                                'association_state' => 'ASSOCIATED',
                                'document_type' => is_string($document['documentType'] ?? null) ? $document['documentType'] : null,
                                'original_filename' => is_string($document['fileName'] ?? null) ? $document['fileName'] : null,
                            ];
                        }
                    }
                }
            } finally {
                fclose($stream);
            }
        }

        return $relationships;
    }

    /** @return list<array{field: string, target_dataset: string, cardinality: string, required: bool}> */
    private function relationships(string $dataset): array
    {
        return match ($dataset) {
            'businesses' => [['field' => 'ownerId', 'target_dataset' => 'business_owners', 'cardinality' => 'one', 'required' => true]],
            'business_permit_applications' => [
                ['field' => 'businessId', 'target_dataset' => 'businesses', 'cardinality' => 'one', 'required' => true],
                ['field' => 'businessOwnerId', 'target_dataset' => 'business_owners', 'cardinality' => 'one', 'required' => true],
            ],
            'payment_schedules' => [['field' => 'applicationId', 'target_dataset' => 'business_permit_applications', 'cardinality' => 'one', 'required' => true]],
            'payments' => [
                ['field' => 'applicationId', 'target_dataset' => 'business_permit_applications', 'cardinality' => 'one', 'required' => true],
                ['field' => 'scheduleId', 'target_dataset' => 'payment_schedules', 'cardinality' => 'one', 'required' => true],
            ],
            'permit_clearances' => [
                ['field' => 'applicationId', 'target_dataset' => 'business_permit_applications', 'cardinality' => 'one', 'required' => true],
                ['field' => 'clearanceTypeId', 'target_dataset' => 'clearance_types', 'cardinality' => 'one', 'required' => true],
            ],
            'permits' => [
                ['field' => 'ownerId', 'target_dataset' => 'business_owners', 'cardinality' => 'one', 'required' => true],
                ['field' => 'businessId', 'target_dataset' => 'businesses', 'cardinality' => 'one', 'required' => true],
                ['field' => 'applicationId', 'target_dataset' => 'business_permit_applications', 'cardinality' => 'one', 'required' => false],
            ],
            'unitsOfMeasurement' => [
                ['field' => 'applicationId', 'target_dataset' => 'business_permit_applications', 'cardinality' => 'one', 'required' => false],
                ['field' => 'businessId', 'target_dataset' => 'businesses', 'cardinality' => 'one', 'required' => false],
            ],
            'fee_overrides' => [
                ['field' => 'divisionGroupId', 'target_dataset' => 'division_groups', 'cardinality' => 'one', 'required' => true],
                ['field' => 'feeId', 'target_dataset' => 'fees', 'cardinality' => 'one', 'required' => true],
            ],
            default => [],
        };
    }

    private function requireArchive(): ZipArchive
    {
        return $this->archive ?? throw new RuntimeException('The Ipil source acquisition has not begun.');
    }

    private function requiredConfig(string $key): string
    {
        $value = config("ipil_rescue.{$key}");

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("The Ipil rescue configuration [{$key}] is required.");
        }

        return $value;
    }

    private function assertPrivateEvidenceFile(string $path): void
    {
        $real = realpath($path);
        $private = realpath(storage_path('app/private'));

        if ($real === false || $private === false || ! is_file($real) || ! str_starts_with($real, $private.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Pricing evidence must be an existing file under local private storage.');
        }
    }

    private function checksum(string $path): string
    {
        $checksum = hash_file('sha256', $path);

        return is_string($checksum) ? $checksum : throw new RuntimeException('A configured source artifact could not be fingerprinted.');
    }

    /** @return array<string, mixed> */
    private function jsonObject(string $path): array
    {
        $payload = json_decode($this->files->get($path), true, flags: JSON_THROW_ON_ERROR);

        return is_array($payload) && ! array_is_list($payload) ? $payload : throw new RuntimeException('Pricing manifest must be a JSON object.');
    }
}
