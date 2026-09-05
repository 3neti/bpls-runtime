<?php

namespace App\Data\Application;

use Spatie\LaravelData\Data;

final class ApplicationAttachmentData extends Data
{
    public const Schema = 'bpls.application-attachment-data.v1';

    public function __construct(
        public readonly string $schema_version,
        public readonly string $key,
        public readonly int $sequence,
        public readonly string $label,
        public readonly string $short_label,
        public readonly string $document_kind,
        public readonly string $section,
        public readonly string $target,
        public readonly string $state,
        public readonly bool $available,
        public readonly string $tone,
    ) {}
}
