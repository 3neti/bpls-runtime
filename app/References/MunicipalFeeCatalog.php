<?php

namespace App\References;

use Illuminate\Support\Arr;
use LogicException;
use Symfony\Component\Yaml\Yaml;

final class MunicipalFeeCatalog
{
    /** @return array<string, mixed> */
    public function read(?string $path = null): array
    {
        $catalogPath = $path ?? database_path('seeders/data/ipil_municipal_fee_catalog.v1.yaml');
        if (! is_file($catalogPath)) {
            throw new LogicException("Municipal fee catalogue not found at [{$catalogPath}].");
        }

        $contents = file_get_contents($catalogPath);
        if (! is_string($contents)) {
            throw new LogicException('Municipal fee catalogue could not be read.');
        }

        $catalog = Yaml::parse($contents);
        if (! is_array($catalog)) {
            throw new LogicException('Municipal fee catalogue must contain a YAML mapping.');
        }

        foreach (['schema_version', 'catalog.code', 'catalog.title', 'catalog.effective_from', 'business_divisions', 'lines_of_business', 'fee_categories', 'fees'] as $key) {
            if (! Arr::has($catalog, $key)) {
                throw new LogicException("Municipal fee catalogue is missing [{$key}].");
            }
        }

        if ((int) $catalog['schema_version'] !== 1) {
            throw new LogicException('Unsupported municipal fee catalogue schema version.');
        }

        return [
            ...$catalog,
            'source_sha256' => hash('sha256', $contents),
            'source_path' => $catalogPath,
        ];
    }
}
