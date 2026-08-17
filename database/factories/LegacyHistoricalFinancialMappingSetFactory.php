<?php

namespace Database\Factories;

use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyHistoricalFinancialMappingSet;
use App\Models\LegacyImportBatch;
use App\Models\LegacyMappingPlan;
use App\Models\LegacySource;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LegacyHistoricalFinancialMappingSet> */
class LegacyHistoricalFinancialMappingSetFactory extends Factory
{
    protected $model = LegacyHistoricalFinancialMappingSet::class;

    public function definition(): array
    {
        return [
            'legacy_source_id' => LegacySource::factory(),
            'financial_import_batch_id' => LegacyImportBatch::factory(),
            'registry_import_batch_id' => LegacyImportBatch::factory(),
            'legacy_financial_mapping_plan_id' => LegacyFinancialMappingPlan::factory(),
            'legacy_mapping_plan_id' => LegacyMappingPlan::factory(),
            'run_reference' => fake()->unique()->slug(3),
            'cohort_sha256' => hash('sha256', fake()->uuid()),
            'proposal_package_sha256' => hash('sha256', fake()->uuid()),
            'status' => 'accepting',
            'cohort_size' => 5,
            'decision_authority' => 'Test authority',
            'evidence_reference' => 'TEST-EVIDENCE',
            'metadata' => [
                'production_rehearsal_authorized' => false,
            ],
        ];
    }
}
