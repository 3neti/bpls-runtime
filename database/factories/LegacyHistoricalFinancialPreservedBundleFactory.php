<?php

namespace Database\Factories;

use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyHistoricalFinancialPreservationExecution;
use App\Models\LegacyHistoricalFinancialPreservationProposal;
use App\Models\LegacyHistoricalFinancialPreservedBundle;
use App\Models\LegacyImportBatch;
use App\Models\LegacyRecord;
use App\Models\LegacySource;
use App\Models\PermitApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyHistoricalFinancialPreservedBundle>
 */
class LegacyHistoricalFinancialPreservedBundleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $snapshot = [
            'schema_version' => 'bpls.historical-financial-preservation-bundle.v1',
            'financial_history' => ['schedules' => [], 'totals' => []],
            'provenance' => ['fee_policy_provenance' => 'incomplete', 'future_policy_executable' => false],
        ];

        return [
            'legacy_historical_financial_preservation_execution_id' => LegacyHistoricalFinancialPreservationExecution::factory(),
            'legacy_historical_financial_preservation_proposal_id' => LegacyHistoricalFinancialPreservationProposal::factory(),
            'legacy_application_id_mapping_id' => LegacyApplicationIdMapping::factory(),
            'legacy_source_id' => LegacySource::factory(),
            'legacy_import_batch_id' => LegacyImportBatch::factory(),
            'legacy_record_id' => LegacyRecord::factory(),
            'permit_application_id' => PermitApplication::factory(),
            'source_projection_hash' => hash('sha256', fake()->uuid()),
            'bundle_snapshot_hash' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)),
            'status' => 'preserved',
            'mapping_basis' => 'accepted_exact_application_mapping',
            'snapshot' => $snapshot,
            'metadata' => ['fixture' => true, 'downstream_reference_count' => 0],
        ];
    }
}
