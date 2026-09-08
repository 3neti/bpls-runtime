<?php

namespace App\Console\Commands;

use App\Actions\ImportMunicipalFeeCatalog;
use Illuminate\Console\Command;

class ImportMunicipalFeeCatalogCommand extends Command
{
    protected $signature = 'bpls:fees:import {path?}';

    protected $description = 'Import the versioned Municipality of Ipil fee catalogue';

    public function handle(ImportMunicipalFeeCatalog $importer): int
    {
        $result = $importer->handle($this->argument('path'));
        $this->table(['Item', 'Count'], collect($result)->map(fn (int|string $value, string $key): array => [$key, $value])->values());

        return self::SUCCESS;
    }
}
