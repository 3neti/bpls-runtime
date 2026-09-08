<?php

namespace Database\Seeders;

use App\Actions\ImportMunicipalFeeCatalog;
use Illuminate\Database\Seeder;

class MunicipalFeeCatalogSeeder extends Seeder
{
    public function __construct(private readonly ImportMunicipalFeeCatalog $importer) {}

    public function run(): void
    {
        $this->importer->handle();
    }
}
