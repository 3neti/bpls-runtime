<?php

namespace App\Contracts;

interface IpilCullSource
{
    /**
     * @return array{source_system: string, deployment_identity_sha256: string, source_engine: string, source_engine_version: string|null, source_schema_sha256: string, consistency_method: string, tool: array{name: string, version: string, sha256: string}}
     */
    public function preflight(): array;

    public function begin(string $workingDirectory): void;

    /**
     * @return list<array{name: string, identity_field: string, fields_sha256: string, relationships: list<array{field: string, target_dataset: string, cardinality: string, required: bool}>}>
     */
    public function datasets(): array;

    /**
     * @return array{rows: list<array{raw: string, value: array<string, mixed>}>, next_cursor: string|null}
     */
    public function databasePage(string $dataset, ?string $cursor, int $limit): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function mediaObjects(): array;

    /**
     * @param  array<string, mixed>  $object
     * @return array{status: string, bytes: string|null, attempts: int, error: string|null}
     */
    public function retrieveMedia(array $object, int $maximumAttempts): array;

    /**
     * @return array{manifest: array<string, mixed>, records: list<array<string, mixed>>}
     */
    public function pricingEvidence(): array;
}
