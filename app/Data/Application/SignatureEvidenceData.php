<?php

namespace App\Data\Application;

use Spatie\LaravelData\Data;

final class SignatureEvidenceData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly int $signer_id,
        public readonly string $purpose,
        public readonly string $signable_type,
        public readonly int $signable_id,
        public readonly string $captured_at,
        public readonly string $method,
        public readonly string $evidence_digest,
        public readonly int $media_id,
        public readonly string $legal_semantics = 'visual_facsimile_evidence_only',
    ) {}
}
