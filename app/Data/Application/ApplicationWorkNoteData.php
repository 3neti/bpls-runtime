<?php

namespace App\Data\Application;

use Spatie\LaravelData\Data;

final class ApplicationWorkNoteData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $actor_key,
        public readonly string $actor_label,
        public readonly string $instruction,
        public readonly string $section,
        public readonly string $anchor,
        public readonly string $state,
        public readonly string $state_label,
        public readonly string $tone,
        public readonly bool $actionable,
        public readonly ?string $action_label,
        public readonly ?string $action_url,
        public readonly ?string $completed_at,
        public readonly ?string $blocking_reason,
    ) {}
}
