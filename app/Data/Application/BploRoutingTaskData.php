<?php

namespace App\Data\Application;

use Spatie\LaravelData\Data;

final class BploRoutingTaskData extends Data
{
    /**
     * @param  array<string, mixed>  $application
     * @param  array<string, mixed>|null  $routing
     * @param  array<string, mixed>|null  $suggestion
     * @param  list<array{code: string, label: string}>  $office_options
     * @param  array<string, mixed>  $financial_editor
     */
    public function __construct(
        public readonly string $schema_version,
        public readonly array $application,
        public readonly ?array $routing,
        public readonly ?array $suggestion,
        public readonly array $office_options,
        public readonly array $financial_editor,
        public readonly bool $can_determine,
        public readonly bool $manual_confirmation_required,
    ) {}
}
