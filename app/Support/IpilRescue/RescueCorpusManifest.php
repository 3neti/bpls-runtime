<?php

namespace App\Support\IpilRescue;

use InvalidArgumentException;

final readonly class RescueCorpusManifest
{
    public const SchemaVersion = 'bpls.ipil-rescue-corpus.v1';

    /** @var list<string> */
    private const RequiredBindings = [
        'provenance/acquisition.json',
        'provenance/source-identities.jsonl',
        'provenance/tools.json',
        'source/database/schema.json',
        'source/database/table-manifest.json',
        'source/media/media-manifest.jsonl',
        'source/pricing/pricing-manifest.json',
        'source/pricing/records.jsonl',
        'verification/exceptions.jsonl',
    ];

    /**
     * @param  array{system: string, deployment_identity_sha256: string}  $source
     * @param  array{run_id: string, consistency_method: string}  $acquisition
     * @param  array{database_rows: int, media_metadata: int, media_bytes: int, pricing_records: int, exceptions: int}  $counts
     * @param  array<string, string>  $bindings
     */
    private function __construct(
        public string $corpusId,
        public array $source,
        public array $acquisition,
        public string $state,
        public ?string $parentCorpusId,
        public array $counts,
        public array $bindings,
    ) {}

    /** @param  array<string, mixed>  $payload */
    public static function fromArray(array $payload): self
    {
        self::assertExactKeys($payload, [
            'schema_version',
            'corpus_id',
            'source',
            'acquisition',
            'state',
            'parent_corpus_id',
            'counts',
            'bindings',
        ], 'corpus manifest');

        if (($payload['schema_version'] ?? null) !== self::SchemaVersion) {
            throw new InvalidArgumentException('The rescue corpus schema version is unsupported.');
        }

        $corpusId = self::safeId($payload['corpus_id'] ?? null, 'corpus_id');
        $parentCorpusId = $payload['parent_corpus_id'] ?? null;

        if ($parentCorpusId !== null) {
            $parentCorpusId = self::safeId($parentCorpusId, 'parent_corpus_id');
        }

        if (($payload['state'] ?? null) !== 'complete') {
            throw new InvalidArgumentException('The rescue corpus must be complete before it can be used.');
        }

        return new self(
            $corpusId,
            self::source($payload['source'] ?? null),
            self::acquisition($payload['acquisition'] ?? null),
            'complete',
            $parentCorpusId,
            self::counts($payload['counts'] ?? null),
            self::bindings($payload['bindings'] ?? null),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schema_version' => self::SchemaVersion,
            'corpus_id' => $this->corpusId,
            'source' => $this->source,
            'acquisition' => $this->acquisition,
            'state' => $this->state,
            'parent_corpus_id' => $this->parentCorpusId,
            'counts' => $this->counts,
            'bindings' => $this->bindings,
        ];
    }

    public function fingerprint(): string
    {
        return hash('sha256', CanonicalJson::encode($this->toArray()));
    }

    public static function isSafeRelativePath(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') || str_contains($path, "\0")) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    /** @return array{system: string, deployment_identity_sha256: string} */
    private static function source(mixed $source): array
    {
        if (! is_array($source)) {
            throw new InvalidArgumentException('The source manifest section must be an object.');
        }

        self::assertExactKeys($source, ['system', 'deployment_identity_sha256'], 'source');

        return [
            'system' => self::safeId($source['system'] ?? null, 'source.system'),
            'deployment_identity_sha256' => self::sha256($source['deployment_identity_sha256'] ?? null, 'source.deployment_identity_sha256'),
        ];
    }

    /** @return array{run_id: string, consistency_method: string} */
    private static function acquisition(mixed $acquisition): array
    {
        if (! is_array($acquisition)) {
            throw new InvalidArgumentException('The acquisition manifest section must be an object.');
        }

        self::assertExactKeys($acquisition, ['run_id', 'consistency_method'], 'acquisition');

        return [
            'run_id' => self::safeId($acquisition['run_id'] ?? null, 'acquisition.run_id'),
            'consistency_method' => self::nonEmptyString($acquisition['consistency_method'] ?? null, 'acquisition.consistency_method'),
        ];
    }

    /** @return array{database_rows: int, media_metadata: int, media_bytes: int, pricing_records: int, exceptions: int} */
    private static function counts(mixed $counts): array
    {
        if (! is_array($counts)) {
            throw new InvalidArgumentException('The counts manifest section must be an object.');
        }

        $keys = ['database_rows', 'media_metadata', 'media_bytes', 'pricing_records', 'exceptions'];
        self::assertExactKeys($counts, $keys, 'counts');
        $validated = [];

        foreach ($keys as $key) {
            $value = $counts[$key] ?? null;

            if (! is_int($value) || $value < 0) {
                throw new InvalidArgumentException("The {$key} count must be a non-negative integer.");
            }

            $validated[$key] = $value;
        }

        return $validated;
    }

    /** @return array<string, string> */
    private static function bindings(mixed $bindings): array
    {
        if (! is_array($bindings) || array_is_list($bindings)) {
            throw new InvalidArgumentException('Corpus bindings must be an object keyed by relative path.');
        }

        $validated = [];

        foreach ($bindings as $path => $checksum) {
            if (! is_string($path) || ! self::isSafeRelativePath($path)) {
                throw new InvalidArgumentException('A corpus binding contains an unsafe relative path.');
            }

            $validated[$path] = self::sha256($checksum, "bindings.{$path}");
        }

        ksort($validated);
        $missing = array_diff(self::RequiredBindings, array_keys($validated));

        if ($missing !== []) {
            throw new InvalidArgumentException('Corpus bindings do not include every required Rescue Corpus V1 manifest.');
        }

        return $validated;
    }

    private static function safeId(mixed $value, string $field): string
    {
        if (! is_string($value) || preg_match('/^[a-z0-9][a-z0-9._-]{2,127}$/', $value) !== 1) {
            throw new InvalidArgumentException("The {$field} value is not a safe identifier.");
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

    private static function nonEmptyString(mixed $value, string $field): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("The {$field} value must be a non-empty string.");
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $payload
     * @param  list<string>  $expected
     */
    private static function assertExactKeys(array $payload, array $expected, string $section): void
    {
        $actual = array_keys($payload);
        sort($actual);
        sort($expected);

        if ($actual !== $expected) {
            throw new InvalidArgumentException("The {$section} keys do not match the expected contract.");
        }
    }
}
