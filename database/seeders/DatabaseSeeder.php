<?php

namespace Database\Seeders;

use App\Actions\EnsureBplsInstitution;
use App\Actions\InspectInstallationReadiness;
use App\Actions\ProvisionLifecycleLaboratoryActors;
use App\Actions\ProvisionStakeholderPreviewPersonas;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function __construct(
        private readonly EnsureBplsInstitution $ensureBplsInstitution,
        private readonly ProvisionLifecycleLaboratoryActors $provisionLaboratoryActors,
        private readonly ProvisionStakeholderPreviewPersonas $provisionPreviewPersonas,
        private readonly InspectInstallationReadiness $inspectReadiness,
    ) {}

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(MunicipalFeeCatalogSeeder::class);
        $this->call(NelsonConcernedOfficeFeeCatalogSeeder::class);
        $this->call(NelsonTreasuryLobFeeCatalogSeeder::class);
        $this->call(RevenueCodeFeeCatalogSeeder::class);

        $this->ensureBplsInstitution->handle();
        $this->provisionPreviewPersonas->handle();

        if (config('bpls_installation.seed_laboratory_actors') === true) {
            $this->provisionLaboratoryActors->handle();
        }

        $this->inspectReadiness->handle();
    }
}
