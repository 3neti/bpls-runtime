<?php

namespace App\Actions;

use App\Contracts\IpilCullSource;
use App\Support\IpilRescue\CanonicalJson;
use App\Support\IpilRescue\IpilCullResult;
use App\Support\IpilRescue\RescueCorpusManifest;
use App\Support\IpilRescue\RescueCorpusSemantics;
use App\Support\IpilRescue\SourceIdentity;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class CullIpilRescueCorpus
{
    /** @var array<string, mixed>|null */
    private ?array $preflightProfile = null;

    public function __construct(
        private readonly IpilCullSource $source,
        private readonly VerifyIpilRescueCorpus $verifyCorpus,
        private readonly Filesystem $files,
    ) {}

    /** @return array<string, mixed> */
    public function preflight(): array
    {
        $root = $this->approvedDestinationRoot();
        $this->files->ensureDirectoryExists($root);

        if (! is_writable($root)) {
            throw new RuntimeException('The private Rescue Corpus destination is not writable.');
        }

        $freeBytes = disk_free_space($root);

        if (! is_float($freeBytes) || $freeBytes < 134_217_728) {
            throw new RuntimeException('The private Rescue Corpus destination has insufficient practical free space.');
        }

        $ignored = new Process(['git', 'check-ignore', '-q', $root], base_path());
        $ignored->run();

        if (! $ignored->isSuccessful()) {
            throw new RuntimeException('The private Rescue Corpus destination is not Git-ignored.');
        }

        $profile = $this->source->preflight();
        $this->preflightProfile = $profile;

        return [
            'passed' => true,
            'source' => $profile['source_system'],
            'deployment_identity_sha256' => $profile['deployment_identity_sha256'],
            'source_schema_sha256' => $profile['source_schema_sha256'],
            'destination' => $root,
            'destination_class' => 'local-private',
            'read_only' => true,
            'write_back' => false,
            'network_operation' => true,
            'corpus_schema' => RescueCorpusManifest::SchemaVersion,
        ];
    }

    public function handle(?string $resumeSnapshotId = null): IpilCullResult
    {
        $this->preflight();
        $profile = $this->preflightProfile ?? throw new RuntimeException('Ipil source preflight did not produce a source profile.');
        $root = $this->approvedDestinationRoot();
        $snapshotId = $resumeSnapshotId ?? $this->newSnapshotId();
        $this->assertSafeSnapshotId($snapshotId);
        $working = $root.'/.in-progress/'.$snapshotId;
        $control = $root.'/.control/'.$snapshotId;
        $final = $root.'/'.$snapshotId;

        if (is_dir($final)) {
            throw new RuntimeException('A finalized Rescue Corpus snapshot is immutable and cannot be resumed or replaced.');
        }

        $this->files->ensureDirectoryExists($working);
        $this->files->ensureDirectoryExists($control);
        $statePath = $control.'/run-state.json';
        $startedAt = $this->existingStartedAt($statePath) ?? $this->timestamp();
        $this->writeState($statePath, $snapshotId, 'IN_PROGRESS', $startedAt, null, $profile['deployment_identity_sha256']);

        try {
            $this->source->begin($control);
            $database = $this->transportDatabase($working, $control, $profile);
            $media = $this->transportMedia($working, $control);
            $pricing = $this->transportPricing($working);
            $findingCount = $this->finalizeFindings($working, $media['findings']);
            $this->writeSourceIdentities($working, $snapshotId, $profile, $database, $media, $pricing);
            $completedAt = $this->timestamp();
            $this->writeProvenance($working, $snapshotId, $startedAt, $completedAt, $profile);

            $counts = [
                'database_rows' => $database['row_count'],
                'media_metadata' => $media['metadata_count'],
                'media_bytes' => $media['object_count'],
                'pricing_records' => $pricing['record_count'],
                'exceptions' => $findingCount,
            ];
            $bindings = $this->bindings($working);
            $manifest = [
                'schema_version' => RescueCorpusManifest::SchemaVersion,
                'corpus_id' => $snapshotId,
                'source' => [
                    'system' => $profile['source_system'],
                    'deployment_identity_sha256' => $profile['deployment_identity_sha256'],
                ],
                'acquisition' => [
                    'run_id' => $snapshotId,
                    'consistency_method' => $profile['consistency_method'],
                ],
                'state' => 'complete',
                'parent_corpus_id' => null,
                'counts' => $counts,
                'bindings' => $bindings,
            ];
            $this->writeJson($working.'/corpus.json', $manifest);
            $this->writeChecksums($working.'/verification/checksums.sha256', $bindings);
            $verification = $this->verifyCorpus->handle($working);
            $this->files->ensureDirectoryExists(dirname($final));

            if (! $this->files->moveDirectory($working, $final)) {
                throw new RuntimeException('The verified Rescue Corpus could not be atomically finalized.');
            }

            $this->files->deleteDirectory($control);

            return new IpilCullResult(
                $snapshotId,
                $final,
                $verification->corpusFingerprint,
                $counts,
                count($database['tables']),
                $media['retrieved_count'],
                $media['missing_count'],
                $media['failure_count'],
                $media['unresolved_count'],
                $media['bytes_count'],
                $profile['source_schema_sha256'],
                $pricing['fingerprint'],
            );
        } catch (Throwable $exception) {
            $this->writeState($statePath, $snapshotId, 'FAILED', $startedAt, $this->timestamp(), $profile['deployment_identity_sha256']);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    private function transportDatabase(string $root, string $control, array $profile): array
    {
        $tablesRoot = $root.'/source/database/tables';
        $this->files->ensureDirectoryExists($tablesRoot);
        $tables = [];
        $rowCount = 0;
        $chunkSize = max(1, (int) config('ipil_rescue.chunk_size', 500));
        $datasets = $this->source->datasets();
        $checkpointPath = $control.'/database-checkpoint.json';
        $checkpoint = is_file($checkpointPath)
            ? json_decode($this->files->get($checkpointPath), true, flags: JSON_THROW_ON_ERROR)
            : [];

        if (! is_array($checkpoint) || (array_is_list($checkpoint) && $checkpoint !== [])) {
            throw new RuntimeException('The database resume checkpoint is invalid.');
        }

        foreach ($datasets as $dataset) {
            $name = $dataset['name'];
            $path = "source/database/tables/{$name}.jsonl";
            $absolutePath = $root.'/'.$path;

            if (is_file($absolutePath)) {
                $count = $this->countJsonl($absolutePath);
                $prior = $checkpoint[$name] ?? null;

                if (! is_array($prior) || ($prior['row_count'] ?? null) !== $count || ($prior['sha256'] ?? null) !== $this->checksum($absolutePath)) {
                    throw new RuntimeException('A resumed database table differs from its verified checkpoint.');
                }
            } else {
                $temporary = $absolutePath.'.part';
                $this->files->delete($temporary);
                $this->files->put($temporary, '');
                $cursor = null;
                $count = 0;

                do {
                    $page = $this->source->databasePage($name, $cursor, $chunkSize);

                    foreach ($page['rows'] as $row) {
                        if (! is_string($row['value'][$dataset['identity_field']] ?? null)) {
                            throw new RuntimeException("Source dataset [{$name}] contains a row without its exact source identity.");
                        }

                        $this->files->append($temporary, $row['raw']."\n");
                        $count++;
                    }

                    $cursor = $page['next_cursor'];
                } while ($cursor !== null);

                if (! $this->files->move($temporary, $absolutePath)) {
                    throw new RuntimeException("Source dataset [{$name}] could not be closed atomically.");
                }
            }

            $checksum = $this->checksum($absolutePath);
            $tables[] = ['dataset' => $name, 'relative_path' => $path, 'row_count' => $count, 'sha256' => $checksum];
            $checkpoint[$name] = ['row_count' => $count, 'sha256' => $checksum];
            $this->files->put($checkpointPath, CanonicalJson::encode($checkpoint)."\n");
            $rowCount += $count;
        }

        $schema = [
            'schema_version' => RescueCorpusSemantics::DatabaseSchemaVersion,
            'source_engine' => $profile['source_engine'],
            'source_engine_version' => $profile['source_engine_version'],
            'source_schema_sha256' => $profile['source_schema_sha256'],
            'datasets' => $datasets,
        ];
        $manifest = [
            'schema_version' => RescueCorpusSemantics::DatabaseManifestVersion,
            'format' => 'jsonl',
            'consistency_method' => $profile['consistency_method'],
            'tables' => $tables,
        ];
        $this->writeJson($root.'/source/database/schema.json', $schema);
        $this->writeJson($root.'/source/database/table-manifest.json', $manifest);

        return ['tables' => $tables, 'datasets' => $datasets, 'row_count' => $rowCount];
    }

    /** @return array<string, mixed> */
    private function transportMedia(string $root, string $control): array
    {
        $this->files->ensureDirectoryExists($root.'/source/media/objects');
        $checkpointPath = $control.'/media-checkpoint.jsonl';
        $entries = $this->readCheckpoint($checkpointPath);
        $findings = [];
        $seenContent = [];

        foreach ($entries as $entry) {
            if (is_string($entry['sha256'] ?? null)) {
                $seenContent[$entry['sha256']] = true;
            }
        }

        foreach ($this->source->mediaObjects() as $object) {
            $sourceKey = hash('sha256', (string) $object['source_key']);

            if (isset($entries[$sourceKey])) {
                $this->assertCheckpointObject($root, $entries[$sourceKey]);

                continue;
            }

            $existingObject = $root.'/source/media/objects/'.hash('sha256', (string) $object['storage_id']).'.bin';

            if (is_file($existingObject)) {
                if (filesize($existingObject) !== $object['declared_size_bytes'] || ! hash_equals((string) $object['declared_sha256'], $this->checksum($existingObject))) {
                    throw new RuntimeException('An existing rescued storage object differs from current source metadata.');
                }

                $retrieval = ['status' => 'retrieved', 'bytes' => $this->files->get($existingObject), 'attempts' => 0, 'error' => null];
            } else {
                $retrieval = $this->source->retrieveMedia($object, max(1, (int) config('ipil_rescue.media_attempts', 3)));
            }
            $entry = $this->mediaEntry($root, $object, $sourceKey, $retrieval, $seenContent);
            $entries[$sourceKey] = $entry;
            $this->files->append($checkpointPath, CanonicalJson::encode($entry)."\n");
        }

        ksort($entries);
        $findings = [];

        foreach ($entries as $entry) {
            foreach ($entry['finding_codes'] as $code) {
                $findings[] = $this->finding('media', $code, $entry['source_dataset'], $entry['source_key_sha256'], 'Media evidence requires review; source identifiers remain private.');
            }
        }

        $manifestBytes = implode("\n", array_map(fn (array $entry): string => CanonicalJson::encode($entry), $entries))."\n";
        $this->writeText($root.'/source/media/media-manifest.jsonl', $manifestBytes);
        $objects = [];
        $metadata = 0;
        $missing = 0;
        $failures = 0;
        $unresolved = 0;

        foreach ($entries as $entry) {
            $metadata += $entry['evidence_role'] === 'metadata-relationship' ? 1 : 0;
            $unresolved += $entry['association_state'] === 'UNRESOLVED' ? 1 : 0;
            $missing += $entry['disposition'] === 'source-missing' ? 1 : 0;
            $failures += in_array($entry['disposition'], ['access-denied', 'retrieval-failed'], true) ? 1 : 0;

            if (is_string($entry['object_relative_path'])) {
                $objects[$entry['object_relative_path']] = (int) $entry['rescued_size_bytes'];
            }
        }

        return [
            'entries' => array_values($entries),
            'findings' => $findings,
            'metadata_count' => $metadata,
            'object_count' => count($objects),
            'retrieved_count' => count($objects),
            'missing_count' => $missing,
            'failure_count' => $failures,
            'unresolved_count' => $unresolved,
            'bytes_count' => array_sum($objects),
        ];
    }

    /**
     * @param  array<string, mixed>  $object
     * @param  array<string, mixed>  $retrieval
     * @param  array<string, true>  $seenContent
     * @return array<string, mixed>
     */
    private function mediaEntry(string $root, array $object, string $sourceKey, array $retrieval, array &$seenContent): array
    {
        $status = $retrieval['status'];
        $bytes = $retrieval['bytes'];
        $objectPath = null;
        $checksum = null;
        $size = null;
        $detectedMime = null;
        $verified = false;
        $codes = [];
        $disposition = match ($status) {
            'source-missing' => 'source-missing',
            'access-denied' => 'access-denied',
            default => 'retrieval-failed',
        };

        if ($status === 'retrieved' && is_string($bytes)) {
            $checksum = hash('sha256', $bytes);
            $size = strlen($bytes);
            $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: null;
            $objectPath = 'source/media/objects/'.hash('sha256', (string) $object['storage_id']).'.bin';
            $this->writeText($root.'/'.$objectPath, $bytes);
            $verified = true;
            $isCorrupt = $size !== $object['declared_size_bytes']
                || ! hash_equals((string) $object['declared_sha256'], $checksum)
                || ($detectedMime !== null && $object['declared_mime'] !== null && $detectedMime !== $object['declared_mime'])
                || (str_starts_with((string) $detectedMime, 'image/') && @getimagesizefromstring($bytes) === false)
                || ($detectedMime === 'application/pdf' && ! str_starts_with($bytes, '%PDF-'));

            if ($size === 0) {
                $disposition = 'zero-byte';
                $codes[] = 'zero-byte';
            } elseif ($isCorrupt) {
                $disposition = 'corrupt';
                $codes[] = 'corrupt-media';
            } elseif (($object['evidence_role'] ?? null) === 'storage-object') {
                $disposition = 'unassociated-byte';
                $codes[] = 'unassociated-byte';
            } elseif (isset($seenContent[$checksum])) {
                $disposition = 'duplicate-content';
                $codes[] = 'duplicate-content';
            } else {
                $disposition = 'rescued';
            }

            $seenContent[$checksum] = true;
        } else {
            $codes[] = $disposition;
        }

        $entry = [
            'schema_version' => RescueCorpusSemantics::MediaEntryVersion,
            'source_dataset' => $object['source_dataset'],
            'source_key_sha256' => $sourceKey,
            'storage_identifier_sha256' => hash('sha256', (string) $object['storage_id']),
            'relationship' => $object['relationship'],
            'evidence_role' => $object['evidence_role'],
            'document_type' => $object['document_type'],
            'original_filename' => $object['original_filename'],
            'declared_mime' => $object['declared_mime'],
            'detected_mime' => $detectedMime,
            'declared_size_bytes' => $object['declared_size_bytes'],
            'rescued_size_bytes' => $size,
            'object_relative_path' => $objectPath,
            'sha256' => $checksum,
            'retrieval_attempts' => $retrieval['attempts'],
            'bytes_verified' => $verified,
            'association_state' => $object['association_state'],
            'disposition' => $disposition,
            'finding_codes' => array_values(array_unique($codes)),
        ];

        return $entry;
    }

    /** @return array<string, mixed> */
    private function transportPricing(string $root): array
    {
        $pricing = $this->source->pricingEvidence();
        $recordsBytes = implode("\n", array_map(fn (array $record): string => CanonicalJson::encode($record), $pricing['records']))."\n";
        $manifest = $pricing['manifest'];
        $manifest['records_relative_path'] = 'source/pricing/records.jsonl';
        $manifest['records_sha256'] = hash('sha256', $recordsBytes);
        $manifest['record_count'] = count($pricing['records']);
        $this->writeText($root.'/source/pricing/records.jsonl', $recordsBytes);
        $this->writeJson($root.'/source/pricing/pricing-manifest.json', $manifest);

        return [
            'manifest' => $manifest,
            'records' => $pricing['records'],
            'record_count' => count($pricing['records']),
            'fingerprint' => hash('sha256', CanonicalJson::encode($manifest)),
        ];
    }

    /** @param list<array<string, mixed>> $findings */
    private function finalizeFindings(string $root, array $findings): int
    {
        usort($findings, fn (array $left, array $right): int => $left['finding_id'] <=> $right['finding_id']);
        $bytes = $findings === [] ? '' : implode("\n", array_map(fn (array $finding): string => CanonicalJson::encode($finding), $findings))."\n";
        $this->writeText($root.'/verification/exceptions.jsonl', $bytes);

        return count($findings);
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $database
     * @param  array<string, mixed>  $media
     * @param  array<string, mixed>  $pricing
     */
    private function writeSourceIdentities(string $root, string $snapshotId, array $profile, array $database, array $media, array $pricing): void
    {
        $path = $root.'/provenance/source-identities.jsonl';
        $this->files->ensureDirectoryExists(dirname($path));
        $temporary = $path.'.part';
        $this->files->delete($temporary);

        foreach ($database['tables'] as $table) {
            $stream = fopen($root.'/'.$table['relative_path'], 'rb');

            if (! is_resource($stream)) {
                throw new RuntimeException('A rescued database table cannot be reopened for provenance.');
            }

            try {
                while (($line = fgets($stream)) !== false) {
                    if (trim($line) === '') {
                        continue;
                    }

                    $row = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                    $sourceKey = is_array($row) ? ($row['_id'] ?? null) : null;

                    if (! is_string($sourceKey)) {
                        throw new RuntimeException('A rescued row lost its source identity.');
                    }

                    $this->appendIdentity($temporary, $profile, $snapshotId, $table['dataset'], $sourceKey, hash('sha256', trim($line)), $table['relative_path'], 'database-row', 'preserve', ['raw']);
                }
            } finally {
                fclose($stream);
            }
        }

        foreach ($media['entries'] as $entry) {
            $this->appendIdentity($temporary, $profile, $snapshotId, $entry['source_dataset'], $entry['source_key_sha256'], hash('sha256', CanonicalJson::encode($entry)), 'source/media/media-manifest.jsonl', 'document', 'preserve', [$entry['association_state'] === 'UNRESOLVED' ? 'unresolved' : 'associated'], true);
        }

        foreach ($pricing['records'] as $record) {
            $this->appendIdentity($temporary, $profile, $snapshotId, $record['source_dataset'], $record['source_key_sha256'], hash('sha256', CanonicalJson::encode($record)), 'source/pricing/records.jsonl', 'pricing-candidate', 'defer', ['candidate'], true);
        }

        if (! $this->files->move($temporary, $path)) {
            throw new RuntimeException('The source identity registry could not be finalized.');
        }
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  list<string>  $flags
     */
    private function appendIdentity(string $path, array $profile, string $snapshotId, string $dataset, string $sourceKey, string $payloadHash, string $locator, string $kind, string $disposition, array $flags, bool $sourceKeyIsHash = false): void
    {
        $identity = SourceIdentity::fromArray([
            'schema_version' => SourceIdentity::SchemaVersion,
            'source_system' => $profile['source_system'],
            'deployment_identity_sha256' => $profile['deployment_identity_sha256'],
            'corpus_id' => $snapshotId,
            'dataset' => $dataset,
            'source_key_sha256' => $sourceKeyIsHash ? $sourceKey : hash('sha256', $sourceKey),
            'canonical_payload_sha256' => $payloadHash,
            'raw_evidence_locator' => $locator,
            'entity_kind' => $kind,
            'mapping_state' => $kind === 'pricing-candidate' ? 'proposed' : 'observed',
            'mapper_version' => $kind === 'pricing-candidate' ? 'ipil-cull-pricing-reference@1.0.0' : null,
            'disposition' => $disposition,
            'evidence' => [],
            'authority' => null,
            'target_reference' => null,
            'flags' => $flags,
        ]);
        $this->files->append($path, CanonicalJson::encode($identity->toArray())."\n");
    }

    /** @param array<string, mixed> $profile */
    private function writeProvenance(string $root, string $snapshotId, string $startedAt, string $completedAt, array $profile): void
    {
        $this->writeJson($root.'/provenance/acquisition.json', [
            'schema_version' => RescueCorpusSemantics::AcquisitionVersion,
            'run_id' => $snapshotId,
            'source_system' => $profile['source_system'],
            'deployment_identity_sha256' => $profile['deployment_identity_sha256'],
            'mode' => 'explicit-network',
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'consistency_method' => $profile['consistency_method'],
            'read_only' => true,
            'write_back' => false,
            'destination_class' => 'local-private',
        ]);
        $cullerPath = __FILE__;
        $this->writeJson($root.'/provenance/tools.json', [
            'schema_version' => RescueCorpusSemantics::ToolsVersion,
            'tools' => [
                $profile['tool'],
                ['name' => 'ipil-cull', 'version' => '1.0.0', 'sha256' => $this->checksum($cullerPath)],
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function finding(string $class, string $code, ?string $dataset, ?string $sourceKey, string $message): array
    {
        return [
            'schema_version' => RescueCorpusSemantics::FindingVersion,
            'finding_id' => 'finding-'.substr(hash('sha256', $class."\0".$code."\0".$dataset."\0".$sourceKey), 0, 24),
            'evidence_class' => $class,
            'code' => $code,
            'severity' => in_array($code, ['access-denied', 'retrieval-failed', 'corrupt-media'], true) ? 'error' : 'warning',
            'status' => 'open',
            'source_key_sha256' => $sourceKey,
            'dataset' => $dataset,
            'message' => $message,
            'details' => ['redacted' => true],
            'disposition' => 'preserve',
            'authority' => null,
        ];
    }

    private function approvedDestinationRoot(): string
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Ipil culling is restricted to local and testing environments.');
        }

        $configured = config('ipil_rescue.destination_root');

        if (! is_string($configured) || str_contains($configured, '://')) {
            throw new RuntimeException('The Rescue Corpus destination must be a local path.');
        }

        $privatePath = storage_path('app/private');
        $private = realpath($privatePath);

        if ($private === false || is_link($privatePath) || str_contains($configured, '..') || ! str_starts_with($configured, $privatePath.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('The Rescue Corpus destination must remain under local private storage.');
        }

        $candidate = $privatePath;

        foreach (explode(DIRECTORY_SEPARATOR, substr($configured, strlen($privatePath) + 1)) as $segment) {
            $candidate .= DIRECTORY_SEPARATOR.$segment;

            if (is_link($candidate)) {
                throw new RuntimeException('The Rescue Corpus destination cannot traverse a symbolic link.');
            }
        }

        if (str_starts_with($configured, public_path()) || is_link($configured)) {
            throw new RuntimeException('The Rescue Corpus destination cannot be public or symbolic.');
        }

        return $configured;
    }

    /** @return array<string, array<string, mixed>> */
    private function readCheckpoint(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $entries = [];

        foreach (preg_split('/\R/', trim($this->files->get($path))) ?: [] as $line) {
            $entry = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($entry) || ! is_string($entry['source_key_sha256'] ?? null) || isset($entries[$entry['source_key_sha256']])) {
                throw new RuntimeException('The media resume checkpoint is invalid or duplicated.');
            }

            $entries[$entry['source_key_sha256']] = $entry;
        }

        return $entries;
    }

    /** @param array<string, mixed> $entry */
    private function assertCheckpointObject(string $root, array $entry): void
    {
        if ($entry['object_relative_path'] === null) {
            return;
        }

        $path = $root.'/'.$entry['object_relative_path'];

        if (! is_file($path) || ! hash_equals((string) $entry['sha256'], $this->checksum($path)) || filesize($path) !== $entry['rescued_size_bytes']) {
            throw new RuntimeException('A resumed media object differs from its verified checkpoint.');
        }
    }

    /** @return array<string, string> */
    private function bindings(string $root): array
    {
        $bindings = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));

            if (in_array($relative, ['corpus.json', 'verification/checksums.sha256'], true)) {
                continue;
            }

            $bindings[$relative] = $this->checksum($file->getPathname());
        }

        ksort($bindings);

        return $bindings;
    }

    /** @param array<string, string> $bindings */
    private function writeChecksums(string $path, array $bindings): void
    {
        $contents = '';

        foreach ($bindings as $relative => $checksum) {
            $contents .= "{$checksum}  {$relative}\n";
        }

        $this->writeText($path, $contents);
    }

    /** @param array<string, mixed> $payload */
    private function writeJson(string $path, array $payload): void
    {
        $this->writeText($path, CanonicalJson::encode($payload)."\n");
    }

    private function writeText(string $path, string $contents): void
    {
        $this->files->ensureDirectoryExists(dirname($path));

        if (is_file($path)) {
            $existing = $this->files->get($path);

            if (! hash_equals(hash('sha256', $existing), hash('sha256', $contents))) {
                throw new RuntimeException('An existing rescue artifact differs from the deterministic resumed output.');
            }

            return;
        }

        $temporary = $path.'.part';
        $this->files->put($temporary, $contents);

        if (! $this->files->move($temporary, $path)) {
            throw new RuntimeException('A rescue artifact could not be closed atomically.');
        }
    }

    private function writeState(string $path, string $snapshotId, string $state, string $startedAt, ?string $finishedAt, string $deploymentHash): void
    {
        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, CanonicalJson::encode([
            'snapshot_id' => $snapshotId,
            'state' => $state,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'deployment_identity_sha256' => $deploymentHash,
        ])."\n");
    }

    private function existingStartedAt(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $state = json_decode($this->files->get($path), true);

        return is_array($state) && is_string($state['started_at'] ?? null) ? $state['started_at'] : null;
    }

    private function countJsonl(string $path): int
    {
        $count = 0;
        $stream = fopen($path, 'rb');

        if (! is_resource($stream)) {
            throw new RuntimeException('A rescued JSONL file could not be read.');
        }

        try {
            while (($line = fgets($stream)) !== false) {
                if (trim($line) !== '') {
                    $count++;
                }
            }
        } finally {
            fclose($stream);
        }

        return $count;
    }

    private function checksum(string $path): string
    {
        $checksum = hash_file('sha256', $path);

        return is_string($checksum) ? $checksum : throw new RuntimeException('A rescue artifact could not be fingerprinted.');
    }

    private function newSnapshotId(): string
    {
        return 'ipil-'.strtolower(gmdate('Ymd\THis\Z')).'-'.bin2hex(random_bytes(4));
    }

    private function assertSafeSnapshotId(string $snapshotId): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]{2,127}$/', $snapshotId) !== 1 || str_contains($snapshotId, '..')) {
            throw new RuntimeException('The snapshot identifier is unsafe.');
        }
    }

    private function timestamp(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM);
    }
}
