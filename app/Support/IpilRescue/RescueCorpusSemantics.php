<?php

namespace App\Support\IpilRescue;

use InvalidArgumentException;

final class RescueCorpusSemantics
{
    public const DatabaseSchemaVersion = 'bpls.ipil-rescue-database-schema.v1';

    public const DatabaseManifestVersion = 'bpls.ipil-rescue-database-manifest.v1';

    public const MediaEntryVersion = 'bpls.ipil-rescue-media-entry.v1';

    public const PricingManifestVersion = 'bpls.ipil-rescue-pricing-manifest.v1';

    public const PricingRecordVersion = 'bpls.ipil-rescue-pricing-record.v1';

    public const AcquisitionVersion = 'bpls.ipil-rescue-acquisition.v1';

    public const ToolsVersion = 'bpls.ipil-rescue-tools.v1';

    public const FindingVersion = 'bpls.ipil-rescue-finding.v1';

    /** @var list<string> */
    public const MediaDispositions = [
        'rescued',
        'source-missing',
        'access-denied',
        'corrupt',
        'zero-byte',
        'duplicate-content',
        'orphan-metadata',
        'orphan-byte',
    ];

    /** @var list<string> */
    public const FindingCodes = [
        'access-denied',
        'ambiguous-mapping',
        'checksum-mismatch',
        'contradictory-source-evidence',
        'corrupt-media',
        'duplicate-content',
        'missing-required-field',
        'orphan-byte',
        'orphan-metadata',
        'source-missing',
        'unresolved-reference',
        'unsupported-source-value',
        'zero-byte',
    ];

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $manifest
     * @param  callable(string): string  $boundFile
     */
    public function verifyDatabase(array $schema, array $manifest, callable $boundFile): int
    {
        $this->exactKeys($schema, [
            'schema_version',
            'source_engine',
            'source_engine_version',
            'source_schema_sha256',
            'datasets',
        ], 'database schema');
        $this->version($schema, self::DatabaseSchemaVersion, 'database schema');
        $this->nonEmptyString($schema['source_engine'] ?? null, 'database schema source_engine');
        $this->nullableString($schema['source_engine_version'] ?? null, 'database schema source_engine_version');
        $this->sha256($schema['source_schema_sha256'] ?? null, 'database schema source_schema_sha256');

        $datasets = $schema['datasets'] ?? null;

        if (! is_array($datasets) || ! array_is_list($datasets)) {
            throw new InvalidArgumentException('The database schema datasets must be a list.');
        }

        $schemaDatasets = [];
        $relationshipTargets = [];

        foreach ($datasets as $dataset) {
            if (! is_array($dataset) || array_is_list($dataset)) {
                throw new InvalidArgumentException('Each database schema dataset must be an object.');
            }

            $this->exactKeys($dataset, ['name', 'identity_field', 'fields_sha256', 'relationships'], 'database schema dataset');
            $name = $this->token($dataset['name'] ?? null, 'database schema dataset name');
            $this->token($dataset['identity_field'] ?? null, 'database schema identity_field', true);
            $this->sha256($dataset['fields_sha256'] ?? null, 'database schema fields_sha256');

            if (isset($schemaDatasets[$name])) {
                throw new InvalidArgumentException("The database schema repeats dataset {$name}.");
            }

            $relationships = $dataset['relationships'] ?? null;

            if (! is_array($relationships) || ! array_is_list($relationships)) {
                throw new InvalidArgumentException('Database schema relationships must be a list.');
            }

            foreach ($relationships as $relationship) {
                if (! is_array($relationship) || array_is_list($relationship)) {
                    throw new InvalidArgumentException('Each database relationship must be an object.');
                }

                $this->exactKeys($relationship, ['field', 'target_dataset', 'cardinality', 'required'], 'database relationship');
                $this->token($relationship['field'] ?? null, 'database relationship field', true);
                $relationshipTargets[] = $this->token($relationship['target_dataset'] ?? null, 'database relationship target_dataset');

                if (! in_array($relationship['cardinality'] ?? null, ['one', 'many'], true)) {
                    throw new InvalidArgumentException('A database relationship cardinality is unsupported.');
                }

                $this->boolean($relationship['required'] ?? null, 'database relationship required');
            }

            $schemaDatasets[$name] = true;
        }

        foreach (array_unique($relationshipTargets) as $targetDataset) {
            if (! isset($schemaDatasets[$targetDataset])) {
                throw new InvalidArgumentException("A database relationship targets undeclared dataset {$targetDataset}.");
            }
        }

        $this->exactKeys($manifest, ['schema_version', 'format', 'consistency_method', 'tables'], 'database manifest');
        $this->version($manifest, self::DatabaseManifestVersion, 'database manifest');

        if (($manifest['format'] ?? null) !== 'jsonl') {
            throw new InvalidArgumentException('The database manifest format must be jsonl.');
        }

        $this->nonEmptyString($manifest['consistency_method'] ?? null, 'database consistency_method');
        $tables = $manifest['tables'] ?? null;

        if (! is_array($tables) || ! array_is_list($tables)) {
            throw new InvalidArgumentException('The database manifest tables must be a list.');
        }

        $manifestDatasets = [];
        $rows = 0;

        foreach ($tables as $table) {
            if (! is_array($table) || array_is_list($table)) {
                throw new InvalidArgumentException('Each database table manifest entry must be an object.');
            }

            $this->exactKeys($table, ['dataset', 'relative_path', 'row_count', 'sha256'], 'database table manifest entry');
            $dataset = $this->token($table['dataset'] ?? null, 'database table dataset');
            $path = $this->relativePath($table['relative_path'] ?? null, 'database table relative_path');
            $rowCount = $this->nonNegativeInteger($table['row_count'] ?? null, 'database table row_count');
            $checksum = $this->sha256($table['sha256'] ?? null, 'database table sha256');

            if (isset($manifestDatasets[$dataset])) {
                throw new InvalidArgumentException("The database manifest repeats dataset {$dataset}.");
            }

            $contents = $boundFile($path);

            if (! hash_equals($checksum, hash('sha256', $contents))) {
                throw new InvalidArgumentException("The database table checksum does not match {$path}.");
            }

            if ($this->jsonlCount($contents, $path, true) !== $rowCount) {
                throw new InvalidArgumentException("The database table row count does not match {$path}.");
            }

            $manifestDatasets[$dataset] = true;
            $rows += $rowCount;
        }

        $expected = array_keys($schemaDatasets);
        $actual = array_keys($manifestDatasets);
        sort($expected);
        sort($actual);

        if ($actual !== $expected) {
            throw new InvalidArgumentException('The database schema and table manifest dataset inventories differ.');
        }

        return $rows;
    }

    /**
     * @param  callable(string): string  $boundFile
     * @return array{metadata: int, bytes: int, source_identities: list<string>}
     */
    public function verifyMedia(string $contents, callable $boundFile): array
    {
        $metadata = 0;
        $objects = [];
        $sourceIdentities = [];

        foreach ($this->jsonlObjects($contents, 'media manifest') as $entry) {
            $this->exactKeys($entry, [
                'schema_version',
                'source_dataset',
                'source_key_sha256',
                'storage_identifier_sha256',
                'relationship',
                'document_type',
                'original_filename',
                'declared_mime',
                'detected_mime',
                'declared_size_bytes',
                'rescued_size_bytes',
                'object_relative_path',
                'sha256',
                'retrieval_attempts',
                'bytes_verified',
                'disposition',
                'finding_codes',
            ], 'media manifest entry');
            $this->version($entry, self::MediaEntryVersion, 'media manifest entry');
            $sourceDataset = $this->token($entry['source_dataset'] ?? null, 'media source_dataset');
            $sourceKey = $this->sha256($entry['source_key_sha256'] ?? null, 'media source_key_sha256');
            $sourceIdentities[] = $sourceDataset."\0".$sourceKey;
            $this->sha256($entry['storage_identifier_sha256'] ?? null, 'media storage_identifier_sha256');
            $this->nonEmptyString($entry['relationship'] ?? null, 'media relationship');
            $this->nullableString($entry['document_type'] ?? null, 'media document_type');
            $this->nullableString($entry['original_filename'] ?? null, 'media original_filename');
            $this->nullableString($entry['declared_mime'] ?? null, 'media declared_mime');
            $this->nullableString($entry['detected_mime'] ?? null, 'media detected_mime');
            $this->nullableNonNegativeInteger($entry['declared_size_bytes'] ?? null, 'media declared_size_bytes');
            $this->nonNegativeInteger($entry['retrieval_attempts'] ?? null, 'media retrieval_attempts');
            $this->boolean($entry['bytes_verified'] ?? null, 'media bytes_verified');

            $disposition = $entry['disposition'] ?? null;

            if (! in_array($disposition, self::MediaDispositions, true)) {
                throw new InvalidArgumentException('A media disposition is unsupported.');
            }

            $this->findingCodes($entry['finding_codes'] ?? null, 'media finding_codes');
            $hasObject = $entry['object_relative_path'] !== null;
            $requiresObject = in_array($disposition, ['rescued', 'corrupt', 'zero-byte', 'duplicate-content', 'orphan-byte'], true);

            if ($requiresObject !== $hasObject) {
                throw new InvalidArgumentException('The media disposition and object presence are inconsistent.');
            }

            if ($hasObject) {
                $path = $this->relativePath($entry['object_relative_path'], 'media object_relative_path');
                $size = $this->nonNegativeInteger($entry['rescued_size_bytes'] ?? null, 'media rescued_size_bytes');
                $checksum = $this->sha256($entry['sha256'] ?? null, 'media sha256');
                $bytes = $boundFile($path);

                if (strlen($bytes) !== $size || ! hash_equals($checksum, hash('sha256', $bytes))) {
                    throw new InvalidArgumentException("Media object integrity does not match {$path}.");
                }

                $objects[$path] = true;
            } elseif ($entry['rescued_size_bytes'] !== null || $entry['sha256'] !== null || $entry['bytes_verified'] !== false) {
                throw new InvalidArgumentException('A media entry without an object cannot claim rescued bytes.');
            }

            if ($hasObject && $entry['bytes_verified'] !== true) {
                throw new InvalidArgumentException('Acquired media must bind transfer-verified bytes.');
            }

            if ($disposition === 'zero-byte' && $entry['rescued_size_bytes'] !== 0) {
                throw new InvalidArgumentException('Zero-byte media must have a rescued size of zero.');
            }

            if ($disposition === 'rescued' && $entry['rescued_size_bytes'] === 0) {
                throw new InvalidArgumentException('A zero-byte object cannot be classified as rescued.');
            }

            if ($disposition !== 'orphan-byte') {
                $metadata++;
            }
        }

        return ['metadata' => $metadata, 'bytes' => count($objects), 'source_identities' => $sourceIdentities];
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  callable(string): string  $boundFile
     */
    public function verifyPricing(array $manifest, callable $boundFile): array
    {
        $this->exactKeys($manifest, [
            'schema_version',
            'evidence_class',
            'raw_database_fingerprint_sha256',
            'records_relative_path',
            'records_sha256',
            'record_count',
            'interpreter',
            'interpreter_version',
            'interpretation_state',
            'source_datasets',
        ], 'pricing manifest');
        $this->version($manifest, self::PricingManifestVersion, 'pricing manifest');

        if (($manifest['evidence_class'] ?? null) !== 'interpreted-pricing-knowledge') {
            throw new InvalidArgumentException('The pricing manifest evidence class is unsupported.');
        }

        $this->sha256($manifest['raw_database_fingerprint_sha256'] ?? null, 'pricing raw database fingerprint');
        $path = $this->relativePath($manifest['records_relative_path'] ?? null, 'pricing records_relative_path');
        $checksum = $this->sha256($manifest['records_sha256'] ?? null, 'pricing records_sha256');
        $count = $this->nonNegativeInteger($manifest['record_count'] ?? null, 'pricing record_count');
        $this->nonEmptyString($manifest['interpreter'] ?? null, 'pricing interpreter');
        $this->nonEmptyString($manifest['interpreter_version'] ?? null, 'pricing interpreter_version');

        if (($manifest['interpretation_state'] ?? null) !== 'candidate-only') {
            throw new InvalidArgumentException('Pricing knowledge must remain candidate-only at this frontier.');
        }

        $sourceDatasets = $manifest['source_datasets'] ?? null;

        if (! is_array($sourceDatasets) || ! array_is_list($sourceDatasets) || $sourceDatasets === []) {
            throw new InvalidArgumentException('The pricing manifest must bind at least one raw source dataset.');
        }

        foreach ($sourceDatasets as $sourceDataset) {
            if (! is_array($sourceDataset) || array_is_list($sourceDataset)) {
                throw new InvalidArgumentException('Each pricing source dataset must be an object.');
            }

            $this->exactKeys($sourceDataset, ['dataset', 'row_count', 'sha256'], 'pricing source dataset');
            $this->token($sourceDataset['dataset'] ?? null, 'pricing source dataset name');
            $this->nonNegativeInteger($sourceDataset['row_count'] ?? null, 'pricing source dataset row_count');
            $this->sha256($sourceDataset['sha256'] ?? null, 'pricing source dataset sha256');
        }

        $contents = $boundFile($path);

        if (! hash_equals($checksum, hash('sha256', $contents))) {
            throw new InvalidArgumentException('The pricing records checksum does not match the pricing manifest.');
        }

        $records = $this->jsonlObjects($contents, 'pricing records');
        $sourceIdentities = [];

        foreach ($records as $record) {
            $this->exactKeys($record, [
                'schema_version',
                'source_dataset',
                'source_key_sha256',
                'knowledge_kind',
                'candidate_payload_sha256',
                'confidence',
                'evidence',
            ], 'pricing record');
            $this->version($record, self::PricingRecordVersion, 'pricing record');
            $sourceDataset = $this->token($record['source_dataset'] ?? null, 'pricing source_dataset');
            $sourceKey = $this->sha256($record['source_key_sha256'] ?? null, 'pricing source_key_sha256');
            $sourceIdentities[] = $sourceDataset."\0".$sourceKey;
            $this->token($record['knowledge_kind'] ?? null, 'pricing knowledge_kind');
            $this->sha256($record['candidate_payload_sha256'] ?? null, 'pricing candidate_payload_sha256');

            if (! in_array($record['confidence'] ?? null, ['established', 'probable', 'ambiguous', 'unknown'], true)) {
                throw new InvalidArgumentException('A pricing record confidence is unsupported.');
            }

            if (! is_array($record['evidence'] ?? null) || array_is_list($record['evidence'])) {
                throw new InvalidArgumentException('Pricing record evidence must be an object.');
            }
        }

        if (count($records) !== $count) {
            throw new InvalidArgumentException('The pricing record count does not match the pricing manifest.');
        }

        return ['count' => $count, 'source_identities' => $sourceIdentities];
    }

    /** @param  array<string, mixed>  $payload */
    public function verifyAcquisition(array $payload, RescueCorpusManifest $manifest): void
    {
        $this->exactKeys($payload, [
            'schema_version',
            'run_id',
            'source_system',
            'deployment_identity_sha256',
            'mode',
            'started_at',
            'completed_at',
            'consistency_method',
            'read_only',
            'write_back',
            'destination_class',
        ], 'acquisition provenance');
        $this->version($payload, self::AcquisitionVersion, 'acquisition provenance');

        if (($payload['run_id'] ?? null) !== $manifest->acquisition['run_id']) {
            throw new InvalidArgumentException('Acquisition provenance run_id does not match the corpus manifest.');
        }

        if (($payload['source_system'] ?? null) !== $manifest->source['system']) {
            throw new InvalidArgumentException('Acquisition provenance source system does not match the corpus manifest.');
        }

        if (($payload['deployment_identity_sha256'] ?? null) !== $manifest->source['deployment_identity_sha256']) {
            throw new InvalidArgumentException('Acquisition provenance deployment identity does not match the corpus manifest.');
        }

        if (! in_array($payload['mode'] ?? null, ['explicit-network', 'synthetic'], true)) {
            throw new InvalidArgumentException('The acquisition mode is unsupported.');
        }

        $this->nonEmptyString($payload['started_at'] ?? null, 'acquisition started_at');
        $this->nonEmptyString($payload['completed_at'] ?? null, 'acquisition completed_at');

        if (($payload['consistency_method'] ?? null) !== $manifest->acquisition['consistency_method']) {
            throw new InvalidArgumentException('Acquisition consistency method does not match the corpus manifest.');
        }

        if (($payload['read_only'] ?? null) !== true || ($payload['write_back'] ?? null) !== false) {
            throw new InvalidArgumentException('Acquisition provenance must prove read-only access and no write-back.');
        }

        if (($payload['destination_class'] ?? null) !== 'local-private') {
            throw new InvalidArgumentException('The acquisition destination must be local-private.');
        }
    }

    /** @param  array<string, mixed>  $payload */
    public function verifyTools(array $payload): void
    {
        $this->exactKeys($payload, ['schema_version', 'tools'], 'tools provenance');
        $this->version($payload, self::ToolsVersion, 'tools provenance');
        $tools = $payload['tools'] ?? null;

        if (! is_array($tools) || ! array_is_list($tools) || $tools === []) {
            throw new InvalidArgumentException('Tools provenance must list at least one tool.');
        }

        foreach ($tools as $tool) {
            if (! is_array($tool) || array_is_list($tool)) {
                throw new InvalidArgumentException('Each tools provenance entry must be an object.');
            }

            $this->exactKeys($tool, ['name', 'version', 'sha256'], 'tools provenance entry');
            $this->token($tool['name'] ?? null, 'tool name');
            $this->nonEmptyString($tool['version'] ?? null, 'tool version');
            $this->sha256($tool['sha256'] ?? null, 'tool sha256');
        }
    }

    public function verifyFindings(string $contents): array
    {
        $findings = $this->jsonlObjects($contents, 'findings');
        $sourceIdentities = [];

        foreach ($findings as $finding) {
            $this->exactKeys($finding, [
                'schema_version',
                'finding_id',
                'evidence_class',
                'code',
                'severity',
                'status',
                'source_key_sha256',
                'dataset',
                'message',
                'details',
                'disposition',
                'authority',
            ], 'finding');
            $this->version($finding, self::FindingVersion, 'finding');
            $this->token($finding['finding_id'] ?? null, 'finding_id');

            if (! in_array($finding['evidence_class'] ?? null, ['database', 'media', 'pricing', 'provenance', 'corpus'], true)) {
                throw new InvalidArgumentException('A finding evidence_class is unsupported.');
            }

            $this->findingCodes([$finding['code'] ?? null], 'finding code');

            if (! in_array($finding['severity'] ?? null, ['info', 'warning', 'error', 'blocker'], true)) {
                throw new InvalidArgumentException('A finding severity is unsupported.');
            }

            if (! in_array($finding['status'] ?? null, ['open', 'accepted', 'resolved', 'waived'], true)) {
                throw new InvalidArgumentException('A finding status is unsupported.');
            }

            if ($finding['dataset'] !== null) {
                $dataset = $this->token($finding['dataset'], 'finding dataset');
            } else {
                $dataset = null;
            }

            if ($finding['source_key_sha256'] !== null) {
                $sourceKey = $this->sha256($finding['source_key_sha256'], 'finding source_key_sha256');

                if ($dataset === null) {
                    throw new InvalidArgumentException('A finding source key requires a dataset.');
                }

                $sourceIdentities[] = $dataset."\0".$sourceKey;
            }

            $this->nonEmptyString($finding['message'] ?? null, 'finding message');

            if (! is_array($finding['details'] ?? null) || array_is_list($finding['details'])) {
                throw new InvalidArgumentException('Finding details must be an object.');
            }

            $this->nullableString($finding['disposition'] ?? null, 'finding disposition');
            $this->nullableString($finding['authority'] ?? null, 'finding authority');
        }

        return ['count' => count($findings), 'source_identities' => $sourceIdentities];
    }

    /** @return list<array<string, mixed>> */
    private function jsonlObjects(string $contents, string $label): array
    {
        if (trim($contents) === '') {
            return [];
        }

        $objects = [];

        foreach (preg_split('/\R/', trim($contents)) ?: [] as $lineNumber => $line) {
            $payload = json_decode($line, true);

            if (! is_array($payload) || array_is_list($payload)) {
                throw new InvalidArgumentException("Invalid JSON object in {$label} line ".($lineNumber + 1).'.');
            }

            $objects[] = $payload;
        }

        return $objects;
    }

    private function jsonlCount(string $contents, string $label, bool $allowAnyObject): int
    {
        $objects = $this->jsonlObjects($contents, $label);

        if (! $allowAnyObject) {
            throw new InvalidArgumentException('Internal verifier misuse.');
        }

        return count($objects);
    }

    /** @param  array<mixed>  $payload */
    private function exactKeys(array $payload, array $expected, string $label): void
    {
        $actual = array_keys($payload);
        sort($actual);
        sort($expected);

        if ($actual !== $expected) {
            throw new InvalidArgumentException("The {$label} keys do not match the approved contract.");
        }
    }

    /** @param  array<string, mixed>  $payload */
    private function version(array $payload, string $expected, string $label): void
    {
        if (($payload['schema_version'] ?? null) !== $expected) {
            throw new InvalidArgumentException("The {$label} schema version is unsupported.");
        }
    }

    private function sha256(mixed $value, string $label): string
    {
        if (! is_string($value) || preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            throw new InvalidArgumentException("The {$label} must be a lowercase SHA-256 hash.");
        }

        return $value;
    }

    private function relativePath(mixed $value, string $label): string
    {
        if (! is_string($value) || ! RescueCorpusManifest::isSafeRelativePath($value)) {
            throw new InvalidArgumentException("The {$label} must be a safe relative path.");
        }

        return $value;
    }

    private function token(mixed $value, string $label, bool $allowLeadingUnderscore = false): string
    {
        $pattern = $allowLeadingUnderscore ? '/^_?[a-z][a-z0-9._-]{0,127}$/' : '/^[a-z][a-z0-9._-]{0,127}$/';

        if (! is_string($value) || preg_match($pattern, $value) !== 1) {
            throw new InvalidArgumentException("The {$label} is not a safe token.");
        }

        return $value;
    }

    private function nonEmptyString(mixed $value, string $label): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("The {$label} must be a non-empty string.");
        }

        return $value;
    }

    private function nullableString(mixed $value, string $label): ?string
    {
        return $value === null ? null : $this->nonEmptyString($value, $label);
    }

    private function nonNegativeInteger(mixed $value, string $label): int
    {
        if (! is_int($value) || $value < 0) {
            throw new InvalidArgumentException("The {$label} must be a non-negative integer.");
        }

        return $value;
    }

    private function nullableNonNegativeInteger(mixed $value, string $label): ?int
    {
        return $value === null ? null : $this->nonNegativeInteger($value, $label);
    }

    private function boolean(mixed $value, string $label): bool
    {
        if (! is_bool($value)) {
            throw new InvalidArgumentException("The {$label} must be a boolean.");
        }

        return $value;
    }

    private function findingCodes(mixed $codes, string $label): void
    {
        if (! is_array($codes) || ! array_is_list($codes)) {
            throw new InvalidArgumentException("The {$label} must be a list.");
        }

        foreach ($codes as $code) {
            if (! in_array($code, self::FindingCodes, true)) {
                throw new InvalidArgumentException("The {$label} contains an unsupported value.");
            }
        }
    }
}
