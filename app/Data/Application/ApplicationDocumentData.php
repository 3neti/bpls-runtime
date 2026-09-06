<?php

namespace App\Data\Application;

use Spatie\LaravelData\Data;

final class ApplicationDocumentData extends Data
{
    public function __construct(
        public readonly int $document_id,
        public readonly int $media_id,
        public readonly string $document_type,
        public readonly string $label,
        public readonly string $original_name,
        public readonly string $mime_type,
        public readonly int $size_bytes,
        public readonly string $checksum_sha256,
        public readonly int $version,
        public readonly int $uploaded_by_id,
        public readonly string $uploaded_at,
        public readonly ?string $remarks,
        public readonly string $semantic_classification = 'applicant_supplied_evidence',
    ) {}
}
