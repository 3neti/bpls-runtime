<?php

namespace App\Support\IpilRescue;

use InvalidArgumentException;

final readonly class SourceIdentity
{
    public const SchemaVersion = 'bpls.ipil-source-identity.v1';

    /** @var list<string> */
    private const MappingStates = [
        'observed',
        'inferred',
        'proposed',
        'accepted',
        'rehearsed',
        'production-applied',
    ];

    /**
     * @param  array<string, mixed>  $evidence
     * @param  array<string, mixed>|null  $targetReference
     * @param  list<string>  $flags
     */
    private function __construct(
        public string $sourceSystem,
        public string $deploymentIdentitySha256,
        public string $corpusId,
        public string $dataset,
        public string $sourceKeySha256,
        public string $canonicalPayloadSha256,
        public string $rawEvidenceLocator,
        public string $entityKind,
        public string $mappingState,
        public ?string $mapperVersion,
        public string $disposition,
        public array $evidence,
        public ?string $authority,
        public ?array $targetReference,
        public array $flags,
    ) {}

    /** @param  array<string, mixed>  $payload */
    public static function fromArray(array $payload): self
    {
        self::assertExactKeys($payload, [
            'schema_version',
            'source_system',
            'deployment_identity_sha256',
            'corpus_id',
            'dataset',
            'source_key_sha256',
            'canonical_payload_sha256',
            'raw_evidence_locator',
            'entity_kind',
            'mapping_state',
            'mapper_version',
            'disposition',
            'evidence',
            'authority',
            'target_reference',
            'flags',
        ]);

        if (($payload['schema_version'] ?? null) !== self::SchemaVersion) {
            throw new InvalidArgumentException('The source identity schema version is unsupported.');
        }

        $mappingState = self::string($payload['mapping_state'] ?? null, 'mapping_state');

        if (! in_array($mappingState, self::MappingStates, true)) {
            throw new InvalidArgumentException('The source identity mapping state is unsupported.');
        }

        $evidence = $payload['evidence'] ?? null;

        if (! is_array($evidence) || (array_is_list($evidence) && $evidence !== [])) {
            throw new InvalidArgumentException('Source identity evidence must be an object.');
        }

        $targetReference = $payload['target_reference'] ?? null;

        if ($targetReference !== null && (! is_array($targetReference) || array_is_list($targetReference))) {
            throw new InvalidArgumentException('Source identity target_reference must be an object or null.');
        }

        return new self(
            self::safeId($payload['source_system'] ?? null, 'source_system'),
            self::sha256($payload['deployment_identity_sha256'] ?? null, 'deployment_identity_sha256'),
            self::safeId($payload['corpus_id'] ?? null, 'corpus_id'),
            self::safeToken($payload['dataset'] ?? null, 'dataset'),
            self::sha256($payload['source_key_sha256'] ?? null, 'source_key_sha256'),
            self::sha256($payload['canonical_payload_sha256'] ?? null, 'canonical_payload_sha256'),
            self::safeLocator($payload['raw_evidence_locator'] ?? null),
            self::safeToken($payload['entity_kind'] ?? null, 'entity_kind'),
            $mappingState,
            self::nullableString($payload['mapper_version'] ?? null, 'mapper_version'),
            self::safeToken($payload['disposition'] ?? null, 'disposition'),
            $evidence,
            self::nullableString($payload['authority'] ?? null, 'authority'),
            $targetReference,
            self::flags($payload['flags'] ?? null),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schema_version' => self::SchemaVersion,
            'source_system' => $this->sourceSystem,
            'deployment_identity_sha256' => $this->deploymentIdentitySha256,
            'corpus_id' => $this->corpusId,
            'dataset' => $this->dataset,
            'source_key_sha256' => $this->sourceKeySha256,
            'canonical_payload_sha256' => $this->canonicalPayloadSha256,
            'raw_evidence_locator' => $this->rawEvidenceLocator,
            'entity_kind' => $this->entityKind,
            'mapping_state' => $this->mappingState,
            'mapper_version' => $this->mapperVersion,
            'disposition' => $this->disposition,
            'evidence' => $this->evidence,
            'authority' => $this->authority,
            'target_reference' => $this->targetReference,
            'flags' => $this->flags,
        ];
    }

    public function fingerprint(): string
    {
        return hash('sha256', CanonicalJson::encode($this->toArray()));
    }

    private static function safeLocator(mixed $value): string
    {
        if (! is_string($value) || ! RescueCorpusManifest::isSafeRelativePath($value)) {
            throw new InvalidArgumentException('The raw evidence locator must be a safe relative path.');
        }

        return $value;
    }

    private static function safeId(mixed $value, string $field): string
    {
        if (! is_string($value) || preg_match('/^[a-z0-9][a-z0-9._-]{2,127}$/', $value) !== 1) {
            throw new InvalidArgumentException("The {$field} value is not a safe identifier.");
        }

        return $value;
    }

    private static function safeToken(mixed $value, string $field): string
    {
        if (! is_string($value) || preg_match('/^[a-z][A-Za-z0-9._-]{0,127}$/', $value) !== 1) {
            throw new InvalidArgumentException("The {$field} value is not a safe token.");
        }

        return $value;
    }

    private static function sha256(mixed $value, string $field): string
    {
        if (! is_string($value) || preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            throw new InvalidArgumentException("The {$field} value must be a lowercase SHA-256 hash.");
        }

        return $value;
    }

    private static function string(mixed $value, string $field): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("The {$field} value must be a non-empty string.");
        }

        return $value;
    }

    private static function nullableString(mixed $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::string($value, $field);
    }

    /** @return list<string> */
    private static function flags(mixed $flags): array
    {
        if (! is_array($flags) || ! array_is_list($flags)) {
            throw new InvalidArgumentException('Source identity flags must be a list.');
        }

        foreach ($flags as $flag) {
            if (! is_string($flag) || preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $flag) !== 1) {
                throw new InvalidArgumentException('A source identity flag is invalid.');
            }
        }

        $flags = array_values(array_unique($flags));
        sort($flags);

        return $flags;
    }

    /**
     * @param  array<mixed>  $payload
     * @param  list<string>  $expected
     */
    private static function assertExactKeys(array $payload, array $expected): void
    {
        $actual = array_keys($payload);
        sort($actual);
        sort($expected);

        if ($actual !== $expected) {
            throw new InvalidArgumentException('The source identity keys do not match the expected contract.');
        }
    }
}
