<?php

namespace App\LifecycleScenarios;

use Closure;
use RuntimeException;

final class ScenarioExternalDependencyProfile
{
    public const string XChangeUnconfigured = 'unconfigured';

    public const string XChangeConfigured = 'configured';

    /**
     * @template TResult
     *
     * @param  array<string, mixed>  $safety
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function run(array $safety, Closure $callback): mixed
    {
        $expectation = data_get($safety, 'external_dependency_expectations.x_change');

        if ($expectation === self::XChangeConfigured) {
            if (! $this->ambientXChangeIsConfigured()) {
                throw new RuntimeException('Lifecycle scenario explicitly requires configured XChange fixture settings, but the adapter configuration is incomplete.');
            }

            return $callback();
        }

        if ($expectation !== self::XChangeUnconfigured) {
            throw new RuntimeException('Lifecycle scenario must explicitly declare XChange as configured or unconfigured before using the fixture boundary.');
        }

        $configurationKeys = ['base_url', 'client_id', 'client_secret'];
        $original = collect($configurationKeys)
            ->mapWithKeys(fn (string $key): array => [$key => config("services.x_change.{$key}")])
            ->all();

        config()->set(collect($configurationKeys)
            ->mapWithKeys(fn (string $key): array => ["services.x_change.{$key}" => null])
            ->all());

        try {
            return $callback();
        } finally {
            config()->set(collect($original)
                ->mapWithKeys(fn (mixed $value, string $key): array => ["services.x_change.{$key}" => $value])
                ->all());
        }
    }

    /** @param array<string, mixed> $safety */
    public function expectsConfiguredXChange(array $safety): bool
    {
        return match (data_get($safety, 'external_dependency_expectations.x_change')) {
            self::XChangeConfigured => true,
            self::XChangeUnconfigured => false,
            default => throw new RuntimeException('Lifecycle scenario has no supported explicit XChange fixture expectation.'),
        };
    }

    private function ambientXChangeIsConfigured(): bool
    {
        return collect(['base_url', 'client_id', 'client_secret'])
            ->every(fn (string $key): bool => is_string(config("services.x_change.{$key}")) && config("services.x_change.{$key}") !== '');
    }
}
