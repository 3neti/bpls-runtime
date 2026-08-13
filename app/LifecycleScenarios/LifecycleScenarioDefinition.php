<?php

namespace App\LifecycleScenarios;

final readonly class LifecycleScenarioDefinition
{
    /**
     * @param  array<string, string>  $actors
     * @param  array<string, mixed>  $safety
     * @param  array<string, mixed>  $expectations
     * @param  array<string, array{width: int, height: int}>  $viewports
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $mode,
        public string $risk,
        public array $actors,
        public array $safety,
        public array $expectations,
        public array $viewports,
    ) {}
}
