<?php

namespace App\Actions;

use Database\Seeders\NelsonConcernedOfficeFeeCatalogSeeder;
use Database\Seeders\RevenueCodeFeeCatalogSeeder;
use Illuminate\Support\Facades\Storage;

class InstallBplsBaseline
{
    public function __construct(
        private readonly RevenueCodeFeeCatalogSeeder $revenueCodeFeeCatalog,
        private readonly NelsonConcernedOfficeFeeCatalogSeeder $nelsonConcernedOfficeFeeCatalog,
        private readonly RetireEvaluatorUatPricingFixture $retireEvaluatorUatPricingFixture,
        private readonly EnsureBplsInstitution $ensureInstitution,
        private readonly ProvisionStakeholderPreviewPersonas $provisionPreviewPersonas,
        private readonly InspectBplsInstallation $inspectInstallation,
    ) {}

    /** @return array<string, mixed> */
    public function handle(): array
    {
        $this->revenueCodeFeeCatalog->run();
        $this->nelsonConcernedOfficeFeeCatalog->run();
        $retiredEvaluatorUatFeeRules = $this->retireEvaluatorUatPricingFixture->handle();
        $this->ensureInstitution->handle();
        $this->provisionPreviewPersonas->handle();

        $manifest = $this->inspectInstallation->handle();
        $manifest['evidence']['installed_at'] = now()->toIso8601String();
        $manifest['evidence']['retired_evaluator_uat_fee_rules'] = $retiredEvaluatorUatFeeRules;

        Storage::disk('local')->put(
            'private/bpls-installation/manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL,
        );

        return $manifest;
    }
}
