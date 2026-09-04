<?php

namespace App\Data\Application;

use Spatie\LaravelData\Data;

final class ActorContextData extends Data
{
    /**
     * @param  list<array{key: string, label: string, section: string, href: ?string}>  $current_tasks
     * @param  list<array{key: string, label: string, section: string, href: ?string}>  $available_affordances
     */
    public function __construct(
        public readonly ?int $actor_id,
        public readonly string $actor_label,
        public readonly ?string $role_code,
        public readonly array $current_tasks,
        public readonly array $available_affordances,
    ) {}
}
