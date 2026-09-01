<?php

namespace App\Actions;

use App\StakeholderPreview\StakeholderPreviewSafety;
use Illuminate\Support\Str;
use RuntimeException;

class AssertDisposableProductLabEnvironment
{
    public function __construct(private readonly StakeholderPreviewSafety $previewSafety) {}

    /** @return array<string, string> */
    public function handle(): array
    {
        if (app()->environment() !== 'local') {
            throw new RuntimeException('Product lab requires APP_ENV=local exactly.');
        }

        if (! $this->previewSafety->isEnabled()) {
            throw new RuntimeException('Product lab requires the synthetic-only stakeholder preview safety profile with production migration disabled and integrations disabled.');
        }

        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");

        if ($driver === 'sqlite') {
            $configuredPath = (string) config("database.connections.{$connection}.database");
            $databasePath = Str::startsWith($configuredPath, DIRECTORY_SEPARATOR)
                ? $configuredPath
                : base_path($configuredPath);
            $databaseDirectory = realpath(dirname($databasePath));
            $allowedDirectory = realpath(database_path());

            if ($databaseDirectory === false || $allowedDirectory === false
                || $databaseDirectory !== $allowedDirectory
                || basename($databasePath) !== 'database.sqlite'
                || is_link($databasePath)) {
                throw new RuntimeException('Product lab refused SQLite outside the repository database/database.sqlite disposable local target.');
            }

            return [
                'environment' => app()->environment(),
                'driver' => $driver,
                'database' => $databasePath,
                'safety_profile' => StakeholderPreviewSafety::Profile,
            ];
        }

        if ($driver === 'pgsql') {
            $host = (string) config("database.connections.{$connection}.host");
            $database = (string) config("database.connections.{$connection}.database");

            if (! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)
                || ! Str::is(['bpls_product_lab', 'bpls_product_lab_*'], $database)) {
                throw new RuntimeException('Product lab requires local PostgreSQL and a database named bpls_product_lab or bpls_product_lab_*.');
            }

            return [
                'environment' => app()->environment(),
                'driver' => $driver,
                'database' => $database,
                'safety_profile' => StakeholderPreviewSafety::Profile,
            ];
        }

        throw new RuntimeException("Product lab does not authorize destructive use of database driver [{$driver}].");
    }
}
