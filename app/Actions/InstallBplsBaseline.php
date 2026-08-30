<?php

namespace App\Actions;

use Database\Seeders\RevenueCodeFeeCatalogSeeder;
use Illuminate\Support\Facades\Storage;

class InstallBplsBaseline
{
    public function __construct(
        private readonly RevenueCodeFeeCatalogSeeder $revenueCodeFeeCatalog,
        private readonly EnsureBplsInstitution $ensureInstitution,
        private readonly InspectBplsInstallation $inspectInstallation,
    ) {}

    /** @return array<string, mixed> */
    public function handle(): array
    {
        $this->revenueCodeFeeCatalog->run();
        $this->ensureInstitution->handle();

        $manifest = $this->inspectInstallation->handle();
        $manifest['evidence']['installed_at'] = now()->toIso8601String();

        Storage::disk('local')->put(
            'private/bpls-installation/manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL,
        );

        return $manifest;
    }
}
