<?php

namespace App\Data\Application;

use Spatie\LaravelData\Data;

final class ApplicationDeclarationData extends Data
{
    /** @param array<string, mixed>|null $snapshot */
    public function __construct(
        public readonly string $page,
        public readonly string $semantics,
        public readonly string $state,
        public readonly ?string $declared_at,
        public readonly ?string $snapshot_hash,
        public readonly ?array $snapshot,
    ) {}
}
